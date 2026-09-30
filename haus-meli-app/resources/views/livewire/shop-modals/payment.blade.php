<div x-data="{ paymentOpen: false }" 
        @open-payment.window="paymentOpen = true"
        @checkout-completed.window="paymentOpen = false"
        class="modal" 
        :style="paymentOpen ? 'display: flex; z-index: 7000; background: rgba(0,0,0,0.6);' : 'display: none;'" 
        @click="paymentOpen = false">
        
        <div class="panel" style="max-width: 400px; text-align: center;" @click.stop>

            <h3 style="margin-bottom: 20px; color: var(--text);">{{ __('Zahlung wählen') }}</h3>

            <div style="margin-bottom: 20px; font-size: 1.2rem;">
                <span>{{ __('Gesamtbetrag:') }}</span>
                <strong style="color: var(--accent);">{{ number_format($this->stayTotal, 2, ',', '.') }} €</strong>
            </div>

            <div style="display: flex; flex-direction: column; gap: 10px;">
                @php
                    $paypalAmount = number_format($this->stayTotal, 2, '.', '');
                @endphp
                <a href="https://www.paypal.com/paypalme/DeinName/{{ $paypalAmount }}EUR" target="_blank"
                    style="text-decoration:none; display:flex; align-items:center; justify-content:center; gap:10px; background:#0070BA; color:white; padding:15px; border-radius:12px; font-weight:bold; transition: transform 0.1s;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="white">
                        <path d="M7.076 21.337H2.47a.641.641 0 0 1-.633-.74L4.944.901C5.026.382 5.474 0 5.998 0h7.46c2.57 0 4.578.543 5.69 1.81 1.01 1.15 1.304 2.42 1.012 4.287-.023.143-.047.288-.077.437-.946 5.05-4.336 6.794-9.02 6.794H10.3l-1.917 12.196a.56.56 0 0 1-.555.467 2.47.64 0 0 1-.752-.654z" />
                    </svg>
                    <span>{{ __('PayPal (Schnell)') }}</span>
                </a>

                <button @click="$dispatch('open-qr')"
                    style="background: var(--surface); color: var(--text); border: 1px solid var(--border); padding: 15px; border-radius: 12px; font-weight:bold; display:flex; align-items:center; justify-content:center; gap:10px;">
                    <span>💸 {{ __('Bar / Überweisung') }}</span>
                </button>
            </div>

            <p style="margin-top: 20px; font-size: 0.85rem; color: var(--muted); line-height: 1.4;">
                {!! __('Bitte bezahlen Sie den Betrag jetzt.<br>Klicken Sie danach unten auf "Abschließen".') !!}
            </p>

            <hr style="border: 0; border-top: 1px solid var(--border); margin: 20px 0;">

            <button wire:click="finalizeCheckout" 
                    wire:loading.attr="disabled"
                    id= "finalize-checkout-btn"
                    class="add-btn"
                    style="width: 100%; background: var(--color-green-soft); color: var(--color-green-text); border: 1px solid var(--color-green-border);">
                <span wire:loading.remove wire:target="finalizeCheckout">{{ __('Zahlung getätigt & Abschließen') }}</span>
                <span wire:loading wire:target="finalizeCheckout">{{ __('Wird verarbeitet...') }}</span>
            </button>

            <button @click="paymentOpen = false"
                style="margin-top: 10px; background: transparent; border: none; color: var(--muted); cursor: pointer;">
                {{ __('Abbrechen') }}
            </button>
        </div>
    </div>