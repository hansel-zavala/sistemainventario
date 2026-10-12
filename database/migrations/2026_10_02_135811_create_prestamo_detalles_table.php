<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prestamo_detalles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('prestamo_id')
                ->constrained('prestamos')
                ->cascadeOnDelete();

            $table->foreignId('herramienta_id')
                ->constrained('herramientas')
                ->restrictOnDelete();

            $table->string('estado', 20)
                ->default('prestado');

            $table->dateTime('fecha_devolucion')
                ->nullable();

            $table->foreignId('recibido_por')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->text('observaciones_devolucion')
                ->nullable();

            $table->timestamps();

            $table->index([
                'herramienta_id',
                'estado',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prestamo_detalles');
    }
};