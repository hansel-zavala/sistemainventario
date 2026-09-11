<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidades_medida', function (Blueprint $table) {
            $table->id();

            $table->string('nombre_singular', 50);

            $table->string('nombre_plural', 50);

            $table->string('abreviatura', 15)
                ->nullable();

            $table->boolean('permite_decimales')
                ->default(false);

            $table->boolean('activo')
                ->default(true);

            $table->foreignId('creado_por')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->unique(
                'nombre_singular',
                'unidades_medida_singular_unique'
            );

            $table->unique(
                'nombre_plural',
                'unidades_medida_plural_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidades_medida');
    }
};