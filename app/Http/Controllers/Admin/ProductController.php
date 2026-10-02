<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        $products = Product::with('category')
            ->orderBy('name')
            ->paginate(20);

        return view('admin.products.index', compact('products'));
    }

    public function create(): View
    {
        return view('admin.products.create', ['categories' => $this->categories()]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $data = $request->validated();

        Product::create($data + ['slug' => Product::uniqueSlugFor($data['name'])]);

        return redirect()->route('admin.products.index')
            ->with('status', 'Produk berhasil ditambahkan.');
    }

    public function edit(Product $product): View
    {
        return view('admin.products.edit', [
            'product' => $product,
            'categories' => $this->categories(),
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        // Slug tidak diubah saat nama diedit, supaya URL tetap stabil.
        $product->update($request->validated());

        return redirect()->route('admin.products.index')
            ->with('status', 'Produk berhasil diperbarui.');
    }

    public function toggle(Product $product): RedirectResponse
    {
        $product->update(['is_active' => ! $product->is_active]);

        return back()->with('status', 'Produk berhasil '.($product->is_active ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    public function destroy(Product $product): RedirectResponse
    {
        $blocked = 'Produk tidak dapat dihapus karena sudah dipakai pada menu harian. Nonaktifkan produk jika tidak ingin dijual lagi.';

        if ($product->dailyMenus()->exists()) {
            return back()->with('error', $blocked);
        }

        try {
            $product->delete();
        } catch (QueryException) {
            return back()->with('error', $blocked);
        }

        return redirect()->route('admin.products.index')
            ->with('status', 'Produk berhasil dihapus.');
    }

    private function categories()
    {
        return Category::orderBy('sort_order')->orderBy('name')->get();
    }
}
