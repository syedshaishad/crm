<?php

namespace Modules\Products;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Sellable catalog: services and goods with rates, HSN/SAC and GST.
 * Invoice/proposal lines are snapshots — editing a product never rewrites
 * issued documents, and deleting one only hides it from the picker.
 */
class Product extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'sku', 'description', 'rate', 'tax_rate', 'hsn_code', 'image_path', 'unit', 'opening_stock', 'low_stock_at', 'status'];

    protected $casts = ['rate' => 'decimal:2', 'tax_rate' => 'decimal:2', 'opening_stock' => 'decimal:2', 'low_stock_at' => 'decimal:2'];

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function stockEntries()
    {
        return $this->hasMany(StockEntry::class);
    }

    /** On-hand = opening + ins − outs ± adjustments. */
    public function stockOnHand(): float
    {
        $in = (float) $this->stockEntries()->where('type', 'in')->sum('qty');
        $out = (float) $this->stockEntries()->where('type', 'out')->sum('qty');
        $adj = (float) $this->stockEntries()->where('type', 'adjustment')->sum('qty');

        return round((float) ($this->opening_stock ?? 0) + $in - $out + $adj, 2);
    }

    public function isLowStock(): bool
    {
        return $this->low_stock_at !== null && $this->stockOnHand() <= (float) $this->low_stock_at;
    }
}
