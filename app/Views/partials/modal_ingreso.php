<?php /* Ventana "Registrar ingreso de stock" (lote + vencimiento). Se abre con cualquier botón que tenga data-ingreso, data-id, data-nombre, data-eoq y data-volver. */ ?>
<dialog id="modalIngreso">
    <form method="post" action="/admin/medicamentos/stock" class="p-7">
        <?= csrf_field() ?>
        <input type="hidden" name="idMedicamento" id="ingresoId">
        <input type="hidden" name="volver" id="ingresoVolver" value="/admin/medicamentos">

        <span class="eyebrow">Ingreso de stock</span>
        <h2 class="serif text-[32px] leading-tight">Registrar ingreso</h2>
        <p id="ingresoNombre" class="text-[#555] mb-5"></p>

        <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
                <label class="campo" for="ingresoLote">Número de lote</label>
                <input id="ingresoLote" name="numeroLote" type="text" maxlength="50" required placeholder="Ej: L2411-A" class="input">
            </div>
            <div>
                <label class="campo" for="ingresoVenc">Fecha de vencimiento</label>
                <input id="ingresoVenc" name="fechaVencimiento" type="date" required class="input">
            </div>
        </div>

        <label class="campo" for="ingresoCantidad">Cantidad que ingresa</label>
        <input id="ingresoCantidad" name="cantidad" type="number" min="1" required class="input mb-6">

        <div class="flex justify-end gap-2">
            <button type="button" class="btn btn-sec" onclick="this.closest('dialog').close()">Cancelar</button>
            <button type="submit" class="btn">Registrar ingreso</button>
        </div>
    </form>
</dialog>

<script>
(function () {
    const dlg = document.getElementById('modalIngreso');
    function manana() {
        const m = new Date(); m.setDate(m.getDate() + 1);
        return [m.getFullYear(), String(m.getMonth() + 1).padStart(2, '0'), String(m.getDate()).padStart(2, '0')].join('-');
    }
    document.addEventListener('click', e => {
        const b = e.target.closest('[data-ingreso]');
        if (!b) return;
        document.getElementById('ingresoId').value = b.dataset.id;
        document.getElementById('ingresoVolver').value = b.dataset.volver || '/admin/medicamentos';
        document.getElementById('ingresoNombre').textContent = b.dataset.nombre;
        document.getElementById('ingresoLote').value = '';
        document.getElementById('ingresoVenc').value = '';
        document.getElementById('ingresoVenc').min = manana();
        document.getElementById('ingresoCantidad').value = parseInt(b.dataset.eoq, 10) > 0 ? b.dataset.eoq : '';
        dlg.showModal();
    });
})();
</script>