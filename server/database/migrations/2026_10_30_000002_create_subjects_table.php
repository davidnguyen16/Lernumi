<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('institution_id')->constrained()->cascadeOnDelete();
            $table->string('subject_code', 20);
            $table->string('subject_name', 200);
            $table->string('subject_description', 1000)->nullable();
            $table->timestampsTz();

            // The same code is allowed at different institutions.
            $table->unique(['institution_id', 'subject_code']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE subjects
            ADD CONSTRAINT subjects_code_format_check
            CHECK (subject_code ~ '^[A-Z0-9-]{2,20}$'),
            ADD CONSTRAINT subjects_name_not_blank_check
            CHECK (length(btrim(subject_name)) > 0)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
