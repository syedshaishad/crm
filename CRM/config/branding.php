<?php

return [
    /*
    |--------------------------------------------------------------------------
    | White-label branding. DB (settings table) wins; .env is only install seed.
    | IMPORTANT: no hard-coded vendor branding anywhere in views/controllers.
    |--------------------------------------------------------------------------
    */
    'company_name' => env('BRAND_COMPANY_NAME', env('APP_NAME', 'CRM')),
    'tagline' => env('BRAND_TAGLINE', ''),
    'primary_color' => env('BRAND_PRIMARY_COLOR', '#4f46e5'),
    'accent_color' => env('BRAND_ACCENT_COLOR', '#06b6d4'),
    'link_color' => env('BRAND_LINK_COLOR', ''),
    'background_color' => env('BRAND_BG_COLOR', 'transparent'),
    'surface_color' => env('BRAND_SURFACE_COLOR', 'rgba(255,255,255,.70)'),
    'sidebar_bg' => env('BRAND_SIDEBAR_BG', 'rgba(255,255,255,.64)'),
    'sidebar_text' => env('BRAND_SIDEBAR_TEXT', '#101623'),
    'header_bg' => env('BRAND_HEADER_BG', ''),
    'text_color' => env('BRAND_TEXT_COLOR', '#101623'),
    'text_secondary' => env('BRAND_TEXT_SECONDARY', '#33405c'),
    'border_color' => env('BRAND_BORDER_COLOR', 'rgba(255,255,255,.72)'),
    'success_color' => env('BRAND_SUCCESS_COLOR', '#0d7a45'),
    'warning_color' => env('BRAND_WARNING_COLOR', '#9a6208'),
    'danger_color' => env('BRAND_DANGER_COLOR', '#c0271d'),
    'info_color' => env('BRAND_INFO_COLOR', '#1a5fd0'),
    'logo_path' => env('BRAND_LOGO_PATH', ''),
    'favicon_path' => env('BRAND_FAVICON_PATH', ''),
    'login_heading' => env('BRAND_LOGIN_HEADING', ''),
    'login_subtext' => env('BRAND_LOGIN_SUBTEXT', ''),
    'address' => env('BRAND_ADDRESS', ''),
    'gstin' => env('BRAND_GSTIN', ''),
    'invoice_prefix' => env('BRAND_INVOICE_PREFIX', 'INV-'),
    'invoice_footer' => env('BRAND_INVOICE_FOOTER', ''),

    // Changeable settings keys (kept in sync with the Settings UI forms).
    'editable_keys' => [
        'brand.company_name', 'brand.tagline',
        'brand.primary_color', 'brand.accent_color', 'brand.link_color',
        'brand.background_color', 'brand.surface_color',
        'brand.sidebar_bg', 'brand.sidebar_text', 'brand.header_bg',
        'brand.text_color', 'brand.text_secondary', 'brand.border_color',
        'brand.success_color', 'brand.warning_color', 'brand.danger_color', 'brand.info_color',
        'brand.logo_path', 'brand.favicon_path',
        'brand.login_heading', 'brand.login_subtext',
        'company.address', 'company.gstin', 'company.state', 'company.email', 'company.phone', 'company.website',
        'invoice.prefix', 'invoice.footer', 'invoice.logo_path', 'invoice.signature_path',
        'invoice.declaration', 'invoice.default_notes', 'invoice.due_days', 'invoice.units',
        'expense.prefix', 'expenses.categories', 'expenses.methods',
        'payments.prefix', 'payments.methods', 'payments.default_method',
        'proposal.prefix', 'proposal.terms', 'proposal.valid_days',
        'credit.prefix',
        'leads.default_view', 'tasks.default_view',
        'bank.name', 'bank.account_name', 'bank.account_number', 'bank.ifsc', 'bank.branch', 'bank.upi_id',
        'leads.sources',
        'tax.default_gst_rate', 'tax.enable_gst',
        'mail.from_address', 'mail.from_name',
        'email.invoice_subject', 'email.invoice_body',
        'email.receipt_subject', 'email.receipt_body',
        'email.proposal_subject', 'email.proposal_body',
        'email.reset_subject', 'email.reset_body',
        'app.name', 'app.currency', 'app.date_format', 'app.per_page',
        'uploads.receipt_max_mb', 'uploads.document_max_mb',
        'backups.retain_count',
    ],
];
