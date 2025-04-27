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
        Schema::create('monthly_statistics_cache', function (Blueprint $table) {
            $table->id();
            $table->foreignId('puskesmas_id')->constrained()->cascadeOnDelete();
            $table->enum('disease_type', ['ht', 'dm']);
            $table->integer('year');
            $table->integer('month');
            
            // Jumlah pasien berdasarkan gender
            $table->unsignedInteger('male_count')->default(0);
            $table->unsignedInteger('female_count')->default(0);
            $table->unsignedInteger('total_count')->default(0);
            
            // Jumlah pasien standar dan tidak standar
            $table->unsignedInteger('standard_count')->default(0);
            $table->unsignedInteger('non_standard_count')->default(0);
            
            // Persentase standar
            $table->decimal('standard_percentage', 8, 2)->default(0.00);
            
            $table->timestamps();
            
            // Unique constraint untuk mencegah duplikasi data
            $table->unique(['puskesmas_id', 'disease_type', 'year', 'month'], 'unique_monthly_stats');
            
            // Index untuk pencarian yang lebih cepat
            $table->index(['puskesmas_id', 'year', 'month'], 'idx_puskesmas_year_month');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_statistics_cache');
    }
};