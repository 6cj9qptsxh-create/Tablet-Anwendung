<nav class="shell-nav" aria-label="Seiten" :class="{ 'is-wheeling': wheelOn, 'is-dragging': wheelDrag }">
    <button type="button"
            class="shell-nav-side"
            @click="goNeighbor(-1)"
            :disabled="!neighbor(-1)">
        <span class="material-symbols-rounded" aria-hidden="true">chevron_left</span>
        <span class="shell-nav-label" x-text="neighbor(-1) ? tabName(neighbor(-1)) : ''"></span>
    </button>
    <div class="shell-nav-wheel"
         role="slider"
         tabindex="0"
         aria-orientation="horizontal"
         aria-label="Seite wählen"
         aria-valuemin="0"
         :aria-valuemax="tabs.length - 1"
         :aria-valuenow="Math.min(tabs.length - 1, Math.max(0, Math.round(wheelIndex)))"
         :aria-valuetext="tabName(tabs[Math.min(tabs.length - 1, Math.max(0, Math.round(wheelIndex)))] || currentTab)"
         :style="wheelStyle()"
         x-on:pointerdown="wheelDown($event)"
         x-on:pointermove="wheelMove($event)"
         x-on:pointerup="wheelUp($event)"
         x-on:pointercancel="wheelUp($event)"
         x-on:keydown.left.prevent="goNeighbor(-1)"
         x-on:keydown.right.prevent="goNeighbor(1)">
        <div class="shell-nav-lens shell-nav-current" aria-hidden="true"></div>
        <div class="shell-nav-wheel-clip" aria-hidden="true">
            <div class="shell-nav-wheel-track">
                <template x-for="(tab, i) in tabs" :key="tab">
                    <span class="shell-nav-wheel-item" :style="wheelItemStyle(i)" x-text="tabName(tab)"></span>
                </template>
            </div>
        </div>
    </div>
    <button type="button"
            class="shell-nav-side is-next"
            @click="goNeighbor(1)"
            :disabled="!neighbor(1)">
        <span class="shell-nav-label" x-text="neighbor(1) ? tabName(neighbor(1)) : ''"></span>
        <span class="material-symbols-rounded" aria-hidden="true">chevron_right</span>
    </button>
</nav>
