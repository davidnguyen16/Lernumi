<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table): void {
            $table->string('website', 2048)->nullable()->change();
            $table->string('logo_key', 2048)->nullable();
        });
        Schema::table('subjects', fn (Blueprint $table) => $table->unique(['id', 'institution_id']));

        Schema::create('study_terms', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('institution_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedSmallInteger('session');
            $table->timestampsTz();
            $table->unique(['institution_id', 'year', 'session']);
            $table->unique(['id', 'institution_id']);
        });
        DB::statement('ALTER TABLE study_terms ADD CONSTRAINT study_term_number_range CHECK (year BETWEEN 1000 AND 9999 AND session BETWEEN 1 AND 99)');

        Schema::create('study_term_periods', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('study_term_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('type', 20);
            $table->timestampTz('start_at');
            $table->timestampTz('end_at');
            $table->timestampsTz();
            $table->index(['study_term_id', 'start_at']);
        });
        DB::statement("ALTER TABLE study_term_periods ADD CONSTRAINT study_term_period_values CHECK (length(btrim(name)) > 0 AND type IN ('TEACHING','BREAK','EXAM') AND end_at > start_at)");

        Schema::create('offerings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('institution_id')->constrained()->restrictOnDelete();
            $table->uuid('subject_id');
            $table->uuid('term_id');
            $table->timestampsTz();
            // Composite keys make cross-institution subject/term combinations impossible.
            $table->foreign(['subject_id', 'institution_id'])->references(['id', 'institution_id'])->on('subjects')->restrictOnDelete();
            $table->foreign(['term_id', 'institution_id'])->references(['id', 'institution_id'])->on('study_terms')->restrictOnDelete();
            $table->unique(['subject_id', 'term_id']);
            $table->index('term_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offerings');
        Schema::dropIfExists('study_term_periods');
        Schema::dropIfExists('study_terms');
        Schema::table('subjects', fn (Blueprint $table) => $table->dropUnique(['id', 'institution_id']));
        Schema::table('institutions', function (Blueprint $table): void {
            $table->string('website', 255)->nullable()->change();
            $table->dropColumn('logo_key');
        });
    }
};
