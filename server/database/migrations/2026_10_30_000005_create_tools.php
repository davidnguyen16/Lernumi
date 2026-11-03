<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tools', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 64)->unique();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('code_base_location', 2048)->nullable();
            $table->uuid('active_version_id')->nullable();
            $table->timestampsTz();
        });
        Schema::create('tool_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tool_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('created_by_id')->constrained('users')->restrictOnDelete();
            $table->string('version', 100);
            $table->string('entry_path', 512);
            $table->jsonb('manifest');
            $table->string('archive_key')->unique();
            $table->string('storage_prefix')->unique();
            $table->char('sha256', 64);
            $table->unsignedInteger('compressed_size');
            $table->unsignedInteger('unpacked_size');
            $table->unsignedInteger('file_count');
            $table->text('release_notes')->nullable();
            $table->timestampTz('created_at');
            $table->unique(['tool_id', 'version']);
            $table->unique(['id', 'tool_id']);
        });
        Schema::table('tools', function (Blueprint $table): void {
            $table->foreign('active_version_id')->references('id')->on('tool_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tools', fn (Blueprint $table) => $table->dropForeign(['active_version_id']));
        Schema::dropIfExists('tool_versions');
        Schema::dropIfExists('tools');
    }
};
