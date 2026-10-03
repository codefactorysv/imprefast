<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_files', function (Blueprint $table) {
            $table->string('document_type', 20)->nullable()->after('type');
            $table->unsignedInteger('source_pages')->nullable()->after('document_type');
            $table->string('page_detection', 20)->nullable()->after('source_pages');
            $table->unsignedInteger('copies')->nullable()->after('page_detection');
            $table->unsignedInteger('print_units')->nullable()->after('copies');
            $table->string('paper_size', 20)->nullable()->after('print_units');
            $table->boolean('double_sided')->default(false)->after('paper_size');
            $table->boolean('stapled')->default(false)->after('double_sided');
            $table->decimal('unit_price', 10, 2)->nullable()->after('stapled');
            $table->decimal('subtotal', 10, 2)->nullable()->after('unit_price');
            $table->unsignedInteger('sort_order')->default(0)->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('order_files', function (Blueprint $table) {
            $table->dropColumn(['document_type', 'source_pages', 'page_detection', 'copies', 'print_units', 'paper_size', 'double_sided', 'stapled', 'unit_price', 'subtotal', 'sort_order']);
        });
    }
};
