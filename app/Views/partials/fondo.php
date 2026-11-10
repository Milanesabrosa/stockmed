<?php /* Fondo animado de StockMed: curvas que bajan con el scroll y detalles de farmacia. Uso: <?= $this->include('partials/fondo') ?> */ ?>
<style>
        /* Fondo animado (curvas + detalles de farmacia) */
        #bg { position: fixed; inset: 0; z-index: 0; overflow: hidden; pointer-events: none; }
        #bg .curva { position: absolute; will-change: transform; }
        #bg .deco { position: absolute; left: 0; top: 0; will-change: transform; opacity: .55; }
        .contenido { position: relative; z-index: 1; }

</style>
    <!-- FONDO: curvas que bajan con el scroll y detalles de farmacia -->
    <div id="bg" aria-hidden="true">
        <svg class="curva" data-curva="1" style="left:-15%; top:-10%; width:75%;" viewBox="0 0 600 600" fill="none">
            <path d="M-20 140C140 40 330 70 430 210S560 470 640 520V640H-20Z" fill="#8ed462" opacity=".30"/>
        </svg>
        <svg class="curva" data-curva="2" style="right:-20%; top:25%; width:80%;" viewBox="0 0 600 600" fill="none">
            <path d="M620 60C470 80 330 190 300 330S170 560 -20 560V640H620Z" fill="#8ed462" opacity=".26"/>
        </svg>
        <svg class="curva" data-curva="3" style="left:-10%; top:70%; width:70%;" viewBox="0 0 600 600" fill="none">
            <path d="M-20 300C120 220 260 260 360 360S520 540 640 500V640H-20Z" fill="#8ed462" opacity=".22"/>
        </svg>

        <!-- Detalles: cruz, cápsula, pastilla, gota, frasco -->
        <svg class="deco" data-deco data-base=".12" data-x="8" data-vel=".18" width="34" height="34" viewBox="0 0 24 24"><path d="M9 3h6v6h6v6h-6v6H9v-6H3V9h6z" fill="#ff705d"/></svg>
        <svg class="deco" data-deco data-base=".55" data-x="90" data-vel=".12" width="30" height="30" viewBox="0 0 24 24"><path d="M9 3h6v6h6v6h-6v6H9v-6H3V9h6z" fill="#8ed462"/></svg>
        <svg class="deco" data-deco data-base=".30" data-x="93" data-vel=".22" width="60" height="30" viewBox="0 0 60 30"><rect width="60" height="30" rx="15" fill="#2ba0ff"/><path d="M30 0h15a15 15 0 0 1 0 30H30z" fill="#fff"/></svg>
        <svg class="deco" data-deco data-base=".70" data-x="5" data-vel=".16" width="56" height="28" viewBox="0 0 60 30"><rect width="60" height="30" rx="15" fill="#f5e211"/><path d="M30 0h15a15 15 0 0 1 0 30H30z" fill="#fff"/></svg>
        <svg class="deco" data-deco data-base=".82" data-x="85" data-vel=".10" width="26" height="26" viewBox="0 0 24 24"><circle cx="12" cy="12" r="11" fill="#fff" stroke="#2c2e2a" stroke-width="1.5"/><path d="M4 12h16" stroke="#2c2e2a" stroke-width="1.5"/></svg>
        <svg class="deco" data-deco data-base=".45" data-x="3" data-vel=".14" width="22" height="30" viewBox="0 0 22 30"><path d="M11 1C11 1 1 13 1 20a10 10 0 0 0 20 0C21 13 11 1 11 1z" fill="#2ba0ff"/></svg>
        <svg class="deco" data-deco data-base=".92" data-x="48" data-vel=".20" width="34" height="42" viewBox="0 0 34 42"><rect x="10" y="0" width="14" height="8" rx="2" fill="#2c2e2a"/><rect x="3" y="8" width="28" height="32" rx="8" fill="#fff" stroke="#2c2e2a" stroke-width="1.5"/><path d="M17 17v14M10 24h14" stroke="#8ed462" stroke-width="3" stroke-linecap="round"/></svg>
        <svg class="deco" data-deco data-base=".05" data-x="62" data-vel=".09" width="14" height="14" viewBox="0 0 14 14"><circle cx="7" cy="7" r="6" fill="#f5e211"/></svg>
        <svg class="deco" data-deco data-base=".65" data-x="30" data-vel=".13" width="16" height="16" viewBox="0 0 14 14"><circle cx="7" cy="7" r="6" fill="#ff705d"/></svg>
    </div>

<script>
(function () {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        /* ---------- Fondo animado con el scroll ---------- */

        const curvas = document.querySelectorAll('#bg .curva');
        const decos = document.querySelectorAll('#bg .deco');
        let y = window.scrollY, pendiente = false;

        function pintar() {
            pendiente = false;
            const vh = window.innerHeight, vw = window.innerWidth;

            // Curvas: bajan lentamente y ondulan de lado a lado
            curvas.forEach((c, i) => {
                const k = 0.10 + i * 0.05;
                const dir = i % 2 ? -1 : 1;
                c.style.transform = 'translate3d(' + (Math.sin(y / 380 + i) * 40 * dir) + 'px,' + (y * k) + 'px,0)';
            });

            // Detalles de farmacia: descienden en bucle y giran suavemente
            decos.forEach((d, i) => {
                const base = parseFloat(d.dataset.base) * vh;
                const vel = parseFloat(d.dataset.vel);
                const x = parseFloat(d.dataset.x) / 100 * vw;
                const rango = vh + 120;
                const top = ((base + y * vel) % rango) - 60;
                d.style.transform = 'translate3d(' + x + 'px,' + top + 'px,0) rotate(' + (y * 0.05 * (i % 2 ? 1 : -1)) + 'deg)';
            });
        }
        function pedir() { if (!pendiente) { pendiente = true; requestAnimationFrame(pintar); } }

        window.addEventListener('scroll', () => { y = window.scrollY; pedir(); }, { passive: true });
        window.addEventListener('resize', pedir);
        pintar();
})();
</script>