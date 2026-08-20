@props([
    'target',
    'label' => 'Cari',
    'placeholder' => 'Cari kode atau nama',
])

<div {{ $attributes->merge(['class' => 'ui-toolbar']) }}>
    <label class="ui-search">
        <span class="ui-sr-only">{{ $label }}</span>
        <input type="search" data-ui-search="{{ $target }}" placeholder="{{ $placeholder }}" aria-label="{{ $label }}">
    </label>
</div>

@once
    <script>
        (() => {
            const update = (input) => {
                const query = input.value.trim().toLowerCase();
                document.querySelectorAll(input.dataset.uiSearch).forEach((row) => {
                    row.hidden = query !== '' && !(row.dataset.searchText || '').toLowerCase().includes(query);
                });
            };

            document.querySelectorAll('[data-ui-search]').forEach((input) => {
                input.addEventListener('input', () => update(input));
            });
        })();
    </script>
@endonce
