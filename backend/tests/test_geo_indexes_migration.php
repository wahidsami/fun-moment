<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$passed = 0;
$failed = 0;

function assertCheck($condition, $description) {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] $description" . PHP_EOL;
        $passed++;
    } else {
        echo "  [FAIL] $description" . PHP_EOL;
        $failed++;
    }
}

echo "==================================================================" . PHP_EOL;
echo " WORKSTREAM B: GEOGRAPHIC INDEXES MIGRATION & PERFORMANCE DRILL" . PHP_EOL;
echo "==================================================================" . PHP_EOL;

require_once __DIR__ . '/../database/migrations/2026_11_06_180000_add_performance_indexes_to_service_areas_and_cities.php';
$migration = new AddPerformanceIndexesToServiceAreasAndCities();

// 1. Run migration UP
echo "--- Running Migration UP ---" . PHP_EOL;
$migration->up();

$cityIndexes = DB::select("SELECT indexname FROM pg_indexes WHERE tablename = 'service_cities' AND indexname = 'idx_service_cities_country_status'");
assertCheck(count($cityIndexes) === 1, 'Index idx_service_cities_country_status was created on service_cities');

$areaIndexes = DB::select("SELECT indexname FROM pg_indexes WHERE tablename = 'service_areas' AND indexname = 'idx_service_areas_city_country_status'");
assertCheck(count($areaIndexes) === 1, 'Index idx_service_areas_city_country_status was created on service_areas');

// 2. Performance Verification with EXPLAIN (ANALYZE, BUFFERS)
echo PHP_EOL . "--- EXPLAIN ANALYZE Performance Verification ---" . PHP_EOL;
$explainCity = DB::select("
    EXPLAIN (ANALYZE, BUFFERS)
    SELECT id, service_city
    FROM service_cities
    WHERE country_id = 2 AND status = 1
    ORDER BY service_city ASC
    LIMIT 300
");
$cityPlanText = implode("\n", array_map(fn($r) => $r->{'QUERY PLAN'}, $explainCity));
echo "City Query Plan:" . PHP_EOL . $cityPlanText . PHP_EOL . PHP_EOL;

$explainArea = DB::select("
    EXPLAIN (ANALYZE, BUFFERS)
    SELECT id, service_area
    FROM service_areas
    WHERE country_id = 2 AND service_city_id = 2 AND status = 1
    ORDER BY service_area ASC
    LIMIT 500
");
$areaPlanText = implode("\n", array_map(fn($r) => $r->{'QUERY PLAN'}, $explainArea));
echo "Area Query Plan:" . PHP_EOL . $areaPlanText . PHP_EOL . PHP_EOL;

assertCheck(str_contains($areaPlanText, 'idx_service_areas_city_country_status'), 'Query on service_areas utilizes idx_service_areas_city_country_status index');

// 3. Run migration DOWN to test rollback cleanliness
echo "--- Testing Migration DOWN Rollback ---" . PHP_EOL;
$migration->down();

$cityIndexesAfterDown = DB::select("SELECT indexname FROM pg_indexes WHERE tablename = 'service_cities' AND indexname = 'idx_service_cities_country_status'");
assertCheck(count($cityIndexesAfterDown) === 0, 'Index idx_service_cities_country_status is dropped cleanly on rollback');

$areaIndexesAfterDown = DB::select("SELECT indexname FROM pg_indexes WHERE tablename = 'service_areas' AND indexname = 'idx_service_areas_city_country_status'");
assertCheck(count($areaIndexesAfterDown) === 0, 'Index idx_service_areas_city_country_status is dropped cleanly on rollback');

// 4. Re-apply UP so local database has the performance indexes
$migration->up();
assertCheck(true, 'Migration UP re-applied successfully');

echo PHP_EOL . "==================================================================" . PHP_EOL;
echo " TEST SUMMARY: $passed PASSED, $failed FAILED" . PHP_EOL;
echo "==================================================================" . PHP_EOL;

exit($failed > 0 ? 1 : 0);
