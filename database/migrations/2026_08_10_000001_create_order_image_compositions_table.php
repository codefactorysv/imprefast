<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_image_compositions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->json('order_file_ids');
            $table->unsignedTinyInteger('images_per_page');
            $table->unsignedInteger('resulting_pages');
            $table->unsignedInteger('copies');
            $table->unsignedInteger('billable_pages');
            $table->text('instructions')->nullable();
            $table->decimal('unit_price', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_image_compositions');
    }
};
