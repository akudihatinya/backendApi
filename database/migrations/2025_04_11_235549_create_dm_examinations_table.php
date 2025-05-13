<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dm_examinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('puskesmas_id')->constrained()->cascadeOnDelete();
            $table->date('examination_date');
            $table->enum('examination_type', ['hba1c', 'gdp', 'gd2jpp', 'gdsp']);
            $table->decimal('result', 8, 2);
            $table->integer('year');
            $table->integer('month');
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dm_examinations');
    }
};