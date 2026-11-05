<?php

namespace Modules\Settings\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Modules\Installer\EnvWriter;
use Modules\Licensing\LicenseManager;
use Modules\Settings\SettingStore;
use Modules\Updater\UpdateManager;
use Modules\Updater\VersionTracker;

class SettingsController extends Controller
{
    public function __construct()
    {
        // Every method here controls the whole install (keys, backups, code
        // updates, licenses) — administrators only. Per-user pages (account,
        // 2FA) live in UsersController.
        $this->middleware('admin');
    }

    public function index()
    {
        return view('settings.index');
    }

    public function general(SettingStore $s)
    {
        return view('settings.general', ['s' => $s, 'tz' => config('app.timezone')]);
    }

    public function generalStore(Request $r, SettingStore $s)
    {
        $data = $r->validate([
            'app_currency' => 'required|string|max:10',
            'app_date_format' => 'required|string|max:20',
            'app_per_page' => 'required|integer|min:5|max:100',
            'app_timezone' => 'required|string|max:60',
            'uploads_receipt_max_mb' => 'nullable|integer|min:1|max:100',
            'uploads_document_max_mb' => 'nullable|integer|min:1|max:100',
            'leads_default_view' => 'nullable|in:list,board',
            'leads_stale_days' => 'nullable|integer|min:1|max:90',
            'tasks_default_view' => 'nullable|in:list,calendar',
            'projects_hourly_rate' => 'nullable|numeric|min:0',
            'team_weekly_hours' => 'nullable|integer|min:1|max:168',
            'leave_sick' => 'nullable|integer|min:0|max:365',
            'leave_casual' => 'nullable|integer|min:0|max:365',
            'leave_earned' => 'nullable|integer|min:0|max:365',
            'reminders_enabled' => 'nullable|boolean',
            'reminders_clients' => 'nullable|boolean',
            'company_monthly_target' => 'nullable|numeric|min:0',
            'backups_retain_count' => 'nullable|integer|min:1|max:3',
            'reminders_recipients' => 'nullable|string|max:2000',
        ]);
        $s->set('app.currency', strtoupper($data['app_currency']), 'app');
        $s->set('app.date_format', $data['app_date_format'], 'app');
        $s->set('app.per_page', $data['app_per_page'], 'app');
        $s->set('uploads.receipt_max_mb', $data['uploads_receipt_max_mb'] ?? 10, 'uploads');
        $s->set('uploads.document_max_mb', $data['uploads_document_max_mb'] ?? 20, 'uploads');
        $s->set('leads.default_view', $data['leads_default_view'] ?? 'list', 'leads');
        $s->set('leads.stale_days', $data['leads_stale_days'] ?? 7, 'leads');
        $s->set('tasks.default_view', $data['tasks_default_view'] ?? 'list', 'tasks');
        $s->set('projects.hourly_rate', $data['projects_hourly_rate'] ?? 0, 'projects');
        $s->set('team.weekly_hours', $data['team_weekly_hours'] ?? 48, 'team');
        $s->set('leave.sick', $data['leave_sick'] ?? 6, 'leave');
        $s->set('leave.casual', $data['leave_casual'] ?? 6, 'leave');
        $s->set('leave.earned', $data['leave_earned'] ?? 12, 'leave');
        $s->set('reminders.enabled', isset($data['reminders_enabled']) ? '1' : '0', 'reminders');
        $s->set('reminders.clients', isset($data['reminders_clients']) ? '1' : '0', 'reminders');
        $s->set('backups.retain_count', $data['backups_retain_count'] ?? 3, 'backups');
        $s->set('reminders.recipients', $data['reminders_recipients'] ?? '', 'reminders');
        $s->set('company.monthly_target', $data['company_monthly_target'] ?? 0, 'company');
        app(EnvWriter::class)->write(['APP_TIMEZONE' => $data['app_timezone']]);

        return back()->with('ok', 'General settings saved. Timezone applies after config refresh.');
    }

    public function billing(SettingStore $s)
    {
        return view('settings.billing', ['s' => $s]);
    }

