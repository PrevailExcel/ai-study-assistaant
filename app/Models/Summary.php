<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Summary extends Model
{

    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'user_id',
        'document_id',
        'topic_id',
        'topic_index',
        'content',
        'type',
        'max_length'
    ];
    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected $hidden = [
        "updated_at",
        "created_at"
    ];

}
