<x-layouts.app title="Mi perfil">
    <div class="mx-auto flex max-w-5xl flex-col gap-6">
        <header>
            <h1 class="text-2xl font-bold text-ink">Mi perfil</h1>
            <p class="mt-2 text-sm text-muted">Consulta tu información y administra la seguridad de tu cuenta.</p>
        </header>

        @if (session('status'))
            <div role="status" class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
                <x-icon name="check" /> <span>{{ session('status') }}</span>
            </div>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        <section aria-labelledby="profile-information-title" class="overflow-hidden rounded-xl border border-line bg-surface">
            <div class="flex flex-wrap items-center gap-4 border-b border-line px-5 py-6 sm:px-7">
                <span aria-hidden="true" class="flex size-16 shrink-0 items-center justify-center rounded-full bg-accent-soft text-2xl font-bold text-accent">{{ mb_strtoupper(mb_substr($usuario->name, 0, 1)) }}</span>
                <div class="min-w-0 flex-1">
                    <h2 id="profile-information-title" class="break-words text-lg font-semibold text-ink">{{ $usuario->name }}</h2>
                    <p class="mt-1 text-sm text-muted">{{ $usuario->getRoleNames()->implode(', ') ?: 'Sin rol asignado' }}</p>
                </div>
                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800">{{ $usuario->activo ? 'Cuenta activa' : 'Cuenta inactiva' }}</span>
            </div>
            <div class="flex flex-col gap-6 p-5 sm:p-7">
                <dl class="grid gap-6 sm:grid-cols-2">
                    <div><dt class="text-sm text-muted">Nombre completo</dt><dd class="mt-1 break-words font-medium">{{ $usuario->name }}</dd></div>
                    <div><dt class="text-sm text-muted">Correo electrónico</dt><dd class="mt-1 break-all font-medium">{{ $usuario->email }}</dd></div>
                    <div><dt class="text-sm text-muted">Rol</dt><dd class="mt-1 font-medium">{{ $usuario->getRoleNames()->implode(', ') ?: 'Sin rol asignado' }}</dd></div>
                    <div><dt class="text-sm text-muted">Miembro desde</dt><dd class="mt-1 font-medium">{{ $usuario->created_at?->format('d/m/Y') ?? 'No disponible' }}</dd></div>
                </dl>
                <p class="flex items-start gap-2 text-sm text-muted"><x-icon name="lock" class="size-4" /><span>Para cambiar tu correo electrónico, contacta al administrador.</span></p>
                <details data-profile-edit @if($errors->perfil->any()) open @endif class="border-t border-line pt-5">
                    <summary class="secondary-button w-fit list-none [&::-webkit-details-marker]:hidden"><x-icon name="edit" class="size-4" />Editar información</summary>
                    <div data-auth-actions class="mt-5 max-w-xl">
                        <form data-auth-form method="POST" action="{{ route('perfil.update') }}" class="flex flex-col gap-4">
                            @csrf
                            @method('PATCH')
                            <div class="flex flex-col gap-2">
                                <label for="profile-name" class="text-sm font-semibold">Nombre completo</label>
                                <input id="profile-name" name="name" value="{{ old('name', $usuario->name) }}" autocomplete="name" required maxlength="255" class="profile-input" @if($errors->perfil->has('name')) aria-invalid="true" aria-describedby="profile-name-error" @endif>
                                @error('name', 'perfil')<p id="profile-name-error" role="alert" class="text-sm text-red-700">{{ $message }}</p>@enderror
                            </div>
                            <div class="flex flex-wrap gap-3">
                                <button type="submit" data-loading-label="Guardando…" class="primary-button">
                                    <svg data-loading-indicator aria-hidden="true" class="hidden size-4 motion-safe:animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" opacity=".25" /><path d="M12 3a9 9 0 0 1 9 9" stroke="currentColor" stroke-width="3" /></svg>
                                    <span data-button-label>Guardar cambios</span>
                                </button>
                                <a href="{{ route('perfil.show') }}" class="secondary-button">Cancelar</a>
                            </div>
                        </form>
                        <p data-submit-status role="status" class="sr-only"></p>
                    </div>
                </details>
            </div>
        </section>

        <section data-auth-actions aria-labelledby="password-title" class="rounded-xl border border-line bg-surface p-5 sm:p-7">
            <div class="mb-6 flex items-start gap-3">
                <span class="rounded-lg bg-accent-soft p-2.5 text-accent"><x-icon name="lock" /></span>
                <div>
                    <h2 id="password-title" class="text-lg font-semibold">Cambiar contraseña</h2>
                    <p class="mt-1 text-sm leading-6 text-muted">Al guardar, se invalidarán los demás accesos. Podrás seguir trabajando en esta sesión.</p>
                </div>
            </div>
            @if ($errors->password->any())
                <div id="profile-password-errors" role="alert" class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                    <ul class="flex flex-col gap-1">@foreach ($errors->password->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            <form data-auth-form method="POST" action="{{ route('perfil.password.update') }}" class="flex flex-col gap-5">
                @csrf
                @method('PUT')
                <div class="grid gap-5 lg:grid-cols-2">
                    <div class="flex flex-col gap-4">
                        @foreach (['current_password' => 'Contraseña actual', 'password' => 'Nueva contraseña', 'password_confirmation' => 'Confirmar nueva contraseña'] as $name => $label)
                            <div class="flex flex-col gap-2">
                                <label for="{{ $name }}" class="text-sm font-semibold">{{ $label }}</label>
                                <div class="relative">
                                    <input id="{{ $name }}" name="{{ $name }}" type="password" autocomplete="{{ $name === 'current_password' ? 'current-password' : 'new-password' }}" required @if($name !== 'current_password') minlength="8" maxlength="72" @endif aria-describedby="password-requirements{{ $errors->password->any() ? ' profile-password-errors' : '' }}" @if($errors->password->has($name)) aria-invalid="true" @endif class="profile-input pr-14">
                                    <button type="button" data-password-toggle data-password-label="{{ mb_strtolower($label) }}" aria-label="Mostrar {{ mb_strtolower($label) }}" aria-controls="{{ $name }}" aria-pressed="false" class="hidden absolute inset-y-1 right-1 w-11 cursor-pointer items-center justify-center rounded-lg text-muted hover:bg-accent-soft hover:text-accent">
                                        <svg aria-hidden="true" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" /><circle cx="12" cy="12" r="3" /><path data-password-eye-slash d="m3 3 18 18" class="hidden" /></svg>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div id="password-requirements" data-password-requirements class="h-fit rounded-xl border border-line bg-canvas p-5 text-sm">
                        <p class="mb-4 font-semibold text-ink">Requisitos de la contraseña</p>
                        <ul class="flex flex-col gap-3" aria-live="polite" aria-atomic="true">
                            <li data-password-length class="flex items-start gap-2 text-[#526871]"><span data-requirement-icon aria-hidden="true">○</span><span data-requirement-label>Entre 8 y 72 caracteres.</span></li>
                            <li data-password-uppercase class="flex items-start gap-2 text-[#526871]"><span data-requirement-icon aria-hidden="true">○</span><span data-requirement-label>Al menos una letra mayúscula.</span></li>
                            <li data-password-symbol class="flex items-start gap-2 text-[#526871]"><span data-requirement-icon aria-hidden="true">○</span><span data-requirement-label>Al menos un símbolo, como !, @, # o $.</span></li>
                            <li data-password-match class="flex items-start gap-2 text-[#526871]"><span data-requirement-icon aria-hidden="true">○</span><span data-requirement-label>Confirma tu contraseña para comprobar que coinciden.</span></li>
                        </ul>
                    </div>
                </div>
                <button type="submit" data-loading-label="Actualizando…" class="primary-button self-start">
                    <svg data-loading-indicator aria-hidden="true" class="hidden size-4 motion-safe:animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" opacity=".25" /><path d="M12 3a9 9 0 0 1 9 9" stroke="currentColor" stroke-width="3" /></svg>
                    <span data-button-label>Actualizar contraseña</span>
                </button>
            </form>
            <p data-submit-status role="status" class="sr-only"></p>
        </section>
    </div>
</x-layouts.app>
