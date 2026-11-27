<?php

namespace App\Http\Controllers;

use App\Models\IngresoTaller;
use App\Services\RegistrarIngresoTaller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IngresoTallerDocumentoController extends Controller
{
    public function imprimir(IngresoTaller $ingreso): View
    {
        abort_unless($ingreso->emitido(), 404);

        return view('livewire.taller.ingresos.imprimir', compact('ingreso'));
    }

    public function descargar(IngresoTaller $ingreso): StreamedResponse
    {
        abort_unless($ingreso->firmado() && $ingreso->archivo_firmado, 404);

        $disco = Storage::disk(RegistrarIngresoTaller::DISCO_FIRMAS);
        abort_unless($disco->exists($ingreso->archivo_firmado), 404);

        $extension = pathinfo($ingreso->archivo_firmado, PATHINFO_EXTENSION);

        return $disco->download($ingreso->archivo_firmado, $ingreso->folio.'-firmado.'.$extension, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
