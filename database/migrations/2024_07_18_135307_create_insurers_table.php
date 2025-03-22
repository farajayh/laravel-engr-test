<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('email')->nullable();
            $table->enum(
                'claim_date_preference', 
                ['submission_date','encounter_date']
            )->default('submission_date'); //a reference table could be used
            $table->string('daily_processing_capacity');
            $table->integer('min_batch_size');
            $table->integer('max_batch_size');
            $table->enum(
                'specialty',
                ['Cardiology', 'Orthopedics', 'Neurology', 'Oncology', 'Pediatrics']
            ); //a reference table could be used
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurers');
    }
}; 