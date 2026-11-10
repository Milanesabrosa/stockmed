<?= $this->extend('layouts/staff') ?>

<?= $this->section('contenido') ?>
<?php $roles = [1 => 'Cliente', 2 => 'Farmacéutico', 3 => 'Administrador']; ?>

<div class="mb-8">
    <span class="eyebrow">Administración</span>
    <h1 class="serif text-[44px] leading-tight">Usuarios registrados</h1>
    <p class="text-[#555]"><?= count($usuarios) ?> usuario(s) en el sistema.</p>
</div>

<div class="card mb-6">
    <input id="buscar" type="search" autocomplete="off" placeholder="Buscar por nombre, CI, teléfono o correo" class="input">
</div>

<div class="caja overflow-x-auto">
    <table class="tabla">
        <thead>
            <tr>
                <th>ID</th><th>Nombre</th><th>Apellido</th><th>CI</th><th>Teléfono</th><th>Correo</th><th>Rol</th><th>Estado</th><th>Cambiar rol</th><th>Eliminar</th>
            </tr>
        </thead>
        <tbody id="cuerpo">
            <?php foreach ($usuarios as $u): ?>
            <tr class="fila" data-buscar="<?= esc($u['nombre'] . ' ' . $u['apellido'] . ' ' . $u['ci'] . ' ' . $u['telefono'] . ' ' . $u['correo']) ?>">
                <td class="text-[#666]"><?= esc($u['idPersona']) ?></td>
                <td class="font-medium"><?= esc($u['nombre']) ?></td>
                <td><?= esc($u['apellido']) ?></td>
                <td><?= esc($u['ci']) ?></td>
                <td><?= esc($u['telefono']) ?></td>
                <td><?= esc($u['correo']) ?></td>
                <td><span class="badge <?= (int) $u['idRol'] === 1 ? '' : 'badge-ok' ?>"><?= esc($roles[(int) $u['idRol']] ?? 'Rol ' . $u['idRol']) ?></span></td>
                <td><span class="badge"><?= esc($u['estado']) ?></span></td>
                <td>
                    <?php if ((int) $u['idRol'] === 3): ?>
                        <span class="text-[#888]">-</span>
                    <?php else: $haciaFarmaceutico = (int) $u['idRol'] === 1; ?>
                        <form method="post" action="/admin/clientes/rol"
                              onsubmit="return confirm('<?= esc(($haciaFarmaceutico ? 'Convertir a ' : 'Pasar a cliente a ') . $u['nombre'] . ' ' . $u['apellido'], 'js') ?><?= $haciaFarmaceutico ? ' en farmacéutico?' : '?' ?>');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="idPersona" value="<?= esc($u['idPersona']) ?>">
                            <input type="hidden" name="idRol" value="<?= $haciaFarmaceutico ? 2 : 1 ?>">
                            <button type="submit" class="btn btn-mini <?= $haciaFarmaceutico ? '' : 'btn-sec' ?>"><?= $haciaFarmaceutico ? 'Hacer farmacéutico' : 'Pasar a cliente' ?></button>
                        </form>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ((int) $u['idRol'] === 1): ?>
                        <form method="post" action="/admin/clientes/eliminar"
                              onsubmit="return confirm('<?= esc('Eliminar la cuenta de ' . $u['nombre'] . ' ' . $u['apellido'], 'js') ?>? Se cancelarán sus reservas pendientes y no podrá volver a iniciar sesión.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="idPersona" value="<?= esc($u['idPersona']) ?>">
                            <button type="submit" class="btn btn-peligro btn-mini">Eliminar</button>
                        </form>
                    <?php else: ?>
                        <span class="text-[#888]">-</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <tr id="sinResultados" class="<?= empty($usuarios) ? '' : 'hidden' ?>">
                <td colspan="10" class="text-center py-10 text-[#666]"><?= empty($usuarios) ? 'Aún no hay usuarios registrados.' : 'No se encontraron usuarios.' ?></td>
            </tr>
        </tbody>
    </table>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    const filas = [...document.querySelectorAll('#cuerpo tr.fila')];
    document.getElementById('buscar').addEventListener('input', e => {
        const q = norm(e.target.value); let n = 0;
        filas.forEach(f => { const ok = !q || norm(f.dataset.buscar).includes(q); f.classList.toggle('hidden', !ok); if (ok) n++; });
        if (filas.length) document.getElementById('sinResultados').classList.toggle('hidden', n > 0);
    });
</script>
<?= $this->endSection() ?>