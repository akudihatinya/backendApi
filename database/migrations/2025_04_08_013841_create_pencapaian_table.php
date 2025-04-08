<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pencapaian_bulanan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_program_id')->constrained('tahun_program');
            $table->foreignId('puskesmas_id')->constrained('puskesmas');
            $table->foreignId('jenis_program_id')->constrained('ref_jenis_program');
            $table->integer('bulan');
            $table->string('parameter', 30);
            $table->integer('nilai');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pencapaian_bulanan');
    }
};
