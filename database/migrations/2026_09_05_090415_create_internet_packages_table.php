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
        Schema::create('internet_packages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('owner_id')->constrained('users')->cascadeOnDelete();
            
            $table->string('name', 30);
            $table->string('description', 255)->nullable();
            
            $table->timestamps();
            
            $table->unique(['owner_id', 'name']);
        });

        Schema::create('internet_bundles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('package_id')->constrained('internet_packages')->cascadeOnDelete();

            $table->unsignedTinyInteger('number');
            $table->string('name', 30);
            $table->decimal('price', 12, 2);
            $table->string('short_code', 3);
            $table->string('description', 255)->nullable();

            $table->unsignedInteger('up_mbps')->nullable();
            $table->unsignedInteger('down_mbps')->nullable();
            $table->unsignedInteger('limit_mbs')->nullable();
            $table->unsignedInteger('device_count')->nullable();

            $table->unsignedInteger('duration_hours')->nullable();
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();

            $table->string('status', 10)->default('active');

            $table->timestamps();
            
            $table->unique(['package_id', 'number']);
            $table->unique(['package_id', 'name']);
        });

        Schema::create('internet_vouchers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->foreignUuid('bundle_id')->nullable()->constrained('internet_bundles')->nullOnDelete();

            $table->string('username');
            $table->string('password');
            $table->string('phone_number')->index();

            $table->unsignedInteger('up_mbps')->nullable();
            $table->unsignedInteger('down_mbps')->nullable();
            $table->unsignedInteger('duration_hours')->nullable();

            $table->unsignedSmallInteger('session_count')->nullable();
            $table->unsignedBigInteger('remaining_bytes')->nullable();

            $table->decimal('price', 12, 2);
            $table->boolean('generated')->default(false)->index();

            $table->timestamps();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->index()->nullable();

            $table->index(['zone_id', 'username']);
            $table->index(['zone_id', 'bundle_id']);
            $table->unique(['zone_id', 'username']);
        });

        Schema::create('internet_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('voucher_id')->constrained('internet_vouchers')->cascadeOnDelete();
            $table->foreignUuid('router_id')->constrained('zone_routers')->cascadeOnDelete();

            $table->string('session_id', 20);
            $table->string('mac_address', 17);

            $table->timestamps();

            $table->index(['router_id', 'mac_address']);
            $table->unique(['voucher_id', 'mac_address']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('internet_sessions');
        Schema::dropIfExists('internet_vouchers');
        Schema::dropIfExists('internet_bundles');
        Schema::dropIfExists('internet_packages');
    }
};
