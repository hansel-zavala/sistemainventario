<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_inventario', function (Blueprint $table) {

            $table->foreignId('departamento_destino_id')
                ->nullable()
                ->after('fecha_movimiento')
                ->constrained('departamentos')
                ->restrictOnDelete();

            $table->string('persona_recibe', 150)
                ->nullable()
                ->after('departamento_destino_id');

            $table->string('motivo', 255)
                ->nullable()
                ->after('persona_recibe');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_inventario', function (Blueprint $table) {

            $table->dropForeign([
                'departamento_destino_id'
            ]);

            $table->dropColumn([
                'departamento_destino_id',
                'persona_recibe',
                'motivo',
            ]);
        });
    }
};