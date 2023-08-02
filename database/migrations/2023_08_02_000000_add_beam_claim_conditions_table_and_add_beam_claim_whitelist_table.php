<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('beams', function (Blueprint $table) {
            $table->integer('claim_limit')->after('flags_mask')->default(1);
        });

        Schema::create('beam_claim_conditions', function (Blueprint $table) {
            $table->foreignId('beam_id')->references('id')->on('beams');
            $table->string('type');
            $table->string('value');
            $table->timestamps();
        });

        Schema::create('beam_claim_whitelist', function (Blueprint $table) {
            $table->foreignId('beam_id')->references('id')->on('beams');
            $table->string('address')->index();
            $table->timestamps();

            $table->unique(['beam_id', 'address']);
        });
    }

    public function down(): void
    {
        Schema::table('beams', function (Blueprint $table) {
            $table->dropColumn('claim_limit');
        });
        Schema::dropIfExists('beam_claim_conditions');
        Schema::dropIfExists('beam_claim_whitelist');
    }
};
