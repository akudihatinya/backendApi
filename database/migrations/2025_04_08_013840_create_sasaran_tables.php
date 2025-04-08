<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sasaran Tahunan
        Schema::create('sasaran_tahunan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_program_id')->constrained('tahun_program');
            $table->foreignId('dinas_id')->constrained('dinas');
            $table->string('nama', 100);
            $table->text('keterangan')->nullable();
            $table->foreignId('status_id')->constrained('ref_status');
            $table->timestamps();
        });

        // Sasaran Puskesmas
        Schema::create('sasaran_puskesmas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sasaran_tahunan_id')->constrained('sasaran_tahunan')->onDelete('cascade');
            $table->foreignId('puskesmas_id')->constrained('puskesmas');
            $table->foreignId('jenis_program_id')->constrained('ref_jenis_program');
            $table->string('parameter', 30);
            $table->integer('nilai');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sasaran_puskesmas');
        Schema::dropIfExists('sasaran_tahunan');
    }
};