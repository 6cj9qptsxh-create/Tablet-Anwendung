<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Order;
use App\Models\Product;
use Carbon\Carbon;

class Shop extends Component
{
    public $selectedCategory = '__all'; // "__all" oder "__super_Getränke" oder "Getränke||Kaffee"
    public $search = '';
    public $orderMode = 'delivery'; // 'delivery' oder 'self'
    public $cartDelivery = [];
    public $cartSelf = [];
    public $isOwner = false;
    public $selectedDeliveryDate = null; // Y-m-d
    public $history = [];
    public $showCheckoutModal = false;
    public $showWelcomeModal = false;
    public $feedbackText = ''; // <--- NEU: Für das Danke-Modal
    public $renderKey = 0; // <--- NEU: Zwingt Alpine.js zum Neu-Laden

    private const CUTOFF_HOUR = 20;
    private const CUTOFF_MINUTE = 30;
    
    // Wird aufgerufen, wenn sich der Modus ändert
    public function setOrderMode($mode)
    {
        $this->orderMode = $mode;
        $this->selectedCategory = '__all'; // Kategorie beim Wechsel zurücksetzen

        if ($mode === 'delivery') {
            $this->ensureSelectedDeliveryDate();
        }
    }

    private function detectOwnerFromIp(): bool
    {
        return \App\Support\ClientNetwork::isFamily();
    }

    private function getChannel(): string
    {
        return $this->isOwner ? 'owner' : 'guest';
    }

    /** Lieferfreie Tage (Y-m-d) aus calendar_events */
    private function getNoDeliveryDates(): array
    {
        $holidays = [];
        try {
            $blockedEvents = \Illuminate\Support\Facades\DB::table('ferienwohnung_laravel.calendar_events')
                ->where('type', 'no_delivery')
                ->where('end_date', '>=', now()->toDateString())
                ->get();

            foreach ($blockedEvents as $event) {
                $period = \Carbon\CarbonPeriod::create($event->start_date, $event->end_date);
                foreach ($period as $date) {
                    $holidays[] = $date->format('Y-m-d');
                }
            }
        } catch (\Exception $e) {
            // Falls die DB mal zickt, bricht der Shop nicht zusammen
        }

        return array_values(array_unique($holidays));
    }

    /** Deadline = Vortag 20:30. Bis einschließlich 20:30 noch änderbar. */
    private function getDeadlineForDeliveryDate(Carbon $deliveryDate): Carbon
    {
        return $deliveryDate->copy()->subDay()->setTime(self::CUTOFF_HOUR, self::CUTOFF_MINUTE, 0);
    }

    private function isDeliveryDateOrderable(Carbon $deliveryDate, array $holidays): bool
    {
        $key = $deliveryDate->format('Y-m-d');
        if (in_array($key, $holidays, true)) {
            return false;
        }

        return !now()->greaterThan($this->getDeadlineForDeliveryDate($deliveryDate));
    }

    /**
     * Nächster Liefertag nach bisheriger Logik (für Default-Auswahl).
     * Morgen, nach 20:30 übermorgen, no_delivery überspringen.
     */
    private function calculateNextDeliveryDate()
    {
        $now = now();
        $deliveryWeekdays = [0, 1, 2, 3, 4, 5, 6];
        $holidays = $this->getNoDeliveryDates();

        $searchDate = $now->copy()->addDay();
        $hasWarning = false;

        $todayIsHoliday = in_array($now->format('Y-m-d'), $holidays, true);
        $todayIsBlocked = !in_array($now->dayOfWeek, $deliveryWeekdays, true) || $todayIsHoliday;

        if ($todayIsBlocked) {
            $searchDate->addDay();
            if ($todayIsHoliday) {
                $hasWarning = true;
            }
        }

        $cutoffTime = $now->copy()->setTime(self::CUTOFF_HOUR, self::CUTOFF_MINUTE, 0);
        if (!$todayIsBlocked && $now->greaterThan($cutoffTime)) {
            $searchDate->addDay();
        }

        while (!in_array($searchDate->dayOfWeek, $deliveryWeekdays, true) || in_array($searchDate->format('Y-m-d'), $holidays, true)) {
            if (in_array($searchDate->format('Y-m-d'), $holidays, true)) {
                $hasWarning = true;
            }
            $searchDate->addDay();
        }

        return [
            'date' => $searchDate,
            'has_warning' => $hasWarning,
        ];
    }

    /** Nächste 7 Tage ab morgen mit Status für die Tagesleiste */
    public function getDeliveryDaysProperty(): array
    {
        if ($this->orderMode === 'self') {
            return [];
        }

        $holidays = $this->getNoDeliveryDates();
        $channel = $this->getChannel();
        $existing = Order::query()
            ->where('channel', $channel)
            ->whereDate('delivery_date', '>=', now()->addDay()->toDateString())
            ->whereDate('delivery_date', '<=', now()->addDays(7)->toDateString())
            ->get()
            ->keyBy(fn (Order $o) => $o->delivery_date->format('Y-m-d'));

        $days = [];
        for ($i = 1; $i <= 7; $i++) {
            $date = now()->copy()->addDays($i)->startOfDay();
            $key = $date->format('Y-m-d');
            $order = $existing->get($key);
            $orderable = $this->isDeliveryDateOrderable($date, $holidays);
            $isNoDelivery = in_array($key, $holidays, true);

            $days[] = [
                'date' => $key,
                'label_day' => $date->locale('de')->translatedFormat('D'),
                'label_date' => $date->format('d.m.'),
                'orderable' => $orderable,
                'has_order' => (bool) $order,
                'blocked' => !$orderable,
                'no_delivery' => $isNoDelivery,
                'selected' => $this->selectedDeliveryDate === $key,
            ];
        }

        return $days;
    }

