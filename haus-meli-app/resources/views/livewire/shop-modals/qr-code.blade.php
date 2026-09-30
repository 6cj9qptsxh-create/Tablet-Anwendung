<div x-data="{ qrOpen: false }" 
        @open-qr.window="qrOpen = true"
        :style="qrOpen ? 'display: flex; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:8000; align-items:center; justify-content:center; cursor:pointer;' : 'display: none;'" 
        @click="qrOpen = false">
        
        <div style="background:#ffffff; padding:25px; border-radius:15px; max-width:90%; width:340px; text-align:center; cursor:default; box-shadow: 0 10px 25px rgba(0,0,0,0.5);" @click.stop>
            <h3 style="margin:0 0 15px 0; color:#005eb8; font-family:sans-serif;">{{ __('Bank-App Überweisung') }}</h3>

            @php
                $myIBAN = "AT903740100000111138";
                $myBIC = ""; // OPTIONAL: Falls ELBA/George zickt, trag hier deine BIC/SWIFT ein!
                $myHolder = "Joerg Lukas"; // TIPP: 'oe' statt 'ö' ist bei Bank-Apps sicherer
                
                $cleanIban = str_replace(' ', '', strtoupper($myIBAN));
                $amount = (float) $this->stayTotal;
                
                // EPC Daten streng nach EU-GiroCode-Standard (12 Zeilen)
                $qrLines = [
                    "BCD", 
                    "002",             // Version 002 (viel fehlerfreier als 001)
                    "1",               // Zeichensatz 1 = UTF-8
                    "SCT",             // SEPA Credit Transfer
                    $myBIC,            // Zeile 5: BIC (Manche Banken erzwingen dieses Feld)
                    $myHolder,         // Zeile 6: Name
                    $cleanIban,        // Zeile 7: IBAN
                    "EUR" . number_format($amount, 2, '.', ''), // Zeile 8: z.B. EUR12.50
                    "",                // Zeile 9: Purpose
                    "",                // Zeile 10: Referenz
                    "Fruehstueck",     // Zeile 11: Verwendungszweck (Ohne Sonderzeichen!)
                    ""                 // Zeile 12: WICHTIG! Leere Abschlusszeile
                ];
                
                $qrPayload = implode("\n", $qrLines);
                
                // WICHTIG: rawurlencode! Macht %20 statt +, damit die Bank es lesen kann
                $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . rawurlencode($qrPayload);
            @endphp

            <div style="color:#333 !important;">
                <div style="background:white; padding:10px; border-radius:8px; display:inline-block; border:1px solid #ddd;">
                    <img src="{{ $qrUrl }}" style="width:240px; height:240px; display:block;" alt="EPC-QR-Code">
                </div>
                <div style="margin-top:15px; font-family:monospace; font-weight:bold; font-size:15px; color:#000 !important; word-break:break-all;">
                    {{ implode(' ', str_split($myIBAN, 4)) }}
                </div>
                <div style="margin-top:5px; font-size:16px; color:#000 !important; font-weight:bold;">
                    {{ __('Betrag: EUR') }} {{ number_format($this->stayTotal, 2, '.', '') }}
                </div>
            </div>
            
        </div>
    </div>