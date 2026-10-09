<?php

namespace Database\Factories;

use App\Models\Facility;
use App\Models\FacilitySchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FacilitySchedule>
 */
class FacilityScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'facility_id' => Facility::factory(),
            'day_of_week' => 'Monday',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ];
    }
}