    public function getSelectedDayInfoProperty(): ?array
    {
        if ($this->orderMode === 'self' || !$this->selectedDeliveryDate) {
            return null;
        }

        $holidays = $this->getNoDeliveryDates();
        $date = Carbon::parse($this->selectedDeliveryDate)->locale('de');
        $deadline = $this->getDeadlineForDeliveryDate($date)->locale('de');
        $key = $date->format('Y-m-d');
        $isNoDelivery = in_array($key, $holidays, true);
        $orderable = $this->isDeliveryDateOrderable($date, $holidays);
        $hasOrder = Order::query()
            ->where('channel', $this->getChannel())
            ->whereDate('delivery_date', $this->selectedDeliveryDate)
            ->exists();

        $dayLabel = $date->translatedFormat('D, d.m.');
        $deadlineLabel = $deadline->translatedFormat('D, d.m.');

        if ($isNoDelivery) {
            return [
                'class' => 'red',
                'text' => "Am {$dayLabel} ist keine Lieferung möglich (Lieferpause).",
                'orderable' => false,
                'has_order' => $hasOrder,
                'reason' => 'no_delivery',
            ];
        }

        if (!$orderable) {
            return [
                'class' => 'red',
                'text' => "Bestellfrist für {$dayLabel} abgelaufen — war möglich bis {$deadlineLabel}, 20:30 Uhr.",
                'orderable' => false,
                'has_order' => $hasOrder,
                'reason' => 'deadline',
            ];
        }

        // Dringlichkeit: Deadline ist heute → leicht hervorgehoben, sonst grün
        $class = $deadline->isToday() && now()->greaterThan($deadline) ? 'red' : 'green';

        return [
            'class' => $class,
            'text' => "Bestellung für {$dayLabel} — änderbar bis {$deadlineLabel}, 20:30 Uhr",
            'orderable' => true,
            'has_order' => $hasOrder,
            'reason' => null,
        ];
    }

    /** Ob der gewählte Liefertag bestellbar ist (Shop/Warenkorb aktiv) */
    public function getSelectedDayOrderableProperty(): bool
    {
        if ($this->orderMode === 'self') {
            return true;
        }

        return (bool) data_get($this->selectedDayInfo, 'orderable', false);
    }

    private function isDateInDeliveryWindow(string $date): bool
    {
        for ($i = 1; $i <= 7; $i++) {
            if (now()->copy()->addDays($i)->format('Y-m-d') === $date) {
                return true;
            }
        }

        return false;
    }

    public function selectDeliveryDate(string $date): void
    {
        // Jeder Tag der 7-Tage-Leiste ist wählbar (auch gesperrt) — für die Begründung.
        // Nicht über $this->deliveryDays gehen (Computed-Cache / Highlight).
        if (!$this->isDateInDeliveryWindow($date)) {
            return;
        }

        $holidays = $this->getNoDeliveryDates();
        $orderable = $this->isDeliveryDateOrderable(Carbon::parse($date)->startOfDay(), $holidays);

        // Gleicher Tag: bei bestellbaren Tagen Warenkorb neu laden
        if ($date === $this->selectedDeliveryDate) {
            if ($orderable) {
                $this->loadCartForSelectedDay();
            }
            unset($this->cartDetails);
            unset($this->selectedDayInfo);
            return;
        }

        // Entwurf des bisherigen bestellbaren Tages sichern
        if ($this->selectedDeliveryDate) {
            $prevOrderable = $this->isDeliveryDateOrderable(
                Carbon::parse($this->selectedDeliveryDate)->startOfDay(),
                $holidays
            );
            if ($prevOrderable) {
                $this->persistDeliveryDraft($this->selectedDeliveryDate);
            }
        }

        $this->selectedDeliveryDate = $date;

        if ($orderable) {
            $this->loadCartForSelectedDay();
        } else {
            // Gesperrter Tag: Warenkorb schließen, nicht bestellbar
            $this->cartDelivery = [];
            $this->saveCart();
            $this->dispatch('close-cart');
        }

        unset($this->cartDetails);
        unset($this->deliveryDays);
        unset($this->selectedDayInfo);
        unset($this->selectedDayOrderable);
    }

    private function ensureSelectedDeliveryDate(): void
    {
        $holidays = $this->getNoDeliveryDates();

        if ($this->selectedDeliveryDate) {
            $current = Carbon::parse($this->selectedDeliveryDate)->startOfDay();
            if ($this->isDeliveryDateOrderable($current, $holidays)) {
                return;
            }
        }

        $next = $this->calculateNextDeliveryDate()['date']->format('Y-m-d');
        if ($this->isDeliveryDateOrderable(Carbon::parse($next)->startOfDay(), $holidays)) {
            $this->selectedDeliveryDate = $next;
        } else {
            $this->selectedDeliveryDate = null;
            for ($i = 1; $i <= 7; $i++) {
                $candidate = now()->copy()->addDays($i)->startOfDay();
                if ($this->isDeliveryDateOrderable($candidate, $holidays)) {
                    $this->selectedDeliveryDate = $candidate->format('Y-m-d');
                    break;
                }
            }
        }

        if ($this->selectedDeliveryDate) {
            $this->loadCartForSelectedDay();
        }
    }

    private function findOrderForDate(?string $date): ?Order
    {
        if (!$date) {
            return null;
        }

        return Order::query()
            ->where('channel', $this->getChannel())
            ->whereDate('delivery_date', $date)
            ->first();
    }

    private function getDeliveryDrafts(): array
    {
        return \Cache::get($this->getCartPrefix() . 'cart_delivery_drafts', []);
    }

    private function persistDeliveryDraft(?string $date): void
    {
        if (!$date || $this->orderMode !== 'delivery') {
            return;
        }

        $drafts = $this->getDeliveryDrafts();
        $drafts[$date] = $this->cartDelivery;
        \Cache::put($this->getCartPrefix() . 'cart_delivery_drafts', $drafts);
    }

    private function clearDeliveryDraft(?string $date): void
    {
        if (!$date) {
            return;
        }

        $drafts = $this->getDeliveryDrafts();
        unset($drafts[$date]);
        \Cache::put($this->getCartPrefix() . 'cart_delivery_drafts', $drafts);
    }

    /**
     * Priorität: Tages-Entwurf (inkl. „2. Warenkorb“ nach Bestellung)
     * → sonst gespeicherte Bestellung → sonst leer.
     */
    private function loadCartForSelectedDay(): void
    {
        if (!$this->selectedDeliveryDate) {
            return;
        }

        $drafts = $this->getDeliveryDrafts();
        if (array_key_exists($this->selectedDeliveryDate, $drafts)) {
            $this->cartDelivery = $drafts[$this->selectedDeliveryDate] ?? [];
        } else {
            $order = $this->findOrderForDate($this->selectedDeliveryDate);
            $this->cartDelivery = $order
                ? $this->cartFromOrderItems($order->items ?? [])
                : [];
        }

        $this->saveCart();
    }

