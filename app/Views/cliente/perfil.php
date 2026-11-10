<?php
$e = static fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$editando = old('nombre') !== null;                // tras un error de validación, se reabre el formulario
$val = static fn($campo, $actual) => $e(old($campo) ?? $actual);
$esCliente = (int) $persona['idRol'] === 1;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi perfil - StockMed</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f5f1e4; color: #2c2e2a; }
        dialog { border: none; border-radius: 30px; padding: 0; max-width: 440px; width: calc(100% - 32px); }
        dialog::backdrop { background: rgba(44, 46, 42, .45); }
    </style>
</head>
<body class="antialiased min-h-screen flex flex-col">

    <?= $this->include('partials/fondo') ?>

    <div class="contenido flex-1 flex flex-col">

        <header class="sticky top-0 z-50 w-full px-4 pt-4">
            <div class="max-w-[1200px] mx-auto flex items-center justify-between gap-3">
                <nav class="bg-white rounded-[50px] pl-3 pr-6 py-2.5 shadow-sm border border-[#d5d5d4] flex items-center gap-6">
                    <a href="/" class="flex items-center gap-2.5">
                        <span class="w-9 h-9 rounded-[10px] bg-[#8ed462] flex items-center justify-center">
                            <svg width="20" height="20" viewBox="0 0 24 24"><path d="M9 3h6v6h6v6h-6v6H9v-6H3V9h6z" fill="#fff"/></svg>
                        </span>
                        <span class="font-semibold text-[17px] tracking-tight">StockMed</span>
                    </a>
                    <div class="hidden md:flex items-center gap-5 text-[15px]">
                        <a href="/" class="hover:text-[#80827f] transition-colors">Catálogo</a>
                        <a href="/mis-reservas" class="hover:text-[#80827f] transition-colors">Mis reservas</a>
                        <a href="/perfil" class="font-medium underline decoration-[#8ed462] decoration-2 underline-offset-8">Mi perfil</a>
                    </div>
                </nav>
                <div class="bg-white rounded-[50px] pl-6 pr-2 py-2 shadow-sm border border-[#d5d5d4] flex items-center gap-3">
                    <span class="hidden sm:inline text-sm font-medium text-[#80827f]">Hola, <?= $e(session()->get('nombre')) ?></span>
                    <a href="/logout" class="px-4 py-2 rounded-[50px] text-sm font-medium bg-[#2c2e2a] text-white hover:bg-black transition-colors">Salir</a>
                </div>
            </div>
        </header>

        <main class="max-w-[1200px] mx-auto px-4 w-full">

            <section class="mt-6 bg-[#8ed462] rounded-[50px] px-6 md:px-12 py-12 text-center">
                <h1 class="text-[clamp(36px,6vw,64px)] leading-[1.05] tracking-[-0.05em] font-medium">Mi perfil</h1>
                <p class="mt-3 text-[17px] text-[#2c2e2a]/80 max-w-xl mx-auto">Revise y actualice sus datos de contacto.</p>
            </section>

            <section class="mt-10 mb-16 max-w-[640px] mx-auto">

                <?php if (session()->has('mensaje')): ?>
                    <div class="mb-6 px-6 py-4 rounded-[30px] bg-white border border-[#8ed462] text-[15px] flex items-start gap-3">
                        <span class="mt-1.5 w-2.5 h-2.5 rounded-full bg-[#8ed462] flex-none"></span><span><?= $e(session()->get('mensaje')) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (session()->has('error')): ?>
                    <div class="mb-6 px-6 py-4 rounded-[30px] bg-white border border-[#ff705d] text-[15px] flex items-start gap-3">
                        <span class="mt-1.5 w-2.5 h-2.5 rounded-full bg-[#ff705d] flex-none"></span><span><?= $e(session()->get('error')) ?></span>
                    </div>
                <?php endif; ?>

                <!-- Vista: datos -->
                <article id="vista" class="bg-white rounded-[30px] p-6 shadow-sm border border-[#e0dbce] <?= $editando ? 'hidden' : '' ?>">
                    <dl class="divide-y divide-[#e0dbce]">
                        <?php foreach ([
                            'Nombre'   => $persona['nombre'],
                            'Apellido' => $persona['apellido'],
                            'Carnet de identidad' => $persona['ci'],
                            'Teléfono' => $persona['telefono'],
                            'Correo electrónico' => $persona['correo'],
                        ] as $etiqueta => $valor): ?>
                            <div class="py-3 flex items-center justify-between gap-4">
                                <dt class="text-[13px] text-[#80827f]"><?= $e($etiqueta) ?></dt>
                                <dd class="text-[15px] font-medium text-right break-all"><?= $e($valor) ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                    <button type="button" id="btnEditar" class="mt-5 w-full py-3 px-4 rounded-[50px] text-[15px] font-medium bg-[#2c2e2a] text-white hover:bg-[#8ed462] hover:text-[#2c2e2a] transition-all">Editar</button>
                </article>

                <!-- Edición -->
                <form id="edicion" method="post" action="/perfil/guardar" class="bg-white rounded-[30px] p-6 shadow-sm border border-[#e0dbce] <?= $editando ? '' : 'hidden' ?>">
                    <?= csrf_field() ?>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label for="nombre" class="block text-[13px] font-medium mb-1">Nombre</label>
                            <input id="nombre" name="nombre" required maxlength="60" value="<?= $val('nombre', $persona['nombre']) ?>" class="w-full rounded-[16px] border border-[#d5d5d4] px-4 py-2.5 text-[15px] outline-none focus:border-[#2c2e2a]">
                        </div>
                        <div>
                            <label for="apellido" class="block text-[13px] font-medium mb-1">Apellido</label>
                            <input id="apellido" name="apellido" required maxlength="60" value="<?= $val('apellido', $persona['apellido']) ?>" class="w-full rounded-[16px] border border-[#d5d5d4] px-4 py-2.5 text-[15px] outline-none focus:border-[#2c2e2a]">
                        </div>
                    </div>
                    <div class="mt-4">
                        <label for="telefono" class="block text-[13px] font-medium mb-1">Teléfono</label>
                        <input id="telefono" name="telefono" type="tel" required value="<?= $val('telefono', $persona['telefono']) ?>" class="w-full rounded-[16px] border border-[#d5d5d4] px-4 py-2.5 text-[15px] outline-none focus:border-[#2c2e2a]">
                    </div>
                    <div class="mt-4">
                        <label for="correo" class="block text-[13px] font-medium mb-1">Correo electrónico</label>
                        <input id="correo" name="correo" type="email" required maxlength="100" value="<?= $val('correo', $persona['correo']) ?>" class="w-full rounded-[16px] border border-[#d5d5d4] px-4 py-2.5 text-[15px] outline-none focus:border-[#2c2e2a]">
                    </div>
                    <p class="mt-4 text-xs text-[#80827f]">El carnet de identidad no se puede modificar. Si necesita corregirlo, comuníquese con la farmacia.</p>
                    <div class="mt-5 flex gap-3">
                        <button type="button" id="btnCancelar" class="flex-1 py-3 px-4 rounded-[50px] text-[15px] font-medium bg-[#f5f1e4] border border-[#2c2e2a] hover:bg-[#e0dbce] transition-all">Cancelar</button>
                        <button type="submit" class="flex-1 py-3 px-4 rounded-[50px] text-[15px] font-medium bg-[#2c2e2a] text-white hover:bg-[#8ed462] hover:text-[#2c2e2a] transition-all">Guardar cambios</button>
                    </div>
                </form>

                <?php if ($esCliente): ?>
                    <div class="mt-8 text-center">
                        <button type="button" id="btnEliminar" class="text-[14px] text-[#ff705d] underline underline-offset-4 hover:text-[#d9503e]">Eliminar mi cuenta</button>
                    </div>
                <?php endif; ?>
            </section>
        </main>

        <footer class="w-full bg-[#f5e211] py-8 text-center mt-auto border-t border-[#e0dbce]">
            <div class="max-w-[1200px] mx-auto px-4">
                <?= $this->include('partials/ubicacion') ?>
                <p class="text-[15px] font-medium">StockMed Online &mdash; Sistema Automatizado de Control de Inventario y Farmacia.</p>
            </div>
        </footer>
    </div>

    <?php if ($esCliente): ?>
    <dialog id="dlgEliminar">
        <form method="post" action="/perfil/eliminar" class="p-7">
            <?= csrf_field() ?>
            <h2 class="text-[22px] font-medium leading-tight">¿Eliminar su cuenta?</h2>
            <p class="mt-2 text-[14px] text-[#555]">Esta acción no se puede deshacer. Sus reservas pendientes se cancelarán y ya no podrá iniciar sesión con esta cuenta.</p>
            <label for="clave" class="block text-[13px] font-medium mt-5 mb-1">Escriba su contraseña para confirmar</label>
            <input id="clave" name="contrasena" type="password" required autocomplete="current-password" class="w-full rounded-[16px] border border-[#d5d5d4] px-4 py-2.5 text-[15px] outline-none focus:border-[#2c2e2a]">
            <div class="mt-6 flex gap-3">
                <button type="button" onclick="this.closest('dialog').close()" class="flex-1 py-3 rounded-[50px] text-[15px] font-medium bg-[#f5f1e4] border border-[#2c2e2a]">Cancelar</button>
                <button type="submit" class="flex-1 py-3 rounded-[50px] text-[15px] font-medium bg-[#ff705d] text-white hover:bg-[#d9503e]">Eliminar cuenta</button>
            </div>
        </form>
    </dialog>
    <?php endif; ?>

    <script>
        const vista = document.getElementById('vista'), edicion = document.getElementById('edicion');
        function modo(editar) { vista.classList.toggle('hidden', editar); edicion.classList.toggle('hidden', !editar); }
        document.getElementById('btnEditar').addEventListener('click', () => modo(true));
        document.getElementById('btnCancelar').addEventListener('click', () => { edicion.reset(); modo(false); });
        const eliminar = document.getElementById('btnEliminar');
        if (eliminar) eliminar.addEventListener('click', () => document.getElementById('dlgEliminar').showModal());
    </script>
</body>
</html>