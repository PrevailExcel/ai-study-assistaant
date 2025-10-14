<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('summaries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('batch_id')->nullable()->index();
            $table->foreignUuid('user_id')->constrained('users');
            $table->foreignUuid('document_id')->constrained('documents');
            $table->foreignUuid('topic_id')->constrained('topics');
            $table->longText('content');
            $table->enum('type', ['brief', 'detailed', 'key_points', 'visual'])->default('brief'); // e.g., brief, detailed, key_points, visual
            $table->integer('max_length')->default(100);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('summaries');
    }
};
