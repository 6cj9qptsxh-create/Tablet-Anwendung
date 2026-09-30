<nav class="shell-nav" aria-label="Seiten">
    <button type="button"
            class="shell-nav-side"
            @click="goNeighbor(-1)"
            :disabled="!neighbor(-1)">
        <span class="material-symbols-rounded" aria-hidden="true">chevron_left</span>
        <span class="shell-nav-label" x-text="neighbor(-1) ? tabName(neighbor(-1)) : ''"></span>
    </button>
    <span class="shell-nav-current" x-text="tabName(currentTab)"></span>
    <button type="button"
            class="shell-nav-side is-next"
            @click="goNeighbor(1)"
            :disabled="!neighbor(1)">
        <span class="shell-nav-label" x-text="neighbor(1) ? tabName(neighbor(1)) : ''"></span>
        <span class="material-symbols-rounded" aria-hidden="true">chevron_right</span>
    </button>
</nav>
