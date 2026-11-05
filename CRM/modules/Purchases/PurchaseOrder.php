<?php

namespace Modules\Purchases;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Gst\Calculator;

class PurchaseOrder extends Model
{
    use SoftDeletes;

    protected $fillable = ['number', 'vendor', 'vendor_email', 'project_id', 'issue_date', 'expected_on', 'status', 'subtotal', 'discount', 'taxable_value', 'gst_rate', 'gst_amount', 'total', 'notes', 'converted_expense_id', 'created_by', 'assigned_to'];

    protected $casts = ['issue_date' => 'date', 'expected_on' => 'date'];

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function expense()
    {
        return $this->belongsTo(\Modules\Expenses\Expense::class, 'converted_expense_id');
    }

    public function project()
    {
        return $this->belongsTo(\Modules\Projects\Project::class);
    }

    public static function statuses(): array
    {
        return ['draft' => 'Draft', 'sent' => 'Sent', 'received' => 'Received', 'cancelled' => 'Cancelled'];
    }

    public function recalc(): void
    {
        $computed = Calculator::computeLines($this->items()->get(), (float) ($this->discount ?? 0), $this->gst_rate);
        $this->subtotal = $computed['subtotal'];
        $this->discount = $computed['discount'];
        $this->taxable_value = $computed['taxable'];
        $this->gst_amount = $computed['gst'];
        $this->total = $computed['taxable'] + $computed['gst'];
        $this->save();
    }
}
