<div x-data="{ thanksOpen: false, showForm: false, feedbackSent: false }" 
     @checkout-completed.window="thanksOpen = true"
     class="modal" 
     :style="thanksOpen ? 'display: flex; z-index: 12000; background: rgba(0,0,0,0.85);' : 'display: none;'">
     
    <div class="panel" style="text-align: center; max-width: 350px;" @click.stop>
        <div style="font-size: 3.5rem; margin-bottom: 10px;">👋</div>

        <h3 style="font-size: 1.5rem; margin-bottom: 15px;">{{ __('Vielen Dank!') }}</h3>

        <p style="color: var(--muted); margin-bottom: 25px; line-height: 1.5;">
            {{ __('Wir hoffen, Sie hatten einen wunderbaren Aufenthalt.') }}
        </p>

        <button x-show="!showForm && !feedbackSent" 
                @click="showForm = true" 
                class="add-btn"
                style="width: 100%; margin-bottom: 12px; background: #FFD700; color: #333; border: none; font-weight: 800; cursor: pointer;">
            <span>✍️ {{ __('Feedback schreiben') }}</span>
        </button>

        <div x-show="showForm && !feedbackSent" x-transition style="display: none; margin-bottom: 15px;">
            <textarea wire:model="feedbackText" rows="4"
                style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text); resize: none; margin-bottom: 10px; box-sizing: border-box;"
                placeholder="{{ __('Wie hat es Ihnen gefallen?') }}"></textarea>
            
            <button wire:click="sendFeedback" 
                    @click="feedbackSent = true; showForm = false"
                    style="width: 100%; padding: 10px; background: var(--accent); color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer;">
                <span wire:loading.remove wire:target="sendFeedback">{{ __('Absenden') }}</span>
                <span wire:loading wire:target="sendFeedback">{{ __('Wird gesendet...') }}</span>
            </button>
        </div>

        <div x-show="feedbackSent" x-transition
            style="display: none; margin-bottom: 20px; color: var(--color-green-text); font-weight: bold; padding: 10px; background: var(--color-green-soft); border-radius: 8px;">
            <span>{{ __('Danke für Ihr Feedback!') }}</span>
        </div>

        <button @click="location.reload()"
            style="width: 100%; padding: 12px; background: var(--surface); border: 1px solid var(--border); color: var(--text); border-radius: var(--radius); cursor: pointer; font-weight: bold;">
            <span>{{ __('App neu starten') }}</span>
        </button>
    </div>
</div>