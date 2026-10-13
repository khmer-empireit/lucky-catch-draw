<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draws', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->foreignId('participant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('pool_size');
            $table->string('pool_hash', 64);
            $table->json('pool_snapshot');
            $table->foreignId('drawn_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('drawn_at');
            $table->string('status')->default('valid');
            $table->string('voided_reason')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('voided_at')->nullable();
            $table->string('idempotency_key')->unique();
            $table->timestamps();

            $table->unsignedInteger('active_sequence')
                ->nullable()
                ->storedAs("if(`status` = 'valid', `sequence`, null)");

            $table->unique(['campaign_id', 'active_sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draws');
    }
};
