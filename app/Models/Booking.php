<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    public const SOURCE_ONLINE_BOOKING = 'Online Booking';

    public const SOURCE_ON_SITE_REGISTRATION = 'On-Site Registration';

    protected $fillable = [
        'reference_code',
        'source',
        'facility_id',
        'pdl_name',
        'visitor_full_name',
        'visitor_sex',
        'visitor_age',
        'visitor_email',
        'visitor_contact',
        'visitor_id_type',
        'visitor_id_number',
        'relationship_to_pdl',
        'visit_date',
        'time_slot',
        'status',
        'admin_remarks',
        'terms_accepted',
        'terms_accepted_at',
        'privacy_consent',
        'privacy_consent_at',
        'privacy_notice_version',
    ];

    protected $casts = [
        'visit_date' => 'date',
        'visitor_age' => 'integer',
        'terms_accepted' => 'boolean',
        'terms_accepted_at' => 'datetime',
        'privacy_consent' => 'boolean',
        'privacy_consent_at' => 'datetime',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function accompanyingVisitors(): HasMany
    {
        return $this->hasMany(AccompanyingVisitor::class);
    }

    public function getPdlFullNameAttribute(): string
    {
        return $this->pdl_name ?? 'N/A';
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(BookingStatusHistory::class)
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function scopeVisibleToUser(Builder $query, ?User $user = null): Builder
    {
        if ($user === null || $user->isSuperAdmin()) {
            return $query;
        }

        return $query->where('facility_id', $user->facility_id ?? 0);
    }

    /**
     * Classify an age into a visitation age group. The system stores the visitor's
     * age (snapshot), so classification uses that stored age directly.
     */
    public static function ageGroupForAge(?int $age): string
    {
        if ($age === null || $age < 1) {
            return 'Unknown';
        }

        if ($age < 18) {
            return 'Minor';
        }

        if ($age <= 59) {
            return 'Adult';
        }

        return 'Senior';
    }

    /**
     * Daily visitation summary for a date, counting every person attending
     * (primary + accompanying visitors), segmented by sex and age group.
     *
     * The summary only covers visitations that were not rejected, so expected
     * pending and approved bookings for the date are included while rejected
     * ones are excluded. Bookings are reported separately from the people
     * attending so one appointment with several visitors is not counted as
     * several bookings.
     *
     * @return array<string, mixed>
     */
    public static function dailyVisitationSummaryFor(mixed $date, ?User $user = null): array
    {
        $bookings = Booking::visibleToUser($user)
            ->with('accompanyingVisitors')
            ->whereDate('visit_date', $date)
            ->where('status', '!=', 'Rejected')
            ->get();

        $summary = [
            'total' => 0,
            'bookings' => 0,
            'primary_visitors' => 0,
            'accompanying_visitors' => 0,
            'sex' => ['Male' => 0, 'Female' => 0, 'Unknown' => 0],
            'age_group' => ['Minor' => 0, 'Adult' => 0, 'Senior' => 0, 'Unknown' => 0],
        ];

        foreach ($bookings as $booking) {
            $summary['bookings']++;
            $summary['primary_visitors']++;
            $summary = static::countVisitorInSummary($summary, $booking->visitor_sex, $booking->visitor_age);

            foreach ($booking->accompanyingVisitors as $visitor) {
                $summary['accompanying_visitors']++;
                $summary = static::countVisitorInSummary($summary, $visitor->sex, $visitor->age);
            }
        }

        return $summary;
    }

    private static function countVisitorInSummary(array $summary, ?string $sex, ?int $age): array
    {
        $sexKey = ($sex === 'Male' || $sex === 'Female') ? $sex : 'Unknown';
        $ageGroup = static::ageGroupForAge($age);

        $sexCounts = $summary['sex'];
        $ageCounts = $summary['age_group'];

        $sexCounts[$sexKey]++;
        $ageCounts[$ageGroup]++;

        $summary['sex'] = $sexCounts;
        $summary['age_group'] = $ageCounts;
        $summary['total']++;

        return $summary;
    }
}
