@props([
    'title',
    'message' => null,
])

<div {{ $attributes->merge(['class' => 'ui-state ui-empty-state']) }} role="status">
    <strong>{{ $title }}</strong>
    @if ($message)
        <span>{{ $message }}</span>
    @else
        {{ $slot }}
    @endif
</div>
