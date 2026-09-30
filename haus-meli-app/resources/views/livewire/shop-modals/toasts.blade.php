<!-- Unten in der shop.blade.php, vor dem schließenden </div> -->
<div wire:ignore x-data="{ 
    toasts: [],
    add(message, type = 'success') {
        let id = Date.now();
        this.toasts.push({ id: id, message: message, type: type, show: true });
        
        setTimeout(() => {
            let toast = this.toasts.find(t => t.id === id);
            if (toast) toast.show = false;
            setTimeout(() => { this.toasts = this.toasts.filter(t => t.id !== id); }, 300);
        }, 3000);
    }
}" 
@notify.window="add($event.detail.message, $event.detail.type || 'success')"
style="position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); z-index: 9999; display: flex; flex-direction: column; gap: 10px; pointer-events: none;">
    
    <template x-for="toast in toasts" :key="toast.id">
        <div x-show="toast.show" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform translate-y-4"
             x-transition:enter-end="opacity-100 transform translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             style="background: var(--card); color: var(--text); padding: 12px 24px; border-radius: 50px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); border: 1px solid var(--border); pointer-events: auto;">
             <span x-text="toast.message"></span>
        </div>
    </template>
</div>