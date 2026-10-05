<?php

namespace Tests\Feature;

use App\Models\DeliveryArea;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminDeliveryAreaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::factory()->admin()->create());
    }

    private function valid(array $override = []): array
    {
        return array_merge(['district' => 'Tanah Sareal', 'delivery_fee' => 5000, 'is_active' => 1], $override);
    }

    private function orderIn(DeliveryArea $area, array $attributes = []): Order
    {
        return Order::factory()->create(array_merge([
            'delivery_area_id' => $area->id,
            'delivery_area_name' => $area->district,
            'delivery_fee' => $area->delivery_fee,
            'fulfillment_type' => 'delivery',
        ], $attributes));
    }

    // ---------- CRUD ----------

    public function test_admin_can_list_areas_with_fee_status_and_usage(): void
    {
        $used = DeliveryArea::factory()->create(['district' => 'Bogor Barat', 'delivery_fee' => 7000]);
        $this->orderIn($used);
        $this->orderIn($used);
        DeliveryArea::factory()->create(['district' => 'Bogor Utara', 'is_active' => false]);

        $this->get('/admin/delivery-areas')
            ->assertOk()
            ->assertSee('Bogor Barat')
            ->assertSee('Rp7.000')
            ->assertSee('dipakai 2 pesanan')
            ->assertSee('Nonaktif');
    }

    public function test_empty_state_is_shown(): void
    {
        $this->get('/admin/delivery-areas')->assertOk()->assertSee('Belum ada area pengiriman.');
    }

    public function test_admin_can_create_area(): void
    {
        $this->post('/admin/delivery-areas', $this->valid())
            ->assertRedirect(route('admin.delivery-areas.index'))
            ->assertSessionHas('status', 'Area pengiriman berhasil ditambahkan.');

        $this->assertDatabaseHas('delivery_areas', ['district' => 'Tanah Sareal', 'delivery_fee' => 5000, 'is_active' => 1]);
    }

    public function test_validation_rejects_invalid_input(): void
    {
        DeliveryArea::factory()->create(['district' => 'Sudah Ada']);

        $this->post('/admin/delivery-areas', $this->valid(['district' => '']))->assertSessionHasErrors(['district' => 'Nama area wajib diisi.']);
        $this->post('/admin/delivery-areas', $this->valid(['district' => 'Sudah Ada']))->assertSessionHasErrors(['district' => 'Nama area ini sudah ada.']);
        $this->post('/admin/delivery-areas', $this->valid(['district' => str_repeat('a', 101)]))->assertSessionHasErrors('district');

        foreach (['', 'abc', '-1', '5000.5', 'Rp 5.000', '1000001'] as $fee) {
            $this->post('/admin/delivery-areas', $this->valid(['district' => "Area {$fee}", 'delivery_fee' => $fee]))
                ->assertSessionHasErrors('delivery_fee');
        }

        $this->post('/admin/delivery-areas', $this->valid(['is_active' => 'mungkin']))->assertSessionHasErrors('is_active');

        $this->assertDatabaseCount('delivery_areas', 1);
    }

    public function test_free_delivery_with_zero_fee_is_allowed(): void
    {
        $this->post('/admin/delivery-areas', $this->valid(['delivery_fee' => 0]))->assertSessionHasNoErrors();

        $this->assertSame(0, DeliveryArea::first()->delivery_fee);
    }

    public function test_admin_can_update_area_and_keep_own_name(): void
    {
        $area = DeliveryArea::factory()->create(['district' => 'Tanah Sareal', 'delivery_fee' => 5000]);

        $this->put("/admin/delivery-areas/{$area->id}", $this->valid(['district' => 'Tanah Sareal', 'delivery_fee' => 6500]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.delivery-areas.index'));

        $this->assertSame(6500, $area->fresh()->delivery_fee);
    }

    public function test_edit_form_warns_when_area_is_used_by_orders(): void
    {
        $area = DeliveryArea::factory()->create();
        $this->orderIn($area);

        $this->get("/admin/delivery-areas/{$area->id}/edit")
            ->assertOk()
            ->assertSee('sudah dipakai 1 pesanan')
            ->assertSee('pesanan lama tetap memakai data saat pesanan dibuat');

        $other = DeliveryArea::factory()->create();
        $this->get("/admin/delivery-areas/{$other->id}/edit")->assertDontSee('sudah dipakai');
    }

    // ---------- integritas pesanan historis ----------

    public function test_new_fee_and_name_do_not_change_old_orders(): void
    {
        $area = DeliveryArea::factory()->create(['district' => 'Tanah Sareal', 'delivery_fee' => 5000]);
        $order = $this->orderIn($area, ['subtotal' => 30000, 'total' => 35000]);
        $before = $order->fresh()->getAttributes();

        $this->put("/admin/delivery-areas/{$area->id}", $this->valid(['district' => 'Tanah Sareal Baru', 'delivery_fee' => 9000]));

        $this->assertSame(9000, $area->fresh()->delivery_fee);
        $this->assertSame($before, $order->fresh()->getAttributes());
        $this->assertSame('Tanah Sareal', $order->fresh()->delivery_area_name);
        $this->assertSame(5000, $order->fresh()->delivery_fee);
        $this->assertSame(35000, $order->fresh()->total);
    }

    public function test_area_used_by_orders_can_be_deactivated_and_old_orders_keep_their_info(): void
    {
        $area = DeliveryArea::factory()->create(['district' => 'Bogor Tengah', 'is_active' => true]);
        $order = $this->orderIn($area);

        $this->patch("/admin/delivery-areas/{$area->id}/toggle")
            ->assertSessionHas('status', 'Area pengiriman berhasil dinonaktifkan.');

        $this->assertFalse($area->fresh()->is_active);
        $this->assertSame($area->id, $order->fresh()->delivery_area_id);
        $this->assertSame('Bogor Tengah', $order->fresh()->delivery_area_name);

        $this->patch("/admin/delivery-areas/{$area->id}/toggle")
            ->assertSessionHas('status', 'Area pengiriman berhasil diaktifkan.');
        $this->assertTrue($area->fresh()->is_active);
    }

    public function test_inactive_area_cannot_be_chosen_for_new_orders(): void
    {
        $active = DeliveryArea::factory()->create(['is_active' => true]);
        $inactive = DeliveryArea::factory()->create(['is_active' => false]);

        $this->assertTrue(DeliveryArea::lockActive($active->id)->is($active));

        $this->expectException(ModelNotFoundException::class);
        DeliveryArea::lockActive($inactive->id);
    }

    public function test_lock_active_rejects_unknown_area(): void
    {
        $this->expectException(ModelNotFoundException::class);

        DeliveryArea::lockActive(9999);
    }

    // ---------- hapus ----------

    public function test_unused_area_can_be_deleted(): void
    {
        $area = DeliveryArea::factory()->create();

        $this->delete("/admin/delivery-areas/{$area->id}")
            ->assertRedirect(route('admin.delivery-areas.index'))
            ->assertSessionHas('status', 'Area pengiriman berhasil dihapus.');

        $this->assertDatabaseMissing('delivery_areas', ['id' => $area->id]);
    }

    public function test_area_used_by_an_order_cannot_be_deleted_and_message_is_clear(): void
    {
        $area = DeliveryArea::factory()->create(['district' => 'Dipakai']);
        $order = $this->orderIn($area);

        $this->from('/admin/delivery-areas')->delete("/admin/delivery-areas/{$area->id}")
            ->assertRedirect('/admin/delivery-areas')
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'sudah dipakai pada pesanan')
                && str_contains($m, 'Nonaktifkan area')
                && ! str_contains($m, 'SQLSTATE'));

        $this->assertDatabaseHas('delivery_areas', ['id' => $area->id]);
        $this->assertSame($area->id, $order->fresh()->delivery_area_id, 'pesanan historis tidak diubah');
    }

    public function test_foreign_key_violation_during_delete_is_handled_without_corrupting_data(): void
    {
        // Simulasi race: pesanan baru "menyelinap" tepat sebelum baris area dihapus.
        $area = DeliveryArea::factory()->create(['district' => 'Balapan']);
        DeliveryArea::deleting(function (DeliveryArea $model) {
            DB::table('orders')->insert([
                'order_number' => 'KB-20261002-9999', 'tracking_token' => str_repeat('x', 40),
                'customer_name' => 'Budi', 'customer_phone' => '628111111111', 'fulfillment_type' => 'delivery',
                'delivery_area_id' => $model->id, 'delivery_area_name' => $model->district,
                'subtotal' => 1000, 'delivery_fee' => 0, 'total' => 1000, 'status' => 'pending_payment',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        $this->from('/admin/delivery-areas')->delete("/admin/delivery-areas/{$area->id}")
            ->assertRedirect('/admin/delivery-areas')
            ->assertSessionHas('error', fn ($m) => ! str_contains($m, 'SQLSTATE'));

        DeliveryArea::flushEventListeners();
        $this->assertDatabaseHas('delivery_areas', ['id' => $area->id]);
        $this->assertSame(0, Order::whereNotNull('delivery_area_id')->whereDoesntHave('deliveryArea')->count(), 'tidak ada referensi rusak');
    }

    public function test_deleting_an_already_deleted_area_does_not_crash(): void
    {
        $area = DeliveryArea::factory()->create();
        $id = $area->id;
        $this->delete("/admin/delivery-areas/{$id}");

        $this->delete("/admin/delivery-areas/{$id}")->assertNotFound();
    }

    // ---------- tampilan, pencarian, akses ----------

    public function test_search_and_status_filter(): void
    {
        DeliveryArea::factory()->create(['district' => 'Tanah Sareal', 'is_active' => true]);
        DeliveryArea::factory()->create(['district' => 'Bogor Barat', 'is_active' => false]);
        DeliveryArea::factory()->create(['district' => 'Diskon 50% Area', 'is_active' => true]);

        $this->get('/admin/delivery-areas?q=sareal')->assertSee('Tanah Sareal')->assertDontSee('Bogor Barat');
        $this->get('/admin/delivery-areas?status=nonaktif')->assertSee('Bogor Barat')->assertDontSee('Tanah Sareal');
        $this->get('/admin/delivery-areas?q=%25')->assertSee('Diskon 50% Area')->assertDontSee('Bogor Barat');
        $this->get('/admin/delivery-areas?q=zzz')->assertSee('Tidak ada area yang cocok')->assertSee('Reset pencarian');
        $this->get('/admin/delivery-areas?status=ngawur')->assertOk()->assertSee('Tanah Sareal');
    }

    public function test_area_names_are_escaped(): void
    {
        DeliveryArea::factory()->create(['district' => '<script>alert(1)</script>']);

        $this->get('/admin/delivery-areas')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_navigation_contains_delivery_areas_link(): void
    {
        $this->get('/admin/dashboard')->assertSee(route('admin.delivery-areas.index'), false);
    }

    public function test_guest_and_non_admin_cannot_manage_areas(): void
    {
        $area = DeliveryArea::factory()->create();
        $requests = [
            ['get', '/admin/delivery-areas'],
            ['get', '/admin/delivery-areas/create'],
            ['post', '/admin/delivery-areas', $this->valid(['district' => 'Baru'])],
            ['get', "/admin/delivery-areas/{$area->id}/edit"],
            ['put', "/admin/delivery-areas/{$area->id}", $this->valid()],
            ['patch', "/admin/delivery-areas/{$area->id}/toggle"],
            ['delete', "/admin/delivery-areas/{$area->id}"],
        ];

        auth()->logout();
        foreach ($requests as $request) {
            [$method, $url, $data] = $request + [2 => []];
            $this->{$method}($url, $data)->assertRedirect(route('admin.login'));
        }

        $this->actingAs(User::factory()->create());
        foreach ($requests as $request) {
            [$method, $url, $data] = $request + [2 => []];
            $this->{$method}($url, $data)->assertForbidden();
        }

        $this->assertDatabaseCount('delivery_areas', 1);
        $this->assertTrue($area->fresh()->is_active);
    }

    public function test_destructive_actions_do_not_work_with_get(): void
    {
        $area = DeliveryArea::factory()->create();

        $this->get("/admin/delivery-areas/{$area->id}")->assertStatus(405);
        $this->get("/admin/delivery-areas/{$area->id}/toggle")->assertStatus(405);
        $this->assertDatabaseHas('delivery_areas', ['id' => $area->id]);
    }

    public function test_array_query_parameters_do_not_break_the_area_list(): void
    {
        DeliveryArea::factory()->create(['district' => 'Area Aman']);

        $this->get('/admin/delivery-areas?q[]=x&status[]=aktif')->assertOk()->assertSee('Area Aman');
    }
}
