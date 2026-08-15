@extends('layouts.app')

@section('content')
<div class="flex items-center justify-center min-h-screen bg-surface">
    <div class="w-full max-w-md p-8 bg-white rounded-lg shadow">
        <h1 class="text-display mb-6">Reset Password</h1>

        @if (session('status'))
            <div class="mb-4 p-4 bg-success-subtle text-success rounded">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}">
            @csrf

            <div class="mb-4">
                <label for="email" class="block text-label mb-2">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                    class="w-full px-4 py-2 border border-border rounded focus:outline-none focus:ring-2 focus:ring-accent">
                @error('email')
                    <span class="text-danger text-caption">{{ $message }}</span>
                @enderror
            </div>

            <button type="submit" class="w-full px-4 py-2 bg-accent text-white rounded hover:opacity-90">
                Send Password Reset Link
            </button>
        </form>

        <div class="mt-6 text-center">
            <a href="{{ route('login') }}" class="text-accent hover:underline">Back to Login</a>
        </div>
    </div>
</div>
@endsection
