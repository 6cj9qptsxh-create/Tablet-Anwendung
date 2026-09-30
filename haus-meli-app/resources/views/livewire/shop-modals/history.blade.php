@php
    $sections = $this->historySections;
@endphp
<div class="modal history-modal" :style="historyOpen ? 'display: flex;' : 'display: none;'" @click="historyOpen = false">
    <div class="panel history-panel" @click.stop>

        <div class="history-panel-head">
            <h3>🧾 Deine Bestellungen</h3>
            <button type="button" class="history-close" @click="historyOpen = false" aria-label="Schließen">&times;</button>
        </div>

        <div class="history-scroll">
            @if($sections['is_empty'])
                <div class="history-empty">Noch keine Bestellungen vorhanden.</div>
            @else
                @if(count($sections['delivery']) > 0)
                    <div class="history-group-label">Lieferung</div>
                    <div class="history-list">
                        @foreach($sections['delivery'] as $entry)
                            @php $order = $entry['raw']; @endphp
                            <button type="button"
                                    class="history-card {{ $entry['editable'] ? 'is-editable' : 'is-locked' }}"
                                    wire:click="openHistoryOrder('{{ $entry['date_ymd'] }}')"
                                    wire:key="history-delivery-{{ $entry['date_ymd'] }}">
                                <div class="history-card-top">
                                    <div class="history-card-title">
                                        <span class="history-card-day">{{ $entry['label_day'] }}</span>
                                        <span class="history-card-meta">aktualisiert {{ $entry['placed_label'] }}</span>
                                    </div>
                                    <span class="history-status {{ $entry['editable'] ? 'ok' : 'done' }}">
                                        {{ $entry['status_label'] }}
                                    </span>
                                </div>

                                <div class="history-card-items">
                                    @foreach($order['items'] as $item)
                                        <div class="history-line">
                                            <div class="history-line-name">
                                                {{ $item['qty'] }}x {{ $item['name'] }}
                                                @php
                                                    $extraInfo = trim(($item['variant_name'] ?? '') . ' ' . ($item['size'] ?? ''));
                                                @endphp
                                                @if($extraInfo)
                                                    <span class="history-line-extra">({{ $extraInfo }})</span>
                                                @endif
                                            </div>
                                            <span class="history-line-price">
                                                {{ number_format($item['line_total'], 2, ',', '.') }} €
                                            </span>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="history-card-total">
                                    {{ number_format($order['total'], 2, ',', '.') }} €
                                </div>
                            </button>
                        @endforeach
                    </div>
                @endif

                @if(count($sections['self']) > 0)
                    <div class="history-group-label">Selbstbedienung</div>
                    <div class="history-list">
                        @foreach($sections['self'] as $entry)
                            @php $order = $entry['raw']; @endphp
                            <div class="history-card is-self" wire:key="history-self-{{ $order['id'] ?? $loop->index }}">
                                <div class="history-card-top">
                                    <div class="history-card-title">
                                        <span class="history-card-day">{{ $entry['label_day'] }}</span>
                                        <span class="history-card-meta">{{ $entry['placed_label'] }}</span>
                                    </div>
                                    <span class="history-status self">SB</span>
                                </div>

                                <div class="history-card-items">
                                    @foreach($order['items'] as $item)
                                        <div class="history-line">
                                            <div class="history-line-name">
                                                {{ $item['qty'] }}x {{ $item['name'] }}
                                                @php
                                                    $extraInfo = trim(($item['variant_name'] ?? '') . ' ' . ($item['size'] ?? ''));
                                                @endphp
                                                @if($extraInfo)
                                                    <span class="history-line-extra">({{ $extraInfo }})</span>
                                                @endif
                                            </div>
                                            <span class="history-line-price">
                                                {{ number_format($item['line_total'], 2, ',', '.') }} €
                                            </span>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="history-card-total">
                                    {{ number_format($order['total'], 2, ',', '.') }} €
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>

        <div class="history-footer">
            <div class="history-stay-total">
                <span>Gesamtsumme Aufenthalt</span>
                <strong>{{ number_format($this->stayTotal, 2, ',', '.') }} €</strong>
            </div>

            @if($showCheckoutModal)
                <button type="button" @click="$dispatch('open-payment')" class="add-btn history-checkout-btn">
                    <span>Kostenpflichtig abrechnen</span>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </button>
            @endif
        </div>
    </div>
</div>
