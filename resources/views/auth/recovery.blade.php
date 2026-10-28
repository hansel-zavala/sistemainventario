<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar contraseña | {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('image/LogoLaCeibaPNG.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-dvh bg-[#F3F7F6] text-[#243746] antialiased" style="font-family: 'Segoe UI', sans-serif;">
    <main data-auth-actions class="flex min-h-dvh flex-col items-center justify-center gap-6 px-4 py-8 sm:px-8">
        <section aria-labelledby="recovery-title" class="flex w-full max-w-lg flex-col gap-6 rounded-3xl border border-[#DCE6E3] bg-white p-6 shadow-lg sm:p-10">
            <header class="flex items-center gap-4">
                <img src="{{ asset('image/LogoLaCeibaPNG.png') }}" alt="Escudo de La Ceiba" width="120" height="120" class="size-24 object-contain">
                <div>
                    <p class="font-semibold text-[#173C66] text-xl">Municipalidad de La Ceiba</p>
                    <p class="text-sm text-[#526871]">Departamento de Informática</p>
                </div>
            </header>
            <div class="flex flex-col gap-2">
                <p class="text-sm font-medium text-[#007F78]">Paso {{ ['email' => 1, 'code' => 2, 'password' => 3][$step] }} de 3</p>
                <h1 id="recovery-title" class="text-2xl font-semibold text-[#173C66]">{{ ['email' => 'Recuperar contraseña', 'code' => 'Ingresa tu código', 'password' => 'Crea tu nueva contraseña'][$step] }}</h1>
                <p class="text-sm leading-relaxed text-[#526871]">
                    {{ ['email' => 'Ingresa el correo de tu cuenta para recibir un código de recuperación.', 'code' => 'El código tiene 6 dígitos, vence en 10 minutos y permite un máximo de 5 intentos.', 'password' => 'Usa al menos 8 caracteres. Al guardar, deberás iniciar sesión nuevamente en tus dispositivos.'][$step] }}
                </p>
            </div>
            @if (session('status'))
            <div role="status" class="rounded-xl bg-[#EAF3F0] p-4 text-sm text-[#006D67]">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
            <div id="recovery-errors" role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $errors->first() }}</div>
            @endif
            <form data-auth-form method="POST" action="{{ route(['email' => 'password.email', 'code' => 'password.verify', 'password' => 'password.update'][$step]) }}" class="flex flex-col gap-5">
                @csrf
                @if ($step === 'email')
                <div class="flex flex-col gap-2">
                    <label for="email" class="text-sm font-semibold">Correo electrónico</label>
                    <input id="email" name="email" type="email" value="{{ old('email', session('password_recovery.email')) }}" autocomplete="email" required maxlength="255" @if ($errors->any()) aria-describedby="recovery-errors" @endif class="min-h-12 rounded-xl border border-[#BBCDC7] px-4 py-3 outline-none focus:border-[#007F78] focus:ring-3 focus:ring-[#007F78]/15" placeholder="Ingresa tu correo electrónico">
                </div>
                @elseif ($step === 'code')
                <div class="flex flex-col gap-2">
                    <label for="code" class="text-sm font-semibold">Código de verificación</label>
                    <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" required @if ($errors->any()) aria-describedby="recovery-errors" @endif class="min-h-12 rounded-xl border border-[#BBCDC7] px-4 py-3 text-center text-2xl tracking-widest outline-none focus:border-[#007F78] focus:ring-3 focus:ring-[#007F78]/15" placeholder="Ingresa tu código">
                </div>
                @else
                @foreach (['password' => 'Nueva contraseña', 'password_confirmation' => 'Confirmar contraseña'] as $name => $label)
                <div class="flex flex-col gap-2">
                    <label for="{{ $name }}" class="text-sm font-semibold">{{ $label }}</label>
                    <input id="{{ $name }}" name="{{ $name }}" type="password" autocomplete="new-password" minlength="8" maxlength="72" required @if ($errors->any()) aria-describedby="recovery-errors" @endif class="min-h-12 rounded-xl border border-[#BBCDC7] px-4 py-3 outline-none focus:border-[#007F78] focus:ring-3 focus:ring-[#007F78]/15">
                </div>
                @endforeach
                @endif
                <button type="submit" data-loading-label="{{ ['email' => 'Enviando…', 'code' => 'Verificando…', 'password' => 'Guardando…'][$step] }}" class="flex items-center justify-center gap-2 disabled:cursor-not-allowed disabled:opacity-60 min-h-12 cursor-pointer rounded-full bg-[#007F78] px-5 py-3 font-semibold text-white hover:bg-[#006D67] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#007F78]">
                    <svg data-loading-indicator aria-hidden="true" class="hidden size-4 motion-safe:animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" opacity=".25" /><path d="M12 3a9 9 0 0 1 9 9" stroke="currentColor" stroke-width="3" /></svg>
                    <span data-button-label>{{ ['email' => 'Enviar código', 'code' => 'Verificar código', 'password' => 'Guardar contraseña'][$step] }}</span>
                </button>
            </form>
            <p data-submit-status role="status" class="sr-only"></p>
            @if ($step === 'code')
            <form data-auth-form method="POST" action="{{ route('password.email') }}">
                @csrf
                <input type="hidden" name="email" value="{{ session('password_recovery.email') }}">
                <button type="submit" data-loading-label="Reenviando…" data-resend-seconds="{{ $resendSeconds }}" @disabled($resendSeconds > 0) class="flex w-full items-center justify-center gap-2
               rounded-full border border-[#DCE6E3]
               bg-white px-5 py-3
               text-sm font-semibold text-[#007F78]
               transition-colors duration-200
               hover:border-[#007F78] hover:bg-[#EAF5F3]
               focus-visible:outline-2
               focus-visible:outline-offset-2
               focus-visible:outline-[#007F78]
               disabled:cursor-not-allowed
                disabled:opacity-50">
                    <svg data-loading-indicator aria-hidden="true" class="hidden size-4 motion-safe:animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" opacity=".25" /><path d="M12 3a9 9 0 0 1 9 9" stroke="currentColor" stroke-width="3" /></svg>
                    <span data-button-label>{{ $resendSeconds > 0 ? "Reenviar código en {$resendSeconds} s" : 'Reenviar código' }}</span>
                </button>
                <p class="text-sm text-[#526871] text-center">Podrás solicitar otro código después de 60 segundos.</p>
                <noscript><p class="text-sm text-[#526871]">Actualiza esta página después de la espera para habilitar el reenvío.</p></noscript>
            </form>
            <a href="{{ route('password.request') }}" class="flex w-full items-center justify-center
              rounded-full px-5 py-3
              text-sm font-medium text-[#526871]
              transition-colors duration-200
              hover:bg-[#F3F4F6]
              hover:text-[#007F78]
              focus-visible:outline-2
              focus-visible:outline-offset-2
              focus-visible:outline-[#007F78]"> Usar otro correo</a>
            @endif

            <div class="border-t border-[#DCE6E3]"></div>

            <a href="{{ route('login') }}" class="block w-full rounded-full
                  bg-[#F3F4F6] px-5 py-3
                  text-center text-sm font-medium
                  text-[#34454D]
                  transition-colors duration-200
                  hover:bg-[#E5E7E9]
                  focus-visible:outline-2
                  focus-visible:outline-offset-2
                  focus-visible:outline-[#007F78]">
                Volver al inicio de sesión</a>
        </section>
        <p class="text-center text-xs text-[#526871]">Sistema de Inventario y Servicios Técnicos</p>
    </main>
</body>

</html>
