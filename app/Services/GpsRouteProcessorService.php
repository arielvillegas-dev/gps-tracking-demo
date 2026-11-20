<?php

namespace App\Services;

use App\Models\GpsPing;
use App\Models\GpsRoute;
use App\Models\GpsSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class GpsRouteProcessorService
{
    public const MAX_ACCURACY_METERS = 45.0;
    public const MAX_PLAUSIBLE_SPEED_KMH = 180.0;

    public function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * (sin($dLon / 2) ** 2);

        return $earthRadiusKm * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    /**
     * Ingests a batch of simulated or live GPS pings, filtering corrupted fixes,
     * detecting stops, speed alerts, and deviation from the planned polyline.
     *
     * @param  array<int, array<string, mixed>>  $pings
     * @return array{accepted: int, filtered_noise: int, total_distance_km: float, stops_count: int, speed_alerts_count: int}
     */
    public function ingestPings(GpsSession $session, array $pings): array
    {
        $session->loadMissing('route');
        $speedLimit = (float) $session->route->speed_limit_kmh;
        $planned = $session->route->planned_polyline_json;

        return DB::transaction(function () use ($session, $pings, $speedLimit, $planned) {
            $accepted = 0;
            $filtered = 0;
            $prevPing = GpsPing::query()
                ->where('gps_session_id', $session->id)
                ->latest('recorded_at')
                ->first();

            foreach ($pings as $raw) {
                $lat = (float) ($raw['latitude'] ?? 0);
                $lon = (float) ($raw['longitude'] ?? 0);
                $acc = (float) ($raw['accuracy_meters'] ?? 999);
                $speed = (float) ($raw['speed_kmh'] ?? 0);
                $recordedAt = CarbonImmutable::parse((string) $raw['recorded_at']);

                if (($lat === 0.0 && $lon === 0.0) || $acc <= 0 || $acc > self::MAX_ACCURACY_METERS) {
                    $filtered++;
                    continue;
                }

                if ($prevPing !== null) {
                    $seconds = max(1, $recordedAt->diffInSeconds($prevPing->recorded_at, true));
                    $distKm = $this->haversineKm($prevPing->latitude, $prevPing->longitude, $lat, $lon);
                    $impliedKmh = ($distKm / $seconds) * 3600.0;
                    if ($distKm > 0.05 && $impliedKmh > self::MAX_PLAUSIBLE_SPEED_KMH) {
                        $filtered++;
                        continue;
                    }
                }

                $isStop = $speed <= 2.0;
                $isSpeedAlert = $speed > $speedLimit;
                $deviationMeters = $this->minDistanceToPolylineMeters($lat, $lon, $planned);

                $created = GpsPing::query()->create([
                    'gps_session_id' => $session->id,
                    'latitude' => $lat,
                    'longitude' => $lon,
                    'speed_kmh' => $speed,
                    'accuracy_meters' => $acc,
                    'heading' => (float) ($raw['heading'] ?? 0),
                    'is_stop' => $isStop,
                    'is_speed_alert' => $isSpeedAlert,
                    'route_deviation_meters' => $deviationMeters,
                    'recorded_at' => $recordedAt,
                ]);
                $prevPing = $created;
                $accepted++;
            }

            $allPings = GpsPing::query()
                ->where('gps_session_id', $session->id)
                ->orderBy('recorded_at')
                ->get();

            $totalKm = 0.0;
            for ($i = 1; $i < $allPings->count(); $i++) {
                $totalKm += $this->haversineKm(
                    $allPings[$i - 1]->latitude,
                    $allPings[$i - 1]->longitude,
                    $allPings[$i]->latitude,
                    $allPings[$i]->longitude
                );
            }

            $stops = $allPings->where('is_stop', true)->count();
            $alerts = $allPings->where('is_speed_alert', true)->count();

            $session->update([
                'total_distance_km' => round($totalKm, 3),
                'stops_count' => $stops,
                'speed_alerts_count' => $alerts,
                'filtered_noise_count' => $session->filtered_noise_count + $filtered,
            ]);

            return [
                'accepted' => $accepted,
                'filtered_noise' => $filtered,
                'total_distance_km' => round($totalKm, 3),
                'stops_count' => $stops,
                'speed_alerts_count' => $alerts,
            ];
        });
    }

    public function seedSyntheticDemo(): GpsSession
    {
        $route = GpsRoute::query()->firstOrCreate(
            ['code' => 'R-CORRIDOR-01'],
            [
                'name' => 'Metropolitan Corridor Loop (Synthetic)',
                'speed_limit_kmh' => 55.0,
                'planned_polyline_json' => [
                    ['lat' => 13.6929, 'lng' => -89.2182],
                    ['lat' => 13.6960, 'lng' => -89.2130],
                    ['lat' => 13.6995, 'lng' => -89.2080],
                    ['lat' => 13.7025, 'lng' => -89.2030],
                    ['lat' => 13.7060, 'lng' => -89.1985],
                ],
            ]
        );

        $session = GpsSession::query()->create([
            'gps_route_id' => $route->id,
            'unit_code' => 'UNIT-'.random_int(100, 999),
            'driver_name' => 'Synthetic Operator',
            'status' => 'active',
        ]);

        $t0 = CarbonImmutable::now()->subMinutes(10);
        $this->ingestPings($session, [
            ['latitude' => 13.6929, 'longitude' => -89.2182, 'speed_kmh' => 32, 'accuracy_meters' => 7, 'heading' => 55, 'recorded_at' => $t0->toIso8601String()],
            ['latitude' => 13.6961, 'longitude' => -89.2129, 'speed_kmh' => 45, 'accuracy_meters' => 8, 'heading' => 56, 'recorded_at' => $t0->addSeconds(60)->toIso8601String()],
            ['latitude' => 13.6995, 'longitude' => -89.2080, 'speed_kmh' => 0.5, 'accuracy_meters' => 6, 'heading' => 56, 'recorded_at' => $t0->addSeconds(120)->toIso8601String()],
            ['latitude' => 13.7027, 'longitude' => -89.2028, 'speed_kmh' => 68, 'accuracy_meters' => 9, 'heading' => 52, 'recorded_at' => $t0->addSeconds(180)->toIso8601String()],
            ['latitude' => 13.7060, 'longitude' => -89.1985, 'speed_kmh' => 28, 'accuracy_meters' => 7, 'heading' => 50, 'recorded_at' => $t0->addSeconds(240)->toIso8601String()],
            ['latitude' => 0.0, 'longitude' => 0.0, 'speed_kmh' => 0, 'accuracy_meters' => 5, 'heading' => 0, 'recorded_at' => $t0->addSeconds(260)->toIso8601String()],
        ]);

        return $session->fresh(['route', 'pings']);
    }

    /**
     * @param  array<int, array{lat: float, lng: float}>  $polyline
     */
    private function minDistanceToPolylineMeters(float $lat, float $lng, array $polyline): float
    {
        if (empty($polyline)) {
            return 0.0;
        }
        $minKm = PHP_FLOAT_MAX;
        foreach ($polyline as $vertex) {
            $d = $this->haversineKm($lat, $lng, (float) $vertex['lat'], (float) $vertex['lng']);
            if ($d < $minKm) {
                $minKm = $d;
            }
        }

        return round($minKm * 1000.0, 2);
    }
}
