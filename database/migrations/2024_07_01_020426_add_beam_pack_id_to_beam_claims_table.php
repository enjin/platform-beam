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
        Schema::table('beam_claims', function (Blueprint $table) {
            $table->dropColumn('beam_pack_id');
            $table->unique(['idempotency_key']);
        });
    }
};
