@if ($errors->any())
    <div class="ui-state ui-state--error" role="alert" data-form-errors>
        <strong>Periksa kembali isian form.</strong>
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
