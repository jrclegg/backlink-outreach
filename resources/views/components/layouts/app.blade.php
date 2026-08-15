<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Backlink Outreach' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-surface text-text-primary">
    <div class="flex h-screen">
        <!-- Left Navigation -->
        <aside class="w-64 border-r border-border bg-surface flex flex-col">
            <!-- Logo / Org -->
            <div class="px-8 py-8 border-b border-border">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-heading">Backlinks</h1>
                        @if(auth()->check() && auth()->user()->currentOrganization)
                            <p class="text-caption mt-1">{{ auth()->user()->currentOrganization->name }}</p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Navigation Menu -->
            <nav class="flex-1 overflow-y-auto px-4 py-6 space-y-1">
                @if(auth()->check())
                    <a href="{{ route('dashboard') }}" wire:navigate class="block px-4 py-2 rounded-lg text-body hover:bg-surface-inset transition-colors">Dashboard</a>
                    <a href="{{ route('projects.index') }}" wire:navigate class="block px-4 py-2 rounded-lg text-body hover:bg-surface-inset transition-colors">Projects</a>
                    <a href="{{ route('inboxes.index') }}" wire:navigate class="block px-4 py-2 rounded-lg text-body hover:bg-surface-inset transition-colors">Email Inboxes</a>
                @endif
            </nav>

            <!-- User Menu -->
            <div class="border-t border-border px-4 py-6 space-y-2">
                @if(auth()->check())
                    <div class="text-caption">{{ auth()->user()->name }}</div>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="w-full text-left px-4 py-2 text-body text-danger hover:bg-danger-subtle rounded-lg transition-colors">Logout</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" wire:navigate class="block px-4 py-2 rounded-lg text-body hover:bg-surface-inset transition-colors">Login</a>
                @endif
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto">
            <div class="max-w-6xl mx-auto px-8 py-8">
                <!-- Page Header -->
                @if(isset($slot))
                    {{ $slot }}
                @endif
            </div>
        </main>
    </div>

    @livewireScripts
</body>
</html>
