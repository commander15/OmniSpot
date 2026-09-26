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
        Schema::create('zone_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('zone_id')->constrained('zones')->cascadeOnDelete();
            $table->foreignUuid('package_id')->constrained('internet_packages')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zone_packages');
    }
};