    private function cartFromOrderItems(array $items): array
    {
        $cart = [];
        foreach ($items as $key => $item) {
            $productId = $item['product_id'] ?? null;
            $variantId = $item['variant_id'] ?? null;
            $qty = (int) ($item['qty'] ?? 0);

            if (!$productId || !$variantId) {
                $parts = explode('_', (string) $key, 2);
                $productId = $productId ?: ($parts[0] ?? null);
                $variantId = $variantId ?: ($parts[1] ?? null);
            }

            if (!$productId || !$variantId || $qty <= 0) {
                continue;
            }

            $cartKey = $productId . '_' . $variantId;
            $cart[$cartKey] = [
                'product_id' => (int) $productId,
                'variant_id' => (int) $variantId,
                'qty' => $qty,
            ];
        }

        return $cart;
    }

    /** Bereits in der offenen Bestellung des gewählten Tages reservierte Menge (für Stock-Checks) */
    private function getReservedQtyForVariant($variantId): int
    {
        if ($this->orderMode !== 'delivery' || !$this->selectedDeliveryDate) {
            return 0;
        }

        $order = $this->findOrderForDate($this->selectedDeliveryDate);
        if (!$order || !$order->isOpen()) {
            return 0;
        }

        $sum = 0;
        foreach ($order->items ?? [] as $key => $item) {
            $vid = $item['variant_id'] ?? (explode('_', (string) $key, 2)[1] ?? null);
            if ((int) $vid === (int) $variantId) {
                $sum += (int) ($item['qty'] ?? 0);
            }
        }

        return $sum;
    }

    private function adjustStock(array $items, int $direction): void
    {
        foreach ($items as $key => $item) {
            $variantId = $item['variant_id'] ?? (explode('_', (string) $key, 2)[1] ?? null);
            $qty = (int) ($item['qty'] ?? 0);
            if (!$variantId || $qty <= 0) {
                continue;
            }

            $variant = \App\Models\ProductVariant::find($variantId);
            if ($variant && $variant->stock !== null) {
                if ($direction < 0) {
                    $variant->decrement('stock', $qty);
                } else {
                    $variant->increment('stock', $qty);
                }
            }
        }
    }


    // --- WARENKORB LOGIK ---

    // Gibt den passenden Präfix zurück (z.B. "guest_" oder "owner_")
    private function getCartPrefix()
    {
        return $this->isOwner ? 'owner_' : 'guest_';
    }

    // Speichert den aktuellen Stand sofort für alle anderen Geräte (gemeinsamer Cache pro Kanal)
    private function saveCart()
    {
        $prefix = $this->getCartPrefix();
        \Cache::put($prefix . 'cart_delivery', $this->cartDelivery);
        \Cache::put($prefix . 'cart_self', $this->cartSelf);

        // Liefer-Warenkorb zusätzlich pro gewähltem Tag als Entwurf merken
        if ($this->orderMode === 'delivery' && $this->selectedDeliveryDate) {
            $drafts = $this->getDeliveryDrafts();
            $drafts[$this->selectedDeliveryDate] = $this->cartDelivery;
            \Cache::put($prefix . 'cart_delivery_drafts', $drafts);
        }
    }

    /**
     * Gemeinsamen Warenkorb vom Server holen (Tablet + Handys, gleicher Kanal).
     * Bei Lieferung: Tages-Entwurf hat Vorrang vor dem generischen Cart-Cache.
     */
    public function syncCart()
    {
        $prefix = $this->getCartPrefix();
        $this->cartSelf = \Cache::get($prefix . 'cart_self', []);

        if ($this->orderMode === 'delivery' && $this->selectedDeliveryDate) {
            $drafts = $this->getDeliveryDrafts();
            if (array_key_exists($this->selectedDeliveryDate, $drafts)) {
                $this->cartDelivery = $drafts[$this->selectedDeliveryDate] ?? [];
            } else {
                $order = $this->findOrderForDate($this->selectedDeliveryDate);
                $this->cartDelivery = $order
                    ? $this->cartFromOrderItems($order->items ?? [])
                    : \Cache::get($prefix . 'cart_delivery', []);
            }
        } else {
            $this->cartDelivery = \Cache::get($prefix . 'cart_delivery', []);
        }

        unset($this->cartDetails);
        unset($this->cartSections);
        unset($this->cartMatchesOrder);
    }

    /** Vor jeder Änderung den gemeinsamen Stand laden (verhindert gegenseitiges Überschreiben) */
    private function pullSharedCart(): void
    {
        $this->syncCart();
    }

    private function &getActiveCart()
    {
        if ($this->orderMode === 'delivery') {
            return $this->cartDelivery;
        }
        return $this->cartSelf;
    }

    public function addToCart($productId, $variantId, $qty)
    {
        if ($this->orderMode === 'delivery' && !$this->selectedDayOrderable) {
            $this->dispatch('notify', message: 'Dieser Tag ist nicht bestellbar.', type: 'error');
            return;
        }

        $this->pullSharedCart();
        $cart = &$this->getActiveCart();
        $key = $productId . '_' . $variantId;

        $product = \App\Models\Product::with('variants')->find($productId);
        $variant = $product ? $product->variants->firstWhere('id', $variantId) : null;
        
        // Wir fragen jetzt sauber und logisch nach 'stock'
        $stock = $variant ? $variant->getAttribute('stock') : null;
        
        // Unendlich (9999) annehmen, wenn das Feld leer ist.
        // Bei bestehender Bestellung ist der Bestand schon abgezogen → reservierte Menge wieder freigeben.
        $maxQty = ($stock === null || $stock === '')
            ? 9999
            : (int) $stock + $this->getReservedQtyForVariant($variantId);
        
        $currentQty = isset($cart[$key]) ? $cart[$key]['qty'] : 0;
        $allowedToAdd = $maxQty - $currentQty;

        if ($allowedToAdd <= 0) {
            return; 
        }

        if ($qty > $allowedToAdd) {
            $qty = $allowedToAdd;
        }

        if (isset($cart[$key])) {
            $cart[$key]['qty'] += $qty;
        } else {
            $cart[$key] = [
                'product_id' => $productId,
                'variant_id' => $variantId,
                'qty' => $qty,
            ];
        }
        
        $this->saveCart(); 

        // Label für den Toast zusammenbauen:
        $product = \App\Models\Product::find($productId);
        $pName = json_decode($product->name, true)['de'] ?? 'Artikel';
        
        $extraInfo = '';
        if ($variantId) {
            $variant = $product->variants->where('id', $variantId)->first();
            $vName = json_decode($variant->name, true)['de'] ?? '';
            $extraInfo = trim($vName . ' ' . ($variant->size ?? ''));
        }

        $anzeigeName = $extraInfo ? "$pName ($extraInfo)" : $pName;

        $this->dispatch('open-cart', skipOnPhone: true);

        // Toast feuern! (z.B. "Hinzugefügt: 2x Cola (0,33L)")
        $this->dispatch('notify', message: "Hinzugefügt: {$qty}x {$anzeigeName}");
    }

