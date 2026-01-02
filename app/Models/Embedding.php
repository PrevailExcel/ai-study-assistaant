<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Embedding extends Model
{
    protected $fillable = [
        'document_id',
        'chunk_text',
        'vector_id',
        'chunk_index',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }
}
