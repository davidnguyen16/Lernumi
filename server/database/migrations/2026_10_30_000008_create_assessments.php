<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('offering_id')->constrained()->restrictOnDelete();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->decimal('weight', 5, 2);
            $table->boolean('hurdle')->default(false);
            $table->boolean('is_group')->default(false);
            $table->timestampTz('publish_at')->nullable();
            $table->timestampTz('due_at')->nullable();
            $table->string('expected_due', 500)->nullable();
            $table->timestampsTz();
            $table->index(['offering_id', 'publish_at']);
        });
        DB::statement("ALTER TABLE assessments ADD CONSTRAINT assessment_values CHECK (length(btrim(name)) > 0 AND weight >= 0 AND weight <= 100 AND (due_at IS NULL OR publish_at IS NULL OR due_at >= publish_at))");
        Schema::create('assessment_allow_tools', function (Blueprint $table): void {
            $table->foreignUuid('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tool_id')->constrained()->restrictOnDelete();
            $table->primary(['assessment_id', 'tool_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_allow_tools');
        Schema::dropIfExists('assessments');
    }
};
