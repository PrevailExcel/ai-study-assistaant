<?php

namespace App\Models;

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
}
