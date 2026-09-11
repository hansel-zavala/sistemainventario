<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('categoria_id')
                ->constrained('categorias')
                ->restrictOnDelete();

            $table->foreignId('tipo_equipo_id')
                ->constrained('tipos_equipo')
                ->restrictOnDelete();

            $table->foreignId('departamento_id')
                ->constrained('departamentos')
                ->restrictOnDelete();

            $table->foreignId('marca_id')
                ->constrained('marcas')
                ->restrictOnDelete();

            $table->string('modelo', 100)
                ->nullable();

            $table->string('color', 50)
                ->nullable();

            $table->string('numero_serie', 100)
                ->nullable()
                ->unique();

            $table->string('numero_inventario', 100)
                ->unique();

            $table->text('observaciones')
                ->nullable();

            $table->foreignId('creado_por')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipos');
    }
};