    public function removeFromCart($key)
    {
        $this->pullSharedCart();
        $cart = &$this->getActiveCart();
        unset($cart[$key]);
        
        $this->saveCart(); // Sofort hochladen!
    }

    public function clearCart(bool $silent = false)
    {
        $this->pullSharedCart();

        if ($this->orderMode === 'delivery') {
            $this->cartDelivery = [];
        } else {
            $this->cartSelf = [];
        }
        
        $this->saveCart(); // Sofort hochladen!

        unset($this->cartDetails);
        unset($this->cartSections);
        unset($this->cartMatchesOrder);

        if (!$silent) {
            $this->dispatch('notify', message: 'Warenkorb wurde geleert.');
        }
    }

    /**
     * Nur den „Warenkorb“-Teil leeren (neue Artikel).
     * Gespeicherte Bestellung bleibt unberührt.
     */
    public function clearNewCartItems(): void
    {
        $this->pullSharedCart();

        if ($this->orderMode !== 'delivery' || ! $this->selectedDeliveryDate) {
            $this->clearCart();

            return;
        }

        $order = $this->findOrderForDate($this->selectedDeliveryDate);
        if (! $order) {
            $this->clearCart();

            return;
        }

        $this->cartDelivery = $this->cartFromOrderItems($order->items ?? []);
        $this->saveCart();

        unset($this->cartDetails);
        unset($this->cartSections);
        unset($this->cartMatchesOrder);

        $this->dispatch('notify', message: 'Warenkorb wurde geleert.');
    }

    /** Gespeicherte Bestellung für den gewählten Tag komplett löschen (inkl. Bestand zurück) */
    public function deleteOrder(): void
    {
        if ($this->orderMode !== 'delivery' || ! $this->selectedDeliveryDate) {
            return;
        }

        $order = $this->findOrderForDate($this->selectedDeliveryDate);
        if (! $order) {
            $this->dispatch('notify', message: 'Keine Bestellung gefunden.', type: 'error');

            return;
        }

        if ($order->isLocked()) {
            $this->dispatch('notify', message: 'Abgeschlossene Bestellungen können nicht gelöscht werden.', type: 'error');

            return;
        }

        if (! $this->selectedDayOrderable) {
            $this->dispatch('notify', message: 'Bestellfrist abgelaufen — Löschen nicht mehr möglich.', type: 'error');

            return;
        }

        $this->adjustStock($order->items ?? [], +1);
        \Illuminate\Support\Facades\Cache::forget('shop_products_cache');

        $frozenDate = Carbon::parse($this->selectedDeliveryDate)->format('d.m.Y');
        $order->delete();

        $this->cartDelivery = [];
        $this->clearDeliveryDraft($this->selectedDeliveryDate);
        $this->saveCart();

        $this->history = collect($this->history)
            ->reject(fn ($o) => ($o['mode'] ?? null) === 'delivery'
                && ($o['delivery_date'] ?? null) === $frozenDate)
            ->values()
            ->all();

        $prefix = $this->getCartPrefix();
        \Illuminate\Support\Facades\Cache::put($prefix . 'order_history', $this->history);

        unset($this->cartDetails);
        unset($this->cartSections);
        unset($this->cartMatchesOrder);
        unset($this->deliveryDays);
        unset($this->selectedDayInfo);
        $this->renderKey++;

        $this->dispatch('notify', message: 'Bestellung gelöscht.');
    }

    public function updateCartQty($key, $newQty)
    {
        $this->pullSharedCart();
        $cart = &$this->getActiveCart();
        if (!isset($cart[$key])) return;

        if ($newQty <= 0) {
            unset($cart[$key]);
            $this->saveCart();
            return;
        }

        $productId = $cart[$key]['product_id'];
        $variantId = $cart[$key]['variant_id'];

        // Strenge Bestandsprüfung wie in addToCart()
        $product = \App\Models\Product::with('variants')->find($productId);
        $variant = $product ? $product->variants->firstWhere('id', $variantId) : null;
        $stock = $variant ? $variant->getAttribute('stock') : null;
        $maxQty = ($stock === null || $stock === '')
            ? 9999
            : (int) $stock + $this->getReservedQtyForVariant($variantId);

        // Harte Grenze setzen
        if ($newQty > $maxQty) {
            $newQty = $maxQty;
            $this->dispatch('notify', message: 'Maximaler Lagerbestand erreicht!', type: 'error');
        }

        $cart[$key]['qty'] = $newQty;
        $this->saveCart();
    }