    public function billingStore(Request $r, SettingStore $s)
    {
        $data = $r->validate([
            'invoice_prefix' => 'nullable|string|max:20',
            'expense_prefix' => 'nullable|string|max:20',
            'payment_prefix' => 'nullable|string|max:20',
            'proposal_prefix' => 'nullable|string|max:20',
            'proposal_terms' => 'nullable|string',
                        'proposal_theme' => 'nullable|in:classic,compact',
            'proposal_discount_limit' => 'nullable|numeric|min:0|max:100',            'proposal_valid_days' => 'nullable|integer|min:1|max:365',
            'proposal_followup_days' => 'nullable|string|max:50',
            'credit_prefix' => 'nullable|string|max:20',
            'invoice_currencies' => 'nullable|string|max:200',
            'numbering_mode' => 'nullable|in:continuous,fy',
            'bank_name' => 'nullable|string|max:100',
            'bank_account_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:50',
            'bank_ifsc' => 'nullable|string|max:20',
            'bank_branch' => 'nullable|string|max:100',
            'bank_upi_id' => 'nullable|string|max:100',
            'expense_categories' => 'nullable|string|max:500',
            'expense_methods' => 'nullable|string|max:200',
            'payment_methods' => 'nullable|string|max:200',
            'invoice_units' => 'nullable|string|max:200',
            'lead_sources' => 'nullable|string|max:500',
            'invoice_due_days' => 'nullable|integer|min:0|max:365',
            'invoice_theme' => 'nullable|in:classic,compact',            'invoice_default_notes' => 'nullable|string',
            'invoice_footer' => 'nullable|string',
            'invoice_declaration' => 'nullable|string',
            'payments_default_method' => 'nullable|string|max:20',
            'invoice_logo' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:2048',
            'invoice_logo_dark' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:2048',
            'signature' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:2048',            'late_fee_enabled' => 'nullable|boolean',
            'late_fee_type' => 'nullable|in:flat,percent',
            'late_fee_value' => 'nullable|numeric|min:0',
            'late_fee_grace_days' => 'nullable|integer|min:0|max:365',
            'cheque_alert_days' => 'nullable|integer|min:1|max:90',
            'collections_dunning_days' => 'nullable|string|max:50',
            'loyalty_earn_percent' => 'nullable|numeric|min:0|max:100',
            'payroll_overtime_rate' => 'nullable|numeric|min:1|max:5',
            'payroll_tds_percent' => 'nullable|numeric|min:0|max:30',
        ]);
        $s->set('billing.late_fee_enabled', isset($data['late_fee_enabled']) ? '1' : '0', 'billing');
        foreach (['invoice_prefix' => 'invoice.prefix', 'expense_prefix' => 'expense.prefix', 'payment_prefix' => 'payments.prefix', 'proposal_prefix' => 'proposal.prefix', 'numbering_mode' => 'numbering.mode', 'proposal_terms' => 'proposal.terms', 'proposal_theme' => 'proposal.theme', 'proposal_discount_limit' => 'proposal.discount_limit', 'proposal_valid_days' => 'proposal.valid_days', 'proposal_followup_days' => 'proposal.followup_days', 'credit_prefix' => 'credit.prefix', 'invoice_currencies' => 'invoice.currencies', 'bank_name' => 'bank.name', 'bank_account_name' => 'bank.account_name', 'bank_account_number' => 'bank.account_number', 'bank_ifsc' => 'bank.ifsc', 'bank_branch' => 'bank.branch', 'bank_upi_id' => 'bank.upi_id', 'lead_sources' => 'leads.sources', 'expense_categories' => 'expenses.categories', 'expense_methods' => 'expenses.methods', 'payment_methods' => 'payments.methods', 'invoice_units' => 'invoice.units', 'invoice_due_days' => 'invoice.due_days', 'invoice_theme' => 'invoice.theme', 'invoice_default_notes' => 'invoice.default_notes', 'invoice_footer' => 'invoice.footer', 'invoice_declaration' => 'invoice.declaration', 'payments_default_method' => 'payments.default_method', 'late_fee_type' => 'billing.late_fee_type', 'late_fee_value' => 'billing.late_fee_value', 'late_fee_grace_days' => 'billing.late_fee_grace_days', 'cheque_alert_days' => 'cheques.alert_days', 'collections_dunning_days' => 'collections.dunning_days', 'loyalty_earn_percent' => 'loyalty.earn_percent', 'payroll_overtime_rate' => 'payroll.overtime_rate', 'payroll_tds_percent' => 'payroll.tds_percent'] as $input => $key) {
            if (array_key_exists($input, $data)) {
                $s->set($key, $data[$input] ?? '', explode('.', $key)[0]);
            }
        }
        if ($r->hasFile('invoice_logo')) {
            $s->set('invoice.logo_path', $r->file('invoice_logo')->store('branding', 'public'), 'invoice');
        }
        if ($r->hasFile('invoice_logo_dark')) {
            $s->set('invoice.logo_dark_path', $r->file('invoice_logo_dark')->store('branding', 'public'), 'invoice');
        }
        if ($r->hasFile('signature')) {
            $old = $s->get('invoice.signature_path');
            $s->set('invoice.signature_path', $r->file('signature')->store('branding', 'public'), 'invoice');
            if ($old) {
                Storage::disk('public')->delete($old);
            }
        }

        return back()->with('ok', 'Billing settings saved.');
    }

