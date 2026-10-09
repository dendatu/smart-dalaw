<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Facility extends Model
{
    public const SLOT_DURATION_MINUTES = 60;

    protected $fillable = [
        'name',
        'max_visitors_per_hour',
        'regional_office',
        'jail_unit_name',
        'badge_image_path',
    ];

    public function pdls(): HasMany
    {
        return $this->hasMany(Pdl::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function admins(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(FacilitySchedule::class);
    }

    public function scopeVisibleToUser(Builder $query, ?User $user = null): Builder
    {
        if ($user === null || $user->isSuperAdmin()) {
            return $query;
        }

        return $query->whereKey($user->facility_id ?? 0);
    }

    /**
     * Public URL for the facility's configured badge/logo image, or null when
     * no badge is configured.
     *
     * Seeded badges (e.g. "regional-logos/ncr-logo.svg") ship with the
     * application under public/ and must be served with asset(). Admin-uploaded
     * badges are stored on the public storage disk and use its URL.
     */
    public function getBadgeUrlAttribute(): ?string
    {
        $path = $this->badge_image_path;

        if (blank($path)) {
            return null;
        }

        if (is_file(public_path($path))) {
            return asset($path);
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * The configured visitation periods for the weekday matching the given date.
     *
     * A day with no configured periods is treated as unavailable. Each period is
     * returned as an associative array with "start_time" and "end_time" keys in
     * 24-hour "H:i" format.
     *
     * @return array<array<string, string>>
     */
    public function periodsForDate(mixed $date): array
    {
        $weekday = static::weekdayNameFor($date);
        $periods = [];

        if (blank($weekday)) {
            return $periods;
        }

        foreach ($this->schedules()->where('day_of_week', $weekday)->orderBy('start_time')->get() as $period) {
            $startTime = static::normalizeTime($period->start_time);
            $endTime = static::normalizeTime($period->end_time);

            if ($startTime && $endTime && $endTime > $startTime) {
                $periods[] = ['start_time' => $startTime, 'end_time' => $endTime];
            }
        }

        return $periods;
    }

    /**
     * The selectable one-hour visitation slots for the given date, generated from
     * the facility's configured periods.
     */
    public function availableTimeSlotsForDate(mixed $date)
    {
        $slots = collect();

        foreach ($this->periodsForDate($date) as $period) {
            $periodStart = CarbonImmutable::parse('2000-01-01 '.$period['start_time']);
            $periodEnd = CarbonImmutable::parse('2000-01-01 '.$period['end_time']);
            $cursor = $periodStart;

            while ($cursor->addMinutes(self::SLOT_DURATION_MINUTES)->lte($periodEnd)) {
                $slotEnd = $cursor->addMinutes(self::SLOT_DURATION_MINUTES);

                $slots->push($cursor->format('g:i A').' - '.$slotEnd->format('g:i A'));

                $cursor = $slotEnd;
            }
        }

        return $slots;
    }

    /**
     * Whether the given formatted time slot (e.g. "1:00 PM - 2:00 PM") falls inside
     * one of the facility's configured visitation periods for the given date.
     */
    public function isTimeSlotAvailableOnDate(mixed $date, string $timeSlot): bool
    {
        $parsed = static::parseTimeSlot($timeSlot);

        if (! $parsed) {
            return false;
        }

        foreach ($this->periodsForDate($date) as $period) {
            if ($parsed['start_time'] >= $period['start_time'] && $parsed['end_time'] <= $period['end_time']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse a slash-delimited time slot label into normalized "H:i" bounds.
     *
     * @return ?array<string, string>
     */
    public static function parseTimeSlot(string $timeSlot): ?array
    {
        $parts = explode(' - ', $timeSlot);

        if (count($parts) !== 2) {
            return null;
        }

        $startTime = static::parseFormattedTime($parts[0]);
        $endTime = static::parseFormattedTime($parts[1]);

        if (! $startTime || ! $endTime || $endTime <= $startTime) {
            return null;
        }

        return ['start_time' => $startTime, 'end_time' => $endTime];
    }

    /**
     * Parse a "g:i A" style time (e.g. "8:00 AM", "1:00 PM") into "H:i". Returns
     * null when the value cannot be interpreted as a time.
     */
    public static function parseFormattedTime(string $time): ?string
    {
        try {
            return CarbonImmutable::parse('2000-01-01 '.trim($time))->format('H:i');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Normalize a database time value ("13:00:00" or "13:00") to "H:i".
     */
    public static function normalizeTime(?string $time): ?string
    {
        if (blank($time)) {
            return null;
        }

        try {
            return CarbonImmutable::parse('2000-01-01 '.$time)->format('H:i');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Resolve the full weekday name (e.g. "Monday") for the given date value.
     */
    public static function weekdayNameFor(mixed $date): ?string
    {
        try {
            $parsed = $date instanceof CarbonImmutable || $date instanceof Carbon
                ? $date
                : CarbonImmutable::parse((string) $date);

            return $parsed->format('l');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
