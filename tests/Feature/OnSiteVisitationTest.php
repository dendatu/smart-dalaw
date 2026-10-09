<?php

use App\Models\Booking;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

function createOnSiteFacility(): Facility
{
    $facility = Facility::create([
        'name' => 'On-Site Test Jail',
        'max_visitors_per_hour' => 50,
    ]);

    enableFacilitySchedule($facility);

    return $facility;
}

function onSitePayload(Facility $facility, array $overrides = []): array
{
    return array_merge([
        'facility_id' => $facility->id,
        'pdl_name' => 'Juan Dela Cruz',
        'visitor_full_name' => 'On-Site Visitor',
        'visitor_sex' => 'Male',
        'visitor_age' => 30,
        'visitor_contact' => '09123456789',
        'visitor_id_type' => 'Passport',
        'visitor_id_number' => 'P1234567',
        'relationship_to_pdl' => 'Spouse',
        'visit_date' => today()->toDateString(),
        'time_slot' => '8:00 AM - 9:00 AM',
    ], $overrides);
}

test('facility admin can record an on-site visitation that appears in booking records', function () {
    $facility = createOnSiteFacility();
    $admin = User::factory()->create([
        'role' => 'facility_admin',
        'facility_id' => $facility->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.visitations.store'), onSitePayload($facility))
        ->assertRedirect(route('admin.dashboard'));

    $booking = Booking::where('visitor_full_name', 'On-Site Visitor')->firstOrFail();

    expect($booking->source)->toBe(Booking::SOURCE_ON_SITE_REGISTRATION)
        ->and($booking->status)->toBe('Approved')
        ->and($booking->facility_id)->toBe($facility->id);
});

test('on-site visitation with accompanying visitors saves all visitors', function () {
    $facility = createOnSiteFacility();
    $admin = User::factory()->create([
        'role' => 'facility_admin',
        'facility_id' => $facility->id,
    ]);

    $payload = onSitePayload($facility, [
        'accompanying_visitors' => [
            [
                'full_name' => 'Maria Companion',
                'sex' => 'Female',
                'age' => 28,
                'id_type' => 'Passport',
                'id_number' => 'P2222222',
                'relationship_to_pdl' => 'Spouse',
            ],
        ],
    ]);

    $this->actingAs($admin)
        ->post(route('admin.visitations.store'), $payload)
        ->assertRedirect(route('admin.dashboard'));

    $booking = Booking::with('accompanyingVisitors')
        ->where('visitor_full_name', 'On-Site Visitor')
        ->firstOrFail();

    expect($booking->accompanyingVisitors)->toHaveCount(1)
        ->and($booking->accompanyingVisitors->first()->full_name)->toBe('Maria Companion');
});

test('on-site visitation is rejected when facility admin targets another facility', function () {
    $ownFacility = createOnSiteFacility();
    $otherFacility = Facility::create(['name' => 'Other Jail', 'max_visitors_per_hour' => 10]);
    enableFacilitySchedule($otherFacility);

    $admin = User::factory()->create([
        'role' => 'facility_admin',
        'facility_id' => $ownFacility->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.visitations.store'), onSitePayload($otherFacility))
        ->assertSessionHasErrors('facility_id');

    expect(Booking::count())->toBe(0);
});

test('unauthenticated users cannot create on-site visitations', function () {
    $facility = createOnSiteFacility();

    $this->post(route('admin.visitations.store'), onSitePayload($facility))
        ->assertRedirect(route('login'));

    expect(Booking::count())->toBe(0);
});

test('on-site visitation create page is accessible to facility admins', function () {
    $facility = createOnSiteFacility();
    $admin = User::factory()->create([
        'role' => 'facility_admin',
        'facility_id' => $facility->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.visitations.create'))
        ->assertOk()
        ->assertSeeText('Record on-site visitation');
});

test('on-site visitation records status history', function () {
    $facility = createOnSiteFacility();
    $admin = User::factory()->create([
        'role' => 'facility_admin',
        'facility_id' => $facility->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.visitations.store'), onSitePayload($facility))
        ->assertRedirect();

    $booking = Booking::where('visitor_full_name', 'On-Site Visitor')->firstOrFail();

    $this->assertDatabaseHas('booking_status_histories', [
        'booking_id' => $booking->id,
        'from_status' => null,
        'to_status' => 'Approved',
    ]);
});

test('on-site visitation visit date may be today or in the past', function () {
    $facility = createOnSiteFacility();
    $admin = User::factory()->create([
        'role' => 'facility_admin',
        'facility_id' => $facility->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.visitations.store'), onSitePayload($facility, [
            'visit_date' => today()->subDays(3)->toDateString(),
        ]))
        ->assertRedirect(route('admin.dashboard'));

    expect(Booking::count())->toBe(1);
});
