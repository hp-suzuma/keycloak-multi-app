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
        Schema::create('playbooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scope_id')->constrained('scopes')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('created_by');
            $table->timestamp('created_at', 6);
            $table->text('updated_by')->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->boolean('is_deleted')->default(false);

        });

        DB::statement('CREATE UNIQUE INDEX playbooks_scope_code_active_unique ON playbooks (scope_id, code) WHERE is_deleted = false');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS playbooks_scope_code_active_unique');
        Schema::dropIfExists('playbooks');
    }
};
