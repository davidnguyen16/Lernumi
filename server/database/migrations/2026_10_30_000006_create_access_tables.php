<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table): void {
            $table->string('code', 100)->primary();
            $table->string('description');
        });
        foreach (\App\Services\PermissionCatalog::codes() as $code) DB::table('permissions')->insert(['code' => $code, 'description' => ucfirst(str_replace(['.', '_'], ' ', $code)).'.']);
        Schema::create('roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('institution_id')->constrained()->restrictOnDelete();
            $table->string('role_code', 100);
            $table->string('role_name', 200);
            $table->string('scope_type', 20);
            $table->string('request_mode', 20)->default('DISABLED');
            $table->string('auto_grant_email_regex', 500)->nullable();
            $table->timestampsTz();
            $table->unique(['institution_id', 'role_code']);
        });
        DB::statement("ALTER TABLE roles ADD CONSTRAINT roles_scope_type_check CHECK (scope_type IN ('INSTITUTION','OFFERING') AND request_mode IN ('DISABLED','PUBLIC','CONDITIONAL'))");
        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->foreignUuid('role_id')->constrained()->cascadeOnDelete();
            $table->string('permission_code', 100);
            $table->foreign('permission_code')->references('code')->on('permissions')->restrictOnDelete();
            $table->primary(['role_id', 'permission_code']);
        });
        Schema::create('role_inclusions', function (Blueprint $table): void {
            $table->foreignUuid('including_role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignUuid('included_role_id')->constrained('roles')->restrictOnDelete();
            $table->primary(['including_role_id', 'included_role_id']);
        });
        Schema::create('role_request_visibilities', function (Blueprint $table): void {
            $table->foreignUuid('requestable_role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignUuid('required_role_id')->constrained('roles')->restrictOnDelete();
            $table->primary(['requestable_role_id', 'required_role_id']);
        });
        Schema::create('role_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('role_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('granted_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('granted_at')->useCurrent();
            $table->timestampTz('revoked_at')->nullable();
            $table->foreignUuid('revoked_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
        });
        DB::statement('CREATE UNIQUE INDEX role_assignments_active_unique ON role_assignments (user_id, role_id) WHERE revoked_at IS NULL');
        Schema::create('scope_assignment_codes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('offering_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('created_by_id')->constrained('users')->restrictOnDelete();
            $table->string('code_hash', 64)->unique();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('used_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->foreignUuid('revoked_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
        });
        Schema::create('scope_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('role_assignment_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('offering_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('granted_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignUuid('scope_assignment_code_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->timestampTz('granted_at')->useCurrent();
            $table->timestampTz('revoked_at')->nullable();
            $table->foreignUuid('revoked_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
        });
        DB::statement('CREATE UNIQUE INDEX scope_assignments_active_unique ON scope_assignments (role_assignment_id, offering_id) WHERE revoked_at IS NULL');
        Schema::create('role_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('institution_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('role_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('PENDING');
            $table->text('note')->nullable();
            $table->timestampTz('requested_at')->useCurrent();
            $table->timestampTz('processed_at')->nullable();
            $table->foreignUuid('processed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('approved_assignment_id')->nullable()->unique()->constrained('role_assignments')->restrictOnDelete();
            $table->timestampsTz();
        });
        DB::statement("CREATE UNIQUE INDEX role_requests_pending_unique ON role_requests (user_id, role_id) WHERE status = 'PENDING'");
        Schema::create('scope_assignment_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('role_assignment_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('offering_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('PENDING');
            $table->text('note')->nullable();
            $table->timestampTz('requested_at')->useCurrent();
            $table->timestampTz('processed_at')->nullable();
            $table->foreignUuid('processed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('approved_scope_assignment_id')->nullable()->unique()->constrained('scope_assignments')->restrictOnDelete();
            $table->timestampsTz();
        });
        DB::statement("CREATE UNIQUE INDEX scope_requests_pending_unique ON scope_assignment_requests (role_assignment_id, offering_id) WHERE status = 'PENDING'");
        foreach (['role_requests', 'scope_assignment_requests'] as $table) DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_status_check CHECK (status IN ('PENDING','APPROVED','REJECTED') AND ((status = 'PENDING' AND processed_at IS NULL) OR (status <> 'PENDING' AND processed_at IS NOT NULL)))");
        Schema::create('access_audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('institution_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);
            $table->uuid('subject_id')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['institution_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['access_audit_logs', 'scope_assignment_requests', 'role_requests', 'scope_assignments', 'scope_assignment_codes', 'role_assignments', 'role_request_visibilities', 'role_inclusions', 'role_permissions', 'roles', 'permissions'] as $table) Schema::dropIfExists($table);
    }
};
