<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('route_assignments', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('sub');
            $table->boolean('is_active')->default(true)->after('server_url');
            $table->unsignedInteger('priority')->default(100)->after('is_active');
            $table->text('notes')->nullable()->after('priority');
            $table->timestamp('last_resolved_at')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('route_assignments', function (Blueprint $table) {
            $table->dropColumn([
                'display_name',
                'is_active',
                'priority',
                'notes',
                'last_resolved_at',
            ]);
        });
    }
};
