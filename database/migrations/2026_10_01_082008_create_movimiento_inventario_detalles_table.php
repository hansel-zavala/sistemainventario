<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimiento_inventario_detalles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('movimiento_inventario_id')
                ->constrained('movimientos_inventario')
                ->cascadeOnDelete();

            $table->string('tipo_item', 30);

            $table->unsignedBigInteger('item_id');

            $table->decimal('cantidad', 12, 2);

            $table->decimal('existencia_anterior', 12, 2);

            $table->decimal('existencia_posterior', 12, 2);

            $table->timestamps();

            $table->index([
                'tipo_item',
                'item_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'movimiento_inventario_detalles'
        );
    }
};