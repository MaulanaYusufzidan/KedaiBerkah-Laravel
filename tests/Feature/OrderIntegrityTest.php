<?php

namespace Tests\Feature;

use App\Models\DeliveryArea;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_timezone_is_jakarta(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->assertSame('Asia/Jakarta', now()->timezoneName);
    }

    public function test_timestamps_are_stored_in_jakarta_time(): void
    {
        Carbon::setTestNow('2026-10-02 00:30:00'); // 00:30 WIB = 17:30 UTC hari sebelumnya
        $order = Order::factory()->create();
        Carbon::setTestNow();

        $this->assertSame('2026-10-02 00:30:00', $order->fresh()->getRawOriginal('created_at'));
    }

    public function test_delivery_area_used_by_an_order_cannot_be_deleted_at_database_level(): void
    {
        $area = DeliveryArea::factory()->create();
        $order = Order::factory()->create(['delivery_area_id' => $area->id, 'delivery_area_name' => $area->district]);

        try {
            $area->delete();
            $this->fail('Area yang dipakai pesanan seharusnya tidak bisa dihapus.');
        } catch (QueryException) {
            // diharapkan: FK RESTRICT
        }

        $this->assertDatabaseHas('delivery_areas', ['id' => $area->id]);
        $this->assertSame($area->id, $order->fresh()->delivery_area_id);
    }

    public function test_unused_delivery_area_can_be_deleted(): void
    {
        $area = DeliveryArea::factory()->create();

        $area->delete();

        $this->assertDatabaseMissing('delivery_areas', ['id' => $area->id]);
    }

    public function test_order_keeps_area_snapshot_when_area_is_renamed_or_fee_changes(): void
    {
        $area = DeliveryArea::factory()->create(['district' => 'Tanah Sareal', 'delivery_fee' => 5000]);
        $order = Order::factory()->create([
            'delivery_area_id' => $area->id,
            'delivery_area_name' => 'Tanah Sareal',
            'delivery_fee' => 5000,
        ]);

        $area->update(['district' => 'Tanah Sareal Baru', 'delivery_fee' => 9000]);

        $order->refresh();
        $this->assertSame('Tanah Sareal', $order->delivery_area_name);
        $this->assertSame(5000, $order->delivery_fee);
    }

    public function test_active_scope_excludes_inactive_areas(): void
    {
        DeliveryArea::factory()->create(['district' => 'Aktif', 'is_active' => true]);
        DeliveryArea::factory()->create(['district' => 'Nonaktif', 'is_active' => false]);

        $this->assertSame(['Aktif'], DeliveryArea::active()->pluck('district')->all());
    }

    public function test_report_indexes_exist(): void
    {
        $this->assertTrue(Schema::hasIndex('orders', ['created_at']));
        $this->assertTrue(Schema::hasIndex('payments', ['order_id', 'status']));
    }

    public function test_realized_scope_counts_only_paid_and_not_cancelled_without_duplication(): void
    {
        $paid = Order::factory()->create(['total' => 30000]);
        Payment::factory()->create(['order_id' => $paid->id, 'status' => 'paid']);
        Payment::factory()->create(['order_id' => $paid->id, 'status' => 'paid']); // dua baris paid

        $unpaid = Order::factory()->create();
        Payment::factory()->create(['order_id' => $unpaid->id, 'status' => 'pending']);

        $rejected = Order::factory()->create();
        Payment::factory()->create(['order_id' => $rejected->id, 'status' => 'rejected']);

        $cancelled = Order::factory()->create(['status' => 'cancelled']);
        Payment::factory()->create(['order_id' => $cancelled->id, 'status' => 'paid']);

        Order::factory()->create(); // tanpa pembayaran

        $this->assertSame([$paid->id], Order::realized()->pluck('id')->all());
        $this->assertSame(30000, (int) Order::realized()->sum('total'));
    }
}
