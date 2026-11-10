<?php
/*
 * Vista: Catálogo de Medicamentos - StockMed
 * Variables esperadas (igual que antes): $medicamentos
 * Campos usados: idMedicamento, nombreComercial, nombreGenerico, nombreCategoria,
 *                descripcion, viaAdministrativa, dosis, precioUnitario, stockActual,
 *                recetaMedica (opcional), imagen (opcional)
 * Opcional: $categorias (con nombreCategoria y descripcion) para mostrar también
 *           categorías que aún no tienen medicamentos o su descripción.
 */

$e = static fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');

// Agrupar medicamentos por categoría (cualquier categoría nueva aparece sola)
$grupos = [];
if (!empty($medicamentos)) {
    foreach ($medicamentos as $m) {
        $cat = $m['nombreCategoria'] ?? 'Sin categoría';
        $grupos[$cat][] = $m;
    }
}

// Descripciones de categoría si el controlador las envía
$descCategorias = [];
if (!empty($categorias) && is_array($categorias)) {
    foreach ($categorias as $c) {
        $descCategorias[$c['nombreCategoria']] = $c['descripcion'] ?? '';
        if (!isset($grupos[$c['nombreCategoria']])) {
            $grupos[$c['nombreCategoria']] = []; // categoría sin medicamentos todavía
        }
    }
}
ksort($grupos, SORT_NATURAL | SORT_FLAG_CASE);

// Items del carrusel del hero (solo muestra). Se repiten para que el bucle sea continuo.
$heroItems = !empty($medicamentos) ? array_values($medicamentos) : [];
$heroLista = [];
if ($heroItems) {
    while (count($heroLista) < 8) {
        $heroLista = array_merge($heroLista, $heroItems);
    }
}

