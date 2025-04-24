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
            if (Schema::hasColumn('beams', 'fuel_tank_public_key')) {
                $table->dropColumn('fuel_tank_public_key');
            }

            if (Schema::hasColumn('beams', 'fuel_tank_rule_set_id')) {
                $table->dropColumn('fuel_tank_rule_set_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
