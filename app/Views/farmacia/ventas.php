<?= $this->extend('layouts/staff') ?>

<?= $this->section('contenido') ?>
<div class="mb-6">
    <span class="eyebrow">Mostrador</span>
    <h1 class="serif text-[44px] leading-tight">Venta en mostrador</h1>
    <p class="text-[#555]">Agregue los productos que pide el cliente al carrito y cobre todo en una sola venta.</p>
</div>

<div class="grid gap-6 lg:grid-cols-[1fr_390px] items-start">

    <!-- Productos -->
    <div>
        <div class="flex flex-wrap gap-2 mb-4">
            <button type="button" class="chip activo" data-cat="">Todas</button>
            <?php foreach ($categorias as $c): ?>
                <button type="button" class="chip" data-cat="<?= (int) $c['idCategoria'] ?>"><?= esc($c['nombreCategoria']) ?></button>
            <?php endforeach; ?>
        </div>
        <input id="buscar" type="search" class="input mb-5" placeholder="Buscar por nombre comercial o genérico…" autocomplete="off">

        <style>
            /* Tarjeta de producto: borde verde fino para separarla del fondo y de las demás */
            .med-card { background: var(--cream); border: 1.5px solid #7fae88; border-radius: 16px; overflow: hidden;
                        display: flex; flex-direction: column; box-shadow: 0 1px 3px rgba(15, 62, 23, .08); transition: border-color .15s, box-shadow .15s; }
            .med-card:hover { border-color: var(--forest); box-shadow: 0 4px 14px rgba(15, 62, 23, .12); }
            .med-head { background: var(--keylime); padding: 16px 20px; border-bottom: 1px solid #c9dccb; }
            .med-body { padding: 16px 20px; display: flex; flex-direction: column; gap: 14px; flex: 1; }
            .med-datos { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 20px; }
            .med-datos dt { font-size: 11px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: #4d6b52; margin-bottom: 2px; }
            .med-datos dd { font-size: 14px; color: var(--charcoal); line-height: 1.3; }
            .med-pie { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 20px; border-top: 1px solid #c9dccb; background: #f6fbf5; }
        </style>

        <div id="productos" class="grid xl:grid-cols-2 gap-5">
            <?php foreach ($medicamentos as $m):
                $venc  = $vencimientos[$m['idMedicamento']] ?? null;
                $stock = (int) $m['stockActual'];
                $conReceta = (int) $m['recetaMedica'] === 1;
                // Campos opcionales: se muestran solo si existen en la tabla y tienen valor
                $presentacion  = trim((string) ($m['presentacion'] ?? ''));
                $concentracion = trim((string) ($m['concentracion'] ?? ''));
                $laboratorio   = trim((string) ($m['laboratorio'] ?? ''));
                $descripcion   = trim((string) ($m['descripcion'] ?? ''));
            ?>
                <article class="med-card" data-med
                     data-id="<?= (int) $m['idMedicamento'] ?>"
                     data-nombre="<?= esc($m['nombreComercial']) ?>"
                     data-precio="<?= esc($m['precioUnitario']) ?>"
                     data-stock="<?= $stock ?>"
                     data-receta="<?= (int) $m['recetaMedica'] ?>"
                     data-cat="<?= (int) $m['idCategoria'] ?>"
                     data-buscar="<?= esc($m['nombreComercial'] . ' ' . $m['nombreGenerico'] . ' ' . $m['nombreCategoria'] . ' ' . $laboratorio) ?>">

                    <header class="med-head">
                        <span class="eyebrow"><?= esc($m['nombreCategoria']) ?></span>
                        <h3 class="text-[19px] font-semibold leading-tight mt-1" style="color: var(--forest);"><?= esc($m['nombreComercial']) ?></h3>
                        <p class="text-[13px] text-[#4d6b52]"><?= esc($m['nombreGenerico']) ?></p>
                    </header>

                    <div class="med-body">
                        <div class="flex flex-wrap gap-2">
                            <?php if ($conReceta): ?>
                                <span class="badge badge-aviso">Requiere receta médica</span>
                            <?php else: ?>
                                <span class="badge badge-ok">Venta libre</span>
                            <?php endif; ?>
                            <?php if ($stock <= 0): ?>
                                <span class="badge badge-bajo">Agotado</span>
                            <?php elseif ($stock <= 10): ?>
                                <span class="badge badge-bajo">Stock bajo</span>
                            <?php endif; ?>
                        </div>

                        <dl class="med-datos">
                            <div>
                                <dt>Principio activo</dt>
                                <dd><?= esc($m['nombreGenerico']) ?></dd>
                            </div>
                            <div>
                                <dt>Categoría</dt>
                                <dd><?= esc($m['nombreCategoria']) ?></dd>
                            </div>
                            <?php if ($presentacion !== ''): ?>
                                <div><dt>Presentación</dt><dd><?= esc($presentacion) ?></dd></div>
                            <?php endif; ?>
                            <?php if ($concentracion !== ''): ?>
                                <div><dt>Concentración</dt><dd><?= esc($concentracion) ?></dd></div>
                            <?php endif; ?>
                            <?php if ($laboratorio !== ''): ?>
                                <div><dt>Laboratorio</dt><dd><?= esc($laboratorio) ?></dd></div>
                            <?php endif; ?>
                            <div>
                                <dt>Vencimiento</dt>
                                <dd><?= $venc ? esc(date('d/m/Y', strtotime($venc['fecha']))) : '—' ?></dd>
                            </div>
                        </dl>

                        <?php if ($descripcion !== ''): ?>
                            <p class="text-[13px] text-[#444] leading-relaxed"><?= esc($descripcion) ?></p>
                        <?php endif; ?>
                    </div>

                    <footer class="med-pie">
                        <div>
                            <p class="serif text-[28px] leading-none">Bs. <?= number_format($m['precioUnitario'], 2) ?></p>
                            <p class="text-[13px] text-[#4d6b52] mt-1">Disponible: <strong data-disp><?= $stock ?></strong> unid.</p>
                        </div>
                        <button type="button" class="btn" data-agregar>Agregar</button>
                    </footer>
                </article>
            <?php endforeach; ?>
        </div>
        <p id="sinResultados" class="card text-center text-[#555] hidden mt-4">No hay productos que coincidan.</p>
    </div>

    <!-- Carrito -->
    <div class="card lg:sticky lg:top-6">
        <div class="flex items-center justify-between mb-3">
            <h2 class="serif text-[28px] leading-tight">Venta actual</h2>
            <button type="button" id="vaciar" class="btn btn-sec btn-mini">Vaciar</button>
        </div>

        <p id="carritoVacio" class="text-[#4d6b52] py-6 text-center">Aún no hay productos.</p>
        <ul id="lista" class="divide-y divide-[#c9dccb] mb-4"></ul>

        <div class="flex items-center justify-between border-t border-[#c9dccb] pt-3 mb-3">
            <span class="eyebrow">Total</span>
            <span id="total" class="serif text-[34px] leading-none">Bs. 0.00</span>
        </div>

        <div class="grid grid-cols-2 gap-3 mb-4">
            <div>
                <label class="campo" for="recibido">Monto recibido</label>
                <input id="recibido" type="number" min="0" step="0.01" class="input" placeholder="0.00">
            </div>
            <div>
                <label class="campo">Cambio</label>
                <p id="cambio" class="input" style="background:transparent;">—</p>
            </div>
        </div>

        <button type="button" id="cobrar" class="btn w-full" disabled>Cobrar</button>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    const tarjetas = [...document.querySelectorAll('[data-med]')];
    const info = {};          // id -> datos del producto
    const carrito = new Map(); // id -> cantidad
    tarjetas.forEach(t => {
        info[t.dataset.id] = {
            id: t.dataset.id, nombre: t.dataset.nombre, precio: parseFloat(t.dataset.precio),
            stock: parseInt(t.dataset.stock, 10), receta: t.dataset.receta === '1', el: t
        };
    });

    const $ = id => document.getElementById(id);
    const bs = n => 'Bs. ' + n.toFixed(2);
    let categoria = '';

    /* ---- Filtros ---- */
    function filtrar() {
        const q = window.norm($('buscar').value);
        let visibles = 0;
        tarjetas.forEach(t => {
            const ok = (!categoria || t.dataset.cat === categoria) && (!q || window.norm(t.dataset.buscar).includes(q));
            t.classList.toggle('hidden', !ok);
            if (ok) visibles++;
        });
        $('sinResultados').classList.toggle('hidden', visibles > 0);
    }
    $('buscar').addEventListener('input', filtrar);
    document.querySelectorAll('.chip').forEach(c => c.addEventListener('click', () => {
        document.querySelectorAll('.chip').forEach(x => x.classList.remove('activo'));
        c.classList.add('activo'); categoria = c.dataset.cat; filtrar();
    }));

    /* ---- Carrito ---- */
    function pintarTarjetas() {
        Object.values(info).forEach(p => {
            const enCarrito = carrito.get(p.id) || 0;
            p.el.querySelector('[data-disp]').textContent = Math.max(p.stock - enCarrito, 0);
            p.el.querySelector('[data-agregar]').disabled = p.stock - enCarrito <= 0;
        });
    }

    function pintar() {
        const ul = $('lista'); ul.innerHTML = '';
        let total = 0;
        carrito.forEach((cant, id) => {
            const p = info[id]; total += cant * p.precio;
            const li = document.createElement('li');
            li.className = 'py-3 flex flex-col gap-2';
            li.innerHTML = '<div class="flex justify-between gap-2"><span class="font-medium leading-tight"></span><span class="font-semibold whitespace-nowrap"></span></div>' +
                '<div class="flex items-center justify-between"><div class="flex items-center gap-2">' +
                '<button type="button" class="btn btn-sec btn-mini" data-menos aria-label="Quitar uno">−</button>' +
                '<input type="number" min="1" class="input input-mini" style="width:70px;text-align:center" data-cant>' +
                '<button type="button" class="btn btn-sec btn-mini" data-mas aria-label="Agregar uno">+</button></div>' +
                '<button type="button" class="btn btn-peligro btn-mini" data-quitar>Quitar</button></div>';
            li.querySelector('.font-medium').textContent = p.nombre + (p.receta ? ' (receta)' : '');
            li.querySelector('.font-semibold').textContent = bs(cant * p.precio);
            const inp = li.querySelector('[data-cant]'); inp.value = cant; inp.max = p.stock;
            li.querySelector('[data-menos]').onclick = () => cambiar(id, cant - 1);
            li.querySelector('[data-mas]').onclick = () => cambiar(id, cant + 1);
            li.querySelector('[data-quitar]').onclick = () => cambiar(id, 0);
            inp.onchange = () => cambiar(id, parseInt(inp.value, 10) || 0);
            ul.appendChild(li);
        });
        $('carritoVacio').classList.toggle('hidden', carrito.size > 0);
        $('total').textContent = bs(total);
        $('cobrar').disabled = carrito.size === 0;
        calcularCambio(total);
        pintarTarjetas();
    }

    function cambiar(id, cant) {
        const p = info[id];
        if (cant > p.stock) { cant = p.stock; window.toast('Solo hay ' + p.stock + ' de ' + p.nombre + '.', true); }
        if (cant <= 0) carrito.delete(id); else carrito.set(id, cant);
        pintar();
    }

    function totalActual() { let t = 0; carrito.forEach((c, id) => t += c * info[id].precio); return t; }
    function calcularCambio(total) {
        const r = parseFloat($('recibido').value);
        $('cambio').textContent = isNaN(r) ? '—' : (r >= total ? bs(r - total) : 'Falta ' + bs(total - r));
    }
    $('recibido').addEventListener('input', () => calcularCambio(totalActual()));

    tarjetas.forEach(t => t.querySelector('[data-agregar]').addEventListener('click', () => {
        const id = t.dataset.id; cambiar(id, (carrito.get(id) || 0) + 1);
    }));
    $('vaciar').addEventListener('click', () => { carrito.clear(); $('recibido').value = ''; pintar(); });

    /* ---- Cobrar ---- */
    $('cobrar').addEventListener('click', async () => {
        if ([...carrito.keys()].some(id => info[id].receta) &&
            !confirm('Hay medicamentos que requieren receta médica. ¿El cliente presentó su receta?')) return;

        const boton = $('cobrar'); boton.disabled = true;
        const fd = new FormData();
        fd.append(window.CSRF.n, window.CSRF.v);
        fd.append('items', JSON.stringify([...carrito].map(([id, cantidad]) => ({ id: parseInt(id, 10), cantidad }))));

        try {
            const r = await fetch('/farmacia/ventas/cobrar', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const d = await r.json();
            if (d.csrf) window.CSRF.v = d.csrf;
            if (d.stock && typeof d.stock === 'object') {
                Object.entries(d.stock).forEach(([id, s]) => { if (info[id]) info[id].stock = s; });
            }
            if (d.ok) { carrito.clear(); $('recibido').value = ''; }
            window.toast(d.mensaje, !d.ok);
        } catch (e) {
            window.toast('No se pudo conectar con el servidor.', true);
        }
        pintar();
    });

    pintar();
})();
</script>
<?= $this->endSection() ?>