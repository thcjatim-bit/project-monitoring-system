@props([
    'name',
    'id',
    'options' => [],
    'value' => '',
    'placeholder' => 'Pilih',
    'searchable' => true,
    'clearable' => false,
    'disabled' => false,
    'loading' => false,
    'emptyMessage' => 'Data tidak ditemukan',
])

@php
    $normalizedOptions = collect($options)->map(function (mixed $option, string|int $optionValue): array {
        if (is_array($option)) {
            return [
                'value' => (string) ($option['value'] ?? $optionValue),
                'label' => (string) ($option['label'] ?? $option['value'] ?? $optionValue),
                'search' => (string) ($option['search'] ?? $option['label'] ?? $optionValue),
            ];
        }

        return [
            'value' => (string) $optionValue,
            'label' => (string) $option,
            'search' => (string) $option,
        ];
    })->values();
    $selectedValue = (string) ($value ?? '');
    $selectedOption = $normalizedOptions->first(fn (array $option): bool => $option['value'] === $selectedValue);
@endphp

<div
    {{ $attributes->merge(['class' => 'ui-select']) }}
    data-ui-select
    data-placeholder="{{ $placeholder }}"
    data-clearable="{{ $clearable ? 'true' : 'false' }}"
    data-disabled="{{ $disabled ? 'true' : 'false' }}"
>
    <div class="ui-select__control">
        <button
            id="{{ $id }}"
            class="ui-select__trigger"
            type="button"
            data-ui-select-trigger
            aria-controls="{{ $id }}-listbox"
            aria-expanded="false"
            aria-haspopup="listbox"
            @disabled($disabled)
        >
            <span data-ui-select-label class="{{ $selectedOption ? '' : 'is-placeholder' }}">{{ $selectedOption['label'] ?? $placeholder }}</span>
            <span aria-hidden="true" class="ui-select__chevron">▾</span>
        </button>
        @if ($clearable)
            <button class="ui-select__clear" type="button" data-ui-select-clear aria-label="Hapus pilihan" @if (! $selectedOption || $disabled) hidden @endif>×</button>
        @endif
    </div>

    <input type="hidden" name="{{ $name }}" value="{{ $selectedValue }}" data-ui-select-value @disabled($disabled)>

    <div id="{{ $id }}-listbox" class="ui-select__popup" data-ui-select-popup role="listbox" aria-label="{{ $placeholder }}" hidden>
        @if ($searchable)
            <label class="ui-select__search">
                <span class="ui-sr-only">Cari {{ $placeholder }}</span>
                <input type="search" data-ui-select-search placeholder="Cari..." autocomplete="off">
            </label>
        @endif

        <div class="ui-select__options">
            @if ($loading)
                <div class="ui-select__state">Memuat data...</div>
            @elseif ($normalizedOptions->isEmpty())
                <div class="ui-select__state" data-ui-select-empty>{{ $emptyMessage }}</div>
            @else
                @foreach ($normalizedOptions as $option)
                    <button
                        class="ui-select__option"
                        type="button"
                        role="option"
                        data-ui-select-option
                        data-value="{{ $option['value'] }}"
                        data-label="{{ $option['label'] }}"
                        data-search-text="{{ $option['search'] }}"
                        aria-selected="{{ $option['value'] === $selectedValue ? 'true' : 'false' }}"
                    >{{ $option['label'] }}</button>
                @endforeach
                <div class="ui-select__state" data-ui-select-empty hidden>{{ $emptyMessage }}</div>
            @endif
        </div>
    </div>
</div>
