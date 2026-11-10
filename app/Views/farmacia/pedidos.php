<?= $this->extend('layouts/staff') ?>

<?= $this->section('contenido') ?>
<?php
$conteo = ['pendiente' => 0, 'completado' => 0, 'cancelada' => 0];
foreach ($reservas as $r) {
    $k = strtolower($r['estado']);
    if (isset($conteo[$k])) $conteo[$k]++;
}
?>

<div class="mb-8">
    <span class="eyebrow">Farmacia</span>
    <h1 class="serif text-[44px] leading-tight">Pedidos de clientes</h1>
    <p class="text-[#555]">Marque como completado el pedido cuando el cliente lo recoja. El stock ya se descontó al reservar.</p>
</div>

<div class="card mb-6">
    <div id="chips" class="flex flex-wrap gap-2 mb-4">
        <button type="button" data-estado="pendiente" class="chip activo">Pendientes (<?= $conteo['pendiente'] ?>)</button>
        <button type="button" data-estado="completado" class="chip">Completados (<?= $conteo['completado'] ?>)</button>
        <button type="button" data-estado="cancelada" class="chip">Cancelados (<?= $conteo['cancelada'] ?>)</button>
        <button type="button" data-estado="" class="chip">Todos (<?= count($reservas) ?>)</button>
    </div>
    <input id="buscar" type="search" autocomplete="off" placeholder="Buscar por cliente, CI o medicamento" class="input">
</div>

<div id="lista" class="grid lg:grid-cols-2 gap-4">
    <?php foreach ($reservas as $p):
        $estado = strtolower($p['estado']);
        $pendiente = $estado === 'pendiente';
        $vencida = $pendiente && strtotime($p['fechaExpira']) < time();
        $nombresItems = implode(' ', array_map(fn($i) => $i['nombreComercial'] . ' ' . $i['nombreGenerico'], $p['items']));
        $claseEstado = $estado === 'completado' ? 'badge-ok' : ($estado === 'cancelada' ? 'badge-bajo' : 'badge-aviso');
    ?>
    <article class="pedido caja p-5 flex flex-col" data-estado="<?= esc($estado) ?>"
             data-buscar="<?= esc($p['nombre'] . ' ' . $p['apellido'] . ' ' . $p['ci'] . ' ' . $nombresItems) ?>">
        <div class="flex items-start justify-between gap-3">
            <div>
                <span class="eyebrow">Reserva #<?= esc($p['idReserva']) ?></span>
                <h3 class="text-[18px] font-medium"><?= esc($p['nombre'] . ' ' . $p['apellido']) ?></h3>
                <p class="text-xs text-[#666]">CI: <?= esc($p['ci']) ?> &middot; Tel: <?= esc($p['telefono']) ?></p>
            </div>
            <div class="text-right">
                <span class="badge <?= $vencida ? 'badge-bajo' : $claseEstado ?>"><?= $vencida ? 'Vencida' : esc($p['estado']) ?></span>
                <p class="text-xs text-[#666] mt-2">Reservada: <?= esc($p['fechaReserva']) ?></p>
                <p class="text-xs text-[#666]">Expira: <?= esc($p['fechaExpira']) ?></p>
            </div>
        </div>

        <ul class="mt-4 text-[13px]">
            <?php foreach ($p['items'] as $it): ?>
                <li class="flex justify-between py-1.5 border-t border-[#efeeeb]">
                    <span><strong><?= esc($it['cantidad']) ?></strong> x <?= esc($it['nombreComercial']) ?> <span class="text-[#888]">(<?= esc($it['nombreGenerico']) ?>)</span></span>
                    <span>Bs. <?= number_format($it['cantidad'] * $it['precio'], 2) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="flex items-center justify-between mt-3 pt-3 border-t border-[#efeeeb]">
            <?= $p['requiereReceta'] == 1 ? '<span class="badge badge-aviso">Pedir receta médica</span>' : '<span></span>' ?>
            <p class="font-semibold">Total: Bs. <?= number_format($p['total'], 2) ?></p>
        </div>

        <?php if ($pendiente): ?>
        <div class="flex flex-wrap gap-2 mt-4">
            <form method="post" action="/farmacia/pedidos/completar/<?= esc($p['idReserva']) ?>"
                  onsubmit="return confirm('¿El cliente ya recogió este pedido?');">
                <?= csrf_field() ?>
                <button type="submit" class="btn">Marcar como completado</button>
            </form>
            <form method="post" action="/farmacia/pedidos/cancelar/<?= esc($p['idReserva']) ?>"
                  onsubmit="return confirm('¿Cancelar este pedido? Los medicamentos volverán al inventario.');">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-peligro">Cancelar</button>
            </form>
        </div>
        <?php endif; ?>
    </article>
    <?php endforeach; ?>
</div>

<div id="sinResultados" class="card text-center text-[#555] hidden">No hay pedidos para mostrar con este filtro.</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    const pedidos = [...document.querySelectorAll('.pedido')];
    const chips = document.querySelectorAll('#chips .chip');
    const buscar = document.getElementById('buscar');
    const vacio = document.getElementById('sinResultados');
    let estado = 'pendiente';

    function filtrar() {
        const q = norm(buscar.value); let n = 0;
        pedidos.forEach(p => {
            const ok = (!estado || p.dataset.estado === estado) && (!q || norm(p.dataset.buscar).includes(q));
            p.classList.toggle('hidden', !ok); if (ok) n++;
        });
        vacio.classList.toggle('hidden', n > 0);
    }
    buscar.addEventListener('input', filtrar);
    chips.forEach(c => c.addEventListener('click', () => {
        chips.forEach(x => x.classList.remove('activo')); c.classList.add('activo'); estado = c.dataset.estado; filtrar();
    }));
    filtrar();
})();
</script>
<?= $this->endSection() ?>