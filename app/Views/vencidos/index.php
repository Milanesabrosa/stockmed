<?= $this->extend('layouts/staff') ?>

<?= $this->section('contenido') ?>
<?php
$esAdmin  = (int) session()->get('idRol') === 3;
$tabInicial = count($porReponer) > 0 ? 'reponer' : 'proximos';
?>

<div class="mb-6">
    <span class="eyebrow">Control de vencimientos</span>
    <h1 class="serif text-[44px] leading-tight">Vencimientos</h1>
    <p class="text-[#555] max-w-3xl">
        Cuando un lote llega a su fecha de vencimiento, sus unidades se retiran del inventario y quedan registradas como pérdida.
        Si el medicamento se queda sin unidades vigentes, deja de venderse hasta que se registre un nuevo lote.
    </p>
</div>

<?php if ($recien['unidades'] > 0): ?>
    <div class="card mb-6">
        Se retiraron <strong><?= (int) $recien['unidades'] ?></strong> unidades vencidas de
        <strong><?= (int) $recien['medicamentos'] ?></strong> medicamento(s)<?= $recien['inactivados'] > 0 ? ', y ' . (int) $recien['inactivados'] . ' dejó/dejaron de venderse' : '' ?>.
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
    <div class="card">
        <span class="eyebrow">Por reponer</span>
        <p class="serif text-[32px] leading-tight"><?= count($porReponer) ?></p>
        <p class="text-xs text-[#4d6b52]">medicamentos sin stock vigente</p>
    </div>
    <div class="card">
        <span class="eyebrow">Vencen en 30 días</span>
        <p class="serif text-[32px] leading-tight"><?= count($proximos) ?></p>
        <p class="text-xs text-[#4d6b52]">lotes a la venta</p>
    </div>
    <div class="card">
        <span class="eyebrow">Pérdida total</span>
        <p class="serif text-[32px] leading-tight">Bs. <?= number_format($totales['valor'], 2) ?></p>
        <p class="text-xs text-[#4d6b52]"><?= number_format($totales['unidades']) ?> unidades vencidas</p>
    </div>
</div>

<div class="flex flex-wrap gap-2 mb-6" id="pestanas">
    <button type="button" class="chip" data-tab="reponer">Por reponer (<?= count($porReponer) ?>)</button>
    <button type="button" class="chip" data-tab="proximos">Próximos a vencer (<?= count($proximos) ?>)</button>
    <button type="button" class="chip" data-tab="perdidas">Pérdidas</button>
</div>

<!-- Por reponer -->
<section data-panel="reponer" class="hidden">
    <h3 class="serif text-[28px] mb-1">Por reponer</h3>
    <p class="text-[#555] text-[13px] mb-3">Se vencieron y ya no se venden. Registre un nuevo lote para que vuelvan al catálogo.</p>
    <?php if (empty($porReponer)): ?>
        <div class="card text-center text-[#555]">No hay medicamentos pendientes de reposición.</div>
    <?php else: ?>
        <div class="caja overflow-x-auto">
            <table class="tabla">
                <thead><tr><th>Medicamento</th><th>Categoría</th><th>Retirado el</th><?php if ($esAdmin): ?><th></th><?php endif; ?></tr></thead>
                <tbody>
                <?php foreach ($porReponer as $m): ?>
                    <tr>
                        <td><p class="font-medium"><?= esc($m['nombreComercial']) ?></p><p class="text-xs text-[#666]"><?= esc($m['nombreGenerico']) ?></p></td>
                        <td><?= esc($m['nombreCategoria'] ?? '') ?></td>
                        <td><?= $m['ultimaBaja'] ? esc(date('d/m/Y', strtotime($m['ultimaBaja']))) : '' ?></td>
                        <?php if ($esAdmin): ?>
                        <td>
                            <button type="button" class="btn btn-mini" data-ingreso
                                    data-id="<?= (int) $m['idMedicamento'] ?>" data-nombre="<?= esc($m['nombreComercial']) ?>"
                                    data-eoq="<?= (int) $m['eoq'] ?>" data-volver="/vencidos">Registrar nuevo lote</button>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<!-- Próximos a vencer -->
