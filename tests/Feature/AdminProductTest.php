<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DailyMenu;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProductTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function asAdmin(): static
    {
        return $this->actingAs(User::factory()->admin()->create());
    }

    private function valid(array $override = []): array
    {
        return array_merge([
            'category_id' => Category::factory()->create()->id,
            'name' => 'Ayam Bakar Paha',
            'description' => 'Paha ayam bakar.',
            'base_price' => 18000,
            'is_active' => 1,
        ], $override);
    }

    public function test_admin_can_view_products_with_category_and_price(): void
    {
        $category = Category::factory()->create(['name' => 'Ayam Bakar']);
        Product::factory()->create(['category_id' => $category->id, 'name' => 'Paha Bakar', 'base_price' => 18000]);

        $this->asAdmin()->get('/admin/products')
            ->assertOk()
            ->assertSee('Paha Bakar')
            ->assertSee('Ayam Bakar')
            ->assertSee('Rp18.000');
    }

    public function test_empty_state_is_shown_without_products(): void
    {
        $this->asAdmin()->get('/admin/products')->assertOk()->assertSee('Belum ada produk.');
    }

    public function test_admin_can_create_product(): void
    {
        $data = $this->valid(['slug' => 'dari-request']);

        $this->asAdmin()->post('/admin/products', $data)->assertRedirect(route('admin.products.index'));

        $this->assertDatabaseHas('products', [
            'name' => 'Ayam Bakar Paha', 'slug' => 'ayam-bakar-paha', 'base_price' => 18000, 'is_active' => 1,
        ]);
        $this->assertDatabaseMissing('products', ['slug' => 'dari-request']);
        $this->assertNull(Product::first()->image);
    }

    public function test_category_is_required_and_must_exist(): void
    {
        $this->asAdmin();

        $this->post('/admin/products', $this->valid(['category_id' => '']))->assertSessionHasErrors('category_id');
        $this->post('/admin/products', $this->valid(['category_id' => 9999]))->assertSessionHasErrors(['category_id' => 'Kategori tidak ditemukan.']);
        $this->post('/admin/products', $this->valid(['category_id' => 'abc']))->assertSessionHasErrors('category_id');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_invalid_prices_are_rejected(): void
    {
        $this->asAdmin();

        foreach (['', 'abc', 'Rp 15.000', '15.5', '-1', '-15000', '10000001'] as $price) {
            $this->post('/admin/products', $this->valid(['base_price' => $price, 'name' => "P {$price}"]))
                ->assertSessionHasErrors('base_price');
        }

        $this->assertDatabaseCount('products', 0);
    }

    public function test_price_zero_to_max_is_accepted_as_integer(): void
    {
        $this->asAdmin()->post('/admin/products', $this->valid(['base_price' => '15000']))->assertSessionHasNoErrors();

        $this->assertSame(15000, Product::first()->base_price);
    }

    public function test_name_is_required_and_unique_and_slug_never_collides(): void
    {
        $this->asAdmin();
        $category = Category::factory()->create()->id;

        $this->post('/admin/products', $this->valid(['category_id' => $category, 'name' => '']))->assertSessionHasErrors('name');
        $this->post('/admin/products', $this->valid(['category_id' => $category, 'name' => 'Soto Ayam']))->assertSessionHasNoErrors();
        $this->post('/admin/products', $this->valid(['category_id' => $category, 'name' => 'Soto Ayam']))->assertSessionHasErrors('name');
        $this->post('/admin/products', $this->valid(['category_id' => $category, 'name' => 'soto-ayam']))->assertSessionHasNoErrors();

        $this->assertSame(['soto-ayam', 'soto-ayam-2'], Product::orderBy('id')->pluck('slug')->all());
    }

    public function test_description_is_optional_but_limited(): void
    {
        $this->asAdmin();

        $this->post('/admin/products', $this->valid(['description' => null]))->assertSessionHasNoErrors();
        $this->post('/admin/products', $this->valid(['name' => 'Lain', 'description' => str_repeat('a', 1001)]))
            ->assertSessionHasErrors('description');
    }

    public function test_admin_can_update_product_and_slug_stays_stable(): void
    {
        $product = Product::factory()->create(['name' => 'Es Teh', 'slug' => 'es-teh', 'base_price' => 4000]);

        $this->asAdmin()->put("/admin/products/{$product->id}", $this->valid([
            'category_id' => $product->category_id, 'name' => 'Es Teh Manis', 'base_price' => 5000,
        ]))->assertRedirect(route('admin.products.index'));

        $product->refresh();
        $this->assertSame('Es Teh Manis', $product->name);
        $this->assertSame('es-teh', $product->slug);
        $this->assertSame(5000, $product->base_price);
    }

    public function test_edit_form_shows_current_values(): void
    {
        $product = Product::factory()->create(['name' => 'Es Teh', 'base_price' => 4000]);

        $this->asAdmin()->get("/admin/products/{$product->id}/edit")
            ->assertOk()
            ->assertSee('value="Es Teh"', false)
            ->assertSee('value="4000"', false);
    }

    public function test_admin_can_toggle_product(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $this->asAdmin()->patch("/admin/products/{$product->id}/toggle")->assertRedirect();
        $this->assertFalse($product->fresh()->is_active);

        $this->patch("/admin/products/{$product->id}/toggle");
        $this->assertTrue($product->fresh()->is_active);
    }

    public function test_admin_can_delete_unused_product(): void
    {
        $product = Product::factory()->create();

        $this->asAdmin()->delete("/admin/products/{$product->id}")->assertRedirect(route('admin.products.index'));

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_product_used_in_daily_menu_cannot_be_deleted(): void
    {
        $menu = DailyMenu::factory()->create();

        $this->asAdmin()->from('/admin/products')
            ->delete("/admin/products/{$menu->product_id}")
            ->assertRedirect('/admin/products')
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'sudah dipakai pada menu harian') && ! str_contains($m, 'SQLSTATE'));

        $this->assertDatabaseHas('products', ['id' => $menu->product_id]);
    }

    public function test_product_text_is_escaped(): void
    {
        $product = Product::factory()->create([
            'name' => '<b>Tebal</b>',
            'description' => '</textarea><script>alert(1)</script>',
        ]);

        $this->asAdmin()->get('/admin/products')
            ->assertDontSee('<b>Tebal</b>', false)
            ->assertSee('&lt;b&gt;Tebal&lt;/b&gt;', false);

        $this->get("/admin/products/{$product->id}/edit")
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_guest_and_non_admin_cannot_use_product_management(): void
    {
        $product = Product::factory()->create();
        $requests = [
            ['get', '/admin/products'],
            ['get', '/admin/products/create'],
            ['post', '/admin/products', $this->valid(['name' => 'Baru'])],
            ['get', "/admin/products/{$product->id}/edit"],
            ['put', "/admin/products/{$product->id}", $this->valid()],
            ['patch', "/admin/products/{$product->id}/toggle"],
            ['delete', "/admin/products/{$product->id}"],
        ];

        foreach ($requests as $request) {
            [$method, $url, $data] = $request + [2 => []];
            $this->{$method}($url, $data)->assertRedirect(route('admin.login'));
        }

        $this->actingAs(User::factory()->create());
        foreach ($requests as $request) {
            [$method, $url, $data] = $request + [2 => []];
            $this->{$method}($url, $data)->assertForbidden();
        }

        $this->assertDatabaseCount('products', 1);
    }
}
