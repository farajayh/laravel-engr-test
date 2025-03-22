<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claims', function (Blueprint $table) {
            $table->id();
            $table->string('insurer_code')->index(); //indexed for better performance
            $table->string('provider_name')->index(); //indexed for better performance
            $table->date('encounter_date');
            $table->string('specialty');
            $table->integer('priority_level');
            $table->decimal('total_amount', 10, 2);
            $table->decimal('base_processing_cost',10,2)->default(0);
            $table->json('items'); //a different table could be created for this if needed
            $table->string('batch_id')->nullable()->index(); //indexed for performance. batches and batch_items table could be created for this if needed
            $table->date('batch_date')->nullable();
            $table->boolean('is_processed')->default(false);
            $table->date('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claims');
    }
}; 