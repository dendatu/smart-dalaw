<?php

use App\Models\Booking;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->facility = Facility::create(['name' => 'Export Test Facility', 'max_visitors_per_hour' => 20]);
});

function createExportBooking(Facility $facility, string $reference, string $date, array $attributes = []): Booking
{
    return Booking::create(array_merge([
        'facility_id' => $facility->id,
        'reference_code' => $reference,
        'pdl_name' => 'Juan Dela Cruz',
        'visitor_full_name' => 'Maria Santos',
        'visitor_sex' => 'Female',
        'visitor_age' => 30,
        'visitor_contact' => '09123456789',
        'visitor_id_type' => 'Passport',
        'visitor_id_number' => 'P1234567',
        'relationship_to_pdl' => 'Spouse',
        'visit_date' => $date,
        'time_slot' => '8:00 AM - 9:00 AM',
        'status' => 'Approved',
    ], $attributes));
}

/**
 * @return list<list<string>>
 */
function parseExportCsv(string $content): array
{
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

    return array_map(str_getcsv(...), preg_split('/\r?\n/', trim($content)));
}

test('summary page has a download CSV button for the selected range', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.daily-summary', ['from' => '2026-10-01', 'to' => '2026-10-15']))
        ->assertOk()
        ->assertSee('formaction="'.route('admin.daily-summary.export').'"', false)
        ->assertSeeText('Download CSV');
});

test('admins can download the visitation summary for a date range as CSV', function () {
    $booking = createExportBooking($this->facility, 'BJMP-EXP001', '2026-10-01', ['visitor_is_pwd' => true]);
    $booking->accompanyingVisitors()->create([
        'full_name' => 'Ana Santos',
        'age' => 25,
        'sex' => 'Female',
        'is_pregnant' => true,
        'id_type' => 'Passport',
        'id_number' => 'P2',
        'relationship_to_pdl' => 'Sibling',
    ]);
    createExportBooking($this->facility, 'BJMP-EXP002', '2026-10-15', ['visitor_sex' => 'Male', 'visitor_full_name' => 'Pedro Cruz']);
    createExportBooking($this->facility, 'BJMP-EXP003', '2026-10-10', ['status' => 'Pending']);
    createExportBooking($this->facility, 'BJMP-EXP004', '2026-10-16');

    $response = $this->actingAs(User::factory()->create())
        ->get(route('admin.daily-summary.export', ['from' => '2026-10-01', 'to' => '2026-10-15']))
        ->assertOk()
        ->assertDownload('visitation-summary_2026-10-01_to_2026-10-15.csv');

    expect($response->headers->get('Content-Type'))->toContain('text/csv');

    $content = $response->streamedContent();
    $rows = parseExportCsv($content);

    expect($content)->toStartWith("\xEF\xBB\xBF")
        ->and($rows)->toContain(['From', '2026-10-01'])
        ->and($rows)->toContain(['To', '2026-10-15'])
        ->and($rows)->toContain(['Total visitors', '3'])
        ->and($rows)->toContain(['Persons with Disability (PWD)', '1'])
        ->and($rows)->toContain(['Pregnant', '1'])
        ->and($rows)->toContain(['2026-10-01', '1', '0', '2', '1', '1', '2'])
        ->and($rows)->toContain(['Total', '2', '1', '2', '1', '1', '3'])
        ->and($rows)->toContain(['2026-10-01', '8:00 AM - 9:00 AM', 'BJMP-EXP001', 'Maria Santos', 'Primary', 'Female', '30', 'Yes', 'No', 'Juan Dela Cruz', 'Spouse', 'Export Test Facility'])
        ->and($rows)->toContain(['2026-10-01', '8:00 AM - 9:00 AM', 'BJMP-EXP001', 'Ana Santos', 'Accompanying', 'Female', '25', 'No', 'Yes', 'Juan Dela Cruz', 'Sibling', 'Export Test Facility'])
        ->and($rows)->toContain(['2026-10-15', '8:00 AM - 9:00 AM', 'BJMP-EXP002', 'Pedro Cruz', 'Primary', 'Male', '30', 'No', 'No', 'Juan Dela Cruz', 'Spouse', 'Export Test Facility'])
        ->and($content)->not->toContain('BJMP-EXP003')
        ->and($content)->not->toContain('BJMP-EXP004');
});

test('exported values that look like spreadsheet formulas are neutralised', function () {
    createExportBooking($this->facility, 'BJMP-EXP005', '2026-10-01', ['visitor_full_name' => '=HYPERLINK("http://example.com")']);

    $content = $this->actingAs(User::factory()->create())
        ->get(route('admin.daily-summary.export', ['from' => '2026-10-01', 'to' => '2026-10-01']))
        ->streamedContent();

    expect($content)->toContain('"\'=HYPERLINK(""http://example.com"")"');
});

test('export rejects an end date before the start date', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.daily-summary.export', ['from' => '2026-10-15', 'to' => '2026-10-01']))
        ->assertSessionHasErrors('to');
});

test('guests cannot download the visitation summary', function () {
    $this->get(route('admin.daily-summary.export'))->assertRedirect();
});
