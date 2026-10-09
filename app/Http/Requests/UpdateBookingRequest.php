<?php

namespace App\Http\Requests;

use App\Models\Facility;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'pdl_name' => ['required', 'string', 'max:255'],
            'visitor_full_name' => ['required', 'string', 'max:255'],
            'visitor_email' => ['nullable', 'email', 'max:255'],
            'visitor_contact' => ['required', 'string', 'max:20'],
            'visitor_sex' => ['required', 'string', 'in:Male,Female'],
            'visitor_age' => ['required', 'integer', 'min:1', 'max:120'],
            'visitor_id_type' => ['required', 'string', 'max:50'],
            'visitor_id_number' => ['required', 'string', 'max:50'],
            'relationship_to_pdl' => ['required', 'string', 'max:100'],
            'visit_date' => ['required', 'date'],
            'time_slot' => ['required', 'string', 'max:50'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $booking = $this->route('booking');

            if (! $booking || $validator->errors()->has('visit_date')) {
                return;
            }

            $facilityId = $this->input('facility_id');
            $visitDate = $this->input('visit_date');
            $timeSlot = $this->input('time_slot');

            // Existing bookings must remain valid when a facility schedule changes.
            // Only validate the facility schedule when the schedule itself is being
            // changed by this update.
            $scheduleChanged = (int) $booking->facility_id !== (int) $facilityId
                || $booking->visit_date->toDateString() !== $visitDate
                || $booking->time_slot !== $timeSlot;

            if (! $scheduleChanged) {
                return;
            }

            $facility = Facility::find($facilityId);

            if ($facility && $visitDate && $timeSlot
                && $facility->isTimeSlotAvailableOnDate($visitDate, $timeSlot) === false) {
                $validator->errors()->add('time_slot', 'The selected time is outside the facility\'s configured visitation schedule.');
            }
        }];
    }
}
