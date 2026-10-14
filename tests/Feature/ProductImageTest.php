<?php

namespace Tests\Feature;

use App\Models\ProductImage;
use App\Models\SupplierProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    private User $supplier;

    private SupplierProduct $listing;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->supplier = $this->makeSupplier();
        $product = $this->makeProduct(['name' => 'Simba Cement 42.5R']);
        $this->listing = $this->supplier->supplier->supplierProducts()->create(['product_id' => $product->id]);
    }

    private function upload(array $files, ?SupplierProduct $listing = null): TestResponse
    {
        $listing ??= $this->listing;

        return $this->post("/api/supplier-products/{$listing->id}/images", ['images' => $files], ['Accept' => 'application/json']);
    }

    public function test_supplier_uploads_photos_that_are_resized_and_re_encoded(): void
    {
        Sanctum::actingAs($this->supplier);

        $response = $this->upload([
            UploadedFile::fake()->image('front.jpg', 2400, 1800),
            UploadedFile::fake()->image('back.png', 600, 600),
        ])->assertCreated()->assertJsonCount(2, 'images');

        $first = ProductImage::orderBy('id')->first();
        $this->assertTrue($first->is_primary);
        $this->assertFalse(ProductImage::orderBy('id')->skip(1)->first()->is_primary);
        $this->assertSame(1600, $first->width, 'Large variant is capped at 1600px.');

        foreach (['thumb', 'large'] as $variant) {
            $path = $first->path($variant);
            Storage::disk('public')->assertExists($path);
            $info = getimagesizefromstring(Storage::disk('public')->get($path));
            $this->assertContains($info['mime'], ['image/webp', 'image/jpeg']);
        }
        $this->assertLessThanOrEqual(480, getimagesizefromstring(Storage::disk('public')->get($first->path('thumb')))[0]);

        // The original upload name never appears in storage.
        $this->assertStringNotContainsString('front', $response->getContent());

        // Served same-origin with long caching, no cookies.
        $this->get($first->urls['thumb'])
            ->assertOk()
            ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public')
            ->assertHeaderMissing('Set-Cookie');
    }

    public function test_files_that_are_not_real_photos_are_rejected(): void
    {
        Sanctum::actingAs($this->supplier);

        $this->upload([UploadedFile::fake()->create('notes.pdf', 20, 'application/pdf')])->assertUnprocessable();

        // A script renamed to .jpg is judged by its content, not its name.
        $fake = UploadedFile::fake()->createWithContent('photo.jpg', "<?php echo 'hi'; ?>");
        $this->upload([$fake])->assertUnprocessable();

        // SVG can carry scripts and is not accepted.
        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->upload([$svg])->assertUnprocessable();

        $this->upload([UploadedFile::fake()->image('tiny.jpg', 100, 100)])->assertUnprocessable();
        $this->upload([UploadedFile::fake()->image('huge.jpg', 5000, 300)])->assertUnprocessable();
        $this->upload([UploadedFile::fake()->image('big.jpg', 800, 800)->size(6000)])->assertUnprocessable();

        $this->assertSame(0, ProductImage::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_photo_count_is_limited_per_listing(): void
    {
        Sanctum::actingAs($this->supplier);

        $files = fn ($n) => array_map(fn ($i) => UploadedFile::fake()->image("p{$i}.jpg", 300, 300), range(1, $n));

        $this->upload($files(6))->assertCreated();
        $this->upload($files(3))->assertUnprocessable()->assertJsonValidationErrors('images');
        $this->assertSame(6, ProductImage::count());
    }

    public function test_only_the_owner_can_change_a_listings_photos(): void
    {
        Sanctum::actingAs($this->supplier);
        $this->upload([UploadedFile::fake()->image('a.jpg', 400, 400)])->assertCreated();
        $image = ProductImage::first();

        $intruder = $this->makeSupplier();
        Sanctum::actingAs($intruder);
        $this->upload([UploadedFile::fake()->image('b.jpg', 400, 400)])->assertForbidden();
        $this->deleteJson("/api/supplier-products/{$this->listing->id}/images/{$image->id}")->assertForbidden();

        // An image id from another listing does not resolve under this one.
        $ownListing = $intruder->supplier->supplierProducts()->create(['product_id' => $this->makeProduct()->id]);
        $this->deleteJson("/api/supplier-products/{$ownListing->id}/images/{$image->id}")->assertNotFound();

        Sanctum::actingAs($this->makeBuyer());
        $this->upload([UploadedFile::fake()->image('c.jpg', 400, 400)])->assertForbidden();

        $this->assertSame(1, ProductImage::count());
    }

    public function test_primary_photo_can_be_changed_and_deleting_cleans_up_files(): void
    {
        Sanctum::actingAs($this->supplier);
        $this->upload([UploadedFile::fake()->image('a.jpg', 400, 400), UploadedFile::fake()->image('b.jpg', 400, 400)])->assertCreated();
        [$a, $b] = ProductImage::orderBy('id')->get();

        $this->postJson("/api/supplier-products/{$this->listing->id}/images/{$b->id}/primary")
            ->assertOk()
            ->assertJsonPath('images.0.id', $b->id)
            ->assertJsonPath('images.0.is_primary', true);

        $this->deleteJson("/api/supplier-products/{$this->listing->id}/images/{$b->id}")->assertOk();
        Storage::disk('public')->assertMissing($b->path('thumb'));
        $this->assertTrue($a->fresh()->is_primary, 'The next photo becomes the main one.');

        // Removing the listing removes its photo files too.
        $this->deleteJson("/api/supplier-products/{$this->listing->id}")->assertOk();
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame(0, ProductImage::count());
    }

    public function test_photos_appear_on_catalog_cards_and_product_pages_for_active_listings_only(): void
    {
        Sanctum::actingAs($this->supplier);
        $this->upload([UploadedFile::fake()->image('a.jpg', 400, 400)])->assertCreated();
        $image = ProductImage::first();
        $productId = $this->listing->product_id;

        $this->getJson('/api/products?search=simba')
            ->assertJsonPath('data.0.image.thumb', $image->urls['thumb'])
            ->assertJsonPath('data.0.image.source', 'supplier');
        $this->getJson("/api/products/{$productId}")
            ->assertJsonPath('data.images.0.large', $image->urls['large'])
            ->assertJsonPath('data.images.0.supplier', $this->supplier->supplier->business_name);
        $this->getJson('/api/products/suggest?q=simba')->assertJsonPath('data.0.image', $image->urls['thumb']);

        // A hidden listing's photos are not shown to buyers.
        $this->listing->update(['is_active' => false]);
        $this->getJson('/api/products?search=simba')->assertJsonPath('data.0.image', null);
        $this->getJson("/api/products/{$productId}")->assertJsonCount(0, 'data.images');
    }

    public function test_admin_manages_catalog_photos_which_take_priority(): void
    {
        Sanctum::actingAs($this->supplier);
        $this->upload([UploadedFile::fake()->image('a.jpg', 400, 400)])->assertCreated();

        $productId = $this->listing->product_id;
        $this->post("/api/admin/catalog/products/{$productId}/images", ['images' => [UploadedFile::fake()->image('c.jpg', 400, 400)]], ['Accept' => 'application/json'])
            ->assertForbidden();

        Sanctum::actingAs($this->makeAdmin());
        $catalogImage = $this->post("/api/admin/catalog/products/{$productId}/images", [
            'images' => [UploadedFile::fake()->image('c.jpg', 400, 400)],
            'alt_text' => 'Grey 50kg bag',
        ], ['Accept' => 'application/json'])->assertCreated()->json('images.0');

        $this->getJson('/api/products?search=simba')
            ->assertJsonPath('data.0.image.thumb', $catalogImage['urls']['thumb'])
            ->assertJsonPath('data.0.image.alt', 'Grey 50kg bag')
            ->assertJsonPath('data.0.image.source', 'catalog');
    }

    public function test_media_route_only_serves_recorded_images(): void
    {
        $this->get('/media/products/00000000-0000-0000-0000-000000000000/thumb.webp')->assertNotFound();
        $this->get('/media/products/../../.env')->assertNotFound();
        $this->get('/media/other/thing.webp')->assertNotFound();
    }
}
