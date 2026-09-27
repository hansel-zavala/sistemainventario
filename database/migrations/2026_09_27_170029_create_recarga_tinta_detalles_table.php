<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recarga_tinta_detalles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('recarga_tinta_id')
                ->constrained('recargas_tinta')
                ->cascadeOnDelete();

            $table->foreignId('tinta_id')
                ->constrained('tintas')
                ->restrictOnDelete();

            $table->decimal('porcentaje_antes', 5, 2);

            $table->decimal('porcentaje_agregado', 5, 2);

            $table->decimal('porcentaje_despues', 5, 2);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recarga_tinta_detalles');
    }
};