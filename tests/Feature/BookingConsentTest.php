<?php

use App\Models\Booking;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

function createConsentFacility(): array
{
    $facility = Facility::create([
        'name' => 'Consent Test Facility',
        'max_visitors_per_hour' => 20,
    ]);

    enableFacilitySchedule($facility);

    return [$facility];
}

test('booking cannot be submitted unless both agreements are accepted', function () {
    [$facility] = createConsentFacility();

    $this->post(route('bookings.store'), [
        'facility_id' => $facility->id,
        'pdl_name' => 'Juan Dela Cruz',
        'visitor_full_name' => 'Visitor Name',
        'visitor_contact' => '09123456789',
        'visitor_id_type' => 'Passport',
        'visitor_id_number' => 'P1234567',
        'relationship_to_pdl' => 'Sibling',
        'visit_date' => today()->addDays(4)->toDateString(),
        'time_slot' => '8:00 AM - 9:00 AM',
    ])->assertSessionHasErrors(['terms_accepted', 'privacy_consent']);

    expect(Booking::count())->toBe(0);
});

test('booking stores consent status timestamps and privacy notice version', function () {
    [$facility] = createConsentFacility();

    $this->post(route('bookings.store'), [
        'facility_id' => $facility->id,
        'pdl_name' => 'Juan Dela Cruz',
        'visitor_full_name' => 'Visitor Name',
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
    ])->assertRedirect();

    $booking = Booking::where('visitor_full_name', 'Visitor Name')->firstOrFail();

    expect($booking->terms_accepted)->toBeTrue()
        ->and($booking->privacy_consent)->toBeTrue()
        ->and($booking->terms_accepted_at)->not->toBeNull()
        ->and($booking->privacy_consent_at)->not->toBeNull()
        ->and($booking->privacy_notice_version)->toBe('2026-09-28-v1');
});

test('booking form provides mandatory agreement controls and privacy notice', function () {
    [$facility] = createConsentFacility();

    $this->get(route('bookings.create'))
        ->assertOk()
        ->assertSee('name="terms_accepted"', false)
        ->assertSee('name="privacy_consent"', false)
        ->assertSeeText('Data Privacy Agreement')
        ->assertSeeText('Privacy Notice');
});

test('booking rejects a PDL assigned to a different facility', function () {
    [$selectedFacility] = createConsentFacility();
    [$otherFacility] = createConsentFacility();

    $this->post(route('bookings.store'), [
        'facility_id' => $selectedFacility->id,
        'pdl_name' => 'Juan Dela Cruz',
        'visitor_full_name' => 'Visitor Name',
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
    ])->assertRedirect();

    expect(Booking::count())->toBe(1);
});

test('facility admin cannot browse another facility through public booking endpoints', function () {
    [$assignedFacility] = createConsentFacility();
    [$otherFacility] = createConsentFacility();
    $facilityAdmin = User::factory()->create([
        'role' => User::ROLE_FACILITY_ADMIN,
        'facility_id' => $assignedFacility->id,
    ]);

    $this->actingAs($facilityAdmin)
        ->get(route('bookings.create'))
        ->assertOk()
        ->assertSeeText('Consent Test Facility')
        ->assertDontSee('value="'.$otherFacility->id.'"', false);

    $this->postJson(route('api.check-availability'), [
        'facility_id' => $otherFacility->id,
        'visit_date' => today()->addDays(4)->toDateString(),
        'time_slot' => '8:00 AM - 9:00 AM',
    ])->assertNotFound();
});
