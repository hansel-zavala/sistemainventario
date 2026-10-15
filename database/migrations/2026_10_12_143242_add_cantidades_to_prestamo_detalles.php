<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prestamo_detalles', function (Blueprint $table) {
            $table->decimal('cantidad_prestada', 12, 2)
                ->default(1)
                ->after('equipo_id');

            $table->decimal('cantidad_devuelta', 12, 2)
                ->default(0)
                ->after('cantidad_prestada');
        });

        // Conservar las devoluciones existentes.
        // Antes, cada detalle correspondía a una sola unidad.
        DB::table('prestamo_detalles')
            ->where('estado', 'devuelto')
            ->update(['cantidad_devuelta' => 1]);
    }

    public function down(): void
    {
        // No revertir si hay préstamos de más de una unidad.
        $hayMultiples = DB::table('prestamo_detalles')
            ->where('cantidad_prestada', '>', 1)
            ->exists();

        if ($hayMultiples) {
            throw new RuntimeException(
                'Existen préstamos por cantidad. No se puede revertir sin perder información.'
            );
        }

        Schema::table('prestamo_detalles', function (Blueprint $table) {
            $table->dropColumn([
                'cantidad_prestada',
                'cantidad_devuelta',
            ]);
        });
    }
};
