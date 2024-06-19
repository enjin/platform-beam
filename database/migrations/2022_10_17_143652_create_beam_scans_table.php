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
        Schema::create('beam_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beam_id')->index()->constrained();
            $table->string('wallet_public_key')->index();
            $table->string('message');
            $table->unsignedInteger('count')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beam_scans');
    }
};
