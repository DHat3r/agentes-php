<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // Un registro por estudiante y tema: nivel actual y rachas para la regla de ajuste.
        Schema::create('progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('topic');
            $table->unsignedTinyInteger('level')->default(1);
            $table->unsignedInteger('correct_streak')->default(0);
            $table->unsignedInteger('wrong_streak')->default(0);
            $table->timestamps();
            $table->unique(['student_id', 'topic']);
        });

        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('topic');
            $table->unsignedTinyInteger('level');
            $table->text('statement');
            $table->text('expected_answer');
            $table->text('student_answer')->nullable();
            $table->boolean('correct')->nullable();
            $table->text('feedback')->nullable();
            $table->boolean('level_adjusted')->default(false);
            $table->json('trace')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercises');
        Schema::dropIfExists('progress');
        Schema::dropIfExists('students');
    }
};
