{{-- Reusable Blade component used across multiple RSRS pages. --}}

@props([
    'id' => 'geoMap',
    'height' => '420px',
    'config' => [],
    'mode' => 'picker',
    'showToolbar' => true,
])

<div class="geo-map-shell" data-map-shell>
    @if ($showToolbar)
        <div class="geo-map-toolbar">
            <div>
                <div class="geo-map-toolbar__label">Selected coordinates</div>
                <div class="geo-map-toolbar__coords" data-map-coordinates>Click on the map to choose a location</div>
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-map-recenter>
                <i class="bi bi-crosshair me-1"></i> Recenter
            </button>
        </div>
    @endif

    <div
        id="{{ $id }}"
        class="geo-map-canvas"
        style="height: {{ $height }};"
        data-map-root
        data-map-mode="{{ $mode }}"
        data-map-config='@json($config)'
    ></div>
</div>
