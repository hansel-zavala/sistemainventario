<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingresos_taller', function (Blueprint $table): void {
            $table->string('categoria_nombre', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ingresos_taller', function (Blueprint $table): void {
            $table->dropColumn('categoria_nombre');
        });
    }
};
