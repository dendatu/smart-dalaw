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

function createSummaryFacility(): Facility
{
    $facility = Facility::create([
        'name' => 'Summary Test Jail',
        'max_visitors_per_hour' => 50,
    ]);

    enableFacilitySchedule($facility);

    return $facility;
}

function createSummaryBooking(Facility $facility, string $sex, int $age, string $date, string $status = 'Approved'): Booking
{
    return Booking::create([
        'reference_code' => 'BJMP-'.strtoupper(Str::random(8)),
        'facility_id' => $facility->id,
        'pdl_name' => 'Test PDL',
        'visitor_full_name' => "Visitor {$sex} {$age}",
        'visitor_sex' => $sex,
        'visitor_age' => $age,
        'visitor_contact' => '09123456789',
        'visitor_id_type' => 'Passport',
        'visitor_id_number' => 'P'.rand(1000000, 9999999),
        'relationship_to_pdl' => 'Sibling',
        'visit_date' => $date,
        'time_slot' => '8:00 AM - 9:00 AM',
        'status' => $status,
    ]);
}

test('daily summary counts primary visitors by sex and age group correctly', function () {
    $facility = createSummaryFacility();
    $date = today()->toDateString();

    createSummaryBooking($facility, 'Male', 15, $date);   // minor
    createSummaryBooking($facility, 'Female', 15, $date); // minor
    createSummaryBooking($facility, 'Male', 30, $date);   // adult
    createSummaryBooking($facility, 'Female', 30, $date); // adult
    createSummaryBooking($facility, 'Male', 65, $date);   // senior
    createSummaryBooking($facility, 'Female', 65, $date); // senior

    $summary = Booking::dailyVisitationSummaryFor($date);

    expect($summary['total'])->toBe(6)
        ->and($summary['sex']['Male'])->toBe(3)
        ->and($summary['sex']['Female'])->toBe(3)
        ->and($summary['age_group']['Minor'])->toBe(2)
        ->and($summary['age_group']['Adult'])->toBe(2)
        ->and($summary['age_group']['Senior'])->toBe(2);
});

test('daily summary includes accompanying visitors in counts', function () {
    $facility = createSummaryFacility();
    $date = today()->toDateString();

    // Primary: Male 35 (adult)
    $booking = createSummaryBooking($facility, 'Male', 35, $date);

    // Accompanying: Female 32 (adult) + Male 15 (minor)
    $booking->accompanyingVisitors()->createMany([
        [
            'full_name' => 'Maria Companion',
            'sex' => 'Female',
            'age' => 32,
            'id_type' => 'Passport',
            'id_number' => 'P2222222',
            'relationship_to_pdl' => 'Spouse',
        ],
        [
            'full_name' => 'Pedro Companion',
            'sex' => 'Male',
            'age' => 15,
            'id_type' => 'Passport',
            'id_number' => 'P3333333',
            'relationship_to_pdl' => 'Child',
        ],
    ]);

    $summary = Booking::dailyVisitationSummaryFor($date);

    expect($summary['total'])->toBe(3)
        ->and($summary['sex']['Male'])->toBe(2)
        ->and($summary['sex']['Female'])->toBe(1)
        ->and($summary['age_group']['Adult'])->toBe(2)
        ->and($summary['age_group']['Minor'])->toBe(1);
});

test('daily summary excludes rejected bookings', function () {
    $facility = createSummaryFacility();
    $date = today()->toDateString();

    createSummaryBooking($facility, 'Male', 30, $date, 'Approved');
    createSummaryBooking($facility, 'Female', 30, $date, 'Rejected');

    $summary = Booking::dailyVisitationSummaryFor($date);

    expect($summary['total'])->toBe(1)
        ->and($summary['sex']['Male'])->toBe(1)
        ->and($summary['sex']['Female'])->toBe(0);
});

test('daily summary is scoped to facility for facility admins', function () {
    $facilityA = createSummaryFacility();
    $facilityB = Facility::create(['name' => 'Other Jail', 'max_visitors_per_hour' => 10]);
    enableFacilitySchedule($facilityB);

    $admin = User::factory()->create([
        'role' => 'facility_admin',
        'facility_id' => $facilityA->id,
    ]);

    $date = today()->toDateString();
    createSummaryBooking($facilityA, 'Male', 30, $date);   // visible to admin
    createSummaryBooking($facilityB, 'Female', 30, $date); // NOT visible

    $summary = Booking::dailyVisitationSummaryFor($date, $admin);

    expect($summary['total'])->toBe(1)
        ->and($summary['sex']['Male'])->toBe(1)
        ->and($summary['sex']['Female'])->toBe(0);
});

