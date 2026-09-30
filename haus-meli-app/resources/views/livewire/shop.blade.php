<div id="tab-order" class="world active {{ ($orderMode === 'delivery' && !$this->selectedDayOrderable) ? 'shop-day-locked' : '' }}"
    wire:poll.15s.visible="syncCart"
    :class="{'cart-is-open': cartOpen, 'cart-animating': cartAnimating}" 
    x-data="{ 
        cartOpen: false, 
        cartAnimating: false,
        historyOpen: false,
        paymentOpen: false,
        qrOpen: false,
        thanksOpen: false,
        measureShopLayout() {
            const wrap = this.$root.querySelector('.shop-header-wrap');
            if (!wrap || wrap.offsetHeight === 0) return;

            /* Die Wrap-Unterkante (inkl. grauem Feld) ist exakt die Dock-Position des Warenkorbs */
            document.documentElement.style.setProperty('--cart-dock-top', wrap.offsetHeight + 'px');
        }
    }"
    x-init="
        measureShopLayout();
        $watch('cartOpen', () => {
            cartAnimating = true;
            clearTimeout(window.__cartAnimTimer);
            /* Etwas länger als die CSS-Transition, damit der Layout-Shift
               hinter der Abdimmung verborgen bleibt */
            window.__cartAnimTimer = setTimeout(() => cartAnimating = false, 780);
        });
        const headerWrapEl = $root.querySelector('.shop-header-wrap');
        if (headerWrapEl) {
            new ResizeObserver(() => measureShopLayout()).observe(headerWrapEl);
        }
        const onLivewireMorph = () => measureShopLayout();
        document.addEventListener('livewire:init', () => {
            Livewire.hook('morph.updated', onLivewireMorph);
        });
        if (typeof Livewire !== 'undefined') {
            Livewire.hook('morph.updated', onLivewireMorph);
        }
    "
    @open-cart.window="cartOpen = true"
    @close-cart.window="cartOpen = false"
    @close-history.window="historyOpen = false"
    @order-placed.window="cartOpen = false"
    @open-history.window="historyOpen = true"
    @cart-updated.window="location.reload()"
    @checkout-completed.window="paymentOpen = false; historyOpen = false; thanksOpen = true;">
    <div class="container">

        <div class="card toggle-btn-container">
            <button id="mode-btn-delivery" wire:click="setOrderMode('delivery')" class="toggle-btn {{ $orderMode === 'delivery' ? 'active' : '' }}">
                <span>🚚 Lieferung</span>
            </button>
            <button id="mode-btn-self" wire:click="setOrderMode('self')" class="toggle-btn {{ $orderMode === 'self' ? 'active' : '' }}">
                <span>🏪 Selbstbedienung</span>
            </button>
        </div>
    </div>

    {{-- Sticky-Bereich: Header klebt nur innerhalb dieses Wrappers, der bis zum Ende der Produkte reicht --}}
    <div class="shop-region">
        {{-- Die Sticky-Platte: eckig, seitlich breiter, endet bündig an der Warenkorb-Oberkante --}}
        <div class="shop-header-wrap">
        <div class="card header shop-header">
            <div class="header-top">
                <button id="history-open-btn" @click="$dispatch('open-history')" class="add-btn" style="position: relative; padding: 8px 12px; border-radius: 10px; display: flex; align-items: center; gap: 6px; background: var(--soft); color: var(--text);">
                    <span style="font-size: 1.2rem; line-height: 1;">🧾</span>
                    <span>Bestellungen</span>

                    @if(count($history) > 0)
                        <div class="badge-teams {{ $showCheckoutModal ? 'badge-red' : 'badge-green' }}">
                            {{ count($history) }}
                        </div>
                    @endif
                </button>
                <button @click="cartOpen = !cartOpen" 
                        @if($orderMode === 'delivery' && !$this->selectedDayOrderable) disabled @endif
                        class="add-btn" 
                        style="position: relative; padding: 8px 12px; border-radius: 10px; display: flex; align-items: center; gap: 6px;">
                    <span style="font-size: 1.2rem; line-height: 1;">🛒</span>
                    <span>Warenkorb</span>
                    
                    @if($this->cartDetails['count'] > 0)
                        <div class="badge-teams {{ $this->cartMatchesOrder ? 'badge-green' : 'badge-red' }}">
                            {{ $this->cartDetails['count'] }}
                        </div>
                    @endif
                </button>
            </div>
            
            <div class="header-row" style="display: flex; gap: 10px; align-items: center;">
                
                <div style="flex: 1; position: relative;" id="categoryDropdownRoot">
                    <div class="custom-dd" id="cat-dd" x-data="{ open: false }" @click.away="open = false" :class="{ 'open': open }">
                        
                        <div class="custom-dd-header" @click="open = !open" tabindex="0">
                            <div class="custom-dd-label">{{ $currentCategoryLabel }}</div>
                            <span class="custom-dd-arrow"></span>
                        </div>
                        
                        <div class="custom-dd-list" x-show="open" style="display: none;" x-transition>
                            <div class="custom-dd-item {{ $selectedCategory === '__all' ? 'selected' : '' }}" 
                                wire:click="$set('selectedCategory', '__all'); $set('search', '')" 
                                @click="open = false">
                                Alle Kategorien
                            </div>
                            
                            @foreach($dropdownItems as $item)
                                <div class="custom-dd-item {{ $selectedCategory === $item['value'] ? 'selected' : '' }} {{ $item['isGroup'] ? 'super-category-title' : '' }}" 
                                     wire:click="$set('selectedCategory', '{{ $item['value'] }}')" 
                                     @click="open = false">
                                    {!! $item['label'] !!}
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div style="flex: 1;">
                    <div class="search-wrapper" style="position: relative;">
                        <input wire:model.live.debounce.300ms="search" type="text" id="search-input" placeholder="Artikel suchen..." autocomplete="off">
                    </div>
                </div>
            </div>

            @if($orderMode === 'delivery' && count($this->deliveryDays) > 0)
                <div class="delivery-day-picker card" style="padding: 6px; margin: 0; flex-direction: row;">
                    @foreach($this->deliveryDays as $day)
                        <button type="button"
                                wire:key="delivery-day-{{ $day['date'] }}"
                                wire:click="selectDeliveryDate('{{ $day['date'] }}')"
                                class="toggle-btn delivery-day-chip
                                    {{ $day['selected'] ? 'active' : '' }}
                                    {{ $day['blocked'] ? 'is-blocked' : '' }}
                                    {{ $day['has_order'] ? 'has-order' : '' }}"
                                title="{{ $day['label_day'] }} {{ $day['label_date'] }}">
                            <span class="delivery-day-chip-day">{{ $day['label_day'] }}</span>
                            <span class="delivery-day-chip-date">{{ $day['label_date'] }}</span>
                            @if($day['has_order'])
                                <span class="delivery-day-chip-mark ok" aria-hidden="true">
                                    <span class="material-symbols-rounded">check</span>
                                </span>
                            @elseif($day['blocked'])
                                <span class="delivery-day-chip-mark no" aria-hidden="true">
                                    <span class="material-symbols-rounded">close</span>
                                </span>
                            @endif
                        </button>
                    @endforeach
                </div>

                @if($this->selectedDayInfo)
                    <div class="deadline-info {{ $this->selectedDayInfo['class'] }}" style="display: block;">
                        {{ $this->selectedDayInfo['text'] }}
                    </div>
                @endif
            @endif
        </div>
        </div>

        <div class="shop-layout">
        <div class="container shop-content">
        <div id="all-categories" wire:key="product-grid-{{ $renderKey }}">

            <div>
                @if($orderMode === 'self')
                    <div class="source-disclaimer" style="border-left-color: var(--accent-soft);">
                        <span style="font-size: 1.2em;">🏪</span>
                        <div>Selbstbedienung: Produkte können jederzeit entnommen werden. Bitte am Ende des Aufenthalts bezahlen.</div>
                    </div>
                @else
                    <div class="source-disclaimer">
                        <span style="font-size: 1.2em;">ℹ️</span>
                        <div>Hinweis: Die Produktangaben basieren auf Informationen des Lieferanten. Für Details: <a href="{{ asset('docs/original_sortiment.pdf') }}" target="_blank">Original-PDF ansehen</a></div>
                    </div>
                @endif
            </div>

            @if($saleProducts->count() > 0)
                <section class="category-section" style="margin-bottom: 40px;">
                    <h2 class="super-heading">🔥 <span>Angebot des Monats (-15%)</span></h2>
                    
                    <div class="grid-list">
                        @foreach($saleProducts as $product)
                            <x-product-card :product="$product" :is-sale="true" />
                        @endforeach
                    </div>
                </section>
            @endif
            
            @forelse($groupedProducts as $superCatName => $categories)
                @php
                    $firstSpace = strpos($superCatName, ' ');
                    $icon = '';
                    $text = $superCatName;
                    if ($firstSpace !== false && $firstSpace <= 5 && !preg_match('/[a-zA-ZäöüÄÖÜß]/', substr($superCatName, 0, $firstSpace))) {
                        $icon = substr($superCatName, 0, $firstSpace);
                        $text = substr($superCatName, $firstSpace + 1);
                    }
                @endphp
                
                <h2 class="super-heading">
                    @if($icon)
                        {!! $icon !!} <span>{{ $text }}</span>
                    @else
                        <span>{{ $text }}</span>
                    @endif
                </h2>

                @foreach($categories as $categoryName => $products)
                    <section class="category-section">
                        <div class="cat-title">{{ $categoryName }}</div>
                        
                        <div class="grid-list">
                            @foreach($products as $product)
                                <x-product-card :product="$product" />
                            @endforeach
                        </div>
                    </section>
                @endforeach
            @empty
                <div style="padding: 20px; text-align: center; color: var(--muted);">
                    Keine Produkte für diese Auswahl gefunden.
                </div>
            @endforelse
        </div>
        </div>

        @include('livewire.shop-modals.cart')
        </div>
    </div>

    @include('livewire.shop-modals.product-info')

    @include('livewire.shop-modals.history')

    @include('livewire.shop-modals.payment')

    @include('livewire.shop-modals.qr-code')

    @include('livewire.shop-modals.thanks')

    @include('livewire.shop-modals.toasts')
</div>