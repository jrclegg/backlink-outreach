<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Backlink Outreach</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-surface text-text-primary">
    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="w-full max-w-md">
            <div class="mb-8 text-center">
                <h1 class="text-display">Backlinks</h1>
                <p class="text-caption mt-2">Sign in to your account</p>
            </div>

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="text-label block mb-2">Email</label>
                    <input type="email" name="email" class="w-full px-4 py-2 border border-border rounded-lg text-body focus:outline-none focus:ring-2 focus:ring-accent" required value="{{ old('email') }}" />
                    @error('email')
                        <p class="text-danger text-caption mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="text-label block mb-2">Password</label>
                    <input type="password" name="password" class="w-full px-4 py-2 border border-border rounded-lg text-body focus:outline-none focus:ring-2 focus:ring-accent" required />
                    @error('password')
                        <p class="text-danger text-caption mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="w-full px-4 py-2 bg-accent text-white rounded-lg text-body hover:opacity-90 transition-opacity">
                    Sign In
                </button>
            </form>

            <div class="mt-6 text-center">
                <p class="text-caption">
                    Don't have an account?
                    <a href="{{ route('register') }}" class="text-accent hover:underline">Sign up</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
