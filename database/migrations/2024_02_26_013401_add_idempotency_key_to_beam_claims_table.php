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
            $table->string('idempotency_key', 255)->nullable()->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('beam_claims', function (Blueprint $table) {
            $table->dropColumn('idempotency_key');
        });
    }
};
