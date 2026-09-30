<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderPdfService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ExportOrderPdfs extends Command
{
    protected $signature = 'orders:export-pdf
                            {date? : Liefertag (Y-m-d), Standard: morgen}
                            {--channel= : Nur guest oder owner}
                            {--lock : Bestellungen danach auf locked setzen}';

    protected $description = 'Erzeugt Bestell-PDFs (Wechner-ähnlich) und legt sie auf dem NAS ab';

    public function handle(OrderPdfService $pdfs): int
    {
        $date = $this->argument('date')
            ? Carbon::parse($this->argument('date'))->toDateString()
            : now()->addDay()->toDateString();

        $query = Order::query()->whereDate('delivery_date', $date);

        if ($channel = $this->option('channel')) {
            $query->where('channel', $channel);
        }

        $orders = $query->orderBy('channel')->get();

        if ($orders->isEmpty()) {
            $this->warn("Keine Bestellungen für {$date}.");
            return self::SUCCESS;
        }

        foreach ($orders as $order) {
            try {
                $result = $pdfs->generateAndStore($order);
                $this->info("PDF: {$result['path']} ({$result['disk']})");

                if ($this->option('lock') && $order->isOpen()) {
                    $order->update(['status' => 'locked']);
                    $this->line("  → status = locked");
                }
            } catch (\Throwable $e) {
                $this->error("Fehler bei Order #{$order->id} ({$order->channel}): ".$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
