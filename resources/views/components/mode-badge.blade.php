@props(['mode'])
<span {{ $attributes->merge(['class' => 'badge '.match ($mode) { 'automatic' => 'badge-green', 'approval' => 'badge-teal', default => 'badge-zinc' }]) }}>{{ match ($mode) { 'automatic' => 'Automatic', 'approval' => 'Approval', default => 'Off' } }}</span>
