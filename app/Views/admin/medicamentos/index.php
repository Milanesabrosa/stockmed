<?= $this->extend('layouts/staff') ?>

<?= $this->section('contenido') ?>
<?php
$conteo = [];
$nBajo  = 0;
foreach ($medicamentos as $m) {
    $conteo[$m['nombreCategoria']] = ($conteo[$m['nombreCategoria']] ?? 0) + 1;
    if ($m['stockActual'] <= $m['rop']) $nBajo++;
}
?>

<div class="flex flex-wrap items-end justify-between gap-4 mb-8">
    <div>
        <span class="eyebrow">Administración</span>
        <h1 class="serif text-[44px] leading-tight">Inventario</h1>
        <p class="text-[#555]">Stock, puntos de reorden (ROP) y lotes económicos (EOQ) de cada medicamento.</p>
    </div>
    <div class="flex gap-2 flex-wrap">
        <button type="button" class="btn btn-sec" onclick="document.getElementById('modalCategoria').showModal()">Nueva categoría</button>
        <a href="/admin/medicamentos/nuevo" class="btn">Nuevo medicamento</a>
    </div>
</div>

<!-- Buscador y filtros por categoría (se generan solos con las categorías existentes) -->
<div class="card mb-6">
    <input id="buscar" type="search" autocomplete="off" placeholder="Buscar por nombre comercial, genérico o categoría" class="input mb-4">
    <div id="chips" class="flex flex-wrap gap-2">
        <button type="button" data-cat="" class="chip activo">Mostrar todo (<?= count($medicamentos) ?>)</button>
        <button type="button" data-cat="" data-bajo="1" class="chip">Stock bajo (<?= $nBajo ?>)</button>
        <?php foreach ($categorias as $c): ?>
            <button type="button" data-cat="<?= esc($c['nombreCategoria']) ?>" class="chip">
                <?= esc($c['nombreCategoria']) ?> (<?= $conteo[$c['nombreCategoria']] ?? 0 ?>)
            </button>
        <?php endforeach; ?>
    </div>
</div>

<div class="caja overflow-x-auto">
    <table class="tabla">
        <thead>
            <tr>
                <th>Medicamento</th>
                <th>Categoría</th>
                <th>Precio (Bs)</th>
                <th>Stock</th>
                <th>ROP</th>
                <th>EOQ</th>
                <th>Vence</th>
                <th>Receta</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="cuerpo">
            <?php foreach ($medicamentos as $m): $bajo = $m['stockActual'] <= $m['rop']; ?>
            <tr class="fila" data-cat="<?= esc($m['nombreCategoria']) ?>" data-bajo="<?= $bajo ? 1 : 0 ?>"
                data-buscar="<?= esc($m['nombreComercial'] . ' ' . $m['nombreGenerico'] . ' ' . $m['nombreCategoria']) ?>">
                <td>
                    <p class="font-medium"><?= esc($m['nombreComercial']) ?></p>
                    <p class="text-xs text-[#666]">Genérico: <?= esc($m['nombreGenerico']) ?></p>
                </td>
                <td><span class="badge"><?= esc($m['nombreCategoria']) ?></span></td>
                <td><?= number_format($m['precioUnitario'], 2) ?></td>
                <td><span class="badge <?= $bajo ? 'badge-bajo' : 'badge-ok' ?>"><?= esc($m['stockActual']) ?></span></td>
                <td><?= esc($m['rop']) ?></td>
                <td><?= esc($m['eoq']) ?></td>
                <td>
                    <?php $v = $vencimientos[$m['idMedicamento']] ?? null; ?>
                    <?php if (!$v): ?>
                        <span class="text-[#888]">Sin lote</span>
                    <?php else:
                        $dias = (int) floor((strtotime($v['fecha']) - strtotime(date('Y-m-d'))) / 86400); ?>
                        <span class="badge <?= $dias < 0 ? 'badge-bajo' : ($dias <= 30 ? 'badge-aviso' : '') ?>"><?= date('d/m/Y', strtotime($v['fecha'])) ?></span>
                    <?php endif; ?>
                </td>
                <td><?= $m['recetaMedica'] == 1 ? '<span class="badge badge-aviso">Requiere</span>' : '<span class="text-[#888]">No</span>' ?></td>
                <td>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" class="btn btn-sec btn-mini ver">Detalle</button>

                        <button type="button" class="btn btn-mini" data-ingreso
                                data-id="<?= esc($m['idMedicamento']) ?>" data-nombre="<?= esc($m['nombreComercial']) ?>"
                                data-eoq="<?= (int) $m['eoq'] ?>" data-volver="/admin/medicamentos">Agregar stock</button>

                        <form method="post" action="/admin/medicamentos/eliminar/<?= esc($m['idMedicamento']) ?>"
                              onsubmit="return confirm('¿Eliminar este medicamento del inventario?');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-peligro btn-mini">Eliminar</button>
                        </form>
                    </div>
                </td>
            </tr>
            <tr class="detalle hidden">
                <td colspan="9">
                    <div class="grid md:grid-cols-2 gap-x-8 gap-y-3 text-[13px]">
                        <p><span class="eyebrow block">Descripción</span><?= esc($m['descripcion']) ?: '-' ?></p>
                        <p><span class="eyebrow block">Indicaciones terapéuticas</span><?= esc($m['indicaciones']) ?: '-' ?></p>
                        <p><span class="eyebrow block">Efectos secundarios</span><?= esc($m['efectosSecundarios']) ?: '-' ?></p>
                        <p><span class="eyebrow block">Vía de administración</span><?= esc($m['viaAdministrativa']) ?: '-' ?></p>
                        <p><span class="eyebrow block">Dosis recomendada</span><?= esc($m['dosis']) ?: '-' ?></p>
                        <p><span class="eyebrow block">Imagen</span>
                            <?php if (!empty($m['imagen'])): ?>
                                <a href="<?= esc($m['imagen']) ?>" target="_blank" rel="noopener" class="underline text-[#0f3e17]">Ver imagen</a>
                            <?php else: ?>-<?php endif; ?>
                        </p>
                        <div class="md:col-span-2">
                            <span class="eyebrow block mb-1">Lotes registrados</span>
                            <?php $ls = $lotes[$m['idMedicamento']] ?? []; ?>
                            <?php if (empty($ls)): ?>
                                <p class="text-[#666]">Sin lotes registrados (stock anterior al control por lotes).</p>
                            <?php else: ?>
                                <ul class="space-y-1">
                                    <?php foreach ($ls as $l): $vencido = $l['fechaVencimiento'] < date('Y-m-d'); ?>
                                        <li>
                                            <strong>Lote <?= esc($l['numeroLote']) ?></strong>
                                            &middot; vence <?= date('d/m/Y', strtotime($l['fechaVencimiento'])) ?>
                                            <?= $vencido && $l['restante'] > 0 ? '<span class="badge badge-bajo">Vencido</span>' : '' ?>
                                            &middot; ingresaron <?= (int) $l['cantidad'] ?> el <?= date('d/m/Y', strtotime($l['fechaIngreso'])) ?>
                                            &middot; quedan aprox. <?= (int) $l['restante'] ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <tr id="sinResultados" class="<?= empty($medicamentos) ? '' : 'hidden' ?>">
                <td colspan="9" class="text-center py-10 text-[#666]">
                    <?= empty($medicamentos) ? 'No hay medicamentos registrados en el inventario.' : 'No se encontraron medicamentos con ese criterio.' ?>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<!-- Modal: nueva categoría -->
