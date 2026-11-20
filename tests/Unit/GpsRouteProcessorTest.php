<?php

namespace Tests\Unit;

use App\Services\GpsRouteProcessorService;
use PHPUnit\Framework\TestCase;

class GpsRouteProcessorTest extends TestCase
{
    public function test_computes_haversine_distance_accurately(): void
    {
        $svc = new GpsRouteProcessorService();
        $km = $svc->haversineKm(13.6929, -89.2182, 13.7019, -89.2182);

        $this->assertGreaterThan(0.95, $km);
        $this->assertLessThan(1.05, $km);
    }
}
