<div class="info-block info-connect" wire:poll.30s.visible>
    <div class="info-copy-list">
        @if($wifi['ready'])
            <button type="button" class="info-copy" :class="{ 'is-copied': copied === 'ssid' }" @click="copy('ssid', $refs.ssid.textContent)">
                <span class="info-copy-body">
                    <span class="info-copy-label" data-i18n="info_network">Netzwerk</span>
                    <strong id="wifi-name-display" x-ref="ssid">{{ $wifi['ssid'] }}</strong>
                </span>
                <span class="material-symbols-rounded" x-text="copied === 'ssid' ? 'check' : 'content_copy'"></span>
            </button>
            <button type="button" class="info-copy is-mono" :class="{ 'is-copied': copied === 'pass' }" @click="copy('pass', $refs.pass.textContent)">
                <span class="info-copy-body">
                    <span class="info-copy-label" data-i18n="info_password">Passwort</span>
                    <strong id="wifi-pass-display" x-ref="pass">{{ $wifi['password'] }}</strong>
                </span>
                <span class="material-symbols-rounded" x-text="copied === 'pass' ? 'check' : 'content_copy'"></span>
            </button>
        @else
            <p class="info-app-note">WLAN-Zugang wird eingerichtet.</p>
        @endif
        <p class="info-app-note" data-i18n="info_app_text">Scannen Sie den App-Code, um die Anwendung auf dem Handy zu öffnen.</p>
    </div>
    <div class="info-qrs">
        @if($wifi['ready'])
            <figure class="info-qr" wire:key="wifi-qr-{{ md5($wifi['payload']) }}">
                <img id="wifi-qr-code" src="{{ $wifi['qr'] }}" alt="WLAN QR" width="120" height="120" />
                <figcaption>WLAN</figcaption>
            </figure>
        @endif
        <figure class="info-qr info-app-qr">
            <img id="app-url-qr-code" src="https://api.qrserver.com/v1/create-qr-code/?size=280x280&data={{ urlencode(rtrim((string) config('app.url'), '/').'/') }}" alt="App QR" width="120" height="120" />
            <figcaption data-i18n="info_app_title">App</figcaption>
        </figure>
    </div>
</div>
