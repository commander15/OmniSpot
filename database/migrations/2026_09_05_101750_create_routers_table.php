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
        Schema::create('routers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('zone_id')->nullable()->references('zones')->cascadeOnDelete();
            $table->string('name', 30);
            $table->string('description', 255);
            $table->string('ip_address', 30);
            $table->char('mac_address', 17);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('routers');
    }
};
