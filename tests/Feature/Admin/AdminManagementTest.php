<?php

use App\Models\Booking;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

function createAdminManagementBooking(Facility $facility, array $attributes = []): Booking
{
    return Booking::create(array_merge([
        'reference_code' => 'BJMP-'.Str::upper(Str::random(8)),
        'facility_id' => $facility->id,
        'pdl_name' => 'Juan Dela Cruz',
        'visitor_full_name' => 'Sample Visitor',
        'visitor_sex' => 'Male',
        'visitor_age' => 30,
        'visitor_email' => 'visitor@example.com',
        'visitor_contact' => '09123456789',
        'visitor_id_type' => 'Passport',
        'visitor_id_number' => 'P1234567',
        'relationship_to_pdl' => 'Spouse',
        'visit_date' => now()->addDays(5)->toDateString(),
        'time_slot' => '8:00 AM - 9:00 AM',
        'status' => 'Pending',
    ], $attributes));
}

function createAdminManagementFacility(int $maxVisitorsPerHour = 5): Facility
{
    $facility = Facility::create([
        'name' => 'Test City Jail',
        'max_visitors_per_hour' => $maxVisitorsPerHour,
    ]);

    enableFacilitySchedule($facility);

    return $facility;
}

test('admin dashboard summarizes booking activity', function () {
    $admin = User::factory()->create();
    $facility = createAdminManagementFacility();

    createAdminManagementBooking($facility, [
        'visit_date' => today()->toDateString(),
        'status' => 'Pending',
    ]);
    createAdminManagementBooking($facility, ['status' => 'Approved']);
    createAdminManagementBooking($facility, ['status' => 'Rejected']);

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk()
        ->assertSeeText('Total bookings')
        ->assertSeeText('Visits today')
        ->assertSeeText('Change status')
        ->assertSee('name="reason"', false)
        ->assertViewHas('analytics', fn (array $analytics): bool => $analytics['total'] === 3
            && $analytics['pending'] === 1
            && $analytics['approved'] === 1
            && $analytics['rejected'] === 1
            && $analytics['today'] === 1);
});

test('admin management routes redirect guests to login', function () {
    $this->get(route('admin.users.index'))->assertRedirect(route('login'));
});

test('admins can create edit and delete admin accounts', function () {
    $admin = User::factory()->create();
    $facility = createAdminManagementFacility();

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertSeeText($admin->email);

    $this->get(route('admin.users.create'))
        ->assertOk()
        ->assertSeeText('Create account');

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Second Admin',
            'email' => 'second-admin@example.com',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
            'role' => 'facility_admin',
            'facility_id' => $facility->id,
        ])
        ->assertRedirect(route('admin.users.index'));

    $managedUser = User::where('email', 'second-admin@example.com')->firstOrFail();
    expect($managedUser->role)->toBe(User::ROLE_FACILITY_ADMIN)
        ->and($managedUser->facility_id)->toBe($facility->id);

    $this->get(route('admin.users.edit', $managedUser))
        ->assertOk()
        ->assertSeeText('Edit admin account');

    $this->put(route('admin.users.update', $managedUser), [
        'name' => 'Updated Admin',
        'email' => 'updated-admin@example.com',
        'role' => 'super_admin',
        'password' => '',
        'password_confirmation' => '',
    ])->assertRedirect(route('admin.users.index'));

    $this->assertDatabaseHas('users', [
        'id' => $managedUser->id,
        'name' => 'Updated Admin',
        'email' => 'updated-admin@example.com',
    ]);

    $this->delete(route('admin.users.destroy', $managedUser))
        ->assertRedirect(route('admin.users.index'));

    $this->assertDatabaseMissing('users', ['id' => $managedUser->id]);
});

test('admins cannot delete their own account', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $admin))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('users', ['id' => $admin->id]);
});

test('admins must explain status changes and each reason is kept in the booking history', function () {
    $admin = User::factory()->create();
    $facility = createAdminManagementFacility();
    $booking = createAdminManagementBooking($facility);

    $this->actingAs($admin)
        ->post(route('admin.bookings.status.update', $booking), [
            'status' => 'Approved',
            'reason' => 'Visitor identity verified.',
        ])
        ->assertRedirect(route('admin.dashboard'));

    $this->post(route('admin.bookings.status.update', $booking), [
        'status' => 'Rejected',
        'reason' => 'Facility inspection requires a reschedule.',
    ])->assertRedirect(route('admin.dashboard'));

    $this->assertDatabaseHas('booking_status_histories', [
        'booking_id' => $booking->id,
        'user_id' => $admin->id,
        'from_status' => 'Pending',
        'to_status' => 'Approved',
        'reason' => 'Visitor identity verified.',
    ]);

    $this->assertDatabaseHas('booking_status_histories', [
        'booking_id' => $booking->id,
        'user_id' => $admin->id,
        'from_status' => 'Approved',
        'to_status' => 'Rejected',
        'reason' => 'Facility inspection requires a reschedule.',
    ]);

    $this->post(route('bookings.track.search'), ['reference_code' => $booking->reference_code])
        ->assertOk()
        ->assertSeeText('Status history')
        ->assertSeeText('Visitor identity verified.')
        ->assertSeeText('Facility inspection requires a reschedule.');
});

test('admin status cannot change without a reason', function () {
    $admin = User::factory()->create();
    $facility = createAdminManagementFacility();
    $booking = createAdminManagementBooking($facility);

    $this->actingAs($admin)
        ->post(route('admin.bookings.status.update', $booking), ['status' => 'Approved'])
        ->assertSessionHasErrors('reason');

    expect($booking->fresh()->status)->toBe('Pending');
});

