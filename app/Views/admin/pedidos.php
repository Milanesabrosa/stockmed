<?= $this->extend('layouts/staff') ?>

<?= $this->section('contenido') ?>
<div class="mb-8">
    <span class="eyebrow">Administración</span>
    <h1 class="serif text-[44px] leading-tight">Pedidos</h1>
    <p class="text-[#555]">Acepte (completado) o cancele las reservas pendientes de los clientes.</p>
</div>

<?php $nBajo = count($bajoStock); ?>
<?php if ($nBajo > 0): ?>
    <div class="card flex flex-wrap items-center justify-between gap-3 mb-8">
        <p><strong><?= $nBajo ?></strong> medicamento(s) con stock bajo o agotado.</p>
        <a href="/admin/medicamentos?filtro=bajo" class="btn btn-sec">Ver y reponer en Inventario</a>
    </div>
<?php endif; ?>

<!-- Pedidos pendientes -->
<h2 class="serif text-[30px] mb-4">Pedidos pendientes <span class="badge align-middle"><?= count($pendientes) ?></span></h2>

<?php if (empty($pendientes)): ?>
    <div class="card text-center text-[#555] mb-4">No hay pedidos pendientes en este momento.</div>
<?php else: ?>
    <div class="grid lg:grid-cols-2 gap-4 mb-12">
        <?php foreach ($pendientes as $p): $vencida = strtotime($p['fechaExpira']) < time(); ?>
        <article class="caja p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="eyebrow">Reserva #<?= esc($p['idReserva']) ?></span>
                    <h3 class="text-[18px] font-medium"><?= esc($p['nombre'] . ' ' . $p['apellido']) ?></h3>
                    <p class="text-xs text-[#666]">CI: <?= esc($p['ci']) ?> &middot; Tel: <?= esc($p['telefono']) ?></p>
                    <p class="text-xs text-[#666]"><?= esc($p['correo']) ?></p>
                </div>
                <div class="text-right">
                    <span class="badge <?= $vencida ? 'badge-bajo' : 'badge-aviso' ?>"><?= $vencida ? 'Vencida' : 'Pendiente' ?></span>
                    <p class="text-xs text-[#666] mt-2">Expira: <?= esc($p['fechaExpira']) ?></p>
                </div>
            </div>

            <ul class="mt-4 text-[13px]">
                <?php foreach ($p['items'] as $it): ?>
                    <li class="flex justify-between py-1.5 border-t border-[#efeeeb]">
                        <span><?= esc($it['cantidad']) ?> x <?= esc($it['nombreComercial']) ?> <span class="text-[#888]">(<?= esc($it['nombreGenerico']) ?>)</span></span>
                        <span>Bs. <?= number_format($it['cantidad'] * $it['precio'], 2) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="flex items-center justify-between mt-3 pt-3 border-t border-[#efeeeb]">
                <?= $p['requiereReceta'] == 1 ? '<span class="badge badge-aviso">Requiere receta</span>' : '<span></span>' ?>
                <p class="font-semibold">Total: Bs. <?= number_format($p['total'], 2) ?></p>
            </div>

            <div class="flex flex-wrap gap-2 mt-4">
                <form method="post" action="/farmacia/pedidos/completar/<?= esc($p['idReserva']) ?>"
                      onsubmit="return confirm('¿El cliente ya recogió este pedido?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="volver" value="/admin/pedidos">
                    <button type="submit" class="btn">Marcar como completado</button>
                </form>
                <form method="post" action="/farmacia/pedidos/cancelar/<?= esc($p['idReserva']) ?>"
                      onsubmit="return confirm('¿Cancelar este pedido? Los medicamentos volverán al inventario.');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="volver" value="/admin/pedidos">
                    <button type="submit" class="btn btn-peligro">Cancelar pedido</button>
                </form>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>