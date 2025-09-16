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
        'title',
        'doc_id',
        'file_path',
        'file_type',
        'user_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime:Y-m-d H:i:s',
    ];

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

    public function flashcards()
    {
        return $this->hasMany(Flashcard::class);
    }

    public function topics()
    {
        return $this->hasMany(Topic::class);
    }
}