    public function branding(SettingStore $s)
    {
        return view('settings.branding', ['s' => $s]);
    }

    public function brandingStore(Request $r, SettingStore $s)
    {
        $data = $r->validate([
            'app_name' => 'required|string|max:100',
            'brand_company_name' => 'required|string|max:255',
            'brand_tagline' => 'nullable|string|max:255',
            'brand_primary_color' => 'nullable|string|max:20',
            'brand_accent_color' => 'nullable|string|max:20',
            'brand_link_color' => 'nullable|string|max:20',
            'brand_background_color' => 'nullable|string|max:20',
            'brand_surface_color' => 'nullable|string|max:20',
            'brand_sidebar_bg' => 'nullable|string|max:20',
            'brand_sidebar_text' => 'nullable|string|max:20',
            'brand_header_bg' => 'nullable|string|max:20',
            'brand_text_color' => 'nullable|string|max:20',
            'brand_text_secondary' => 'nullable|string|max:20',
            'brand_border_color' => 'nullable|string|max:20',
            'brand_success_color' => 'nullable|string|max:20',
            'brand_warning_color' => 'nullable|string|max:20',
            'brand_danger_color' => 'nullable|string|max:20',
            'brand_info_color' => 'nullable|string|max:20',
            'brand_login_heading' => 'nullable|string|max:255',
            'brand_login_subtext' => 'nullable|string|max:255',
            'company_address' => 'nullable|string',
            'company_gstin' => 'nullable|string|max:20',
            'company_state' => 'nullable|string|max:100',
            'company_website' => 'nullable|string|max:255',
            'company_email' => 'nullable|email',
            'company_phone' => 'nullable|string|max:50',
            'mail_from_address' => 'nullable|email',
            'mail_from_name' => 'nullable|string|max:100',
            'logo' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:2048',
            'logo_dark' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:2048',            'favicon' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp,ico|max:1024',
        ]);
        $map = [
            'app_name' => 'app.name', 'brand_company_name' => 'brand.company_name',
            'brand_tagline' => 'brand.tagline', 'brand_primary_color' => 'brand.primary_color',
            'brand_accent_color' => 'brand.accent_color', 'brand_link_color' => 'brand.link_color',
            'brand_background_color' => 'brand.background_color', 'brand_surface_color' => 'brand.surface_color',
            'brand_sidebar_bg' => 'brand.sidebar_bg', 'brand_sidebar_text' => 'brand.sidebar_text',
            'brand_header_bg' => 'brand.header_bg', 'brand_text_color' => 'brand.text_color',
            'brand_text_secondary' => 'brand.text_secondary', 'brand_border_color' => 'brand.border_color',
            'brand_success_color' => 'brand.success_color', 'brand_warning_color' => 'brand.warning_color',
            'brand_danger_color' => 'brand.danger_color', 'brand_info_color' => 'brand.info_color',
            'brand_login_heading' => 'brand.login_heading',
            'brand_login_subtext' => 'brand.login_subtext', 'company_address' => 'company.address',
            'company_gstin' => 'company.gstin', 'company_state' => 'company.state', 'company_website' => 'company.website',
            'company_email' => 'company.email',
            'company_phone' => 'company.phone',
            'mail_from_address' => 'mail.from_address',
            'mail_from_name' => 'mail.from_name',
        ];
        foreach ($map as $input => $key) {
            if (array_key_exists($input, $data)) {
                $s->set($key, $data[$input], explode('.', $key)[0]);
            }
        }
        if ($r->hasFile('logo')) {
            $s->set('brand.logo_path', $r->file('logo')->store('branding', 'public'), 'brand');
        }
        if ($r->hasFile('logo_dark')) {
            $s->set('brand.logo_dark_path', $r->file('logo_dark')->store('branding', 'public'), 'brand');
        }
        if ($r->hasFile('favicon')) {
            $s->set('brand.favicon_path', $r->file('favicon')->store('branding', 'public'), 'brand');
        }

        return back()->with('ok', 'Branding saved.');
    }