    public function updateCartVariant($oldKey, $newVariantId)
    {
        $this->pullSharedCart();
        $cart = &$this->getActiveCart();
        if (!isset($cart[$oldKey])) return;

        $item = $cart[$oldKey];
        $productId = $item['product_id'];
        $newKey = $productId . '_' . $newVariantId;

        if ($oldKey === $newKey) return; 

        // Bestand der NEUEN Variante prüfen
        $product = \App\Models\Product::with('variants')->find($productId);
        $newVariant = $product ? $product->variants->firstWhere('id', $newVariantId) : null;
        $stock = $newVariant ? $newVariant->getAttribute('stock') : null;
        $maxQty = ($stock === null || $stock === '')
            ? 9999
            : (int) $stock + $this->getReservedQtyForVariant($newVariantId);

        $currentQtyOfNewVariant = isset($cart[$newKey]) ? $cart[$newKey]['qty'] : 0;
        $qtyToMove = $item['qty'];
        
        $allowedToAdd = $maxQty - $currentQtyOfNewVariant;

        // Wenn die neue Variante schon ausverkauft/am Limit ist
        if ($allowedToAdd <= 0) {
            $this->dispatch('notify', message: 'Die gewählte Variante hat keinen ausreichenden Bestand mehr.', type: 'error');
            return;
        }

        if ($qtyToMove > $allowedToAdd) {
            $qtyToMove = $allowedToAdd;
            $this->dispatch('notify', message: 'Menge wurde an den Restbestand angepasst.', type: 'error');
        }

        if (isset($cart[$newKey])) {
            // Neue Variante ist schon im Korb: Mengen zusammenführen
            $cart[$newKey]['qty'] += $qtyToMove;
            unset($cart[$oldKey]);
        } else {
            // Array neu aufbauen und den alten Key AN ORT UND STELLE ersetzen,
            // damit das Item nicht ans Ende des Warenkorbs rutscht
            $newCart = [];
            foreach ($cart as $k => $entry) {
                if ($k === $oldKey) {
                    $newCart[$newKey] = [
                        'product_id' => $productId,
                        'variant_id' => $newVariantId,
                        'qty' => $qtyToMove,
                    ];
                } else {
                    $newCart[$k] = $entry;
                }
            }
            $cart = $newCart;
        }

        $this->saveCart();
    }

    private function normalizeCartMap(array $items): array
    {
        $map = [];
        foreach ($items as $key => $item) {
            $productId = $item['product_id'] ?? null;
            $variantId = $item['variant_id'] ?? null;
            $qty = (int) ($item['qty'] ?? 0);

            if (!$productId || !$variantId) {
                $parts = explode('_', (string) $key, 2);
                $productId = $productId ?: ($parts[0] ?? null);
                $variantId = $variantId ?: ($parts[1] ?? null);
            }

            if (!$productId || !$variantId || $qty <= 0) {
                continue;
            }

            $k = ((int) $productId) . '_' . ((int) $variantId);
            $map[$k] = ($map[$k] ?? 0) + $qty;
        }
        ksort($map);

        return $map;
    }

    /** Warenkorb entspricht exakt der gespeicherten Bestellung des gewählten Tags */
    public function getCartMatchesOrderProperty(): bool
    {
        if ($this->orderMode !== 'delivery' || !$this->selectedDeliveryDate) {
            return false;
        }

        $order = $this->findOrderForDate($this->selectedDeliveryDate);
        if (!$order) {
            return false;
        }

        return $this->normalizeCartMap($this->cartDelivery) === $this->normalizeCartMap($order->items ?? []);
    }

    /**
     * Aufteilung für die Sidebar: Bestellung (bereits gespeichert) vs. neuer Warenkorb.
     */
    public function getCartSectionsProperty(): array
    {
        $details = $this->cartDetails;
        $items = $details['items'] ?? [];
        $hasOrder = false;
        $orderKeys = [];

        if ($this->orderMode === 'delivery' && $this->selectedDeliveryDate) {
            $order = $this->findOrderForDate($this->selectedDeliveryDate);
            if ($order) {
                $hasOrder = true;
                $orderKeys = array_fill_keys(array_keys($this->normalizeCartMap($order->items ?? [])), true);
            }
        }

        $orderItems = [];
        $cartItems = [];

        if (!$hasOrder) {
            $cartItems = $items;
        } else {
            foreach ($items as $key => $item) {
                $normKey = ((int) ($item['product_id'] ?? 0)) . '_' . ((int) ($item['variant_id'] ?? 0));
                if (isset($orderKeys[$normKey])) {
                    $orderItems[$key] = $item;
                } else {
                    $cartItems[$key] = $item;
                }
            }
        }

        $count = (int) ($details['count'] ?? 0);
        $dirty = $hasOrder && ! $this->cartMatchesOrder;

        $orderable = $this->orderMode !== 'delivery' || $this->selectedDayOrderable;

        return [
            'has_order' => $hasOrder,
            'order_items' => $orderItems,
            'cart_items' => $cartItems,
            'show_update' => $dirty && $count > 0,
            'show_reset' => $dirty,
            'show_place' => ! $hasOrder && $count > 0,
            'show_delete_order' => $hasOrder && $orderable,
            'show_clear_cart' => $orderable && (
                count($cartItems) > 0 || (! $hasOrder && $count > 0)
            ),
        ];
    }

    /** Verwirft lokale Änderungen und lädt die gespeicherte Bestellung neu */
    public function resetCartToOrder(): void
    {
        if ($this->orderMode !== 'delivery' || ! $this->selectedDeliveryDate) {
            return;
        }

        $order = $this->findOrderForDate($this->selectedDeliveryDate);
        if (! $order) {
            $this->dispatch('notify', message: 'Keine gespeicherte Bestellung gefunden.', type: 'error');

            return;
        }

        $this->clearDeliveryDraft($this->selectedDeliveryDate);
        $this->loadCartForSelectedDay();

        unset($this->cartDetails);
        unset($this->cartSections);
        unset($this->cartMatchesOrder);
        unset($this->selectedDayInfo);

        $this->dispatch('notify', message: 'Bestellung wiederhergestellt.');
    }

