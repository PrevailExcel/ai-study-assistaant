<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->onDelete('cascade');
            $table->longText('content');
            $table->integer('chunk_index');
            $table->integer('char_count');
            $table->integer('word_count');
            $table->json('metadata')->nullable();
            
            // Store Qdrant point ID instead of embedding vector
            $table->string('qdrant_point_id')->nullable()->index();
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['document_id', 'chunk_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
    }
};