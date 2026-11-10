<?php
/* Layout compartido del panel de Administrador y Farmacéutico (estilo Ease Health: crema, verde bosque, tarjetas planas) */
$rol      = (int) session()->get('idRol');
$esAdmin  = $rol === 3;
$activo   = $activo ?? '';
$menu = $esAdmin ? [
    ['inventario', '/admin/medicamentos',       'Inventario'],
    ['nuevo',      '/admin/medicamentos/nuevo', 'Nuevo medicamento'],
    ['pedidos',    '/admin/pedidos',            'Pedidos'],
    ['ventas',     '/farmacia/ventas',          'Venta en mostrador'],
    ['vencidos',   '/vencidos',                 'Vencimientos'],
    ['historial',  '/farmacia/ventas/historial','Historial de ventas'],
    ['clientes',   '/admin/clientes',           'Usuarios registrados'],
    ['reportes',   '/reportes',                 'Reportes'],
] : [
    ['pedidos', '/farmacia/pedidos', 'Pedidos de clientes'],
    ['ventas',  '/farmacia/ventas',  'Venta en mostrador'],
    ['historial','/farmacia/ventas/historial','Historial de ventas'],
    ['vencidos','/vencidos',         'Vencimientos'],
    ['reportes','/reportes',         'Reportes'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($titulo ?? 'Panel') ?> - StockMed</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --forest: #0f3e17; --forest-dark: #0c2f10; --sage: #b1dbb8; --keylime: #e1f4df;
            --mint: #cfe7d3; --slate: #b6ced5; --cream: #fffefc; --charcoal: #222222; --border: #efeeeb;
        }
        body { font-family: 'Inter', sans-serif; background: var(--cream); color: var(--charcoal); font-size: 14px; }
        .serif { font-family: 'Cormorant Garamond', serif; font-weight: 300; color: var(--forest); letter-spacing: -0.02em; }
        .eyebrow { font-size: 11px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: var(--forest); }
        .card { background: var(--keylime); border-radius: 14px; padding: 24px; }
        .caja { background: var(--cream); border: 1px solid var(--border); border-radius: 14px; }

        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; background: var(--forest); color: var(--cream);
               border-radius: 14px; padding: 10px 18px; font-size: 14px; border: 1px solid var(--forest); cursor: pointer; transition: background .2s; }
        .btn:hover { background: var(--forest-dark); }
        .btn:disabled { opacity: .45; cursor: not-allowed; }
        .btn-sec { background: var(--cream); color: var(--forest); border: 1px solid var(--forest); }
        .btn-sec:hover { background: var(--keylime); }
        .btn-peligro { background: var(--cream); color: #9b2c1e; border: 1px solid #e3bcb5; }
        .btn-peligro:hover { background: #fbeae7; }
        .btn-mini { padding: 6px 12px; font-size: 12px; border-radius: 10px; }

        .input { width: 100%; background: var(--cream); border: 1px solid #c9dccb; border-radius: 14px; padding: 10px 14px; font-size: 14px; outline: none; }
        .input:focus { border-color: var(--forest); }
        .input-mini { padding: 6px 10px; border-radius: 10px; font-size: 12px; }
        label.campo { display: block; font-size: 12px; font-weight: 600; color: var(--forest); margin-bottom: 6px; }

        .badge { display: inline-block; border-radius: 999px; padding: 3px 12px; font-size: 12px; background: var(--cream); color: var(--forest); border: 1px solid var(--border); white-space: nowrap; }
        .badge-ok { background: var(--sage); border-color: var(--sage); }
        .badge-bajo { background: #fbeae7; color: #9b2c1e; border-color: #f0cfca; }
        .badge-aviso { background: #fdf3cf; color: #5c4a00; border-color: #f1e3a6; }

        table.tabla { width: 100%; border-collapse: collapse; }
        table.tabla th { text-align: left; padding: 12px 14px; font-size: 11px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: var(--forest); background: var(--keylime); }
        table.tabla th:first-child { border-top-left-radius: 14px; } table.tabla th:last-child { border-top-right-radius: 14px; }
        table.tabla td { padding: 12px 14px; border-top: 1px solid var(--border); vertical-align: middle; }
        table.tabla tr.detalle td { background: #f6fbf5; }

        .chip { border: 1px solid #c9dccb; background: var(--cream); color: var(--forest); border-radius: 999px; padding: 6px 14px; font-size: 13px; cursor: pointer; transition: all .15s; }
        .chip:hover { background: var(--keylime); }
        .chip.activo { background: var(--forest); color: var(--cream); border-color: var(--forest); }

        dialog { border: none; border-radius: 14px; padding: 0; max-width: 460px; width: calc(100% - 32px); background: var(--cream); }
        dialog::backdrop { background: rgba(15, 62, 23, .35); }

        #toast { position: fixed; right: 20px; bottom: 20px; z-index: 100; max-width: 340px; padding: 12px 18px; border-radius: 14px; background: var(--forest); color: var(--cream);
                 font-size: 14px; opacity: 0; transform: translateY(10px); transition: all .25s; pointer-events: none; }
        #toast.visible { opacity: 1; transform: translateY(0); }
        #toast.error { background: #9b2c1e; }

        /* Impresión / guardar como PDF: solo el contenido del reporte */
        @media print {
            aside, .no-print, #toast { display: none !important; }
            main { padding: 0 !important; }
            body { background: #fff; font-size: 12px; }
            .card { background: #fff; border: 1px solid #ccc; }
            table.tabla th { background: #eee !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            tr, article { break-inside: avoid; }
        }
    </style>
</head>
<body class="antialiased">
<div class="min-h-screen lg:flex">

    <!-- Barra lateral -->
    <aside class="lg:w-64 lg:min-h-screen shrink-0 p-6 flex flex-col justify-between gap-8" style="background: var(--keylime);">
        <div>
            <a href="<?= $esAdmin ? '/admin/medicamentos' : '/farmacia/pedidos' ?>" class="block mb-8">
                <span class="eyebrow"><?= $esAdmin ? 'Administración' : 'Farmacia' ?></span>
                <span class="serif block text-[34px] leading-tight">StockMed</span>
            </a>
            <nav class="flex lg:flex-col gap-1 flex-wrap">
                <?php foreach ($menu as [$clave, $url, $texto]): ?>
                    <a href="<?= $url ?>" class="px-3 py-2.5 rounded-[10px] text-[14px] transition-colors <?= $activo === $clave ? 'bg-[#0f3e17] text-[#fffefc]' : 'text-[#222] hover:bg-[#cfe7d3]' ?>">
                        <?= esc($texto) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>

        <div class="text-[13px]">
            <p class="font-medium"><?= esc(trim((session()->get('nombre') ?? '') . ' ' . (session()->get('apellido') ?? ''))) ?></p>
            <p class="text-[#4d6b52] mb-3"><?= $esAdmin ? 'Administrador' : 'Farmacéutico' ?></p>
            <div class="flex gap-2 flex-wrap">
                <a href="/" class="btn btn-sec btn-mini">Ver catálogo</a>
                <a href="/logout" class="btn btn-mini">Cerrar sesión</a>
            </div>
        </div>
    </aside>

    <!-- Contenido -->
    <main class="flex-1 min-w-0 p-6 lg:p-10">
        <?php if (session()->has('mensaje')): ?>
            <div class="mb-6 px-5 py-3 rounded-[14px] text-[14px]" style="background: var(--sage);"><?= esc(session()->get('mensaje')) ?></div>
        <?php endif; ?>
        <?php if (session()->has('error')): ?>
            <div class="mb-6 px-5 py-3 rounded-[14px] text-[14px] badge-bajo" style="border: 1px solid #f0cfca;"><?= esc(session()->get('error')) ?></div>
        <?php endif; ?>

        <?= $this->renderSection('contenido') ?>
    </main>
</div>

<div id="toast" role="status"></div>

<script>
    window.CSRF = <?= json_encode(['n' => csrf_token(), 'v' => csrf_hash()]) ?>;
    window.norm = s => (s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
    window.toast = function (msg, error) {
        const t = document.getElementById('toast');
        t.textContent = msg; t.classList.toggle('error', !!error); t.classList.add('visible');
        clearTimeout(window._tt); window._tt = setTimeout(() => t.classList.remove('visible'), 3200);
    };
</script>
<?= $this->renderSection('scripts') ?>
</body>
</html>