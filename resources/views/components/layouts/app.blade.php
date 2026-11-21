<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ isset($title) ? $title.' · Outreach' : 'Outreach' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
@php
    $nav = [
        ['Home', 'dashboard', 'home', 'dashboard'],
        ['AI campaigns', 'outreach.index', 'campaigns', 'outreach.*'],
        ['Projects', 'projects.index', 'projects', 'projects.*'],
        ['Unsorted mail', 'mail.unassigned', 'inbox', 'mail.*'],
    ];
    $setup = [
        ['Email accounts', 'inboxes.index', 'mail', 'inboxes.*'],
        ['AI settings', 'ai.settings', 'sparkles', 'ai.*'],
        ['Opt-outs', 'suppressions.index', 'block', 'suppressions.*'],
    ];
    $older = [
        ['Prospects', 'prospects.index', 'users', 'prospects.*'],
        ['Find prospects', 'discover.index', 'search', 'discover.*'],
        ['Backlink search', 'backlink-graph.search', 'link', 'backlink-graph.*'],
        ['Email sequences', 'campaigns.create', 'queue', 'campaigns.*'],
    ];
    $olderOpen = collect($older)->contains(fn ($i) => request()->routeIs($i[3]));
@endphp
<body class="bg-canvas text-zinc-900 antialiased" x-data="{ menu: false }">
    <div class="flex min-h-screen">
        <!-- Mobile backdrop -->
        <div x-show="menu" x-transition.opacity x-cloak @click="menu = false" class="fixed inset-0 z-30 bg-zinc-900/30 md:hidden"></div>

        <aside :data-open="menu" class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-border bg-white transition-transform max-md:-translate-x-full max-md:data-open:translate-x-0 md:sticky md:top-0 md:h-screen md:w-60">
            <div class="flex items-center gap-2.5 px-5 h-16 shrink-0">
                <div class="flex size-8 items-center justify-center rounded-lg bg-zinc-900 text-white"><x-icon name="campaigns" class="size-4" /></div>
                <div class="min-w-0">
                    <p class="text-sm font-semibold leading-tight">Outreach</p>
                    @if(auth()->check() && auth()->user()->currentOrganization)
                        <p class="truncate text-xs text-zinc-500 leading-tight">{{ auth()->user()->currentOrganization->name }}</p>
                    @endif
                </div>
                <button @click="menu = false" class="btn-ghost btn-sm ml-auto px-2 md:hidden" aria-label="Close menu"><x-icon name="x" /></button>
            </div>

            @auth
            <nav class="flex-1 overflow-y-auto px-3 pb-4" aria-label="Main">
                <div class="space-y-0.5">
                    @foreach($nav as [$label, $route, $icon, $pattern])
                        <a href="{{ route($route) }}" wire:navigate class="nav-item {{ request()->routeIs($pattern) ? 'is-active' : '' }}"><x-icon :name="$icon" />{{ $label }}</a>
                    @endforeach
                </div>
                <p class="nav-heading">Setup</p>
                <div class="space-y-0.5">
                    @foreach($setup as [$label, $route, $icon, $pattern])
                        <a href="{{ route($route) }}" wire:navigate class="nav-item {{ request()->routeIs($pattern) ? 'is-active' : '' }}"><x-icon :name="$icon" />{{ $label }}</a>
                    @endforeach
                </div>
                <details class="group mt-1" @if($olderOpen) open @endif>
                    <summary class="nav-heading flex items-center gap-1 list-none hover:text-zinc-600">Older tools<x-icon name="chevron-right" class="size-3 transition-transform group-open:rotate-90" /></summary>
                    <div class="space-y-0.5">
                        @foreach($older as [$label, $route, $icon, $pattern])
                            <a href="{{ route($route) }}" wire:navigate class="nav-item {{ request()->routeIs($pattern) ? 'is-active' : '' }}"><x-icon :name="$icon" />{{ $label }}</a>
                        @endforeach
                    </div>
                </details>
            </nav>

            <div class="flex items-center gap-2.5 border-t border-border px-4 py-3">
                <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-xs font-semibold text-zinc-600">{{ collect(explode(' ', auth()->user()->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}</div>
                <p class="min-w-0 flex-1 truncate text-sm font-medium">{{ auth()->user()->name }}</p>
                <form action="{{ route('logout') }}" method="POST">@csrf<button type="submit" class="btn-ghost btn-sm px-2" title="Log out" aria-label="Log out"><x-icon name="logout" /></button></form>
            </div>
            @endauth
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            @auth
            <header class="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-border bg-white/90 px-4 backdrop-blur md:hidden">
                <button @click="menu = true" class="btn-ghost btn-sm px-2" aria-label="Open menu"><x-icon name="menu" class="size-5" /></button>
                <p class="text-sm font-semibold">{{ $title ?? 'Outreach' }}</p>
            </header>
            @endauth
            <main class="flex-1">
                <div class="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6 md:py-10 lg:px-10">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>
    <style>[x-cloak]{display:none!important}</style>
    @livewireScripts
</body>
</html>
