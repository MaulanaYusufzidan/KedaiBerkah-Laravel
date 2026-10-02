<?php

namespace Tests\Feature;

use App\Enums\DailyMenuStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Category;
use App\Models\DailyMenu;
use App\Models\DeliveryArea;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_belongs_to_category_and_has_daily_menus(): void
    {
        $menu = DailyMenu::factory()->create();

        $this->assertInstanceOf(Category::class, $menu->product->category);
        $this->assertTrue($menu->product->dailyMenus->contains($menu));
        $this->assertSame(DailyMenuStatus::Available, $menu->status);
    }

    public function test_same_product_cannot_have_two_menus_on_the_same_date(): void
    {
        $product = Product::factory()->create();
        DailyMenu::factory()->create(['product_id' => $product->id, 'menu_date' => '2026-10-02']);

        $this->expectException(QueryException::class);
        DailyMenu::factory()->create(['product_id' => $product->id, 'menu_date' => '2026-10-02']);
    }

    public function test_same_product_can_have_menus_on_different_dates(): void
    {
        $product = Product::factory()->create();
        DailyMenu::factory()->create(['product_id' => $product->id, 'menu_date' => '2026-10-02']);
        DailyMenu::factory()->create(['product_id' => $product->id, 'menu_date' => '2026-10-03']);

        $this->assertSame(2, $product->dailyMenus()->count());
    }

    public function test_order_relations_and_enum_casts(): void
    {
        $area = DeliveryArea::factory()->create();
        $order = Order::factory()->create(['delivery_area_id' => $area->id]);
        OrderItem::factory()->create(['order_id' => $order->id]);
        Payment::factory()->create(['order_id' => $order->id]);
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_status' => null,
            'to_status' => OrderStatus::PendingPayment,
        ]);

        $order->refresh();

        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame($area->id, $order->deliveryArea->id);
        $this->assertCount(1, $order->items);
        $this->assertSame(PaymentStatus::Pending, $order->payment->status);
        $this->assertSame(OrderStatus::PendingPayment, $order->statusHistories->first()->to_status);
    }

    public function test_order_number_must_be_unique(): void
    {
        Order::factory()->create(['order_number' => 'KB-20261002-0001']);

        $this->expectException(QueryException::class);
        Order::factory()->create(['order_number' => 'KB-20261002-0001']);
    }

    public function test_order_server_fields_cannot_be_mass_assigned(): void
    {
        $order = new Order;
        $order->fill([
            'customer_name' => 'Budi',
            'total' => 1,
            'status' => 'completed',
            'order_number' => 'KB-X',
            'tracking_token' => 'x',
        ]);

        $this->assertSame('Budi', $order->customer_name);
        $this->assertNull($order->total);
        $this->assertNull($order->status);
        $this->assertNull($order->order_number);
    }

    public function test_payment_status_and_verifier_cannot_be_mass_assigned(): void
    {
        $payment = new Payment;
        $payment->fill(['amount' => 1000, 'status' => 'paid', 'verified_by' => 1]);

        $this->assertSame(1000, $payment->amount);
        $this->assertNull($payment->status);
        $this->assertNull($payment->verified_by);
    }

    public function test_order_item_keeps_snapshot_when_menu_is_deleted(): void
    {
        $menu = DailyMenu::factory()->create();
        $item = OrderItem::factory()->create(['daily_menu_id' => $menu->id, 'product_name' => 'Menu uji']);

        $menu->delete();

        $item->refresh();
        $this->assertNull($item->daily_menu_id);
        $this->assertSame('Menu uji', $item->product_name);
    }

    public function test_deleting_order_removes_items_payments_and_histories(): void
    {
        $order = Order::factory()->create();
        OrderItem::factory()->create(['order_id' => $order->id]);
        Payment::factory()->create(['order_id' => $order->id]);
        OrderStatusHistory::create(['order_id' => $order->id, 'to_status' => OrderStatus::PendingPayment]);

        $order->delete();

        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('order_status_histories', 0);
    }

    public function test_category_with_products_cannot_be_deleted(): void
    {
        $product = Product::factory()->create();

        $this->expectException(QueryException::class);
        $product->category->delete();
    }

    public function test_user_role_is_not_mass_assignable(): void
    {
        $user = new User;
        $user->fill(['name' => 'X', 'role' => 'admin']);

        $this->assertNull($user->role);
        $this->assertFalse($user->isAdmin());
    }

    public function test_setting_get_and_set(): void
    {
        $this->assertSame('x', Setting::get('missing', 'x'));

        Setting::set('whatsapp_number', '6281234567890');
        Setting::set('whatsapp_number', '6280000000000');

        $this->assertSame('6280000000000', Setting::get('whatsapp_number'));
        $this->assertDatabaseCount('settings', 1);
    }

    public function test_seeder_is_idempotent_and_does_not_invent_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        Setting::set('whatsapp_number', '6280000000000');
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('categories', 5);
        $this->assertSame('6280000000000', Setting::get('whatsapp_number'), 'seeder tidak boleh menimpa setting yang diubah admin');
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('delivery_areas', 0);
        $this->assertDatabaseCount('users', 0);
        $this->assertNull(Setting::get('bank_account_number'));
    }
}
