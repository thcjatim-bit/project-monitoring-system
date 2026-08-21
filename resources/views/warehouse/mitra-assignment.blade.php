<x-layouts.app>
    <x-ui.page>
        <x-ui.page-header eyebrow="Warehouse" title="Assignment Warehouse Mitra" subtitle="Tugaskan User aktif hanya ke Warehouse milik Mitra Anda.">
            <x-slot:actions><x-ui.badge tone="neutral" label="Tenant scoped" /></x-slot:actions>
        </x-ui.page-header>

        @if (session('status')) <div class="ui-state ui-state--success" role="status">{{ session('status') }}</div> @endif
        <x-form-errors />
        @forelse ($warehouses as $warehouse)
            <x-ui.panel>
                <div class="ui-section-head"><div><h2>{{ $warehouse->nama }}</h2><p class="ui-help">{{ $warehouse->kode }} · User yang ditugaskan</p></div><x-ui.badge :tone="$warehouse->aktif ? 'done' : 'neutral'" :label="$warehouse->aktif ? 'Aktif' : 'Nonaktif'" /></div>
                <div class="ui-list">
                    @forelse ($warehouse->users as $user)
                        <div class="ui-list__item"><strong>{{ $user->name }}</strong><span class="ui-muted">{{ $user->email }}</span><form method="POST" action="{{ route('mitra.warehouses.unassign', [$warehouse, $user]) }}" data-submit-loading>@csrf @method('DELETE')<button class="ui-button ui-button--muted" type="submit">Hapus assignment</button></form></div>
                    @empty
                        <x-ui.empty-state title="Belum ada User yang ditugaskan." />
                    @endforelse
                </div>
                @if ($warehouse->aktif)
                    <form class="ui-form" method="POST" action="{{ route('mitra.warehouses.assign', $warehouse) }}" data-submit-loading>
                        @csrf
                        <label>User aktif<select name="user_id" required><option value="">Pilih User Mitra</option>@foreach ($users as $user)<option value="{{ $user->id }}">{{ $user->name }} · {{ $user->email }}</option>@endforeach</select></label>
                        <button class="ui-button" type="submit">Tugaskan User</button>
                    </form>
                @endif
            </x-ui.panel>
        @empty
            <x-ui.empty-state title="Belum ada Warehouse dalam cakupan Mitra Anda." />
        @endforelse
    </x-ui.page>
</x-layouts.app>
