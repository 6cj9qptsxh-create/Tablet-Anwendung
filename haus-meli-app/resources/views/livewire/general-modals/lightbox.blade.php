<div id="lightbox-overlay" class="modal-overlay"
    style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.95); z-index:13000; align-items:center; justify-content:center; touch-action: none;"
    onclick="closeLightbox(event)">

    <div id="lightbox-viewport" style="position:relative; width:100%; max-width:1200px; height:100%; overflow:hidden; display:flex; align-items:center;">
        
        <div id="lightbox-track" style="display:flex; align-items:center; height:100%; width:100%; will-change: transform; cursor: grab;">
            </div>

    </div>

    <div id="lb-counter" style="position:absolute; bottom:20px; color:#aaa; font-variant-numeric: tabular-nums; font-size:1.1rem; z-index:13001; pointer-events: none;"></div>
</div>