<section data-panel="proximos" class="hidden">
    <h3 class="serif text-[28px] mb-1">Próximos a vencer</h3>
    <p class="text-[#555] text-[13px] mb-3">Siguen a la venta. Úselos primero o promociónelos antes de que se pierdan.</p>
    <?php if (empty($proximos)): ?>
        <div class="card text-center text-[#555]">Ningún lote vence en los próximos 30 días.</div>
    <?php else: ?>
        <div class="caja overflow-x-auto">
            <table class="tabla">
                <thead><tr><th>Medicamento</th><th>Lote</th><th>Vence</th><th>Unidades</th><th>Valor en riesgo</th></tr></thead>
                <tbody>
                <?php foreach ($proximos as $l): ?>
                    <tr>
                        <td><p class="font-medium"><?= esc($l['nombreComercial']) ?></p><p class="text-xs text-[#666]"><?= esc($l['nombreGenerico']) ?></p></td>
                        <td><?= esc($l['numeroLote']) ?></td>
                        <td>
                            <?= esc(date('d/m/Y', strtotime($l['fechaVencimiento']))) ?>
                            <span class="badge <?= $l['dias'] <= 7 ? 'badge-bajo' : 'badge-aviso' ?>"><?= $l['dias'] === 1 ? 'mañana' : 'en ' . $l['dias'] . ' días' ?></span>
                        </td>
                        <td><?= (int) $l['restante'] ?></td>
                        <td>Bs. <?= number_format($l['restante'] * $l['precioUnitario'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<!-- Pérdidas -->
<section data-panel="perdidas" class="hidden">
    <h3 class="serif text-[28px] mb-1">Pérdidas por vencimiento</h3>
    <p class="text-[#555] text-[13px] mb-3">Historial de unidades retiradas. El valor usa el precio de venta del medicamento al momento de la baja.</p>
    <?php if (empty($historial)): ?>
        <div class="card text-center text-[#555]">Aún no hay pérdidas registradas.</div>
    <?php else: ?>
        <div class="caja overflow-x-auto">
            <table class="tabla">
                <thead><tr><th>Retirado el</th><th>Medicamento</th><th>Lote</th><th>Venció</th><th>Unidades</th><th>Valor perdido</th><?php if ($esAdmin): ?><th></th><?php endif; ?></tr></thead>
                <tbody>
                <?php foreach ($historial as $h): ?>
                    <tr>
                        <td><?= esc(date('d/m/Y H:i', strtotime($h['fechaBaja']))) ?></td>
                        <td><p class="font-medium"><?= esc($h['nombreComercial']) ?></p><p class="text-xs text-[#666]"><?= esc($h['nombreGenerico']) ?></p></td>
                        <td><?= esc($h['numeroLote'] ?? '-') ?></td>
                        <td><?= !empty($h['fechaVencimiento']) ? esc(date('d/m/Y', strtotime($h['fechaVencimiento']))) : '-' ?></td>
                        <td><?= (int) $h['cantidad'] ?></td>
                        <td>Bs. <?= number_format($h['cantidad'] * $h['precioUnitario'], 2) ?></td>
                        <?php if ($esAdmin): ?>
                        <td>
                            <?php if ($h['estado'] === 'activo'): ?>
                            <button type="button" class="btn btn-sec btn-mini" data-ingreso
                                    data-id="<?= (int) $h['idMedicamento'] ?>" data-nombre="<?= esc($h['nombreComercial']) ?>"
                                    data-eoq="<?= (int) $h['eoq'] ?>" data-volver="/vencidos">Nuevo lote</button>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php if ($esAdmin) echo $this->include('partials/modal_ingreso'); ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    const botones = document.querySelectorAll('#pestanas [data-tab]');
    const paneles = document.querySelectorAll('[data-panel]');
    function ir(tab) {
        botones.forEach(b => b.classList.toggle('activo', b.dataset.tab === tab));
        paneles.forEach(p => p.classList.toggle('hidden', p.dataset.panel !== tab));
    }
    botones.forEach(b => b.addEventListener('click', () => ir(b.dataset.tab)));
    ir('<?= $tabInicial ?>');
})();
</script>
<?= $this->endSection() ?>