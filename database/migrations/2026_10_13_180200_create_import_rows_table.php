<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('raw');
            $table->string('name')->nullable();
            $table->string('profile_url')->nullable();
            $table->string('profile_url_normalized')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('external_id')->nullable();
            $table->string('status');
            $table->json('errors')->nullable();
            $table->timestamps();

            $table->index(['import_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
    }
};
