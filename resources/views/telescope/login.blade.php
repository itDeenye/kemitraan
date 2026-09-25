<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <title>Login DNY Telescope</title>
    <link rel="stylesheet" href="{{ asset('css/telescope-login.css') }}">
</head>
<body>
<main>
    <div class="brand">
        <img src="{{ asset('favicon.ico') }}" alt="DNY Skincare">
        <h1>DNY Telescope</h1>
    </div>
    <p>Masuk menggunakan akun administrator DNY yang aktif.</p>

    <form method="POST" action="{{ route('telescope.login.store') }}">
        @csrf

        <label for="username">Username</label>
        <input id="username" name="username" value="{{ old('username') }}" maxlength="50" autocomplete="username" required autofocus>
        @error('username')
            <div class="error">{{ $message }}</div>
        @enderror

        <label for="password">Kata sandi</label>
        <input id="password" name="password" type="password" minlength="8" autocomplete="current-password" required>
        @error('password')
            <div class="error">{{ $message }}</div>
        @enderror

        <button type="submit">Masuk ke DNY Telescope</button>
    </form>
</main>
</body>
</html>
