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
            $table->foreignUuid('owner_id')->constrained('users')->cascadeOnDelete();

            $table->string('name', 30);
            $table->string('phone', 30);
            $table->string('description', 40)->nullable();
            $table->string('status', 10)->default('active');

            $table->timestamps();
        });

        Schema::create('zone_routers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('zone_id')->constrained('zones')->cascadeOnDelete();

            $table->string('name', 30);
            $table->string('net_address', 30);
            $table->string('mac_address', 30)->nullable();
            $table->string('description', 255)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zone_routers');
        Schema::dropIfExists('zones');
    }
};
