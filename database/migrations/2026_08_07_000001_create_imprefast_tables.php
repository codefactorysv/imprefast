<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', fn (Blueprint $t) => $t->boolean('is_admin')->default(false)->index());
        Schema::create('services', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('slug')->unique(); $t->text('description');
            $t->string('icon')->default('🖨️'); $t->enum('calculation_type',['automatic','quote']);
            $t->decimal('price_per_page',8,2)->nullable(); $t->boolean('pickup_return')->default(false);
            $t->text('instructions')->nullable(); $t->boolean('active')->default(true); $t->unsignedInteger('sort_order')->default(0); $t->timestamps();
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->id(); $t->string('code')->nullable()->unique(); $t->foreignId('service_id')->constrained();
            $t->string('customer_name'); $t->string('phone',8)->index(); $t->string('email')->nullable();
            $t->enum('status',['pending_quote','awaiting_customer','awaiting_payment','confirmed','preparing','ready','on_the_way','delivered','cancelled'])->index();
            $t->enum('payment_method',['cash','transfer']); $t->enum('payment_status',['pending','pending_verification','partial','paid','rejected','refunded'])->default('pending');
            $t->unsignedInteger('pages')->nullable(); $t->unsignedInteger('copies')->nullable(); $t->string('paper_size')->nullable(); $t->boolean('double_sided')->default(false);
            $t->string('orientation')->nullable(); $t->boolean('cover')->default(false); $t->boolean('stapled')->default(false); $t->text('details')->nullable(); $t->text('instructions')->nullable();
            $t->timestamp('requested_at'); $t->boolean('urgent')->default(false); $t->decimal('work_subtotal',10,2)->nullable();
            $t->decimal('delivery_fee',10,2)->nullable(); $t->decimal('total',10,2)->nullable(); $t->decimal('verified_paid',10,2)->default(0);
            $t->string('department')->default('San Miguel'); $t->string('municipality'); $t->string('neighborhood')->nullable(); $t->string('street')->nullable(); $t->string('house_number')->nullable(); $t->text('address_reference')->nullable();
            $t->decimal('latitude',10,7)->nullable(); $t->decimal('longitude',10,7)->nullable(); $t->decimal('distance_km',8,2)->nullable(); $t->unsignedInteger('duration_minutes')->nullable(); $t->string('route_provider')->nullable(); $t->timestamp('route_calculated_at')->nullable();
            $t->decimal('base_fee_snapshot',8,2)->nullable(); $t->decimal('price_per_km_snapshot',8,2)->nullable(); $t->decimal('minimum_fee_snapshot',8,2)->nullable(); $t->decimal('pickup_factor_snapshot',8,2)->nullable(); $t->decimal('urgent_surcharge_snapshot',8,2)->nullable();
            $t->string('public_token',64)->unique(); $t->text('internal_notes')->nullable(); $t->timestamps();
        });
        Schema::create('order_files', function (Blueprint $t) { $t->id(); $t->foreignId('order_id')->constrained()->cascadeOnDelete(); $t->string('disk')->default('local'); $t->string('path'); $t->string('original_name'); $t->string('mime'); $t->unsignedBigInteger('size'); $t->enum('type',['document','facade','payment_proof'])->default('document'); $t->timestamp('expires_at')->nullable(); $t->timestamps(); });
        Schema::create('quotes', function (Blueprint $t) { $t->id(); $t->foreignId('order_id')->constrained()->cascadeOnDelete(); $t->decimal('work_price',10,2); $t->decimal('delivery_price',10,2); $t->decimal('surcharges',10,2)->default(0); $t->decimal('deposit_required',10,2)->default(0); $t->text('notes')->nullable(); $t->timestamp('expires_at'); $t->enum('status',['pending','accepted','rejected','expired'])->default('pending'); $t->timestamp('responded_at')->nullable(); $t->string('token',64)->unique(); $t->timestamps(); });
        Schema::create('payments', function (Blueprint $t) { $t->id(); $t->foreignId('order_id')->constrained(); $t->decimal('amount',10,2); $t->string('method'); $t->enum('status',['pending_verification','verified','rejected','refunded']); $t->foreignId('verified_by')->nullable()->constrained('users'); $t->timestamp('verified_at')->nullable(); $t->text('notes')->nullable(); $t->timestamps(); });
        Schema::create('bank_accounts', function (Blueprint $t) { $t->id(); $t->string('bank'); $t->string('holder'); $t->string('account_type'); $t->string('account_number'); $t->text('instructions')->nullable(); $t->boolean('active')->default(true); $t->timestamps(); });
        Schema::create('settings', function (Blueprint $t) { $t->id(); $t->string('key')->unique(); $t->text('value')->nullable(); $t->string('type')->default('string'); $t->boolean('is_public')->default(false); $t->timestamps(); });
        Schema::create('status_histories', function (Blueprint $t) { $t->id(); $t->foreignId('order_id')->constrained()->cascadeOnDelete(); $t->string('from_status')->nullable(); $t->string('to_status'); $t->foreignId('user_id')->nullable()->constrained(); $t->text('note')->nullable(); $t->timestamps(); });
        Schema::create('audit_logs', function (Blueprint $t) { $t->id(); $t->foreignId('user_id')->nullable()->constrained(); $t->string('action'); $t->nullableMorphs('auditable'); $t->json('old_values')->nullable(); $t->json('new_values')->nullable(); $t->text('reason')->nullable(); $t->string('ip_address',45)->nullable(); $t->timestamps(); });
    }
    public function down(): void { foreach (['audit_logs','status_histories','settings','bank_accounts','payments','quotes','order_files','orders','services'] as $table) Schema::dropIfExists($table); Schema::table('users', fn(Blueprint $t) => $t->dropColumn('is_admin')); }
};
