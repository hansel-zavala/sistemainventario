<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('herramientas', function (Blueprint $table) {
            $table->id();

            $table->string('nombre', 150);

            $table->foreignId('categoria_id')
                ->constrained('categorias')
                ->restrictOnDelete();

            $table->foreignId('marca_id')
                ->nullable()
                ->constrained('marcas')
                ->restrictOnDelete();

            $table->foreignId('unidad_medida_id')
                ->constrained('unidades_medida')
                ->restrictOnDelete();

            $table->string('tipo_control', 20);

            $table->string('codigo_interno', 100)
                ->nullable()
                ->unique();

            $table->decimal('cantidad_actual', 12, 2)
                ->default(0);

            $table->decimal('stock_minimo', 12, 2)
                ->default(0);

            $table->text('observaciones')
                ->nullable();

            $table->boolean('activo')
                ->default(true);

            $table->foreignId('creado_por')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('herramientas');
    }
};