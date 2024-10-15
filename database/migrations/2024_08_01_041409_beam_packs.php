<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('beams', function (Blueprint $table) {
            $table->boolean('is_pack')->default(false)->index();
        });

        Schema::create('beam_packs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beam_id')->constrained()->cascadeOnDelete();
            $table->string('code')->index()->nullable();
            $table->unsignedInteger('nonce')->nullable();
            $table->boolean('is_claimed')->default(false)->index();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::table('beam_claims', function (Blueprint $table) {
            $table->foreignId('beam_pack_id')->index()->nullable()->constrained()->cascadeOnDelete();
            $table->dropUnique(['idempotency_key']);
            $table->index(['idempotency_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('beams', function (Blueprint $table) {
            $table->dropColumn('is_pack');
        });
        Schema::dropIfExists('beam_packs');
        Schema::table('beam_claims', function (Blueprint $table) {
            $table->dropColumn('beam_pack_id');
            $table->unique(['idempotency_key']);
        });
    }
};
