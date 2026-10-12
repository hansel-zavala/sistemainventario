
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prestamo_detalles', function (Blueprint $table) {
            $table->foreignId('herramienta_id')
                ->nullable()
                ->change();

            $table->foreignId('equipo_id')
                ->nullable()
                ->after('herramienta_id')
                ->constrained('equipos')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        // No permite revertir si ya existen préstamos de equipos.
        if (
            \Illuminate\Support\Facades\DB::table('prestamo_detalles')
                ->whereNotNull('equipo_id')
                ->exists()
        ) {
            throw new \RuntimeException(
                'No se puede revertir: existen préstamos de equipos.'
            );
        }

        Schema::table('prestamo_detalles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('equipo_id');

            $table->foreignId('herramienta_id')
                ->nullable(false)
                ->change();
        });
    }
};
