<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('short_description')->nullable()->after('slug');
            $table->string('pricing_model')->default('per_page')->index()->after('calculation_type');
            $table->string('billing_unit')->default('página')->after('price_per_page');
            $table->boolean('requires_file')->default(false);
            $table->boolean('requires_pages')->default(false);
            $table->boolean('requires_quantity')->default(false);
            $table->boolean('allows_letter')->default(true);
            $table->boolean('allows_legal')->default(true);
            $table->boolean('allows_duplex')->default(true);
            $table->boolean('allows_urgent')->default(true);
            $table->softDeletes();
        });

        Schema::create('service_variants', function (Blueprint $table) {
            $table->id(); $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('name'); $table->string('group_name')->default('Opción');
            $table->enum('price_effect',['surcharge','replace'])->default('surcharge');
            $table->decimal('price',10,2)->default(0); $table->boolean('active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0); $table->timestamps(); $table->softDeletes();
        });
        Schema::create('service_price_tiers', function (Blueprint $table) {
            $table->id(); $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('minimum_quantity'); $table->unsignedInteger('maximum_quantity')->nullable();
            $table->decimal('price',10,2); $table->string('label'); $table->boolean('active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0); $table->timestamps(); $table->softDeletes();
        });
        Schema::create('service_addons', function (Blueprint $table) {
            $table->id(); $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('name'); $table->text('description')->nullable();
            $table->enum('price_type',['fixed','per_unit'])->default('fixed'); $table->decimal('price',10,2);
            $table->boolean('active')->default(true)->index(); $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps(); $table->softDeletes();
        });
        Schema::create('order_items', function (Blueprint $table) {
            $table->id(); $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service_name'); $table->string('pricing_model'); $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price',10,2)->nullable(); $table->decimal('subtotal',10,2)->nullable();
            $table->json('snapshot'); $table->timestamps();
        });
        Schema::create('order_item_options', function (Blueprint $table) {
            $table->id(); $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->string('type'); $table->string('name'); $table->decimal('price',10,2)->default(0);
            $table->json('snapshot')->nullable(); $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_options'); Schema::dropIfExists('order_items');
        Schema::dropIfExists('service_addons'); Schema::dropIfExists('service_price_tiers'); Schema::dropIfExists('service_variants');
        Schema::table('services', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['short_description','pricing_model','billing_unit','requires_file','requires_pages','requires_quantity','allows_letter','allows_legal','allows_duplex','allows_urgent']);
        });
    }
};
