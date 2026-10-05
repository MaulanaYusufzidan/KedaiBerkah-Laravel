<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FulfillmentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderTransitionRequest;
use App\Models\Order;
use App\Support\Like;
use App\Support\OrderTransitionException;
use App\Support\OrderWorkflow;
use App\Support\Phone;
use App\Support\Query;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $search = Query::text($request, 'q');
        $status = OrderStatus::tryFrom(Query::text($request, 'status'));
        $payment = PaymentStatus::tryFrom(Query::text($request, 'payment'));
        $fulfillment = FulfillmentType::tryFrom(Query::text($request, 'fulfillment'));
        $from = $this->date($request->query('from'));
        $to = $this->date($request->query('to'));

        // Filter selain status dipakai juga untuk ringkasan jumlah per status.
        $base = Order::query()
            ->when($search !== '', fn (Builder $q) => $q->where(function (Builder $q) use ($search) {
                $q->whereRaw("order_number like ? escape '!'", [Like::contains($search)])
                    ->orWhereRaw("customer_name like ? escape '!'", [Like::contains($search)]);
            }))
            ->when($from, fn (Builder $q) => $q->where('created_at', '>=', $from->startOfDay()))
            ->when($to, fn (Builder $q) => $q->where('created_at', '<=', $to->endOfDay()))
            ->when($fulfillment, fn (Builder $q) => $q->where('fulfillment_type', $fulfillment->value))
            ->when($payment, fn (Builder $q) => $q->whereHas('payment', fn (Builder $p) => $p->where('status', $payment->value)));

        $counts = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $orders = (clone $base)
            ->when($status, fn (Builder $q) => $q->where('status', $status->value))
            ->with('payment')
            ->withCount('items')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'counts' => $counts,
            'statuses' => OrderStatus::cases(),
            'paymentStatuses' => PaymentStatus::cases(),
            'fulfillments' => FulfillmentType::cases(),
            'filters' => [
                'q' => $search,
                'status' => $status?->value,
                'payment' => $payment?->value,
                'fulfillment' => $fulfillment?->value,
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
            ],
            'filtered' => $search !== '' || $status || $payment || $fulfillment || $from || $to,
        ]);
    }

    public function show(Order $order, OrderWorkflow $workflow): View
    {
        $order->load(['items', 'deliveryArea', 'payments.verifier', 'statusHistories.changedBy']);
        $payment = $order->payments->sortByDesc('id')->first();
        $phone = Phone::normalize($order->customer_phone);

        return view('admin.orders.show', [
            'order' => $order,
            'payment' => $payment,
            'actions' => $workflow->availableActions($order),
            'actionLabels' => OrderWorkflow::ACTIONS,
            'noteRequired' => OrderWorkflow::NOTE_REQUIRED,
            'customerWhatsapp' => Phone::whatsappUrl($phone, "Halo {$order->customer_name}, kami dari Kedai Berkah menghubungi terkait pesanan {$order->order_number}."),
        ]);
    }

    public function transition(OrderTransitionRequest $request, Order $order, OrderWorkflow $workflow): RedirectResponse
    {
        try {
            $updated = $workflow->apply($order, $request->validated('action'), $request->user(), $request->validated('note'));
        } catch (OrderTransitionException $e) {
            return redirect()->route('admin.orders.show', $order)->with('error', $e->getMessage());
        }

        return redirect()->route('admin.orders.show', $order)
            ->with('status', 'Status pesanan diperbarui menjadi "'.$updated->status->label().'".');
    }

    /** Bukti pembayaran disimpan privat; hanya admin yang dapat membukanya lewat route ini. */
    public function proof(Order $order): BinaryFileResponse
    {
        $path = $order->payments()->latest('id')->value('proof');
        $disk = Storage::disk('local');

        abort_unless(
            $path && preg_match('#^payment-proofs/[A-Za-z0-9._-]{1,100}$#', $path) && ! str_contains($path, '..') && $disk->exists($path),
            404
        );

        return response()->file($disk->path($path), [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'",
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function date(mixed $value): ?Carbon
    {
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $date = Carbon::createFromFormat('!Y-m-d', $value);

            return $date && $date->format('Y-m-d') === $value ? $date : null;
        }

        return null;
    }
}
