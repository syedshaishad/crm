@extends('layouts.app')
@section('title','Stock — '.$product->name)
@section('content')
<div class="page-head"><h1>Stock</h1><div class="crumb">{{ $product->name }}@if($product->sku) · {{ $product->sku }}@endif</div></div>

<div class="grid stats">
  <div class="stat acc" style="--acc:#0a84ff"><div class="lbl">On hand</div><b>{{ $onHand }} {{ $product->unit }}</b><div class="sub">opening {{ $product->opening_stock ?? 0 }}</div></div>
  <div class="stat acc" style="--acc:{{ $product->low_stock_at !== null && $onHand <= (float)$product->low_stock_at ? '#ff453a' : '#30d158' }}"><div class="lbl">Alert level</div><b>{{ $product->low_stock_at ?? '—' }}</b><div class="sub">{{ $product->low_stock_at !== null && $onHand <= (float)$product->low_stock_at ? 'low stock' : 'healthy' }}</div></div>
</div>

<div class="cols-2">
<div class="card"><div class="card-head"><h3>Movements</h3><a class="btn sm sec" href="{{ route('products.edit',$product) }}">Edit product</a></div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Date</th><th>Type</th><th style="text-align:right">Qty</th><th style="text-align:right">Balance</th><th>Reference</th><th class="noprint"></th></tr>
@forelse($rows as $r)<tr><td class="nowrap">{{ d($r['e']->moved_on) }}</td><td><span class="badge {{ $r['e']->type==='in' ? 'b-green' : ($r['e']->type==='out' ? 'b-amber' : 'b-grey') }}">{{ $r['e']->type }}</span></td><td class="nowrap" style="text-align:right">{{ $r['delta'] > 0 ? '+' : '' }}{{ $r['delta'] }}</td><td class="nowrap" style="text-align:right"><b>{{ $r['balance'] }}</b></td><td>{{ $r['e']->reference ?: '—' }}@if($r['e']->notes)<div class="hint">{{ $r['e']->notes }}</div>@endif</td>
<td class="noprint"><form method="POST" action="{{ route('products.stock.destroy',[$product,$r['e']]) }}" style="display:inline" data-confirm="Remove this stock entry? Balances recalculate.">@csrf @method('DELETE')<button class="icon-btn danger">Delete</button></form></td></tr>@empty<tr><td colspan="6"><div class="empty">No movements yet — opening stock is the whole balance.</div></td></tr>@endforelse</table></div></div>
<div class="card"><div class="card-head"><h3>Record movement</h3></div>
<form method="POST" action="{{ route('products.stock.store',$product) }}">@csrf
<label class="f">Type</label><select class="in" name="type"><option value="in">In — purchase / production</option><option value="out">Out — sale / issue</option><option value="adjustment">Adjustment — signed correction</option></select>
<div class="form-grid">
<div><label class="f">Qty</label><input class="in" type="number" step="0.01" name="qty" required placeholder="0.00"></div>
<div><label class="f">Date</label><input class="in" type="date" name="moved_on" value="{{ date('Y-m-d') }}" required></div>
</div>
<label class="f">Reference</label><input class="in" name="reference" placeholder="Bill no, invoice no…">
<label class="f">Notes</label><textarea class="in" name="notes" rows="2"></textarea>
<div class="form-actions"><button class="btn" type="submit">Save movement</button></div>
</form></div>
<div class="card"><div class="card-head"><h3>Physical audit</h3><span class="hint">books say {{ $onHand }} {{ $product->unit }}</span></div>
<form method="POST" action="{{ route('products.stock.audit',$product) }}">@csrf
<label class="f">Counted qty</label><input class="in" type="number" step="0.01" min="0" name="counted" required placeholder="What you actually counted">
<div><label class="f">Date</label><input class="in" type="date" name="moved_on" value="{{ date('Y-m-d') }}" required></div>
<p class="fhint">The difference posts itself as an adjustment — no maths needed.</p>
<div class="form-actions"><button class="btn sec" type="submit">Post audit</button></div>
</form></div>
</div>
@endsection
