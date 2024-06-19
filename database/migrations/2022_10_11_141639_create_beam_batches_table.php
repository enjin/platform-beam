<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('beam_batches', function (Blueprint $table) {
            $table->id();
            $table->string('beam_type')->default('TRANSFER_TOKEN');
            $table->timestamp('completed_at')->index()->nullable();
            $table->timestamp('processed_at')->index()->nullable();
            $table->foreignId('transaction_id')->index()->nullable()->constrained();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beam_batches');
    }
};
