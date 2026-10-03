<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $quotedOrderIds = DB::table('orders')->where('requires_quote', true)->pluck('id');
        DB::table('status_histories')->where('to_status', 'awaiting_confirmation')->whereIn('order_id', $quotedOrderIds)->update(['to_status' => 'pending_quote']);
        DB::table('status_histories')->where('from_status', 'awaiting_confirmation')->whereIn('order_id', $quotedOrderIds)->update(['from_status' => 'pending_quote']);
        DB::table('status_histories')->whereIn('to_status', ['awaiting_customer', 'awaiting_confirmation', 'awaiting_payment', 'confirmed'])->update(['to_status' => 'received']);
        DB::table('status_histories')->whereIn('from_status', ['awaiting_customer', 'awaiting_confirmation', 'awaiting_payment', 'confirmed'])->update(['from_status' => 'received']);
        DB::table('status_histories')->where('to_status', 'quote_expired')->update(['to_status' => 'pending_quote']);
        DB::table('status_histories')->where('from_status', 'quote_expired')->update(['from_status' => 'pending_quote']);
    }

    public function down(): void {}
};
