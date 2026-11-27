<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingresos_taller', function (Blueprint $table): void {
            $table->boolean('alta_inventario')->default(false);
            $table->foreignId('categoria_alta_id')->nullable()->constrained('categorias')->nullOnDelete();
            $table->string('numero_serie', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ingresos_taller', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('categoria_alta_id');
            $table->dropColumn(['alta_inventario', 'numero_serie']);
        });
    }
};
