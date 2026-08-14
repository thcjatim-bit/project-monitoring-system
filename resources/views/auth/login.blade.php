<main>
    <h1>Masuk</h1>

    <form method="POST" action="{{ route('login.store') }}">
        @csrf
        <label>Email <input name="email" type="email" value="{{ old('email') }}" required autofocus></label>
        <label>Kata sandi <input name="password" type="password" required></label>
        @error('email') <p>{{ $message }}</p> @enderror
        <button type="submit">Masuk</button>
    </form>
</main>
