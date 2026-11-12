<?php

use App\Models\Booking;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->facility = Facility::create(['name' => 'Range Test Facility', 'max_visitors_per_hour' => 20]);
});

function createRangeBooking(Facility $facility, string $date, array $attributes = []): Booking
{
    static $sequence = 0;
    $sequence++;

    return Booking::create(array_merge([
        'facility_id' => $facility->id,
        'reference_code' => 'BJMP-RNG'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
        'pdl_name' => 'Juan Dela Cruz',
        'visitor_full_name' => 'Visitor '.$sequence,
        'visitor_sex' => 'Female',
        'visitor_age' => 30,
        'visitor_contact' => '09123456789',
        'visitor_id_type' => 'Passport',
        'visitor_id_number' => 'P'.$sequence,
        'relationship_to_pdl' => 'Spouse',
        'visit_date' => $date,
        'time_slot' => '8:00 AM - 9:00 AM',
        'status' => 'Approved',
    ], $attributes));
}

test('summary covers every approved booking within the selected date range', function () {
    createRangeBooking($this->facility, '2026-09-30');
    createRangeBooking($this->facility, '2026-10-01', ['visitor_is_pwd' => true]);
    createRangeBooking($this->facility, '2026-10-08', ['visitor_sex' => 'Male', 'visitor_age' => 65]);
    createRangeBooking($this->facility, '2026-10-15', ['visitor_is_pregnant' => true]);
    createRangeBooking($this->facility, '2026-10-10', ['status' => 'Pending']);
    createRangeBooking($this->facility, '2026-10-16');

    $this->actingAs(User::factory()->create())
        ->get(route('admin.daily-summary', ['from' => '2026-10-01', 'to' => '2026-10-15']))
        ->assertOk()
        ->assertSeeText('October 01, 2026 – October 15, 2026')
        ->assertSeeText('Breakdown by Date')
        ->assertViewHas('bookings', fn ($bookings) => $bookings->pluck('visit_date')->map->toDateString()->all() === ['2026-10-01', '2026-10-08', '2026-10-15'])
        ->assertViewHas('summary', fn (array $summary): bool => $summary['total_visitors'] === 3
            && $summary['by_sex'] === ['Male' => 1, 'Female' => 2]
            && $summary['by_age']['senior'] === 1
            && $summary['pwd'] === 1
            && $summary['pregnant'] === 1
            && array_keys($summary['by_date']) === ['2026-10-01', '2026-10-08', '2026-10-15']);
});

test('single date parameter still shows a one-day summary', function () {
    createRangeBooking($this->facility, '2026-10-01');
    createRangeBooking($this->facility, '2026-10-02');

    $this->actingAs(User::factory()->create())
        ->get(route('admin.daily-summary', ['date' => '2026-10-01']))
        ->assertOk()
        ->assertDontSeeText('Breakdown by Date')
        ->assertViewHas('summary', fn (array $summary): bool => $summary['from'] === '2026-10-01'
            && $summary['to'] === '2026-10-01'
            && $summary['total_visitors'] === 1);
});

test('end date cannot be before the start date', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.daily-summary', ['from' => '2026-10-15', 'to' => '2026-10-01']))
        ->assertSessionHasErrors('to');
});
