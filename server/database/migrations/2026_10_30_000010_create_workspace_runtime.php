<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->timestampTz('last_opened_at')->nullable();
            $table->timestampsTz();
        });
        Schema::create('personal_workspaces', function (Blueprint $table): void {
            $table->uuid('workspace_id')->primary();
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
        });
        Schema::create('assignment_workspaces', function (Blueprint $table): void {
            $table->uuid('workspace_id')->primary();
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreignUuid('attempt_id')->unique()->constrained('assessment_attempts')->restrictOnDelete();
        });
        Schema::create('documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->uuid('parent_folder_id')->nullable();
            $table->string('entry_kind', 10);
            $table->char('content_hash', 64)->nullable();
            $table->string('content_key')->nullable();
            $table->string('mime_type', 150)->nullable();
            $table->unsignedInteger('size_bytes')->nullable();
            $table->timestampsTz();
            $table->unique(['workspace_id', 'id']);
            $table->foreign(['workspace_id', 'parent_folder_id'])->references(['workspace_id', 'id'])->on('documents')->cascadeOnDelete();
        });
        DB::statement('CREATE UNIQUE INDEX documents_sibling_name_unique ON documents (workspace_id, parent_folder_id, name) NULLS NOT DISTINCT');
        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_kind_check CHECK (entry_kind IN ('FILE','FOLDER')), ADD CONSTRAINT documents_not_self_parent CHECK (id <> parent_folder_id)");
        Schema::create('assignment_work_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('assignment_workspace_id');
            $table->foreign('assignment_workspace_id')->references('workspace_id')->on('assignment_workspaces')->cascadeOnDelete();
            $table->foreignUuid('tool_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('tool_version_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->timestampTz('start_at', 3);
            $table->timestampTz('end_at', 3)->nullable();
            $table->timestampsTz();
            $table->foreign(['tool_version_id', 'tool_id'])->references(['id', 'tool_id'])->on('tool_versions')->restrictOnDelete();
        });
        Schema::create('event_batches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('assignment_work_session_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_batch_id');
            $table->unsignedInteger('seq_start');
            $table->unsignedInteger('seq_end');
            $table->timestampTz('client_at', 3);
            $table->timestampTz('received_at', 3);
            $table->jsonb('events');
            $table->char('payload_hash', 64);
            $table->char('previous_chain_hash', 64)->nullable();
            $table->char('chain_hash', 64);
            $table->unique(['assignment_work_session_id', 'client_batch_id']);
            $table->unique(['assignment_work_session_id', 'seq_start']);
        });
        DB::statement('ALTER TABLE event_batches ADD CONSTRAINT event_sequence_range CHECK (seq_start >= 0 AND seq_end >= seq_start), ADD CONSTRAINT event_count_matches CHECK (jsonb_typeof(events) = \'array\' AND jsonb_array_length(events) = seq_end - seq_start + 1)');
        Schema::create('tool_runtime_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('login_session_id')->nullable()->index();
            $table->foreignUuid('role_assignment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tool_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('tool_version_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('work_session_id')->nullable()->constrained('assignment_work_sessions')->cascadeOnDelete();
            $table->string('mode', 20);
            $table->boolean('read_only');
            $table->jsonb('capabilities');
            $table->timestampTz('expires_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        foreach (['tool_runtime_sessions', 'event_batches', 'assignment_work_sessions', 'documents', 'assignment_workspaces', 'personal_workspaces', 'workspaces'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
