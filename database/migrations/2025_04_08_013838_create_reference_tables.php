<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Referensi Jenis Kelamin
        Schema::create('ref_jenis_kelamin', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 1)->unique();
            $table->string('nama', 20);
            $table->timestamps();
        });

        // Referensi Jenis Program
        Schema::create('ref_jenis_program', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 5)->unique();
            $table->string('nama', 50);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        // Referensi Status
        Schema::create('ref_status', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();
            $table->string('nama', 50);
            $table->string('kategori', 30);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ref_status');
        Schema::dropIfExists('ref_jenis_program');
        Schema::dropIfExists('ref_jenis_kelamin');
    }
};