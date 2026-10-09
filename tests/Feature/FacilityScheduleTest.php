<?php

use App\Models\Booking;
use App\Models\Facility;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

function createScheduledFacility(): Facility
{
    $facility = Facility::create([
        'name' => 'San Fernando City District Jail - Male Dormitory',
        'max_visitors_per_hour' => 20,
    ]);

    $facility->schedules()->createMany([
        ['day_of_week' => 'Tuesday', 'start_time' => '13:00', 'end_time' => '17:00'],
        ['day_of_week' => 'Wednesday', 'start_time' => '13:00', 'end_time' => '17:00'],
        ['day_of_week' => 'Thursday', 'start_time' => '13:00', 'end_time' => '17:00'],
        ['day_of_week' => 'Friday', 'start_time' => '13:00', 'end_time' => '17:00'],
        ['day_of_week' => 'Saturday', 'start_time' => '08:00', 'end_time' => '12:00'],
        ['day_of_week' => 'Saturday', 'start_time' => '13:00', 'end_time' => '17:00'],
        ['day_of_week' => 'Sunday', 'start_time' => '08:00', 'end_time' => '12:00'],
        ['day_of_week' => 'Sunday', 'start_time' => '13:00', 'end_time' => '17:00'],
    ]);

    return $facility;
}

function scheduleBookingPayload(Facility $facility, array $overrides = []): array
{
    return array_merge([
        'facility_id' => $facility->id,
        'pdl_name' => 'Juan Dela Cruz',
        'visitor_full_name' => 'Schedule Visitor',
        'visitor_sex' => 'Male',
        'visitor_age' => 30,
        'visitor_contact' => '09123456789',
        'visitor_id_type' => 'Passport',
        'visitor_id_number' => 'P1234567',
        'relationship_to_pdl' => 'Sibling',
        'terms_accepted' => '1',
        'privacy_consent' => '1',
    ], $overrides);
}

test('a day without configured periods produces no public slots', function () {
    $facility = createScheduledFacility();
    $monday = CarbonImmutable::today()->next(Carbon::MONDAY);

    $this->getJson(route('api.facility-slots', $facility).'?date='.$monday->toDateString())
        ->assertOk()
        ->assertJsonPath('slots', []);
});

test('weekday slots are generated only inside the configured period', function () {
    $facility = createScheduledFacility();
    $tuesday = CarbonImmutable::today()->next(Carbon::TUESDAY);

    $this->getJson(route('api.facility-slots', $facility).'?date='.$tuesday->toDateString())
        ->assertOk()
        ->assertJsonPath('slots', [
            '1:00 PM - 2:00 PM',
            '2:00 PM - 3:00 PM',
            '3:00 PM - 4:00 PM',
            '4:00 PM - 5:00 PM',
        ]);
});

test('weekend slots cover both periods and never the break between them', function () {
    $facility = createScheduledFacility();
    $saturday = CarbonImmutable::today()->next(Carbon::SATURDAY);

    $this->getJson(route('api.facility-slots', $facility).'?date='.$saturday->toDateString())
        ->assertOk()
        ->assertJsonPath('slots', [
            '8:00 AM - 9:00 AM',
            '9:00 AM - 10:00 AM',
            '10:00 AM - 11:00 AM',
            '11:00 AM - 12:00 PM',
            '1:00 PM - 2:00 PM',
            '2:00 PM - 3:00 PM',
            '3:00 PM - 4:00 PM',
            '4:00 PM - 5:00 PM',
        ]);
});

test('availability check reports a slot outside the configured schedule', function () {
    $facility = createScheduledFacility();
    $tuesday = CarbonImmutable::today()->next(Carbon::TUESDAY);

    $this->postJson(route('api.check-availability'), [
        'facility_id' => $facility->id,
        'visit_date' => $tuesday->toDateString(),
        'time_slot' => '8:00 AM - 9:00 AM',
    ])->assertOk()
        ->assertJsonPath('available', false)
        ->assertJsonPath('reason', 'outside_schedule');
});

test('booking submission rejects a time slot outside the configured schedule', function () {
    $facility = createScheduledFacility();
    $tuesday = CarbonImmutable::today()->next(Carbon::TUESDAY);

    $this->post(route('bookings.store'), scheduleBookingPayload($facility, [
        'visit_date' => $tuesday->toDateString(),
        'time_slot' => '8:00 AM - 9:00 AM',
    ]))->assertSessionHasErrors('time_slot');

    expect(Booking::count())->toBe(0);
});

test('booking submission requires at least one day of advance notice', function () {
    $facility = createScheduledFacility();

    $this->post(route('bookings.store'), scheduleBookingPayload($facility, [
        'visit_date' => today()->toDateString(),
        'time_slot' => '1:00 PM - 2:00 PM',
    ]))->assertSessionHasErrors('visit_date');

    expect(Booking::count())->toBe(0);
});

test('booking form offers a database-backed facility dropdown and schedule driven time slots', function () {
    $facility = createScheduledFacility();

    $this->get(route('bookings.create'))
        ->assertOk()
        ->assertDontSee('id="facility_search"', false)
        ->assertDontSee('Search facilities', false)
        ->assertSee('id="facility_id"', false)
        ->assertSee('value="'.$facility->id.'"', false)
        ->assertSee('id="time_slot"', false)
        ->assertSee('api/facilities/:facility/slots', false)
        ->assertDontSee('value="8:00 AM - 9:00 AM"', false);
});
