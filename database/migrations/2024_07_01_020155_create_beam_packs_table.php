
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
        Schema::create('beam_packs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beam_id')->constrained()->cascadeOnDelete();
            $table->string('code')->index()->nullable();
            $table->unsignedInteger('nonce')->nullable();
            $table->boolean('is_claimed')->default(false)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beam_packs');
    }
};
