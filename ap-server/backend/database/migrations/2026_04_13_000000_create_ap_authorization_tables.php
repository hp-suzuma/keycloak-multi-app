<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
            $table->timestamps();
        });

        Schema::create('scopes', function (Blueprint $table) {
            $table->id();
            $table->string('layer');
            $table->string('code');
            $table->string('name');
            $table->foreignId('parent_scope_id')->nullable()->constrained('scopes')->nullOnDelete();
            $table->timestamps();

            $table->unique(['layer', 'code']);
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('scope_layer');
            $table->string('permission_role');
            $table->string('slug')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['role_id', 'permission_id']);
        });

        Schema::create('user_role_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('keycloak_sub');
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scope_id')->constrained('scopes')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['keycloak_sub', 'role_id', 'scope_id']);
            $table
                ->foreign('keycloak_sub')
                ->references('keycloak_sub')
                ->on('ap_users')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_role_assignments');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('scopes');
        Schema::dropIfExists('ap_users');
    }
};
