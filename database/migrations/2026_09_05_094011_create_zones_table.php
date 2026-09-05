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
        Schema::create('zones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('owner_id')->references('users')->cascadeOnDelete();
            $table->string('name', 30);
            $table->string('phone', 30);
            $table->string('description', 40);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('zone_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('zone_id')->references('zones')->cascadeOnDelete();
            $table->foreignUuid('package_id')->references('packages')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zone_packages');
        Schema::dropIfExists('zones');
    }
};
