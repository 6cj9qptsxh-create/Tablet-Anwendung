@props(['product', 'isSale' => false])

@php
    $name = json_decode($product->name, true)['de'] ?? 'Unbekannt';
    $notes = json_decode($product->variants->first()?->notes ?? '""', true)['de'] ?? null;
    $isSingleVariant = $product->variants->count() <= 1; // Check: Nur eine Variante?

    $variantsJson = $product->variants->map(function($v) use ($name, $isSale, $isSingleVariant) {
        $vName = json_decode($v->name, true)['de'] ?? '';
        $priceVal = (float)$v->price;
        $priceFormatted = number_format($priceVal, 2, ',', '.') . ' €';
        
        $parts = [];

        // LOGIK FÜR DAS LABEL IM DROPDOWN
        if (!$isSingleVariant && !empty($vName)) {
            $parts[] = $vName;
        }

        if (!empty($v->size)) {
            $parts[] = $v->size;
        }
        $parts[] = $priceFormatted;
        
        $fullLabel = implode(' – ', $parts);

        // Sale-Logik
        if ($isSale && $v->getAttribute('original_price')) {
            $origPrice = number_format($v->getAttribute('original_price'), 2, ',', '.') . ' €';
            $fullLabel .= " (statt $origPrice)";
        }

        // Metadaten (unverändert)
        $desc = json_decode($v->description, true)['de'] ?? '';
        $allergensArr = json_decode($v->allergens, true) ?? [];
        $stock = $v->getAttribute('stock') ?? 9999;
        
        return [
            'id'        => $v->id, 
            'label'     => $vName ?: $name, 
            'full'      => $fullLabel,      // Das geht ins HTML-Dropdown
            'price'     => $priceVal,
            'size'      => $v->size,
            'desc'      => $desc, 
            'allergens' => !empty($allergensArr) ? implode(' | ', $allergensArr) : '',
            'stock'     => (int)$stock,
        ];
    })->toJson();
@endphp

<div class="card" wire:key="product-{{ $product->id }}-{{ $isSale ? 'sale' : 'normal' }}"
     x-data="{ 
        pName: '{{ addslashes($name) }}',
        pImg: '{{ asset('img/products/' . $product->image_path) }}',
        variants: {{ $variantsJson }},
        selectedVid: null,
        selectedLabel: '',
        ddOpen: false,
        qty: 1,

        get inCartQty() {
            let activeCart = $wire.orderMode === 'delivery' ? $wire.cartDelivery : $wire.cartSelf;
            let key = '{{ $product->id }}_' + this.selectedVid;
            return activeCart[key] ? activeCart[key].qty : 0;
        },
        // Der Gesamtbestand der aktuell gewählten Variante
        get currentStock() {
            let v = this.variants.find(v => v.id === this.selectedVid);
            return (v && v.stock !== undefined) ? v.stock : 9999;
        },
        get available() {
            return Math.max(0, this.currentStock - this.inCartQty);
        },
        get stockText() {
            if (this.currentStock >= 9999) return ''; // Kugelsicher: Alles ab 9999 hat keinen Text
            if (this.available <= 0) return 'Ausverkauft';
            if (this.available <= 10) return this.available + ' verfügbar';
            return 'Verfügbar';
        },
        // Die Farbe für den Text (Grün, Orange, Rot)
        get stockColor() {
            if (this.available <= 0) return '#ff4d4d'; // Rot
            if (this.available <= 10) return '#ffa502'; // Orange/Gelb
            return '#2ed573'; // Grün
        }
     }"
     x-init="selectedVid = variants[0]?.id; selectedLabel = variants[0]?.label;"
     x-effect="if (qty > available) qty = Math.max(1, available)">
     
    <div class="thumb" style="position: relative;">
        @if($isSale)
            <div style="position:absolute; top:5px; right:5px; background:var(--color-red-text, #ff4d4d); color:white; font-size:0.75rem; font-weight:bold; padding:3px 6px; border-radius:6px; z-index:10; box-shadow: 0 2px 5px rgba(0,0,0,0.3);">
                -15%
            </div>
        @endif
        <img class="product-img" loading="lazy" width="96" height="96" src="{{ asset('img/products/' . $product->image_path) }}" alt="{{ $name }}">
    </div>

    <div class="content-wrapper">
        <div class="card-main-row">
            
            <div class="card-left-col">
                <div class="card-text-row" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <div class="title" style="cursor: pointer; text-decoration: underline dotted;" title="Klicken für mehr Infos"
                         @click="$dispatch('open-product-modal', { title: pName, img: pImg, desc: variants.find(v => v.id === selectedVid)?.desc || '', allergens: variants.find(v => v.id === selectedVid)?.allergens || '' })">
                        {{ $name }}
                    </div>
                    
                    <div x-show="currentStock < 9999" 
                        style="display: none; font-size: 0.8rem; font-weight: bold; background: rgba(255,255,255,0.1); padding: 2px 8px; border-radius: 10px;"
                        x-text="stockText" 
                        :style="'color: ' + stockColor + ';'">
                    </div>
                </div>
                
                <div class="custom-dd {{ $product->variants->count() <= 1 ? 'static-dd' : '' }}" 
                    :class="{ 'open': ddOpen }" 
                    @click.away="ddOpen = false" 
                    style="margin-top: auto;">
                    
                    <div class="custom-dd-header" @if($product->variants->count() > 1) @click="ddOpen = !ddOpen" @endif>
                        <div class="custom-dd-label" 
                            x-text="variants.find(v => v.id === selectedVid)?.full">
                        </div>
                        @if($product->variants->count() > 1)<span class="custom-dd-arrow"></span>@endif
                    </div>

                    @if($product->variants->count() > 1)
                        <div class="custom-dd-list" x-show="ddOpen" style="display: none;">
                            <template x-for="v in variants" :key="v.id">
                                <div x-show="v.id !== selectedVid" 
                                    class="custom-dd-item" 
                                    @click="selectedVid = v.id; selectedLabel = v.label; ddOpen = false"
                                    x-text="v.full">
                                </div>
                            </template>
                        </div>
                    @endif
                </div>
            </div>
            
            <div class="action-group">
                <button @click="$wire.addToCart({{ $product->id }}, selectedVid, qty).then(() => { qty = 1 })" 
                        class="add-btn icon-only"
                        :class="{ 'disabled': available === 0 }"
                        :disabled="available === 0">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path><line x1="12" y1="9" x2="12" y2="15"></line><line x1="9" y1="12" x2="15" y2="12"></line></svg>
                </button>
                
                <div class="qty-control" :class="{ 'disabled': available === 0 }">
                    <button @click="if(qty > 1) qty--" class="qty-btn minus" :disabled="available === 0">-</button>
                    <div class="val" x-text="qty">1</div>
                    <button @click="if(qty < available) qty++" class="qty-btn plus" 
                            :class="{ 'disabled': qty >= available }" 
                            :disabled="qty >= available || available === 0">+</button>
                </div>
            </div>
            
        </div>
        
        @if($notes)
            <div class="product-note small" style="margin-top: 10px; color: var(--muted);">{{ $notes }}</div>
        @endif
    </div>
</div>