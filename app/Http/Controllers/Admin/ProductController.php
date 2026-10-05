<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Support\ImageProcessingException;
use App\Support\ImageStorage;
use App\Support\Like;
use App\Support\Query;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class ProductController extends Controller
{
    private const IMAGE_DIR = 'products';

    public function index(Request $request): View
    {
        $search = Query::text($request, 'q');
        $categoryId = Query::int($request, 'category');
        $status = in_array($request->query('status'), ['aktif', 'nonaktif'], true) ? $request->query('status') : null;

        $products = Product::with('category')
            ->when($search !== '', fn ($q) => $q->whereRaw("name like ? escape '!'", [Like::contains($search)]))
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->when($status, fn ($q) => $q->where('is_active', $status === 'aktif'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.products.index', [
            'products' => $products,
            'categories' => $this->categories(),
            'filters' => ['q' => $search, 'category' => $categoryId, 'status' => $status],
            'filtered' => $search !== '' || $categoryId || $status,
        ]);
    }

    public function create(): View
    {
        return view('admin.products.create', ['categories' => $this->categories()]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['image', 'remove_image']);

        try {
            $newImage = $request->hasFile('image')
                ? ImageStorage::store($request->file('image'), self::IMAGE_DIR)
                : null;
        } catch (ImageProcessingException $e) {
            return back()->withInput()->withErrors(['image' => $e->getMessage()]);
        }

        try {
            DB::transaction(fn () => Product::create($data + [
                'slug' => Product::uniqueSlugFor($data['name']),
                'image' => $newImage,
            ]));
        } catch (Throwable $e) {
            ImageStorage::delete($newImage, self::IMAGE_DIR); // jangan tinggalkan file yatim
            throw $e;
        }

        return redirect()->route('admin.products.index')->with('status', 'Produk berhasil ditambahkan.');
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
        $data = $request->safe()->except(['image', 'remove_image']);
        $oldImage = $product->image;
        $remove = $request->boolean('remove_image');

        try {
            $newImage = $request->hasFile('image')
                ? ImageStorage::store($request->file('image'), self::IMAGE_DIR)
                : null;
        } catch (ImageProcessingException $e) {
            // Record dan gambar lama tidak disentuh.
            return back()->withInput()->withErrors(['image' => $e->getMessage()]);
        }

        if ($newImage) {
            $data['image'] = $newImage;
        } elseif ($remove) {
            $data['image'] = null;
        }

        try {
            DB::transaction(fn () => $product->update($data));
        } catch (Throwable $e) {
            ImageStorage::delete($newImage, self::IMAGE_DIR);
            throw $e;
        }

        // Database sudah berhasil: baru sekarang gambar lama boleh dihapus.
        if (($newImage || $remove) && $oldImage) {
            $this->deleteImageIfUnused($oldImage, $product->id);
        }

        return redirect()->route('admin.products.index')->with('status', 'Produk berhasil diperbarui.');
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

        if ($product->image) {
            $this->deleteImageIfUnused($product->image, $product->id);
        }

        return redirect()->route('admin.products.index')->with('status', 'Produk berhasil dihapus.');
    }

    /** File hanya dihapus jika tidak dipakai produk lain dan path-nya milik aplikasi. */
    private function deleteImageIfUnused(string $path, int $exceptProductId): void
    {
        if (! Product::where('image', $path)->where('id', '!=', $exceptProductId)->exists()) {
            ImageStorage::delete($path, self::IMAGE_DIR);
        }
    }

    private function categories()
    {
        return Category::orderBy('sort_order')->orderBy('name')->get();
    }
}
