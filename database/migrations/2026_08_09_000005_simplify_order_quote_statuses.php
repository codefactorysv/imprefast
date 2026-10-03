<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('status_histories', function (Blueprint $table) {
            $table->string('origin', 30)->default('panel')->after('note');
            $table->text('reason')->nullable()->after('origin');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY status ENUM('received','pending_quote','awaiting_customer','awaiting_confirmation','awaiting_payment','confirmed','preparing','ready','on_the_way','delivered','cancelled','quote_expired') NOT NULL");
            DB::statement("ALTER TABLE quotes MODIFY status ENUM('draft','sent','pending','accepted','rejected','expired') NOT NULL");
        }

        DB::table('orders')->where('status', 'awaiting_confirmation')->where('requires_quote', true)->update(['status' => 'pending_quote']);
        DB::table('orders')->where('status', 'awaiting_confirmation')->where('requires_quote', false)->update(['status' => 'received']);
        DB::table('orders')->whereIn('status', ['awaiting_customer', 'awaiting_payment', 'confirmed'])->update(['status' => 'received']);
        DB::table('orders')->where('status', 'quote_expired')->update(['status' => 'pending_quote']);
        DB::table('quotes')->where('status', 'pending')->update(['status' => 'sent']);
        $quotedOrderIds = DB::table('orders')->where('requires_quote', true)->pluck('id');
        DB::table('status_histories')->where('to_status', 'awaiting_confirmation')->whereIn('order_id', $quotedOrderIds)->update(['to_status' => 'pending_quote']);
        DB::table('status_histories')->where('from_status', 'awaiting_confirmation')->whereIn('order_id', $quotedOrderIds)->update(['from_status' => 'pending_quote']);
        DB::table('status_histories')->whereIn('to_status', ['awaiting_customer', 'awaiting_confirmation', 'awaiting_payment', 'confirmed'])->update(['to_status' => 'received']);
        DB::table('status_histories')->whereIn('from_status', ['awaiting_customer', 'awaiting_confirmation', 'awaiting_payment', 'confirmed'])->update(['from_status' => 'received']);
        DB::table('status_histories')->where('to_status', 'quote_expired')->update(['to_status' => 'pending_quote']);
        DB::table('status_histories')->where('from_status', 'quote_expired')->update(['from_status' => 'pending_quote']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY status ENUM('received','pending_quote','preparing','ready','on_the_way','delivered','cancelled') NOT NULL");
            DB::statement("ALTER TABLE quotes MODIFY status ENUM('draft','sent','accepted','rejected','expired') NOT NULL DEFAULT 'draft'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY status VARCHAR(40) NOT NULL");
            DB::statement("ALTER TABLE quotes MODIFY status VARCHAR(40) NOT NULL");
        }
        Schema::table('status_histories', fn (Blueprint $table) => $table->dropColumn(['origin', 'reason']));
    }
};
