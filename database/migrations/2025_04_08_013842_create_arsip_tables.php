<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Arsip Pemeriksaan
        Schema::create('arsip_pemeriksaan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_program_id')->constrained('tahun_program');
            $table->foreignId('pasien_id')->constrained('pasien');
            $table->unsignedBigInteger('pemeriksaan_id_original');
            $table->date('tgl_periksa');
            $table->foreignId('petugas_id')->constrained('users');
            $table->foreignId('jenis_program_id')->constrained('ref_jenis_program');
            $table->foreignId('status_id')->constrained('ref_status');
            $table->json('data_json');
            $table->timestamp('created_at')->useCurrent();
        });

        // Arsip Laporan
        Schema::create('arsip_laporan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_program_id')->constrained('tahun_program');
            $table->foreignId('puskesmas_id')->constrained('puskesmas');
            $table->unsignedBigInteger('laporan_id_original');
            $table->integer('bulan');
            $table->foreignId('status_id')->constrained('ref_status');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->json('data_json');
            $table->timestamp('created_at')->useCurrent();
        });

        // Arsip Sasaran
        Schema::create('arsip_sasaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_program_id')->constrained('tahun_program');
            $table->foreignId('dinas_id')->constrained('dinas');
            $table->unsignedBigInteger('sasaran_id_original');
            $table->json('data_json');
            $table->timestamp('created_at')->useCurrent();
        });

        // Arsip Pencapaian
        Schema::create('arsip_pencapaian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_program_id')->constrained('tahun_program');
            $table->foreignId('puskesmas_id')->constrained('puskesmas');
            $table->foreignId('jenis_program_id')->constrained('ref_jenis_program');
            $table->json('data_json');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arsip_pencapaian');
        Schema::dropIfExists('arsip_sasaran');
        Schema::dropIfExists('arsip_laporan');
        Schema::dropIfExists('arsip_pemeriksaan');
    }
};