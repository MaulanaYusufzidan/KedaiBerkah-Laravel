<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCategoryTest extends TestCase
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
        return array_merge(['name' => 'Ayam Bakar', 'sort_order' => 1, 'is_active' => 1], $override);
    }

    public function test_admin_can_view_categories(): void
    {
        Category::factory()->create(['name' => 'Soto Ayam']);

        $this->asAdmin()->get('/admin/categories')
            ->assertOk()
            ->assertSee('Soto Ayam')
            ->assertSee('Aktif')
            ->assertSee('Tambah kategori');
    }

    public function test_empty_state_is_shown_without_categories(): void
    {
        $this->asAdmin()->get('/admin/categories')->assertOk()->assertSee('Belum ada kategori.');
    }

    public function test_admin_can_create_category_and_slug_is_made_by_server(): void
    {
        $this->asAdmin()->post('/admin/categories', $this->valid(['slug' => 'dari-request']))
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHas('status', 'Kategori berhasil ditambahkan.');

        $this->assertDatabaseHas('categories', ['name' => 'Ayam Bakar', 'slug' => 'ayam-bakar', 'sort_order' => 1, 'is_active' => 1]);
        $this->assertDatabaseMissing('categories', ['slug' => 'dari-request']);
    }

    public function test_validation_rejects_invalid_input(): void
    {
        $this->asAdmin();

        $this->post('/admin/categories', $this->valid(['name' => '']))->assertSessionHasErrors(['name' => 'Nama kategori wajib diisi.']);
        $this->post('/admin/categories', $this->valid(['name' => str_repeat('a', 101)]))->assertSessionHasErrors('name');
        $this->post('/admin/categories', $this->valid(['sort_order' => 'abc']))->assertSessionHasErrors('sort_order');
        $this->post('/admin/categories', $this->valid(['sort_order' => -1]))->assertSessionHasErrors('sort_order');
        $this->post('/admin/categories', $this->valid(['sort_order' => 1.5]))->assertSessionHasErrors('sort_order');
        $this->post('/admin/categories', $this->valid(['sort_order' => 70000]))->assertSessionHasErrors('sort_order');
        $this->post('/admin/categories', $this->valid(['is_active' => 'mungkin']))->assertSessionHasErrors('is_active');

        $this->assertDatabaseCount('categories', 0);
    }

    public function test_name_must_be_unique_and_slug_never_collides(): void
    {
        $this->asAdmin();
        $this->post('/admin/categories', $this->valid(['name' => 'Ayam Bakar']))->assertSessionHasNoErrors();

        // Nama sama persis ditolak.
        $this->post('/admin/categories', $this->valid(['name' => 'Ayam Bakar']))->assertSessionHasErrors('name');

        // Nama berbeda yang menghasilkan slug sama mendapat akhiran.
        $this->post('/admin/categories', $this->valid(['name' => 'ayam-bakar']))->assertSessionHasNoErrors();

        $this->assertSame(['ayam-bakar', 'ayam-bakar-2'], Category::orderBy('id')->pluck('slug')->all());
    }

    public function test_admin_can_update_category_and_slug_stays_stable(): void
    {
        $category = Category::factory()->create(['name' => 'Ramesan', 'slug' => 'ramesan']);

        $this->asAdmin()->put("/admin/categories/{$category->id}", $this->valid(['name' => 'Ramesan Spesial', 'sort_order' => 5]))
            ->assertRedirect(route('admin.categories.index'));

        $category->refresh();
        $this->assertSame('Ramesan Spesial', $category->name);
        $this->assertSame('ramesan', $category->slug);
        $this->assertSame(5, $category->sort_order);
    }

    public function test_category_can_be_saved_with_its_own_name(): void
    {
        $category = Category::factory()->create(['name' => 'Minuman']);

        $this->asAdmin()->put("/admin/categories/{$category->id}", $this->valid(['name' => 'Minuman']))
            ->assertSessionHasNoErrors();
    }

    public function test_admin_can_toggle_category_active_state(): void
    {
        $category = Category::factory()->create(['is_active' => true]);

        $this->asAdmin()->patch("/admin/categories/{$category->id}/toggle")
            ->assertRedirect()
            ->assertSessionHas('status', 'Kategori berhasil dinonaktifkan.');
        $this->assertFalse($category->fresh()->is_active);

        $this->patch("/admin/categories/{$category->id}/toggle");
        $this->assertTrue($category->fresh()->is_active);
    }

    public function test_admin_can_delete_unused_category(): void
    {
        $category = Category::factory()->create();

        $this->asAdmin()->delete("/admin/categories/{$category->id}")
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_category_in_use_cannot_be_deleted_and_shows_clear_message(): void
    {
        $product = Product::factory()->create();

        $this->asAdmin()->from('/admin/categories')
            ->delete("/admin/categories/{$product->category_id}")
            ->assertRedirect('/admin/categories')
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'masih digunakan oleh produk') && ! str_contains($m, 'SQLSTATE'));

        $this->assertDatabaseHas('categories', ['id' => $product->category_id]);
    }

    public function test_category_names_are_escaped(): void
    {
        Category::factory()->create(['name' => '<script>alert(1)</script>']);

        $this->asAdmin()->get('/admin/categories')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_guest_and_non_admin_cannot_use_category_management(): void
    {
        $category = Category::factory()->create();
        $requests = [
            ['get', '/admin/categories'],
            ['get', '/admin/categories/create'],
            ['post', '/admin/categories', $this->valid(['name' => 'Baru'])],
            ['get', "/admin/categories/{$category->id}/edit"],
            ['put', "/admin/categories/{$category->id}", $this->valid()],
            ['patch', "/admin/categories/{$category->id}/toggle"],
            ['delete', "/admin/categories/{$category->id}"],
        ];

        foreach ($requests as $request) {
            [$method, $url, $data] = $request + [2 => []];
            $this->{$method}($url, $data)->assertRedirect(route('admin.login'));
        }

        $this->actingAs(User::factory()->create()); // role staff
        foreach ($requests as $request) {
            [$method, $url, $data] = $request + [2 => []];
            $this->{$method}($url, $data)->assertForbidden();
        }

        $this->assertDatabaseCount('categories', 1);
        $this->assertTrue($category->fresh()->is_active);
    }

    public function test_destructive_actions_do_not_work_with_get(): void
    {
        $category = Category::factory()->create();

        $this->asAdmin()->get("/admin/categories/{$category->id}")->assertStatus(405);
        $this->get("/admin/categories/{$category->id}/toggle")->assertStatus(405);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }
}
