<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('auditorias', function (Blueprint $table) {
            $table->unsignedBigInteger('registro_id')->nullable()->change();
            $table->string('ip_address', 45)->nullable()->after('usuario_id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auditorias', function (Blueprint $table) {
            $table->dropIndex(['ip_address']);
            $table->dropColumn('ip_address');
        });

        DB::table('auditorias')->whereNull('registro_id')->update(['registro_id' => 0]);

        Schema::table('auditorias', function (Blueprint $table) {
            $table->unsignedBigInteger('registro_id')->nullable(false)->change();
        });
    }
};