    public function getCartDetailsProperty()
    {
        $activeCart = $this->orderMode === 'delivery' ? $this->cartDelivery : $this->cartSelf;
        $details = [];
        $total = 0;
        
        if (empty($activeCart)) {
            return ['items' => [], 'total' => 0, 'count' => 0];
        }
        
        $productIds = collect($activeCart)->pluck('product_id')->unique();
        $products = \App\Models\Product::with('variants')->whereIn('id', $productIds)->get()->keyBy('id');
        $actionMonth = $this->orderMode === 'delivery' && $this->selectedDeliveryDate
            ? Carbon::parse($this->selectedDeliveryDate)->month
            : now()->addDay()->month;
        
        foreach ($activeCart as $key => $item) {
            $product = $products->get($item['product_id']);
            if (!$product) continue;
            
            $variant = $product->variants->firstWhere('id', $item['variant_id']);
            if (!$variant) continue;
            
            $pName = json_decode($product->name, true)['de'] ?? 'Unbekannt';
            $vName = json_decode($variant->name, true)['de'] ?? '';
            
            $price = $variant->price;
            if ($variant->sale_month == $actionMonth) {
                $price = round($price * 0.85, 2);
            }
            
            $lineTotal = $price * $item['qty'];
            $total += $lineTotal;

            // Metadaten für das Klick-Modal sammeln
            $desc = json_decode($variant->description, true)['de'] ?? '';
            $allergensArr = json_decode($variant->allergens, true) ?? [];
            
            $availableVariants = $product->variants->map(function($v) use ($actionMonth) {
                $vLabel = json_decode($v->name, true)['de'] ?? '';
                $vPrice = $v->price;
                if ($v->sale_month == $actionMonth) {
                    $vPrice = round($vPrice * 0.85, 2);
                }
                return [
                    'id' => $v->id,
                    'label' => trim($vLabel . ' ' . $v->size),
                    'price' => $vPrice,
                    'stock' => $v->stock ?? 9999
                ];
            })->toArray();

            $stock = $variant->getAttribute('stock');
            $maxStock = ($stock === null || $stock === '')
                ? 9999
                : (int) $stock + $this->getReservedQtyForVariant($item['variant_id']);

            $details[$key] = [
                'product_id'     => $product->id,
                'variant_id'     => $item['variant_id'],
                'image_path'     => $product->image_path,
                'name'           => $pName,      
                'variant_name'   => $vName,      
                'size'           => $variant->size, 
                'price'          => $price,
                'qty'            => $item['qty'],
                'line_total'     => $lineTotal,
                'desc'           => $desc, // NEU FÜR INFO MODAL
                'allergens'      => !empty($allergensArr) ? implode(' | ', $allergensArr) : '', // NEU
                'available_variants' => $availableVariants,
                'max_stock'      => $maxStock
            ];
        }
        
        return [
            'items' => $details,
            'total' => $total,
            'count' => collect($activeCart)->sum('qty')
        ];
    }

    // Wird von Livewire automatisch aufgerufen, sobald man im Suchfeld tippt
    public function updatedSearch()
    {
        if (!empty($this->search)) {
            $this->selectedCategory = '__all'; // Springe sauber auf "Alle Kategorien"
        }
    }

    public function mount()
    {
        $this->isOwner = $this->detectOwnerFromIp();
        $this->syncCart();
        $this->loadHistory();
        $this->checkStayDates();

        if ($this->orderMode === 'delivery') {
            $this->ensureSelectedDeliveryDate();
        }
    }

    public function loadHistory()
    {
        $prefix = $this->getCartPrefix();
        // Lade die Historie (falls leer, dann leeres Array)
        $this->history = \Cache::get($prefix . 'order_history', []);
    }

    public function checkStayDates()
    {
        $today = now()->toDateString();

        try {
            // Wir nutzen jetzt ganz elegant unser neues Model!
            $currentBooking = \App\Models\Booking::where('check_in', '<=', $today)
                ->where('check_out', '>=', $today)
                ->first();

            if ($currentBooking) {
                if ($currentBooking->check_in === $today) {
                    $this->showWelcomeModal = true;
                }
                if ($currentBooking->check_out === $today && !empty($this->history)) {
                    $this->showCheckoutModal = true;
                }
            }
        } catch (\Exception $e) {
            // Falls die DB mal nicht erreichbar ist
        }
    }

    // Wird aufgerufen, wenn man im Warenkorb auf "Kostenpflichtig bestellen" klickt
    public function placeOrder()
    {
        $this->pullSharedCart();
        $details = $this->cartDetails;
        if ($details['count'] === 0) {
            return;
        }

        $ok = $this->orderMode === 'delivery'
            ? $this->placeDeliveryOrder($details)
            : $this->placeSelfOrder($details);

        if (!$ok) {
            return;
        }

        if ($this->orderMode === 'delivery') {
            // Bestellung bleibt im Warenkorb editierbar (nicht leeren)
            $this->loadCartForSelectedDay();
        } else {
            $this->clearCart(true);
        }

        unset($this->cartDetails);
        unset($this->cartSections);
        unset($this->cartMatchesOrder);
        unset($this->deliveryDays);
        unset($this->selectedDayInfo);
        $this->renderKey++;
        $this->dispatch('order-placed');
    }

    private function placeDeliveryOrder(array $details): bool
    {
        if (!$this->selectedDeliveryDate) {
            $this->ensureSelectedDeliveryDate();
        }

        if (!$this->selectedDeliveryDate) {
            $this->dispatch('notify', message: 'Kein Liefertag verfügbar.', type: 'error');
            return false;
        }

        $deliveryDate = Carbon::parse($this->selectedDeliveryDate)->startOfDay();
        $holidays = $this->getNoDeliveryDates();

        if (!$this->isDeliveryDateOrderable($deliveryDate, $holidays)) {
            $this->dispatch('notify', message: 'Bestellfrist für diesen Tag ist abgelaufen.', type: 'error');
            return false;
        }

        $existing = $this->findOrderForDate($this->selectedDeliveryDate);
        if ($existing && $existing->isLocked()) {
            $this->dispatch('notify', message: 'Diese Bestellung ist bereits abgeschlossen.', type: 'error');
            return false;
        }

        // Bestand: alte Bestellung zurückbuchen, neue abziehen
        if ($existing) {
            $this->adjustStock($existing->items ?? [], +1);
        }
        $this->adjustStock($details['items'], -1);

        \Illuminate\Support\Facades\Cache::forget('shop_products_cache');

        $order = Order::updateOrCreate(
            [
                'delivery_date' => $this->selectedDeliveryDate,
                'channel' => $this->getChannel(),
            ],
            [
                'status' => 'open',
                'items' => $details['items'],
                'total' => $details['total'],
                'placed_at' => $existing?->placed_at ?? now(),
            ]
        );

        $frozenDate = $deliveryDate->format('d.m.Y');
        $historyEntry = [
            'id' => $order->id,
            'date' => now()->toIso8601String(),
            'delivery_date' => $frozenDate,
            'mode' => 'delivery',
            'total' => $details['total'],
            'items' => $details['items'],
        ];

        // Gleichen Liefertag in der Historie ersetzen statt zu duplizieren
        $this->history = collect($this->history)
            ->reject(fn ($o) => ($o['mode'] ?? null) === 'delivery'
                && ($o['delivery_date'] ?? null) === $frozenDate)
            ->prepend($historyEntry)
            ->values()
            ->all();

        $prefix = $this->getCartPrefix();
        \Illuminate\Support\Facades\Cache::put($prefix . 'order_history', $this->history);

        // Entwurf auf den neuen Bestellstand setzen (kein „2. Warenkorb“ mehr)
        $this->cartDelivery = $this->cartFromOrderItems($details['items']);
        $this->persistDeliveryDraft($this->selectedDeliveryDate);

        $this->dispatch(
            'notify',
            message: $existing ? 'Bestellung aktualisiert!' : 'Bestellung erfolgreich gesendet!'
        );

        return true;
    }

