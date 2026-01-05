<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Testing\Fluent\Concerns\Has;

class Document extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'title',
        'file_path',
        'file_type',
        'file_size',
        'status',
        'error_message',
        'processed_text',
        'metadata',
        'total_chunks',
        'error_message',
        'processing_started_at',
        'processing_completed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
                'created_at' => 'datetime:Y-m-d H:i:s',
        'processing_started_at' => 'datetime',
        'processing_completed_at' => 'datetime',
    ];

    public function embeddings()
    {
        return $this->hasMany(Embedding::class);
    }

    public function chunks()
    {
        return $this->hasMany(DocumentChunk::class);
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed' || 'processed';
    }

    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }
    
    public function getFilePathAttribute($value)
    {
        return url($value);
    }

    protected $hidden = [
        'updated_at',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    public function getCreatedAtFormattedAttribute(): string
    {
        return Carbon::parse($this->created_at)->format('jS M, Y');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function summaries()
    {
        return $this->hasMany(Summary::class);
    }

    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    public function quizzes()
    {
        return $this->hasMany(Quiz::class);
    }

    public function flashcards()
    {
        return $this->hasMany(Flashcard::class);
    }

    public function topics()
    {
        return $this->hasMany(Topic::class);
    }
}
