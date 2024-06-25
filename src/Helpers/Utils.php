<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\Servers\Helper;

use Carbon\Carbon;

class Utils
{
    /**
     * Formats a date
     *
     * @return string|null Formatted date, or null
     */
    public static function formatDate(?string $date, ?string $format = null, ?int $adjustHours = null): ?string
    {
        if (empty($date)) {
            return null;
        }

        $dateObject = Carbon::parse($date);

        if ($adjustHours !== null) {
            $dateObject->addHours((int) $adjustHours);
        }

        if (!is_null($format)) {
            return $dateObject->format($format);
        }

        return $dateObject->toDateTimeString();
    }
}
