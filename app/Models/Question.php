<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $fillable = [
        'question_text',
        'options',
        'correct_answer',
        'user_answer',
        'explanation',
        'quiz_id'
    ];

    protected $casts = [
        'options' => 'array',
    ];

    protected $hidden = [
        "updated_at",
        "created_at"
    ];

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }
}
