<?php

namespace App\Models;

use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory;

    protected $fillable = ['question_text', 'human_answer', 'category'];

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }
}
