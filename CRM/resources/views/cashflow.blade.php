@extends('layouts.app')
@section('title','Cash flow')
@section('head')<link rel="stylesheet" href="{{ asset('assets/css/dc.css').'?v=9' }}">@endsection
@section('content')
<div class="dc">
  <section class="dc-gc" style="padding:16px;margin-bottom:14px">
    <div class="dc-sh2"><h2>Cash flow &amp; collections</h2><a href="{{ route('dashboard') }}">← Dashboard</a></div>
    <p class="hint" style="margin:2px 0 14px">Expected money in comes from open invoice balances by due date. Planned money out comes from active recurring expenses. Weeks run Monday to Sunday.</p>
    <div class="dc-kp">
      <div class="dc-k"><small>Outstanding</small><b>{{ money($outstanding) }}</b></div>
      <div class="dc-k"><small>Overdue</small><b>{{ money($overdueTotal) }}</b></div>
      <div class="dc-k"><small>Due in 7 days</small><b>{{ money($dueWeek) }}</b></div>
      <div class="dc-k"><small>Expected · 13 weeks</small><b>{{ money(collect($weeks)->sum('in')) }}</b></div>
      <div class="dc-k dk"><small>Planned out</small><b>{{ money(collect($weeks)->sum('out')) }}</b></div>
    </div>
  </section>

  <section class="dc-gc" style="padding:16px;margin-bottom:14px">
    <div class="dc-sh2"><h2>Next 13 weeks</h2><span class="hint">{{ $start->format('j M') }} – {{ $end->format('j M Y') }}</span></div>
    @if(collect($weeks)->sum('in') + collect($weeks)->sum('out') <= 0)
    <p class="hint" style="margin:10px 0 18px">No forward cash movements yet. Open invoices with a future due date and active recurring expenses will show up here.</p>
    @endif
    <div class="cf-scroll">
      <div class="cf-chart" role="img" aria-label="Expected money in and planned money out for each of the next thirteen weeks">
        @foreach($weeks as $w)
        <div class="cf-col">
          <div class="cf-bars">
            <div class="cf-bar cf-in" style="height:{{ max(2, (int) round($w['in'] / $maxFlow * 100)) }}%" title="Expected in {{ money($w['in']) }}"></div>
            <div class="cf-bar cf-out" style="height:{{ max(2, (int) round($w['out'] / $maxFlow * 100)) }}%" title="Planned out {{ money($w['out']) }}"></div>
          </div>
          <small>{{ $w['label'] }}</small>
        </div>
        @endforeach
      </div>
    </div>
    <p class="hint" style="margin:12px 0 4px"><b style="color:var(--dc-green)">●</b> Money in &nbsp; <b style="color:var(--dc-amber)">●</b> Money out</p>
    <div class="tbl-scroll roomy">
      <table class="tbl">
        <thead><tr><th scope="col">Week starting</th><th scope="col">Expected in</th><th scope="col">Planned out</th><th scope="col">Net</th><th scope="col">Running position</th></tr></thead>
        <tbody>
          @foreach($weeks as $w)
          <tr>
            <td>{{ $w['label'] }} <span class="hint">→ {{ \Carbon\Carbon::parse($w['to'])->format('d M') }}</span></td>
            <td>{{ money($w['in']) }}</td>
            <td>{{ money($w['out']) }}</td>
            <td style="color:{{ $w['net'] < 0 ? 'var(--dc-red)' : 'var(--dc-green)' }}">{{ money($w['net']) }}</td>
            <td>{{ money($w['cum']) }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>

  <div class="dc-g2">
    <section class="dc-gc" style="flex:1 1 380px;padding:16px">
      <div class="dc-sh2"><h2>Receivables by age</h2><a href="{{ route('reports.receivables') }}">Full ageing →</a></div>
      <table class="tbl">
        <thead><tr><th scope="col">Bucket</th><th scope="col">Invoices</th><th scope="col">Balance</th></tr></thead>
        <tbody>
          @foreach($buckets as $label => $amount)
          <tr>
            <td>{{ $label }}</td>
            <td>{{ $bucketCounts[$label] }}</td>
            <td><b>{{ money($amount) }}</b></td>
          </tr>
          @endforeach
        </tbody>
      </table>
      <p class="hint" style="margin:10px 0 0">Balances exclude paid and written-off amounts.</p>
    </section>

    <section class="dc-gc" style="flex:1 1 380px;padding:16px">
      <div class="dc-sh2"><h2>Who to chase</h2><span class="hint">Oldest and largest first</span></div>
      @if($chase->isEmpty())
        <p class="dc-em">Nothing due or overdue in the next 7 days. Good spot.</p>
      @else
        @foreach($chase as $x)
        <div class="dc-rw">
          <span class="dc-av">{{ strtoupper(substr($x['client'], 0, 1)) }}</span>
          <span style="flex:1;min-width:0">
            <a href="{{ route('invoices.show', $x['id']) }}"><b>{{ $x['number'] }}</b> · {{ $x['client'] }}</a>
            <span class="hint">Due {{ d($x['due_date'],'j M Y') }}
              @if($x['days_overdue']) · {{ $x['days_overdue'] }} days late @endif
            </span>
          </span>
          <b>{{ money($x['balance']) }}</b>
          <form method="POST" action="{{ route('invoices.remind', $x['id']) }}" data-ajax-post data-refresh="page">@csrf
            <button class="btn sm sec" type="submit">Remind</button>
          </form>
        </div>
        @endforeach
      @endif
    </section>
  </div>
</div>
@endsection
