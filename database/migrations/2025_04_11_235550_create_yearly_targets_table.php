<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yearly_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('puskesmas_id')->constrained()->cascadeOnDelete();
            $table->enum('disease_type', ['ht', 'dm']);
            $table->integer('year');
            $table->integer('target_count');
            $table->timestamps();
            
            $table->unique(['puskesmas_id', 'disease_type', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yearly_targets');
    }
};