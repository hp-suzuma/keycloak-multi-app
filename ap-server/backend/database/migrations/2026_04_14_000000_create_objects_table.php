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
        Schema::create('objects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scope_id')->constrained('scopes')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->timestamps();

            $table->unique(['scope_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('objects');
    }
};
