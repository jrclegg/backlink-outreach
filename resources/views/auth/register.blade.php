<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Backlink Outreach</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-surface text-text-primary">
    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="w-full max-w-md">
            <div class="mb-8 text-center">
                <h1 class="text-display">Backlinks</h1>
                <p class="text-caption mt-2">Create your account</p>
            </div>

            <form method="POST" action="{{ route('register') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="text-label block mb-2">Name</label>
                    <input type="text" name="name" class="w-full px-4 py-2 border border-border rounded-lg text-body focus:outline-none focus:ring-2 focus:ring-accent" required value="{{ old('name') }}" />
                    @error('name')
                        <p class="text-danger text-caption mt-1">{{ $message }}</p>
                    @enderror
                </div>

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

                <div>
                    <label class="text-label block mb-2">Confirm Password</label>
                    <input type="password" name="password_confirmation" class="w-full px-4 py-2 border border-border rounded-lg text-body focus:outline-none focus:ring-2 focus:ring-accent" required />
                </div>

                <button type="submit" class="w-full px-4 py-2 bg-accent text-white rounded-lg text-body hover:opacity-90 transition-opacity">
                    Create Account
                </button>
            </form>

            <div class="mt-6 text-center">
                <p class="text-caption">
                    Already have an account?
                    <a href="{{ route('login') }}" class="text-accent hover:underline">Sign in</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
