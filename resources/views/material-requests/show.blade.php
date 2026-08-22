<x-layouts.app>
    <main>
        <p><a href="{{ route('material-requests.index') }}">&larr; Kembali ke Request Material</a></p>
        <h1>Request Material #{{ $materialRequest->id }}</h1>
        <p>Status: <strong>{{ $materialRequest->status }}</strong></p>

        @if ($materialRequest->mitra)
            <p>Mitra: {{ $materialRequest->mitra->nama }}</p>
        @endif
        @if ($materialRequest->project)
            <p>Project: {{ $materialRequest->project->id_project }} — {{ $materialRequest->project->nama }}</p>
        @endif
        @if ($materialRequest->catatan)
            <p>Catatan: {{ $materialRequest->catatan }}</p>
        @endif
        @if ($materialRequest->decision_note)
            <p>Alasan keputusan: {{ $materialRequest->decision_note }}</p>
        @endif
        @if ($materialRequest->decider)
            <p>Diputuskan oleh: {{ $materialRequest->decider->name }}</p>
        @endif

        <h2>Material yang diminta</h2>
        <ul>
            @forelse ($materialRequest->items as $item)
                <li>{{ $item->material->nama }}: {{ $item->qty }} {{ $item->material->unit->nama }}</li>
            @empty
                <li>Belum ada item material.</li>
            @endforelse
        </ul>
    </main>
</x-layouts.app>
