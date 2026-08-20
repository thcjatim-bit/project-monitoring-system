@props([
    'eyebrow' => null,
    'title',
    'subtitle' => null,
])

<header class="ui-page__header">
    <div>
        @if ($eyebrow)
            <p class="ui-page__eyebrow">{{ $eyebrow }}</p>
        @endif
        <h1>{{ $title }}</h1>
        @if ($subtitle)
            <p class="ui-page__subtitle">{{ $subtitle }}</p>
        @endif
    </div>
    @if (isset($actions))
        <div class="ui-page__actions">{{ $actions }}</div>
    @endif
</header>
