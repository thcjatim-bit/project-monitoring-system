@props([
    'tone' => 'neutral',
    'label' => null,
])

<span {{ $attributes->class(['ui-badge', 'ui-badge--'.$tone]) }}>{{ $label ?? $slot }}</span>
