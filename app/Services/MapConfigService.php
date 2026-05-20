<?php

namespace App\Services;

/**
 * Service class that centralizes MapConfigService business logic.
 */
class MapConfigService
{
    /**
     * Build a frontend-safe map configuration payload.
     *
     * @return array<string, mixed>
     */
    public function forFrontend(): array
    {
        return [
            'defaultCenter' => [
                'lat' => config('map.default_center.lat'),
                'lng' => config('map.default_center.lng'),
            ],
            'defaultZoom' => config('map.default_zoom'),
            'minZoom' => config('map.min_zoom'),
            'maxZoom' => config('map.max_zoom'),
            'tiles' => [
                'url' => config('map.tiles.url'),
                'attribution' => config('map.tiles.attribution'),
            ],
            'reverseGeocodeUrl' => route('maps.reverse-geocode'),
            'searchUrl' => route('maps.search'),
            'provider' => config('map.geocoder.provider'),
        ];
    }
}
