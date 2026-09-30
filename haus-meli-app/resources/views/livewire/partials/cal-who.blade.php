@php
    $who = strtoupper(preg_replace('/[^ML]/', '', (string) ($ev->who ?? '')) ?? '');
    $hasM = str_contains($who, 'M');
    $hasL = str_contains($who, 'L');
@endphp
@if(($hasM || $hasL) && ! in_array($ev->type ?? '', ['schicht', 'geburtstag'], true))
    <span class="cal-who-badges {{ !empty($stack) ? 'is-stack' : '' }}">
        @if($hasM)<span class="cal-who is-m">M</span>@endif
        @if($hasL)<span class="cal-who is-l">L</span>@endif
    </span>
@endif
