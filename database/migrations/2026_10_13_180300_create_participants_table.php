<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('import_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('profile_url');
            $table->string('profile_url_normalized');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('external_id')->nullable();
            $table->boolean('eligible')->default(true);
            $table->string('eligibility_note')->nullable();
            $table->boolean('is_winner')->default(false);
            $table->timestamps();

            $table->unique(['campaign_id', 'profile_url_normalized']);
            $table->unique(['campaign_id', 'external_id']);
            $table->index(['campaign_id', 'eligible']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participants');
    }
};
