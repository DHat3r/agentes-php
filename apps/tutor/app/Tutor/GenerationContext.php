<?php

namespace App\Tutor;

use App\Models\Exercise;
use App\Models\Progress;
use App\Models\Student;

/** Estado compartido por las herramientas mientras el agente crea un ejercicio. */
final class GenerationContext
{
    public ?Exercise $exercise = null;

    public function __construct(
        public readonly Student $student,
        public readonly string $topic,
    ) {}

    public function progress(): Progress
    {
        return $this->student->progressFor($this->topic);
    }
}
