<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Iniciar sesión | {{ config('app.name') }}</title>

    <link rel="icon" href="{{ asset('image/LogoLaCeibaPNG.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-dvh bg-[#F3F7F6] text-[#243746] antialiased" style="font-family: 'Segoe UI', sans-serif;">
    <main data-auth-actions class="flex min-h-dvh flex-col items-center justify-center px-4 py-6 sm:px-8 sm:py-10 lg:px-12">
        <div class="grid w-full max-w-6xl overflow-hidden rounded-3xl border border-[#DCE6E3] bg-white shadow-[0_24px_80px_-32px_rgba(23,60,66,0.25)] lg:grid-cols-[1.08fr_1fr]">
            <section aria-labelledby="system-title" class="relative flex flex-col border-b border-[#DCE6E3] px-6 py-7 sm:px-10 lg:border-r lg:border-b-0 lg:px-12 lg:py-10">


                <div class="mt-7 flex flex-col gap-3 lg:mt-10">
                    <h1 id="system-title" class="text-3xl leading-[1.12] font-semibold tracking-tight text-[#173C66] sm:text-3xl lg:text-4xl text-center">
                        Sistema de Inventario y Servicios Técnicos
                    </h1>
                    <p class="text-base font-medium text-[#007F78] sm:text-lg text-center">Departamento de Informática</p>
                </div>

                <div class="hidden flex-1 items-center justify-center py-9 lg:flex">
                    <img
                        src="{{ asset('image/PortadaLaCeiba.jpg') }}"
                        alt="La Ceiba, la novia de Honduras"
                        width="508"
                        height="393"
                        class="h-auto w-full max-w-[320px] object-contain">
                </div>

                <p class="hidden border-t border-[#E5ECE9] pt-5 text-sm leading-relaxed text-[#526871] lg:block text-center">
                    Gestión de equipos y recursos tecnológicos municipales.
                </p>
            </section>

            <section aria-labelledby="login-title" class="flex items-center bg-[#F8FAF9] px-6 py-9 sm:px-10 sm:py-12 lg:px-14 lg:py-16">
                <div class="mx-auto flex w-full max-w-sm flex-col gap-8">
                    <div class="flex flex-col gap-3 items-center">
                        <header class="flex items-center gap-4">
                            <img
                                src="{{ asset('image/LogoLaCeibaPNG.png') }}"
                                alt="Escudo de La Ceiba"
                                width="447"
                                height="447"
                                class="size-16 shrink-0 object-contain sm:size-24">
                            <div class="flex flex-col gap-1">
                                <p class="text-base font-semibold leading-snug text-[#173C66] sm:text-xl">Municipalidad de La Ceiba</p>
                                <p class="text-sm text-[#526871]">Atlántida, Honduras</p>
                            </div>
                        </header>
                    </div>

                    <div class="grid grid-cols-1 gap-3">
                        <h2 id="login-title" class="text-3xl font-semibold tracking-tight text-[#173C66]">Bienvenido</h2>
                        <p class="text-sm leading-relaxed text-[#526871] sm:text-base">Ingresa a tu cuenta para acceder al sistema.</p>
                    </div>

                    @if (session('status'))
                    <div role="status" class="rounded-xl border border-[#BBCDC7] bg-white px-4 py-3 text-sm text-[#006D67]">{{ session('status') }}</div>
                    @endif

                    @if ($errors->any())
                    <div id="login-error" role="alert" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm leading-relaxed text-red-800">
                        {{ $errors->first() }}
                    </div>
                    @endif

                    <form data-auth-form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-5">
                        @csrf

                        <div class="flex flex-col gap-2">
                            <label for="email" class="text-sm font-semibold">Correo electrónico</label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                value="{{ old('email') }}"
                                autocomplete="username"
                                autocapitalize="none"
                                spellcheck="false"
                                placeholder="Ingresa tu correo electrónico"
                                required
                                @if ($errors->any()) aria-describedby="login-error" @endif
                            class="min-h-12 w-full rounded-xl border border-[#BBCDC7] bg-white px-4 py-3 text-base text-[#243746] outline-none placeholder:text-sm placeholder:text-[#657974] focus:border-[#007F78] focus:ring-3 focus:ring-[#007F78]/15"
                            >
                        </div>

                        <div class="flex flex-col gap-2">
                            <label for="password" class="text-sm font-semibold">Contraseña</label>
                            <div class="relative">
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    autocomplete="current-password"
                                    placeholder="Ingresa tu contraseña"
                                    required
                                    @if ($errors->any()) aria-describedby="login-error" @endif
                                class="min-h-12 w-full rounded-xl border border-[#BBCDC7] bg-white py-3 pr-14 pl-4 text-base text-[#243746] outline-none placeholder:text-sm placeholder:text-[#657974] focus:border-[#007F78] focus:ring-3 focus:ring-[#007F78]/15"
                                >
                                <button
                                    type="button"
                                    id="toggle-password"
                                    aria-label="Mostrar contraseña"
                                    aria-controls="password"
                                    aria-pressed="false"
                                    hidden
                                    class="absolute inset-y-1 right-1 flex w-11 cursor-pointer items-center justify-center rounded-lg text-[#526871] hover:bg-[#EAF3F0] hover:text-[#006D67] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#007F78]">
                                    <svg aria-hidden="true" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
                                        <circle cx="12" cy="12" r="3" />
                                        <path id="password-eye-slash" d="m3 3 18 18" style="display: none;" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <label for="remember" class="flex min-h-11 w-fit cursor-pointer items-center gap-3 text-sm text-[#526871]">
                            <input
                                type="checkbox"
                                id="remember"
                                name="remember"
                                @checked(old('remember'))
                                class="size-4 cursor-pointer accent-[#007F78] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#007F78]">
                            Recordarme
                        </label>

                        <button type="submit" data-loading-label="Ingresando…" class="flex items-center justify-center gap-2 disabled:cursor-not-allowed disabled:opacity-60 min-h-12 w-full cursor-pointer rounded-full bg-[#007F78] px-5 py-3.5 text-base font-semibold text-white motion-safe:transition-colors hover:bg-[#006D67] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#007F78] active:bg-[#005B56]">
                            <svg data-loading-indicator aria-hidden="true" class="hidden size-4 motion-safe:animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" opacity=".25" /><path d="M12 3a9 9 0 0 1 9 9" stroke="currentColor" stroke-width="3" /></svg>
                            <span data-button-label>Iniciar sesión</span>
                        </button>
                    </form>
                    <p data-submit-status role="status" class="sr-only"></p>

                    <div class="border-t border-[#DCE6E3] pt-6">
                        <a href="{{ route('password.request') }}"
                            class="block w-full rounded-full bg-[#F3F4F6] px-5 py-3
                                text-center text-sm font-medium text-[#34454D]
                                transition-all duration-200 ease-in-out
                                hover:bg-[#E5E7E9] hover:cursor-pointer
                                focus-visible:outline-2 focus-visible:outline-offset-2
                                focus-visible:outline-[#007F78]">
                            ¿Olvidaste la contraseña?
                        </a>
                    </div>
                </div>
            </section>
        </div>

        <footer class="pt-6 text-center text-xs leading-relaxed text-[#526871] sm:text-sm">
            Municipalidad de La Ceiba · Departamento de Informática
        </footer>
    </main>

    <script>
        const passwordInput = document.getElementById('password');
        const togglePassword = document.getElementById('toggle-password');
        const passwordEyeSlash = document.getElementById('password-eye-slash');

        togglePassword.hidden = false;
        togglePassword.addEventListener('click', () => {
            const showPassword = passwordInput.type === 'password';

            passwordInput.type = showPassword ? 'text' : 'password';
            togglePassword.setAttribute('aria-pressed', String(showPassword));
            togglePassword.setAttribute('aria-label', showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña');
            passwordEyeSlash.style.display = showPassword ? '' : 'none';
        });
    </script>
</body>

</html>
