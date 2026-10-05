<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\SalesReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $today = now()->toImmutable()->startOfDay();
        $defaultFrom = $today->subDays(29);

        $validator = Validator::make($request->query(), [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'group' => ['nullable', Rule::in(SalesReport::GRANULARITIES)],
        ], [
            'from.date_format' => 'Tanggal awal tidak valid.',
            'to.date_format' => 'Tanggal akhir tidak valid.',
            'group.in' => 'Pilihan grafik tidak valid.',
        ]);

        $errors = $validator->errors()->all();
        $from = $defaultFrom;
        $to = $today;

        if (! $errors) {
            $from = $request->query('from') ? CarbonImmutable::createFromFormat('!Y-m-d', $request->query('from')) : $defaultFrom;
            $to = $request->query('to') ? CarbonImmutable::createFromFormat('!Y-m-d', $request->query('to')) : $today;

            if ($from > $to) {
                $errors[] = 'Tanggal awal tidak boleh setelah tanggal akhir.';
            } elseif ($from->diffInDays($to) + 1 > SalesReport::MAX_DAYS) {
                $errors[] = 'Rentang tanggal maksimal '.SalesReport::MAX_DAYS.' hari.';
            }

            if ($errors) {
                [$from, $to] = [$defaultFrom, $today];
            }
        }

        $report = new SalesReport($from, $to);
        $requested = $request->query('group');
        $granularity = $report->effectiveGranularity($errors ? null : $requested);
        $summary = $report->summary();
        $series = $summary['orders'] > 0 ? $report->series($granularity) : [];

        return view('admin.dashboard', [
            'missingSettings' => $this->missingSettings(),
            'errors' => $errors,
            'from' => $from,
            'to' => $to,
            'days' => $report->days(),
            'granularity' => $granularity,
            'granularityAdjusted' => ! $errors && $requested === 'daily' && $granularity !== 'daily',
            'summary' => $summary,
            'series' => $series,
            'topProducts' => $summary['orders'] > 0 ? $report->topProducts() : collect(),
            'statusCounts' => $report->statusCounts(),
            'statusLabels' => collect(OrderStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]),
            'presets' => [
                '7 hari' => [$today->subDays(6), $today],
                '30 hari' => [$defaultFrom, $today],
                'Bulan ini' => [$today->startOfMonth(), $today],
            ],
        ]);
    }

    /** Pengaturan penting yang masih kosong, berdasarkan data tersimpan. */
    private function missingSettings(): array
    {
        $missing = [];

        if (! Setting::get('whatsapp_number')) {
            $missing[] = 'Nomor WhatsApp';
        }
        if (! Setting::get('bank_account_number') && ! Setting::get('qris_image')) {
            $missing[] = 'Informasi pembayaran (rekening atau QRIS)';
        }
        if (! Setting::get('opening_hours')) {
            $missing[] = 'Jam operasional';
        }

        return $missing;
    }
}
