@extends('layouts.app')

@section('content')
<div class="flex items-center justify-center min-h-screen bg-surface">
    <div class="w-full max-w-md p-8 bg-white rounded-lg shadow">
        <h1 class="text-display mb-6">Verify Email Address</h1>

        @if (session('status') == 'verification-link-sent')
            <div class="mb-4 p-4 bg-success-subtle text-success rounded">
                A new verification link has been sent to your email address.
            </div>
        @endif

        <p class="text-body mb-6">
            Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn't receive the email, we will gladly send you another.
        </p>

        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="w-full px-4 py-2 bg-accent text-white rounded hover:opacity-90 mb-4">
                Resend Verification Email
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="w-full px-4 py-2 border border-border rounded hover:bg-surface">
                Log Out
            </button>
        </form>
    </div>
</div>
@endsection
