<div>
{{-- Styles scoped au composant — préfixe tc-* pour éviter tout conflit --}}
<style>
    .tc-wrap { display: flex; flex-direction: column; gap: 1.5rem; font-family: ui-sans-serif, system-ui, sans-serif; }

    .tc-header { padding-bottom: 1rem; border-bottom: 1px solid #e5e7eb; }
    .tc-header h2 { font-size: 1rem; font-weight: 600; color: #111827; margin: 0 0 0.25rem 0; }
    .tc-header-meta { display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; color: #6b7280; }
    .tc-header-meta .sep { color: #d1d5db; }

    .tc-badges { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.75rem; }
    .tc-badge { display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; }
    .tc-badge svg { width: 14px; height: 14px; flex-shrink: 0; stroke-width: 2; }
    .tc-badge-info { background: #eff6ff; color: #1d4ed8; box-shadow: inset 0 0 0 1px #dbeafe; }
    .tc-badge-warn { background: #fffbeb; color: #b45309; box-shadow: inset 0 0 0 1px #fde68a; }
    .tc-badge-danger { background: #fef2f2; color: #b91c1c; box-shadow: inset 0 0 0 1px #fecaca; font-weight: 600; }

    .tc-section + .tc-section { margin-top: 1.5rem; }
    .tc-section-head { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem; }
    .tc-section-title { font-size: 0.6875rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.06em; margin: 0; }
    .tc-section-count { background: #f3f4f6; color: #4b5563; font-size: 0.6875rem; font-weight: 600; padding: 0.125rem 0.5rem; border-radius: 9999px; }
    .tc-section-count-warn { background: #fef3c7; color: #92400e; }

    .tc-list { display: flex; flex-direction: column; gap: 0.5rem; list-style: none; padding: 0; margin: 0; }
    .tc-row { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 0.625rem 1rem; border: 1px solid #f3f4f6; border-radius: 0.75rem; background: #ffffff; transition: border-color 150ms, box-shadow 150ms; }
    .tc-row:hover { border-color: #e5e7eb; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
    .tc-row-left { display: flex; align-items: center; gap: 0.75rem; min-width: 0; flex: 1; }
    .tc-row-actions { display: flex; align-items: center; gap: 0.25rem; flex-shrink: 0; opacity: 0.65; transition: opacity 150ms; }
    .tc-row:hover .tc-row-actions { opacity: 1; }

    .tc-position { width: 1.5rem; font-family: ui-monospace, monospace; font-size: 0.75rem; color: #9ca3af; text-align: right; flex-shrink: 0; }

    .tc-avatar { display: inline-flex; align-items: center; justify-content: center; width: 2.25rem; height: 2.25rem; min-width: 2.25rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; line-height: 1; flex-shrink: 0; }

    .tc-info { min-width: 0; flex: 1; }
    .tc-info-name { font-size: 0.875rem; font-weight: 500; color: #111827; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .tc-info-meta { font-size: 0.75rem; color: #6b7280; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 0.125rem; }

    .tc-btn { display: inline-flex; align-items: center; justify-content: center; width: 2rem; height: 2rem; border-radius: 0.5rem; color: #9ca3af; background: transparent; border: none; cursor: pointer; transition: background-color 150ms, color 150ms; padding: 0; }
    .tc-btn svg { width: 18px; height: 18px; stroke-width: 1.75; }
    .tc-btn:hover { background: #f3f4f6; color: #374151; }
    .tc-btn-success:hover { background: #f0fdf4; color: #15803d; }
    .tc-btn-danger:hover { background: #fef2f2; color: #b91c1c; }
    .tc-btn:focus-visible { outline: 2px solid #3b82f6; outline-offset: 1px; }
    .tc-btn:disabled { opacity: 0.3; cursor: not-allowed; }
    .tc-btn:disabled:hover { background: transparent; color: #9ca3af; }

    .tc-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1.75rem 1rem; text-align: center; border: 1px dashed #e5e7eb; border-radius: 0.75rem; background: #fafafa; }
    .tc-empty svg { width: 32px; height: 32px; color: #d1d5db; margin-bottom: 0.5rem; stroke-width: 1.5; }
    .tc-empty p { font-size: 0.875rem; color: #9ca3af; margin: 0; }

    .tc-move-panel { padding: 1rem; border: 1px solid #bfdbfe; background: #eff6ff; border-radius: 0.75rem; display: flex; flex-direction: column; gap: 0.75rem; }
    .tc-move-panel p { font-size: 0.875rem; color: #1e3a8a; margin: 0; }
    .tc-select { display: block; width: 100%; padding: 0.5rem 0.75rem; font-size: 0.875rem; background: white; border: 1px solid #d1d5db; border-radius: 0.5rem; color: #111827; }
    .tc-select:focus { outline: 2px solid #3b82f6; outline-offset: -1px; border-color: #3b82f6; }
    .tc-move-actions { display: flex; gap: 0.5rem; }
    .tc-action-primary { display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.4rem 0.75rem; background: #2563eb; color: white; font-size: 0.75rem; font-weight: 600; border: none; border-radius: 0.5rem; cursor: pointer; transition: background-color 150ms; }
    .tc-action-primary:hover { background: #1d4ed8; }
    .tc-action-primary:disabled { opacity: 0.4; cursor: not-allowed; background: #2563eb; }
    .tc-action-secondary { padding: 0.4rem 0.75rem; background: white; color: #374151; font-size: 0.75rem; font-weight: 600; border: 1px solid #d1d5db; border-radius: 0.5rem; cursor: pointer; transition: background-color 150ms; }
    .tc-action-secondary:hover { background: #f9fafb; }

    .tc-loading { display: none; align-items: center; justify-content: center; padding: 0.5rem 0; }
    .tc-loading svg { width: 20px; height: 20px; color: #9ca3af; animation: tc-spin 0.6s linear infinite; }
    @keyframes tc-spin { to { transform: rotate(360deg); } }
</style>

<div class="tc-wrap">

    {{-- Indicateur de chargement (Livewire gère display:flex via wire:loading.flex) --}}
    <div class="tc-loading"
         wire:loading.flex
         wire:target="promote,confirmMove,confirmRemove,openRemoveDialog,openMoveModal,closeRemoveDialog,closeMoveModal">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-opacity="0.25"></circle>
            <path d="M12 2 A10 10 0 0 1 22 12" stroke="currentColor" stroke-width="3" stroke-linecap="round" fill="none"></path>
        </svg>
    </div>

    {{-- Header de la session --}}
    <div class="tc-header">
        <h2>{{ $this->table->topic }}</h2>
        <div class="tc-header-meta">
            <span>{{ $this->table->scheduled_at->translatedFormat('d F Y · H\hi') }}</span>
            <span class="sep">•</span>
            <span style="font-weight: 500; color: #4b5563;">Niveau {{ $this->table->level->code }}</span>
        </div>
        <div class="tc-badges">
            <span class="tc-badge tc-badge-info">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                </svg>
                {{ $registered->count() }} / {{ $this->table->max_participants }} inscrits
            </span>
            @if($waitlist->count() > 0)
                <span class="tc-badge tc-badge-warn">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    {{ $waitlist->count() }} en attente
                </span>
            @endif
            @if($isFull)
                <span class="tc-badge tc-badge-danger">Complet</span>
            @endif
        </div>
    </div>

    {{-- Panneau de déplacement direct --}}
    @if($moveRegistrationId && $movingRegistration)
        <div class="tc-move-panel">
            <p>Déplacer <strong>{{ $movingRegistration->user?->full_name }}</strong> vers&nbsp;:</p>
            <select wire:model.live="targetTableId" class="tc-select">
                <option value="">— Choisir une session —</option>
                @foreach($availableTables as $id => $label)
                    <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </select>
            @if($availableTables->isEmpty())
                <p style="font-size: 0.75rem; font-style: italic; color: #6b7280; margin: 0;">
                    Aucune autre session disponible.
                </p>
            @endif
            <div class="tc-move-actions">
                <button type="button" wire:click="confirmMove" @disabled(!$targetTableId) class="tc-action-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:14px;height:14px;stroke-width:2.5;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                    Confirmer le déplacement
                </button>
                <button type="button" wire:click="closeMoveModal" class="tc-action-secondary">
                    Annuler
                </button>
            </div>
        </div>
    @endif

    {{-- Dialog retrait / vivier (overlay) --}}
    @include('livewire.admin.partials.remove-or-pool-dialog')

    {{-- Section : Inscrits confirmés --}}
    <section class="tc-section">
        <div class="tc-section-head">
            <h3 class="tc-section-title">Inscrits confirmés</h3>
            <span class="tc-section-count">{{ $registered->count() }}</span>
        </div>

        @if($registered->isEmpty())
            <div class="tc-empty">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                </svg>
                <p>Aucun inscrit confirmé pour cette session.</p>
            </div>
        @else
            <ul class="tc-list">
                @foreach($registered as $reg)
                    @php
                        $name = $reg->user?->full_name ?? '?';
                        $palette = [
                            ['bg' => '#dbeafe', 'fg' => '#1d4ed8'],
                            ['bg' => '#ede9fe', 'fg' => '#6d28d9'],
                            ['bg' => '#fce7f3', 'fg' => '#be185d'],
                            ['bg' => '#dcfce7', 'fg' => '#15803d'],
                            ['bg' => '#fef3c7', 'fg' => '#a16207'],
                            ['bg' => '#cffafe', 'fg' => '#0e7490'],
                            ['bg' => '#ccfbf1', 'fg' => '#0f766e'],
                            ['bg' => '#ffe4e6', 'fg' => '#be123c'],
                        ];
                        $color = $palette[abs(crc32($name)) % count($palette)];
                    @endphp
                    <li class="tc-row">
                        <div class="tc-row-left">
                            <span class="tc-avatar" style="background-color: {{ $color['bg'] }}; color: {{ $color['fg'] }};">
                                {{ mb_substr($reg->user?->first_name ?? '?', 0, 1) }}{{ mb_substr($reg->user?->last_name ?? '', 0, 1) }}
                            </span>
                            <div class="tc-info">
                                <div class="tc-info-name">{{ $reg->user?->full_name }}</div>
                                <div class="tc-info-meta">{{ $reg->card?->cardType?->name ?? '—' }}</div>
                            </div>
                        </div>
                        <div class="tc-row-actions">
                            <button type="button"
                                    wire:click="openMoveModal({{ $reg->id }})"
                                    title="Déplacer vers une autre session"
                                    class="tc-btn">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-5.25-.75L12 7.5m3.75 3.75L12 15m3.75-3.75H8.25" />
                                </svg>
                            </button>
                            <button type="button"
                                    wire:click="openRemoveDialog({{ $reg->id }})"
                                    title="Annuler / Mettre au vivier"
                                    class="tc-btn tc-btn-danger">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Section : Liste d'attente --}}
    <section class="tc-section">
        <div class="tc-section-head">
            <h3 class="tc-section-title">Liste d'attente</h3>
            <span class="tc-section-count tc-section-count-warn">{{ $waitlist->count() }}</span>
        </div>

        @if($waitlist->isEmpty())
            <div class="tc-empty">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <p>Aucune personne en attente.</p>
            </div>
        @else
            <ul class="tc-list">
                @foreach($waitlist as $index => $reg)
                    @php
                        $name = $reg->user?->full_name ?? '?';
                        $palette = [
                            ['bg' => '#dbeafe', 'fg' => '#1d4ed8'],
                            ['bg' => '#ede9fe', 'fg' => '#6d28d9'],
                            ['bg' => '#fce7f3', 'fg' => '#be185d'],
                            ['bg' => '#dcfce7', 'fg' => '#15803d'],
                            ['bg' => '#fef3c7', 'fg' => '#a16207'],
                            ['bg' => '#cffafe', 'fg' => '#0e7490'],
                            ['bg' => '#ccfbf1', 'fg' => '#0f766e'],
                            ['bg' => '#ffe4e6', 'fg' => '#be123c'],
                        ];
                        $color = $palette[abs(crc32($name)) % count($palette)];
                    @endphp
                    <li class="tc-row">
                        <div class="tc-row-left">
                            <span class="tc-position">#{{ $index + 1 }}</span>
                            <span class="tc-avatar" style="background-color: {{ $color['bg'] }}; color: {{ $color['fg'] }};">
                                {{ mb_substr($reg->user?->first_name ?? '?', 0, 1) }}{{ mb_substr($reg->user?->last_name ?? '', 0, 1) }}
                            </span>
                            <div class="tc-info">
                                <div class="tc-info-name">{{ $reg->user?->full_name }}</div>
                                <div class="tc-info-meta">{{ $reg->user?->email }}</div>
                            </div>
                        </div>
                        <div class="tc-row-actions">
                            <button type="button"
                                    wire:click="promote({{ $reg->id }})"
                                    @disabled($isFull)
                                    title="{{ $isFull ? 'Table complète' : 'Promouvoir en inscrit confirmé' }}"
                                    wire:confirm="Promouvoir {{ $reg->user?->full_name }} en inscrit confirmé ?"
                                    class="tc-btn tc-btn-success">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 12 12 8.25m0 0 3.75 3.75M12 8.25v8.25M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </button>
                            <button type="button"
                                    wire:click="openMoveModal({{ $reg->id }})"
                                    title="Déplacer vers une autre session"
                                    class="tc-btn">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-5.25-.75L12 7.5m3.75 3.75L12 15m3.75-3.75H8.25" />
                                </svg>
                            </button>
                            <button type="button"
                                    wire:click="openRemoveDialog({{ $reg->id }})"
                                    title="Retirer / Mettre au vivier"
                                    class="tc-btn tc-btn-danger">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

</div>
</div>
