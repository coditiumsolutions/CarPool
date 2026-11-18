<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Place;
use Illuminate\Database\Seeder;

/** Common areas per city for pickup/drop-off suggestions. Idempotent. Coordinates can be added later. */
class PlacesSeeder extends Seeder
{
    private const AREAS = [
        'Karachi' => ['Gulshan-e-Iqbal', 'Gulistan-e-Jauhar', 'Clifton', 'DHA Phase 5', 'Saddar', 'North Nazimabad', 'Nazimabad', 'PECHS', 'Korangi', 'Malir', 'Shahrah-e-Faisal', 'Bahadurabad', 'Tariq Road', 'Airport', 'Surjani Town'],
        'Hyderabad' => ['Latifabad', 'Qasimabad', 'Hirabad', 'Saddar', 'Hussainabad', 'Autobhan Road', 'Hyderabad Railway Station', 'Gulistan-e-Sajjad'],
        'Lahore' => ['Johar Town', 'DHA Phase 5', 'Gulberg', 'Model Town', 'Bahria Town', 'Wapda Town', 'Lahore Railway Station', 'Allama Iqbal Airport', 'Mall Road', 'Township', 'Faisal Town', 'Iqbal Town', 'Shadman'],
        'Islamabad' => ['G-11', 'F-10', 'F-7', 'I-8', 'E-11', 'Blue Area', 'DHA Islamabad', 'Bahria Town', 'Islamabad Airport', 'Faisal Mosque', 'H-13'],
        'Rawalpindi' => ['Saddar', 'Satellite Town', 'Bahria Town', 'Chaklala', 'Commercial Market', 'Rawalpindi Railway Station', 'Raja Bazaar', 'Westridge', 'Adiala Road'],
    ];

    public function run(): void
    {
        foreach (self::AREAS as $cityName => $areas) {
            $city = City::where('city_name', $cityName)->first();
            if (! $city) {
                continue;
            }
            foreach ($areas as $area) {
                Place::firstOrCreate(['city_id' => $city->id, 'name' => $area]);
            }
        }
    }
}
