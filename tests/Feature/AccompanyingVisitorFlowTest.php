<?php

use App\Models\AccompanyingVisitor;
use App\Models\Booking;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->facility = Facility::create([
        'name' => 'Accompanying Visitor Jail',
        'max_visitors_per_hour' => 20,
    ]);
    enableFacilitySchedule($this->facility);
});

function accompanyingVisitorBookingPayload(Facility $facility, array $overrides = []): array
{
    return array_merge([
        'facility_id' => $facility->id,
        'pdl_name' => 'Mico Deguito',
        'visitor_full_name' => 'Deneil Datu',
        'visitor_sex' => 'Male',
        'visitor_age' => 30,
        'visitor_contact' => '09123456789',
        'visitor_id_type' => 'Passport',
        'visitor_id_number' => 'P1234567',
        'relationship_to_pdl' => 'Sibling',
        'visit_date' => today()->addDays(4)->toDateString(),
        'time_slot' => '8:00 AM - 9:00 AM',
        'terms_accepted' => '1',
        'privacy_consent' => '1',
    ], $overrides);
}

function accompanyingVisitorEntry(string $name, array $overrides = []): array
{
    return array_merge([
        'full_name' => $name,
        'age' => 28,
        'sex' => 'Female',
        'id_type' => 'Passport',
        'id_number' => 'P'.Str::random(7),
        'relationship_to_pdl' => 'Spouse',
    ], $overrides);
}

test('a booking without accompanying visitors shows an empty state on the status page', function () {
    $this->post(route('bookings.store'), accompanyingVisitorBookingPayload($this->facility))
        ->assertSessionHasNoErrors();

    $booking = Booking::firstOrFail();

    expect($booking->accompanyingVisitors)->toHaveCount(0);

    $this->post(route('bookings.track.search'), [
        'reference_code' => $booking->reference_code,
    ])->assertOk()
        ->assertSeeText('Accompanying Visitors')
        ->assertSeeText('No accompanying visitors.')
        ->assertSeeText('Primary visitor')
        ->assertSeeText('Total expected visitors');
});

test('accompanying visitors submitted with a booking are persisted and shown on the status page', function () {
    $this->post(route('bookings.store'), accompanyingVisitorBookingPayload($this->facility, [
        'accompanying_visitors' => [
            accompanyingVisitorEntry('Maria Dela Cruz'),
            accompanyingVisitorEntry('Pedro Dela Cruz', ['sex' => 'Male', 'age' => 12]),
        ],
    ]))->assertSessionHasNoErrors();

    $booking = Booking::firstOrFail();

    expect($booking->accompanyingVisitors()->pluck('full_name')->all())
        ->toBe(['Maria Dela Cruz', 'Pedro Dela Cruz']);

    $this->post(route('bookings.track.search'), [
        'reference_code' => $booking->reference_code,
    ])->assertOk()
        ->assertSeeText('Maria Dela Cruz')
        ->assertSeeText('Pedro Dela Cruz')
        ->assertSeeText('Primary visitor')
        ->assertSeeText('Accompanying visitors')
        ->assertSeeText('Total expected visitors')
        ->assertDontSeeText('No accompanying visitors.');

    // Counts: primary 1, accompanying 2, total 3.
    $response = $this->post(route('bookings.track.search'), [
        'reference_code' => $booking->reference_code,
    ]);
    $response->assertSee('<dd class="mt-0.5 text-slate-900">3</dd>', false);
});

test('the booking success page shows accompanying visitors and counts', function () {
    $this->post(route('bookings.store'), accompanyingVisitorBookingPayload($this->facility, [
        'accompanying_visitors' => [
            accompanyingVisitorEntry('Maria Dela Cruz'),
            accompanyingVisitorEntry('Pedro Dela Cruz'),
        ],
    ]))->assertSessionHasNoErrors();

    $booking = Booking::firstOrFail();

    $this->get(route('booking.success', $booking->reference_code))
        ->assertOk()
        ->assertSeeText('Accompanying Visitors')
        ->assertSeeText('Maria Dela Cruz')
        ->assertSeeText('Pedro Dela Cruz')
        ->assertSeeText('Total expected visitors');
});

test('accompanying visitors survive a page refresh of the status page', function () {
    $this->post(route('bookings.store'), accompanyingVisitorBookingPayload($this->facility, [
        'accompanying_visitors' => [accompanyingVisitorEntry('Maria Dela Cruz')],
    ]))->assertSessionHasNoErrors();

    $booking = Booking::firstOrFail();

    foreach (range(1, 2) as $attempt) {
        $this->post(route('bookings.track.search'), [
            'reference_code' => $booking->reference_code,
        ])->assertOk()->assertSeeText('Maria Dela Cruz');
    }

    expect($booking->accompanyingVisitors()->count())->toBe(1);
});

