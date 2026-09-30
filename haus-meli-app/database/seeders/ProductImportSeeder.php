<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Str;

class ProductImportSeeder extends Seeder
{
    /**
     * Hilfsfunktion: Erzwingt UTF-8, egal was reinkommt.
     */
    private function forceUtf8($string) {
        if (!$string) return '';
        if (mb_check_encoding($string, 'UTF-8')) {
            return trim($string);
        }
        return trim(mb_convert_encoding($string, 'UTF-8', 'Windows-1252'));
    }

    public function run(): void
    {
        $csvFile = base_path('products.csv');
        if (!file_exists($csvFile)) $csvFile = storage_path('app/products.csv');

        if (!file_exists($csvFile)) {
            $this->command->error("❌ Datei 'products.csv' nicht gefunden!");
            return;
        }

        $lines = file($csvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if (count($lines) < 2) {
            $this->command->error("❌ Datei scheint leer zu sein.");
            return;
        }

        // 1. Trennzeichen erkennen
        $firstLine = $lines[0];
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        $this->command->info("ℹ️  Erkanntes Trennzeichen: '{$delimiter}'");

        // 2. Header parsen
        $headerRaw = str_getcsv($firstLine, $delimiter);
        
        $header = [];
        foreach ($headerRaw as $col) {
            $col = $this->forceUtf8($col);
            $col = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $col); // BOM weg
            $header[] = strtolower(trim($col)); 
        }

        $this->command->warn("Gefundene Spalten: " . implode(' | ', $header));

        $map = array_flip($header);
        
        // HIER IST DIE KORREKTUR: Wir prüfen auf name_de statt id!
        if (!isset($map['name_de'])) {
            $this->command->error("❌ Spalte 'name_de' fehlt! (Gefunden: " . implode(', ', $header) . ")");
            return;
        }
        if (!isset($map['price'])) {
            $this->command->error("❌ Spalte 'price' fehlt!");
            return;
        }

        $count = 0;
        
        // 3. Zeilen verarbeiten
        for ($i = 1; $i < count($lines); $i++) {
            $line = $lines[$i];
            $row = str_getcsv($line, $delimiter);

            if (count($row) < 2) continue;

            $val = function($key) use ($row, $map) {
                $idx = $map[$key] ?? -1;
                if ($idx === -1 || !isset($row[$idx])) return '';
                return $this->forceUtf8($row[$idx]);
            };

            // --- DIE NEUE LOGIK OHNE EXCEL-ID ---
            $nameDe = $val('name_de');
            if (!$nameDe) continue; // Wenn kein Name da ist, überspringen

            // Wir generieren die ID automatisch aus dem Namen!
            // Aus "Kappler Honig" wird "kappler-honig"
            $code = Str::slug($nameDe); 

            // --- PREIS REPARATUR ---
            $rawPrice = $val('price'); 
            $cleanPrice = str_replace(',', '.', $rawPrice);
            $cleanPrice = preg_replace('/[^0-9.]/', '', $cleanPrice);
            $price = (float) $cleanPrice;

            if ($count < 3) {
                $this->command->line("--------------------------------");
                $this->command->info("Importiere: " . $nameDe);
                $this->command->line("  Generierte ID: {$code}");
                $this->command->line("  Original Preis: '{$rawPrice}' -> Datenbank: {$price}");
            }

            // --- STOCK REPARATUR ---
            $rawMax = $val('stock');
            $stock = ($rawMax === '' || $rawMax === null) ? null : (int)$rawMax;

            // --- DATENBANK PRODUKT ---
            $product = Product::updateOrCreate(
                ['code' => $code], // Wir suchen nach dem generierten Namen (z.B. 'semmel')
                [
                    'name' => json_encode([
                        'de' => $nameDe,
                        'en' => $val('name_en')
                    ], JSON_UNESCAPED_UNICODE),
                    'category' => json_encode([
                        'de' => $val('category_de'),
                        'en' => $val('category_en')
                    ], JSON_UNESCAPED_UNICODE),
                    'super_category' => json_encode([
                        'de' => $val('super_category_de'),
                        'en' => $val('super_category_en')
                    ], JSON_UNESCAPED_UNICODE),
                    
                    'image_path' => $nameDe . '.png', 
                    'is_active' => true,
                    'sort_order' => $i, 
                ]
            );

            // Allergene
            $allergens = [];
            foreach (['a', 'g', 'e', 'n', 'f', 'c', 'h'] as $allergen) {
                // Wir suchen nach der kleingeschriebenen Spalte ('a', 'g'...)
                if ($val($allergen)) { 
                    $allergens[] = strtoupper($allergen); // Speichern es aber groß ('A')
                }
            }

            // Variante
            ProductVariant::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'name->de'   => $val('variant_de'), 
                ],
                [
                    'name' => json_encode([
                        'de' => $val('variant_de'),
                        'en' => $val('variant_en')
                    ], JSON_UNESCAPED_UNICODE),
                    'description' => json_encode([
                        'de' => $val('info_de'),
                        'en' => $val('info_en')
                    ], JSON_UNESCAPED_UNICODE),
                    'notes' => json_encode([
                        'de' => $val('notes_de'),
                        'en' => $val('notes_en')
                    ], JSON_UNESCAPED_UNICODE),
                    'price' => $price,
                    'size' => $val('size'),
                    'stock' => $stock,
                    'sale_month' => is_numeric($val('sale')) ? (int)$val('sale') : null,
                    'allergens' => json_encode($allergens),
                ]
            );

            $count++;
        }

        $this->command->info("\n✅ Fertig! {$count} Produkte erfolgreich importiert.");
    }
}