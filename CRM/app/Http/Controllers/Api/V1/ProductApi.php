<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ProductResource;
use Illuminate\Http\Request;
use Modules\Products\Product;

class ProductApi extends BaseApi
{
    public function index(Request $r)
    {
        $q = Product::orderBy('name');
        if ($r->boolean('active_only', true) && ! $r->filled('status')) {
            $q->where('status', 'active');
        }
        $rows = $this->applySearch($r, $q, ['name', 'sku', 'description', 'hsn_code'], ['status' => 'status']);

        return ProductResource::collection($rows);
    }

    public function show(Product $product)
    {
        return new ProductResource($product);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'name' => 'required|string|max:255', 'sku' => 'nullable|string|max:40',
            'description' => 'nullable|string', 'rate' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:28', 'hsn_code' => 'nullable|string|max:20',
            'unit' => 'nullable|string|max:10', 'status' => 'nullable|in:active,archived',
        ]);
        $data['rate'] ??= 0;
        $data['unit'] ??= 'NOS';

        return (new ProductResource(Product::create($data)))->response()->setStatusCode(201);
    }

    public function update(Request $r, Product $product)
    {
        $data = $r->validate([
            'name' => 'sometimes|required|string|max:255', 'sku' => 'nullable|string|max:40',
            'description' => 'nullable|string', 'rate' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:28', 'hsn_code' => 'nullable|string|max:20',
            'unit' => 'nullable|string|max:10', 'status' => 'nullable|in:active,archived',
        ]);
        $product->update($data);

        return new ProductResource($product->fresh());
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}
