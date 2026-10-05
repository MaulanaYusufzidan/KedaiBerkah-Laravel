<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DailyMenu;
use App\Models\Product;
use App\Models\User;
use App\Support\ImageProcessingException;
use App\Support\ImageStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminProductImageTest extends TestCase
{
    use RefreshDatabase;

    private const PATH_PATTERN = '#^products/[A-Za-z0-9]{40}\.(webp|jpg)$#';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create());
    }

    private function fields(array $override = []): array
    {
        return array_merge([
            'category_id' => Category::factory()->create()->id,
            'name' => 'Ayam Bakar Paha',
            'base_price' => 18000,
            'is_active' => 1,
        ], $override);
    }

    private function files(): array
    {
        return Storage::disk('public')->allFiles('products');
    }

    private function dimensions(string $path): array
    {
        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($path));

        return [$w, $h];
    }

    /** JPEG 400x200: separuh kiri merah, separuh kanan biru, dengan tag orientasi EXIF opsional. */
    private function jpegWithOrientation(?int $orientation, string $tail = ''): UploadedFile
    {
        $img = imagecreatetruecolor(400, 200);
        imagefilledrectangle($img, 0, 0, 199, 199, imagecolorallocate($img, 255, 0, 0));
        imagefilledrectangle($img, 200, 0, 399, 199, imagecolorallocate($img, 0, 0, 255));
        ob_start();
        imagejpeg($img, null, 95);
        $jpeg = ob_get_clean();

        if ($orientation !== null) {
            $tiff = "MM\x00\x2A\x00\x00\x00\x08".pack('n', 1).pack('nnNn', 0x0112, 3, 1, $orientation)."\x00\x00"."\x00\x00\x00\x00";
            $exif = "Exif\x00\x00".$tiff;
            $jpeg = substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);
        }

        return UploadedFile::fake()->createWithContent('foto-hp.jpg', $jpeg.$tail);
    }

    private function colorAt(string $path, int $x, int $y): array
    {
        $img = imagecreatefromstring(Storage::disk('public')->get($path));
        $rgb = imagecolorat($img, $x, $y);

        return [($rgb >> 16) & 255, ($rgb >> 8) & 255, $rgb & 255];
    }

    // ---------- penyimpanan ----------

    public function test_valid_image_is_reencoded_resized_and_stored_with_random_name(): void
    {
        $file = UploadedFile::fake()->image('../../evil.php.jpg', 2400, 1600);

        $this->post('/admin/products', $this->fields(['image' => $file]))->assertSessionHasNoErrors();

        $product = Product::firstOrFail();
        $this->assertMatchesRegularExpression(self::PATH_PATTERN, $product->image);
        $this->assertStringNotContainsString('evil', $product->image);
        $this->assertCount(1, $this->files());
        [$w, $h] = $this->dimensions($product->image);
        $this->assertSame(1200, max($w, $h));
        $this->assertSame(800, min($w, $h), 'rasio gambar dipertahankan');
    }

    public function test_small_image_is_not_upscaled(): void
    {
        $this->post('/admin/products', $this->fields(['image' => UploadedFile::fake()->image('kecil.png', 300, 200)]));

        $this->assertSame([300, 200], $this->dimensions(Product::firstOrFail()->image));
    }

    public function test_embedded_php_payload_does_not_survive_reencoding(): void
    {
        $file = $this->jpegWithOrientation(null, "\n<?php system(\$_GET['c']); ?>");

        $this->post('/admin/products', $this->fields(['image' => $file]))->assertSessionHasNoErrors();

        $stored = Storage::disk('public')->get(Product::firstOrFail()->image);
        $this->assertStringNotContainsString('<?php', $stored);
        $this->assertStringNotContainsString('system(', $stored);
    }

    // ---------- EXIF ----------

    public function test_exif_orientation_6_is_rotated_clockwise(): void
    {
        if (! function_exists('exif_read_data')) {
            $this->markTestSkipped('Ekstensi exif tidak tersedia.');
        }

        $this->post('/admin/products', $this->fields(['image' => $this->jpegWithOrientation(6)]))->assertSessionHasNoErrors();

        $path = Product::firstOrFail()->image;
        $this->assertSame([200, 400], $this->dimensions($path), 'landscape + orientasi 6 menjadi portrait');
        [$rTop] = $this->colorAt($path, 100, 20);
        [, , $bBottom] = $this->colorAt($path, 100, 380);
        $this->assertGreaterThan(200, $rTop, 'sisi kiri asli (merah) menjadi atas');
        $this->assertGreaterThan(200, $bBottom, 'sisi kanan asli (biru) menjadi bawah');
    }

    public function test_exif_orientation_8_is_rotated_counter_clockwise(): void
    {
        if (! function_exists('exif_read_data')) {
            $this->markTestSkipped('Ekstensi exif tidak tersedia.');
        }

        $this->post('/admin/products', $this->fields(['image' => $this->jpegWithOrientation(8)]));

        $path = Product::firstOrFail()->image;
        $this->assertSame([200, 400], $this->dimensions($path));
        [, , $bTop] = $this->colorAt($path, 100, 20);
        [$rBottom] = $this->colorAt($path, 100, 380);
        $this->assertGreaterThan(200, $bTop);
        $this->assertGreaterThan(200, $rBottom);
    }

    public function test_exif_orientation_3_rotates_180_and_missing_exif_keeps_original(): void
    {
        if (! function_exists('exif_read_data')) {
            $this->markTestSkipped('Ekstensi exif tidak tersedia.');
        }

        $this->post('/admin/products', $this->fields(['name' => 'Rotasi 180', 'image' => $this->jpegWithOrientation(3)]));
        $rotated = Product::where('name', 'Rotasi 180')->firstOrFail()->image;
        $this->assertSame([400, 200], $this->dimensions($rotated));
        [, , $blueLeft] = $this->colorAt($rotated, 50, 100);
        $this->assertGreaterThan(200, $blueLeft, 'kiri/kanan tertukar setelah 180 derajat');

        $this->post('/admin/products', $this->fields(['name' => 'Tanpa EXIF', 'image' => $this->jpegWithOrientation(null)]));
        $plain = Product::where('name', 'Tanpa EXIF')->firstOrFail()->image;
        $this->assertSame([400, 200], $this->dimensions($plain));
        [$redLeft] = $this->colorAt($plain, 50, 100);
        $this->assertGreaterThan(200, $redLeft);
    }

    public function test_stored_file_has_no_exif_metadata(): void
    {
        $this->post('/admin/products', $this->fields(['image' => $this->jpegWithOrientation(6)]));

        $this->assertStringNotContainsString('Exif', Storage::disk('public')->get(Product::firstOrFail()->image));
    }

    // ---------- penolakan ----------

    public function test_invalid_uploads_are_rejected_and_nothing_is_stored(): void
    {
        $cases = [
            'teks menyamar jpg' => UploadedFile::fake()->createWithContent('x.jpg', 'ini bukan gambar'),
            'php' => UploadedFile::fake()->create('shell.php', 10, 'application/x-php'),
            'php berkedok gambar' => UploadedFile::fake()->createWithContent('shell.jpg', '<?php echo 1;'),
            'svg' => UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            'gif' => UploadedFile::fake()->image('anim.gif', 200, 200),
            'pdf' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
            'terlalu besar' => UploadedFile::fake()->image('besar.jpg', 800, 800)->size(3000),
            'terlalu kecil' => UploadedFile::fake()->image('mini.jpg', 50, 50),
            'terlalu lebar' => UploadedFile::fake()->image('raksasa.png', 6001, 200),
        ];

        foreach ($cases as $label => $file) {
            $this->post('/admin/products', $this->fields(['name' => "Produk {$label}", 'image' => $file]))
                ->assertSessionHasErrors('image');
        }

        $this->assertDatabaseCount('products', 0);
        $this->assertSame([], $this->files());
    }

    public function test_image_field_must_be_an_uploaded_file_not_a_path_string(): void
    {
        $this->post('/admin/products', $this->fields(['image' => 'products/'.str_repeat('a', 40).'.webp']))
            ->assertSessionHasErrors('image');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_size_and_type_errors_are_in_indonesian(): void
    {
        $this->post('/admin/products', $this->fields(['image' => UploadedFile::fake()->image('b.jpg', 800, 800)->size(3000)]))
            ->assertSessionHasErrors(['image' => 'Ukuran gambar maksimal 2 MB.']);

        $this->post('/admin/products', $this->fields(['name' => 'Lain', 'image' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]))
            ->assertSessionHasErrors(['image' => 'Berkas harus berupa gambar JPG, PNG, atau WebP.']);
    }

    public function test_unprocessable_image_throws_a_clear_exception(): void
    {
        $this->expectException(ImageProcessingException::class);
        $this->expectExceptionMessage('bukan gambar yang valid');

        ImageStorage::store(UploadedFile::fake()->createWithContent('x.jpg', "\xFF\xD8\xFF\xE0 rusak"), 'products');
    }

    // ---------- ganti, hapus, dan integritas ----------

    public function test_replacing_image_deletes_the_old_file_only_after_database_update(): void
    {
        $this->post('/admin/products', $this->fields(['image' => UploadedFile::fake()->image('a.jpg', 500, 500)]));
        $product = Product::firstOrFail();
        $old = $product->image;

        $this->put("/admin/products/{$product->id}", $this->fields([
            'category_id' => $product->category_id, 'image' => UploadedFile::fake()->image('b.png', 600, 600),
        ]))->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertNotSame($old, $product->image);
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($product->image);
        $this->assertCount(1, $this->files());
    }

    public function test_failed_update_keeps_old_image_and_removes_the_new_file(): void
    {
        $this->post('/admin/products', $this->fields(['image' => UploadedFile::fake()->image('a.jpg', 500, 500)]));
        $product = Product::firstOrFail();
        $old = $product->image;

        Product::updating(fn () => throw new \RuntimeException('database gagal'));

        $this->put("/admin/products/{$product->id}", $this->fields([
            'category_id' => $product->category_id, 'image' => UploadedFile::fake()->image('b.jpg', 600, 600),
        ]))->assertStatus(500);

        Product::flushEventListeners();
        $this->assertSame($old, $product->fresh()->image);
        Storage::disk('public')->assertExists($old);
        $this->assertSame([$old], $this->files(), 'file baru dibersihkan, tidak ada file yatim');
    }

    public function test_failed_create_does_not_leave_an_orphan_file(): void
    {
        Product::creating(fn () => throw new \RuntimeException('database gagal'));

        $this->post('/admin/products', $this->fields(['image' => UploadedFile::fake()->image('a.jpg', 500, 500)]))
            ->assertStatus(500);

        Product::flushEventListeners();
        $this->assertSame([], $this->files());
        $this->assertDatabaseCount('products', 0);
    }

    public function test_update_without_new_file_keeps_existing_image(): void
    {
        $this->post('/admin/products', $this->fields(['image' => UploadedFile::fake()->image('a.jpg', 500, 500)]));
        $product = Product::firstOrFail();
        $old = $product->image;

        $this->put("/admin/products/{$product->id}", $this->fields(['category_id' => $product->category_id, 'name' => 'Nama Baru']));

        $this->assertSame($old, $product->fresh()->image);
        Storage::disk('public')->assertExists($old);
    }

    public function test_remove_image_checkbox_deletes_file_and_clears_column(): void
    {
        $this->post('/admin/products', $this->fields(['image' => UploadedFile::fake()->image('a.jpg', 500, 500)]));
        $product = Product::firstOrFail();
        $old = $product->image;

        $this->put("/admin/products/{$product->id}", $this->fields(['category_id' => $product->category_id, 'remove_image' => 1]));

        $this->assertNull($product->fresh()->image);
        Storage::disk('public')->assertMissing($old);
    }

    public function test_old_image_shared_with_another_product_is_not_deleted(): void
    {
        $this->post('/admin/products', $this->fields(['image' => UploadedFile::fake()->image('a.jpg', 500, 500)]));
        $first = Product::firstOrFail();
        $shared = $first->image;
        $second = Product::factory()->create(['image' => $shared]);

        $this->put("/admin/products/{$first->id}", $this->fields(['category_id' => $first->category_id, 'remove_image' => 1]));

        Storage::disk('public')->assertExists($shared);
        $this->assertSame($shared, $second->fresh()->image);
    }

    public function test_deleting_product_removes_its_image(): void
    {
        $this->post('/admin/products', $this->fields(['image' => UploadedFile::fake()->image('a.jpg', 500, 500)]));
        $product = Product::firstOrFail();
        $path = $product->image;

        $this->delete("/admin/products/{$product->id}")->assertRedirect(route('admin.products.index'));

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_blocked_delete_keeps_the_image(): void
    {
        $this->post('/admin/products', $this->fields(['image' => UploadedFile::fake()->image('a.jpg', 500, 500)]));
        $product = Product::firstOrFail();
        DailyMenu::factory()->create(['product_id' => $product->id]);

        $this->delete("/admin/products/{$product->id}")->assertSessionHas('error');

        Storage::disk('public')->assertExists($product->image);
    }

    public function test_tampered_database_path_is_never_deleted(): void
    {
        Storage::disk('public')->put('rahasia/penting.txt', 'jangan dihapus');
        Storage::disk('public')->put('products/bukan-nama-acak.webp', 'x');

        foreach (['products/../rahasia/penting.txt', '../rahasia/penting.txt', 'products/bukan-nama-acak.webp', '/etc/passwd'] as $i => $path) {
            $product = Product::factory()->create(['image' => $path, 'name' => "Rusak {$i}"]);
            $this->delete("/admin/products/{$product->id}")->assertRedirect(route('admin.products.index'));
        }

        Storage::disk('public')->assertExists('rahasia/penting.txt');
        Storage::disk('public')->assertExists('products/bukan-nama-acak.webp');
    }

    // ---------- tampilan ----------

    public function test_list_shows_thumbnail_or_local_placeholder(): void
    {
        $this->post('/admin/products', $this->fields(['name' => 'Dengan Foto', 'image' => UploadedFile::fake()->image('a.jpg', 500, 500)]));
        Product::factory()->create(['name' => 'Tanpa Foto', 'image' => null]);
        $withImage = Product::where('name', 'Dengan Foto')->firstOrFail();

        $this->get('/admin/products')
            ->assertOk()
            ->assertSee('http://localhost/storage/'.$withImage->image, false)
            ->assertSee('images/placeholder-produk.svg', false)
            ->assertSee('loading="lazy"', false);

        $this->assertFileExists(public_path('images/placeholder-produk.svg'));
    }

    public function test_edit_form_has_upload_input_preview_and_remove_option_only_when_image_exists(): void
    {
        $plain = Product::factory()->create(['image' => null]);
        $this->get("/admin/products/{$plain->id}/edit")
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('type="file"', false)
            ->assertSee('id="image-preview"', false)
            ->assertDontSee('Hapus foto saat ini');

        $this->post('/admin/products', $this->fields(['name' => 'Berfoto', 'image' => UploadedFile::fake()->image('a.jpg', 500, 500)]));
        $withImage = Product::where('name', 'Berfoto')->firstOrFail();
        $this->get("/admin/products/{$withImage->id}/edit")->assertSee('Hapus foto saat ini');
    }

    // ---------- pencarian & filter ----------

    public function test_search_filters_by_name_case_insensitively_and_escapes_wildcards(): void
    {
        Product::factory()->create(['name' => 'Ayam Bakar Paha']);
        Product::factory()->create(['name' => 'Soto Ayam']);
        Product::factory()->create(['name' => 'Diskon 50% Spesial']);
        Product::factory()->create(['name' => 'Es Teh']);

        $this->get('/admin/products?q=ayam')->assertSee('Ayam Bakar Paha')->assertSee('Soto Ayam')->assertDontSee('Es Teh');
        $this->get('/admin/products?q=%25')->assertSee('Diskon 50% Spesial')->assertDontSee('Es Teh');
        $this->get('/admin/products?q=_')->assertSee('Tidak ada produk yang cocok');
    }

    public function test_filters_by_category_and_status_and_combination(): void
    {
        $ayam = Category::factory()->create(['name' => 'Kat Ayam']);
        $minum = Category::factory()->create(['name' => 'Kat Minum']);
        Product::factory()->create(['name' => 'Ayam A', 'category_id' => $ayam->id, 'is_active' => true]);
        Product::factory()->create(['name' => 'Ayam B', 'category_id' => $ayam->id, 'is_active' => false]);
        Product::factory()->create(['name' => 'Teh C', 'category_id' => $minum->id, 'is_active' => true]);

        $this->get("/admin/products?category={$ayam->id}")->assertSee('Ayam A')->assertSee('Ayam B')->assertDontSee('Teh C');
        $this->get('/admin/products?status=nonaktif')->assertSee('Ayam B')->assertDontSee('Ayam A')->assertDontSee('Teh C');
        $this->get("/admin/products?category={$ayam->id}&status=aktif")->assertSee('Ayam A')->assertDontSee('Ayam B');
        $this->get('/admin/products?status=ngawur&category=abc')->assertOk()->assertSee('Ayam A')->assertSee('Teh C');
    }

    public function test_pagination_keeps_filters_and_empty_states_differ(): void
    {
        $cat = Category::factory()->create();
        foreach (range(1, 25) as $i) {
            Product::factory()->create(['name' => sprintf('Menu %02d', $i), 'category_id' => $cat->id]);
        }

        $this->get('/admin/products?q=Menu')
            ->assertSee('Menu 01')->assertDontSee('Menu 25')
            ->assertSee('Halaman 1 dari 2')
            ->assertSee('q=Menu&amp;page=2', false);

        $this->get('/admin/products?q=tidakada')->assertSee('Reset pencarian');
    }

    public function test_guest_and_non_admin_cannot_upload(): void
    {
        auth()->logout();
        $this->post('/admin/products', $this->fields(['image' => UploadedFile::fake()->image('a.jpg', 300, 300)]))
            ->assertRedirect(route('admin.login'));

        $this->actingAs(User::factory()->create())
            ->post('/admin/products', $this->fields(['image' => UploadedFile::fake()->image('a.jpg', 300, 300)]))
            ->assertForbidden();

        $this->assertDatabaseCount('products', 0);
        $this->assertSame([], $this->files());
    }

    public function test_array_query_parameters_do_not_break_the_product_list(): void
    {
        Product::factory()->create(['name' => 'Ayam Aman']);

        $this->get('/admin/products?q[]=x&category[]=1&status[]=aktif')->assertOk()->assertSee('Ayam Aman');
    }
}
