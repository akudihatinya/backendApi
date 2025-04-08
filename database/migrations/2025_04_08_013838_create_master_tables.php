<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dinas
        Schema::create('dinas', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 10)->unique();
            $table->string('nama', 100);
            $table->text('alamat')->nullable();
            $table->timestamps();
        });

        // Puskesmas
        Schema::create('puskesmas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dinas_id')->constrained('dinas');
            $table->string('kode', 10)->unique();
            $table->string('nama', 100);
            $table->text('alamat')->nullable();
            $table->timestamps();
        });

        // Tahun Program
        Schema::create('tahun_program', function (Blueprint $table) {
            $table->id();
            $table->integer('tahun')->unique();
            $table->string('nama', 50);
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai')->nullable();
            $table->boolean('is_active')->default(false);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('puskesmas');
        Schema::dropIfExists('dinas');
        Schema::dropIfExists('tahun_program');
    }
};