test('rejected bookings cannot be reactivated into a full hourly slot', function () {
    $admin = User::factory()->create();
    $facility = createAdminManagementFacility(1);
    $rejectedBooking = createAdminManagementBooking($facility, [
        'status' => 'Rejected',
        'visit_date' => today()->addDays(5)->toDateString(),
    ]);
    createAdminManagementBooking($facility, [
        'status' => 'Pending',
        'visit_date' => today()->addDays(5)->toDateString(),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.bookings.status.update', $rejectedBooking), [
            'status' => 'Approved',
            'reason' => 'The visitor provided corrected documents.',
        ])
        ->assertSessionHasErrors('status');

    expect($rejectedBooking->fresh()->status)->toBe('Rejected');
});

test('admins can set the visitors allowed per hourly slot', function () {
    $admin = User::factory()->create();
    $facility = createAdminManagementFacility();

    $this->actingAs($admin)
        ->get(route('admin.facilities.settings'))
        ->assertOk()
        ->assertSeeText('Visitors per hourly slot');

    $this->put(route('admin.facilities.capacity.update', $facility), [
        'max_visitors_per_hour' => 12,
    ])->assertRedirect(route('admin.facilities.settings'));

    $this->assertDatabaseHas('facilities', [
        'id' => $facility->id,
        'max_visitors_per_hour' => 12,
    ]);
});

test('admins can save regional unit naming and an approved facility badge', function () {
    Storage::fake('public');
    $admin = User::factory()->create();
    $facility = createAdminManagementFacility();

    $this->actingAs($admin)
        ->patch(route('admin.facilities.branding.update', $facility), [
            'regional_office' => 'Region III',
            'jail_unit_name' => 'Mabalacat City Jail',
            'badge_image' => UploadedFile::fake()->image('facility-badge.png', 240, 240),
        ])
        ->assertRedirect(route('admin.facilities.settings'));

    $facility->refresh();
    expect($facility->regional_office)->toBe('Region III')
        ->and($facility->jail_unit_name)->toBe('Mabalacat City Jail')
        ->and($facility->badge_image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($facility->badge_image_path);
});

test('admins can edit a booking without changing its approval status', function () {
    $admin = User::factory()->create();
    $facility = createAdminManagementFacility();
    $booking = createAdminManagementBooking($facility, ['status' => 'Approved']);

    $this->actingAs($admin)
        ->get(route('admin.bookings.edit', $booking))
        ->assertOk()
        ->assertSeeText($booking->reference_code);

    $this->actingAs($admin)
        ->put(route('admin.bookings.update', $booking), [
            'facility_id' => $facility->id,
            'pdl_name' => 'Juan Dela Cruz',
            'visitor_full_name' => 'Updated Visitor',
            'visitor_sex' => 'Male',
            'visitor_age' => 30,
            'visitor_email' => 'updated@example.com',
            'visitor_contact' => '09987654321',
            'visitor_id_type' => 'Driver\'s License',
            'visitor_id_number' => 'DL-123456',
            'relationship_to_pdl' => 'Sibling',
            'visit_date' => today()->addDays(8)->toDateString(),
            'time_slot' => '9:00 AM - 10:00 AM',
        ])
        ->assertRedirect(route('admin.dashboard'));

    $this->assertDatabaseHas('bookings', [
        'id' => $booking->id,
        'visitor_full_name' => 'Updated Visitor',
        'status' => 'Approved',
        'time_slot' => '9:00 AM - 10:00 AM',
    ]);
});

test('booking edits cannot exceed facility slot capacity', function () {
    $admin = User::factory()->create();
    $facility = createAdminManagementFacility(1);
    $bookingToEdit = createAdminManagementBooking($facility, [
        'visit_date' => today()->addDays(5)->toDateString(),
    ]);
    createAdminManagementBooking($facility, [
        'visit_date' => today()->addDays(6)->toDateString(),
        'time_slot' => '9:00 AM - 10:00 AM',
    ]);

    $this->actingAs($admin)
        ->put(route('admin.bookings.update', $bookingToEdit), [
            'facility_id' => $facility->id,
            'pdl_name' => 'Juan Dela Cruz',
            'visitor_full_name' => 'Sample Visitor',
            'visitor_sex' => 'Male',
            'visitor_age' => 30,
            'visitor_email' => 'visitor@example.com',
            'visitor_contact' => '09123456789',
            'visitor_id_type' => 'Passport',
            'visitor_id_number' => 'P1234567',
            'relationship_to_pdl' => 'Spouse',
            'visit_date' => today()->addDays(6)->toDateString(),
            'time_slot' => '9:00 AM - 10:00 AM',
        ])
        ->assertSessionHasErrors('time_slot');

    expect($bookingToEdit->fresh()->visit_date->toDateString())
        ->toBe(today()->addDays(5)->toDateString());
});

test('visit calendar shows all bookings in the selected month', function () {
    $admin = User::factory()->create();
    $facility = createAdminManagementFacility();
    createAdminManagementBooking($facility, [
        'reference_code' => 'BJMP-OCT001',
        'visit_date' => '2026-10-08',
    ]);
    createAdminManagementBooking($facility, [
        'reference_code' => 'BJMP-OCT002',
        'visit_date' => '2026-10-08',
        'time_slot' => '9:00 AM - 10:00 AM',
    ]);
    createAdminManagementBooking($facility, [
        'reference_code' => 'BJMP-NOV001',
        'visit_date' => '2026-11-04',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.calendar', ['month' => '2026-10']))
        ->assertOk()
        ->assertSee('BJMP-OCT001')
        ->assertSee('BJMP-OCT002')
        ->assertDontSee('BJMP-NOV001');
});
