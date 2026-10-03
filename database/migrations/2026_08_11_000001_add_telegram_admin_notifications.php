<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('reviewed_at')->nullable()->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::create('telegram_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('chat_id')->unique();
            $table->string('username')->nullable();
            $table->string('display_name')->nullable();
            $table->timestamp('connected_at');
            $table->timestamps();
        });
        Schema::create('telegram_link_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 20);
            $table->string('type', 40);
            $table->string('status', 20)->default('pending')->index();
            $table->string('telegram_message_id')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'channel', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notifications');
        Schema::dropIfExists('telegram_link_codes');
        Schema::dropIfExists('telegram_connections');
        Schema::table('orders', fn (Blueprint $table) => $table->dropConstrainedForeignId('reviewed_by'));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('reviewed_at'));
    }
};
