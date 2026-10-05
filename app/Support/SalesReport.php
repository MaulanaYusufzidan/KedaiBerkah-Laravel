<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Laporan penjualan untuk dashboard admin.
 *
 * Definisi omzet terealisasi: jumlah orders.total dari pesanan yang punya pembayaran
 * berstatus "paid" dan status pesanan bukan "cancelled" (lihat Order::scopeRealized).
 * Tanggal transaksi memakai orders.created_at dalam timezone aplikasi (Asia/Jakarta).
 * Semua agregasi dilakukan di database; PHP hanya mengelompokkan baris harian
 * (maksimal MAX_DAYS baris) menjadi minggu/bulan.
 */
class SalesReport
{
    public const MAX_DAYS = 366;

    public const DAILY_MAX_DAYS = 62;

    public const GRANULARITIES = ['daily', 'weekly', 'monthly'];

    public readonly CarbonImmutable $from;

    public readonly CarbonImmutable $to;

    private ?Collection $dailyCache = null;

    public function __construct(CarbonInterface $from, CarbonInterface $to)
    {
        $this->from = CarbonImmutable::instance($from)->startOfDay();
        $this->to = CarbonImmutable::instance($to)->endOfDay();
    }

    public function days(): int
    {
        return (int) $this->from->startOfDay()->diffInDays($this->to->startOfDay()) + 1;
    }

    /** Granularitas yang dipakai: otomatis jika kosong, dan harian dibatasi untuk rentang pendek. */
    public function effectiveGranularity(?string $requested): string
    {
        if (! in_array($requested, self::GRANULARITIES, true)) {
            return match (true) {
                $this->days() <= 31 => 'daily',
                $this->days() <= 180 => 'weekly',
                default => 'monthly',
            };
        }

        return ($requested === 'daily' && $this->days() > self::DAILY_MAX_DAYS) ? 'weekly' : $requested;
    }

    /** @return array{revenue:int, orders:int, average:int} */
    public function summary(): array
    {
        $orders = $this->realizedOrders()->count();
        $revenue = (int) $this->realizedOrders()->sum('total');

        return [
            'revenue' => $revenue,
            'orders' => $orders,
            'average' => $orders > 0 ? intdiv($revenue, $orders) : 0,
        ];
    }

    /** @return Collection<string, array{revenue:int, orders:int}> dikunci Y-m-d, hanya hari yang punya transaksi */
    public function daily(): Collection
    {
        return $this->dailyCache ??= $this->realizedOrders()
            ->selectRaw('date(created_at) as day, count(*) as orders, sum(total) as revenue')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->mapWithKeys(fn ($row) => [
                (string) $row->day => ['revenue' => (int) $row->revenue, 'orders' => (int) $row->orders],
            ]);
    }

    /**
     * Deret grafik dengan hari/minggu/bulan kosong terisi 0 agar sumbu waktu kontinu.
     *
     * @return list<array{label:string, title:string, revenue:int, orders:int}>
     */
    public function series(string $granularity): array
    {
        $daily = $this->daily();
        $buckets = [];

        for ($day = $this->from->startOfDay(); $day <= $this->to; $day = $day->addDay()) {
            $data = $daily->get($day->format('Y-m-d'), ['revenue' => 0, 'orders' => 0]);

            [$key, $label, $title] = match ($granularity) {
                'weekly' => $this->weekBucket($day),
                'monthly' => [
                    $day->format('Y-m'),
                    $day->locale('id')->isoFormat('MMM YY'),
                    $day->locale('id')->isoFormat('MMMM Y'),
                ],
                default => [
                    $day->format('Y-m-d'),
                    $day->locale('id')->isoFormat('D MMM'),
                    $day->locale('id')->isoFormat('dddd, D MMMM Y'),
                ],
            };

            $buckets[$key] ??= ['label' => $label, 'title' => $title, 'revenue' => 0, 'orders' => 0];
            $buckets[$key]['revenue'] += $data['revenue'];
            $buckets[$key]['orders'] += $data['orders'];
        }

        return array_values($buckets);
    }

    /** @return Collection<int, object{product_name:string, qty:int, revenue:int}> */
    public function topProducts(int $limit = 5): Collection
    {
        return OrderItem::query()
            ->whereHas('order', fn (Builder $q) => $q->realized()->whereBetween('created_at', [$this->from, $this->to]))
            ->selectRaw('product_name, sum(quantity) as qty, sum(line_total) as revenue')
            ->groupBy('product_name')
            ->orderByDesc('qty')
            ->orderByDesc('revenue')
            ->orderBy('product_name')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (object) [
                'product_name' => $row->product_name,
                'qty' => (int) $row->qty,
                'revenue' => (int) $row->revenue,
            ]);
    }

    /** Jumlah semua pesanan pada periode per status (termasuk yang belum dibayar/dibatalkan). */
    public function statusCounts(): array
    {
        $counts = Order::query()
            ->whereBetween('created_at', [$this->from, $this->to])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $result = [];
        foreach (OrderStatus::cases() as $status) {
            $result[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return $result;
    }

    private function realizedOrders(): Builder
    {
        return Order::query()->realized()->whereBetween('created_at', [$this->from, $this->to]);
    }

    /** Minggu dimulai hari Senin; label memakai tanggal awal minggu (dipotong ke awal periode). */
    private function weekBucket(CarbonImmutable $day): array
    {
        $start = $day->startOfWeek(Carbon::MONDAY);
        $end = $start->addDays(6);
        $shownStart = $start < $this->from ? $this->from->startOfDay() : $start;
        $shownEnd = $end > $this->to ? $this->to->startOfDay() : $end;

        $label = $shownStart->locale('id')->isoFormat('D MMM');
        $title = $shownStart->locale('id')->isoFormat('D MMM').' – '.$shownEnd->locale('id')->isoFormat('D MMM Y');

        return [$start->format('Y-m-d'), $label, 'Minggu '.$title];
    }
}
