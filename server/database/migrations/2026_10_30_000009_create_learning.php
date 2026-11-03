<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_groups', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('assessment_id')->constrained('assessments')->restrictOnDelete();
            $table->string('name', 120);
            $table->foreignUuid('created_by_id')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
            $table->unique(['id', 'assessment_id']);
        });
        Schema::create('assessment_group_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('group_id');
            $table->uuid('assessment_id');
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
            $table->foreign(['group_id', 'assessment_id'])->references(['id', 'assessment_id'])->on('assessment_groups')->cascadeOnDelete();
            $table->unique(['group_id', 'user_id']);
            $table->unique(['assessment_id', 'user_id']);
        });
        Schema::create('assessment_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('assessment_id')->constrained('assessments')->restrictOnDelete();
            $table->uuid('group_id')->nullable();
            $table->foreign(['group_id', 'assessment_id'])->references(['id', 'assessment_id'])->on('assessment_groups')->restrictOnDelete();
            $table->timestampTz('start_at')->useCurrent();
            $table->timestampTz('submit_at')->nullable();
            $table->timestampsTz();
            $table->index(['assessment_id', 'user_id', 'start_at']);
        });
        Schema::create('assessment_attempt_marks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('attempt_id')->constrained('assessment_attempts')->restrictOnDelete();
            $table->foreignUuid('marked_by_id')->constrained('users')->restrictOnDelete();
            $table->decimal('score', 10, 2)->nullable();
            $table->decimal('out_of', 10, 2)->default(100);
            $table->text('feedback')->nullable();
            $table->string('status', 20)->default('DRAFT');
            $table->uuid('supersedes_mark_id')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampsTz();
            $table->unique(['id', 'attempt_id']);
            $table->foreign(['supersedes_mark_id', 'attempt_id'])->references(['id', 'attempt_id'])->on('assessment_attempt_marks')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE assessment_attempt_marks ADD CONSTRAINT assessment_marks_values CHECK (out_of > 0 AND out_of <= 1000000 AND (score IS NULL OR (score >= 0 AND score <= out_of)) AND status IN ('DRAFT','SUBMITTED','SUPERSEDED') AND ((status = 'DRAFT' AND submitted_at IS NULL) OR (status <> 'DRAFT' AND score IS NOT NULL AND submitted_at IS NOT NULL)))");
        DB::statement("CREATE UNIQUE INDEX assessment_marks_one_draft ON assessment_attempt_marks (attempt_id, marked_by_id) WHERE status = 'DRAFT'");
        DB::statement('ALTER TABLE assessment_attempts ADD CONSTRAINT assessment_attempt_dates CHECK (submit_at IS NULL OR submit_at >= start_at)');
        Schema::create('assessment_attempt_results', function (Blueprint $table): void {
            $table->uuid('attempt_id')->primary();
            $table->foreign('attempt_id')->references('id')->on('assessment_attempts')->restrictOnDelete();
            $table->uuid('mark_id');
            $table->foreign(['mark_id', 'attempt_id'])->references(['id', 'attempt_id'])->on('assessment_attempt_marks')->restrictOnDelete();
            $table->foreignUuid('finalised_by_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('finalised_at');
            $table->text('note')->nullable();
            $table->timestampsTz();
        });
        Schema::create('assessment_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->timestampsTz();
            $table->unique(['user_id', 'assessment_id']);
        });
        DB::statement('ALTER TABLE assessment_plans ADD CONSTRAINT assessment_plan_dates CHECK (end_date >= start_date)');
        Schema::create('calendar_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('offering_id')->nullable()->constrained('offerings')->nullOnDelete();
            $table->string('title', 120);
            $table->text('note')->nullable();
            $table->timestampTz('start_at');
            $table->timestampTz('end_at');
            $table->timestampsTz();
            $table->index(['user_id', 'start_at']);
        });
        DB::statement("ALTER TABLE calendar_events ADD CONSTRAINT calendar_event_dates CHECK (end_at > start_at AND end_at - start_at <= interval '24 hours')");
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
        Schema::dropIfExists('assessment_plans');
        Schema::dropIfExists('assessment_attempt_results');
        Schema::dropIfExists('assessment_attempt_marks');
        Schema::dropIfExists('assessment_attempts');
        Schema::dropIfExists('assessment_group_members');
        Schema::dropIfExists('assessment_groups');
    }
};
