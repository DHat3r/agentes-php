<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('queued')->index();
            $table->text('llm_answer')->nullable();
            $table->float('similarity_score')->nullable();
            $table->float('quality_score')->nullable();
            $table->float('completeness_score')->nullable();
            $table->float('overall_score')->nullable()->index();
            // Cuántas veces el agente pidió al juez evaluar una respuesta.
            $table->unsignedInteger('attempts')->default(0);
            $table->json('trace')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
