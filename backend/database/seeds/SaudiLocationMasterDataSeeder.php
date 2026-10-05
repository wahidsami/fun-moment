<?php

namespace Database\Seeders;

use App\Country;
use App\ServiceCity;
use App\ServiceArea;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SaudiLocationMasterDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $dataFile = database_path('data/saudi_cities_districts.json');

        if (!File::exists($dataFile)) {
            $this->command->error("Data file not found: {$dataFile}");
            return;
        }

        $payload = json_decode(File::get($dataFile), true);
        if (!isset($payload['cities']) || !is_array($payload['cities'])) {
            $this->command->error("Invalid data structure in {$dataFile}");
            return;
        }

        DB::transaction(function () use ($payload) {
            // 1. Authoritative Saudi Arabia Country record (ID: 2)
            $country = Country::firstOrNew(['id' => 2]);
            $country->country = 'Saudi Arabia';
            $country->status = 1;
            if (empty($country->country_code)) {
                $country->country_code = 'SA';
            }
            $country->save();

            // 2. Authoritative Riyadh City record (ID: 2)
            $riyadh = ServiceCity::firstOrNew(['id' => 2]);
            $riyadh->service_city = 'Riyadh';
            $riyadh->country_id = 2;
            $riyadh->status = 1;
            $riyadh->save();

            // 3. Authoritative Olaya Area record under Riyadh (ID: 2)
            $olaya = ServiceArea::firstOrNew(['id' => 2]);
            $olaya->service_area = 'Olaya';
            $olaya->service_city_id = 2;
            $olaya->country_id = 2;
            $olaya->status = 1;
            $olaya->save();

            $cities = $payload['cities'];
            $totalCitiesAdded = 0;
            $totalDistrictsAdded = 0;

            foreach ($cities as $cityData) {
                $cityNameEn = trim($cityData['name_en'] ?? '');
                if (empty($cityNameEn)) {
                    continue;
                }

                if (strcasecmp($cityNameEn, 'Riyadh') === 0) {
                    $cityId = 2;
                } else {
                    $city = ServiceCity::where('country_id', 2)
                        ->whereRaw('LOWER(service_city) = ?', [strtolower($cityNameEn)])
                        ->first();

                    if (!$city) {
                        $city = ServiceCity::create([
                            'service_city' => $cityNameEn,
                            'country_id' => 2,
                            'status' => 1,
                        ]);
                        $totalCitiesAdded++;
                    }
                    $cityId = $city->id;
                }

                // Existing area names for this city to prevent duplicates
                $existingAreas = ServiceArea::where('service_city_id', $cityId)
                    ->pluck('service_area')
                    ->map(fn($n) => strtolower(trim($n)))
                    ->flip()
                    ->all();

                $districts = $cityData['districts'] ?? [];
                $districtsToInsert = [];
                $now = now();

                foreach ($districts as $distData) {
                    $distNameEn = trim($distData['name_en'] ?? '');
                    if (empty($distNameEn)) {
                        continue;
                    }

                    $distKey = strtolower($distNameEn);

                    // Skip if district already exists for this city
                    if (isset($existingAreas[$distKey])) {
                        continue;
                    }

                    // For Riyadh, also avoid adding 'Al Olaya' if 'Olaya' exists
                    if ($cityId === 2 && in_array($distKey, ['olaya', 'al olaya'], true) && isset($existingAreas['olaya'])) {
                        continue;
                    }

                    $existingAreas[$distKey] = true;

                    $districtsToInsert[] = [
                        'service_area' => $distNameEn,
                        'service_city_id' => $cityId,
                        'country_id' => 2,
                        'status' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (!empty($districtsToInsert)) {
                    ServiceArea::insert($districtsToInsert);
                    $totalDistrictsAdded += count($districtsToInsert);
                }
            }

            // Sync PostgreSQL sequences if running under pgsql driver
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("SELECT setval(pg_get_serial_sequence('service_cities', 'id'), COALESCE((SELECT MAX(id) FROM service_cities), 1))");
                DB::statement("SELECT setval(pg_get_serial_sequence('service_areas', 'id'), COALESCE((SELECT MAX(id) FROM service_areas), 1))");
            }

            if (isset($this->command)) {
                $this->command->info("Saudi location master data seeded successfully.");
                $this->command->info("New cities: {$totalCitiesAdded}, New districts: {$totalDistrictsAdded}");
            }
        });
    }
}
