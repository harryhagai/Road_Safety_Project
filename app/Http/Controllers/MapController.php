<?php

namespace App\Http\Controllers;

use App\Services\MapConfigService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
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

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $query = Str::of($validated['query'])->squish()->toString();
        $provider = (string) config('map.geocoder.autocomplete.provider', config('map.geocoder.provider'));
        $searchLimit = max(1, min(6, (int) config('map.geocoder.search_limit', 4)));
        $cacheKey = sprintf('map-search:%s:%s', $provider, md5(mb_strtolower($query)));

        $cachedPayload = Cache::get($cacheKey);

        if (is_array($cachedPayload)) {
            return response()->json($cachedPayload);
        }

        try {
            [$results, $resolvedProvider, $message] = $this->searchWithFallback($query, $searchLimit, $provider);
        } catch (ConnectionException) {
            return response()->json([
                'query' => $query,
                'results' => [],
                'provider' => $provider,
                'message' => 'Location search service could not be reached from this environment.',
            ]);
        }

        $responsePayload = [
            'query' => $query,
            'results' => $results,
            'provider' => $resolvedProvider,
            'message' => $message,
        ];

        Cache::put(
            $cacheKey,
            $responsePayload,
            now()->addMinutes($results !== [] ? (int) config('map.geocoder.cache_ttl_minutes', 15) : 5)
        );

        return response()->json($responsePayload);
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: string, 2: ?string}
     */
    private function searchWithFallback(string $query, int $searchLimit, string $primaryProvider): array
    {
        $providers = collect([$primaryProvider, 'nominatim'])
            ->filter()
            ->unique()
            ->values();

        foreach ($providers as $provider) {
            $response = $provider === 'locationiq'
                ? $this->searchLocationIq($query, $searchLimit)
                : $this->searchNominatim($query, $searchLimit);

            if (! $response || $response->failed()) {
                continue;
            }

            $results = $this->normalizeSearchResults($response->json(), $provider);

            if ($results !== []) {
                return [$results, $provider, $provider === $primaryProvider ? null : 'Search fallback provider used.'];
            }
        }

        return [[], $primaryProvider, 'Location search service is currently unavailable.'];
    }

    private function searchLocationIq(string $query, int $searchLimit): ?Response
    {
        $apiKey = (string) config('map.geocoder.autocomplete.api_key');

        if ($apiKey === '') {
            return null;
        }

        $baseUrl = rtrim((string) config('map.geocoder.autocomplete.base_url'), '/');

        return $this->searchHttpClient()->get($baseUrl . '/autocomplete', [
            'key' => $apiKey,
            'q' => $query,
            'limit' => $searchLimit,
            'normalizecity' => 1,
            'accept-language' => config('map.geocoder.language'),
            'dedupe' => 1,
            'countrycodes' => config('map.geocoder.autocomplete.countrycodes', 'tz'),
        ]);
    }

    private function searchNominatim(string $query, int $searchLimit): Response
    {
        $baseUrl = rtrim((string) config('map.geocoder.base_url'), '/');

        return $this->searchHttpClient()->get($baseUrl . '/search', [
            'format' => 'jsonv2',
            'q' => $query,
            'limit' => $searchLimit,
            'addressdetails' => 0,
            'accept-language' => config('map.geocoder.language'),
            'email' => config('map.geocoder.email'),
            'countrycodes' => config('map.geocoder.autocomplete.countrycodes', 'tz'),
        ]);
    }

    private function searchHttpClient()
    {
        return Http::timeout((int) config('map.geocoder.timeout'))
            ->acceptJson()
            ->withOptions([
                'verify' => config('map.geocoder.verify_ssl'),
            ])
            ->withHeaders([
                'User-Agent' => (string) config('map.geocoder.user_agent'),
            ]);
    }

    /**
     * @param mixed $rawPayload
     * @return array<int, array<string, mixed>>
     */
    private function normalizeSearchResults($rawPayload, string $provider): array
    {
        return collect($rawPayload)
            ->filter(fn ($item) => is_array($item))
            ->map(function (array $item) use ($provider) {
                $displayPlace = trim((string) ($item['display_place'] ?? ''));
                $displayAddress = trim((string) ($item['display_address'] ?? ''));
                $displayName = trim((string) ($item['display_name'] ?? ''));
                $label = $displayPlace !== '' ? $displayPlace : Str::before($displayName, ',');

                return [
                    'label' => $label !== '' ? $label : 'Unknown location',
                    'subtitle' => $displayAddress !== '' ? $displayAddress : $displayName,
                    'lat' => isset($item['lat']) ? (float) $item['lat'] : null,
                    'lng' => isset($item['lon']) ? (float) $item['lon'] : null,
                    'type' => $item['type'] ?? null,
                    'provider' => $provider,
                ];
            })
            ->filter(fn (array $item) => is_numeric($item['lat']) && is_numeric($item['lng']))
            ->values()
            ->all();
    }
}
