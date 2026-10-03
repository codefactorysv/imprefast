<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_location_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending')->index();
            $table->boolean('direct')->default(false);
            $table->json('old_location');
            $table->json('new_location');
            $table->decimal('old_distance_km', 8, 2)->nullable();
            $table->decimal('new_distance_km', 8, 2)->nullable();
            $table->unsignedInteger('new_duration_minutes')->nullable();
            $table->decimal('old_delivery_fee', 10, 2)->nullable();
            $table->decimal('new_delivery_fee', 10, 2)->nullable();
            $table->json('rate_snapshot')->nullable();
            $table->text('customer_message')->nullable();
            $table->string('order_status_at_request');
            $table->text('review_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('admin_seen_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('order_location_changes'); }
};
