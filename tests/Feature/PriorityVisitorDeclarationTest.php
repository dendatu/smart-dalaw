<?php

use App\Models\AccompanyingVisitor;
use App\Models\Booking;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

function createPriorityFacility(): Facility
{
    return Facility::create([
        'name' => 'Priority Test Facility',
        'max_visitors_per_hour' => 20,
    ]);
}

function priorityBookingPayload(Facility $facility, array $overrides = []): array
{
    return array_merge([
        'facility_id' => $facility->id,
        'pdl_name' => 'Juan Dela Cruz',
        'visitor_full_name' => 'Maria Santos',
        'visitor_sex' => 'Female',
        'visitor_age' => 29,
        'visitor_contact' => '09123456789',
        'visitor_id_type' => 'Passport',
        'visitor_id_number' => 'P1234567',
        'relationship_to_pdl' => 'Spouse',
        'visit_date' => today()->addDays(4)->toDateString(),
        'time_slot' => '8:00 AM - 9:00 AM',
        'terms_accepted' => '1',
        'privacy_consent' => '1',
    ], $overrides);
}

test('booking form shows the PWD and pregnancy checkboxes', function () {
    createPriorityFacility();

    $this->get(route('bookings.create'))
        ->assertOk()
        ->assertSee('name="visitor_is_pwd"', false)
        ->assertSee('name="visitor_is_pregnant"', false)
        ->assertSee('accompanying_visitors[${index}][is_pwd]', false)
        ->assertSee('accompanying_visitors[${index}][is_pregnant]', false);
});

test('visitor PWD and pregnancy declarations are saved', function () {
    $facility = createPriorityFacility();

    $this->post(route('bookings.store'), priorityBookingPayload($facility, [
        'visitor_is_pwd' => '1',
        'visitor_is_pregnant' => '1',
        'accompanying_visitors' => [
            [
                'full_name' => 'Pedro Santos',
                'age' => 65,
                'sex' => 'Male',
                'is_pwd' => '1',
                'id_type' => 'Senior Citizen ID',
                'id_number' => 'SC-123',
                'relationship_to_pdl' => 'Parent',
            ],
            [
                'full_name' => 'Ana Santos',
                'age' => 30,
                'sex' => 'Female',
                'id_type' => 'Passport',
                'id_number' => 'P7654321',
                'relationship_to_pdl' => 'Sibling',
            ],
        ],
    ]))->assertRedirect();

    $booking = Booking::with('accompanyingVisitors')->sole();

    expect($booking->visitor_is_pwd)->toBeTrue()
        ->and($booking->visitor_is_pregnant)->toBeTrue()
        ->and($booking->accompanyingVisitors->firstWhere('full_name', 'Pedro Santos')->is_pwd)->toBeTrue()
        ->and($booking->accompanyingVisitors->firstWhere('full_name', 'Pedro Santos')->is_pregnant)->toBeFalse()
        ->and($booking->accompanyingVisitors->firstWhere('full_name', 'Ana Santos')->is_pwd)->toBeFalse()
        ->and($booking->accompanyingVisitors->firstWhere('full_name', 'Ana Santos')->is_pregnant)->toBeFalse();
});

test('declarations default to false when the checkboxes are left unchecked', function () {
    $facility = createPriorityFacility();

    $this->post(route('bookings.store'), priorityBookingPayload($facility))->assertRedirect();

    $booking = Booking::sole();

    expect($booking->visitor_is_pwd)->toBeFalse()
        ->and($booking->visitor_is_pregnant)->toBeFalse();
});

test('male visitors cannot be declared as pregnant', function () {
    $facility = createPriorityFacility();

    $this->post(route('bookings.store'), priorityBookingPayload($facility, [
        'visitor_sex' => 'Male',
        'visitor_is_pregnant' => '1',
        'accompanying_visitors' => [
            [
                'full_name' => 'Pedro Santos',
                'age' => 40,
                'sex' => 'Male',
                'is_pregnant' => '1',
                'id_type' => 'Passport',
                'id_number' => 'P111',
                'relationship_to_pdl' => 'Sibling',
            ],
        ],
    ]))->assertSessionHasErrors(['visitor_is_pregnant', 'accompanying_visitors.0.is_pregnant']);

    expect(Booking::count())->toBe(0);
});

test('admins can update the declarations when editing a booking', function () {
    $admin = User::factory()->create();
    $facility = createPriorityFacility();
    $booking = Booking::create([
        ...priorityBookingPayload($facility),
        'reference_code' => 'BJMP-PRIO01',
        'status' => 'Pending',
        'visitor_is_pwd' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.bookings.edit', $booking))
        ->assertOk()
        ->assertSee('name="visitor_is_pwd"', false);

    $this->actingAs($admin)
        ->put(route('admin.bookings.update', $booking), priorityBookingPayload($facility, [
            'visitor_is_pregnant' => '1',
        ]))
        ->assertRedirect(route('admin.dashboard'));

    expect($booking->fresh()->visitor_is_pwd)->toBeFalse()
        ->and($booking->fresh()->visitor_is_pregnant)->toBeTrue();
});

test('walk-in registrations save the declarations', function () {
    $admin = User::factory()->create();
    $facility = createPriorityFacility();

    $this->actingAs($admin)
        ->post(route('admin.bookings.store'), priorityBookingPayload($facility, [
            'visit_date' => today()->toDateString(),
            'visitor_is_pwd' => '1',
        ]))
        ->assertRedirect(route('admin.dashboard'));

    expect(Booking::sole()->visitor_is_pwd)->toBeTrue();
});

test('daily summary counts PWD and pregnant visitors including companions', function () {
    $admin = User::factory()->create();
    $facility = createPriorityFacility();

    $booking = Booking::create([
        ...priorityBookingPayload($facility, ['visit_date' => today()->toDateString()]),
        'reference_code' => 'BJMP-PRIO02',
        'status' => 'Approved',
        'visitor_is_pwd' => true,
        'visitor_is_pregnant' => true,
    ]);
    AccompanyingVisitor::create([
        'booking_id' => $booking->id,
        'full_name' => 'Pedro Santos',
        'age' => 70,
        'sex' => 'Male',
        'is_pwd' => true,
        'id_type' => 'Senior Citizen ID',
        'id_number' => 'SC-1',
        'relationship_to_pdl' => 'Parent',
    ]);
    AccompanyingVisitor::create([
        'booking_id' => $booking->id,
        'full_name' => 'Ana Santos',
        'age' => 25,
        'sex' => 'Female',
        'id_type' => 'Passport',
        'id_number' => 'P2',
        'relationship_to_pdl' => 'Sibling',
    ]);
    Booking::create([
        ...priorityBookingPayload($facility, ['visit_date' => today()->toDateString()]),
        'reference_code' => 'BJMP-PRIO03',
        'status' => 'Pending',
        'visitor_is_pwd' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.daily-summary', ['date' => today()->toDateString()]))
        ->assertOk()
        ->assertSeeText('Priority Visitors')
        ->assertSeeText('Persons with Disability (PWD)')
        ->assertViewHas('summary', fn (array $summary): bool => $summary['total_visitors'] === 3
            && $summary['pwd'] === 2
            && $summary['pregnant'] === 1
            && $summary['priority'] === 2);
});
