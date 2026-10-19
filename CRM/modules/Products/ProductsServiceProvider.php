<?php

namespace Modules\Products;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ProductsServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Catalog picker data on every invoice/proposal form (create + edit).
        // Controllers stay untouched; empty catalog hides the picker.
        View::composer(
            ['invoices.form', 'invoices.edit', 'proposals.form', 'proposals.edit', 'purchase-orders.form', 'challans.form'],
            function ($view) {
                $view->with('catalog', Product::active()->orderBy('name')->limit(300)
                    ->get(['id', 'name', 'rate', 'tax_rate', 'hsn_code', 'unit', 'image_path']));
            }
        );
    }
}
