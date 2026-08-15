@extends('layouts.app')

@section('content')
<div class="flex items-center justify-center min-h-screen bg-surface">
    <div class="w-full max-w-md p-8 bg-white rounded-lg shadow">
        <h1 class="text-display mb-6">Two Factor Challenge</h1>

        <form method="POST" action="{{ route('two-factor.login') }}">
            @csrf

            <div class="mb-4">
                <label for="code" class="block text-label mb-2">Authentication Code</label>
                <input type="text" id="code" name="code" inputmode="numeric" autofocus
                    class="w-full px-4 py-2 border border-border rounded focus:outline-none focus:ring-2 focus:ring-accent">
                @error('code')
                    <span class="text-danger text-caption">{{ $message }}</span>
                @enderror
            </div>

            <button type="submit" class="w-full px-4 py-2 bg-accent text-white rounded hover:opacity-90">
                Verify
            </button>
        </form>

        <div class="mt-6">
            <form method="POST" action="{{ route('two-factor.login') }}">
                @csrf
                <button type="submit" name="recovery" value="true" class="w-full px-4 py-2 border border-border rounded hover:bg-surface">
                    Use a recovery code
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
