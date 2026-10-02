<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = Category::withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(25);

        return view('admin.categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('admin.categories.create', [
            'nextSortOrder' => ((int) Category::max('sort_order')) + 1,
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();

        Category::create($data + ['slug' => Category::uniqueSlugFor($data['name'])]);

        return redirect()->route('admin.categories.index')
            ->with('status', 'Kategori berhasil ditambahkan.');
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.edit', compact('category'));
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        // Slug tidak diubah saat nama diedit, supaya URL tetap stabil.
        $category->update($request->validated());

        return redirect()->route('admin.categories.index')
            ->with('status', 'Kategori berhasil diperbarui.');
    }

    public function toggle(Category $category): RedirectResponse
    {
        $category->update(['is_active' => ! $category->is_active]);

        return back()->with('status', 'Kategori berhasil '.($category->is_active ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    public function destroy(Category $category): RedirectResponse
    {
        $blocked = 'Kategori tidak dapat dihapus karena masih digunakan oleh produk. Nonaktifkan kategori jika tidak ingin ditampilkan.';

        if ($category->products()->exists()) {
            return back()->with('error', $blocked);
        }

        try {
            $category->delete();
        } catch (QueryException) {
            return back()->with('error', $blocked);
        }

        return redirect()->route('admin.categories.index')
            ->with('status', 'Kategori berhasil dihapus.');
    }
}
