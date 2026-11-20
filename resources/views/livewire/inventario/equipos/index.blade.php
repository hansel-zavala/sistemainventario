<div>

    <div class="mb-6 flex items-center justify-between">

        <div>
            <h1 class="text-2xl font-bold text-gray-800">
                Equipos tecnológicos
            </h1>

            <p class="mt-1 text-sm text-gray-500">
                Registro y control de los equipos tecnológicos.
            </p>
        </div>

        <button
            type="button"
            wire:click="crearEquipo"
            class="cursor-pointer rounded-full bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700"
        >
            + Nuevo equipo
        </button>

    </div>

    <!-- Mensajes de confirmacion de cracion de equipos -->
    @if (session()->has('mensaje'))

        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700">
            {{ session('mensaje') }}
        </div>

    @endif

    <!-- Mensajes de error -->  
    @error('estadoEquipo')
        <p role="alert" class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ $message }}</p>
    @enderror

    <!-- Filtros -->  
    @php
        $filterLabels = ['categoria' => 'Categoría', 'tipo' => 'Tipo de equipo', 'marca' => 'Marca', 'departamento' => 'Procedencia'];
        $statusLabels = ['disponible' => 'Disponible', 'prestado' => 'Prestado', 'inactivo' => 'Inactivo'];
    @endphp

    <!-- tabla de equipos tecnológicos -->
    <x-data-table
        id="equipos"
        label="Tabla de equipos tecnológicos"
        :columns="\App\Services\EquiposTable::COLUMNS"
        :visible-columns="$visibleColumns"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        search-label="Buscar equipo"
        search-placeholder="Inventario, serie, marca, modelo o tipo..."
        :export-disabled="$dateErrors->isNotEmpty()"
        :collapsible-filters="true"
        :active-filter-count="(count(array_filter($filters, fn ($value) => filled($value)))) + (int) ($desde !== '') + (int) ($hasta !== '')"
    >
        <x-slot:export>
            <x-table-export id="equipos" :action="route('inventario.equipos.exportar')"
                :buscar="$buscar" :sort-column="$sortColumn" :sort-direction="$sortDirection"
                :visible-columns="$visibleColumns" :show-date-fields="false" :export-filters="[...$filters, 'desde' => $desde, 'hasta' => $hasta]" />
        </x-slot:export>

        <!-- Filtros -->
        <x-slot:filters>
            @foreach ($filterLabels + ['estado' => 'Estado'] as $name => $label)
                @php
                    $options = $name === 'estado' ? $statusLabels : $filterOptions[$name]->pluck('nombre', 'id');
                @endphp

                <x-searchable-select wire:key="filter-{{ $name }}" id="equipos-filter-{{ $name }}"
                    :label="$label" :options="$options" :model="'filters.'.$name"
                    :all-label="in_array($name, ['tipo', 'estado'], true) ? 'Todos' : 'Todas'"
                    :class="$name === 'estado' ? 'sm:col-span-2' : ''" />
            @endforeach
            <x-table-date-filters id="equipos" :errors="$dateErrors" />
        </x-slot:filters>

        <x-slot:summary>
            Mostrando {{ $equipos->count() }} de {{ $equipos->total() }} equipos encontrados
        </x-slot:summary>

        <!-- Flechas de organizacion -->
        <x-slot:filterChips>
            <x-table-date-chips id="equipos" :desde="$desde" :hasta="$hasta" />
            @foreach ($filterLabels + ['estado' => 'Estado'] as $name => $label)
                @continue(blank($filters[$name] ?? null))
                @php
                    $value = $name === 'estado'
                        ? ($statusLabels[$filters[$name]] ?? null)
                        : $filterOptions[$name]->firstWhere('id', $filters[$name])?->nombre;
                @endphp
                @continue($value === null)
                <button type="button" wire:key="equipos-filter-chip-{{ $name }}" wire:click="removeFilter('{{ $name }}')"
                    wire:loading.attr="disabled" aria-label="Quitar filtro {{ $label }}: {{ $value }}"
                    class="inline-flex min-h-8 max-w-full cursor-pointer items-center gap-1.5 rounded-lg border border-line bg-canvas px-2.5 py-1 text-left text-sm text-ink hover:bg-accent-soft disabled:opacity-50">
                    <span class="min-w-0 wrap-break-word">{{ $label }}: {{ $value }}</span>
                    <x-icon name="close" class="size-3.5" />
                </button>
            @endforeach
        </x-slot:filterChips>

        <!-- Datos de los equipos -->
        @forelse ($equipos as $equipo)
            <tr wire:key="equipment-{{ $equipo->id }}" wire:click="verEquipo({{ $equipo->id }})"
                class="cursor-pointer hover:bg-canvas/60 focus-within:bg-canvas/60">
                @foreach (\App\Services\EquiposTable::COLUMNS as $column => $label)
                    @if (in_array($column, $visibleColumns, true))
                        <td wire:key="cell-{{ $equipo->id }}-{{ $column }}" class="px-4 py-3 text-ink">
                            @if ($column === 'tipo_equipo')
                                <div class="font-medium">{{ $equipo->tipoEquipo->nombre }}</div>
                                <div class="mt-0.5 text-xs text-muted">{{ $equipo->categoria->nombre }}</div>
                            @elseif ($column === 'marca')
                                <div class="font-medium">{{ $equipo->marca->nombre }}</div>
                                @if (filled($equipo->modelo))
                                    <div class="mt-0.5 text-xs text-muted">{{ $equipo->modelo }}</div>
                                @endif
                            @elseif ($column === 'disponibilidad')
                                <span @class(['inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold', 'bg-gray-100 text-gray-600' => !$equipo->activo, 'bg-amber-100 text-amber-800' => $equipo->activo && $equipo->esta_prestado, 'bg-green-100 text-green-800' => $equipo->activo && !$equipo->esta_prestado])>
                                    {{ \App\Services\EquiposTable::status($equipo) }}
                                </span>
                            @else
                                {{ \App\Services\EquiposTable::row($equipo, [$column])[0] }}
                            @endif
                        </td>
                    @endif
                @endforeach

                <!-- Botones de acción -->
                <td class="cursor-default px-4 py-2 text-right">
                    <div class="flex justify-end gap-1">
                        <button type="button" id="ver-equipo-{{ $equipo->id }}" wire:click.stop="verEquipo({{ $equipo->id }})"
                            aria-haspopup="dialog" class="icon-button" title="Ver equipo" aria-label="Ver equipo {{ $equipo->numero_inventario }}"><x-icon name="eye" /></button>
                        <button type="button" wire:click.stop="editarEquipo({{ $equipo->id }})" class="icon-button text-blue-600"
                            title="Editar equipo" aria-label="Editar equipo {{ $equipo->numero_inventario }}"><x-icon name="edit" /></button>
                        <button type="button" wire:click.stop="cambiarEstado({{ $equipo->id }})" wire:loading.attr="disabled"
                            wire:confirm="¿Desea {{ $equipo->activo ? 'desactivar' : 'reactivar' }} el equipo {{ $equipo->numero_inventario }}?"
                            class="icon-button {{ $equipo->activo ? 'text-red-600' : 'text-green-700' }} disabled:opacity-50"
                            title="{{ $equipo->activo ? 'Desactivar equipo' : 'Reactivar equipo' }}"
                            aria-label="{{ $equipo->activo ? 'Desactivar' : 'Reactivar' }} equipo {{ $equipo->numero_inventario }}"><x-icon name="power" /></button>
                    </div>
                </td>
            </tr>

        <!-- Condicion si no se encuentran equipos con estos filtros -->
        @empty
            <tr><td colspan="{{ count($visibleColumns) + 1 }}" class="px-6 py-12 text-center text-muted">No se encontraron equipos con estos filtros.</td></tr>
        @endforelse

        <!-- Paginacion -->      
        <x-slot:pagination>
            <x-table-pagination :paginator="$equipos" label="Paginación de equipos" />
        </x-slot:pagination>
    </x-data-table>

    @if ($equipoDetalle)
        @include('livewire.inventario.equipos.detalle', ['equipo' => $equipoDetalle, 'movimientos' => $movimientosEquipo])
    @endif

    <!-- Modal de creacion y edicion -->
    @if ($mostrarModal)
        
        <!-- Definicion de variables del modal -->
        @php
            $esRegistro = $equipoId === null;
            $claseControl = 'w-full border border-neutral-300 bg-white px-4 py-2 text-sm text-neutral-900 placeholder:text-neutral-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:bg-neutral-100 disabled:text-neutral-500 aria-invalid:border-red-600';
            $claseError = 'mt-1 text-sm text-red-700';
            $camposCatalogo = [
                ['categoriaId', 'Categoría', $categorias, 'categoria', 'Nueva categoría'],
                ['tipoEquipoId', 'Tipo de equipo', $tiposEquipo, 'tipo_equipo', 'Nuevo tipo de equipo'],
                ['departamentoId', 'Departamento de procedencia', $departamentos, 'departamento', 'Nuevo departamento'],
                ['marcaId', 'Marca', $marcas, 'marca', 'Nueva marca'],
            ];
        @endphp

        <!-- Creacion y edicion del modal -->
        <dialog wire:key="modal-equipo" wire:ignore.self x-data x-init="$el.showModal()"
            x-on:cancel.prevent="$wire.cerrarModal()"
            aria-labelledby="titulo-modal-equipo" aria-describedby="descripcion-modal-equipo"
            class="m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-[43rem] overflow-y-auto rounded-2xl border border-neutral-200 bg-white p-4 text-neutral-900 shadow-xl backdrop:bg-black/50 sm:px-5">
            <header class="flex items-start gap-3 border-b border-neutral-200 pb-4">
                <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-xl bg-neutral-100">
                    <x-icon name="monitor" class="size-4" />
                </span>

                <div class="min-w-0 flex-1">
                    <h2 id="titulo-modal-equipo" class="text-lg font-semibold">
                        {{ $esRegistro ? 'Nuevo equipo' : 'Editar equipo' }}
                    </h2>
                    <p id="descripcion-modal-equipo" class="mt-0.5 text-xs text-neutral-600">
                        {{ $esRegistro ? 'Registra la información del equipo en el inventario.' : 'Actualiza la información del equipo en el inventario.' }}
                    </p>
                </div>

                <button type="button" wire:click="cerrarModal" autofocus aria-label="Cerrar"
                    class="inline-flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-full text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900">
                    <x-icon name="close" class="size-4" />
                </button>
            </header>

            @if (session()->has('mensajeCatalogo'))
                <p role="status" class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                    {{ session('mensajeCatalogo') }}
                </p>
            @endif

            <form wire:submit="guardarEquipo" class="divide-y divide-neutral-200">
                <section aria-labelledby="informacion-general-equipo" class="py-5">
                    <h3 id="informacion-general-equipo" class="mb-4 flex items-center gap-2 text-sm font-semibold">
                        <x-icon name="audit" class="size-4 text-neutral-600" />
                        Información general
                    </h3>

                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($camposCatalogo as [$campo, $etiqueta, $opciones, $catalogo, $accionCatalogo])
                            @php
                                $dependeDeCategoria = in_array($catalogo, ['tipo_equipo', 'marca'], true);
                                $inhabilitado = $dependeDeCategoria && ! $categoriaDisponible;
                                $idControl = 'equipo-catalogo-'.$catalogo;
                            @endphp

                            <div wire:key="equipo-campo-{{ $catalogo }}">
                                <label for="{{ $idControl }}" class="mb-1 block text-sm font-medium">
                                    {{ $etiqueta }} <span aria-hidden="true" class="text-red-600">*</span>
                                </label>

                                <div class="flex items-center gap-1.5">
                                    <div class="relative min-w-0 flex-1">
                                        <select id="{{ $idControl }}"
                                            @if ($catalogo === 'categoria') wire:model.live="{{ $campo }}" @else wire:model="{{ $campo }}" @endif
                                            wire:key="{{ $idControl }}-{{ $dependeDeCategoria ? $categoriaId : 'opciones' }}"
                                            required @disabled($inhabilitado)
                                            @error($campo) aria-invalid="true" aria-describedby="{{ $idControl }}-error" @enderror
                                            class="{{ $claseControl }} equipment-select cursor-pointer rounded-full pr-9 invalid:text-neutral-500 disabled:cursor-not-allowed">
                                            <option value="">{{ $inhabilitado ? 'Seleccione una categoría activa' : 'Seleccione una opción' }}</option>

                                            @foreach ($opciones as $opcion)
                                                <option wire:key="{{ $idControl }}-opcion-{{ $opcion->id }}" value="{{ $opcion->id }}">
                                                    {{ $opcion->nombre }}@if ($catalogo === 'departamento' && $opcion->sigla) ({{ $opcion->sigla }})@endif
                                                </option>
                                            @endforeach
                                        </select>
                                        <x-icon name="chevron" class="pointer-events-none absolute right-3 top-1/2 size-3.5 -translate-y-1/2 rotate-90 text-neutral-500" />
                                    </div>

                                    <button type="button" wire:click="abrirModalCatalogo('{{ $catalogo }}')"
                                        aria-label="{{ $accionCatalogo }}" title="{{ $accionCatalogo }}" @disabled($inhabilitado)
                                        class="inline-flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-full border border-neutral-300 text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 disabled:cursor-not-allowed disabled:opacity-50">
                                        <x-icon name="plus" class="size-4" />
                                    </button>
                                </div>

                                @if ($dependeDeCategoria && $categoriaDisponible && $opciones->isEmpty())
                                    <p role="status" class="mt-1 text-xs text-neutral-600">
                                        {{ $catalogo === 'marca' ? 'No hay marcas activas vinculadas a esta categoría.' : 'No hay tipos de equipo activos vinculados a esta categoría.' }}
                                    </p>
                                @endif

                                @error($campo)
                                    <p id="{{ $idControl }}-error" class="{{ $claseError }}">{{ $message }}</p>
                                @enderror
                            </div>
                        @endforeach
                    </div>
                </section>

                <section aria-labelledby="identificacion-equipo" class="py-5">
                    <h3 id="identificacion-equipo" class="mb-4 flex items-center gap-2 text-sm font-semibold">
                        <x-icon name="tag" class="size-4 text-neutral-600" />
                        Identificación del equipo
                    </h3>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="numero-inventario-equipo" class="mb-1 block text-sm font-medium">
                                Número de inventario <span aria-hidden="true" class="text-red-600">*</span>
                            </label>
                            <input id="numero-inventario-equipo" type="text" wire:model="numeroInventario"
                                required maxlength="100" placeholder="Ej. INV-000125" class="{{ $claseControl }} rounded-full"
                                @error('numeroInventario') aria-invalid="true" aria-describedby="error-numero-inventario-equipo" @enderror>
                            @error('numeroInventario')
                                <p id="error-numero-inventario-equipo" class="{{ $claseError }}">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="numero-serie-equipo" class="mb-1 block text-sm font-medium">
                                Número de serie @if ($esRegistro)<span aria-hidden="true" class="text-red-600">*</span>@endif
                            </label>
                            <input id="numero-serie-equipo" type="text" wire:model="numeroSerie"
                                @required($esRegistro) maxlength="100" placeholder="Serie del fabricante" class="{{ $claseControl }} rounded-full"
                                @error('numeroSerie') aria-invalid="true" aria-describedby="error-numero-serie-equipo" @enderror>
                            @error('numeroSerie')
                                <p id="error-numero-serie-equipo" class="{{ $claseError }}">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </section>

                <section aria-labelledby="caracteristicas-equipo" class="py-5">
                    <h3 id="caracteristicas-equipo" class="mb-4 flex items-center gap-2 text-sm font-semibold">
                        <x-icon name="columns" class="size-4 text-neutral-600" />
                        Características adicionales
                    </h3>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="modelo-equipo" class="mb-1 block text-sm font-medium">
                                Modelo @if ($esRegistro)<span aria-hidden="true" class="text-red-600">*</span>@endif
                            </label>
                            <input id="modelo-equipo" type="text" wire:model="modelo"
                                @required($esRegistro) maxlength="100" placeholder="Ej. Latitude 5420" class="{{ $claseControl }} rounded-full"
                                @error('modelo') aria-invalid="true" aria-describedby="error-modelo-equipo" @enderror>
                            @error('modelo')
                                <p id="error-modelo-equipo" class="{{ $claseError }}">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="color-equipo" class="mb-1 block text-sm font-medium">
                                Color @if ($esRegistro)<span aria-hidden="true" class="text-red-600">*</span>@endif
                            </label>
                            <input id="color-equipo" type="text" wire:model="color"
                                @required($esRegistro) maxlength="50" placeholder="Ej. Negro" class="{{ $claseControl }} rounded-full"
                                @error('color') aria-invalid="true" aria-describedby="error-color-equipo" @enderror>
                            @error('color')
                                <p id="error-color-equipo" class="{{ $claseError }}">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label for="observaciones-equipo" class="mb-1 block text-sm font-medium">
                                Observaciones @if ($esRegistro)<span aria-hidden="true" class="text-red-600">*</span>@endif
                            </label>
                            <textarea id="observaciones-equipo" wire:model="observaciones" @required($esRegistro)
                                rows="3" maxlength="2000" placeholder="Detalles o características importantes del equipo..."
                                class="{{ $claseControl }} block resize-y rounded-lg"
                                @error('observaciones') aria-invalid="true" aria-describedby="error-observaciones-equipo" @enderror></textarea>
                            @error('observaciones')
                                <p id="error-observaciones-equipo" class="{{ $claseError }}">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </section>

                <footer class="flex flex-wrap items-center justify-between gap-3 pt-4">
                    <p class="text-xs text-neutral-600"><span aria-hidden="true" class="text-red-600">* </span>Campos obligatorios</p>

                    <div class="ml-auto flex flex-wrap justify-end gap-2">
                        <button type="button" wire:click="cerrarModal"
                            class="inline-flex min-h-9 cursor-pointer items-center justify-center rounded-full border border-neutral-300 px-4 text-sm font-medium text-neutral-700 hover:bg-neutral-100">
                            Cancelar
                        </button>

                        <button type="submit" wire:loading.attr="disabled" wire:target="guardarEquipo"
                            class="inline-flex min-h-9 cursor-pointer items-center justify-center gap-2 rounded-full bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60">
                            <x-icon name="save" class="size-4" />
                            {{ $esRegistro ? 'Registrar equipo' : 'Guardar cambios' }}
                        </button>
                    </div>
                </footer>
            </form>
        </dialog>
    @endif


    <!-- Modal de catálogo rápido -->
    @if ($mostrarModalCatalogo)
        
        <dialog wire:key="modal-catalogo-equipo" wire:ignore.self x-data x-init="$el.showModal()"
            x-on:cancel.prevent="$wire.cerrarModalCatalogo()"
            aria-labelledby="titulo-modal-catalogo-equipo"
            class="m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-md overflow-y-auto rounded-xl bg-white p-6 shadow-xl backdrop:bg-black/50">

                <div class="mb-4 flex items-center justify-between">

                    <div>
                        <h3 id="titulo-modal-catalogo-equipo" class="text-lg font-bold text-gray-800">
                            @if ($tipoCatalogo === 'categoria')
                                Nueva categoría
                            @elseif ($tipoCatalogo === 'tipo_equipo')
                                Tipo de equipo
                            @elseif ($tipoCatalogo === 'departamento')
                                Nuevo departamento
                            @elseif ($tipoCatalogo === 'marca')
                                Marca
                            @else
                                Nuevo registro
                            @endif
                        </h3>

                        @unless ($catalogoVinculable)
                            <p class="text-xs text-gray-500">
                                Ingrese el nombre para registrarlo rápidamente.
                            </p>
                        @endunless
                    </div>

                    <button type="button" wire:click="cerrarModalCatalogo" autofocus aria-label="Cerrar"
                        class="inline-flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-full text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900">
                        <x-icon name="close" class="size-4" />
                    </button>

                </div>

                <form
                    wire:submit="guardarCatalogoRapido"
                    class="space-y-4"
                >
                    @if ($catalogoVinculable)
                        <x-catalogo-rapido-vinculo
                            :categoria="$categoriaCatalogo"
                            :opciones="$opcionesCatalogoExistente"
                            :vincular-existente="$vincularExistente"
                        />
                    @endif

                    @unless ($catalogoVinculable && $vincularExistente)
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Nombre <span aria-hidden="true" class="text-red-600">*</span>
                        </label>

                        <input
                            type="text"
                            wire:model="nuevoNombreCatalogo"
                            placeholder="Nombre del registro"
                            class="w-full rounded-full border border-gray-300 px-4 py-2 focus:border-blue-500 focus:outline-none"
                            autofocus
                        >

                        @error('nuevoNombreCatalogo')
                            <p class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    @if ($tipoCatalogo === 'departamento')
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Sigla <span aria-hidden="true" class="text-red-600">*</span>
                            </label>

                            <input
                                type="text"
                                wire:model="nuevaSiglaDepartamento"
                                placeholder="Ej. RRHH"
                                class="w-full rounded-full border border-gray-300 px-4 py-2 focus:border-blue-500 focus:outline-none"
                            >

                            @error('nuevaSiglaDepartamento')
                                <p class="mt-1 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>
                    @endif

                    @endunless

                    <div class="flex justify-end gap-3 border-t border-gray-100 pt-4">
                        <button
                            type="button"
                            wire:click="cerrarModalCatalogo"
                            class="cursor-pointer rounded-full border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-200"
                        >
                            Cancelar
                        </button>

                        <button
                            type="submit"
                            class="cursor-pointer rounded-full bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                        >
                            {{ $catalogoVinculable && $vincularExistente ? 'Vincular y seleccionar' : 'Guardar' }}
                        </button>
                    </div>

                </form>

        </dialog>

    @endif

</div>