    public function integrations(SettingStore $s)
    {
        return view('settings.integrations', ['s' => $s]);
    }

    public function integrationsStore(Request $r)
    {
        $data = $r->validate([
            'RAZORPAY_KEY_ID' => 'nullable|string|max:100',
            'RAZORPAY_KEY_SECRET' => 'nullable|string|max:255',
            'RAZORPAY_WEBHOOK_SECRET' => 'nullable|string|max:255',
            'MAIL_HOST' => 'required|string|max:255', 'MAIL_PORT' => 'required|numeric',
            'MAIL_USERNAME' => 'nullable|string|max:255', 'MAIL_PASSWORD' => 'nullable|string|max:255',
            'MAIL_ENCRYPTION' => 'nullable|string|max:20',
        ]);
        app(EnvWriter::class)->write($data);

        return back()->with('ok', 'Integrations saved. Config cache refreshes on next request.');
    }

    /** Send a test email to prove the SMTP settings work (surfaces errors inline). */
    public function integrationsTestMail(Request $r, SettingStore $s)
    {
        $to = $s->get('mail.from_address', auth()->user()->email);
        if (! $to) {
            return back()->withErrors(['mail' => 'Set a From address first, then test.']);
        }
        try {
            Mail::raw(
                'This is a test email from your CRM installation. If you received this, SMTP mail is configured correctly.',
                function ($m) use ($to) {
                    $m->to($to)->subject('CRM test email — SMTP is working');
                }
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['mail' => 'Mail failed: '.$e->getMessage()]);
        }

        return back()->with('ok', 'Test email sent to '.$to.'. Check the inbox (and spam).');
    }

    /** WhatsApp Cloud API credentials (.env) + template/toggles (settings). */
    public function whatsappStore(Request $r, SettingStore $s)
    {
        $data = $r->validate([
            'WHATSAPP_ENABLED' => 'nullable|boolean', 'WHATSAPP_AUTO' => 'nullable|boolean',
            'WHATSAPP_PHONE_NUMBER_ID' => 'nullable|string|max:60',
            'WHATSAPP_TOKEN' => 'nullable|string|max:500',
            'WHATSAPP_TEMPLATE' => 'nullable|string|max:100', 'WHATSAPP_LANG' => 'nullable|string|max:10',
            'WHATSAPP_COUNTRY_CODE' => 'nullable|string|max:5',
        ]);
        $env = [];
        if (! empty($data['WHATSAPP_PHONE_NUMBER_ID'])) {
            $env['WHATSAPP_PHONE_NUMBER_ID'] = $data['WHATSAPP_PHONE_NUMBER_ID'];
        }
        // Masked field submits •••••••• — keep the saved token in that case.
        if (! empty($data['WHATSAPP_TOKEN']) && $data['WHATSAPP_TOKEN'] !== '••••••••') {
            $env['WHATSAPP_TOKEN'] = $data['WHATSAPP_TOKEN'];
        }
        if ($env) {
            app(EnvWriter::class)->write($env);
        }
        $s->set('whatsapp.enabled', isset($data['WHATSAPP_ENABLED']) ? '1' : '0', 'whatsapp');
        $s->set('whatsapp.auto', isset($data['WHATSAPP_AUTO']) ? '1' : '0', 'whatsapp');
        $s->set('whatsapp.template', $data['WHATSAPP_TEMPLATE'] ?? 'payment_reminder', 'whatsapp');
        $s->set('whatsapp.lang', $data['WHATSAPP_LANG'] ?? 'en', 'whatsapp');
        $s->set('whatsapp.country_code', preg_replace('/\D+/', '', $data['WHATSAPP_COUNTRY_CODE'] ?? '91') ?: '91', 'whatsapp');

        return back()->with('ok', 'WhatsApp saved. Config cache refreshes on next request.');
    }

