<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departamentos', function (Blueprint $table) {
            $table->id();

            $table->string('nombre', 150);

            $table->string('sigla', 20)
                ->nullable();

            $table->boolean('activo')
                ->default(true);

            $table->foreignId('creado_por')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->unique(
                'nombre',
                'departamentos_nombre_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departamentos');
    }
};