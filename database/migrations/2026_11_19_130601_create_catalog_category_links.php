<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categoria_tipo_equipo', function (Blueprint $table): void {
            $table->foreignId('categoria_id')->constrained('categorias')->cascadeOnDelete();
            $table->foreignId('tipo_equipo_id')->constrained('tipos_equipo')->cascadeOnDelete();
            $table->primary(['categoria_id', 'tipo_equipo_id']);
            $table->index('tipo_equipo_id');
        });

        Schema::create('categoria_marca', function (Blueprint $table): void {
            $table->foreignId('categoria_id')->constrained('categorias')->cascadeOnDelete();
            $table->foreignId('marca_id')->constrained('marcas')->cascadeOnDelete();
            $table->primary(['categoria_id', 'marca_id']);
            $table->index('marca_id');
        });

        DB::table('categoria_tipo_equipo')->insertUsing(
            ['categoria_id', 'tipo_equipo_id'],
            DB::table('equipos')
                ->join('categorias', 'categorias.id', '=', 'equipos.categoria_id')
                ->where('categorias.aplica_a', 'equipo')
                ->select('equipos.categoria_id', 'equipos.tipo_equipo_id')
                ->distinct(),
        );

        DB::table('categoria_marca')->insertUsing(
            ['categoria_id', 'marca_id'],
            DB::table('equipos')->select('categoria_id', 'marca_id')
                ->union(DB::table('herramientas')->select('categoria_id', 'marca_id')->whereNotNull('marca_id'))
                ->union(DB::table('insumos')->select('categoria_id', 'marca_id')->whereNotNull('marca_id')),
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('categoria_marca');
        Schema::dropIfExists('categoria_tipo_equipo');
    }
};
