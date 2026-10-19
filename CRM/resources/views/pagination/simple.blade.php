@php
  $cur = $paginator->currentPage();
  $last = $paginator->lastPage();
  $from = max(1, min($cur - 2, $last - 4));
  $to = min($last, max($cur + 2, 5));
  $first = $paginator->firstItem() ?? 0;
  $toItem = $paginator->lastItem() ?? 0;
@endphp
<div class="tbl-foot pg-foot noprint" role="navigation" aria-label="Pagination">
  <span class="pg-info">Showing {{ $first }} to {{ $toItem }} of {{ $paginator->total() }} entries</span>
  <span class="pg-tools">
    <label class="pg-rows">Rows per page
      <select data-per-page aria-label="Rows per page">
        @foreach([10, 15, 20, 30, 50] as $n)<option value="{{ $n }}" {{ $paginator->perPage() == $n ? 'selected' : '' }}>{{ $n }}</option>@endforeach
      </select>
    </label>
    @if ($paginator->hasPages())
    <span class="pg-nav">
      @if ($paginator->onFirstPage())
        <span class="pg disabled">← Previous</span>
      @else
        <a class="pg" href="{{ $paginator->previousPageUrl() }}" rel="prev">← Previous</a>
      @endif
      @if($from > 1)
        <a class="pg pg-num" href="{{ $paginator->url(1) }}">1</a>
        @if($from > 2)<span class="pg-gap">…</span>@endif
      @endif
      @for($p = $from; $p <= $to; $p++)
        @if($p === $cur)
          <span class="pg pg-num cur" aria-current="page">{{ $p }}</span>
        @else
          <a class="pg pg-num" href="{{ $paginator->url($p) }}">{{ $p }}</a>
        @endif
      @endfor
      @if($to < $last)
        @if($to < $last - 1)<span class="pg-gap">…</span>@endif
        <a class="pg pg-num" href="{{ $paginator->url($last) }}">{{ $last }}</a>
      @endif
      @if ($paginator->hasMorePages())
        <a class="pg" href="{{ $paginator->nextPageUrl() }}" rel="next">Next →</a>
      @else
        <span class="pg disabled">Next →</span>
      @endif
    </span>
    @endif
  </span>
</div>
