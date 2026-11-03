<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function progress(): HasMany
    {
        return $this->hasMany(Progress::class);
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class);
    }

    /** Progreso del tema; si es la primera vez, parte en nivel 1. */
    public function progressFor(string $topic): Progress
    {
        return $this->progress()->firstOrCreate(['topic' => $topic]);
    }
}
