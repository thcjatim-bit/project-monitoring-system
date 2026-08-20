@props(['class' => ''])

<main {{ $attributes->class(['ui-page', $class]) }}>
    {{ $slot }}
</main>
