<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prestamos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('departamento_destino_id')
                ->constrained('departamentos')
                ->restrictOnDelete();

            $table->string('persona_recibe', 150);

            $table->dateTime('fecha_prestamo');

            $table->date('fecha_prevista_devolucion');

            $table->string('estado', 20)
                ->default('activo');

            $table->text('observaciones')
                ->nullable();

            $table->foreignId('registrado_por')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->index('estado');
            $table->index('fecha_prevista_devolucion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prestamos');
    }
};