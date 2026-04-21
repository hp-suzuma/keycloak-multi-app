<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ap_users', function (Blueprint $table) {
            $table->string('keycloak_sub')->primary();
            $table->string('display_name')->nullable();
            $table->string('email')->nullable();
            $table->text('created_by');
            $table->timestamp('created_at', 6);
            $table->text('updated_by')->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->boolean('is_deleted')->default(false);
        });

        Schema::create('scopes', function (Blueprint $table) {
            $table->id();
            $table->string('layer');
            $table->string('code');
            $table->string('name');
            $table->foreignId('parent_scope_id')->nullable()->constrained('scopes')->nullOnDelete();
            $table->text('created_by');
            $table->timestamp('created_at', 6);
            $table->text('updated_by')->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->boolean('is_deleted')->default(false);

        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('scope_layer');
            $table->string('permission_role');
            $table->string('slug');
            $table->string('name');
            $table->text('created_by');
            $table->timestamp('created_at', 6);
            $table->text('updated_by')->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->boolean('is_deleted')->default(false);
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->string('name');
            $table->text('created_by');
            $table->timestamp('created_at', 6);
            $table->text('updated_by')->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->boolean('is_deleted')->default(false);
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->text('created_by');
            $table->timestamp('created_at', 6);
            $table->text('updated_by')->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->boolean('is_deleted')->default(false);

        });

        Schema::create('user_role_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('keycloak_sub');
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scope_id')->constrained('scopes')->cascadeOnDelete();
            $table->text('created_by');
            $table->timestamp('created_at', 6);
            $table->text('updated_by')->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->boolean('is_deleted')->default(false);

            $table
                ->foreign('keycloak_sub')
                ->references('keycloak_sub')
                ->on('ap_users')
                ->cascadeOnDelete();
        });

        DB::statement("CREATE UNIQUE INDEX scopes_layer_code_active_unique ON scopes (layer, code) WHERE is_deleted = false");
        DB::statement("CREATE UNIQUE INDEX roles_slug_active_unique ON roles (slug) WHERE is_deleted = false");
        DB::statement("CREATE UNIQUE INDEX permissions_slug_active_unique ON permissions (slug) WHERE is_deleted = false");
        DB::statement('CREATE UNIQUE INDEX role_permissions_role_permission_active_unique ON role_permissions (role_id, permission_id) WHERE is_deleted = false');
        DB::statement('CREATE UNIQUE INDEX user_role_assignments_active_unique ON user_role_assignments (keycloak_sub, role_id, scope_id) WHERE is_deleted = false');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS user_role_assignments_active_unique');
        DB::statement('DROP INDEX IF EXISTS role_permissions_role_permission_active_unique');
        DB::statement('DROP INDEX IF EXISTS permissions_slug_active_unique');
        DB::statement('DROP INDEX IF EXISTS roles_slug_active_unique');
        DB::statement('DROP INDEX IF EXISTS scopes_layer_code_active_unique');
        Schema::dropIfExists('user_role_assignments');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('scopes');
        Schema::dropIfExists('ap_users');
    }
};
