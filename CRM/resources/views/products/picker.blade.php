@if(!empty($catalog) && count($catalog))
<div class="fsec">Add from catalog</div>
<div style="position:relative;margin-bottom:12px" class="noprint">
  <input class="in" id="catalog-search" placeholder="Type a product or service name, then pick…" autocomplete="off" style="margin:0">
  <div class="combo-pop" id="catalog-pop"></div>
</div>
<script type="application/json" id="catalog-data">{!! $catalog->toJson() !!}</script>
@endif
