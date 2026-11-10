<?= $this->extend('layouts/staff') ?>

<?= $this->section('contenido') ?>
<?php $fmt = fn($f) => date('d/m/Y', strtotime($f)); ?>

<div class="no-print mb-8">
    <span class="eyebrow"><?= (int) session()->get('idRol') === 3 ? 'Administración' : 'Farmacia' ?></span>
    <h1 class="serif text-[44px] leading-tight">Historial de ventas</h1>
    <p class="text-[#555]">Ventas realizadas en mostrador. Los pedidos de clientes se ven en "Pedidos"; ambos suman en Reportes.</p>
</div>

<form method="get" action="/farmacia/ventas/historial" class="card no-print mb-6 grid md:grid-cols-4 gap-4 items-end">
    <div>
        <label class="campo" for="periodo">Período</label>
        <select id="periodo" name="periodo" class="input">
            <option value="hoy" <?= $periodo === 'hoy' ? 'selected' : '' ?>>Hoy</option>
            <option value="semana" <?= $periodo === 'semana' ? 'selected' : '' ?>>Últimos 7 días</option>
            <option value="mes" <?= $periodo === 'mes' ? 'selected' : '' ?>>Este mes</option>
            <option value="mes_anterior" <?= $periodo === 'mes_anterior' ? 'selected' : '' ?>>Mes anterior</option>
            <option value="personalizado" <?= $periodo === 'personalizado' ? 'selected' : '' ?>>Rango personalizado</option>
        </select>
    </div>
    <div id="bloqueFechas" class="grid grid-cols-2 gap-2 md:col-span-2">
        <div>
            <label class="campo" for="desde">Desde</label>
            <input id="desde" type="date" name="desde" value="<?= esc($desde) ?>" class="input">
        </div>
        <div>
            <label class="campo" for="hasta">Hasta</label>
            <input id="hasta" type="date" name="hasta" value="<?= esc($hasta) ?>" class="input">
        </div>
    </div>
    <div><button type="submit" class="btn">Filtrar</button></div>
</form>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="card"><span class="eyebrow">Período</span><p class="serif text-[26px] leading-tight"><?= $fmt($desde) ?><?= $desde !== $hasta ? ' - ' . $fmt($hasta) : '' ?></p></div>
    <div class="card"><span class="eyebrow">Ventas</span><p class="serif text-[32px] leading-tight"><?= count($ventas) ?></p></div>
    <div class="card"><span class="eyebrow">Unidades</span><p class="serif text-[32px] leading-tight"><?= (int) $unidades ?></p></div>
    <div class="card"><span class="eyebrow">Total vendido</span><p class="serif text-[32px] leading-tight">Bs. <?= number_format($total, 2) ?></p></div>
</div>

<div class="no-print mb-4">
    <input id="buscar" type="search" autocomplete="off" placeholder="Buscar por medicamento, categoría o vendedor" class="input">
</div>

<div class="caja overflow-x-auto">
    <table class="tabla">
        <thead>
            <tr><th>Fecha</th><th>Medicamento</th><th>Categoría</th><th>Cant.</th><th>Precio (Bs)</th><th>Subtotal (Bs)</th><th>Vendedor</th></tr>
        </thead>
        <tbody id="cuerpo">
            <?php foreach ($ventas as $v): ?>
            <tr class="fila" data-buscar="<?= esc($v['nombreComercial'] . ' ' . $v['nombreGenerico'] . ' ' . $v['nombreCategoria'] . ' ' . $v['vendedor']) ?>">
                <td class="whitespace-nowrap"><?= esc(date('d/m/Y H:i', strtotime($v['fechaVenta']))) ?></td>
                <td>
                    <p class="font-medium"><?= esc($v['nombreComercial']) ?></p>
                    <p class="text-xs text-[#666]">Genérico: <?= esc($v['nombreGenerico']) ?></p>
                </td>
                <td><span class="badge"><?= esc($v['nombreCategoria']) ?></span></td>
                <td><?= (int) $v['cantidad'] ?></td>
                <td><?= number_format($v['precio'], 2) ?></td>
                <td class="font-medium"><?= number_format($v['cantidad'] * $v['precio'], 2) ?></td>
                <td><?= esc(trim($v['vendedor'])) ?: '-' ?></td>
            </tr>
            <?php endforeach; ?>
            <tr id="sinResultados" class="<?= empty($ventas) ? '' : 'hidden' ?>">
                <td colspan="7" class="text-center py-10 text-[#666]"><?= empty($ventas) ? 'No hay ventas en mostrador en este período.' : 'No se encontraron ventas.' ?></td>
            </tr>
        </tbody>
    </table>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    const periodo = document.getElementById('periodo'), fechas = document.getElementById('bloqueFechas');
    const ajustar = () => fechas.classList.toggle('hidden', periodo.value !== 'personalizado');
    periodo.addEventListener('change', ajustar); ajustar();

    const filas = [...document.querySelectorAll('#cuerpo tr.fila')];
    document.getElementById('buscar').addEventListener('input', e => {
        const q = norm(e.target.value); let n = 0;
        filas.forEach(f => { const ok = !q || norm(f.dataset.buscar).includes(q); f.classList.toggle('hidden', !ok); if (ok) n++; });
        if (filas.length) document.getElementById('sinResultados').classList.toggle('hidden', n > 0);
    });
})();
</script>
<?= $this->endSection() ?>