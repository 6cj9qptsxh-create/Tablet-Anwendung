<nav class="shell-nav" aria-label="Seiten"
     :class="{ 'is-wheeling': wheelOn, 'is-dragging': wheelDrag }"
     x-on:pointerdown="wheelDown($event)"
     x-on:pointermove="wheelMove($event)"
     x-on:pointerup="wheelUp($event)"
     x-on:pointercancel="wheelUp($event)"
     x-on:click.capture="wheelClick($event)">
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
</nav>
