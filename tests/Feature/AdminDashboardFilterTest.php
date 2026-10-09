<?php

use App\Models\Booking;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->admin = User::factory()->create(['role' => 'super_admin']);
    $this->facility = Facility::create([
        'name' => 'Dashboard Filter Jail',
        'max_visitors_per_hour' => 20,
    ]);
    enableFacilitySchedule($this->facility);
});

function createDashboardFilterBooking(Facility $facility, array $overrides = []): Booking
{
    return Booking::create(array_merge([
        'reference_code' => 'BJMP-'.Str::upper(Str::random(8)),
        'facility_id' => $facility->id,
        'pdl_name' => 'Test PDL',
        'visitor_full_name' => 'Dashboard Visitor',
        'visitor_sex' => 'Male',
        'visitor_age' => 30,
        'visitor_contact' => '09123456789',
        'visitor_id_type' => 'Passport',
        'visitor_id_number' => 'P1234567',
        'relationship_to_pdl' => 'Sibling',
        'visit_date' => today()->addDays(3)->toDateString(),
        'time_slot' => '8:00 AM - 9:00 AM',
        'status' => 'Pending',
    ], $overrides));
}

test('dashboard loads without a date parameter', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertViewHas('summaryDate', fn ($date) => $date->toDateString() === today()->toDateString());
});

test('dashboard loads with an empty date parameter', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.dashboard', ['date' => '']))
        ->assertOk()
        ->assertViewHas('summaryDate', fn ($date) => $date->toDateString() === today()->toDateString());
});

test('dashboard loads with empty status and date parameters', function () {
    $booking = createDashboardFilterBooking($this->facility);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard', ['status' => '', 'date' => '']))
        ->assertOk()
        ->assertSee($booking->reference_code);
});

test('dashboard loads with an invalid date parameter', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.dashboard', ['date' => 'not-a-date']))
        ->assertOk()
        ->assertViewHas('summaryDate', fn ($date) => $date->toDateString() === today()->toDateString());
});

test('a valid date filter limits bookings to that visit date', function () {
    $filterDate = today()->addDays(10)->toDateString();
    $otherDate = today()->addDays(11)->toDateString();
    $matching = createDashboardFilterBooking($this->facility, ['visit_date' => $filterDate]);
    $other = createDashboardFilterBooking($this->facility, ['visit_date' => $otherDate]);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard', ['date' => $filterDate]))
        ->assertOk()
        ->assertSee($matching->reference_code)
        ->assertDontSee($other->reference_code)
        ->assertViewHas('summaryDate', fn ($date) => $date->toDateString() === $filterDate);
});

test('a valid status filter limits bookings to that status', function () {
    $pending = createDashboardFilterBooking($this->facility, ['status' => 'Pending']);
    $approved = createDashboardFilterBooking($this->facility, ['status' => 'Approved']);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard', ['status' => 'Approved']))
        ->assertOk()
        ->assertSee($approved->reference_code)
        ->assertDontSee($pending->reference_code);
});

test('an empty status parameter applies no status filter', function () {
    $pending = createDashboardFilterBooking($this->facility, ['status' => 'Pending']);
    $approved = createDashboardFilterBooking($this->facility, ['status' => 'Approved']);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard', ['status' => '']))
        ->assertOk()
        ->assertSee($pending->reference_code)
        ->assertSee($approved->reference_code);
});

test('dashboard totals and daily summary remain correct with empty filters', function () {
    createDashboardFilterBooking($this->facility, ['status' => 'Pending']);
    createDashboardFilterBooking($this->facility, ['status' => 'Approved']);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard', ['status' => '', 'date' => '']))
        ->assertOk()
        ->assertViewHas('analytics', fn (array $analytics) => $analytics['total'] === 2
            && $analytics['pending'] === 1
            && $analytics['approved'] === 1)
        ->assertViewHas('dailySummary', fn (array $summary) => $summary['total'] === 0);
});
