
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prestamos', function (Blueprint $table) {
            $table->dateTime('fecha_prevista_devolucion')
                ->change();
        });

        // Los préstamos antiguos solo tenían fecha.
        // Conservamos el día de vencimiento hasta las 23:59:59.
        DB::statement("
            UPDATE prestamos
            SET fecha_prevista_devolucion =
                TIMESTAMP(
                    DATE(fecha_prevista_devolucion),
                    '23:59:59'
                )
            WHERE TIME(fecha_prevista_devolucion) = '00:00:00'
        ");
    }

    public function down(): void
    {
        Schema::table('prestamos', function (Blueprint $table) {
            $table->date('fecha_prevista_devolucion')
                ->change();
        });
    }
};
