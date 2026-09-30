<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class OrderPdfService
{
    /**
     * PDF für eine Bestellung erzeugen und auf dem NAS (bzw. Fallback) ablegen.
     *
     * @return array{path: string, relative: string, disk: string}
     */
    public function generateAndStore(Order $order): array
    {
        $payload = $this->buildPayload($order);
        $pdf = Pdf::loadView('pdf.order', $payload)
            ->setPaper('a4', 'portrait');

        $relative = $this->relativePath($order);
        $disk = $this->resolveDisk();

        if ($disk === 'orders') {
            Storage::disk('orders')->put($relative, $pdf->output());
            $absolute = rtrim(config('orders.pdf_root'), DIRECTORY_SEPARATOR.'\\/')
                . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        } else {
            Storage::disk('local')->put('orders/'.$relative, $pdf->output());
            $absolute = storage_path('app/private/orders/'.$relative);
        }

        return [
            'path' => $absolute,
            'relative' => $relative,
            'disk' => $disk,
        ];
    }

    public function buildPayload(Order $order): array
    {
        $channel = $order->channel;
        $name = config("orders.names.{$channel}")
            ?? config('orders.names.guest');

        $groups = $this->groupItemsByCategory($order->items ?? []);
        $total = (float) $order->total;

        // Falls Total fehlt/veraltet: aus Positionen neu rechnen
        $calcTotal = 0.0;
        foreach ($groups as $group) {
            foreach ($group['items'] as $row) {
                $calcTotal += $row['line_total'];
            }
        }
        if ($total <= 0) {
            $total = $calcTotal;
        }

        return [
            'customer_name' => $name,
            'address' => config('orders.address') ?: '—',
            'delivery_date' => $order->delivery_date->locale('de')->translatedFormat('l, d.m.Y'),
            'delivery_date_short' => $order->delivery_date->format('d.m.Y'),
            'channel' => $channel,
            'groups' => $groups,
            'total' => $total,
            'calc_total' => $calcTotal,
            'generated_at' => now()->locale('de')->translatedFormat('d.m.Y H:i'),
            'order_id' => $order->id,
        ];
    }

    /** @return array<int, array{category: string, items: array<int, array<string, mixed>>}> */
    private function groupItemsByCategory(array $items): array
    {
        $productIds = [];
        foreach ($items as $item) {
            if (! empty($item['product_id'])) {
                $productIds[] = (int) $item['product_id'];
            }
        }

        $products = Product::query()
            ->whereIn('id', array_unique($productIds))
            ->get()
            ->keyBy('id');

        $grouped = [];

        foreach ($items as $key => $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $product = $products->get($productId);
            $category = 'Sonstiges';

            if ($product) {
                $category = json_decode($product->category, true)['de'] ?? 'Sonstiges';
            }

            $qty = (int) ($item['qty'] ?? 0);
            $unit = (float) ($item['price'] ?? 0);
            $line = isset($item['line_total'])
                ? (float) $item['line_total']
                : round($unit * $qty, 2);

            $extra = trim(($item['variant_name'] ?? '').' '.($item['size'] ?? ''));
            $label = $item['name'] ?? 'Artikel';
            if ($extra !== '') {
                $label .= ' ('.$extra.')';
            }

            if (! isset($grouped[$category])) {
                $grouped[$category] = [];
            }

            $grouped[$category][] = [
                'qty' => $qty,
                'label' => $label,
                'unit_price' => $unit,
                'line_total' => $line,
            ];
        }

        ksort($grouped, SORT_NATURAL | SORT_FLAG_CASE);

        $result = [];
        foreach ($grouped as $category => $rows) {
            $result[] = [
                'category' => $category,
                'items' => $rows,
                'subtotal' => array_sum(array_column($rows, 'line_total')),
            ];
        }

        return $result;
    }

    private function relativePath(Order $order): string
    {
        $date = $order->delivery_date instanceof Carbon
            ? $order->delivery_date
            : Carbon::parse($order->delivery_date);

        $slug = config("orders.filename_slugs.{$order->channel}")
            ?? $order->channel;

        return sprintf(
            '%s/%s/%s_%s.pdf',
            $date->format('Y'),
            $date->format('m'),
            $date->format('Y-m-d'),
            $slug
        );
    }

    private function resolveDisk(): string
    {
        $root = config('orders.pdf_root');

        if (! $root) {
            return 'local';
        }

        // Windows/UNC: is_writable() lügt oft → echte Schreibprobe
        try {
            if (! is_dir($root) && ! @mkdir($root, 0755, true) && ! is_dir($root)) {
                return 'local';
            }

            $probe = rtrim($root, "\\/").DIRECTORY_SEPARATOR.'.pdf_write_test_'.uniqid();
            if (@file_put_contents($probe, 'ok') !== false) {
                @unlink($probe);

                return 'orders';
            }
        } catch (\Throwable $e) {
            // NAS offline / kein Zugriff → Fallback
        }

        return 'local';
    }
}
