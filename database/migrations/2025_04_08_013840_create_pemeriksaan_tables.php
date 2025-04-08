<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pemeriksaan
        Schema::create('pemeriksaan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_program_id')->constrained('tahun_program');
            $table->foreignId('pasien_id')->constrained('pasien');
            $table->foreignId('petugas_id')->constrained('users');
            $table->date('tgl_periksa');
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        // Pemeriksaan Parameter
        Schema::create('pemeriksaan_param', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pemeriksaan_id')->constrained('pemeriksaan')->onDelete('cascade');
            $table->foreignId('jenis_program_id')->constrained('ref_jenis_program');
            $table->string('nama_parameter', 30);
            $table->decimal('nilai', 10, 2);
            $table->timestamps();
        });

        // Pemeriksaan Status
        Schema::create('pemeriksaan_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pemeriksaan_id')->constrained('pemeriksaan')->onDelete('cascade');
            $table->foreignId('jenis_program_id')->constrained('ref_jenis_program');
            $table->foreignId('status_id')->constrained('ref_status');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pemeriksaan_status');
        Schema::dropIfExists('pemeriksaan_param');
        Schema::dropIfExists('pemeriksaan');
    }
};