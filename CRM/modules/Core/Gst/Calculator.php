<?php

namespace Modules\Core\Gst;

/**
 * Pure GST math. One engine, three consumers (invoices, proposals, reports).
 *
 * Knows nothing about Eloquent: callers hand over line objects exposing
 * `amount` and `tax_rate` (null = fall back to the document default rate),
 * and the invoice/proposal-level discount. All money is rounded to 2dp.
 */
class Calculator
{
    /** Round to money precision (2dp). */
    public static function money(float $v): float
    {
        return round($v, 2);
    }

    /** Stable group key for a tax rate (avoids float-equality bugs). */
    public static function slabKey(float $rate): string
    {
        return number_format($rate, 2, '.', '');
    }

    /**
     * Spread the discount pro-rata over the lines, tax each line at its own
     * rate (or $defaultRate when the line has none).
     *
     * Lines exposing netAmount() (per-item discount) are taxed on the net —
     * the invoice-level discount then spreads over the netted lines.
     *
     * @param  iterable<object{amount: mixed, tax_rate: mixed}>  $items
     * @return array{subtotal: float, discount: float, taxable: float, gst: float, lines: list<array{taxable: float, rate: float, tax: float}>}
     */
    public static function computeLines(iterable $items, float $discount, ?float $defaultRate): array
    {
        $lines = [];
        $subtotal = 0.0;
        foreach ($items as $it) {
            $amt = method_exists($it, 'netAmount') ? (float) $it->netAmount() : (float) $it->amount;
            $subtotal += $amt;
            $lines[] = ['item' => $it, 'amount' => $amt, 'gross' => (float) $it->amount];
        }
        $subtotal = self::money($subtotal);
        // Discount is never negative and never exceeds what is being discounted.
        $discount = max(0.0, min($discount, $subtotal));

        $taxable = 0.0;
        $gst = 0.0;
        foreach ($lines as $i => $l) {
            $share = $subtotal > 0 ? $l['amount'] / $subtotal : 0;
            $lineTaxable = self::money($l['amount'] - $discount * $share);
            $rate = $l['item']->tax_rate !== null ? (float) $l['item']->tax_rate : (float) ($defaultRate ?? 0);
            $tax = self::money($lineTaxable * $rate / 100);
            $lines[$i]['taxable'] = $lineTaxable;
            $lines[$i]['rate'] = $rate;
            $lines[$i]['tax'] = $tax;
            $taxable += $lineTaxable;
            $gst += $tax;
        }

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'item_discount' => self::money(array_sum(array_column($lines, 'gross')) - $subtotal),
            'taxable' => self::money($taxable),
            'gst' => self::money($gst),
            'lines' => $lines,
        ];
    }

    /** Slab-wise breakup: [['rate'=>18,'taxable'=>x,'tax'=>y], ...] ascending. */
    public static function slabs(array $lines): array
    {
        $slabs = [];
        foreach ($lines as $l) {
            $k = self::slabKey($l['rate']);
            $slabs[$k] ??= ['rate' => $l['rate'], 'taxable' => 0.0, 'tax' => 0.0];
            $slabs[$k]['taxable'] = self::money($slabs[$k]['taxable'] + $l['taxable']);
            $slabs[$k]['tax'] = self::money($slabs[$k]['tax'] + $l['tax']);
        }
        ksort($slabs);

        return array_values($slabs);
    }

    /**
     * GSTR-1 style summary over documents (each with ->items, ->discount,
     * ->gst_rate, ->isIntraState() and the stored CGST/SGST/IGST columns).
     *
     * @return array{slabs: list<array{rate: float, taxable: float, cgst: float, sgst: float, igst: float, tax: float}>, totals: array{taxable: float, cgst: float, sgst: float, igst: float, count: int}}
     */
    public static function gstrSummary(iterable $documents): array
    {
        $slabs = [];
        $totals = ['taxable' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'igst' => 0.0, 'count' => 0];
        foreach ($documents as $doc) {
            $computed = self::computeLines($doc->items, (float) ($doc->discount ?? 0), $doc->gst_rate !== null ? (float) $doc->gst_rate : null);
            $intra = $doc->isIntraState();
            foreach ($computed['lines'] as $l) {
                $k = self::slabKey($l['rate']);
                $slabs[$k] ??= ['rate' => $l['rate'], 'taxable' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'igst' => 0.0, 'tax' => 0.0];
                $slabs[$k]['taxable'] = self::money($slabs[$k]['taxable'] + $l['taxable']);
                $slabs[$k]['tax'] = self::money($slabs[$k]['tax'] + $l['tax']);
                if ($intra) {
                    $slabs[$k]['cgst'] = self::money($slabs[$k]['cgst'] + $l['tax'] / 2);
                    $slabs[$k]['sgst'] = self::money($slabs[$k]['sgst'] + $l['tax'] / 2);
                } else {
                    $slabs[$k]['igst'] = self::money($slabs[$k]['igst'] + $l['tax']);
                }
            }
            $totals['taxable'] = self::money($totals['taxable'] + (float) $doc->taxable_value);
            $totals['cgst'] = self::money($totals['cgst'] + (float) $doc->cgst_amount);
            $totals['sgst'] = self::money($totals['sgst'] + (float) $doc->sgst_amount);
            $totals['igst'] = self::money($totals['igst'] + (float) $doc->igst_amount);
            $totals['count']++;
        }
        ksort($slabs);
        $totals['slabs'] = array_values($slabs);

        return ['slabs' => array_values($slabs), 'totals' => $totals];
    }
}
