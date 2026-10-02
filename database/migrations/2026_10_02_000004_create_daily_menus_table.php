<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->date('menu_date');
            $table->unsignedInteger('price');
            $table->unsignedInteger('stock')->default(0);
            $table->time('available_from')->nullable();
            $table->time('available_until')->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->string('notes')->nullable();
            $table->timestamps();

            // Satu produk hanya boleh punya satu baris menu per tanggal.
            $table->unique(['product_id', 'menu_date']);
            $table->index(['menu_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_menus');
    }
};