test('an invalid accompanying visitor entry rejects the booking without partial saves', function () {
    $this->post(route('bookings.store'), accompanyingVisitorBookingPayload($this->facility, [
        'accompanying_visitors' => [
            accompanyingVisitorEntry('Maria Dela Cruz'),
            accompanyingVisitorEntry('', ['age' => 0]),
        ],
    ]))->assertSessionHasErrors([
        'accompanying_visitors.1.full_name',
        'accompanying_visitors.1.age',
    ]);

    expect(Booking::count())->toBe(0)
        ->and(AccompanyingVisitor::count())->toBe(0);
});

test('the admin booking edit page shows accompanying visitors and total expected count', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);
    $booking = Booking::create([
        'reference_code' => 'BJMP-'.Str::upper(Str::random(8)),
        'facility_id' => $this->facility->id,
        'pdl_name' => 'Mico Deguito',
        'visitor_full_name' => 'Deneil Datu',
        'visitor_sex' => 'Male',
        'visitor_age' => 30,
        'visitor_contact' => '09123456789',
        'visitor_id_type' => 'Passport',
        'visitor_id_number' => 'P1234567',
        'relationship_to_pdl' => 'Sibling',
        'visit_date' => today()->addDays(4)->toDateString(),
        'time_slot' => '8:00 AM - 9:00 AM',
        'status' => 'Pending',
    ]);
    $booking->accompanyingVisitors()->createMany([
        accompanyingVisitorEntry('Maria Dela Cruz'),
        accompanyingVisitorEntry('Pedro Dela Cruz'),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.bookings.edit', $booking))
        ->assertOk()
        ->assertSeeText('Primary Visitor')
        ->assertSeeText('Deneil Datu')
        ->assertSeeText('Accompanying Visitors')
        ->assertSeeText('Maria Dela Cruz')
        ->assertSeeText('Pedro Dela Cruz')
        ->assertSeeText('Total expected visitors: 3');

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSeeText('Total expected visitors: 3');
});

test('updating a booking does not duplicate or erase accompanying visitors', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);
    $booking = Booking::create([
        'reference_code' => 'BJMP-'.Str::upper(Str::random(8)),
        'facility_id' => $this->facility->id,
        'pdl_name' => 'Mico Deguito',
        'visitor_full_name' => 'Deneil Datu',
        'visitor_sex' => 'Male',
        'visitor_age' => 30,
        'visitor_contact' => '09123456789',
        'visitor_id_type' => 'Passport',
        'visitor_id_number' => 'P1234567',
        'relationship_to_pdl' => 'Sibling',
        'visit_date' => today()->addDays(4)->toDateString(),
        'time_slot' => '8:00 AM - 9:00 AM',
        'status' => 'Pending',
    ]);
    $booking->accompanyingVisitors()->create(accompanyingVisitorEntry('Maria Dela Cruz'));

    $this->actingAs($admin)
        ->put(route('admin.bookings.update', $booking), [
            'facility_id' => $this->facility->id,
            'pdl_name' => $booking->pdl_name,
            'visitor_full_name' => 'Deneil Datu Updated',
            'visitor_contact' => '09123456789',
            'visitor_sex' => 'Male',
            'visitor_age' => 31,
            'visitor_id_type' => 'Passport',
            'visitor_id_number' => 'P1234567',
            'relationship_to_pdl' => 'Sibling',
            'visit_date' => $booking->visit_date->toDateString(),
            'time_slot' => $booking->time_slot,
        ])
        ->assertRedirect(route('admin.dashboard'));

    $booking->refresh();

    expect($booking->visitor_full_name)->toBe('Deneil Datu Updated')
        ->and($booking->accompanyingVisitors()->count())->toBe(1)
        ->and($booking->accompanyingVisitors()->first()->full_name)->toBe('Maria Dela Cruz')
        ->and($booking->status)->toBe('Pending');
});

test('the daily summary counts every person for a booking with accompanying visitors', function () {
    $booking = Booking::create([
        'reference_code' => 'BJMP-'.Str::upper(Str::random(8)),
        'facility_id' => $this->facility->id,
        'pdl_name' => 'Mico Deguito',
        'visitor_full_name' => 'Deneil Datu',
        'visitor_sex' => 'Male',
        'visitor_age' => 30,
        'visitor_contact' => '09123456789',
        'visitor_id_type' => 'Passport',
        'visitor_id_number' => 'P1234567',
        'relationship_to_pdl' => 'Sibling',
        'visit_date' => today()->toDateString(),
        'time_slot' => '8:00 AM - 9:00 AM',
        'status' => 'Pending',
    ]);
    $booking->accompanyingVisitors()->createMany([
        accompanyingVisitorEntry('Maria Dela Cruz'),
        accompanyingVisitorEntry('Pedro Dela Cruz'),
    ]);

    $summary = Booking::dailyVisitationSummaryFor(today()->toDateString());

    expect($summary['total'])->toBe(3);
});
