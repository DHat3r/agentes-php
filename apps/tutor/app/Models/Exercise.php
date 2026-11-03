<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Exercise extends Model
{
    protected $fillable = [
        'student_id', 'topic', 'level', 'statement', 'expected_answer',
        'student_answer', 'correct', 'feedback', 'level_adjusted', 'trace', 'answered_at',
    ];

    /** La respuesta esperada nunca sale en el JSON: el estudiante la vería en la red. */
    protected $hidden = ['expected_answer'];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'correct' => 'boolean',
            'level_adjusted' => 'boolean',
            'trace' => 'array',
            'answered_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function isAnswered(): bool
    {
        return $this->answered_at !== null;
    }
}