    /** Fire the configured template at any mobile to prove the credentials. */
    public function whatsappTest(Request $r, SettingStore $s)
    {
        $data = $r->validate(['to' => 'required|string|max:20']);
        $to = \Modules\Core\WhatsApp\WhatsAppSender::waId($data['to'], $s->get('whatsapp.country_code', '91'));
        if (! $to) {
            return back()->withErrors(['to' => 'That does not look like a valid mobile number.']);
        }
        if (! \Modules\Core\WhatsApp\WhatsAppSender::configured($s)) {
            return back()->withErrors(['to' => 'Save credentials first — WhatsApp is not configured.']);
        }
        try {
            $res = \Illuminate\Support\Facades\Http::timeout(25)->withToken(config('whatsapp.token'))->post(
                'https://graph.facebook.com/v21.0/'.config('whatsapp.phone_number_id').'/messages',
                [
                    'messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'template',
                    'template' => [
                        'name' => $s->get('whatsapp.template', 'payment_reminder'),
                        'language' => ['code' => $s->get('whatsapp.lang', 'en')],
                        'components' => [[
                            'type' => 'body',
                            'parameters' => array_map(fn ($t) => ['type' => 'text', 'text' => (string) $t], ['CRM test', 'oka', 'ok', 'ok']),
                        ]],
                    ],
                ]
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['to' => 'WhatsApp failed: '.$e->getMessage()]);
        }
        if (! $res->successful()) {
            return back()->withErrors(['to' => 'WhatsApp rejected the message: '.($res->json('error.message') ?? 'HTTP '.$res->status())]);
        }

        return back()->with('ok', 'Test template sent to +'.$to.'.');
    }

    public function tax(SettingStore $s)
    {
        return view('settings.tax', ['s' => $s]);
    }

    public function emails(SettingStore $s)
    {
        $defaults = email_template_defaults();
        $samples = [
            'invoice' => ['company_name' => 'Your Company', 'client_name' => 'Priya Nair', 'invoice_number' => 'INV-00001', 'items' => "Website design — 1 × INR 50,000.00 = INR 50,000.00\nHosting setup — 1 × INR 5,000.00 = INR 5,000.00", 'subtotal' => 'INR 55,000.00', 'gst' => 'INR 9,900.00', 'total' => 'INR 64,900.00', 'paid' => 'INR 0.00', 'balance' => 'INR 64,900.00', 'due_date' => '23 Sep 2026', 'link' => url('/invoices/view/sample-token')],
            'receipt' => ['company_name' => 'Your Company', 'client_name' => 'Priya Nair', 'receipt_number' => 'PAY-00001', 'amount' => 'INR 30,000.00', 'method' => 'UPI', 'date' => '16 Sep 2026', 'invoice_number' => 'INV-00001', 'link' => url('/payments/receipt/sample-token')],
            'proposal' => ['company_name' => 'Your Company', 'client_name' => 'Kiran Rao', 'proposal_number' => 'PROP-00001', 'total' => 'INR 64,900.00', 'valid_until' => '01 Oct 2026', 'link' => url('/proposals/sign/sample-token')],
            'reset' => ['app_name' => config('app.name')],
            'reminder' => ['app_name' => config('app.name'), 'date' => date('j M Y'), 'lines' => "• 2 lead follow-ups due\n• 1 task overdue\n• INR 38,760.00 overdue on INV-00001"],
            'overdue' => ['company_name' => 'Your Company', 'client_name' => 'Priya Nair', 'invoice_number' => 'INV-00005', 'total' => 'INR 38,760.00', 'balance' => 'INR 28,760.00', 'due_date' => '27 Aug 2026', 'link' => url('/invoices/view/sample-token'), 'notice' => 'Second notice: ', 'level' => 2],
            'followup' => ['company_name' => 'Your Company', 'client_name' => 'Kiran Rao', 'proposal_number' => 'PROP-00001', 'total' => 'INR 64,900.00', 'valid_until' => '01 Oct 2026', 'link' => url('/proposals/sign/sample-token')],
            'statement' => ['company_name' => 'Your Company', 'client_name' => 'Priya Nair', 'billed' => 'INR 60,180.00', 'received' => 'INR 11,500.00', 'balance' => 'INR 48,680.00', 'rows' => "2026-08-27 · Invoice INV-00003 · +INR 60,180.00 · bal INR 60,180.00\n2026-09-07 · Payment PAY-00001 · −INR 10,000.00 · bal INR 50,180.00", 'paylinks' => 'Pay INV-00003: '.url('/invoices/view/sample-token')],
        ];

        return view('settings.emails', ['s' => $s, 'defaults' => $defaults, 'samples' => $samples]);
    }

