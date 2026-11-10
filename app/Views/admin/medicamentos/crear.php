<?= $this->extend('layouts/staff') ?>

<?= $this->section('contenido') ?>
<?php
/* Esta misma vista sirve para CREAR (sin $m) y para EDITAR (con $m = datos del medicamento) */
$editar   = isset($m) && !empty($m);
$reenvio  = old('nombreComercial') !== null;           // se reenvió el formulario con errores
$val      = static fn(string $campo, $defecto = '') => esc((string) (old($campo) ?? ($m[$campo] ?? $defecto)));
$accion   = $editar ? '/admin/medicamentos/actualizar/' . (int) $m['idMedicamento'] : '/admin/medicamentos/guardar';
$conReceta = $reenvio ? (bool) old('recetaMedica') : ($editar && (int) ($m['recetaMedica'] ?? 0) === 1);
$catSel   = old('idCategoria') ?? ($m['idCategoria'] ?? '');
?>

<style>
    .form-med { font-size: 16px; }
    .form-med .seccion { background: var(--keylime); border-radius: 20px; padding: 32px; }
    .form-med .seccion h2 { font-size: 22px; font-weight: 600; color: var(--forest); display: flex; align-items: center; gap: 12px; margin-bottom: 4px; }
    .form-med .paso { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 50%; background: var(--forest); color: #fff; font-size: 15px; }
    .form-med .sub { color: #3f5a44; margin-bottom: 22px; }
    .form-med label.campo { font-size: 15px; font-weight: 600; margin-bottom: 8px; color: #16361b; }
    .form-med .input { font-size: 16px; padding: 13px 16px; background: #fff; border: 2px solid #8fb596; border-radius: 12px; }
    .form-med .input::placeholder { color: #8a9a8c; }
    .form-med .input:focus { border-color: var(--forest); box-shadow: 0 0 0 4px rgba(15, 62, 23, .14); }
    .form-med .ayuda { font-size: 14px; color: #3f5a44; margin-top: 7px; line-height: 1.45; }
    .form-med .tecnico { color: #6b8570; font-size: 12.5px; }
    .form-med .solo-lectura { background: #eaf3ea; border-style: dashed; font-weight: 600; }
    .form-med .receta { display: flex; align-items: flex-start; gap: 14px; background: #fff; border: 2px solid #8fb596; border-radius: 12px; padding: 16px; cursor: pointer; }
    .form-med .receta input { width: 22px; height: 22px; margin-top: 2px; accent-color: #0f3e17; flex: none; }
    .form-med .ejemplo { margin-top: 16px; background: #fff; border-left: 5px solid var(--forest); border-radius: 10px; padding: 12px 16px; font-size: 15px; display: none; }
    .form-med .ejemplo.visible { display: block; }
    .form-med .btn { font-size: 16px; padding: 14px 26px; }
</style>

<div class="form-med max-w-5xl">
    
    <div class="mt-3 mb-8">
        <span class="eyebrow">Administración</span>
        <h1 class="serif text-[52px] leading-tight"><?= $editar ? 'Editar medicamento' : 'Nuevo medicamento' ?></h1>
        <p class="text-[17px] text-[#3f5a44]">
            <?= $editar
                ? 'Modifique los datos de «' . esc($m['nombreComercial']) . '». Los cambios se verán de inmediato en el catálogo.'
                : 'Complete los datos del medicamento. Los campos con * son obligatorios.' ?>
        </p>
    </div>

    <form action="<?= $accion ?>" method="post" class="grid gap-6">
        <?= csrf_field() ?>

        <!-- 1. Datos -->
        <section class="seccion">
            <h2><span class="paso">1</span> Datos del medicamento</h2>
            <p class="sub">¿Qué medicamento es y quién lo fabrica?</p>

            <div class="grid md:grid-cols-2 gap-5 mb-5">
                <div>
                    <label class="campo" for="nombreComercial">Nombre comercial (marca) *</label>
                    <input id="nombreComercial" type="text" name="nombreComercial" value="<?= $val('nombreComercial') ?>" placeholder="Ej: Tylenol" required class="input">
                </div>
                <div>
                    <label class="campo" for="nombreGenerico">Nombre genérico (principio activo) *</label>
                    <input id="nombreGenerico" type="text" name="nombreGenerico" value="<?= $val('nombreGenerico') ?>" placeholder="Ej: Paracetamol" required class="input">
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-5 mb-5">
                <div>
                    <label class="campo" for="laboratorio">Laboratorio</label>
                    <input id="laboratorio" type="text" name="laboratorio" maxlength="100" value="<?= $val('laboratorio') ?>" placeholder="Ej: Laboratorios INTI" class="input">
                </div>
                <div>
                    <label class="campo" for="idCategoria">Categoría *</label>
                    <select id="idCategoria" name="idCategoria" required class="input">
                        <option value="">Seleccione una categoría...</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= esc($cat['idCategoria']) ?>" <?= (string) $catSel === (string) $cat['idCategoria'] ? 'selected' : '' ?>><?= esc($cat['nombreCategoria']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($categorias)): ?>
                        <p class="ayuda" style="color:#9b2c1e">Primero cree una categoría desde el inventario.</p>
                    <?php endif; ?>
                </div>
            </div>

            <label class="receta">
                <input type="checkbox" name="recetaMedica" value="1" <?= $conReceta ? 'checked' : '' ?>>
                <span>
                    <strong>Requiere receta médica</strong><br>
                    <span class="ayuda" style="margin:0">Se avisará al cliente que debe presentar su receta al recoger el medicamento.</span>
                </span>
            </label>
        </section>

        <!-- 2. Descripción -->
        <section class="seccion">
            <h2><span class="paso">2</span> Imagen e información para el cliente</h2>
            <p class="sub">Esto es lo que verán los clientes en el catálogo.</p>

            <div class="mb-5">
                <label class="campo" for="imagen">Enlace (URL) de la imagen *</label>
                <input id="imagen" type="url" name="imagen" value="<?= $val('imagen') ?>" placeholder="https://ejemplo.com/imagen.jpg" required class="input">
                <p class="ayuda">Copie el enlace de una imagen del producto. Debajo verá cómo queda.</p>
                <img id="vistaImagen" class="hidden" alt="Vista previa de la imagen" style="max-height:150px;border-radius:12px;margin-top:10px;border:2px solid #c9dccb;background:#fff">
            </div>

            <div class="grid md:grid-cols-2 gap-5 mb-5">
                <div>
                    <label class="campo" for="descripcion">Descripción *</label>
                    <textarea id="descripcion" name="descripcion" rows="3" required placeholder="Breve descripción del producto" class="input"><?= $val('descripcion') ?></textarea>
                </div>
                <div>
                    <label class="campo" for="indicaciones">¿Para qué sirve? (indicaciones) *</label>
                    <textarea id="indicaciones" name="indicaciones" rows="3" required placeholder="Ej: Alivio del dolor leve a moderado y la fiebre" class="input"><?= $val('indicaciones') ?></textarea>
                </div>
            </div>

            <div class="mb-5">
                <label class="campo" for="efectosSecundarios">Efectos secundarios</label>
                <textarea id="efectosSecundarios" name="efectosSecundarios" rows="2" placeholder="Ej: Náuseas, mareos. Consulte a su médico si persisten" class="input"><?= $val('efectosSecundarios') ?></textarea>
            </div>

            <div class="grid md:grid-cols-2 gap-5">
                <div>
                    <label class="campo" for="viaAdministrativa">¿Cómo se toma? (vía de administración)</label>
                    <input id="viaAdministrativa" type="text" name="viaAdministrativa" value="<?= $val('viaAdministrativa') ?>" placeholder="Ej: Oral" class="input">
                </div>
                <div>
                    <label class="campo" for="dosis">Dosis recomendada</label>
                    <input id="dosis" type="text" name="dosis" value="<?= $val('dosis') ?>" placeholder="Ej: 500 mg cada 8 horas" class="input">
                </div>
            </div>
        </section>

        <!-- 3. Precio y stock -->
        <section class="seccion">
            <h2><span class="paso">3</span> Precio y control de stock</h2>
            <p class="sub">Cuánto cuesta y cuándo debe avisarle el sistema que hay que reponer.</p>

            <div class="grid md:grid-cols-2 gap-5 mb-5">
                <div>
                    <label class="campo" for="precioUnitario">Precio por unidad (Bs) *</label>
                    <input id="precioUnitario" type="number" step="0.01" min="0" name="precioUnitario" value="<?= $val('precioUnitario') ?>" placeholder="0.00" required class="input">
                </div>
                <div>
                    <?php if ($editar): ?>
                        <label class="campo">Unidades en inventario</label>
                        <div class="input solo-lectura"><?= (int) $m['stockActual'] ?> unidades</div>
                        <p class="ayuda">Para sumar unidades use «Agregar stock» en el inventario: así queda registrado el lote y su fecha de vencimiento.</p>
                    <?php else: ?>
                        <label class="campo" for="stockActual">Unidades que hay ahora *</label>
                        <input id="stockActual" type="number" min="0" name="stockActual" value="<?= $val('stockActual') ?>" placeholder="Ej: 100" required class="input">
                        <p class="ayuda">Si es mayor a 0, complete también el lote (paso 4).</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-5">
                <div>
                    <label class="campo" for="rop">Avisar cuando queden (stock mínimo) *</label>
                    <input id="rop" type="number" min="0" name="rop" value="<?= $val('rop') ?>" placeholder="Ej: 20" required class="input">
                    <p class="ayuda">Cuando las unidades lleguen a este número o menos, el medicamento se marca como <strong>stock bajo</strong>. <span class="tecnico">(Punto de reorden, ROP)</span></p>
                </div>
                <div>
                    <label class="campo" for="eoq">Cantidad sugerida para reponer *</label>
                    <input id="eoq" type="number" min="0" name="eoq" value="<?= $val('eoq') ?>" placeholder="Ej: 100" required class="input">
                    <p class="ayuda">Cuántas unidades conviene pedir al proveedor cada vez que se repone. <span class="tecnico">(Lote económico, EOQ)</span></p>
                </div>
            </div>
            <p id="ejemplo" class="ejemplo"></p>
        </section>

        <!-- 4. Primer lote (solo al crear) -->
        <?php if (!$editar): ?>
        <section class="seccion">
            <h2><span class="paso">4</span> Primer lote</h2>
            <p class="sub">Cada ingreso de medicamento viene en un lote con su fecha de vencimiento. Es obligatorio si puso unidades en el paso 3.</p>
            <div class="grid md:grid-cols-2 gap-5">
                <div>
                    <label class="campo" for="numeroLote">Número de lote</label>
                    <input id="numeroLote" type="text" name="numeroLote" maxlength="50" value="<?= $val('numeroLote') ?>" placeholder="Ej: L2411-A" class="input">
                    <p class="ayuda">Lo encuentra impreso en la caja del medicamento.</p>
                </div>
                <div>
                    <label class="campo" for="fechaVencimiento">Fecha de vencimiento</label>
                    <input id="fechaVencimiento" type="date" name="fechaVencimiento" value="<?= $val('fechaVencimiento') ?>" class="input">
                    <p class="ayuda">Debe ser una fecha futura. Al llegar ese día el lote se retira solo de la venta.</p>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="btn"><?= $editar ? 'Guardar cambios' : 'Guardar medicamento' ?></button>
            <a href="/admin/medicamentos" class="btn btn-sec">Cancelar</a>
        </div>
    </form>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    const $ = id => document.getElementById(id);

    // Vista previa de la imagen
    const url = $('imagen'), prev = $('vistaImagen');
    prev.addEventListener('error', () => prev.classList.add('hidden'));
    function imagen() {
        const v = url.value.trim();
        if (v) { prev.src = v; prev.classList.remove('hidden'); } else { prev.classList.add('hidden'); }
    }
    url.addEventListener('input', imagen); imagen();

    // Frase de ejemplo para stock mínimo / cantidad a reponer
    const rop = $('rop'), eoq = $('eoq'), ej = $('ejemplo');
    function ejemplo() {
        const r = parseInt(rop.value, 10), e = parseInt(eoq.value, 10);
        const ok = !isNaN(r) && !isNaN(e);
        ej.classList.toggle('visible', ok);
        if (ok) ej.textContent = 'Ejemplo: cuando queden ' + r + ' unidades o menos aparecerá como «stock bajo» y se le sugerirá pedir ' + e + ' unidades al proveedor.';
    }
    rop.addEventListener('input', ejemplo); eoq.addEventListener('input', ejemplo); ejemplo();

    // Lote obligatorio solo si hay unidades (formulario de creación)
    const stock = document.querySelector('[name="stockActual"]');
    const lote = $('numeroLote'), venc = $('fechaVencimiento');
    if (stock && lote && venc) {
        const m = new Date(); m.setDate(m.getDate() + 1);
        venc.min = [m.getFullYear(), String(m.getMonth() + 1).padStart(2, '0'), String(m.getDate()).padStart(2, '0')].join('-');
        function ajustar() { const req = (parseInt(stock.value, 10) || 0) > 0; lote.required = req; venc.required = req; }
        stock.addEventListener('input', ajustar); ajustar();
    }
})();
</script>
<?= $this->endSection() ?>