<?php

namespace App\Http\Controllers;

use App\Services\MapConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class MapController extends Controller
{
    public function lab(MapConfigService $mapConfigService): View
    {
        return view('officer.road-segments.map-lab', [
            'mapConfig' => $mapConfigService->forFrontend(),
        ]);
    }

    public function reverseGeocode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $baseUrl = rtrim((string) config('map.geocoder.base_url'), '/');

        try {
            $response = Http::timeout((int) config('map.geocoder.timeout'))
                ->acceptJson()
                ->withOptions([
                    'verify' => config('map.geocoder.verify_ssl'),
                ])
                ->withHeaders([
                    'User-Agent' => (string) config('map.geocoder.user_agent'),
                ])
                ->get($baseUrl . '/reverse', [
                    'format' => 'jsonv2',
                    'lat' => $validated['lat'],
                    'lon' => $validated['lng'],
                    'accept-language' => config('map.geocoder.language'),
                    'email' => config('map.geocoder.email'),
                ]);
        } catch (ConnectionException) {
            return response()->json([
                'display_name' => null,
                'address' => [],
                'lat' => $validated['lat'],
                'lng' => $validated['lng'],
                'provider' => config('map.geocoder.provider'),
                'message' => 'Reverse geocoding service could not be reached from this environment.',
            ]);
        }

        if ($response->failed()) {
            return response()->json([
                'display_name' => null,
                'address' => [],
                'lat' => $validated['lat'],
                'lng' => $validated['lng'],
                'provider' => config('map.geocoder.provider'),
                'message' => 'Reverse geocoding service is currently unavailable.',
            ]);
        }

        $payload = $response->json();

        return response()->json([
            'display_name' => $payload['display_name'] ?? null,
            'address' => $payload['address'] ?? [],
            'lat' => $payload['lat'] ?? $validated['lat'],
            'lng' => $payload['lon'] ?? $validated['lng'],
            'provider' => config('map.geocoder.provider'),
        ]);
    }
}
