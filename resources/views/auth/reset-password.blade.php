@extends('layouts.app')

@section('content')
<div class="flex items-center justify-center min-h-screen bg-surface">
    <div class="w-full max-w-md p-8 bg-white rounded-lg shadow">
        <h1 class="text-display mb-6">Reset Password</h1>

        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div class="mb-4">
                <label for="email" class="block text-label mb-2">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email', $request->email) }}" required autofocus
                    class="w-full px-4 py-2 border border-border rounded focus:outline-none focus:ring-2 focus:ring-accent">
                @error('email')
                    <span class="text-danger text-caption">{{ $message }}</span>
                @enderror
            </div>

            <div class="mb-4">
                <label for="password" class="block text-label mb-2">Password</label>
                <input type="password" id="password" name="password" required
                    class="w-full px-4 py-2 border border-border rounded focus:outline-none focus:ring-2 focus:ring-accent">
                @error('password')
                    <span class="text-danger text-caption">{{ $message }}</span>
                @enderror
            </div>

            <div class="mb-6">
                <label for="password_confirmation" class="block text-label mb-2">Confirm Password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required
                    class="w-full px-4 py-2 border border-border rounded focus:outline-none focus:ring-2 focus:ring-accent">
                @error('password_confirmation')
                    <span class="text-danger text-caption">{{ $message }}</span>
                @enderror
            </div>

            <button type="submit" class="w-full px-4 py-2 bg-accent text-white rounded hover:opacity-90">
                Reset Password
            </button>
        </form>
    </div>
</div>
@endsection
