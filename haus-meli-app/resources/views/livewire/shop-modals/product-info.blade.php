<template x-teleport="body">
            
        <div id="product-info-modal" class="modal"
                x-data="{ open: false, title: '', img: '', desc: '', allergens: '' }"
                @open-product-modal.window="
                title = $event.detail.title;
                img = $event.detail.img;
                desc = $event.detail.desc;
                allergens = $event.detail.allergens;
                open = true;
                "
                :style="open ? 'display: flex;' : 'display: none;'"
                @click="open = false"
                x-transition.opacity>
                
            <div class="modal-content" @click.stop style="position: relative;">
                
                <h2 id="info-modal-title" x-text="title" style="margin-bottom: 20px;"></h2>
                
                <img id="info-modal-img" :src="img" x-show="img">
                
                <p id="info-modal-text" x-text="desc" x-show="desc" style="line-height: 1.6; white-space: pre-wrap; margin-bottom: 10px; margin-top: 15px; color: var(--text);"></p>

                <div class="allergen-info" x-show="allergens" style="width: max-content; margin: 15px auto 0 auto;">
                    <span class="allergen-label">Allergene</span>
                    <span class="allergen-values" x-text="allergens"></span>
                </div>
                
            </div>
        </div>
        
    </template>