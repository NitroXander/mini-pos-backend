<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $products = Product::query()
            ->orderBy('name')
            ->get();

        return ProductResource::collection($products);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        $product = Product::query()->create($this->validated($request));
        AuditLogger::record(
            $request->user(),
            'product.created',
            'product',
            $product->id,
            after: $product->auditSnapshot(),
        );

        return (new ProductResource($product))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product);
    }

    public function update(Request $request, Product $product): ProductResource
    {
        $this->authorizeManage($request);

        $before = $product->auditSnapshot();
        $product->update($this->validated($request, $product));
        AuditLogger::record(
            $request->user(),
            'product.updated',
            'product',
            $product->id,
            $before,
            $product->auditSnapshot(),
        );

        return new ProductResource($product);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $this->authorizeManage($request);

        $before = $product->auditSnapshot();
        $productId = $product->id;
        $product->delete();

        AuditLogger::record(
            $request->user(),
            'product.deleted',
            'product',
            $productId,
            before: $before,
        );

        return response()->json(null, 204);
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()?->managesProducts() ?? false, 403, 'You cannot manage products.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Product $product = null): array
    {
        $shopId = $request->user()->shop_id;

        $rules = [
            'sku' => [
                'required',
                'string',
                'max:64',
                Rule::unique('products', 'sku')
                    ->where(fn ($query) => $query->where('shop_id', $shopId))
                    ->ignore($product),
            ],
            'name' => ['required', 'string', 'max:255'],
            'sell_price_minor' => ['required', 'integer', 'min:0'],
            'unit' => ['nullable', 'string', 'max:32'],
            'barcode' => ['nullable', 'string', 'max:64'],
            'is_active' => ['sometimes', 'boolean'],
            'stock_on_hand' => ['required', 'numeric', 'min:0'],
        ];

        if ($request->user()->seesCost()) {
            $rules['cost_minor'] = ['required', 'integer', 'min:0'];
        }

        $data = $request->validate($rules);

        $data['unit'] = $data['unit'] ?? 'pcs';
        $data['barcode'] = blank($data['barcode'] ?? null) ? null : $data['barcode'];
        $data['is_active'] = $data['is_active'] ?? true;

        if (! $request->user()->seesCost()) {
            unset($data['cost_minor']);

            if ($product === null) {
                $data['cost_minor'] = 0;
            }
        }

        return $data;
    }
}
