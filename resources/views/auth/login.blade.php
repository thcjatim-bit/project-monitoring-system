<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Masuk · PMS THC</title>
        <style>
            :root { color-scheme: light; font-family: "Segoe UI", system-ui, sans-serif; color: #172033; background: #f2f5f9; }
            *, *::before, *::after { box-sizing: border-box; }
            body { margin: 0; min-width: 320px; min-height: 100vh; display: grid; place-items: center; padding: 24px; background: #f2f5f9; }
            .login { width: min(100%, 420px); padding: clamp(24px, 6vw, 48px); border: 1px solid #dbe2eb; border-radius: 16px; background: #fff; box-shadow: 0 14px 38px rgb(28 45 72 / 8%); }
            .login__brand { margin: 0 0 8px; color: #3042c2; font-size: 1.2rem; font-weight: 800; letter-spacing: -.03em; }
            .login__brand span { color: #172033; }
            h1 { margin: 0; font-size: clamp(1.6rem, 5vw, 2rem); }
            .login__intro { margin: 8px 0 28px; color: #667085; line-height: 1.55; }
            .login__field { display: grid; gap: 8px; margin-top: 18px; }
            label { font-weight: 700; }
            input { width: 100%; padding: 12px 14px; border: 1px solid #bfc9d8; border-radius: 10px; color: #172033; font: inherit; }
            input:focus { border-color: #4656d8; outline: 3px solid #edf0ff; }
            input[aria-invalid="true"] { border-color: #c04d59; }
            .login__error { margin: 0; color: #c04d59; font-size: .88rem; }
            button { width: 100%; margin-top: 26px; padding: 13px 16px; border: 0; border-radius: 10px; color: #fff; background: #4656d8; font: inherit; font-weight: 800; cursor: pointer; }
            button:hover { background: #3042c2; }
            .responsive { color: #667085; font-size: .78rem; }
        </style>
    </head>
    <body>
        <main class="login" aria-labelledby="login-title">
            <p class="login__brand">PMS <span>THC</span></p>
            <h1 id="login-title">Masuk ke ruang kendali</h1>
            <p class="login__intro">Kelola Project, Material, dan aktivitas Mitra dengan akses yang sesuai peran Anda.</p>
            <form method="POST" action="{{ route('login.store') }}" novalidate>
                @csrf
                <div class="login__field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" aria-describedby="email-error">
                    @error('email') <p id="email-error" class="login__error" role="alert">{{ $message }}</p> @else <span id="email-error"></span> @enderror
                </div>
                <div class="login__field">
                    <label for="password">Kata sandi</label>
                    <input id="password" name="password" type="password" required autocomplete="current-password" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" aria-describedby="password-error">
                    @error('password') <p id="password-error" class="login__error" role="alert">{{ $message }}</p> @else <span id="password-error"></span> @enderror
                </div>
                <button type="submit">Masuk</button>
            </form>
            <p class="responsive">Responsive untuk desktop dan mobile.</p>
        </main>
    </body>
</html>
