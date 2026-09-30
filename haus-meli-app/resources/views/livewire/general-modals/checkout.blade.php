<div id="checkout-overlay" class="welcome-overlay" style="display:none;">
		<div class="welcome-card" style="border-color: #ff4d4d;">
			<div class="welcome-header">
				<span style="font-size: 3rem;">⚠️</span>
			</div>

			<h1 style="color: #ff4d4d;" data-i18n="checkout_req_title">Abrechnung erforderlich</h1>

			<p data-i18n="checkout_req_msg">Heute ist Ihr Abreisetag. Bitte gleichen Sie Ihre offenen Posten aus, bevor
				Sie die Wohnung verlassen.</p>

			<div id="checkout-total-display"
				style="font-size: 1.5rem; font-weight: 800; padding: 15px; background: rgba(255,77,77,0.1); border-radius: 10px; color: var(--text);">
				<span data-i18n="checkout_total_label">Summe:</span> 0,00 €
			</div>

			<p class="small" style="color: var(--muted);" data-i18n="checkout_info_small">
				Klicken Sie auf den Button unten, um Ihre Bestellungen einzusehen und den Aufenthalt abzuschließen.
			</p>

			<button onclick="openHistoryFromCheckout()" class="add-btn" style="width: 100%; padding: 15px"
				data-i18n="checkout_btn_pay">
				Zu den Bestellungen & Bezahlen
			</button>

			<button onclick="document.getElementById('checkout-overlay').style.display='none'"
				style="background:none; border:none; color:var(--muted); cursor:pointer; text-decoration:underline;"
				data-i18n="checkout_btn_later">
				Später (nur schließen)
			</button>
		</div>
	</div>