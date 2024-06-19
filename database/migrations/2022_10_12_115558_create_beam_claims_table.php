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
        Schema::create('beam_claims', function (Blueprint $table) {
            $table->id();
            $table->string('token_chain_id')->index()->nullable();
            $table->unsignedBigInteger('collection_id')->index();
            $table->foreignId('beam_id')->index()->constrained();
            $table->string('wallet_public_key')->index()->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->string('state')->nullable();
            $table->string('code')->index()->nullable();
            $table->unsignedInteger('nonce')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('type')->default('TRANSFER_TOKEN');
            $table->json('attributes')->nullable();
            $table->foreignId('beam_batch_id')->nullable()->index()->constrained();
            $table->string('ip_address')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beam_claims');
    }
};
