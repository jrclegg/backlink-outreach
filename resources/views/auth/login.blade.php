<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in · Outreach</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-canvas text-zinc-900 antialiased">
    <div class="flex min-h-screen items-center justify-center px-4 py-12">
        <div class="w-full max-w-sm">
            <div class="mb-8 flex flex-col items-center text-center">
                <div class="flex size-10 items-center justify-center rounded-xl bg-zinc-900 text-white"><x-icon name="campaigns" class="size-5" /></div>
                <h1 class="mt-4 text-xl font-semibold tracking-tight">Sign in to Outreach</h1>
                <p class="mt-1 text-sm text-zinc-500">Welcome back.</p>
            </div>

            <form method="POST" action="{{ route('login') }}" class="panel space-y-4 p-6">
                @csrf
                <label class="label">Email
                    <input type="email" name="email" class="field" required autofocus autocomplete="email" value="{{ old('email') }}" />
                    @error('email')<span class="hint block text-rose-700">{{ $message }}</span>@enderror
                </label>
                <label class="label">Password
                    <input type="password" name="password" class="field" required autocomplete="current-password" />
                    @error('password')<span class="hint block text-rose-700">{{ $message }}</span>@enderror
                </label>
                <button type="submit" class="btn-primary w-full">Sign in</button>
            </form>

            <p class="mt-6 text-center text-sm text-zinc-500">No account? <a href="{{ route('register') }}" class="link">Sign up</a></p>
        </div>
    </div>
</body>
</html>