    public function emailsStore(Request $r, SettingStore $s)
    {
        $rules = [];
        foreach (array_keys(email_template_defaults()) as $key) {
            $field = str_replace('.', '_', $key);
            $rules[$field] = str_ends_with($key, '_subject') ? 'required|string|max:255' : 'required|string|max:10000';
        }
        $data = $r->validate($rules);
        foreach (email_template_defaults() as $key => $def) {
            $field = str_replace('.', '_', $key);
            $s->set($key, $data[$field], 'email');
        }

        return back()->with('ok', 'Email templates saved.');
    }

    public function taxStore(Request $r, SettingStore $s)
    {
        $data = $r->validate(['tax_default_gst_rate' => 'required|numeric|in:0,5,12,18,28', 'tax_enable_gst' => 'nullable|boolean', 'tax_tds_enabled' => 'nullable|boolean', 'tax_tds_sections' => 'nullable|string|max:1000']);
        $s->set('tax.default_gst_rate', $data['tax_default_gst_rate'], 'tax');
        $s->set('tax.enable_gst', isset($data['tax_enable_gst']) ? '1' : '0', 'tax');
        $s->set('tax.tds_enabled', isset($data['tax_tds_enabled']) ? '1' : '0', 'tax');
        $s->set('tax.tds_sections', $data['tax_tds_sections'] ?? '', 'tax');

        return back()->with('ok', 'Tax settings saved.');
    }

    public function updates(UpdateManager $updater)
    {
        $backups = collect(glob(storage_path('app/backups/crm-backup-*.zip') ?: []))
            ->map(fn ($p) => ['file' => basename($p), 'size' => filesize($p), 'at' => filemtime($p)])
            ->sortByDesc('at')->values();

        return view('settings.updates', ['current' => VersionTracker::current(), 'packages' => $updater->availablePackages(), 'backups' => $backups, 'preflight' => $updater->preflight()]);
    }

    public function updatesApply(Request $r, UpdateManager $updater)
    {
        if ($r->hasFile('package_file')) {
            $r->validate(['package_file' => 'required|file|mimes:zip|max:102400']);
            $dir = config('version.package_dir');
            @mkdir($dir, 0755, true);
            $name = basename($r->file('package_file')->getClientOriginalName());
            $path = $r->file('package_file')->storeAs('', $name, ['disk' => 'local']);
            // Move into updates dir
            $src = storage_path('app/private/'.$path);
            $dst = $dir.'/'.$name;
            if ($src !== $dst) {
                @rename($src, $dst);
            }
            // Refuse before touching anything when the server or package
            // cannot succeed (limits, permissions, downgrade).
            $blockers = array_values(array_filter($updater->preflight($dst), fn ($c) => $c['fatal'] && ! $c['ok']));
            if (count($blockers) > 0) {
                return back()->with('error', 'Update blocked: '.implode(' · ', array_map(fn ($c) => $c['label'].' — '.$c['detail'], $blockers)));
            }
            $result = $updater->apply($dst);
        } else {
            $r->validate(['package' => 'required|string']);
            $result = $updater->apply($r->package);
        }

        if (! $result['ok']) {
            $msg = $result['error'] ?? 'Update failed';
            if (! empty($result['hint'])) {
                $msg .= ' '.$result['hint'];
            }
            if (! empty($result['failed'])) {
                $msg .= ' Unwritten files: '.implode(', ', array_slice($result['failed'], 0, 5)).(count($result['failed']) > 5 ? ' (+'.(count($result['failed']) - 5).' more — see update log)' : '');
            }

            return back()->with('error', $msg);
        }

        return back()->with('ok', $result['detail'] ?? ('Updated to '.($result['version'] ?? '')));
    }

