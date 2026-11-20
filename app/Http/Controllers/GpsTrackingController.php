<?php

namespace App\Http\Controllers;

use App\Models\GpsSession;
use App\Services\GpsRouteProcessorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GpsTrackingController extends Controller
{
    public function map(GpsRouteProcessorService $processor): View
    {
        $session = GpsSession::query()->with(['route', 'pings'])->latest('id')->first();
        if (! $session) {
            $session = $processor->seedSyntheticDemo();
        }

        return view('map', [
            'session' => $session,
            'sessions' => GpsSession::query()->with('route')->latest('id')->get(),
        ]);
    }

    public function simulateNew(GpsRouteProcessorService $processor): RedirectResponse
    {
        $processor->seedSyntheticDemo();

        return redirect()->route('map.index');
    }

    public function sessionJson(GpsSession $session): JsonResponse
    {
        $session->load(['route', 'pings']);

        return response()->json(['data' => $session]);
    }

    public function ingest(GpsSession $session, Request $request, GpsRouteProcessorService $processor): JsonResponse
    {
        $validated = $request->validate([
            'pings' => ['required', 'array', 'min:1', 'max:200'],
            'pings.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'pings.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'pings.*.speed_kmh' => ['required', 'numeric', 'min:0'],
            'pings.*.accuracy_meters' => ['required', 'numeric', 'min:0'],
            'pings.*.heading' => ['nullable', 'numeric', 'between:0,360'],
            'pings.*.recorded_at' => ['required', 'date'],
        ]);

        $summary = $processor->ingestPings($session, $validated['pings']);

        return response()->json([
            'summary' => $summary,
            'session' => $session->fresh(['route', 'pings']),
        ]);
    }
}
