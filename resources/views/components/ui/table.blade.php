@props(['items' => [], 'columns' => []])

<div class="overflow-x-auto border border-border rounded-lg">
    <table class="w-full">
        <thead>
            <tr class="border-b border-border bg-surface-muted">
                @foreach($columns as $column)
                    <th class="px-4 h-12 text-left text-label align-middle">
                        {{ $column['label'] ?? ucfirst($column) }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>
