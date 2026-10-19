<?php

namespace Modules\Reports\Tally;

use Illuminate\Support\Facades\DB;

/**
 * Tally Prime import XML: party/tax masters + Sales and Purchase vouchers.
 * Books only: filed invoices (no drafts/cancelled), approved expenses.
 * Every voucher balances to zero (debits negative, credits positive).
 */
class TallyExport
{
    public static function xml(string $from, string $to): string
    {
        $invoices = DB::table('invoices')->join('clients', 'clients.id', '=', 'invoices.client_id')
            ->whereNotIn('invoices.status', ['draft', 'cancelled'])
            ->whereDate('invoices.issue_date', '>=', $from)->whereDate('invoices.issue_date', '<=', $to)
            ->orderBy('invoices.issue_date')->orderBy('invoices.id')
            ->get(['invoices.number', 'invoices.issue_date', 'invoices.taxable_value', 'invoices.cgst_amount', 'invoices.sgst_amount', 'invoices.igst_amount', 'invoices.total', 'invoices.gst_rate', 'clients.company', 'clients.name as client', 'clients.gstin', 'clients.state']);
        $expenses = DB::table('expenses')->where('status', 'approved')
            ->whereDate('spent_on', '>=', $from)->whereDate('spent_on', '<=', $to)
            ->orderBy('spent_on')->orderBy('id')
            ->get(['number', 'title', 'vendor', 'amount', 'spent_on']);

        $e = fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_XML1, 'UTF-8');
        $n = fn ($v) => number_format((float) $v, 2, '.', '');
        $dt = fn ($v) => str_replace('-', '', substr((string) $v, 0, 10));

        $masters = '';
        $seen = [];
        $ledger = function (string $name, string $parent, array $extra = []) use (&$masters, &$seen, $e) {
            $name = trim($name) ?: 'General';
            if (isset($seen[$name])) {
                return $name;
            }
            $seen[$name] = true;
            $tags = '';
            foreach ($extra as $k => $v) {
                if ($v !== '' && $v !== null) {
                    $tags .= "<$k>".$e($v)."</$k>";
                }
            }
            $masters .= "<TALLYMESSAGE><LEDGER NAME=\"".$e($name)."\" ACTION=\"Create\"><NAME>".$e($name)."</NAME><PARENT>".$e($parent)."</PARENT>$tags</LEDGER></TALLYMESSAGE>";

            return $name;
        };

        $ledger('Sales Account', 'Sales Accounts');
        $ledger('Purchase Account', 'Purchase Accounts');
        $taxLedgers = [];
        foreach ($invoices as $i) {
            $ledger($i->company ?: $i->client, 'Sundry Debtors', ['PARTYGSTIN' => $i->gstin, 'STATENAME' => $i->state, 'COUNTRYNAME' => 'India']);
            $half = round((float) $i->gst_rate / 2, 2);
            if ((float) $i->cgst_amount > 0) {
                $taxLedgers['c'.$half] = $ledger("CGST @ $half%", 'Duties & Taxes');
            }
            if ((float) $i->sgst_amount > 0) {
                $taxLedgers['s'.$half] = $ledger("SGST @ $half%", 'Duties & Taxes');
            }
            if ((float) $i->igst_amount > 0) {
                $taxLedgers['i'.$i->gst_rate] = $ledger("IGST @ {$i->gst_rate}%", 'Duties & Taxes');
            }
        }
        foreach ($expenses as $x) {
            if (trim((string) $x->vendor) !== '') {
                $ledger($x->vendor, 'Sundry Creditors');
            }
        }

        $txns = '';
        $entry = function (string $ledger, float $amount, bool $deemedPositive) use ($e, $n) {
            $dp = $deemedPositive ? 'Yes' : 'No';

            return "<ALLLEDGERENTRIES.LIST><LEDGERNAME>".$e($ledger)."</LEDGERNAME><ISDEEMEDPOSITIVE>$dp</ISDEEMEDPOSITIVE><AMOUNT>".$n($amount)."</AMOUNT></ALLLEDGERENTRIES.LIST>";
        };
        foreach ($invoices as $i) {
            $party = trim($i->company ?: $i->client) ?: 'General';
            $half = round((float) $i->gst_rate / 2, 2);
            $rows = $entry($party, -(float) $i->total, true)
                .$entry('Sales Account', (float) $i->taxable_value, false);
            if ((float) $i->cgst_amount > 0) {
                $rows .= $entry("CGST @ $half%", (float) $i->cgst_amount, false);
            }
            if ((float) $i->sgst_amount > 0) {
                $rows .= $entry("SGST @ $half%", (float) $i->sgst_amount, false);
            }
            if ((float) $i->igst_amount > 0) {
                $rows .= $entry("IGST @ {$i->gst_rate}%", (float) $i->igst_amount, false);
            }
            $txns .= "<TALLYMESSAGE><VOUCHER VCHTYPE=\"Sales\" ACTION=\"Create\">"
                ."<DATE>".$dt($i->issue_date)."</DATE><EFFECTIVEDATE>".$dt($i->issue_date)."</EFFECTIVEDATE>"
                ."<VOUCHERTYPENAME>Sales</VOUCHERTYPENAME><VOUCHERNUMBER>".$e($i->number)."</VOUCHERNUMBER>"
                ."<PARTYLEDGERNAME>".$e($party)."</PARTYLEDGERNAME>"
                ."<NARRATION>".$e('Sales '.$i->number.' — '.($i->company ?: $i->client))."</NARRATION>"
                .$rows."</VOUCHER></TALLYMESSAGE>";
        }
        foreach ($expenses as $x) {
            $party = trim((string) $x->vendor) !== '' ? trim((string) $x->vendor) : 'General Vendor';
            if ($party === 'General Vendor') {
                $ledger('General Vendor', 'Sundry Creditors');
            }
            $txns .= "<TALLYMESSAGE><VOUCHER VCHTYPE=\"Purchase\" ACTION=\"Create\">"
                ."<DATE>".$dt($x->spent_on)."</DATE><EFFECTIVEDATE>".$dt($x->spent_on)."</EFFECTIVEDATE>"
                ."<VOUCHERTYPENAME>Purchase</VOUCHERTYPENAME><VOUCHERNUMBER>".$e($x->number ?? '')."</VOUCHERNUMBER>"
                ."<PARTYLEDGERNAME>".$e($party)."</PARTYLEDGERNAME>"
                ."<NARRATION>".$e(($x->number ? $x->number.' — ' : '').$x->title)."</NARRATION>"
                .$entry('Purchase Account', -(float) $x->amount, true)
                .$entry($party, (float) $x->amount, false)
                ."</VOUCHER></TALLYMESSAGE>";
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<ENVELOPE><HEADER><TALLYREQUEST>Import Data</TALLYREQUEST></HEADER><BODY>'
            ."<IMPORTDATA><REQUESTDESC><REPORTNAME>All Masters</REPORTNAME></REQUESTDESC><REQUESTDATA>$masters</REQUESTDATA></IMPORTDATA>"
            ."<IMPORTDATA><REQUESTDESC><REPORTNAME>Vouchers</REPORTNAME></REQUESTDESC><REQUESTDATA>$txns</REQUESTDATA></IMPORTDATA>"
            .'</BODY></ENVELOPE>';
    }
}
