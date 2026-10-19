{{-- One invoice line row. Pass the array index and the row's old values (null for a blank row). --}}
<div class="item-row"><input class="in" name="items[{{ $k }}][description]" placeholder="Service / item description" required value="{{ $it['description'] ?? '' }}">
<div style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">
<div style="flex:0 0 80px"><label class="f">Qty</label><input class="in" name="items[{{ $k }}][qty]" type="number" step="0.01" value="{{ $it['qty'] ?? 1 }}" style="margin-bottom:0"></div>
<div style="flex:0 0 80px"><label class="f">Unit</label><input class="in" name="items[{{ $k }}][unit]" value="{{ $it['unit'] ?? 'NOS' }}" list="unit-list" style="margin-bottom:0"></div>
<div style="flex:0 0 110px"><label class="f">HSN/SAC</label><input class="in" name="items[{{ $k }}][hsn_code]" placeholder="9983…" value="{{ $it['hsn_code'] ?? '' }}" style="margin-bottom:0"></div>
<div style="flex:1;min-width:110px"><label class="f">Rate (<span class="rate-cur">Rs.</span>)</label><input class="in" name="items[{{ $k }}][rate]" type="number" step="0.01" min="0" value="{{ $it['rate'] ?? 0 }}" style="margin-bottom:0"></div>
<div style="flex:0 0 70px"><label class="f">Disc%</label><input class="in" name="items[{{ $k }}][discount_percent]" type="number" step="0.01" min="0" max="100" value="{{ $it['discount_percent'] ?? 0 }}" style="margin-bottom:0"></div>
<div style="flex:0 0 90px"><label class="f">GST%</label><select class="in" name="items[{{ $k }}][tax_rate]" style="margin-bottom:0">@foreach([0,5,12,18,28] as $r)<option value="{{ $r }}" {{ (string)($it['tax_rate'] ?? $invoice->gst_rate ?? 18)===(string)$r?'selected':'' }}>{{ $r }}%</option>@endforeach</select></div>
@if($rows > 1)<button type="button" class="row-del" data-remove style="margin-bottom:10px">Remove</button>@endif
</div></div>