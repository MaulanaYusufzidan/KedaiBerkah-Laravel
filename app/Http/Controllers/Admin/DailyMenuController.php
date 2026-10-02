<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DailyMenuStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DailyMenuRequest;
use App\Models\DailyMenu;
use App\Models\Product;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DailyMenuController extends Controller
{
    public function index(Request $request): View
    {
        $date = $this->dateFrom($request->query('date'));

        $menus = DailyMenu::with('product.category')
            ->whereDate('menu_date', $date)
            ->orderBy(Product::select('name')->whereColumn('products.id', 'daily_menus.product_id'))
            ->paginate(30)
            ->withQueryString();

        return view('admin.daily-menus.index', [
            'menus' => $menus,
            'date' => $date,
            'isToday' => $date->isSameDay(now()),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.daily-menus.create', [
            'products' => $this->activeProductsByCategory(),
            'date' => $this->dateFrom($request->query('date')),
            'statuses' => DailyMenuStatus::cases(),
        ]);
    }

    public function store(DailyMenuRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $product = Product::findOrFail($data['product_id']);

        // Harga kosong -> pakai harga dasar produk dari database.
        $data['price'] = $data['price'] ?? $product->base_price;

        try {
            $menu = DailyMenu::create($data);
        } catch (UniqueConstraintViolationException) {
            return back()->withInput()->withErrors([
                'menu_date' => 'Produk tersebut sudah ditambahkan ke menu pada tanggal yang dipilih.',
            ]);
        }

        return redirect()->route('admin.daily-menus.index', ['date' => $menu->menu_date->toDateString()])
            ->with('status', 'Menu harian berhasil ditambahkan.');
    }

    public function edit(DailyMenu $dailyMenu): View
    {
        return view('admin.daily-menus.edit', [
            'menu' => $dailyMenu->load('product.category'),
            'statuses' => DailyMenuStatus::cases(),
        ]);
    }

    public function update(DailyMenuRequest $request, DailyMenu $dailyMenu): RedirectResponse
    {
        try {
            $dailyMenu->update($request->validated());
        } catch (UniqueConstraintViolationException) {
            return back()->withInput()->withErrors([
                'menu_date' => 'Produk tersebut sudah ditambahkan ke menu pada tanggal yang dipilih.',
            ]);
        }

        return redirect()->route('admin.daily-menus.index', ['date' => $dailyMenu->menu_date->toDateString()])
            ->with('status', 'Menu harian berhasil diperbarui.');
    }

    public function destroy(DailyMenu $dailyMenu): RedirectResponse
    {
        $date = $dailyMenu->menu_date->toDateString();

        // Menu yang sudah dipakai pesanan dipertahankan sebagai riwayat.
        if ($dailyMenu->orderItems()->exists()) {
            return back()->with('error', 'Menu tidak dapat dihapus karena sudah dipakai pada pesanan. Ubah statusnya menjadi Nonaktif.');
        }

        $dailyMenu->delete();

        return redirect()->route('admin.daily-menus.index', ['date' => $date])
            ->with('status', 'Menu harian berhasil dihapus.');
    }

    /** Tanggal valid (Y-m-d) dari query string; selain itu hari ini. */
    private function dateFrom(mixed $value): Carbon
    {
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $date = Carbon::createFromFormat('!Y-m-d', $value);

            if ($date && $date->format('Y-m-d') === $value) {
                return $date;
            }
        }

        return now()->startOfDay();
    }

    private function activeProductsByCategory()
    {
        return Product::with('category')
            ->where('is_active', true)
            ->get()
            ->sortBy([['category.sort_order', 'asc'], ['name', 'asc']])
            ->groupBy(fn (Product $product) => $product->category->name);
    }
}
