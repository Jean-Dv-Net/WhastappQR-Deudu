<?php

namespace App\Http\Controllers\Api\V1\Campaign\Statistics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Campaign\Statistics\GetCampaignStatisticsRequest;
use App\Models\CampaignStatistic;
use App\Models\Channel;
use App\Models\Campaign;
use App\Models\User;
use App\Services\QueryFilterService;
use Illuminate\Http\JsonResponse;
use MongoDB\BSON\ObjectId;

class GetCampaignStatisticsController extends Controller
{
    public function __invoke(
        GetCampaignStatisticsRequest $request,
        QueryFilterService $filterService
    ): JsonResponse
    {
        $filters = $request->getFilters();
        $coordinationIds = (array) $request->getValueByField('coordination_id');

        if (!empty($coordinationIds)) {
            $request->remove('coordination_id');
            $filters = $request->getFilters();

            // Extract date filters
            $dateFilters = (array) $request->getValueByField('created_at');

            //  1. Channels group by coordination id
            $channelsGrouped = Channel::whereIn('coordination_id', $coordinationIds)
                ->get(['id', 'coordination_id'])
                ->groupBy('coordination_id');

            $allChannelIds = $channelsGrouped
                ->flatten()
                ->pluck('id')
                ->map(fn($id) => new ObjectId($id))
                ->values()
                ->toArray();

            // 2. Campaigns with their count and last date (a single query)
            /** @var \Illuminate\Database\Eloquent\Builder|\MongoDB\Laravel\Eloquent\Builder $campaigns */
            $campaigns = Campaign::whereIn('channel_id', $allChannelIds)
                ->orderByDesc('created_at');
            
            if ($dateFilters) {
                $campaigns = $filterService
                    ->withCasts([
                        'created_at' => 'datetime'
                    ])
                    ->apply($campaigns, $filters);
            }
            
            $campaigns = $campaigns->get(['id', 'channel_id', 'created_at']);

            $allCampaignObjectIds = $campaigns
                ->pluck('id')
                ->map(fn($id) => new ObjectId($id))
                ->values()
                ->toArray();

            // 3. Filtered statistics (a single query)
            /** @var \Illuminate\Database\Eloquent\Builder|\MongoDB\Laravel\Eloquent\Builder $statisticsQuery */
            $statisticsQuery = CampaignStatistic::whereIn('campaign_id', $allCampaignObjectIds);
            $statistics = $filterService
                ->withCasts([
                    'created_at' => 'datetime'
                ])
                ->apply($statisticsQuery, $filters)
                ->get();

            // 4. Coordinations for the name (a single query)
            $coordinations = User::whereIn('id', $coordinationIds)
                ->get(['id', 'name'])
                ->keyBy('id');

            // 5. Group statistics by campaign_id in memory
            $statsGrouped = $statistics->groupBy(fn($stat) => (string) $stat->campaign_id);

            // 6. Map campaigns to their coordination_id via channel
            $channelToCoordination = $channelsGrouped
                ->flatMap(fn($channels, $coordinationId) =>
                    $channels->mapWithKeys(fn($channel) => [(string) $channel->id => $coordinationId])
                );

            // 7. Group campaigns by coordination_id in memory
            $campaignsGrouped = $campaigns->groupBy(
                fn($campaign) => $channelToCoordination[(string) $campaign->channel_id] ?? null
            );

            // 8. Build result by coordination
            $totals = ['pending' => 0, 'sent' => 0, 'delivered' => 0, 'read' => 0, 'failed' => 0, 'campaign_count' => 0];

            $coordinationsData = collect($coordinationIds)->map(function ($coordinationId) use (
                $coordinations, $campaignsGrouped, $statsGrouped, &$totals
            ) {
                $coordinationCampaigns = $campaignsGrouped->get($coordinationId, collect());
                $campaignIds = $coordinationCampaigns->pluck('id')->map(fn($id) => (string) $id);

                // Sum statistics of the campaigns of this coordination
                $stats = $campaignIds->flatMap(fn($id) => $statsGrouped->get($id, collect()));

                $pending   = $stats->sum('pending');
                $sent      = $stats->sum('sent');
                $delivered = $stats->sum('delivered');
                $read      = $stats->sum('read');
                $failed    = $stats->sum('failed');

                $totals['pending']          += $pending;
                $totals['sent']             += $sent;
                $totals['delivered']        += $delivered;
                $totals['read']             += $read;
                $totals['failed']           += $failed;
                $totals['campaign_count']   += $coordinationCampaigns->count();

                return [
                    'coordination_id'  => $coordinationId,
                    'coordination_name'   => $coordinations->get($coordinationId)?->name ?? 'N/A',
                    'campaign_count'   => $coordinationCampaigns->count(),
                    'last_campaign_at' => $coordinationCampaigns->first()?->created_at?->toDateTime(),
                    'pending'          => $pending,
                    'sent'             => $sent,
                    'delivered'        => $delivered,
                    'read'             => $read,
                    'failed'           => $failed,
                    'delivery_rate'    => $sent > 0 ? round(($delivered / $sent) * 100, 2) : 0,
                    'read_rate'        => $sent > 0 ? round(($read / $sent) * 100, 2) : 0,
                ];
            });

            $totals['delivery_rate'] = $totals['sent'] > 0
                ? round(($totals['delivered'] / $totals['sent']) * 100, 2) : 0;
            $totals['read_rate'] = $totals['sent'] > 0
                ? round(($totals['read'] / $totals['sent']) * 100, 2) : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'coordinations' => $coordinationsData,
                    'totals'        => $totals,
                ],
            ]);
        }

        // --- Flujo sin coordination_id (comportamiento original) ---
        $query = CampaignStatistic::query();

        $filterService->withCasts([
            'campaign_id' => 'object_id',
            'created_at' => 'datetime'
        ])->apply($query, $filters);

        return response()->json([
            'success' => true,
            'data'    => $query->get(),
        ]);
    }
}