<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipos_taller', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('equipo_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->index('equipo_id');
        });

        Schema::create('ingresos_taller', function (Blueprint $table): void {
            $table->id();
            $table->uuid('borrador_token')->unique();
            $table->string('folio', 30)->nullable()->unique();
            $table->unsignedInteger('version')->default(0);
            $table->unsignedInteger('revision')->default(0);
            $table->foreignId('equipo_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('equipo_taller_id')->nullable()->constrained('equipos_taller')->restrictOnDelete();
            $table->foreignId('departamento_id')->nullable()->constrained('departamentos')->restrictOnDelete();
            $table->foreignId('tecnico_id')->constrained('users')->restrictOnDelete();
            $table->string('tecnico_nombre');
            $table->dateTime('fecha_ingreso')->nullable();
            $table->string('departamento_nombre')->nullable();
            $table->string('entregado_por')->nullable();
            $table->string('tipo_equipo', 100)->nullable();
            $table->string('numero_inventario', 100)->nullable()->index();
            $table->string('marca', 100)->nullable();
            $table->string('modelo', 100)->nullable();
            $table->string('color', 50)->nullable();
            $table->string('condicion_equipo', 10)->nullable();
            $table->string('estado_fisico', 10)->nullable();
            $table->text('observaciones')->nullable();
            $table->text('motivo')->nullable();
            $table->string('autorizado_por')->nullable();
            $table->boolean('autoriza_respaldo')->nullable();
            $table->string('archivo_firmado')->nullable();
            $table->dateTime('firmado_en')->nullable();
            $table->timestamps();
            $table->index(['equipo_id', 'fecha_ingreso']);
            $table->index(['equipo_taller_id', 'fecha_ingreso']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingresos_taller');
        Schema::dropIfExists('equipos_taller');
    }
};
