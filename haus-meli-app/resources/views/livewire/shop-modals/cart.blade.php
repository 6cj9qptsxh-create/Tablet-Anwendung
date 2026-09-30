@php
    $sections = $this->cartSections;
    $hasOrder = $sections['has_order'];
    $orderItems = $sections['order_items'];
    $cartItems = $sections['cart_items'];
    $isEmpty = $this->cartDetails['count'] === 0;
@endphp
<div class="sidebar-panel" 
     x-show="cartOpen"
     style="display: none;"
     x-transition:leave="cart-leaving"
     x-transition:leave-end="cart-leaving-end"
     x-data="{
        canUp: false,
        canDown: false,
        check() {
            const el = this.$refs.items;
            if (!el) return;
            this.canUp = el.scrollTop > 4;
            this.canDown = el.scrollTop + el.clientHeight < el.scrollHeight - 4;
        },
        dockPage() {
            /* Seite sanft bis zur Dock-Position scrollen, damit das Panel
               komplett im Bild ist und das Listenende sichtbar wird */
            const dock = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--cart-dock-top')) || 0;
            const delta = this.$root.getBoundingClientRect().top - dock;
            if (delta > 1) window.scrollBy({ top: delta, behavior: 'smooth' });
        },
        toStart() {
            this.dockPage();
            this.$refs.items.scrollTo({ top: 0, behavior: 'smooth' });
        },
        toEnd() {
            this.dockPage();
            this.$refs.items.scrollTo({ top: this.$refs.items.scrollHeight, behavior: 'smooth' });
        }
     }"
     x-init="
        check();
        new ResizeObserver(() => check()).observe($refs.items);
        new MutationObserver(() => check()).observe($refs.items, { childList: true, subtree: true });
     ">

    <button type="button"
            class="cart-collapse-btn"
            @click="cartOpen = false"
            title="Warenkorb schließen"
            aria-label="Warenkorb schließen">
        <span class="material-symbols-rounded">chevron_right</span>
    </button>
    
    <!-- 1. HEADER CARD -->
    <div class="cart-header-box cart-section-head">
        <h3 style="margin:0; font-size: 1.2rem;">
            @if($hasOrder)
                📦 Bestellung
            @else
                🛒 Warenkorb
            @endif
        </h3>
        @if($hasOrder && $sections['show_delete_order'])
            <button type="button"
                    wire:click="deleteOrder"
                    wire:confirm="Bestellung für diesen Tag wirklich komplett löschen?"
                    class="btn-delete"
                    title="Bestellung löschen">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
            </button>
        @elseif(! $hasOrder && $sections['show_clear_cart'])
            <button type="button"
                    wire:click="clearCart"
                    wire:confirm="Warenkorb wirklich leeren?"
                    class="btn-delete"
                    title="Warenkorb leeren">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
            </button>
        @endif
    </div>

    <!-- 2. PRODUKT SCROLL BEREICH (mit Scroll-Hinweis-Pfeilen) -->
    <div class="cart-items-wrap">
        <div class="cart-items-box" x-ref="items" @scroll="check()">

        @if($isEmpty)
            <div style="padding: 40px 10px; text-align: center; color: var(--muted);">
                <div style="font-size: 3rem; margin-bottom: 10px;">🛒</div>
                @if($hasOrder)
                    Bestellung und Warenkorb sind leer.
                @else
                    Dein Warenkorb ist noch leer.
                @endif
            </div>
        @else
            @if($hasOrder)
                @foreach($orderItems as $key => $item)
                    @include('livewire.shop-modals.cart-item', ['key' => $key, 'item' => $item])
                @endforeach

                @if(count($cartItems) > 0)
                    <div class="cart-header-box cart-section-divider cart-section-head">
                        <h3 style="margin:0; font-size: 1.2rem;">🛒 Warenkorb</h3>
                        @if($sections['show_clear_cart'])
                            <button type="button"
                                    wire:click="clearNewCartItems"
                                    wire:confirm="Neuen Warenkorb wirklich leeren?"
                                    class="btn-delete"
                                    title="Warenkorb leeren">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                            </button>
                        @endif
                    </div>

                    @foreach($cartItems as $key => $item)
                        @include('livewire.shop-modals.cart-item', ['key' => $key, 'item' => $item])
                    @endforeach
                @endif
            @else
                @foreach($cartItems as $key => $item)
                    @include('livewire.shop-modals.cart-item', ['key' => $key, 'item' => $item])
                @endforeach
            @endif
        @endif

        </div>

        <button type="button" class="cart-scroll-hint top"
                x-show="canUp" x-transition.opacity
                @click="toStart()"
                title="Zum Anfang">
            <span class="material-symbols-rounded">keyboard_arrow_up</span>
        </button>
    </div>

    <!-- 3. CONTAINER: FOOTER (Sticky unten) -->
    <div class="cart-footer-box">

        <!-- Sitzt über der Footer-Oberkante und ist damit immer sichtbar -->
        <button type="button" class="cart-scroll-hint bottom"
                x-show="canDown" x-transition.opacity
                @click="toEnd()"
                title="Zum Ende">
            <span class="material-symbols-rounded">keyboard_arrow_down</span>
        </button>
        
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 15px; padding: 0 5px;">
            <div style="font-weight:700; color:var(--muted); text-transform: uppercase; font-size: 0.9em;">Gesamtbetrag</div>
            <div style="font-size:1.6em; font-weight:900; color:var(--text);">
                {{ number_format($this->cartDetails['total'], 2, ',', '.') }} €
            </div>
        </div>

        @if($sections['show_reset'] || $sections['show_update'] || $sections['show_place'])
            <div style="display: flex; flex-direction: column; gap: 10px;">
                @if($sections['show_update'] || $sections['show_place'])
                    <button wire:click="placeOrder"
                            @click="$dispatch('notify', { message: 'Bestellung wird gesendet...' })"
                            class="add-btn"
                            style="width: 100%; padding: 12px; font-size: 1.1rem; height: auto;">
                        <span wire:loading.remove wire:target="placeOrder">
                            @if($sections['show_update'])
                                Bestellung aktualisieren
                            @else
                                Kostenpflichtig bestellen
                            @endif
                        </span>
                        <span wire:loading wire:target="placeOrder">Verarbeite... ⏳</span>
                    </button>
                @endif

                @if($sections['show_reset'])
                    <button type="button"
                            wire:click="resetCartToOrder"
                            class="add-btn cart-reset-btn"
                            style="width: 100%; padding: 10px; font-size: 1rem; height: auto;">
                        <span wire:loading.remove wire:target="resetCartToOrder">Änderungen verwerfen</span>
                        <span wire:loading wire:target="resetCartToOrder">Lade…</span>
                    </button>
                @endif
            </div>
        @endif
    </div>
</div>
