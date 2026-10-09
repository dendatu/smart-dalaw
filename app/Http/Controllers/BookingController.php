<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Facility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    public function index()
    {
        return view('home');
    }

    public function create()
    {
        $facilities = Facility::query()->visibleToUser(auth()->user())->orderBy('regional_office')->orderBy('name')->get();

        return view('bookings.create', compact('facilities'));
    }

    public function checkSlotAvailability(Request $request)
    {
        $validated = $request->validate([
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'visit_date' => ['required', 'date'],
            'time_slot' => ['required', 'string', 'max:50'],
        ]);

        if ($request->user()?->isFacilityAdmin() && (int) $request->user()->facility_id !== (int) $validated['facility_id']) {
            abort(404);
        }

        $facility = Facility::findOrFail($validated['facility_id']);

        if (! $facility->isTimeSlotAvailableOnDate($validated['visit_date'], $validated['time_slot'])) {
            return response()->json([
                'available' => false,
                'reason' => 'outside_schedule',
                'current_bookings' => 0,
                'max_visitors_per_hour' => $facility->max_visitors_per_hour,
            ]);
        }

        $existingBookings = Booking::where('facility_id', $facility->id)
            ->whereDate('visit_date', $validated['visit_date'])
            ->where('time_slot', $validated['time_slot'])
            ->where('status', '!=', 'Rejected')
            ->count();

        $available = $existingBookings < $facility->max_visitors_per_hour;

        return response()->json([
            'available' => $available,
            'reason' => $available ? 'available' : 'fully_booked',
            'current_bookings' => $existingBookings,
            'max_visitors_per_hour' => $facility->max_visitors_per_hour,
        ]);
    }

    public function availableSlots(Request $request, Facility $facility)
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
        ]);

        $slots = $facility->availableTimeSlotsForDate($validated['date']);

        return response()->json([
            'date' => $validated['date'],
            'slots' => $slots,
        ]);
    }

    public function store(StoreBookingRequest $request)
    {
        $validated = $request->validated();
        $referenceCode = 'BJMP-'.strtoupper(Str::random(6));
        $consentedAt = now();

        $booking = DB::transaction(function () use ($validated, $referenceCode, $consentedAt): Booking {
            $facility = Facility::query()
                ->lockForUpdate()
                ->findOrFail($validated['facility_id']);

            $existingBookings = Booking::where('facility_id', $facility->id)
                ->whereDate('visit_date', $validated['visit_date'])
                ->where('time_slot', $validated['time_slot'])
                ->where('status', '!=', 'Rejected')
                ->count();

            if ($existingBookings >= $facility->max_visitors_per_hour) {
                throw ValidationException::withMessages([
                    'time_slot' => 'This time slot is fully booked. Please choose another time.',
                ]);
            }

            $accompanyingVisitors = $validated['accompanying_visitors'] ?? [];
            unset($validated['accompanying_visitors']);

            $booking = Booking::create([
                ...$validated,
                'source' => Booking::SOURCE_ONLINE_BOOKING,
                'reference_code' => $referenceCode,
                'status' => 'Pending',
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
                'from_status' => null,
                'to_status' => 'Pending',
                'reason' => 'Booking request submitted.',
            ]);

            return $booking;
        });

        return redirect()->route('booking.success', $referenceCode);
    }

    public function success($code)
    {
        $booking = $this->visibleBookings()->with(['facility', 'statusHistory.changedBy', 'accompanyingVisitors'])
            ->where('reference_code', $code)
            ->firstOrFail();

        return view('bookings.success', compact('booking'));
    }

    public function track()
    {
        return view('bookings.track');
    }

    public function trackSearch(Request $request)
    {
        $request->validate([
            'reference_code' => 'required|string',
        ]);

        $booking = $this->visibleBookings()->with(['facility', 'statusHistory.changedBy', 'accompanyingVisitors'])
            ->where('reference_code', strtoupper($request->reference_code))
            ->first();

        if (! $booking) {
            return back()->with('error', 'No booking found with this reference code.');
        }

        return view('bookings.track-result', compact('booking'));
    }

    public function qrRedirect($code)
    {
        $booking = $this->visibleBookings()->with(['facility'])
            ->where('reference_code', strtoupper($code))
            ->first();

        if (! $booking) {
            return redirect()->route('bookings.track')->with('error', 'Invalid QR code or booking not found.');
        }

        return redirect()->route('booking.success', $booking->reference_code);
    }

    private function visibleBookings(): Builder
    {
        $query = Booking::query();
        $user = auth()->user();

        return $user ? $query->visibleToUser($user) : $query;
    }
}
