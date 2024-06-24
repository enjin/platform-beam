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
        Schema::table('beam_batches', function (Blueprint $table) {
            $table->string('collection_chain_id')->index()->nullable()->after('transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('beam_batches', function (Blueprint $table) {
            $table->dropColumn('collection_chain_id');
        });
    }
};
