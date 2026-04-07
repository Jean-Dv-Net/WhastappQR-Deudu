<?php

namespace App\Trait;

trait FormatsDates
{
    protected function formatDateArray($value): ?array
    {
        if (!$value) return null;

        $date = $value instanceof \DateTimeInterface
            ? $value
            : \Carbon\Carbon::parse($value);

        return [
            'date' => $date->format('Y-m-d H:i:s.u'),
            'timezone_type' => 3,
            'timezone' => $date->getTimezone()->getName(),
        ];
    }

    protected function formatDate($value): ?string
    {
        if (!$value) return null;

        $date = $value instanceof \DateTimeInterface
            ? $value
            : \Carbon\Carbon::parse($value);

        return $date->format('Y-m-d H:i:s.u');
    }
}
