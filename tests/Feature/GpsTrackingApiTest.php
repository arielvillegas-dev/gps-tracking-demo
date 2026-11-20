<?php

namespace Tests\Feature;

use App\Models\GpsRoute;
use App\Models\GpsSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GpsTrackingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_processes_pings_detects_stops_speed_alerts_and_filters_noise(): void
    {
        $route = GpsRoute::query()->create([
            'code' => 'R-TEST-1',
            'name' => 'Test Corridor',
            'speed_limit_kmh' => 50,
            'planned_polyline_json' => [
                ['lat' => 13.6929, 'lng' => -89.2182],
                ['lat' => 13.6980, 'lng' => -89.2100],
            ],
        ]);

        $session = GpsSession::query()->create([
            'gps_route_id' => $route->id,
            'unit_code' => 'UNIT-01',
            'driver_name' => 'Demo Driver',
        ]);

        $res = $this->postJson("/api/v1/sessions/{$session->id}/pings", [
            'pings' => [
                ['latitude' => 13.6929, 'longitude' => -89.2182, 'speed_kmh' => 30, 'accuracy_meters' => 8, 'recorded_at' => '2026-11-20T10:00:00Z'],
                ['latitude' => 13.6955, 'longitude' => -89.2140, 'speed_kmh' => 0.0, 'accuracy_meters' => 6, 'recorded_at' => '2026-11-20T10:01:00Z'],
                ['latitude' => 13.6980, 'longitude' => -89.2100, 'speed_kmh' => 72, 'accuracy_meters' => 9, 'recorded_at' => '2026-11-20T10:02:00Z'],
                ['latitude' => 0.0, 'longitude' => 0.0, 'speed_kmh' => 10, 'accuracy_meters' => 5, 'recorded_at' => '2026-11-20T10:03:00Z'],
                ['latitude' => 13.6990, 'longitude' => -89.2090, 'speed_kmh' => 25, 'accuracy_meters' => 120, 'recorded_at' => '2026-11-20T10:04:00Z'],
            ],
        ]);

        $res->assertOk()
            ->assertJsonPath('summary.accepted', 3)
            ->assertJsonPath('summary.filtered_noise', 2)
            ->assertJsonPath('summary.stops_count', 1)
            ->assertJsonPath('summary.speed_alerts_count', 1);

        $this->assertGreaterThan(0.9, $res->json('summary.total_distance_km'));
    }
}
