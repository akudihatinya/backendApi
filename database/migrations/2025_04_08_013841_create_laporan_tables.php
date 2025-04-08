<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Laporan Bulanan
        Schema::create('laporan_bulanan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_program_id')->constrained('tahun_program');
            $table->foreignId('puskesmas_id')->constrained('puskesmas');
            $table->integer('bulan');
            $table->foreignId('status_id')->constrained('ref_status');
            $table->foreignId('petugas_id')->constrained('users');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        // Laporan Detail
        Schema::create('laporan_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_id')->constrained('laporan_bulanan')->onDelete('cascade');
            $table->foreignId('jenis_program_id')->constrained('ref_jenis_program');
            $table->foreignId('jenis_kelamin_id')->constrained('ref_jenis_kelamin');
            $table->foreignId('status_id')->constrained('ref_status');
            $table->integer('jumlah');
            $table->timestamps();
        });

        // Rekap Dinas
        Schema::create('rekap_dinas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_program_id')->constrained('tahun_program');
            $table->foreignId('dinas_id')->constrained('dinas');
            $table->integer('bulan');
            $table->foreignId('status_id')->constrained('ref_status');
            $table->timestamps();
        });

        // Rekap Detail
        Schema::create('rekap_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rekap_id')->constrained('rekap_dinas')->onDelete('cascade');
            $table->foreignId('puskesmas_id')->constrained('puskesmas');
            $table->foreignId('jenis_program_id')->constrained('ref_jenis_program');
            $table->foreignId('jenis_kelamin_id')->constrained('ref_jenis_kelamin');
            $table->foreignId('status_id')->constrained('ref_status');
            $table->integer('jumlah');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rekap_detail');
        Schema::dropIfExists('rekap_dinas');
        Schema::dropIfExists('laporan_detail');
        Schema::dropIfExists('laporan_bulanan');
    }
};
