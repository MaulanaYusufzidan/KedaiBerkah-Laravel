<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
        $this->seed(SettingSeeder::class);
        $this->actingAs(User::factory()->admin()->create());
    }

    private function valid(array $override = []): array
    {
        return array_merge([
            'shop_name' => 'Kedai Berkah',
            'shop_tagline' => 'Spesialisasi Olahan Ayam',
            'address' => 'Jl. Kp. Kukupu Gg. Jarum, Cibadak, Tanah Sareal, Kota Bogor',
            'opening_hours' => 'Setiap hari 09.30–21.00',
            'whatsapp_number' => '0878-7462-7555',
            'instagram' => 'kedaiberkah',
            'bank_name' => 'BCA',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'Maulana Yusuf Zidan',
            'payment_instructions' => 'Tulis kode pesanan pada berita transfer.',
        ], $override);
    }

    public function test_settings_page_shows_saved_values_with_readable_phone(): void
    {
        $this->get('/admin/settings')
            ->assertOk()
            ->assertSee('Pengaturan toko')
            ->assertSee('value="Kedai Berkah"', false)
            ->assertSee('value="0878-7462-7555"', false)
            ->assertSee('https://wa.me/6287874627555?text=', false);
    }

    public function test_admin_can_save_all_settings_persistently(): void
    {
        $this->put('/admin/settings', $this->valid())
            ->assertRedirect(route('admin.settings.edit'))
            ->assertSessionHas('status', 'Pengaturan berhasil disimpan.');

        $this->assertSame('Kedai Berkah', Setting::get('shop_name'));
        $this->assertSame('Spesialisasi Olahan Ayam', Setting::get('shop_tagline'));
        $this->assertSame('Setiap hari 09.30–21.00', Setting::get('opening_hours'));
        $this->assertSame('kedaiberkah', Setting::get('instagram'));
        $this->assertSame('BCA', Setting::get('bank_name'));
        $this->assertSame('1234567890', Setting::get('bank_account_number'));
        $this->assertSame('Maulana Yusuf Zidan', Setting::get('bank_account_name'));
        $this->assertSame('Tulis kode pesanan pada berita transfer.', Setting::get('payment_instructions'));
    }

    public function test_whatsapp_is_normalized_and_used_for_links(): void
    {
        foreach (['0878-7462-7555', '+62 878 7462 7555', '6287874627555', '87874627555', '0062 878-7462-7555'] as $input) {
            $this->put('/admin/settings', $this->valid(['whatsapp_number' => $input]))->assertSessionHasNoErrors();
            $this->assertSame('6287874627555', Setting::get('whatsapp_number'), "input: {$input}");
        }

        $this->assertSame('https://wa.me/6287874627555?text=Halo%20Kedai', Setting::whatsappUrl('Halo Kedai'));
    }

    public function test_invalid_whatsapp_is_rejected_and_old_value_is_kept(): void
    {
        foreach (['abc', '0878', '0251-1234567', '+1 415 555 2671'] as $bad) {
            $this->put('/admin/settings', $this->valid(['whatsapp_number' => $bad]))
                ->assertSessionHasErrors('whatsapp_number');
        }

        $this->assertSame('6287874627555', Setting::get('whatsapp_number'));
    }

    public function test_empty_whatsapp_clears_the_link(): void
    {
        $this->put('/admin/settings', $this->valid(['whatsapp_number' => '']))->assertSessionHasNoErrors();

        $this->assertNull(Setting::get('whatsapp_number'));
        $this->assertNull(Setting::whatsappUrl('x'));
    }

    public function test_shop_name_is_required_and_lengths_are_limited(): void
    {
        $this->put('/admin/settings', $this->valid(['shop_name' => '']))->assertSessionHasErrors(['shop_name' => 'Nama toko wajib diisi.']);
        $this->put('/admin/settings', $this->valid(['shop_name' => str_repeat('a', 101)]))->assertSessionHasErrors('shop_name');
        $this->put('/admin/settings', $this->valid(['address' => str_repeat('a', 501)]))->assertSessionHasErrors('address');
        $this->put('/admin/settings', $this->valid(['payment_instructions' => str_repeat('a', 501)]))->assertSessionHasErrors('payment_instructions');

        $this->assertSame('Kedai Berkah', Setting::get('shop_name'));
    }

    public function test_bank_account_number_accepts_digits_with_separators_and_stores_digits_only(): void
    {
        $this->put('/admin/settings', $this->valid(['bank_account_number' => '0123 4567-890']))->assertSessionHasNoErrors();
        $this->assertSame('01234567890', Setting::get('bank_account_number'), 'angka nol di depan tidak hilang');

        foreach (['abc12345', '12', '1234567890123456789012345678901', '12 34 abc'] as $bad) {
            $this->put('/admin/settings', $this->valid(['bank_account_number' => $bad]))
                ->assertSessionHasErrors('bank_account_number');
        }
    }

    public function test_instagram_accepts_handle_or_url_and_rejects_garbage(): void
    {
        foreach (['@kedaiberkah', 'kedaiberkah', 'https://www.instagram.com/kedaiberkah/', 'instagram.com/kedaiberkah?igsh=abc'] as $input) {
            $this->put('/admin/settings', $this->valid(['instagram' => $input]))->assertSessionHasNoErrors();
            $this->assertSame('kedaiberkah', Setting::get('instagram'), "input: {$input}");
        }

        $this->put('/admin/settings', $this->valid(['instagram' => 'nama dengan spasi']))->assertSessionHasErrors('instagram');
        $this->put('/admin/settings', $this->valid(['instagram' => '<script>']))->assertSessionHasErrors('instagram');
    }

    public function test_optional_fields_become_null_not_empty_strings(): void
    {
        $this->put('/admin/settings', $this->valid(['shop_tagline' => '', 'bank_name' => '   ', 'instagram' => '']))
            ->assertSessionHasNoErrors();

        foreach (['shop_tagline', 'bank_name', 'instagram'] as $key) {
            $this->assertNull(Setting::where('key', $key)->value('value'), $key);
        }
    }

    public function test_saving_repeatedly_never_duplicates_setting_keys(): void
    {
        $before = Setting::count();

        $this->put('/admin/settings', $this->valid());
        $this->put('/admin/settings', $this->valid(['shop_name' => 'Kedai Berkah Bogor']));

        $this->assertSame($before, Setting::count());
        $this->assertSame(Setting::count(), Setting::distinct('key')->count('key'));
        $this->assertSame('Kedai Berkah Bogor', Setting::get('shop_name'));
    }

    public function test_unknown_fields_and_setting_keys_in_request_are_ignored(): void
    {
        $this->put('/admin/settings', $this->valid([
            'qris_image' => 'qris/'.str_repeat('a', 40).'.webp',
            'app_key' => 'rahasia',
            'role' => 'admin',
            'foo' => 'bar',
        ]))->assertSessionHasNoErrors();

        $this->assertNull(Setting::get('qris_image'));
        $this->assertDatabaseMissing('settings', ['key' => 'app_key']);
        $this->assertDatabaseMissing('settings', ['key' => 'foo']);
    }

    // ---------- QRIS ----------

    public function test_qris_upload_is_processed_stored_and_shown(): void
    {
        $this->put('/admin/settings', $this->valid(['qris' => UploadedFile::fake()->image('qr.png', 2000, 2000)]))
            ->assertSessionHasNoErrors();

        $path = Setting::get('qris_image');
        $this->assertMatchesRegularExpression('#^qris/[A-Za-z0-9]{40}\.(webp|jpg)$#', $path);
        Storage::disk('public')->assertExists($path);
        [$w] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame(1600, $w);

        $this->get('/admin/settings')->assertSee('/storage/'.$path, false)->assertSee('Hapus QRIS saat ini');
    }

    public function test_qris_replace_and_remove_clean_up_files(): void
    {
        $this->put('/admin/settings', $this->valid(['qris' => UploadedFile::fake()->image('a.png', 500, 500)]));
        $first = Setting::get('qris_image');

        $this->put('/admin/settings', $this->valid(['qris' => UploadedFile::fake()->image('b.png', 500, 500)]));
        $second = Setting::get('qris_image');
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        $this->put('/admin/settings', $this->valid(['remove_qris' => 1]));
        $this->assertNull(Setting::get('qris_image'));
        Storage::disk('public')->assertMissing($second);
        $this->assertSame([], Storage::disk('public')->allFiles('qris'));
    }

    public function test_saving_without_new_qris_keeps_the_existing_one(): void
    {
        $this->put('/admin/settings', $this->valid(['qris' => UploadedFile::fake()->image('a.png', 500, 500)]));
        $path = Setting::get('qris_image');

        $this->put('/admin/settings', $this->valid(['shop_name' => 'Nama Baru']));

        $this->assertSame($path, Setting::get('qris_image'));
        Storage::disk('public')->assertExists($path);
    }

    public function test_invalid_qris_is_rejected_without_changing_anything(): void
    {
        $this->put('/admin/settings', $this->valid(['qris' => UploadedFile::fake()->image('a.png', 500, 500)]));
        $path = Setting::get('qris_image');

        foreach ([
            UploadedFile::fake()->createWithContent('x.jpg', '<?php echo 1;'),
            UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
            UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'),
            UploadedFile::fake()->image('besar.png', 800, 800)->size(3000),
            UploadedFile::fake()->image('mini.png', 100, 100),
        ] as $bad) {
            $this->put('/admin/settings', $this->valid(['shop_name' => 'Tidak Boleh Tersimpan', 'qris' => $bad]))
                ->assertSessionHasErrors('qris');
        }

        $this->assertSame($path, Setting::get('qris_image'));
        $this->assertSame('Kedai Berkah', Setting::get('shop_name'), 'validasi gagal tidak menyimpan field lain');
        $this->assertSame([$path], Storage::disk('public')->allFiles('qris'));
    }

    public function test_tampered_qris_path_is_not_deleted(): void
    {
        Storage::disk('public')->put('rahasia/penting.txt', 'x');
        Setting::set('qris_image', '../rahasia/penting.txt');

        $this->put('/admin/settings', $this->valid(['remove_qris' => 1]));

        Storage::disk('public')->assertExists('rahasia/penting.txt');
        $this->assertNull(Setting::get('qris_image'));
    }

    // ---------- keamanan & integritas ----------

    public function test_values_are_escaped_in_the_form(): void
    {
        Setting::set('shop_name', '"><script>alert(1)</script>');
        Setting::set('address', '</textarea><script>alert(2)</script>');

        $this->get('/admin/settings')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<script>alert(2)</script>', false);
    }

    public function test_changing_settings_does_not_change_historical_orders(): void
    {
        $order = Order::factory()->create(['delivery_fee' => 5000, 'total' => 35000, 'subtotal' => 30000]);
        $snapshot = $order->fresh()->getAttributes();

        $this->put('/admin/settings', $this->valid(['shop_name' => 'Nama Lain', 'whatsapp_number' => '0812 3456 7890']));

        $this->assertSame($snapshot, $order->fresh()->getAttributes());
    }

    public function test_guest_and_non_admin_cannot_read_or_change_settings(): void
    {
        auth()->logout();
        $this->get('/admin/settings')->assertRedirect(route('admin.login'));
        $this->put('/admin/settings', $this->valid(['shop_name' => 'Diretas']))->assertRedirect(route('admin.login'));

        $this->actingAs(User::factory()->create());
        $this->get('/admin/settings')->assertForbidden();
        $this->put('/admin/settings', $this->valid(['shop_name' => 'Diretas']))->assertForbidden();

        $this->assertSame('Kedai Berkah', Setting::get('shop_name'));
    }

    public function test_dashboard_reminds_about_missing_settings_and_hides_reminder_when_complete(): void
    {
        $this->get('/admin/dashboard')
            ->assertSee('Pengaturan toko belum lengkap')
            ->assertSee('Informasi pembayaran')
            ->assertSee('Jam operasional')
            ->assertDontSee('Nomor WhatsApp,', false);

        $this->put('/admin/settings', $this->valid());

        $this->get('/admin/dashboard')->assertDontSee('Pengaturan toko belum lengkap');
    }

    public function test_navigation_links_to_settings(): void
    {
        $this->get('/admin/dashboard')->assertSee(route('admin.settings.edit'), false);
    }
}
