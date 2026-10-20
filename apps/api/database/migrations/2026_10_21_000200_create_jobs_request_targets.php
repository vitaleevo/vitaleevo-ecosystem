<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobs_request_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->unique()->constrained('service_requests')->cascadeOnDelete();
            $table->foreignId('storefront_id')->constrained('storefronts')->restrictOnDelete();
            $table->foreignId('storefront_listing_id')->nullable()->constrained('storefront_listings')->nullOnDelete();
            $table->timestamps();
            $table->index(['storefront_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs_request_targets');
    }
};
