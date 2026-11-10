<?= $this->extend('layouts/staff') ?>

<?= $this->section('contenido') ?>
<?php
/* Dibuja una celda según su tipo (ver ReporteController) */
$celda = function ($c) {
    if (!is_array($c)) return esc((string) $c);
    if (isset($c['form'])) {
        $f = $c['form'];
        $html = '<form method="post" action="' . esc($f['action']) . '" class="no-print" onsubmit="return confirm(\'' . esc($f['confirm'], 'js') . '\');">' . csrf_field();
        foreach ($f['campos'] as $k => $v) {
            $html .= '<input type="hidden" name="' . esc($k) . '" value="' . esc($v) . '">';
        }
        return $html . '<button type="submit" class="btn btn-peligro btn-mini">' . esc($f['texto']) . '</button></form>';
    }
    if (isset($c['n'])) return esc(($c['pre'] ?? '') . number_format((float) $c['n'], $c['d'] ?? 0));
    $t = esc($c['t'] ?? '');
    if (!empty($c['b'])) return '<span class="badge badge-' . esc($c['b']) . '">' . $t . '</span>';
    if (isset($c['sub'])) return '<p class="font-medium">' . $t . '</p><p class="text-xs text-[#666]">' . esc($c['sub']) . '</p>';
    return $t;
};
$modoPeriodo = in_array($tipo, ['ventas', 'masvendidos', 'vencidos'], true);
$modoDias    = in_array($tipo, ['sinmovimiento', 'porvencer'], true);
?>

<div class="no-print mb-8">
    <span class="eyebrow"><?= (int) session()->get('idRol') === 3 ? 'Administración' : 'Farmacia' ?></span>
    <h1 class="serif text-[44px] leading-tight">Reportes</h1>
    <p class="text-[#555]">Ventas, más vendidos, agotados, sin movimiento, por vencer, vencidos e inventario. Expórtelos a Excel (CSV) o guárdelos como PDF.</p>
</div>

<!-- Filtros -->
<form method="get" action="/reportes" class="card no-print mb-6 grid md:grid-cols-4 gap-4 items-end">
    <div>
        <label class="campo" for="tipo">Reporte</label>
        <select id="tipo" name="tipo" class="input">
            <?php foreach ($tipos as $clave => $texto): ?>
                <option value="<?= esc($clave) ?>" <?= $tipo === $clave ? 'selected' : '' ?>><?= esc($texto) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div id="bloquePeriodo">
        <label class="campo" for="periodo">Período</label>
        <select id="periodo" name="periodo" class="input">
            <option value="hoy" <?= $periodo === 'hoy' ? 'selected' : '' ?>>Hoy</option>
            <option value="semana" <?= $periodo === 'semana' ? 'selected' : '' ?>>Últimos 7 días</option>
            <option value="mes" <?= $periodo === 'mes' ? 'selected' : '' ?>>Este mes</option>
            <option value="mes_anterior" <?= $periodo === 'mes_anterior' ? 'selected' : '' ?>>Mes anterior</option>
            <option value="personalizado" <?= $periodo === 'personalizado' ? 'selected' : '' ?>>Rango personalizado</option>
        </select>
    </div>

    <div id="bloqueFechas" class="grid grid-cols-2 gap-2">
        <div>
            <label class="campo" for="desde">Desde</label>
            <input id="desde" type="date" name="desde" value="<?= esc($desde) ?>" class="input">
        </div>
        <div>
            <label class="campo" for="hasta">Hasta</label>
            <input id="hasta" type="date" name="hasta" value="<?= esc($hasta) ?>" class="input">
        </div>
    </div>

    <div id="bloqueDias">
        <label class="campo" for="dias" id="etiquetaDias">Plazo</label>
        <select id="dias" name="dias" class="input">
            <?php foreach ([30, 60, 90, 180] as $d): ?>
                <option value="<?= $d ?>" <?= $dias === $d ? 'selected' : '' ?>><?= $d ?> días</option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="flex gap-2">
        <button type="submit" class="btn">Generar</button>
    </div>
</form>

<div class="no-print flex flex-wrap gap-2 mb-8">
    <a href="/reportes/exportar?<?= esc($query) ?>" class="btn btn-sec">Exportar a Excel (CSV)</a>
    <button type="button" class="btn btn-sec" onclick="window.print()">Imprimir / Guardar PDF</button>
</div>

<!-- Reporte -->
<section id="reporte">
    <div class="mb-6">
        <span class="eyebrow">StockMed</span>
        <h2 class="serif text-[36px] leading-tight"><?= esc($rep['titulo']) ?></h2>
        <p class="text-[#555]">
            <?= !empty($rep['subtitulo']) ? esc($rep['subtitulo']) . ' &middot; ' : '' ?>
            Generado el <?= esc($generadoEn) ?> por <?= esc($generadoPor) ?>
        </p>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-10">
        <?php foreach ($rep['resumen'] as [$etiqueta, $valor]): ?>
            <div class="card">
                <span class="eyebrow"><?= esc($etiqueta) ?></span>
                <p class="serif text-[32px] leading-tight break-words"><?= $celda($valor) ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <?php foreach ($rep['secciones'] as $sec): ?>
        <h3 class="serif text-[28px] mb-1"><?= esc($sec['titulo']) ?></h3>
        <?php if (!empty($sec['nota'])): ?><p class="text-[#555] text-[13px] mb-3"><?= esc($sec['nota']) ?></p><?php else: ?><div class="mb-3"></div><?php endif; ?>

        <?php if (empty($sec['filas'])): ?>
            <div class="card text-center text-[#555] mb-10"><?= esc($sec['vacio'] ?? 'Sin datos') ?></div>
        <?php else: ?>
            <div class="caja overflow-x-auto mb-10">
                <table class="tabla">
                    <thead><tr><?php foreach ($sec['columnas'] as $col): ?><th><?= esc($col) ?></th><?php endforeach; ?></tr></thead>
                    <tbody>
                        <?php foreach ($sec['filas'] as $fila): ?>
                            <tr><?php foreach ($fila as $c): ?><td><?= $celda($c) ?></td><?php endforeach; ?></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</section>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    const tipo = document.getElementById('tipo'), periodo = document.getElementById('periodo');
    const bPeriodo = document.getElementById('bloquePeriodo'), bFechas = document.getElementById('bloqueFechas'), bDias = document.getElementById('bloqueDias');
    const etiqueta = document.getElementById('etiquetaDias');
    function ajustar() {
        const conPeriodo = ['ventas', 'masvendidos', 'vencidos'].includes(tipo.value);
        const conDias = ['sinmovimiento', 'porvencer'].includes(tipo.value);
        bPeriodo.classList.toggle('hidden', !conPeriodo);
        bFechas.classList.toggle('hidden', !conPeriodo || periodo.value !== 'personalizado');
        bDias.classList.toggle('hidden', !conDias);
        etiqueta.textContent = tipo.value === 'porvencer' ? 'Vence en los próximos' : 'Sin movimiento en los últimos';
    }
    tipo.addEventListener('change', ajustar);
    periodo.addEventListener('change', ajustar);
    ajustar();
})();
</script>
<?= $this->endSection() ?>