    private function placeSelfOrder(array $details): bool
    {
        $activeCart = $this->cartSelf;
        foreach ($activeCart as $item) {
            if ($item['variant_id']) {
                $variant = \App\Models\ProductVariant::find($item['variant_id']);
                if ($variant && $variant->stock !== null) {
                    $variant->decrement('stock', $item['qty']);
                }
            }
        }

        \Illuminate\Support\Facades\Cache::forget('shop_products_cache');

        $newOrder = [
            'id' => now()->timestamp,
            'date' => now()->toIso8601String(),
            'delivery_date' => now()->format('d.m.Y'),
            'mode' => 'self',
            'total' => $details['total'],
            'items' => $details['items'],
        ];

        array_unshift($this->history, $newOrder);

        $prefix = $this->getCartPrefix();
        \Illuminate\Support\Facades\Cache::put($prefix . 'order_history', $this->history);

        $this->dispatch('notify', message: 'Entnahme verbucht!');

        return true;
    }

    // Wird aufgerufen, wenn der Gast am Abreisetag bezahlt
    public function resetHistory()
    {
        $this->history = [];
        $prefix = $this->getCartPrefix();
        \Cache::forget($prefix . 'order_history');
        
        $this->showCheckoutModal = false;
    }

    // Hilfsfunktion: Berechnet die Gesamtsumme aller Bestellungen
    public function getStayTotalProperty()
    {
        return collect($this->history)->sum('total');
    }

    /**
     * Historie für die UI: Lieferungen nach Liefertag, SB separat.
     */
    public function getHistorySectionsProperty(): array
    {
        $holidays = $this->getNoDeliveryDates();
        $delivery = [];
        $self = [];

        foreach ($this->history as $order) {
            if (($order['mode'] ?? '') === 'self') {
                $self[] = [
                    'raw' => $order,
                    'label_day' => Carbon::parse($order['date'])->locale('de')->translatedFormat('D, d.m.Y'),
                    'placed_label' => Carbon::parse($order['date'])->format('d.m.Y H:i'),
                ];
                continue;
            }

            $deliveryDate = null;
            try {
                $deliveryDate = Carbon::createFromFormat('d.m.Y', $order['delivery_date'] ?? '')->startOfDay();
            } catch (\Exception $e) {
                try {
                    $deliveryDate = Carbon::parse($order['delivery_date'] ?? $order['date'])->startOfDay();
                } catch (\Exception $e2) {
                    $deliveryDate = Carbon::parse($order['date'])->startOfDay();
                }
            }

            $ymd = $deliveryDate->format('Y-m-d');
            $dbOrder = $this->findOrderForDate($ymd);
            $editable = $this->isDeliveryDateOrderable($deliveryDate, $holidays)
                && (!$dbOrder || $dbOrder->isOpen());

            $delivery[] = [
                'raw' => $order,
                'date_ymd' => $ymd,
                'label_day' => $deliveryDate->locale('de')->translatedFormat('D, d.m.Y'),
                'placed_label' => Carbon::parse($order['date'])->format('d.m.Y H:i'),
                'editable' => $editable,
                'status_label' => $editable ? 'änderbar' : 'abgeschlossen',
            ];
        }

        usort($delivery, fn ($a, $b) => strcmp($a['date_ymd'], $b['date_ymd']));

        return [
            'delivery' => $delivery,
            'self' => $self,
            'is_empty' => empty($delivery) && empty($self),
        ];
    }

    /** Aus der Historie: Tag wählen, Warenkorb laden/öffnen */
    public function openHistoryOrder(string $dateYmd): void
    {
        $existing = $this->findOrderForDate($dateYmd);
        if (!$this->isDateInDeliveryWindow($dateYmd) && !$existing) {
            return;
        }

        if ($this->orderMode !== 'delivery') {
            $this->setOrderMode('delivery');
        }

        $holidays = $this->getNoDeliveryDates();
        $orderable = $this->isDeliveryDateOrderable(Carbon::parse($dateYmd)->startOfDay(), $holidays);

        if ($this->selectedDeliveryDate && $this->selectedDeliveryDate !== $dateYmd) {
            $prevOrderable = $this->isDeliveryDateOrderable(
                Carbon::parse($this->selectedDeliveryDate)->startOfDay(),
                $holidays
            );
            if ($prevOrderable) {
                $this->persistDeliveryDraft($this->selectedDeliveryDate);
            }
        }

        $this->selectedDeliveryDate = $dateYmd;
        $this->loadCartForSelectedDay();

        unset($this->cartDetails);
        unset($this->cartSections);
        unset($this->cartMatchesOrder);
        unset($this->deliveryDays);
        unset($this->selectedDayInfo);
        unset($this->selectedDayOrderable);
        unset($this->historySections);

        $this->dispatch('close-history');
        if ($orderable || $existing) {
            $this->dispatch('open-cart');
        }
    }

    public function finalizeCheckout()
    {
        // 1. Historie und Warenkörbe löschen
        $prefix = $this->getCartPrefix();
        \Cache::forget($prefix . 'order_history');
        \Cache::forget($prefix . 'cart_delivery');
        \Cache::forget($prefix . 'cart_self');
        
        // Arrays leeren
        $this->history = [];
        $this->cartDelivery = [];
        $this->cartSelf = [];
        unset($this->cartDetails);

        // 2. Optional: Das Flag auf false setzen
        $this->showCheckoutModal = false;

        // 3. Signal feuern: "Wir sind fertig!"
        $this->dispatch('checkout-completed');
        
        // Toast feuern (Optional)
        $this->dispatch('notify', message: __('Checkout erfolgreich. Vielen Dank!'), type: 'success');
    }

