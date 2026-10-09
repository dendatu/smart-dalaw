<?php

use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

function createBadgeTestFacility(array $attributes = []): Facility
{
    return Facility::create(array_merge([
        'name' => 'Badge Display Test Jail',
        'max_visitors_per_hour' => 10,
    ], $attributes));
}

test('facility badge url resolves seeded public logos through asset', function () {
    $facility = createBadgeTestFacility(['badge_image_path' => 'regional-logos/ncr-logo.svg']);

    expect($facility->badge_url)->toBe(asset('regional-logos/ncr-logo.svg'));
});

test('facility badge url resolves uploaded badges through the public storage disk', function () {
    $facility = createBadgeTestFacility(['badge_image_path' => 'facility-badges/7/badge.png']);

    expect($facility->badge_url)->toBe(Storage::disk('public')->url('facility-badges/7/badge.png'));
});

test('facility badge url is null when the facility has no configured logo', function () {
    $facility = createBadgeTestFacility();

    expect($facility->badge_url)->toBeNull();
});

test('booking form keeps the database-backed dropdown without a search input', function () {
    $facility = createBadgeTestFacility(['badge_image_path' => 'regional-logos/ncr-logo.svg']);

    $this->get(route('bookings.create'))
        ->assertOk()
        ->assertDontSee('id="facility_search"', false)
        ->assertDontSee('Search facilities', false)
        ->assertSee('id="facility_id"', false)
        ->assertSee('value="'.$facility->id.'"', false)
        ->assertSee($facility->name)
        ->assertSee('data-badge-url="'.asset('regional-logos/ncr-logo.svg').'"', false);
});

test('booking form exposes an empty badge url when the facility has no logo', function () {
    createBadgeTestFacility();

    $this->get(route('bookings.create'))
        ->assertOk()
        ->assertSee('data-badge-url=""', false);
});

test('booking form renders the badge image and its fallback for the selected facility', function () {
    createBadgeTestFacility(['badge_image_path' => 'regional-logos/ncr-logo.svg']);

    $this->get(route('bookings.create'))
        ->assertOk()
        ->assertSee('id="facility-badge-fallback"', false)
        ->assertSee('id="facility-badge-image"', false)
        ->assertSee(asset('images/bjmp-logo.png'), false);
});

test('facility settings page renders the resolved badge url', function () {
    createBadgeTestFacility(['badge_image_path' => 'regional-logos/ncr-logo.svg']);
    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.facilities.settings'))
        ->assertOk()
        ->assertSee('src="'.asset('regional-logos/ncr-logo.svg').'"', false);
});
