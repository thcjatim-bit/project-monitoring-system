<x-layouts.app>
    <h1>Penugasan Warehouse</h1>
    @if (session('status')) <p>{{ session('status') }}</p> @endif
    @foreach($warehouses as $warehouse)
        <section><h2>{{ $warehouse->nama }} ({{ $warehouse->kode }})</h2>
            <form method="POST" action="{{ route('admin.warehouses.assign', $warehouse) }}">@csrf
                <select name="user_id">@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select>
                <button>Tugaskan</button>
            </form>
            <ul>@foreach($warehouse->users as $user)<li>{{ $user->name }} <form method="POST" action="{{ route('admin.warehouses.unassign', [$warehouse, $user]) }}" style="display:inline">@csrf @method('DELETE')<button>Hapus</button></form></li>@endforeach</ul>
        </section>
    @endforeach
</x-layouts.app>
