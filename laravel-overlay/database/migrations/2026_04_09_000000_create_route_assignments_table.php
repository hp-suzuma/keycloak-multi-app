<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('sub')->unique();
            $table->string('site_code', 16);
            $table->string('server_url');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_assignments');
    }
};