test('daily summary is shown on the admin dashboard with correct date', function () {
    $facility = createSummaryFacility();
    $admin = User::factory()->create();
    $date = today()->toDateString();

    createSummaryBooking($facility, 'Male', 30, $date);

    $this->actingAs($admin)
        ->get(route('admin.dashboard', ['date' => $date]))
        ->assertOk()
        ->assertViewHas('dailySummary', function (array $summary): bool {
            return $summary['total'] === 1
                && $summary['sex']['Male'] === 1;
        });
});

test('daily summary separates bookings, primary visitors, and accompanying visitors', function () {
    $facility = createSummaryFacility();
    $date = today()->toDateString();

    $booking = createSummaryBooking($facility, 'Male', 30, $date);
    $booking->accompanyingVisitors()->createMany([
        [
            'full_name' => 'Maria Companion',
            'sex' => 'Female',
            'age' => 32,
            'id_type' => 'Passport',
            'id_number' => 'P5555555',
            'relationship_to_pdl' => 'Spouse',
        ],
        [
            'full_name' => 'Pedro Companion',
            'sex' => 'Male',
            'age' => 25,
            'id_type' => 'Passport',
            'id_number' => 'P6666666',
            'relationship_to_pdl' => 'Sibling',
        ],
    ]);

    $summary = Booking::dailyVisitationSummaryFor($date);

    // One appointment with three people is one booking, not three bookings.
    expect($summary['bookings'])->toBe(1)
        ->and($summary['primary_visitors'])->toBe(1)
        ->and($summary['accompanying_visitors'])->toBe(2)
        ->and($summary['total'])->toBe(3)
        ->and($summary['sex']['Male'])->toBe(2)
        ->and($summary['sex']['Female'])->toBe(1);
});

test('approved booking appears in the daily summary for its visit date and not on another date', function () {
    $facility = createSummaryFacility();
    $admin = User::factory()->create();
    $visitDate = today()->addDays(5)->toDateString();
    $otherDate = today()->addDays(6)->toDateString();

    $booking = createSummaryBooking($facility, 'Male', 30, $visitDate, 'Pending');
    $booking->accompanyingVisitors()->create([
        'full_name' => 'Maria Companion',
        'sex' => 'Female',
        'age' => 32,
        'id_type' => 'Passport',
        'id_number' => 'P7777777',
        'relationship_to_pdl' => 'Spouse',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.approve', $booking), ['reason' => 'Approved for the scheduled visitation.'])
        ->assertRedirect(route('admin.dashboard'));

    expect($booking->fresh()->status)->toBe('Approved');

    $this->actingAs($admin)
        ->get(route('admin.dashboard', ['date' => $visitDate]))
        ->assertOk()
        ->assertSeeText('Total expected visitors')
        ->assertSeeText('Bookings: 1')
        ->assertSeeText('Primary visitors: 1')
        ->assertSeeText('Accompanying visitors: 1')
        ->assertViewHas('dailySummary', function (array $summary): bool {
            return $summary['bookings'] === 1
                && $summary['primary_visitors'] === 1
                && $summary['accompanying_visitors'] === 1
                && $summary['total'] === 2;
        });

    // The same booking must not leak into any other date's summary.
    $this->actingAs($admin)
        ->get(route('admin.dashboard', ['date' => $otherDate]))
        ->assertOk()
        ->assertSeeText('Bookings: 0')
        ->assertViewHas('dailySummary', function (array $summary): bool {
            return $summary['bookings'] === 0
                && $summary['primary_visitors'] === 0
                && $summary['accompanying_visitors'] === 0
                && $summary['total'] === 0;
        });
});

test('age boundary 18 is classified as adult not minor', function () {
    expect(Booking::ageGroupForAge(18))->toBe('Adult');
});

test('age boundary 59 is classified as adult not senior', function () {
    expect(Booking::ageGroupForAge(59))->toBe('Adult');
});

test('age boundary 60 is classified as senior', function () {
    expect(Booking::ageGroupForAge(60))->toBe('Senior');
});

test('age boundary 17 is classified as minor', function () {
    expect(Booking::ageGroupForAge(17))->toBe('Minor');
});
