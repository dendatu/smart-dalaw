<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Requests\StoreOnSiteVisitationRequest;
use App\Http\Requests\UpdateBookingRequest;
use App\Models\Booking;
use App\Models\Facility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function edit(Booking $booking): View
    {
        $booking = Booking::visibleToUser(auth()->user())
            ->whereKey($booking->id)
            ->firstOrFail();
        $booking->load(['facility', 'accompanyingVisitors', 'statusHistory.changedBy']);
        $facilities = Facility::visibleToUser(auth()->user())->orderBy('name')->get();

        // Generate the selectable slots from the selected facility's schedule for the
        // booking's visit date. The booking's existing slot is always included so it
        // can be preserved even if the schedule has changed since the booking was made.
        $timeSlots = [$booking->time_slot];

        foreach ($booking->facility?->availableTimeSlotsForDate($booking->visit_date) as $timeSlot) {
            if ($timeSlot !== $booking->time_slot) {
                $timeSlots[] = $timeSlot;
            }
        }

        return view('admin.bookings.edit', compact('booking', 'facilities', 'timeSlots'));
    }

    public function update(UpdateBookingRequest $request, Booking $booking): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($booking, $validated): void {
            $lockedBooking = Booking::visibleToUser(auth()->user())
                ->lockForUpdate()
                ->findOrFail($booking->id);
            $facility = Facility::visibleToUser(auth()->user())
                ->lockForUpdate()
                ->findOrFail($validated['facility_id']);

            if ($lockedBooking->status !== 'Rejected') {
                $existingBookings = Booking::query()
                    ->where('facility_id', $facility->id)
                    ->whereDate('visit_date', $validated['visit_date'])
                    ->where('time_slot', $validated['time_slot'])
                    ->where('status', '!=', 'Rejected')
                    ->where('id', '!=', $lockedBooking->id)
                    ->count();

                if ($existingBookings >= $facility->max_visitors_per_hour) {
                    throw ValidationException::withMessages([
                        'time_slot' => 'This time slot is fully booked. Choose another date or time.',
                    ]);
                }
            }

            $lockedBooking->update($validated);
        });

        return redirect()->route('admin.dashboard')->with('success', 'Booking details updated.');
    }

    public function createOnSite(): View
    {
        $facilities = Facility::visibleToUser(auth()->user())->orderBy('name')->get();
        $facility = $facilities->first();
        $pdls = $facility ? $facility->pdls()->orderBy('first_name')->orderBy('last_name')->get() : collect();

        return view('admin.visitations.create', compact('facilities', 'pdls', 'facility'));
    }

    public function storeOnSite(StoreOnSiteVisitationRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $referenceCode = 'BJMP-'.strtoupper(Str::random(6));
        $consentedAt = now();

        $booking = DB::transaction(function () use ($validated, $referenceCode, $consentedAt): Booking {
            $accompanyingVisitors = $validated['accompanying_visitors'] ?? [];
            unset($validated['accompanying_visitors']);

            $booking = Booking::create([
                ...$validated,
                'source' => Booking::SOURCE_ON_SITE_REGISTRATION,
                'reference_code' => $referenceCode,
                'status' => 'Approved',
                'terms_accepted' => true,
                'terms_accepted_at' => $consentedAt,
                'privacy_consent' => true,
                'privacy_consent_at' => $consentedAt,
                'privacy_notice_version' => StoreBookingRequest::PRIVACY_NOTICE_VERSION,
            ]);

            foreach ($accompanyingVisitors as $visitorData) {
                $booking->accompanyingVisitors()->create($visitorData);
            }

            $booking->statusHistory()->create([
                'user_id' => auth()->id(),
                'from_status' => null,
                'to_status' => 'Approved',
                'reason' => 'On-site visitation recorded by facility admin.',
            ]);

            return $booking;
        });

        return redirect()->route('admin.dashboard')->with('success', 'On-site visitation recorded.');
    }
}
