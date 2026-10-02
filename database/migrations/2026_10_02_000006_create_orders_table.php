<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 20)->unique();
            // Token acak untuk link pelacakan, supaya tidak bergantung pada tebakan kode pesanan.
            $table->string('tracking_token', 64)->unique();
            $table->string('customer_name');
            $table->string('customer_phone', 20);
            $table->string('fulfillment_type', 20);
            $table->text('address')->nullable();
            $table->foreignId('delivery_area_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('delivery_fee')->default(0);
            $table->unsignedInteger('total');
            $table->string('status', 30)->default('pending_payment');
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('customer_phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
