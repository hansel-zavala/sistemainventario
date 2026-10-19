<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditorias', function (Blueprint $table) {
            $table->id();

            // Modelo al que pertenece el registro.
            $table->string('modelo', 150);

            // Identificador del registro afectado.
            $table->unsignedBigInteger('registro_id');

            // Accion realizada.
            $table->string('accion', 30);

            // Valores antes y despues del cambio.
            $table->json('valores_anteriores')->nullable();
            $table->json('valores_nuevos')->nullable();

            // Usuario responsable.
            $table->foreignId('usuario_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Fecha y hora del evento.
            $table->timestamps();

            // Indices para consultas posteriores.
            $table->index(['modelo', 'registro_id']);
            $table->index(['accion', 'created_at']);
            $table->index('usuario_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias');
    }
};
