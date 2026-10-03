{{-- Erwartet: $key, $item --}}
<div class="cart-item-card" x-data="{ ddOpen: false }" wire:key="cart-item-{{ $key }}">

    <div class="thumb" style="cursor: pointer;"
        @click="$dispatch('open-product-modal', { title: '{{ addslashes($item['name']) }}', img: '{{ asset('img/products/' . $item['image_path']) }}', desc: '{{ addslashes($item['desc']) }}', allergens: '{{ addslashes($item['allergens']) }}' })">
        <img src="{{ asset('img/products/' . $item['image_path']) }}" alt="{{ $item['name'] }}">
    </div>

    <div style="display: flex; flex-direction: column; flex-grow: 1; justify-content: space-between; min-width: 0;">

        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 5px;">
            <div style="font-weight: bold; color: var(--text); font-size: 1.05rem; cursor: pointer; text-decoration: underline dotted; padding-right: 10px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"
                @click="$dispatch('open-product-modal', { title: '{{ addslashes($item['name']) }}', img: '{{ asset('img/products/' . $item['image_path']) }}', desc: '{{ addslashes($item['desc']) }}', allergens: '{{ addslashes($item['allergens']) }}' })">
                {{ $item['name'] }}
            </div>

            <button wire:click="updateCartQty('{{ $key }}', 0)" class="btn-delete" title="Entfernen">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
            </button>
        </div>

        <div style="font-weight: 700; color: var(--accent-soft); font-size: 1.1rem; margin-bottom: 10px;">
            {{ number_format($item['line_total'], 2, ',', '.') }} €
        </div>

        <div style="display: flex; gap: 10px; align-items: center; width: 100%;">
            <div class="custom-dd {{ count($item['available_variants']) <= 1 ? 'static-dd' : '' }}"
                :class="{ 'open': ddOpen }" @click.away="ddOpen = false" style="flex-grow: 1; min-width: 0;">

                <div class="custom-dd-header" style="padding: 0 10px; width: 100%;" @if(count($item['available_variants']) > 1) @click="ddOpen = !ddOpen" @endif>
                    <div class="custom-dd-label" style="font-size: 0.85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        @php
                            $extraInfo = trim(($item['variant_name'] ?? '') . ' ' . ($item['size'] ?? ''));
                        @endphp
                        {{ $extraInfo ?: 'Standard' }}
                    </div>
                    @if(count($item['available_variants']) > 1)<span class="custom-dd-arrow" style="font-size: 14px;"></span>@endif
                </div>

                @if(count($item['available_variants']) > 1)
                    <div class="custom-dd-list" x-show="ddOpen" style="display: none; bottom: calc(100% + 5px); top: auto; width: 100%;">
                        @foreach($item['available_variants'] as $v)
                            <div class="custom-dd-item"
                                style="font-size: 0.85rem; @if($v['stock'] <= 0) opacity: 0.5; pointer-events: none; @endif"
                                wire:click="updateCartVariant('{{ $key }}', {{ $v['id'] }})" @click="ddOpen = false">
                                {{ $v['label'] ?: 'Standard' }} ({{ number_format($v['price'], 2, ',', '.') }} €) @if($v['stock'] <= 0) - Ausverkauft @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="qty-control" style="width: 90px; flex-shrink: 0;">
                <button wire:click="updateCartQty('{{ $key }}', {{ $item['qty'] - 1 }})" class="qty-btn minus" style="width: 28px;">-</button>
                <div class="val" style="font-size: 1rem;">{{ $item['qty'] }}</div>
                <button wire:click="updateCartQty('{{ $key }}', {{ $item['qty'] + 1 }})"
                        class="qty-btn plus"
                        style="width: 28px; @if($item['qty'] >= $item['max_stock']) opacity: 0.3; cursor: not-allowed; @endif"
                        @if($item['qty'] >= $item['max_stock']) disabled @endif>+</button>
            </div>
        </div>
    </div>
</div>