$sinImagen = 'data:image/svg+xml;utf8,' . rawurlencode(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 200"><rect width="300" height="200" fill="#f5f1e4"/>'
    . '<g transform="rotate(-35 150 100)"><rect x="105" y="82" width="90" height="36" rx="18" fill="#8ed462"/>'
    . '<path d="M150 82h27a18 18 0 0 1 0 36h-27z" fill="#fff"/></g></svg>'
);
$logueado = session()->get('logged_in');
/* Administrador y farmacéutico: acceso de vuelta a su panel */
$rolSesion = (int) session()->get('idRol');
$urlPanel  = $logueado ? ($rolSesion === 3 ? '/admin/medicamentos' : ($rolSesion === 2 ? '/farmacia/pedidos' : null)) : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StockMed - Catálogo de Medicamentos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --grass: #8ed462; --cream: #f5f1e4; --ink: #2c2e2a; --sand: #e0dbce;
            --stone: #80827f; --mist: #d5d5d4; --sky: #2ba0ff; --coral: #ff705d; --sun: #f5e211;
        }
        html { scroll-behavior: smooth; }
        body { font-family: 'Inter', sans-serif; background: var(--cream); color: var(--ink); }

        /* Hero */
        .hero-titulo { font-size: clamp(40px, 7vw, 81px); line-height: 1.05; letter-spacing: -0.06em; font-weight: 500; }

        /* Carrusel (solo muestra) */
        .carrusel { overflow: hidden; -webkit-mask-image: linear-gradient(90deg, transparent, #000 6%, #000 94%, transparent);
                    mask-image: linear-gradient(90deg, transparent, #000 6%, #000 94%, transparent); }
        .pista { display: flex; gap: 20px; width: max-content; animation: mover 40s linear infinite; }
        .carrusel:hover .pista { animation-play-state: paused; }
        @keyframes mover { to { transform: translateX(-50%); } }
        .mini { width: 230px; flex: none; }

        /* Chips y buscador */
        .chip { transition: all .2s; }
        .chip.activo { background: var(--ink); color: #fff; border-color: var(--ink); }

        .tarjeta { transition: transform .3s, opacity .3s; }
        .tarjeta:hover { transform: translateY(-4px); }


        /* Animación +1 del carrito */
        .plus-flotante { position: absolute; top: -6px; right: -6px; min-width: 22px; height: 22px; padding: 0 6px; border-radius: 999px;
                         background: #ff705d; color: #fff; font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center;
                         opacity: 0; pointer-events: none; }
        .plus-flotante.animar { animation: subirPlus .9s ease-out forwards; }
        @keyframes subirPlus { 0% { opacity: 0; transform: translateY(6px) scale(.6); } 20% { opacity: 1; transform: translateY(-4px) scale(1.1); }
                               100% { opacity: 0; transform: translateY(-34px) scale(1); } }
        #carritoBtn.rebote { animation: rebote .45s ease; }
        @keyframes rebote { 0%,100% { transform: scale(1); } 35% { transform: scale(1.18) rotate(-8deg); } 70% { transform: scale(.95); } }
        #carritoBadge.pop { animation: rebote .45s ease; }

        /* Bandeja del pedido */
        #bandeja { position: fixed; left: 50%; bottom: 16px; transform: translate(-50%, 140%); z-index: 60; width: min(560px, calc(100% - 24px));
                   transition: transform .35s ease; }
        #bandeja.visible { transform: translate(-50%, 0); }
        #bandejaLista { max-height: 0; overflow: hidden; transition: max-height .3s ease; }
        #bandeja.abierta #bandejaLista { max-height: 46vh; overflow-y: auto; }

        /* Vista ampliada del medicamento */
        dialog#modalMed { border: none; border-radius: 40px; padding: 0; width: calc(100% - 24px); max-width: 760px; max-height: 90vh; overflow-y: auto; background: #fff; color: var(--ink); }
        dialog#modalMed::backdrop { background: rgba(44, 46, 42, .45); }
        dialog#modalMed[open] { animation: abrirMed .25s ease; }
        @keyframes abrirMed { from { opacity: 0; transform: translateY(12px) scale(.98); } to { opacity: 1; transform: none; } }

        @media (prefers-reduced-motion: reduce) {
            .pista { animation: none; }
            html { scroll-behavior: auto; }
        }
    </style>
</head>
<body class="antialiased min-h-screen flex flex-col">

    <?= $this->include('partials/fondo') ?>

    <div class="contenido flex-1 flex flex-col">

        <!-- NAVEGACIÓN: dos píldoras flotantes (marca/enlaces) y (sesión) -->
        <header class="sticky top-0 z-50 w-full px-4 pt-4">
            <div class="max-w-[1200px] mx-auto flex items-center justify-between gap-3">
                <nav class="bg-white rounded-[50px] pl-3 pr-6 py-2.5 shadow-sm border border-[#d5d5d4] flex items-center gap-6">
                    <a href="/" class="flex items-center gap-2.5">
                        <span class="w-9 h-9 rounded-[10px] bg-[#8ed462] flex items-center justify-center">
                            <svg width="20" height="20" viewBox="0 0 24 24"><path d="M9 3h6v6h6v6h-6v6H9v-6H3V9h6z" fill="#fff"/></svg>
                        </span>
                        <span class="font-semibold text-[17px] tracking-tight">StockMed</span>
                    </a>
                    <div class="hidden md:flex items-center gap-5 text-[15px] text-[#2c2e2a]">
                        <a href="#catalogo" class="hover:text-[#80827f] transition-colors">Catálogo</a>
                        <a href="#buscador" class="hover:text-[#80827f] transition-colors">Buscar</a>
                    </div>
                    <?php if ($logueado): ?>
                        <!-- Carrito: lleva a Mis reservas -->
                        <a href="/mis-reservas" id="carritoBtn" aria-label="Ir a mis reservas" title="Mis reservas"
                           class="relative w-10 h-10 rounded-full bg-[#f5f1e4] border border-[#d5d5d4] flex items-center justify-center hover:bg-[#e0dbce] transition-colors">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2c2e2a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 4h2.5l2.2 10.2a1.5 1.5 0 0 0 1.5 1.2h8.2a1.5 1.5 0 0 0 1.5-1.1L20.5 8H6.2"/>
                                <circle cx="9.5" cy="19.5" r="1.3"/><circle cx="17" cy="19.5" r="1.3"/>
                            </svg>
                            <span id="carritoBadge" class="absolute -top-1 -right-1 min-w-[20px] h-5 px-1 rounded-full bg-[#ff705d] text-white text-[11px] font-semibold flex items-center justify-center hidden">0</span>
                            <span id="carritoPlus" class="plus-flotante" aria-hidden="true">+1</span>
                        </a>
                    <?php endif; ?>
                </nav>

                <div class="bg-white rounded-[50px] pl-6 pr-2 py-2 shadow-sm border border-[#d5d5d4] flex items-center gap-3">
                    <?php if ($logueado): ?>
                        <a href="/perfil" class="hidden sm:inline text-sm font-medium text-[#80827f] hover:text-[#2c2e2a]">Hola, <?= $e(session()->get('nombre') ?? 'Cliente') ?></a>
                        <a href="/perfil" class="px-4 py-2 rounded-[50px] text-sm font-medium bg-[#f5f1e4] border border-[#d5d5d4] hover:bg-[#e0dbce] transition-colors">Mi perfil</a>
                        <?php if ($urlPanel): ?>
                            <a href="<?= $urlPanel ?>" class="px-4 py-2 rounded-[50px] text-sm font-medium bg-[#8ed462] text-[#2c2e2a] hover:bg-[#7bc851] transition-colors">Ir al panel</a>
                        <?php endif; ?>
                        <a href="/logout" class="px-4 py-2 rounded-[50px] text-sm font-medium bg-[#2c2e2a] text-white hover:bg-black transition-colors">Salir</a>
                    <?php else: ?>
                        <a href="/login" class="text-[15px] font-medium">Iniciar sesión</a>
                        <a href="/login" aria-label="Ir al login" class="w-9 h-9 rounded-full bg-[#8ed462] flex items-center justify-center hover:bg-[#7bc851] transition-colors">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#2c2e2a" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <main class="max-w-[1200px] mx-auto px-4 w-full">

            <!-- HERO: cuadro verde con titular, buscador y carrusel de muestra -->
            <section class="mt-6 bg-[#8ed462] rounded-[50px] px-6 md:px-12 pt-14 pb-10 text-center overflow-hidden">
                <h1 class="hero-titulo max-w-3xl mx-auto">Tu farmacia,<br>siempre con stock</h1>
                <p class="mt-5 text-[18px] text-[#2c2e2a]/80 max-w-xl mx-auto leading-relaxed">
                    Consulte disponibilidad, indicaciones y reserve sus medicamentos en línea de forma rápida y segura.
                </p>

                <!-- Buscador -->
                <div id="buscador" class="mt-8 max-w-xl mx-auto scroll-mt-28">
                    <label for="buscar" class="sr-only">Buscar medicamento</label>
                    <div class="bg-white rounded-[50px] flex items-center pl-6 pr-2 py-2 border border-[#2c2e2a]/10">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#80827f" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg>
                        <input id="buscar" type="search" autocomplete="off" placeholder="Busque por nombre, genérico o categoría"
                               class="flex-1 px-3 py-2.5 text-[16px] bg-transparent outline-none placeholder:text-[#80827f]">
                        <button id="limpiar" type="button" class="hidden px-4 py-2.5 rounded-[50px] text-sm font-medium bg-[#f5f1e4] hover:bg-[#e0dbce] transition-colors">Limpiar</button>
                    </div>
                </div>

                <!-- Carrusel solo de muestra (no redirige) -->
                <?php if ($heroLista): ?>
                <div class="carrusel mt-12" aria-hidden="true">
                    <div class="pista">
                        <?php for ($pasada = 0; $pasada < 2; $pasada++): ?>
                            <?php foreach ($heroLista as $h): ?>
                                <div class="mini bg-white rounded-[30px] p-3 text-left shadow-sm select-none">
                                    <div class="h-28 rounded-[20px] overflow-hidden bg-[#f5f1e4]">
                                        <img src="<?= !empty($h['imagen']) ? $e($h['imagen']) : $sinImagen ?>" alt="" loading="lazy"
                                             onerror="this.onerror=null;this.src='<?= $sinImagen ?>'" class="w-full h-full object-cover">
                                    </div>
                                    <p class="mt-3 px-1 text-[15px] font-medium leading-tight truncate"><?= $e(trim($h['nombreGenerico'] ?? '') !== '' ? $h['nombreGenerico'] : $h['nombreComercial']) ?></p>
                                    <p class="px-1 text-xs text-[#80827f] truncate"><?= $e($h['nombreCategoria'] ?? '') ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php endif; ?>
            </section>

            <!-- CATÁLOGO -->
            <section id="catalogo" class="mt-20 mb-16 scroll-mt-24">
                <div class="text-center max-w-2xl mx-auto mb-8">
                    <h2 class="text-[53px] font-medium leading-[1.15] tracking-[-2.12px]">Catálogo de Medicamentos</h2>
                </div>

                <?php if (!empty($grupos)): ?>
                    <!-- Filtros por categoría (se generan solos) -->
                    <div class="flex flex-wrap justify-center gap-2 mb-12" id="chips">
                        <button type="button" data-cat="" class="chip activo px-4 py-2 rounded-[50px] text-sm font-medium bg-white border border-[#d5d5d4]">Todas</button>
                        <?php foreach (array_keys($grupos) as $nombreCat): ?>
                            <button type="button" data-cat="<?= $e($nombreCat) ?>" class="chip px-4 py-2 rounded-[50px] text-sm font-medium bg-white border border-[#d5d5d4] hover:bg-[#e0dbce]">
                                <?= $e($nombreCat) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <?php foreach ($grupos as $nombreCat => $lista): ?>
                    <div class="seccion-cat mb-12" data-cat="<?= $e($nombreCat) ?>">
                        <div class="flex items-end justify-between flex-wrap gap-2 mb-6 px-2">
                            <div>
                                <h3 class="text-[26px] font-medium leading-tight"><?= $e($nombreCat) ?></h3>
                                <?php if (!empty($descCategorias[$nombreCat])): ?>
                                    <p class="text-[15px] text-[#80827f]"><?= $e($descCategorias[$nombreCat]) ?></p>
                                <?php endif; ?>
                            </div>
                            <span class="contador text-sm text-[#80827f]"><?= count($lista) ?> medicamento(s)</span>
                        </div>

                        <?php if (empty($lista)): ?>
                            <div class="vacia text-center py-10 bg-white/80 rounded-[50px] border border-[#e0dbce] text-[#80827f]">
                                Aún no hay medicamentos registrados en esta categoría.
                            </div>
                        <?php else: ?>
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-5">
                            <?php foreach ($lista as $med):
                                $gen = trim($med['nombreGenerico'] ?? '');
                                $com = trim($med['nombreComercial'] ?? '');
                                $principal = $gen !== '' ? $gen : $com;
                                $otros = ($gen !== '' && strcasecmp($gen, $com) !== 0) ? $com : '';
                                $sinStock = $med['stockActual'] <= 0;
                                $receta = isset($med['recetaMedica']) && $med['recetaMedica'] == 1;
                            ?>
                            <div class="tarjeta bg-white rounded-[30px] p-4 shadow-sm border border-[#e0dbce] flex flex-col"
                                 data-buscar="<?= $e($com . ' ' . $gen . ' ' . ($med['nombreCategoria'] ?? '') . ' ' . ($med['descripcion'] ?? '')) ?>">

                                <div class="ver-detalle cursor-pointer relative h-28 rounded-[20px] overflow-hidden bg-[#f5f1e4] mb-3" title="Ver detalle">
                                    <img src="<?= !empty($med['imagen']) ? $e($med['imagen']) : $sinImagen ?>"
                                         onerror="this.onerror=null;this.src='<?= $sinImagen ?>'"
                                         loading="lazy" class="w-full h-full object-cover" alt="<?= $e($principal) ?>">
                                    <?php if ($receta): ?>
                                        <span class="absolute top-2 left-2 px-2.5 py-0.5 rounded-[10px] text-[11px] font-medium bg-white text-[#ff705d] border border-[#ff705d]/30">Receta</span>
                                    <?php endif; ?>
                                </div>

                                <h5 class="text-[17px] font-medium leading-tight truncate" title="<?= $e($principal) ?>"><?= $e($principal) ?></h5>
                                <?php if ($otros !== ''): ?>
                                    <p class="text-xs text-[#80827f] italic truncate">Otros nombres: <?= $e($otros) ?></p>
                                <?php endif; ?>
                                <p class="mt-1.5 text-[13px] text-[#2c2e2a]/75 leading-snug line-clamp-2" title="<?= $e($med['descripcion']) ?>"><?= $e($med['descripcion']) ?></p>
                                <p class="mt-2 text-xs text-[#80827f] truncate" title="Vía: <?= $e($med['viaAdministrativa']) ?> - Dosis: <?= $e($med['dosis']) ?>">
                                    <?= $e($med['viaAdministrativa']) ?> &middot; <?= $e($med['dosis']) ?>
                                </p>
                                <?php if (trim((string) ($med['efectosSecundarios'] ?? '')) !== ''): ?>
                                    <p class="mt-1 text-xs text-[#80827f] truncate" title="Efectos secundarios: <?= $e($med['efectosSecundarios']) ?>">
                                        <span class="font-medium text-[#2c2e2a]/70">Efectos:</span> <?= $e($med['efectosSecundarios']) ?>
                                    </p>
                                <?php endif; ?>
                                <button type="button" class="ver-detalle mt-2 self-start text-xs font-medium underline decoration-[#8ed462] decoration-2 underline-offset-4 hover:text-[#80827f] transition-colors">Ver detalle</button>

                                <!-- Ficha completa (se muestra en la vista ampliada) -->
                                <template class="ficha">
                                    <div class="grid md:grid-cols-[250px_1fr] gap-6">
                                        <div class="h-56 rounded-[30px] overflow-hidden bg-[#f5f1e4]">
                                            <img src="<?= !empty($med['imagen']) ? $e($med['imagen']) : $sinImagen ?>"
                                                 onerror="this.onerror=null;this.src='<?= $sinImagen ?>'" class="w-full h-full object-cover" alt="<?= $e($principal) ?>">
                                        </div>
                                        <div>
                                            <div class="flex flex-wrap gap-2 mb-3">
                                                <span class="px-3 py-1 rounded-[10px] text-xs font-medium bg-[#e0dbce]"><?= $e($med['nombreCategoria'] ?? '') ?></span>
                                                <?php if ($receta): ?>
                                                    <span class="px-3 py-1 rounded-[10px] text-xs font-medium bg-[#ff705d]/10 text-[#ff705d] border border-[#ff705d]/20">Requiere receta</span>
                                                <?php endif; ?>
                                            </div>
                                            <h3 class="text-[30px] leading-[1.1] tracking-[-0.04em] font-medium"><?= $e($principal) ?></h3>
                                            <?php if ($otros !== ''): ?>
                                                <p class="text-[15px] text-[#80827f] italic mt-1">Otros nombres: <?= $e($otros) ?></p>
                                            <?php endif; ?>
                                            <div class="mt-4 flex items-center gap-3">
                                                <span class="text-[24px] font-bold">Bs. <?= number_format($med['precioUnitario'], 2) ?></span>
                                                <span class="px-3 py-1 rounded-[10px] text-xs font-medium <?= $sinStock ? 'bg-[#ff705d]/20 text-[#ff705d]' : 'bg-[#8ed462]/25' ?>">
                                                    <?= $sinStock ? 'Sin stock' : 'Stock: ' . $e($med['stockActual']) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <dl class="mt-6 grid sm:grid-cols-2 gap-x-8 gap-y-5 text-[14px] leading-relaxed">
                                        <?php
                                        $campos = [
                                            ['Descripción', $med['descripcion'] ?? '', true],
                                            ['Indicaciones', $med['indicaciones'] ?? '', true],
                                            ['Efectos secundarios', $med['efectosSecundarios'] ?? '', true],
                                            ['Vía de administración', $med['viaAdministrativa'] ?? '', false],
                                            ['Dosis recomendada', $med['dosis'] ?? '', false],
                                        ];
                                        foreach ($campos as [$titulo, $valor, $ancho]):
                                            if (trim((string) $valor) === '') continue; ?>
                                            <div class="<?= $ancho ? 'sm:col-span-2' : '' ?>">
                                                <dt class="text-xs font-semibold uppercase tracking-wider text-[#80827f] mb-1"><?= $e($titulo) ?></dt>
                                                <dd class="text-[#2c2e2a]/85"><?= $e($valor) ?></dd>
                                            </div>
                                        <?php endforeach; ?>
                                    </dl>
                                </template>

                                <div class="mt-auto pt-3">
                                    <div class="flex items-center justify-between pt-3 border-t border-[#e0dbce]">
                                        <span class="text-[17px] font-bold">Bs. <?= number_format($med['precioUnitario'], 2) ?></span>
                                        <span class="px-2.5 py-0.5 rounded-[10px] text-[11px] font-medium <?= $sinStock ? 'bg-[#ff705d]/20 text-[#ff705d]' : 'bg-[#8ed462]/25 text-[#2c2e2a]' ?>">
                                            Stock: <?= $e($med['stockActual']) ?>
                                        </span>
                                    </div>

                                    <?php if ($logueado): ?>
                                        <div class="mt-3 flex flex-wrap items-center gap-2">
                                            <div class="selector-cant flex items-center bg-[#f5f1e4] rounded-[50px] border border-[#d5d5d4] <?= $sinStock ? 'opacity-40 pointer-events-none' : '' ?>">
                                                <button type="button" class="menos w-8 h-8 rounded-full text-lg hover:bg-[#e0dbce] transition-colors" aria-label="Disminuir cantidad">&minus;</button>
                                                <span class="cant w-7 text-center text-sm font-medium select-none">1</span>
                                                <button type="button" class="mas w-8 h-8 rounded-full text-lg hover:bg-[#e0dbce] transition-colors" aria-label="Aumentar cantidad">+</button>
                                            </div>
                                            <button type="button"
                                                class="btn-agregar flex-1 min-w-[110px] py-2 px-3 rounded-[50px] text-[13px] font-medium bg-[#2c2e2a] text-white hover:bg-[#8ed462] hover:text-[#2c2e2a] transition-all <?= $sinStock ? 'opacity-50 cursor-not-allowed' : '' ?>"
                                                data-id="<?= $e($med['idMedicamento']) ?>"
                                                data-nombre="<?= $e($principal) ?>"
                                                data-precio="<?= $e($med['precioUnitario']) ?>"
                                                data-stock="<?= (int) $med['stockActual'] ?>"
                                                <?= $sinStock ? 'disabled' : '' ?>>
                                                <?= $sinStock ? 'Sin stock' : 'Agregar al carrito' ?>
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <a href="/login" class="mt-3 block text-center w-full py-2 px-3 rounded-[50px] text-[13px] font-medium bg-[#f5f1e4] border border-[#2c2e2a] hover:bg-[#2c2e2a] hover:text-white transition-all">
                                            Inicie sesión para reservar
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>

                    <!-- Mensaje de búsqueda sin resultados -->
                    <div id="sinResultados" class="hidden text-center py-16 bg-white rounded-[50px] border border-[#e0dbce]">
                        <h4 class="text-[20px] text-[#2c2e2a] font-medium">No se encontró el medicamento</h4>
                        <p class="text-[15px] text-[#80827f] mt-1">Revise la escritura o pruebe con el nombre genérico o la categoría.</p>
                    </div>
                <?php else: ?>
                    <div class="text-center py-16 bg-white rounded-[50px] border border-[#e0dbce]">
                        <h4 class="text-[20px] text-[#80827f] font-medium">No hay medicamentos disponibles en este momento.</h4>
                    </div>
                <?php endif; ?>
            </section>
        </main>

        <?php if ($logueado): ?>
        <!-- Bandeja del pedido (solo con sesión iniciada) -->
        <div id="bandeja" aria-live="polite">
            <div class="bg-white rounded-[30px] border border-[#2c2e2a]/10 shadow-lg overflow-hidden">
                <div id="bandejaLista" class="px-5"></div>
                <div class="flex items-center justify-between gap-3 px-5 py-3">
                    <button type="button" id="bandejaToggle" class="text-left">
                        <span class="block text-[15px] font-medium"><span id="bandejaCantidad">0</span> producto(s) en el carrito</span>
                        <span class="block text-xs text-[#80827f]">Total: Bs. <span id="bandejaTotal">0.00</span> &middot; <span id="bandejaVer">Ver detalle</span></span>
                    </button>
                    <div class="flex items-center gap-2">
                        <button type="button" id="bandejaVaciar" class="px-4 py-2.5 rounded-[50px] text-sm font-medium bg-[#f5f1e4] border border-[#d5d5d4] hover:bg-[#e0dbce] transition-colors">Vaciar</button>
                        <button type="button" id="bandejaPedido" class="px-5 py-2.5 rounded-[50px] text-sm font-medium bg-[#8ed462] text-[#2c2e2a] hover:bg-[#7bc851] transition-colors">Hacer pedido</button>
                    </div>
                </div>
                <p id="bandejaError" class="hidden px-5 pb-3 text-xs text-[#ff705d]"></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Vista ampliada del medicamento -->
        <dialog id="modalMed" aria-label="Detalle del medicamento">
            <div class="p-6 sm:p-8">
                <div class="flex justify-end mb-3">
                    <button type="button" id="cerrarMed" class="px-4 py-2 rounded-[50px] text-sm font-medium bg-[#f5f1e4] border border-[#d5d5d4] hover:bg-[#e0dbce] transition-colors">Cerrar</button>
                </div>
                <div id="medContenido"></div>

                <div class="mt-6 pt-5 border-t border-[#e0dbce]">
                    <?php if ($logueado): ?>
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="flex items-center bg-[#f5f1e4] rounded-[50px] border border-[#d5d5d4]">
                                <button type="button" id="modalMenos" class="w-10 h-10 rounded-full text-xl hover:bg-[#e0dbce] transition-colors" aria-label="Disminuir cantidad">&minus;</button>
                                <span id="modalCant" class="w-9 text-center font-medium select-none">1</span>
                                <button type="button" id="modalMas" class="w-10 h-10 rounded-full text-xl hover:bg-[#e0dbce] transition-colors" aria-label="Aumentar cantidad">+</button>
                            </div>
                            <button type="button" id="modalAgregar" class="flex-1 min-w-[160px] py-3 px-5 rounded-[50px] text-[15px] font-medium bg-[#2c2e2a] text-white hover:bg-[#8ed462] hover:text-[#2c2e2a] transition-all disabled:opacity-50 disabled:cursor-not-allowed">Agregar al carrito</button>
                        </div>
                    <?php else: ?>
                        <a href="/login" class="block text-center w-full py-3 px-5 rounded-[50px] text-[15px] font-medium bg-[#f5f1e4] border border-[#2c2e2a] hover:bg-[#2c2e2a] hover:text-white transition-all">Inicie sesión para reservar</a>
                    <?php endif; ?>
                </div>
            </div>
        </dialog>

        <footer class="w-full bg-[#f5e211] py-8 text-center mt-auto border-t border-[#e0dbce]">
            <div class="max-w-[1200px] mx-auto px-4">
                <?= $this->include('partials/ubicacion') ?>
                <p class="text-[15px] font-medium">StockMed Online &mdash; Sistema Automatizado de Control de Inventario y Farmacia.</p>
            </div>
        </footer>
    </div>

    <script>
    (function () {
        /* ---------- Búsqueda + filtro por categoría ---------- */
        const norm = s => (s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
        const input = document.getElementById('buscar');
        const limpiar = document.getElementById('limpiar');
        const chips = document.querySelectorAll('#chips .chip');
        const secciones = document.querySelectorAll('.seccion-cat');
        const sinRes = document.getElementById('sinResultados');
        let catActiva = '';

        function filtrar() {
            const q = norm(input.value);
            let totalVisibles = 0;

            secciones.forEach(sec => {
                const tarjetas = sec.querySelectorAll('.tarjeta');
                const coincideCat = !catActiva || sec.dataset.cat === catActiva;
                let visibles = 0;

                tarjetas.forEach(t => {
                    const ok = coincideCat && (!q || norm(t.dataset.buscar).includes(q));
                    t.style.display = ok ? '' : 'none';
                    if (ok) visibles++;
                });

                const contador = sec.querySelector('.contador');
                if (contador && tarjetas.length) contador.textContent = visibles + ' medicamento(s)';

                // Categoría sin medicamentos: solo se muestra sin búsqueda activa
                const mostrar = tarjetas.length ? visibles > 0 : (coincideCat && !q);
                sec.style.display = mostrar ? '' : 'none';
                totalVisibles += visibles;
            });

            if (sinRes) sinRes.classList.toggle('hidden', totalVisibles > 0 || (!q && !catActiva));
            if (limpiar) limpiar.classList.toggle('hidden', !input.value);
        }

        if (input) {
            input.addEventListener('input', filtrar);
            input.addEventListener('keydown', e => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    document.getElementById('catalogo').scrollIntoView({ behavior: 'smooth' });
                }
            });
        }
        if (limpiar) limpiar.addEventListener('click', () => { input.value = ''; filtrar(); input.focus(); });
        chips.forEach(c => c.addEventListener('click', () => {
            chips.forEach(x => x.classList.remove('activo'));
            c.classList.add('activo');
            catActiva = c.dataset.cat;
            filtrar();
        }));

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

    <?php if ($logueado): ?>
    <script>
    /* ---------- Carrito de pedidos ---------- */
    (function () {
        const CLAVE = 'stockmed_carrito_<?= (int) session()->get('idPersona') ?>';
        const URL_REGISTRAR = '/reserva/registrar';
        const URL_RESERVAS = '/mis-reservas';
        let CSRF = <?= function_exists('csrf_token') ? json_encode(['n' => csrf_token(), 'v' => csrf_hash()]) : 'null' ?>;

        const badge = document.getElementById('carritoBadge');
        const btnCarrito = document.getElementById('carritoBtn');
        const plus = document.getElementById('carritoPlus');
        const bandeja = document.getElementById('bandeja');
        const lista = document.getElementById('bandejaLista');
        const errorEl = document.getElementById('bandejaError');
        const btnPedido = document.getElementById('bandejaPedido');

        let carrito = {};
        try { carrito = JSON.parse(localStorage.getItem(CLAVE)) || {}; } catch (e) { carrito = {}; }

        /* Sincroniza el carrito guardado con el stock actual de la página */
        const vigente = {};
        document.querySelectorAll('.btn-agregar').forEach(b => { vigente[b.dataset.id] = parseInt(b.dataset.stock, 10); });
        Object.keys(carrito).forEach(id => {
            const st = vigente[id];
            if (!st || st <= 0) { delete carrito[id]; return; }
            carrito[id].stock = st; carrito[id].cant = Math.min(carrito[id].cant, st);
        });

        const guardar = () => { try { localStorage.setItem(CLAVE, JSON.stringify(carrito)); } catch (e) {} };
        const unidades = () => Object.values(carrito).reduce((t, i) => t + i.cant, 0);
        const total = () => Object.values(carrito).reduce((t, i) => t + i.cant * i.precio, 0);

        /* Selector de cantidad de cada tarjeta */
        document.querySelectorAll('.selector-cant').forEach(sel => {
            const cant = sel.querySelector('.cant');
            const max = parseInt(sel.closest('.tarjeta').querySelector('.btn-agregar').dataset.stock, 10) || 1;
            sel.querySelector('.menos').addEventListener('click', () => { cant.textContent = Math.max(1, parseInt(cant.textContent, 10) - 1); });
            sel.querySelector('.mas').addEventListener('click', () => { cant.textContent = Math.min(max, parseInt(cant.textContent, 10) + 1); });
        });

        /* Animación +N sobre el carrito */
        function animar(n) {
            plus.textContent = '+' + n;
            plus.classList.remove('animar'); btnCarrito.classList.remove('rebote'); badge.classList.remove('pop');
            void plus.offsetWidth; // reinicia la animación
            plus.classList.add('animar'); btnCarrito.classList.add('rebote'); badge.classList.add('pop');
        }

        /* Agregar al carrito (no redirige) */
        document.querySelectorAll('.btn-agregar').forEach(btn => {
            btn.addEventListener('click', () => {
                if (btn.disabled) return;
                const tarjeta = btn.closest('.tarjeta');
                const pedido = parseInt(tarjeta.querySelector('.cant').textContent, 10) || 1;
                const id = btn.dataset.id, stock = parseInt(btn.dataset.stock, 10);
                const actual = carrito[id] ? carrito[id].cant : 0;
                const agregable = Math.min(pedido, stock - actual);

                if (agregable <= 0) {
                    const t = btn.textContent;
                    btn.textContent = 'Máximo alcanzado';
                    setTimeout(() => { btn.textContent = t; }, 1600);
                    return;
                }
                carrito[id] = { id: id, nombre: btn.dataset.nombre, precio: parseFloat(btn.dataset.precio), stock: stock, cant: actual + agregable };
                guardar(); pintar(); animar(agregable);
                tarjeta.querySelector('.cant').textContent = '1';

                const t = btn.dataset.texto || btn.textContent;
                btn.dataset.texto = t;
                btn.textContent = 'Agregado';
                setTimeout(() => { btn.textContent = t; }, 1100);
            });
        });

        /* Pintar badge y bandeja */
        function pintar() {
            const n = unidades();
            badge.textContent = n > 99 ? '99+' : n;
            badge.classList.toggle('hidden', n === 0);
            bandeja.classList.toggle('visible', n > 0);
            if (n === 0) bandeja.classList.remove('abierta');

            document.getElementById('bandejaCantidad').textContent = n;
            document.getElementById('bandejaTotal').textContent = total().toFixed(2);

            lista.textContent = '';
            Object.values(carrito).forEach(item => {
                const fila = document.createElement('div');
                fila.className = 'flex items-center justify-between gap-3 py-3 border-b border-[#e0dbce]';

                const info = document.createElement('div');
                info.className = 'min-w-0';
                const nom = document.createElement('p');
                nom.className = 'text-[15px] font-medium truncate'; nom.textContent = item.nombre;
                const sub = document.createElement('p');
                sub.className = 'text-xs text-[#80827f]'; sub.textContent = 'Bs. ' + (item.cant * item.precio).toFixed(2);
                info.append(nom, sub);

                const ctrl = document.createElement('div');
                ctrl.className = 'flex items-center gap-1 flex-none';
                const mk = (txt, fn, label) => {
                    const b = document.createElement('button');
                    b.type = 'button'; b.textContent = txt; b.setAttribute('aria-label', label);
                    b.className = 'w-8 h-8 rounded-full bg-[#f5f1e4] border border-[#d5d5d4] hover:bg-[#e0dbce] transition-colors';
                    b.addEventListener('click', fn); return b;
                };
                const c = document.createElement('span');
                c.className = 'w-8 text-center text-sm font-medium'; c.textContent = item.cant;
                ctrl.append(
                    mk('\u2212', () => { item.cant--; if (item.cant <= 0) delete carrito[item.id]; guardar(); pintar(); }, 'Disminuir'),
                    c,
                    mk('+', () => { if (item.cant < item.stock) { item.cant++; guardar(); pintar(); } }, 'Aumentar'),
                    mk('\u00d7', () => { delete carrito[item.id]; guardar(); pintar(); }, 'Quitar')
                );
                fila.append(info, ctrl);
                lista.appendChild(fila);
            });
        }

        document.getElementById('bandejaToggle').addEventListener('click', () => {
            bandeja.classList.toggle('abierta');
            document.getElementById('bandejaVer').textContent = bandeja.classList.contains('abierta') ? 'Ocultar detalle' : 'Ver detalle';
        });
        document.getElementById('bandejaVaciar').addEventListener('click', () => { carrito = {}; guardar(); pintar(); });

        /* Hacer pedido: UNA sola solicitud con todos los medicamentos y sus cantidades */
        btnPedido.addEventListener('click', async () => {
            errorEl.classList.add('hidden');
            btnPedido.disabled = true; btnPedido.textContent = 'Enviando...';
            try {
                const items = Object.values(carrito).map(i => ({ id: i.id, cantidad: i.cant }));
                const datos = new FormData();
                datos.append('items', JSON.stringify(items));
                if (CSRF) datos.append(CSRF.n, CSRF.v);

                const r = await fetch(URL_REGISTRAR, {
                    method: 'POST', body: datos, credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                });
                const j = await r.json().catch(() => null);
                if (j && j.csrf && CSRF) CSRF.v = j.csrf;
                if (!r.ok || !j || !j.ok) throw new Error((j && j.error) || 'No se pudo registrar el pedido');

                carrito = {}; guardar();
                window.location.href = j.redirect || URL_RESERVAS;
            } catch (err) {
                errorEl.textContent = err.message;
                errorEl.classList.remove('hidden');
                btnPedido.disabled = false; btnPedido.textContent = 'Hacer pedido';
            }
        });

        pintar();
    })();
    </script>
    <?php endif; ?>
    <script>
    /* ---------- Vista ampliada del medicamento ---------- */
    (function () {
        const dlg = document.getElementById('modalMed');
        if (!dlg) return;
        const contenido = document.getElementById('medContenido');
        const cantEl = document.getElementById('modalCant'), menos = document.getElementById('modalMenos'),
              mas = document.getElementById('modalMas'), agregar = document.getElementById('modalAgregar');
        let tarjeta = null, max = 1;

        function abrir(card) {
            tarjeta = card;
            contenido.textContent = '';
            contenido.appendChild(card.querySelector('template.ficha').content.cloneNode(true));
            if (agregar) {
                const btn = card.querySelector('.btn-agregar');
                max = btn ? (parseInt(btn.dataset.stock, 10) || 0) : 0;
                cantEl.textContent = '1';
                agregar.disabled = max <= 0;
                agregar.textContent = max > 0 ? 'Agregar al carrito' : 'Sin stock';
            }
            dlg.showModal();
        }

        document.addEventListener('click', e => {
            const t = e.target.closest('.ver-detalle');
            if (t && t.closest('.tarjeta')) { abrir(t.closest('.tarjeta')); return; }
            if (e.target === dlg) dlg.close();   // clic en el fondo oscuro
        });
        document.getElementById('cerrarMed').addEventListener('click', () => dlg.close());

        if (agregar) {
            menos.addEventListener('click', () => { cantEl.textContent = Math.max(1, parseInt(cantEl.textContent, 10) - 1); });
            mas.addEventListener('click', () => { cantEl.textContent = Math.min(Math.max(max, 1), parseInt(cantEl.textContent, 10) + 1); });
            agregar.addEventListener('click', () => {
                // Reutiliza el botón de la tarjeta: misma lógica de carrito y misma animación
                tarjeta.querySelector('.cant').textContent = cantEl.textContent;
                tarjeta.querySelector('.btn-agregar').click();
                dlg.close();
            });
        }
    })();
    </script>
</body>
</html>