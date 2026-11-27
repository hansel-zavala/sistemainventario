<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hoja de ingreso {{ $ingreso->folio }} | Municipalidad de La Ceiba</title>
    <style>
        * { box-sizing: border-box; }
        body { color: #17232d; font: 11pt/1.45 "Segoe UI", system-ui, sans-serif; margin: 24px auto; padding: 0 20px; max-width: 800px; }
        .acciones { display: flex; flex-wrap: wrap; align-items: center; gap: 16px; margin-bottom: 24px; }
        button { cursor: pointer; padding: 8px 14px; }
        a { color: #175cba; }
        header { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; border-bottom: 2px solid #17232d; padding-bottom: 12px; }
        header img { width: 56px; height: 60px; object-fit: contain; }
        .institucion { flex: 1 1 15rem; }
        .folio { text-align: right; }
        .folio strong { display: block; font-size: 16pt; }
        h1 { font-size: 18pt; margin: 18px 0 12px; }
        h2 { font-size: 11pt; margin: 16px 0 6px; border-bottom: 1px solid #aeb8c0; padding-bottom: 4px; break-after: avoid; }
        dl { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px 14px; margin: 0; }
        dt { font-size: 9pt; color: #3b4a56; }
        dd { margin: 2px 0 0; overflow-wrap: anywhere; }
        p { margin: 0 0 8px; overflow-wrap: anywhere; }
        .texto { white-space: pre-wrap; }
        .prosa { margin-top: 8px; }
        .prosa strong { display: block; break-after: avoid; }
        .firma { width: min(100%, 13rem); margin: 30px 0 0; border-top: 1px solid #17232d; padding-top: 4px; }
        .permiso { margin-top: 14px; break-inside: avoid; }
        @media screen and (max-width: 440px) { dl { grid-template-columns: 1fr; } .folio { text-align: left; } }
        @page { size: letter portrait; margin: 15mm; }
        @media print {
            body { margin: 0; max-width: none; padding: 0; }
            .acciones { display: none; }
            header, dl > div { break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="acciones">
        <a href="{{ route('taller.ingresos.ver', $ingreso) }}">Volver al ingreso</a>
        <button type="button" onclick="window.print()">Imprimir / Guardar como PDF</button>
        @if ($ingreso->firmado())
            <a href="{{ route('taller.ingresos.firmado', $ingreso) }}">Descargar hoja firmada</a>
        @endif
    </div>

    <header>
        <img src="{{ asset('image/LogoLaCeibaPNG.webp') }}" alt="Escudo de La Ceiba" width="56" height="60">
        <div class="institucion">
            <strong>Departamento de Informática</strong><br>
            Municipalidad de La Ceiba<br>
            informatica@alcaldialaceiba.hn
        </div>
        <div class="folio">Folio <strong>{{ $ingreso->folio }}</strong>Revisión {{ $ingreso->revision }}</div>
    </header>

    <main>
        <h1>Hoja de ingreso del equipo</h1>
        <section>
            <h2>Recepción</h2>
            <dl>
                <div><dt>Fecha de ingreso</dt><dd>{{ $ingreso->fecha_ingreso->format('d/m/Y') }}</dd></div>
                <div><dt>Hora de ingreso</dt><dd>{{ $ingreso->fecha_ingreso->format('H:i') }}</dd></div>
                <div><dt>Departamento de procedencia</dt><dd>{{ $ingreso->departamento_nombre }}</dd></div>
                <div><dt>Persona que entrega</dt><dd>{{ $ingreso->entregado_por }}</dd></div>
                <div><dt>Técnico que recibe</dt><dd>{{ $ingreso->tecnico_nombre }}</dd></div>
            </dl>
        </section>
        <section>
            <h2>Descripción del equipo</h2>
            <dl>
                @foreach ([
                    'Condición' => $ingreso->condicion_equipo,
                    'Estado físico' => $ingreso->estado_fisico,
                    'Tipo de equipo' => $ingreso->tipo_equipo,
                    'Número de inventario' => $ingreso->numero_inventario,
                    'Marca' => $ingreso->marca,
                    'Modelo' => $ingreso->modelo,
                    'Color' => $ingreso->color,
                ] as $etiqueta => $valor)
                    <div><dt>{{ $etiqueta }}</dt><dd>{{ filled($valor) ? $valor : 'Sin registrar' }}</dd></div>
                @endforeach
            </dl>
            <div class="prosa">
                <strong>Observaciones del equipo</strong>
                <p class="texto">{{ filled($ingreso->observaciones) ? $ingreso->observaciones : 'Sin observaciones' }}</p>
            </div>
            <div class="prosa">
                <strong>Falla reportada o servicio solicitado</strong>
                <p class="texto">{{ $ingreso->motivo }}</p>
            </div>
        </section>
        <section>
            <h2>Autorización y firma</h2>
            <div class="permiso">
                <p>Yo, <strong>{{ $ingreso->autorizado_por }}</strong>, doy fe de que la información anterior es correcta y autorizo al Departamento de Informática a revisar el equipo descrito.</p>
                <p>Autorizo al Departamento de Informática a realizar un respaldo de los datos del equipo: <strong>{{ $ingreso->autoriza_respaldo ? 'Sí' : 'No' }}</strong>.</p>
                <p class="firma">Firma de quien autoriza</p>
            </div>
        </section>
    </main>
</body>
</html>
