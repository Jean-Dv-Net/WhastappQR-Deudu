<?php

namespace App\Services;

use App\ValueObjects\Filter;
use App\ValueObjects\FilterCollection;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Laravel\Eloquent\Builder as EloquentBuilder;

use function is_array;

class QueryFilterService
{
    /**
     * @var array<string, string> Field casting rules
     */
    protected array $fieldCasts = [];

    /**
     * Set field casting rules for the filters.
     * 
     * @param array<string, string> $casts
     * @return self
     */
    public function withCasts(array $casts): self
    {
        $this->fieldCasts = $casts;
        return $this;
    }

    /**
     * Apply filters to an Eloquent query builder.
     *
     * @param Builder|EloquentBuilder $query The Eloquent query builder
     * @param FilterCollection $filters The collection of filters to apply
     * @return Builder|EloquentBuilder The modified query builder
     */
    public function apply(Builder|EloquentBuilder $query, FilterCollection $filters): Builder|EloquentBuilder
    {
        if ($filters->isEmpty()) {
            return $query;
        }

        foreach ($filters->all() as $filter) {
            $this->applyFilter($query, $filter);
        }

        return $query;
    }

    /**
     * Apply a single filter to the query.
     *
     * @param Builder $query
     * @param Filter $filter
     * @return void
     */
    protected function applyFilter(Builder $query, Filter $filter): void
    {
        $field = $filter->getField();
        $operator = $filter->getOperator();
        $value = $filter->getValue();

        if (isset($this->fieldCasts[$field]) && $this->fieldCasts[$field] === 'object_id' && $value !== null) {
            if (is_array($value)) {
                $value = array_map(fn($id) => $id instanceof \MongoDB\BSON\ObjectId ? $id : new \MongoDB\BSON\ObjectId($id), $value);
            } else {
                $value = $value instanceof \MongoDB\BSON\ObjectId ? $value : new \MongoDB\BSON\ObjectId($value);
            }
        }

        // Cast datetime
        if (isset($this->fieldCasts[$field]) && $this->fieldCasts[$field] === 'datetime' && $value !== null) {
            if (is_array($value)) {
                $value = array_map(
                    fn($v) => new UTCDateTime(
                        Carbon::parse($v, config('app.timezone'))
                            ->utc()
                            ->getTimestampMs()
                    ),
                    $value
                );
            } else {
                $value = new UTCDateTime(
                    Carbon::parse($value, config('app.timezone'))
                        ->utc()
                        ->getTimestampMs()
                );
            }
        }

        match ($operator) {
            Filter::OPERATOR_EQUAL => $query->where($field, '=', $value),
            Filter::OPERATOR_NOT_EQUAL => $query->where($field, '!=', $value),
            Filter::OPERATOR_LIKE => $query->where($field, 'LIKE', $value),
            Filter::OPERATOR_NOT_LIKE => $query->where($field, 'NOT LIKE', $value),
            Filter::OPERATOR_IN => $this->applyInFilter($query, $field, $value),
            Filter::OPERATOR_NOT_IN => $this->applyNotInFilter($query, $field, $value),
            Filter::OPERATOR_GREATER_THAN => $query->where($field, '>', $value),
            Filter::OPERATOR_GREATER_THAN_OR_EQUAL => $query->where($field, '>=', $value),
            Filter::OPERATOR_LESS_THAN => $query->where($field, '<', $value),
            Filter::OPERATOR_LESS_THAN_OR_EQUAL => $query->where($field, '<=', $value),
            Filter::OPERATOR_IS_NULL => $query->whereNull($field),
            Filter::OPERATOR_IS_NOT_NULL => $query->whereNotNull($field),
            Filter::OPERATOR_BETWEEN => $this->applyBetweenFilter($query, $field, $value),
            default => throw new InvalidArgumentException("Unsupported operator: {$operator}")
        };
    }

    /**
     * Apply IN filter.
     *
     * @param Builder $query
     * @param string $field
     * @param mixed $value
     * @return void
     */
    protected function applyInFilter(Builder $query, string $field, mixed $value): void
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException(
                "IN operator requires an array value for field: {$field}"
            );
        }

        $query->whereIn($field, $value);
    }

    /**
     * Apply NOT IN filter.
     *
     * @param Builder $query
     * @param string $field
     * @param mixed $value
     * @return void
     */
    protected function applyNotInFilter(Builder $query, string $field, mixed $value): void
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException(
                "NOT_IN operator requires an array value for field: {$field}"
            );
        }

        $query->whereNotIn($field, $value);
    }

    /**
     * Apply BETWEEN filter.
     *
     * @param Builder $query
     * @param string $field
     * @param mixed $value
     * @return void
     */
    protected function applyBetweenFilter(Builder $query, string $field, mixed $value): void
    {
        if (!is_array($value) || count($value) !== 2) {
            throw new InvalidArgumentException(
                "BETWEEN operator requires an array with exactly 2 values for field: {$field}"
            );
        }

        $query->whereBetween($field, $value);
    }
}
