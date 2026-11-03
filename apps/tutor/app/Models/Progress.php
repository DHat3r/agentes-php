<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Progress extends Model
{
    protected $table = 'progress';

    protected $fillable = ['topic', 'level', 'correct_streak', 'wrong_streak'];

    protected $attributes = ['level' => 1, 'correct_streak' => 0, 'wrong_streak' => 0];

    protected function casts(): array
    {
        return ['level' => 'integer', 'correct_streak' => 'integer', 'wrong_streak' => 'integer'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
