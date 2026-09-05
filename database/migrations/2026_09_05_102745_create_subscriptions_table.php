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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('zone_id')->nullable()->references('zones')->nullOnDelete();
            $table->foreignUuid('bundle_id')->nullable()->references('bundles')->nullOnDelete();
            $table->string('username');
            $table->string('password');
            $table->decimal('price');
            $table->string('ip_address')->nullable();
            $table->string('mac_address')->nullable();
            $table->unsignedBigInteger('bytes_up')->default(0);
            $table->unsignedBigInteger('bytes_down')->default(0);
            $table->string('session_id', 30)->nullable();
            $table->unsignedBigInteger('session_duration')->default(0);
            $table->timestamps();
            $table->timestamp('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
