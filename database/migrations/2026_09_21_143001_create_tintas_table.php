<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tintas', function (Blueprint $table) {
            $table->id();

            $table->string('nombre', 150);

            $table->foreignId('marca_id')
                ->nullable()
                ->constrained('marcas')
                ->restrictOnDelete();

            $table->string('color', 50);

            $table->string('presentacion', 100)
                ->nullable();

            $table->unsignedInteger('botellas_completas')
                ->default(0);

            $table->decimal(
                'porcentaje_botella_abierta',
                5,
                2
            )->default(0);

            $table->decimal(
                'stock_minimo_botellas',
                8,
                2
            )->default(1);

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
        Schema::dropIfExists('tintas');
    }
};