<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration tambahan yang backward-compatible (tidak menghapus atau mengubah data lama):
 * - orders.delivery_area_name: snapshot nama area saat pesanan dibuat.
 * - FK orders.delivery_area_id: SET NULL -> RESTRICT, supaya area yang dipakai pesanan
 *   tidak bisa terhapus dan memutus referensi pesanan historis.
 * - Index untuk laporan penjualan (orders.created_at, payments(order_id, status)).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_area_name')->nullable()->after('delivery_area_id');
        });

        // Isi snapshot untuk pesanan lama dari area yang masih terhubung.
        DB::table('delivery_areas')->orderBy('id')->each(function ($area) {
            DB::table('orders')
                ->where('delivery_area_id', $area->id)
                ->whereNull('delivery_area_name')
                ->update(['delivery_area_name' => $area->district]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['delivery_area_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('delivery_area_id')->references('id')->on('delivery_areas')->restrictOnDelete();
            $table->index('created_at');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        // MariaDB/MySQL bisa memakai index gabungan sebagai index FK payments.order_id,
        // sehingga index tunggal lama hilang. Pulihkan dulu sebelum index gabungan dihapus.
        if (! Schema::hasIndex('payments', ['order_id'])) {
            Schema::table('payments', function (Blueprint $table) {
                $table->index('order_id');
            });
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['order_id', 'status']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropForeign(['delivery_area_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('delivery_area_id')->references('id')->on('delivery_areas')->nullOnDelete();
            $table->dropColumn('delivery_area_name');
        });
    }
};
