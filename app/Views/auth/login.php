<?php $e = static fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8'); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - StockMed</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f5f1e4; color: #2c2e2a; }
    </style>
</head>
<body class="antialiased min-h-screen flex flex-col">

    <?= $this->include('partials/fondo') ?>

    <div class="contenido flex-1 flex flex-col">

        <header class="w-full px-4 pt-4">
            <div class="max-w-[1200px] mx-auto flex items-center justify-between gap-3">
                <a href="/" class="bg-white rounded-[50px] pl-3 pr-6 py-2.5 shadow-sm border border-[#d5d5d4] flex items-center gap-2.5">
                    <span class="w-9 h-9 rounded-[10px] bg-[#8ed462] flex items-center justify-center">
                        <svg width="20" height="20" viewBox="0 0 24 24"><path d="M9 3h6v6h6v6h-6v6H9v-6H3V9h6z" fill="#fff"/></svg>
                    </span>
                    <span class="font-semibold text-[17px] tracking-tight">StockMed</span>
                </a>
                <a href="/" class="bg-white rounded-[50px] pl-2 pr-6 py-2 shadow-sm border border-[#d5d5d4] flex items-center gap-3 text-[15px] font-medium hover:bg-[#f5f1e4] transition-colors">
                    <span class="w-9 h-9 rounded-full bg-[#8ed462] flex items-center justify-center">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#2c2e2a" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg>
                    </span>
                    Volver al catálogo
                </a>
            </div>
        </header>

        <main class="flex-1 flex items-center justify-center px-4 py-10">
            <div class="w-full max-w-md bg-white rounded-[50px] border border-[#e0dbce] shadow-sm px-8 sm:px-10 py-10">

                <div class="text-center mb-8">
                    <span class="inline-flex w-14 h-14 rounded-[16px] bg-[#8ed462] items-center justify-center mb-5">
                        <svg width="28" height="28" viewBox="0 0 24 24"><path d="M9 3h6v6h6v6h-6v6H9v-6H3V9h6z" fill="#fff"/></svg>
                    </span>
                    <h1 class="text-[40px] leading-[1.1] tracking-[-0.05em] font-medium">Iniciar sesión</h1>
                    <p class="mt-2 text-[15px] text-[#80827f]">Acceda para reservar sus medicamentos.</p>
                </div>

                <?php if (session()->has('mensaje')): ?>
                    <div class="mb-5 px-5 py-3 rounded-[24px] bg-[#8ed462]/15 border border-[#8ed462] text-[14px]"><?= $e(session()->get('mensaje')) ?></div>
                <?php endif; ?>
                <?php if (session()->has('error')): ?>
                    <div class="mb-5 px-5 py-3 rounded-[24px] bg-[#ff705d]/10 border border-[#ff705d]/40 text-[#c4402f] text-[14px]"><?= $e(session()->get('error')) ?></div>
                <?php endif; ?>

                <form action="/login/autenticar" method="post" class="space-y-4">
                    <?= function_exists('csrf_field') ? csrf_field() : '' ?>

                    <div>
                        <label for="correo" class="block text-sm font-medium mb-2 ml-2">Correo electrónico</label>
                        <input id="correo" type="email" name="correo" required autocomplete="email" placeholder="correo@gmail.com"
                               class="w-full px-6 py-3.5 rounded-[50px] bg-[#f5f1e4] border border-[#d5d5d4] outline-none placeholder:text-[#80827f] focus:border-[#8ed462] focus:bg-white transition-colors">
                    </div>

                    <div>
                        <label for="contrasena" class="block text-sm font-medium mb-2 ml-2">Contraseña</label>
                        <div class="relative">
                            <input id="contrasena" type="password" name="contrasena" required autocomplete="current-password"
                                   class="w-full pl-6 pr-24 py-3.5 rounded-[50px] bg-[#f5f1e4] border border-[#d5d5d4] outline-none focus:border-[#8ed462] focus:bg-white transition-colors">
                            <button type="button" id="verClave" class="absolute right-2 top-1/2 -translate-y-1/2 px-4 py-2 rounded-[50px] text-xs font-medium text-[#80827f] hover:bg-[#e0dbce] transition-colors">Mostrar</button>
                        </div>
                    </div>

                    <button type="submit" class="w-full mt-2 py-3.5 px-4 rounded-[50px] text-[16px] font-medium bg-[#2c2e2a] text-white hover:bg-black transition-colors flex items-center justify-center gap-2">
                        Ingresar
                        <span class="w-2 h-2 rounded-full bg-[#8ed462] inline-block"></span>
                    </button>
                </form>

                <p class="mt-6 text-center text-[14px] text-[#80827f]">
                    ¿No tiene cuenta?
                    <a href="/registro" class="font-medium text-[#2c2e2a] underline decoration-[#8ed462] decoration-2 underline-offset-4">Regístrese aquí</a>
                </p>
            </div>
        </main>

        <footer class="w-full bg-[#f5e211] py-6 text-center border-t border-[#e0dbce]">
            <p class="text-[14px] font-medium">StockMed Online &mdash; Sistema Automatizado de Control de Inventario y Farmacia.</p>
        </footer>
    </div>

    <script>
        const clave = document.getElementById('contrasena'), ver = document.getElementById('verClave');
        ver.addEventListener('click', () => {
            const oculto = clave.type === 'password';
            clave.type = oculto ? 'text' : 'password';
            ver.textContent = oculto ? 'Ocultar' : 'Mostrar';
        });
    </script>
</body>
</html>