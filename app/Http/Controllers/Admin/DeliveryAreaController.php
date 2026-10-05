<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DeliveryAreaRequest;
use App\Models\DeliveryArea;
use App\Support\Like;
use App\Support\Query;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DeliveryAreaController extends Controller
{
    public function index(Request $request): View
    {
        $search = Query::text($request, 'q');
        $status = in_array($request->query('status'), ['aktif', 'nonaktif'], true) ? $request->query('status') : null;

        $areas = DeliveryArea::withCount('orders')
            ->when($search !== '', fn ($q) => $q->whereRaw("district like ? escape '!'", [Like::contains($search)]))
            ->when($status, fn ($q) => $q->where('is_active', $status === 'aktif'))
            ->orderBy('district')
            ->paginate(25)
            ->withQueryString();

        return view('admin.delivery-areas.index', [
            'areas' => $areas,
            'filters' => ['q' => $search, 'status' => $status],
            'filtered' => $search !== '' || $status !== null,
        ]);
    }

    public function create(): View
    {
        return view('admin.delivery-areas.create');
    }

    public function store(DeliveryAreaRequest $request): RedirectResponse
    {
        DeliveryArea::create($request->validated());

        return redirect()->route('admin.delivery-areas.index')->with('status', 'Area pengiriman berhasil ditambahkan.');
    }

    public function edit(DeliveryArea $deliveryArea): View
    {
        return view('admin.delivery-areas.edit', [
            'area' => $deliveryArea->loadCount('orders'),
        ]);
    }

    /** Pesanan lama memakai snapshot nama & ongkir sendiri, jadi perubahan di sini tidak memengaruhinya. */
    public function update(DeliveryAreaRequest $request, DeliveryArea $deliveryArea): RedirectResponse
    {
        $deliveryArea->update($request->validated());

        return redirect()->route('admin.delivery-areas.index')->with('status', 'Area pengiriman berhasil diperbarui.');
    }

    public function toggle(DeliveryArea $deliveryArea): RedirectResponse
    {
        $deliveryArea->update(['is_active' => ! $deliveryArea->is_active]);

        return back()->with('status', 'Area pengiriman berhasil '.($deliveryArea->is_active ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    public function destroy(DeliveryArea $deliveryArea): RedirectResponse
    {
        $blocked = 'Area tidak dapat dihapus karena sudah dipakai pada pesanan. Nonaktifkan area ini agar tidak bisa dipilih untuk pesanan baru.';

        try {
            // Baris area dikunci selama pengecekan + penghapusan supaya pesanan baru yang
            // sedang dibuat tidak menyelinap di antaranya. FK RESTRICT tetap menjadi pengaman terakhir.
            $result = DB::transaction(function () use ($deliveryArea) {
                $area = DeliveryArea::whereKey($deliveryArea->id)->lockForUpdate()->first();

                if (! $area) {
                    return 'gone';
                }
                if ($area->orders()->exists()) {
                    return 'in_use';
                }

                $area->delete();

                return 'deleted';
            });
        } catch (QueryException) {
            return back()->with('error', $blocked);
        }

        if ($result === 'in_use') {
            return back()->with('error', $blocked);
        }

        return redirect()->route('admin.delivery-areas.index')->with('status', 'Area pengiriman berhasil dihapus.');
    }
}
