@if($removeRegistrationId && $removingRegistration)
    @php
        $isRegistered = $removingRegistration->status->value === 'registered';
    @endphp

    <style>
        .tcd-overlay { position: fixed; inset: 0; z-index: 60; display: flex; align-items: center; justify-content: center; padding: 1rem; font-family: ui-sans-serif, system-ui, sans-serif; }
        .tcd-backdrop { position: absolute; inset: 0; background: rgba(17, 24, 39, 0.6); backdrop-filter: blur(2px); }
        .tcd-card { position: relative; z-index: 1; width: 100%; max-width: 28rem; background: white; border-radius: 1rem; box-shadow: 0 20px 50px -10px rgba(0,0,0,0.3); overflow: hidden; }

        .tcd-header { padding: 1rem 1.25rem; border-bottom: 1px solid #f3f4f6; }
        .tcd-header h3 { font-size: 0.875rem; font-weight: 600; color: #111827; margin: 0; }
        .tcd-header h3 strong { color: #374151; font-weight: 600; }
        .tcd-header p { font-size: 0.75rem; color: #6b7280; margin: 0.25rem 0 0 0; }

        .tcd-body { padding: 1rem 1.25rem; display: flex; flex-direction: column; gap: 0.75rem; }

        .tcd-option { display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.75rem; border: 1px solid #e5e7eb; border-radius: 0.625rem; cursor: pointer; transition: border-color 150ms, background-color 150ms; }
        .tcd-option:hover { border-color: #d1d5db; background-color: #f9fafb; }
        .tcd-option.is-selected-reorient { border-color: #93c5fd; background-color: #eff6ff; }
        .tcd-option.is-selected-pool { border-color: #fcd34d; background-color: #fffbeb; }
        .tcd-option input[type=radio] { margin-top: 0.125rem; flex-shrink: 0; }
        .tcd-option-body { flex: 1; min-width: 0; }
        .tcd-option-title { display: block; font-size: 0.875rem; font-weight: 500; color: #111827; }
        .tcd-option-hint { font-size: 0.75rem; color: #6b7280; margin: 0.125rem 0 0 0; }

        .tcd-sub { margin-top: 0.75rem; display: flex; flex-direction: column; gap: 0.75rem; }
        .tcd-label { display: block; font-size: 0.75rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem; }
        .tcd-label .req { color: #dc2626; margin-left: 0.125rem; }
        .tcd-label .opt { color: #9ca3af; font-weight: 400; }
        .tcd-select, .tcd-textarea { display: block; width: 100%; padding: 0.5rem 0.75rem; font-size: 0.875rem; background: white; border: 1px solid #d1d5db; border-radius: 0.5rem; color: #111827; font-family: inherit; }
        .tcd-textarea { resize: vertical; min-height: 4rem; }
        .tcd-select:focus, .tcd-textarea:focus { outline: 2px solid #3b82f6; outline-offset: -1px; border-color: #3b82f6; }

        .tcd-checkbox { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; }
        .tcd-checkbox input { width: 1rem; height: 1rem; }
        .tcd-checkbox span { font-size: 0.875rem; color: #374151; }

        .tcd-footer { display: flex; align-items: center; justify-content: flex-end; gap: 0.5rem; padding: 0.75rem 1.25rem; border-top: 1px solid #f3f4f6; background: #fafafa; }
        .tcd-btn-cancel { padding: 0.4rem 0.75rem; background: white; color: #374151; font-size: 0.75rem; font-weight: 600; border: 1px solid #d1d5db; border-radius: 0.5rem; cursor: pointer; font-family: inherit; }
        .tcd-btn-cancel:hover { background: #f9fafb; }
        .tcd-btn-confirm { padding: 0.4rem 0.75rem; background: #dc2626; color: white; font-size: 0.75rem; font-weight: 600; border: none; border-radius: 0.5rem; cursor: pointer; font-family: inherit; transition: background-color 150ms; }
        .tcd-btn-confirm:hover { background: #b91c1c; }
        .tcd-btn-confirm:disabled { opacity: 0.4; cursor: not-allowed; background: #dc2626; }
    </style>

    <div class="tcd-overlay"
         x-data
         x-on:keydown.escape.window="$wire.call('closeRemoveDialog')">

        <div class="tcd-backdrop" wire:click="closeRemoveDialog" aria-hidden="true"></div>

        <div class="tcd-card">

            <div class="tcd-header">
                <h3>Que faire de <strong>{{ $removingRegistration->user?->full_name }}</strong> ?</h3>
                <p>
                    {{ $isRegistered
                        ? 'Cette inscription confirmée va être annulée.'
                        : 'Cette personne va être retirée de la file d\'attente.' }}
                </p>
            </div>

            <div class="tcd-body">

                {{-- Option : Réorienter --}}
                @if($eligibleTargetSessions->isNotEmpty())
                    <label class="tcd-option {{ $removeChoice === 'reorient' ? 'is-selected-reorient' : '' }}">
                        <input type="radio" wire:model.live="removeChoice" value="reorient">
                        <div class="tcd-option-body">
                            <span class="tcd-option-title">Réorienter vers une autre session</span>
                            <p class="tcd-option-hint">{{ $eligibleTargetSessions->count() }} session(s) compatible(s) disponible(s).</p>

                            @if($removeChoice === 'reorient')
                                <div class="tcd-sub">
                                    <select wire:model="removeTargetTableId" class="tcd-select">
                                        <option value="">— Choisir une session —</option>
                                        @foreach($eligibleTargetSessions as $id => $label)
                                            <option value="{{ $id }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                        </div>
                    </label>
                @endif

                {{-- Option : Vivier --}}
                <label class="tcd-option {{ $removeChoice === 'pool' ? 'is-selected-pool' : '' }}">
                    <input type="radio" wire:model.live="removeChoice" value="pool">
                    <div class="tcd-option-body">
                        <span class="tcd-option-title">Mettre au vivier global</span>
                        <p class="tcd-option-hint">La personne restera en attente, sans session affectée.</p>

                        @if($removeChoice === 'pool')
                            <div class="tcd-sub">
                                <div>
                                    <label class="tcd-label">
                                        Raison admin
                                        @if($isRegistered)
                                            <span class="req">*</span>
                                        @else
                                            <span class="opt">(optionnelle)</span>
                                        @endif
                                    </label>
                                    <textarea wire:model="removeAdminReason"
                                              rows="2"
                                              placeholder="Raison de cette action…"
                                              class="tcd-textarea"></textarea>
                                </div>

                                @if($isRegistered)
                                    <label class="tcd-checkbox">
                                        <input type="checkbox" wire:model="removeRecreditCard">
                                        <span>Recréditer la séance sur la carte</span>
                                    </label>
                                @endif
                            </div>
                        @endif
                    </div>
                </label>

            </div>

            <div class="tcd-footer">
                <button type="button" wire:click="closeRemoveDialog" class="tcd-btn-cancel">
                    Annuler
                </button>
                <button type="button"
                        wire:click="confirmRemove"
                        @disabled(
                            ($removeChoice === 'reorient' && !$removeTargetTableId) ||
                            ($removeChoice === 'pool' && $isRegistered && blank($removeAdminReason))
                        )
                        class="tcd-btn-confirm">
                    Confirmer
                </button>
            </div>

        </div>
    </div>
@endif
