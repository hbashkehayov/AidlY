<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonPeriod;

class BusinessHoursService
{
    private $businessDays;
    private $startHour;
    private $endHour;
    private $timezone;

    public function __construct()
    {
        // Load from config/env or use defaults
        $this->businessDays = explode(',', env('BUSINESS_DAYS', '1,2,3,4,5')); // Mon-Fri
        $this->startHour = env('BUSINESS_HOURS_START', '09:00');
        $this->endHour = env('BUSINESS_HOURS_END', '18:00');
        $this->timezone = env('DEFAULT_TIMEZONE', 'UTC');
    }

    /**
     * Calculate business hours between two timestamps
     *
     * @param Carbon $start
     * @param Carbon $end
     * @return float Hours in business time
     */
    public function calculateBusinessHours(Carbon $start, Carbon $end): float
    {
        if ($start->gte($end)) {
            return 0;
        }

        $start = $start->copy()->setTimezone($this->timezone);
        $end = $end->copy()->setTimezone($this->timezone);

        $totalMinutes = 0;
        $current = $start->copy();

        // Parse business hours
        [$startHour, $startMinute] = explode(':', $this->startHour);
        [$endHour, $endMinute] = explode(':', $this->endHour);

        while ($current->lt($end)) {
            // Check if current day is a business day
            if (in_array($current->dayOfWeek, $this->businessDays)) {
                // Set business day boundaries
                $dayStart = $current->copy()->setTime((int)$startHour, (int)$startMinute, 0);
                $dayEnd = $current->copy()->setTime((int)$endHour, (int)$endMinute, 0);

                // Adjust if we're starting mid-day
                if ($current->eq($start) && $current->gt($dayStart)) {
                    $dayStart = $current->copy();
                }

                // Adjust if we're ending mid-day
                if ($current->isSameDay($end) && $end->lt($dayEnd)) {
                    $dayEnd = $end->copy();
                }

                // Add minutes only if within business hours
                if ($dayStart->lt($dayEnd)) {
                    $totalMinutes += $dayStart->diffInMinutes($dayEnd);
                }
            }

            // Move to next day
            $current->addDay()->setTime((int)$startHour, (int)$startMinute, 0);
        }

        return round($totalMinutes / 60, 2);
    }

    /**
     * Calculate response time in business hours for a ticket
     *
     * @param string|Carbon $createdAt
     * @param string|Carbon $respondedAt
     * @return float
     */
    public function calculateResponseTime($createdAt, $respondedAt): float
    {
        $start = $createdAt instanceof Carbon ? $createdAt : Carbon::parse($createdAt);
        $end = $respondedAt instanceof Carbon ? $respondedAt : Carbon::parse($respondedAt);

        return $this->calculateBusinessHours($start, $end);
    }

    /**
     * Check if current time is within business hours
     *
     * @param Carbon|null $time
     * @return bool
     */
    public function isBusinessHours(?Carbon $time = null): bool
    {
        $time = $time ?? Carbon::now($this->timezone);

        // Check if it's a business day
        if (!in_array($time->dayOfWeek, $this->businessDays)) {
            return false;
        }

        [$startHour, $startMinute] = explode(':', $this->startHour);
        [$endHour, $endMinute] = explode(':', $this->endHour);

        $businessStart = $time->copy()->setTime((int)$startHour, (int)$startMinute, 0);
        $businessEnd = $time->copy()->setTime((int)$endHour, (int)$endMinute, 0);

        return $time->gte($businessStart) && $time->lte($businessEnd);
    }

    /**
     * Get next business hours start time
     *
     * @param Carbon|null $from
     * @return Carbon
     */
    public function getNextBusinessHoursStart(?Carbon $from = null): Carbon
    {
        $current = ($from ?? Carbon::now($this->timezone))->copy();

        [$startHour, $startMinute] = explode(':', $this->startHour);

        // If we're already in business hours, return current time
        if ($this->isBusinessHours($current)) {
            return $current;
        }

        // Check today first
        if (in_array($current->dayOfWeek, $this->businessDays)) {
            $businessStart = $current->copy()->setTime((int)$startHour, (int)$startMinute, 0);
            if ($current->lt($businessStart)) {
                return $businessStart;
            }
        }

        // Find next business day
        for ($i = 1; $i <= 7; $i++) {
            $next = $current->copy()->addDays($i);
            if (in_array($next->dayOfWeek, $this->businessDays)) {
                return $next->setTime((int)$startHour, (int)$startMinute, 0);
            }
        }

        // Fallback (should never reach here)
        return $current->addDay()->setTime((int)$startHour, (int)$startMinute, 0);
    }
}
