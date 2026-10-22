<?php

namespace Tests\Feature;

use App\Livewire\InfoWifi;
use App\Livewire\Shop;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DemoModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_family_device_on_demo_sees_the_guest_shell(): void
    {
        $this->fromFamily()
            ->get('/demo')
            ->assertOk()
            ->assertHeader('Content-Security-Policy', 'frame-ancestors *')
            ->assertSee('window.HAUS_MELI_DEMO = true', false)
            ->assertSee('data-tab="order"', false)
            ->assertSee('data-tab="tours"', false)
            ->assertDontSee('data-tab="knx"', false)
            ->assertDontSee('id="guest-preview-form"', false);
    }

    public function test_demo_hides_the_wifi_password(): void
    {
        Livewire::withHeaders([
            'X-Haus-Meli-Demo' => '1',
            'Referer' => 'http://192.168.1.10:8081/demo',
        ])->test(InfoWifi::class)
            ->assertSee('In der Demo gibt es kein WLAN.')
            ->assertDontSee('wifi-pass-display');
    }

    public function test_family_home_stays_the_real_app(): void
    {
        $this->fromFamily()
            ->get('/')
            ->assertOk()
            ->assertSee('window.HAUS_MELI_DEMO = false', false)
            ->assertSee('data-tab="knx"', false)
            ->assertDontSee('data-tab="tours"', false)
            ->assertSee('id="guest-preview-form"', false);
    }

    public function test_requests_from_the_demo_page_stay_in_demo_mode(): void
    {
        $this->fromFamily()
            ->withHeaders([
                'Referer' => 'http://192.168.1.10:8081/demo',
                'X-Haus-Meli-Demo' => '1',
            ])
            ->getJson('/api/whoami')
            ->assertOk()
            ->assertJson([
                'family' => true,
                'demo' => true,
                'guest_preview' => true,
                'tours' => true,
            ]);
    }

    public function test_family_home_request_is_not_a_demo(): void
    {
        $this->fromFamily()
            ->getJson('/api/whoami')
            ->assertJson([
                'family' => true,
                'demo' => false,
                'guest_preview' => false,
                'tours' => false,
            ]);
    }

    public function test_saved_tours_in_the_demo_stay_in_the_session(): void
    {
        Storage::fake('local');

        $route = [
            'id' => 'demo1',
            'name' => 'Hoher Riffler',
            'savedAt' => '2026-10-02T10:00:00Z',
            'route' => [
                'startNodeId' => 1,
                'steps' => [['segmentId' => 4]],
            ],
        ];

        $this->withHeaders([
            'Referer' => 'http://192.168.1.10:8081/demo',
            'X-Haus-Meli-Demo' => '1',
        ])->postJson('/tours/saved', ['routes' => [$route]])
            ->assertOk()
            ->assertJsonPath('routes.0.id', 'demo1');

        Storage::disk('local')->assertMissing('tours/saved-routes.json');

        $this->withHeaders([
            'Referer' => 'http://192.168.1.10:8081/demo',
        ])->getJson('/tours/saved')
            ->assertOk()
            ->assertJsonPath('routes.0.name', 'Hoher Riffler');
    }

    public function test_demo_does_not_store_an_accent_color(): void
    {
        $this->withHeaders(['X-Haus-Meli-Demo' => '1'])
            ->postJson('/gast-farbe', ['accent' => '#660000'])
            ->assertStatus(422);
    }

    public function test_demo_cart_and_order_do_not_touch_the_shared_shop(): void
    {
        $product = Product::create([
            'code' => 'Prod-demo',
            'name' => json_encode(['de' => 'Semmel']),
            'category' => json_encode(['de' => 'Backwaren']),
            'super_category' => json_encode(['de' => 'Brot']),
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'name' => json_encode(['de' => 'Stück']),
            'price' => 1.2,
            'stock' => 8,
        ]);

        Cache::put('guest_cart_delivery', ['keep' => true]);
        Cache::put('owner_preview_cart_delivery', ['keep' => true]);
        Cache::put('owner_cart_delivery', ['keep' => true]);

        $shop = Livewire::withHeaders([
            'X-Haus-Meli-Demo' => '1',
            'Referer' => 'http://192.168.1.10:8081/demo',
        ])->test(Shop::class);

        $shop->call('addToCart', $product->id, $variant->id, 1)
            ->assertSet('cartDelivery.'.$product->id.'_'.$variant->id.'.qty', 1)
            ->call('placeOrder');

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(8, $variant->fresh()->stock);
        $this->assertSame(['keep' => true], Cache::get('guest_cart_delivery'));
        $this->assertSame(['keep' => true], Cache::get('owner_preview_cart_delivery'));
        $this->assertSame(['keep' => true], Cache::get('owner_cart_delivery'));
        $this->assertSame(1, session('demo.cart.cart_delivery')[$product->id.'_'.$variant->id]['qty']);
    }

    private function fromFamily(): static
    {
        return $this->withServerVariables([
            'REMOTE_ADDR' => '192.168.1.20',
        ]);
    }
}
