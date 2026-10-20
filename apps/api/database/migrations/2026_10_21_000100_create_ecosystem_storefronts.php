<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefronts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('owner_kind', 16);
            $table->string('app_code', 32);
            $table->string('slug', 190)->unique();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('province', 100)->nullable();
            $table->string('municipality', 100)->nullable();
            $table->string('status', 24)->default('draft');
            $table->timestamps();
            $table->index(['app_code', 'status', 'province']);
            $table->index(['tenant_id', 'status']);
            $table->index(['user_id', 'owner_kind']);
        });
        Schema::create('storefront_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('storefront_id')->constrained('storefronts')->cascadeOnDelete();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price_kz')->nullable();
            $table->string('price_mode', 16)->default('quote');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['storefront_id', 'active']);
        });
        Schema::create('tenant_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('module_code', 32);
            $table->string('status', 20)->default('inactive');
            $table->timestamps();
            $table->unique(['tenant_id', 'module_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_modules');
        Schema::dropIfExists('storefront_listings');
        Schema::dropIfExists('storefronts');
    }
};
