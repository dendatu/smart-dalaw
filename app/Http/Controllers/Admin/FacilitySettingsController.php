<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FacilitySettingsController extends Controller
{
    public const SCHEDULE_DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    public function index(): View
    {
        $facilities = Facility::visibleToUser(auth()->user())->orderBy('name')->get();

        return view('admin.facilities.settings', compact('facilities'));
    }

    public function editSchedule(Facility $facility): View
    {
        $facility = Facility::visibleToUser(auth()->user())->whereKey($facility->id)->firstOrFail();

        $scheduleByDay = [];

        foreach (self::SCHEDULE_DAYS as $day) {
            $scheduleByDay[$day] = [];
        }

        foreach ($facility->schedules()->get() as $schedule) {
            if (array_key_exists($schedule->day_of_week, $scheduleByDay)) {
                $scheduleByDay[$schedule->day_of_week][] = $schedule;
            }
        }

        return view('admin.facilities.schedule', compact('facility', 'scheduleByDay'));
    }

    public function updateSchedule(Request $request, Facility $facility): RedirectResponse
    {
        $facility = Facility::visibleToUser($request->user())->whereKey($facility->id)->firstOrFail();

        $validated = $request->validate([
            'schedules' => ['required', 'array'],
            'schedules.*' => ['array'],
            'schedules.*.*' => ['array'],
            'schedules.*.*.start_time' => ['nullable', 'date_format:H:i'],
            'schedules.*.*.end_time' => ['nullable', 'date_format:H:i'],
        ]);

        $periods = [];
        $errors = [];

        foreach ($validated['schedules'] as $day => $dayInput) {
            if (! in_array($day, self::SCHEDULE_DAYS, true) || $dayInput === null) {
                continue;
            }

            $dayPeriods = [];

            foreach ($dayInput as $periodInput) {
                $startTime = blank($periodInput['start_time'] ?? null) ? null : (string) $periodInput['start_time'];
                $endTime = blank($periodInput['end_time'] ?? null) ? null : (string) $periodInput['end_time'];

                if (! $startTime && ! $endTime) {
                    continue;
                }

                if (! $startTime || ! $endTime) {
                    $errors[] = "Both a start and an end time are required for every {$day} visitation period.";

                    continue;
                }

                if ($endTime <= $startTime) {
                    $errors[] = "The visitation end time must be later than the start time on {$day}.";

                    continue;
                }

                $dayPeriods[] = ['start_time' => $startTime, 'end_time' => $endTime];
            }

            $periods[$day] = $dayPeriods;
        }

        if (count($errors) > 0) {
            throw ValidationException::withMessages([
                'schedules' => implode(' ', $errors),
            ]);
        }

        DB::transaction(function () use ($facility, $periods): void {
            $lockedFacility = Facility::visibleToUser(auth()->user())
                ->lockForUpdate()
                ->whereKey($facility->id)
                ->firstOrFail();

            $lockedFacility->schedules()->delete();

            foreach ($periods as $day => $dayPeriods) {
                foreach ($dayPeriods as $period) {
                    $lockedFacility->schedules()->create([
                        'day_of_week' => $day,
                        'start_time' => $period['start_time'],
                        'end_time' => $period['end_time'],
                    ]);
                }
            }
        });

        return redirect()->route('admin.facilities.settings')->with('success', 'Visitation schedule updated.');
    }

    public function updateCapacity(Request $request, Facility $facility): RedirectResponse
    {
        $facility = Facility::visibleToUser($request->user())->whereKey($facility->id)->firstOrFail();
        $validated = $request->validate([
            'max_visitors_per_hour' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        $facility->update($validated);

        return redirect()->route('admin.facilities.settings')->with('success', 'Hourly visitor limit updated.');
    }

    public function updateBranding(Request $request, Facility $facility): RedirectResponse
    {
        $facility = Facility::visibleToUser($request->user())->whereKey($facility->id)->firstOrFail();
        $validated = $request->validate([
            'regional_office' => ['nullable', 'string', 'max:150'],
            'jail_unit_name' => ['nullable', 'string', 'max:150'],
            'badge_image' => ['nullable', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max('2mb')],
        ]);

        $badge = $validated['badge_image'] ?? null;
        unset($validated['badge_image']);

        $previousBadgePath = $facility->badge_image_path;
        if ($badge instanceof UploadedFile) {
            $badgePath = $badge->store("facility-badges/{$facility->id}", 'public');

            if (! $badgePath) {
                throw ValidationException::withMessages([
                    'badge_image' => 'The facility badge could not be stored. Please try again.',
                ]);
            }

            $validated['badge_image_path'] = $badgePath;
        }

        $facility->update($validated);

        if ($badge instanceof UploadedFile && $previousBadgePath) {
            Storage::disk('public')->delete($previousBadgePath);
        }

        return redirect()->route('admin.facilities.settings')->with('success', 'Facility identity details updated.');
    }
}
