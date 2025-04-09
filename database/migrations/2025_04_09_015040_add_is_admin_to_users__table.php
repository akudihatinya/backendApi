<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('isadmin')->default(false)->after('nama_puskesmas');
            $table->foreignId('dinas_id')->nullable()->after('isadmin')->constrained('dinas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['dinas_id']);
            $table->dropColumn(['isadmin', 'dinas_id']);
        });
    }
};