    /** On-demand full backup (database + uploaded files) as a downloadable zip. */
    public function updatesBackup(UpdateManager $updater)
    {
        $result = $updater->backupNow();
        if (! ($result['ok'] ?? false)) {
            return back()->withErrors(['backup' => $result['error'] ?? 'Backup failed.']);
        }

        return response()->download($result['path'], $result['file'])->deleteFileAfterSend(true);
    }

    /** Nightly backup emails: toggle + owner address. */
    public function backupMail(Request $r, SettingStore $s)
    {
        $data = $r->validate(['backups_email_enabled' => 'nullable|boolean', 'backups_email_to' => 'nullable|email|max:255']);
        $s->set('backups.email_enabled', isset($data['backups_email_enabled']) ? '1' : '0', 'backups');
        $s->set('backups.email_to', $data['backups_email_to'] ?? '', 'backups');

        return back()->with('ok', 'Backup emails saved.');
    }

    public function downloadBackup(string $file)    {
        $file = basename($file);
        if (! str_starts_with($file, 'crm-backup-') || ! str_ends_with($file, '.zip')) {
            abort(404);
        }
        $path = storage_path('app/backups/'.$file);
        abort_unless(is_file($path), 404);

        return response()->download($path, $file);
    }

    public function destroyBackup(string $file)
    {
        $file = basename($file);
        if (! str_starts_with($file, 'crm-backup-') || ! str_ends_with($file, '.zip')) {
            abort(404);
        }
        @unlink(storage_path('app/backups/'.$file));

        return back()->with('ok', 'Backup deleted.');
    }

    public function license(LicenseManager $lm)
    {
        return view('settings.license', [
            'license' => $lm->status(),
            'driver' => $lm->driver(),
            'server' => $lm->serverHost(),
            'product' => (string) config('licensing.product_code'),
            'installation' => $lm->installationId(),
            'domain' => $lm->domain(),
            'hasKey' => $lm->licenseKey() !== '',
            'keyFromEnv' => trim((string) config('licensing.license_key', '')) !== '',
        ]);
    }

    /** Activate a reseller-issued license key against the license server. */
    public function licenseActivate(Request $r, LicenseManager $lm)
    {
        $data = $r->validate(['license_key' => 'required|string|max:200']);
        [$ok, $msg] = $lm->activate($data['license_key']);
        if (! $ok) {
            return back()->withErrors(['license_key' => $msg]);
        }

        return back()->with('ok', $msg);
    }

    /** Release this installation's activation slot (frees it for another server). */
    public function licenseDeactivate(Request $r, LicenseManager $lm)
    {
        $data = $r->validate(['reason' => 'nullable|string|max:200']);
        [$ok, $msg] = $lm->deactivate($data['reason'] ?? '');
        if (! $ok) {
            return back()->withErrors(['license_key' => $msg]);
        }

        return back()->with('ok', $msg);
    }

    /** Force a live re-validation right now (ignores the re-check schedule). */
    public function licenseRefresh(LicenseManager $lm)
    {
        [$ok, $msg] = $lm->refresh();
        if (! $ok) {
            return back()->withErrors(['license_key' => $msg]);
        }

        return back()->with('ok', $msg);
    }

    /** Toggleable workspace modules (Settings/Installer stay on by design). */
    public static function toggleableModules(): array
    {
        return ['Clients', 'Leads', 'Proposals', 'Products', 'Projects', 'Tasks', 'Invoices', 'Payments', 'Expenses', 'Reports', 'Documents', 'Compliance', 'Cheques', 'Amc', 'Challans', 'Purchases', 'Petty'];
    }

    public function modules(SettingStore $s)
    {
        $over = json_decode((string) $s->get('modules.overrides', ''), true) ?: [];

        return view('settings.modules', ['modules' => self::toggleableModules(), 'over' => $over]);
    }

    public function modulesStore(Request $r, SettingStore $s)
    {
        $data = $r->validate(['off' => 'sometimes|array', 'off.*' => 'string']);
        $over = [];
        foreach (self::toggleableModules() as $m) {
            $over[$m] = ! in_array($m, $data['off'] ?? [], true);
        }
        $s->set('modules.overrides', json_encode($over));

        return back()->with('ok', 'Modules updated.');
    }
}
