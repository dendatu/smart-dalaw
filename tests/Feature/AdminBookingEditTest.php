<?php

use App\Models\Booking;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

function createEditTestFacility(): Facility
{
    $facility = Facility::create([
        'name' => 'Edit Test City Jail',
        'max_visitors_per_hour' => 10,
    ]);

    enableFacilitySchedule($facility);

    return $facility;
}

function createEditTestBooking(Facility $facility, array $attributes = []): Booking
{
    return Booking::create(array_merge([
        'reference_code' => 'BJMP-'.Str::upper(Str::random(8)),
        'facility_id' => $facility->id,
        'pdl_name' => 'Juan Dela Cruz',
        'visitor_full_name' => 'Sample Visitor',
        'visitor_sex' => 'Female',
        'visitor_age' => 30,
        'visitor_email' => 'visitor@example.com',
        'visitor_contact' => '09123456789',
        'visitor_id_type' => 'Passport',
        'visitor_id_number' => 'P1234567',
        'relationship_to_pdl' => 'Spouse',
        'visit_date' => today()->addDays(5)->toDateString(),
        'time_slot' => '8:00 AM - 9:00 AM',
        'status' => 'Pending',
    ], $attributes));
}

function editTestBookingPayload(Booking $booking, array $overrides = []): array
{
    return array_merge([
        'facility_id' => $booking->facility_id,
        'pdl_name' => $booking->pdl_name,
        'visitor_full_name' => $booking->visitor_full_name,
        'visitor_sex' => $booking->visitor_sex,
        'visitor_age' => $booking->visitor_age,
        'visitor_email' => $booking->visitor_email,
        'visitor_contact' => $booking->visitor_contact,
        'visitor_id_type' => $booking->visitor_id_type,
        'visitor_id_number' => $booking->visitor_id_number,
        'relationship_to_pdl' => $booking->relationship_to_pdl,
        'visit_date' => $booking->visit_date->toDateString(),
        'time_slot' => $booking->time_slot,
    ], $overrides);
}

test('admin edit booking form pre-selects the visitor sex saved on the booking', function () {
    $admin = User::factory()->create();
    $facility = createEditTestFacility();
    $booking = createEditTestBooking($facility, ['visitor_sex' => 'Female']);

    $this->actingAs($admin)
        ->get(route('admin.bookings.edit', $booking))
        ->assertOk()
        ->assertSee('value="Female" selected', false)
        ->assertDontSee('value="Male" selected', false);
});

test('visitor sex remains selected after saving the booking and reloading the form', function () {
    $admin = User::factory()->create();
    $facility = createEditTestFacility();
    $booking = createEditTestBooking($facility, ['visitor_sex' => 'Female']);

    $this->actingAs($admin)
        ->put(route('admin.bookings.update', $booking), editTestBookingPayload($booking))
        ->assertRedirect(route('admin.dashboard'));

    $this->actingAs($admin)
        ->get(route('admin.bookings.edit', $booking->fresh()))
        ->assertOk()
        ->assertSee('value="Female" selected', false);

    expect($booking->fresh()->visitor_sex)->toBe('Female');
});

test('admin edit page shows primary and accompanying visitors with their details', function () {
    $admin = User::factory()->create();
    $facility = createEditTestFacility();
    $booking = createEditTestBooking($facility, ['visitor_full_name' => 'Juanita Dela Cruz']);

    $booking->accompanyingVisitors()->createMany([
        [
            'full_name' => 'Maria Dela Cruz',
            'sex' => 'Female',
            'age' => 32,
            'id_type' => 'Passport',
            'id_number' => 'P2222222',
            'relationship_to_pdl' => 'Spouse',
        ],
        [
            'full_name' => 'Pedro Dela Cruz',
            'sex' => 'Male',
            'age' => 12,
            'id_type' => 'Passport',
            'id_number' => 'P3333333',
            'relationship_to_pdl' => 'Child',
        ],
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.bookings.edit', $booking))
        ->assertOk()
        ->assertSeeText('Primary Visitor')
        ->assertSeeText('Juanita Dela Cruz')
        ->assertSeeText('Accompanying Visitors')
        ->assertSeeText('Maria Dela Cruz')
        ->assertSeeText('Pedro Dela Cruz')
        ->assertSeeText('Female')
        ->assertSeeText('Male')
        ->assertSeeText('Primary visitor: 1')
        ->assertSeeText('Accompanying visitors: 2')
        ->assertSeeText('Total expected visitors: 3');

    // Each accompanying visitor must appear exactly once in the review list.
    expect(substr_count($response->getContent(), 'Maria Dela Cruz'))->toBe(1)
        ->and(substr_count($response->getContent(), 'Pedro Dela Cruz'))->toBe(1);
});

test('admin edit page shows none when a booking has no accompanying visitors', function () {
    $admin = User::factory()->create();
    $facility = createEditTestFacility();
    $booking = createEditTestBooking($facility);

    $this->actingAs($admin)
        ->get(route('admin.bookings.edit', $booking))
        ->assertOk()
        ->assertSeeText('Accompanying Visitors')
        ->assertSeeText('None');
});

test('retrieving a booking keeps its accompanying visitors intact', function () {
    $facility = createEditTestFacility();
    $booking = createEditTestBooking($facility);

    $booking->accompanyingVisitors()->create([
        'full_name' => 'Maria Dela Cruz',
        'sex' => 'Female',
        'age' => 32,
        'id_type' => 'Passport',
        'id_number' => 'P2222222',
        'relationship_to_pdl' => 'Spouse',
    ]);

    $reloaded = Booking::with('accompanyingVisitors')->findOrFail($booking->id);

    expect($reloaded->accompanyingVisitors)->toHaveCount(1)
        ->and($reloaded->accompanyingVisitors->first()->full_name)->toBe('Maria Dela Cruz');
});
