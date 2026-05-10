<div class="space-y-4 text-sm">
    <div class="grid grid-cols-2 gap-4">
        <div>
            <p class="font-semibold text-gray-500">Entité</p>
            <p>{{ $activity->subject_type ? class_basename($activity->subject_type) : '—' }} #{{ $activity->subject_id ?? '—' }}</p>
        </div>
        <div>
            <p class="font-semibold text-gray-500">Auteur</p>
            <p>{{ $activity->causer?->full_name ?? 'Système' }}</p>
        </div>
        <div>
            <p class="font-semibold text-gray-500">Événement</p>
            <p>{{ $activity->event ?? '—' }}</p>
        </div>
        <div>
            <p class="font-semibold text-gray-500">Date</p>
            <p>{{ $activity->created_at?->format('d/m/Y H:i:s') }}</p>
        </div>
    </div>

    @if($activity->properties && $activity->properties->isNotEmpty())
        @php
            $props = $activity->properties->toArray();
            $attributes = $props['attributes'] ?? null;
            $old = $props['old'] ?? null;
            $extra = collect($props)->except(['attributes', 'old'])->toArray();
        @endphp

        @if($attributes || $old)
            <div>
                <p class="font-semibold text-gray-500 mb-2">Modifications</p>
                <table class="w-full text-xs border-collapse">
                    <thead>
                        <tr class="bg-gray-100 dark:bg-gray-800">
                            <th class="text-left p-2 border border-gray-200 dark:border-gray-700">Champ</th>
                            @if($old)<th class="text-left p-2 border border-gray-200 dark:border-gray-700 text-danger-500">Avant</th>@endif
                            @if($attributes)<th class="text-left p-2 border border-gray-200 dark:border-gray-700 text-success-500">Après</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(array_keys(array_merge($attributes ?? [], $old ?? [])) as $field)
                            <tr class="border border-gray-200 dark:border-gray-700">
                                <td class="p-2 font-mono font-semibold">{{ $field }}</td>
                                @if($old)<td class="p-2 font-mono text-danger-600">{{ json_encode($old[$field] ?? null, JSON_UNESCAPED_UNICODE) }}</td>@endif
                                @if($attributes)<td class="p-2 font-mono text-success-600">{{ json_encode($attributes[$field] ?? null, JSON_UNESCAPED_UNICODE) }}</td>@endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if(!empty($extra))
            <div>
                <p class="font-semibold text-gray-500 mb-1">Contexte</p>
                <pre class="bg-gray-100 dark:bg-gray-800 rounded p-3 text-xs overflow-auto max-h-48">{{ json_encode($extra, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
        @endif
    @else
        <p class="text-gray-400 italic">Aucune propriété enregistrée.</p>
    @endif
</div>
