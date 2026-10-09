<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\FacilitySchedule;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FacilityScheduleSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     *
     * Every facility receives the same sensible default schedule: no visitation on
     * Monday, 1:00 PM-5:00 PM Tuesday through Friday, and two periods on weekends.
     * Schedules are facility-specific records, so each facility can be adjusted
     * independently afterwards.
     */
    public function run(): void
    {
        $defaultSchedule = [
            'Tuesday' => [['13:00', '17:00']],
            'Wednesday' => [['13:00', '17:00']],
            'Thursday' => [['13:00', '17:00']],
            'Friday' => [['13:00', '17:00']],
            'Saturday' => [['08:00', '12:00'], ['13:00', '17:00']],
            'Sunday' => [['08:00', '12:00'], ['13:00', '17:00']],
        ];

        foreach (Facility::query()->orderBy('id')->get() as $facility) {
            foreach ($defaultSchedule as $day => $periods) {
                foreach ($periods as $period) {
                    FacilitySchedule::firstOrCreate(
                        [
                            'facility_id' => $facility->id,
                            'day_of_week' => $day,
                            'start_time' => $period[0],
                            'end_time' => $period[1],
                        ],
                        [
                            'facility_id' => $facility->id,
                            'day_of_week' => $day,
                            'start_time' => $period[0],
                            'end_time' => $period[1],
                        ]
                    );
                }
            }
        }
    }
}
