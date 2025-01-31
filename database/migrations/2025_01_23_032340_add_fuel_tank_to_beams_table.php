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
            $table->string('fuel_tank_public_key')->nullable()->index()->after('source');
            $table->integer('fuel_tank_rule_set_id')->nullable()->after('fuel_tank_public_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('beams', function (Blueprint $table) {
            $table->dropColumn(['fuel_tank_public_key', 'fuel_tank_rule_set_id']);
        });
    }
};