<dialog id="modalCategoria">
    <form method="post" action="/admin/categorias/guardar" class="p-7">
        <?= csrf_field() ?>
        <span class="eyebrow">Categorías</span>
        <h2 class="serif text-[32px] leading-tight mb-5">Nueva categoría</h2>

        <label class="campo" for="nombreCategoria">Nombre</label>
        <input id="nombreCategoria" name="nombreCategoria" type="text" required maxlength="100" placeholder="Ej: Antibióticos" class="input mb-4">

        <label class="campo" for="descCategoria">Descripción</label>
        <textarea id="descCategoria" name="descripcion" rows="3" placeholder="Para qué tipo de medicamentos se usa" class="input mb-6"></textarea>

        <div class="flex justify-end gap-2">
            <button type="button" class="btn btn-sec" onclick="this.closest('dialog').close()">Cancelar</button>
            <button type="submit" class="btn">Guardar categoría</button>
        </div>
    </form>
</dialog>

<?= $this->include('partials/modal_ingreso') ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    const filas = [...document.querySelectorAll('#cuerpo tr.fila')];
    const buscar = document.getElementById('buscar');
    const chips = document.querySelectorAll('#chips .chip');
    const vacio = document.getElementById('sinResultados');
    let cat = '', soloBajo = false;

    function filtrar() {
        const q = norm(buscar.value);
        let visibles = 0;
        filas.forEach(f => {
            const ok = (!cat || f.dataset.cat === cat) && (!soloBajo || f.dataset.bajo === '1') && (!q || norm(f.dataset.buscar).includes(q));
            f.classList.toggle('hidden', !ok);
            const det = f.nextElementSibling;
            if (!ok) det.classList.add('hidden');
            if (ok) visibles++;
        });
        if (filas.length) vacio.classList.toggle('hidden', visibles > 0);
    }

    buscar.addEventListener('input', filtrar);
    chips.forEach(c => c.addEventListener('click', () => {
        chips.forEach(x => x.classList.remove('activo'));
        c.classList.add('activo'); cat = c.dataset.cat; soloBajo = c.dataset.bajo === '1'; filtrar();
    }));
    // Llegar desde "Pedidos" con ?filtro=bajo deja activo el filtro de stock bajo
    if (new URLSearchParams(location.search).get('filtro') === 'bajo') {
        const chipBajo = document.querySelector('#chips [data-bajo="1"]');
        if (chipBajo) chipBajo.click();
    }
    filas.forEach(f => f.querySelector('.ver').addEventListener('click', () => f.nextElementSibling.classList.toggle('hidden')));
})();
</script>
<?= $this->endSection() ?>