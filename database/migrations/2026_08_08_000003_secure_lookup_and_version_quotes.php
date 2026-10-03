<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY status ENUM('pending_quote','awaiting_customer','awaiting_confirmation','awaiting_payment','confirmed','preparing','ready','on_the_way','delivered','cancelled','quote_expired') NOT NULL");
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA ignore_check_constraints = ON');
        }
        Schema::table('quotes', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('order_id');
            $table->decimal('addons_total', 10, 2)->default(0)->after('surcharges');
            $table->decimal('total', 10, 2)->nullable()->after('addons_total');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['order_id', 'version']);
        });
        Schema::create('quote_notifications', function (Blueprint $table) {
            $table->id(); $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->string('driver')->default('manual'); $table->string('status')->default('pending')->index();
            $table->text('url')->nullable(); $table->text('message'); $table->string('provider_message_id')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('sent_at')->nullable();
            $table->timestamps(); $table->unique(['quote_id', 'driver']);
        });
        Schema::create('quote_responses', function (Blueprint $table) {
            $table->id(); $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete(); $table->string('decision');
            $table->string('channel')->default('whatsapp'); $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable(); $table->string('from_status'); $table->string('to_status'); $table->timestamp('responded_at'); $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('quote_responses'); Schema::dropIfExists('quote_notifications');
        Schema::table('quotes', fn (Blueprint $table) => $table->dropUnique(['order_id','version']));
        Schema::table('quotes', fn (Blueprint $table) => $table->dropColumn(['version','addons_total','total','created_by']));
    }
};
