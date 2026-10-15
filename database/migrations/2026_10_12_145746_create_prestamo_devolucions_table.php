
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prestamo_devoluciones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('prestamo_detalle_id')
                ->constrained('prestamo_detalles')
                ->restrictOnDelete();

            $table->decimal('cantidad', 12, 2);

            $table->dateTime('fecha_devolucion');

            $table->foreignId('recibido_por')
                ->constrained('users')
                ->restrictOnDelete();

            $table->text('observaciones')->nullable();

            $table->timestamps();

            $table->index('fecha_devolucion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prestamo_devoluciones');
    }
};
