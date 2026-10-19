{{-- Central theme tokens. Every standalone page (auth, portal, public links,
installer, licensing) includes this so branding changes propagate everywhere. --}}
@php
  $tb = $brand ?? [];
  $isHex = fn ($v) => (bool) preg_match('/^#[0-9a-fA-F]{6}$/', trim((string) $v));
  // Raw (stored) brand colour — only drives the DC accent when explicitly configured,
  // so the design system keeps its built-in accent on a fresh install.
  $ba = trim((string) setting('brand.primary_color'));
  if ($ba === '') { $ba = trim((string) setting('brand.accent_color')); }
  $headDecl = $ba !== '' ? '--brand-accent:'.$ba.';' : '';
  // Brand surfaces drive the DC design-system tokens (light mode; dark keeps its own palette).
  $dcMap = [
    'background_color' => '--dc-page',
    'sidebar_bg'       => '--dc-shell',
    'surface_color'    => '--dc-panel',
    'header_bg'        => '--dc-soft',
    'text_color'       => '--dc-ink',
    'text_secondary'   => '--dc-muted',
    'sidebar_text'     => '--dc-ink2',
    'border_color'     => '--dc-line',
  ];
  foreach ($dcMap as $bk => $var) {
    $v = trim((string) setting('brand.'.$bk));
    if ($isHex($v)) { $headDecl .= $var.':'.$v.';'; }
  }
  $sc = trim((string) setting('brand.surface_color'));
  if ($isHex($sc)) { $headDecl .= '--dc-card:'.$sc.';'; }
  $bc = trim((string) setting('brand.border_color'));
  if ($isHex($bc)) { $headDecl .= '--dc-line2:color-mix(in srgb, '.$bc.' 78%, #161313);'; }
  // Brand status colours drive the DC badge/alert tints (light + dark).
  foreach (['success_color' => '--brand-success', 'warning_color' => '--brand-warning', 'danger_color' => '--brand-danger', 'info_color' => '--brand-info'] as $bk => $var) {
    $v = trim((string) setting('brand.'.$bk));
    if ($isHex($v)) { $headDecl .= $var.':'.$v.';'; }
  }
@endphp
<style>:root{ {!! $headDecl !!}--primary:{{ ($tb['primary_color'] ?? '') ?: '#4f46e5' }};--accent:{{ ($tb['accent_color'] ?? '') ?: '#06b6d4' }};--link:{{ ($tb['link_color'] ?? '') ?: (($tb['primary_color'] ?? '') ?: '#4f46e5') }};--bg:{{ ($tb['background_color'] ?? '') ?: 'transparent' }};--surface:{{ ($tb['surface_color'] ?? '') ?: 'rgba(255,255,255,.70)' }};--side-bg:{{ ($tb['sidebar_bg'] ?? '') ?: 'rgba(255,255,255,.64)' }};--side-text:{{ ($tb['sidebar_text'] ?? '') ?: '#101623' }};--head-bg:{{ $tb['header_bg'] ?? '' }};--ink:{{ ($tb['text_color'] ?? '') ?: '#101623' }};--ink2:{{ ($tb['text_secondary'] ?? '') ?: '#33405c' }};--line:{{ ($tb['border_color'] ?? '') ?: 'rgba(255,255,255,.72)' }};--ok:{{ ($tb['success_color'] ?? '') ?: '#0d7a45' }};--warn:{{ ($tb['warning_color'] ?? '') ?: '#9a6208' }};--bad:{{ ($tb['danger_color'] ?? '') ?: '#c0271d' }};--info:{{ ($tb['info_color'] ?? '') ?: '#1a5fd0' }};--color-primary:var(--primary);--color-accent:var(--accent);--color-background:var(--bg);--color-surface:var(--surface);--color-sidebar:var(--side-bg);--color-sidebar-text:var(--side-text);--color-text-primary:var(--ink);--color-text-secondary:var(--ink2);--color-border:var(--line);--color-success:var(--ok);--color-warning:var(--warn);--color-danger:var(--bad);--color-info:var(--info);}</style>
