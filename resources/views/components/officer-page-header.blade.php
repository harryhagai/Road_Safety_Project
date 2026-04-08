@props([
    'title',
    'subtitle' => null,
])

<section class="officer-shared-page-header">
    <div class="officer-shared-page-header__content">
        <h1 class="officer-shared-page-header__title">{{ $title }}</h1>
        @if ($subtitle)
            <p class="officer-shared-page-header__subtitle">{{ $subtitle }}</p>
        @endif
    </div>
</section>