    // NEU: Diese Funktion wird aufgerufen, wenn der Gast auf "Absenden" im Feedback drückt
    public function sendFeedback()
    {
        // Sicherstellen, dass überhaupt etwas eingetippt wurde
        if (trim($this->feedbackText) === '') {
            return;
        }

        // --- HIER KOMMT SPÄTER DEINE SPEICHER-LOGIK REIN ---
        // z.B. Feedback::create(['text' => $this->feedbackText, 'room' => $prefix]);
        // ---------------------------------------------------

        // Textfeld nach dem Senden wieder leeren
        $this->feedbackText = '';
        
        // Optional: Ein kleines Toast für den User
        $this->dispatch('notify', message: __('Feedback erfolgreich gesendet!'), type: 'success');
    }

    #[Computed]
    public function getProcessedProductsProperty()
    {
        // Wir speichern das fertige Array für 60 Minuten (3600 Sekunden) im Cache!
        return \Illuminate\Support\Facades\Cache::remember('shop_products_cache', 3600, function () {
            
            $products = \App\Models\Product::with('variants')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();

            $actionMonth = now()->addDay()->month; 

            foreach ($products as $p) {
                foreach ($p->variants as $v) {
                    $v->db_price = $v->price; 
                    
                    if ($v->getAttribute('sale_month') == $actionMonth) {
                        if (!$v->getAttribute('original_price')) {
                            $v->setAttribute('original_price', $v->db_price);
                        }
                        $v->price = round($v->original_price * 0.85, 2);
                    }
                }
            }

            return $products;
        });
    }

    public function render()
    {
        // 1. Greife auf unsere sauber vorbereiteten Produkte zu
        $allProducts = $this->processedProducts;

        // 2. MODUS FILTER (Lieferung vs SB)
        $filteredByMode = $allProducts->filter(function($p) {
            $superCat = strtolower(json_decode($p->super_category, true)['de'] ?? '');
            $isSB = str_contains($superCat, 'selbstbedienung') || str_contains($superCat, 'self-service');
            
            if ($this->orderMode === 'delivery') return !$isSB;
            return $isSB;
        });

        // 3. CATEGORY DROPDOWN BAUEN (WICHTIG: Vor der Suche bauen, damit es stabil bleibt!)
        $dropdownItems = [];
        $superCats = [];
        
        foreach ($filteredByMode as $p) {
            $sCat = json_decode($p->super_category, true)['de'] ?? 'Sonstiges';
            $cat = json_decode($p->category, true)['de'] ?? 'Sonstiges';
            
            if (!isset($superCats[$sCat])) {
                $superCats[$sCat] = [];
            }
            if (!in_array($cat, $superCats[$sCat])) {
                $superCats[$sCat][] = $cat;
            }
        }

        foreach ($superCats as $sCatName => $cats) {
            $dropdownItems[] = ['value' => '__super_' . $sCatName, 'label' => $sCatName, 'isGroup' => true];
            foreach ($cats as $catName) {
                $dropdownItems[] = ['value' => $sCatName . '||' . $catName, 'label' => '  ' . $catName, 'isGroup' => false];
            }
        }

        // 4. KATEGORIE FILTER
        if ($this->selectedCategory !== '__all') {
            $filteredByMode = $filteredByMode->filter(function($p) {
                $sCat = json_decode($p->super_category, true)['de'] ?? 'Sonstiges';
                $cat = json_decode($p->category, true)['de'] ?? 'Sonstiges';
                
                if (str_starts_with($this->selectedCategory, '__super_')) {
                    return $sCat === str_replace('__super_', '', $this->selectedCategory);
                } else {
                    $parts = explode('||', $this->selectedCategory);
                    $targetCat = count($parts) === 2 ? $parts[1] : $parts[0];
                    return $cat === $targetCat;
                }
            });
        }

        // 5. SUCHE FILTER
        if (!empty($this->search)) {
            $searchTerm = strtolower($this->search);
            $filteredByMode = $filteredByMode->filter(function($p) use ($searchTerm) {
                $nameDe = strtolower(json_decode($p->name, true)['de'] ?? '');
                return str_contains($nameDe, $searchTerm);
            });
        }

        // 6. SALE LOGIK (Neu, sicher und einfach)
        $actionMonth = now()->addDay()->month; 
        $saleProducts = collect();
        $normalProducts = collect();

        foreach ($filteredByMode as $p) {
            $isSaleItem = false;
            foreach ($p->variants as $v) {
                if ($v->getAttribute('sale_month') == $actionMonth) {
                    $isSaleItem = true;
                    break; // Einer reicht, um das Produkt zum Sale zu machen
                }
            }

            if ($isSaleItem) {
                $saleProducts->push($p);
            } else {
                $normalProducts->push($p);
            }
        }
        
        // 7. FÜR DIE AUSGABE GRUPPIEREN
        $groupedProducts = [];
        foreach ($normalProducts as $p) {
            $sCat = json_decode($p->super_category, true)['de'] ?? 'Sonstiges';
            $cat = json_decode($p->category, true)['de'] ?? 'Sonstiges';
            
            if (!isset($groupedProducts[$sCat])) {
                $groupedProducts[$sCat] = [];
            }
            if (!isset($groupedProducts[$sCat][$cat])) {
                $groupedProducts[$sCat][$cat] = [];
            }
            
            $groupedProducts[$sCat][$cat][] = $p;
        }

        $currentCategoryLabel = 'Alle Kategorien';
        foreach ($dropdownItems as $item) {
            if ($item['value'] === $this->selectedCategory) {
                $currentCategoryLabel = trim(str_replace('&nbsp;', '', $item['label']));
            }
        }

        return view('livewire.shop', [
            'groupedProducts' => $groupedProducts,
            'saleProducts' => $saleProducts, 
            'dropdownItems' => $dropdownItems,
            'currentCategoryLabel' => $currentCategoryLabel
        ]);
    }
}