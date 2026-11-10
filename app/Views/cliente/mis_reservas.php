<?php
$e = static fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');

$sinImagen = 'data:image/svg+xml;utf8,' . rawurlencode(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 200"><rect width="300" height="200" fill="#f5f1e4"/>'
    . '<g transform="rotate(-35 150 100)"><rect x="105" y="82" width="90" height="36" rx="18" fill="#8ed462"/>'
    . '<path d="M150 82h27a18 18 0 0 1 0 36h-27z" fill="#fff"/></g></svg>'
);

/* Color de la etiqueta de estado */
$claseEstado = static function ($estado) {
    switch (strtolower((string) $estado)) {
        case 'pendiente': return 'bg-[#f5e211]/40 text-[#2c2e2a]';
        case 'cancelada': return 'bg-[#ff705d]/15 text-[#ff705d]';
        default:          return 'bg-[#8ed462]/30 text-[#2c2e2a]';
    }
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Reservas - StockMed</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f5f1e4; color: #2c2e2a; }
    </style>
</head>
<body class="antialiased min-h-screen flex flex-col">

    <?= $this->include('partials/fondo') ?>

    <div class="contenido flex-1 flex flex-col">

        <!-- Navegación -->
        <header class="sticky top-0 z-50 w-full px-4 pt-4">
            <div class="max-w-[1200px] mx-auto flex items-center justify-between gap-3">
                <nav class="bg-white rounded-[50px] pl-3 pr-6 py-2.5 shadow-sm border border-[#d5d5d4] flex items-center gap-6">
                    <a href="/" class="flex items-center gap-2.5">
                        <span class="w-9 h-9 rounded-[10px] bg-[#8ed462] flex items-center justify-center">
                            <svg width="20" height="20" viewBox="0 0 24 24"><path d="M9 3h6v6h6v6h-6v6H9v-6H3V9h6z" fill="#fff"/></svg>
                        </span>
                        <span class="font-semibold text-[17px] tracking-tight">StockMed</span>
                    </a>
                    <div class="hidden md:flex items-center gap-5 text-[15px]">
                        <a href="/" class="hover:text-[#80827f] transition-colors">Catálogo</a>
                        <a href="/mis-reservas" class="font-medium underline decoration-[#8ed462] decoration-2 underline-offset-8">Mis reservas</a>
                        <a href="/perfil" class="hover:text-[#80827f] transition-colors">Mi perfil</a>
                    </div>
                </nav>

                <div class="bg-white rounded-[50px] pl-6 pr-2 py-2 shadow-sm border border-[#d5d5d4] flex items-center gap-3">
                    <a href="/perfil" class="hidden sm:inline text-sm font-medium text-[#80827f] hover:text-[#2c2e2a]">Hola, <?= $e(session()->get('nombre') ?? 'Cliente') ?></a>
                    <a href="/perfil" class="px-4 py-2 rounded-[50px] text-sm font-medium bg-[#f5f1e4] border border-[#d5d5d4] hover:bg-[#e0dbce] transition-colors">Mi perfil</a>
                    <a href="/logout" class="px-4 py-2 rounded-[50px] text-sm font-medium bg-[#2c2e2a] text-white hover:bg-black transition-colors">Salir</a>
                </div>
            </div>
        </header>

        <main class="max-w-[1200px] mx-auto px-4 w-full">

            <!-- Encabezado -->
            <section class="mt-6 bg-[#8ed462] rounded-[50px] px-6 md:px-12 py-12 text-center">
                <h1 class="text-[clamp(36px,6vw,64px)] leading-[1.05] tracking-[-0.05em] font-medium">Mis reservas</h1>
                <p class="mt-3 text-[17px] text-[#2c2e2a]/80 max-w-xl mx-auto">
                    Revise el estado de sus pedidos. Cada reserva se mantiene 24 horas para que pase a recogerla.
                </p>
            </section>

            <section class="mt-10 mb-16">

                <?php if (session()->has('mensaje')): ?>
                    <div class="mb-6 px-6 py-4 rounded-[30px] bg-white border border-[#8ed462] text-[15px] flex items-start gap-3">
                        <span class="mt-1.5 w-2.5 h-2.5 rounded-full bg-[#8ed462] flex-none"></span>
                        <span><?= $e(session()->get('mensaje')) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (session()->has('error')): ?>
                    <div class="mb-6 px-6 py-4 rounded-[30px] bg-white border border-[#ff705d] text-[15px] flex items-start gap-3">
                        <span class="mt-1.5 w-2.5 h-2.5 rounded-full bg-[#ff705d] flex-none"></span>
                        <span><?= $e(session()->get('error')) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($reservas)): ?>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                        <?php foreach ($reservas as $res): ?>
                        <article class="bg-white rounded-[30px] p-5 shadow-sm border border-[#e0dbce] flex flex-col">

                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h2 class="text-[20px] font-medium leading-tight">Reserva #<?= $e($res['idReserva']) ?></h2>
                                    <p class="text-xs text-[#80827f] mt-1">Realizada: <?= $e($res['fechaReserva']) ?></p>
                                    <p class="text-xs text-[#80827f]">Expira: <strong class="text-[#2c2e2a]"><?= $e($res['fechaExpira']) ?></strong></p>
                                </div>
                                <span class="px-3 py-1 rounded-[10px] text-xs font-medium <?= $claseEstado($res['estado']) ?>"><?= $e($res['estado']) ?></span>
                            </div>

                            <ul class="mt-4 divide-y divide-[#e0dbce] border-t border-b border-[#e0dbce]">
                                <?php foreach ($res['items'] as $it):
                                    $gen = trim($it['nombreGenerico'] ?? '');
                                    $com = trim($it['nombreComercial'] ?? '');
                                    $principal = $gen !== '' ? $gen : $com;
                                    $otros = ($gen !== '' && strcasecmp($gen, $com) !== 0) ? $com : '';
                                ?>
                                <li class="flex items-center gap-3 py-3">
                                    <img src="<?= !empty($it['imagen']) ? $e($it['imagen']) : $sinImagen ?>"
                                         onerror="this.onerror=null;this.src='<?= $sinImagen ?>'"
                                         alt="" class="w-14 h-14 rounded-[16px] object-cover bg-[#f5f1e4] flex-none">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-[15px] font-medium leading-tight truncate"><?= $e($principal) ?></p>
                                        <?php if ($otros !== ''): ?>
                                            <p class="text-xs text-[#80827f] italic truncate">Otros nombres: <?= $e($otros) ?></p>
                                        <?php endif; ?>
                                        <p class="text-xs text-[#80827f]"><?= $e($it['cantidad']) ?> x Bs. <?= number_format($it['precio'], 2) ?></p>
                                    </div>
                                    <span class="text-[15px] font-semibold flex-none">Bs. <?= number_format($it['cantidad'] * $it['precio'], 2) ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>

                            <div class="mt-4 flex items-center justify-between">
                                <span class="text-[15px] text-[#80827f]">Total</span>
                                <span class="text-[20px] font-bold">Bs. <?= number_format($res['total'], 2) ?></span>
                            </div>

                            <?php if ($res['requiereReceta'] == 1): ?>
                                <p class="mt-3 px-4 py-2 rounded-[16px] text-xs font-medium bg-[#ff705d]/10 text-[#ff705d] border border-[#ff705d]/20">
                                    Presentar la receta médica en mostrador al recoger.
                                </p>
                            <?php endif; ?>

                            <?php if (strtolower($res['estado']) === 'pendiente'): ?>
                                <a href="/reserva/cancelar/<?= $e($res['idReserva']) ?>"
                                   onclick="return confirm('¿Está seguro de cancelar esta reserva? Los medicamentos volverán al inventario.');"
                                   class="mt-4 block text-center w-full py-2.5 px-4 rounded-[50px] text-[14px] font-medium bg-[#f5f1e4] border border-[#2c2e2a] hover:bg-[#ff705d] hover:border-[#ff705d] hover:text-white transition-all">
                                    Cancelar reserva
                                </a>
                            <?php endif; ?>
                        </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-16 bg-white rounded-[50px] border border-[#e0dbce]">
                        <h4 class="text-[20px] font-medium">No tiene ninguna reserva registrada</h4>
                        <p class="text-[15px] text-[#80827f] mt-1 mb-6">Agregue medicamentos al carrito desde el catálogo.</p>
                        <a href="/" class="inline-block px-6 py-3 rounded-[50px] text-[15px] font-medium bg-[#2c2e2a] text-white hover:bg-[#8ed462] hover:text-[#2c2e2a] transition-all">Ir al catálogo</a>
                    </div>
                <?php endif; ?>
            </section>
        </main>

        <footer class="w-full bg-[#f5e211] py-8 text-center mt-auto border-t border-[#e0dbce]">
            <?= $this->include('partials/ubicacion') ?>
            <p class="text-[15px] font-medium">StockMed Online &mdash; Sistema Automatizado de Control de Inventario y Farmacia.</p>
        </footer>
    </div>
</body>
</html>