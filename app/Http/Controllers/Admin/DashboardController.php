<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Facility;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $visibleBookings = Booking::visibleToUser($request->user());

        $countsByStatus = (clone $visibleBookings)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $analytics = [
            'total' => $countsByStatus->sum(),
            'pending' => (int) $countsByStatus->get('Pending', 0),
            'approved' => (int) $countsByStatus->get('Approved', 0),
            'rejected' => (int) $countsByStatus->get('Rejected', 0),
            'today' => (clone $visibleBookings)->whereDate('visit_date', today())->count(),
            'upcoming' => (clone $visibleBookings)->whereDate('visit_date', '>=', today())
                ->where('status', 'Approved')
                ->count(),
        ];

        $query = Booking::visibleToUser($request->user())
            ->with(['facility'])
            ->withCount('accompanyingVisitors');

        $status = $request->query('status');

        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        $requestedDate = $this->resolveRequestedDate($request->query('date'));

        if ($requestedDate !== null) {
            $query->whereDate('visit_date', $requestedDate);
        }

        $bookings = $query->orderBy('created_at', 'desc')->paginate(20);

        $summaryDate = $this->resolveSummaryDate($request->query('date'));
        $dailySummary = Booking::dailyVisitationSummaryFor($summaryDate, $request->user());

        return view('admin.dashboard', compact('analytics', 'bookings', 'dailySummary', 'summaryDate'));
    }

    /**
     * Normalize the date filter from the query string. Missing, empty, or
     * unparseable values return null so callers can apply their default
     * instead of failing on a null/invalid date.
     */
    private function resolveRequestedDate(mixed $date): ?string
    {
        if (! is_string($date) || trim($date) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($date)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Resolve the date used for the daily summary, defaulting to today when
     * the date filter is missing, empty, or invalid.
     */
    private function resolveSummaryDate(mixed $date): CarbonImmutable
    {
        $requestedDate = $this->resolveRequestedDate($date);

        return $requestedDate !== null
            ? CarbonImmutable::parse($requestedDate)
            : CarbonImmutable::today();
    }

    public function approve(Request $request, Booking $booking): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        return $this->changeStatus($booking, 'Approved', $validated['reason']);
    }

    public function reject(Request $request, Booking $booking): RedirectResponse
    {
        $validated = $request->validate([
            'admin_remarks' => ['required', 'string', 'max:500'],
        ]);

        return $this->changeStatus($booking, 'Rejected', $validated['admin_remarks']);
    }

    public function updateStatus(Request $request, Booking $booking): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['Pending', 'Approved', 'Rejected'])],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        return $this->changeStatus($booking, $validated['status'], $validated['reason']);
    }

    private function changeStatus(Booking $booking, string $status, string $reason): RedirectResponse
    {
        DB::transaction(function () use ($booking, $status, $reason): void {
            $lockedBooking = Booking::visibleToUser(auth()->user())
                ->lockForUpdate()
                ->findOrFail($booking->id);

            if ($lockedBooking->status === $status) {
                throw ValidationException::withMessages([
                    'status' => "This booking is already {$status}.",
                ]);
            }

            if ($lockedBooking->status === 'Rejected' && $status !== 'Rejected') {
                $facility = Facility::visibleToUser(auth()->user())
                    ->lockForUpdate()
                    ->findOrFail($lockedBooking->facility_id);
                $existingBookings = Booking::query()
                    ->where('facility_id', $facility->id)
                    ->whereDate('visit_date', $lockedBooking->visit_date)
                    ->where('time_slot', $lockedBooking->time_slot)
                    ->where('status', '!=', 'Rejected')
                    ->whereKeyNot($lockedBooking->id)
                    ->count();

                if ($existingBookings >= $facility->max_visitors_per_hour) {
                    throw ValidationException::withMessages([
                        'status' => 'This visit cannot be reactivated because its hourly slot is full.',
                    ]);
                }
            }

            $previousStatus = $lockedBooking->status;
            $lockedBooking->status = $status;
            $lockedBooking->admin_remarks = $status === 'Rejected' ? $reason : null;
            $lockedBooking->save();

            $lockedBooking->statusHistory()->create([
                'user_id' => auth()->id(),
                'from_status' => $previousStatus,
                'to_status' => $status,
                'reason' => $reason,
            ]);
        });

        return redirect()->route('admin.dashboard')->with('success', "Booking status changed to {$status}.");
    }
}
