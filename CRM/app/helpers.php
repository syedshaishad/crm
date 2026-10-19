<?php

use App\Models\Activity;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Settings\SettingStore;
use Modules\Updater\VersionTracker;

if (! function_exists('setting')) {
    function setting(string $key, $default = null)
    {
        try {
            return app(SettingStore::class)->get($key, $default);
        } catch (Throwable $e) {
            return $default;
        }
    }
}

if (! function_exists('app_version')) {
    function app_version(): string
    {
        try {
            return VersionTracker::current();
        } catch (Throwable $e) {
            return config('version.version', '1.0.0');
        }
    }
}

if (! function_exists('module_enabled')) {
    // Config toggle with UI overrides (settings.modules_overrides JSON).
    // Settings/Installer can never be switched off (admin lockout guard).
    function module_enabled(string $module): bool
    {
        if (in_array($module, ['Settings', 'Installer'], true)) {
            return true;
        }
        try {
            $over = json_decode((string) setting('modules.overrides', ''), true) ?: [];
            if (array_key_exists($module, $over)) {
                return (bool) $over[$module];
            }
        } catch (Throwable $e) {
        }

        return (bool) config('modules.enabled.'.$module, true);
    }
}

if (! function_exists('module_visible')) {
    // Effective visibility: config toggle AND license allowance.
    function module_visible(string $module): bool
    {
        if (! module_enabled($module)) {
            return false;
        }
        try {
            return app(\Modules\Licensing\LicenseManager::class)->moduleAllowed($module);
        } catch (Throwable $e) {
            return true;
        }
    }
}

if (! function_exists('perm_modules')) {
    // Coarse permission groups for the per-user access matrix.
    function perm_modules(): array
    {
        return [
            'dashboard' => 'Dashboard',
            'sales' => 'Sales (leads, clients, proposals, products)',
            'work' => 'Work (projects, tasks)',
            'billing' => 'Billing (invoices, payments, expenses, reminders)',
            'reports' => 'Reports (overview, GSTR-1, TDS)',
            'documents' => 'Documents',
            'compliance' => 'Compliance (expiry documents)',
            'import' => 'Import / Export',
        ];
    }
}

if (! function_exists('perm_module_for_route')) {
    // Map a route name to its permission group (null = no gate).
    function perm_module_for_route(?string $route): ?string
    {
        if (! $route) {
            return null;
        }
        foreach ([
            'dashboard' => 'dashboard',
            'leads.' => 'sales', 'clients.' => 'sales', 'proposals.' => 'sales', 'products.' => 'sales', 'challans.' => 'sales', 'routes.' => 'sales', 'amc.' => 'sales',
            'projects.' => 'work', 'tasks.' => 'work', 'attendance.' => 'work', 'salary.' => 'work',
            'quality.' => 'work', 'meetings.' => 'work', 'knowledge.' => 'work', 'automations.' => 'work',
            'leaves.' => 'work', 'shifts.' => 'work', 'appraisals.' => 'work', 'holidays.' => 'work',
            'overtime.' => 'work', 'reimbursements.' => 'work', 'exits.' => 'work',
            'team.' => 'work', 'skills.' => 'work', 'field.' => 'work', 'voice.' => 'work',
            'hr.' => 'work',
            'invoices.' => 'billing', 'payments.' => 'billing', 'credits.' => 'billing',
            'expenses.' => 'billing', 'reminders.' => 'billing', 'purchase-orders.' => 'billing', 'cheques.' => 'billing', 'petty.' => 'billing', 'dayclose.' => 'billing', 'assets.' => 'billing',
            'payroll.' => 'billing', 'structures.' => 'billing', 'loans.' => 'billing', 'bonuses.' => 'billing',
            'reports.' => 'reports',
            'documents.' => 'documents',
            'expiry.' => 'compliance',
            'import.' => 'import', 'export.' => 'import',
        ] as $prefix => $mod) {
            if ($route === $prefix || str_starts_with($route, $prefix)) {
                return $mod;
            }
        }

        return null;
    }
}

if (! function_exists('user_can')) {
    // Granular gate: admins pass everything; everyone else inherits the
    // role default (full workspace) unless an explicit level was set on
    // their user record (full|view|none). Document ownership (assigned_to
    // / created_by on invoices & proposals) still applies on top.
    function user_can(string $module, string $action = 'view', $user = null): bool
    {
        $user ??= function_exists('auth') ? auth()->user() : null;
        if (! $user) {
            return false;
        }
        if ($user->isAdmin()) {
            return true;
        }
        $perms = $user->permissions ?? [];
        $level = is_array($perms) ? ($perms[$module] ?? 'full') : 'full';
        if ($level === 'none') {
            return false;
        }
        if ($level === 'view') {
            return $action === 'view';
        }

        return true;
    }
}


if (! function_exists('storage_link_ok')) {
    /** True when the web server can serve files out of storage/app/public. */
    function storage_link_ok(): bool
    {
        return is_dir(public_path('storage'));
    }
}

if (! function_exists('money')) {
    function money($amount, ?string $currency = null): string
    {
        $currency = $currency ?: setting('app.currency', 'INR');

        return $currency.' '.number_format((float) $amount, 2);
    }
}

if (! function_exists('upi_intent')) {
    // UPI collect intent understood by GPay/PhonePe/Paytm/BHIM.
    function upi_intent(string $upiId, string $payee, float $amount, string $note = ''): string
    {
        $q = http_build_query(array_filter([
            'pa' => trim($upiId),
            'pn' => trim($payee) ?: 'Merchant',
            'am' => number_format(max(0, $amount), 2, '.', ''),
            'cu' => 'INR',
            'tn' => substr(trim($note), 0, 80),
        ]));

        return 'upi://pay?'.$q;
    }
}

if (! function_exists('upi_qr_svg')) {
    // Offline QR (no external API) — returns raw SVG markup or '' on failure.
    function upi_qr_svg(string $text, int $size = 220): string
    {
        try {
            $code = \BaconQrCode\Encoder\Encoder::encode($text, \BaconQrCode\Common\ErrorCorrectionLevel::M());
            $renderer = new \BaconQrCode\Renderer\ImageRenderer(
                new \BaconQrCode\Renderer\RendererStyle\RendererStyle($size, 2),
                new \BaconQrCode\Renderer\Image\SvgImageBackEnd
            );

            return $renderer->render($code);
        } catch (Throwable $e) {
            return '';
        }
    }
}

if (! function_exists('client_outstanding')) {
    // Unpaid across non-cancelled invoices, net of unlinked advance payments.
    function client_outstanding(int $clientId): float
    {
        try {
            $row = DB::table('invoices')->where('client_id', $clientId)->whereNotIn('status', ['cancelled'])
                ->selectRaw('COALESCE(SUM(total),0) t, COALESCE(SUM(paid_amount),0) p')->first();

            return max(0, (float) ($row->t ?? 0) - (float) ($row->p ?? 0) - client_advance($clientId));
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (! function_exists('client_advance')) {
    // Unlinked (advance) payments sitting with a client, not yet adjusted.
    function client_advance(int $clientId): float
    {
        try {
            return max(0, (float) DB::table('payments')->where('client_id', $clientId)->whereNull('invoice_id')->whereNull('deleted_at')->sum('amount'));
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (! function_exists('credit_warn')) {
    // Over-limit message or null (limit 0 = unlimited).
    function credit_warn($client): ?string
    {
        $limit = (float) ($client->credit_limit ?? 0);
        if ($limit <= 0) {
            return null;
        }
        $out = client_outstanding((int) $client->id);
        if ($out <= $limit) {
            return null;
        }
        $name = $client->company ?: $client->name;

        return $name.' is over the credit limit by '.money($out - $limit).' (owes '.money($out).' of '.money($limit).').';
    }
}

if (! function_exists('late_fee_amount')) {
    // Late fee for an overdue invoice per Settings → Billing (0 = off / not eligible).
    function late_fee_amount($invoice): float
    {
        if (setting('billing.late_fee_enabled', '0') !== '1') {
            return 0;
        }
        if (! in_array($invoice->status, ['sent', 'partial', 'overdue'], true)) {
            return 0;
        }
        if ($invoice->late_fee_on) {
            return 0;
        }
        $balance = $invoice->balance();
        if ($balance <= 0 || ! $invoice->due_date) {
            return 0;
        }
        $grace = (int) setting('billing.late_fee_grace_days', 0);
        if (now()->toDateString() <= \Carbon\Carbon::parse($invoice->due_date)->addDays($grace)->toDateString()) {
            return 0;
        }
        if (setting('billing.late_fee_type', 'flat') === 'percent') {
            return round($balance * (float) setting('billing.late_fee_value', 0) / 100, 2);
        }

        return round((float) setting('billing.late_fee_value', 0), 2);
    }
}

if (! function_exists('upload_reject_reason')) {
    // Executables and inline-scriptable files must never land on the public
    // disk (stored names keep their extension and /storage/* is URL-open).
    // Returns a human reason or null when the file is fine.
    function upload_reject_reason($file): ?string
    {
        $bad = ['php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps', 'exe', 'com', 'bat', 'cmd', 'sh', 'bash', 'zsh', 'pl', 'py', 'pyc', 'rb', 'jar', 'msi', 'dll', 'so', 'dylib', 'scr', 'msc', 'reg', 'vbs', 'vbe', 'jse', 'wsf', 'wsh', 'ps1', 'hta', 'html', 'htm', 'xhtml', 'svg', 'xml', 'swf'];
        $parts = explode('.', strtolower((string) $file->getClientOriginalName()));
        array_shift($parts);
        foreach ($parts as $ext) {
            if (in_array($ext, $bad, true)) {
                return 'Files ending in .'.$ext.' are not allowed for security.';
            }
        }

        return null;
    }
}

if (! function_exists('invoice_theme')) {
    // Print theme for invoices (show + public + PDF share one partial).
    function invoice_theme(): string
    {
        return in_array(setting('invoice.theme', 'classic'), ['classic', 'compact'], true)
            ? setting('invoice.theme', 'classic') : 'classic';
    }
}

if (! function_exists('proposal_theme')) {
    // Print theme for proposals (staff view + print share one partial).
    function proposal_theme(): string
    {
        return in_array(setting('proposal.theme', 'classic'), ['classic', 'compact'], true)
            ? setting('proposal.theme', 'classic') : 'classic';
    }
}

if (! function_exists('gstin_check')) {
    // Offline GSTIN validation: structure + state code + check digit.
    // Returns an error string or null when valid (empty = unregistered, valid).
    function gstin_check(?string $gstin): ?string
    {
        $g = strtoupper(trim((string) $gstin));
        if ($g === '') {
            return null;
        }
        if (! preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][A-Z0-9]Z[A-Z0-9]$/', $g)) {
            return 'GSTIN must be 15 characters: state code + PAN + entity + Z + check digit.';
        }
        $state = (int) substr($g, 0, 2);
        if ($state < 1 || $state > 38) {
            return 'GSTIN state code '.$state.' is not a valid Indian state code.';
        }
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $sum = 0;
        for ($i = 0; $i < 14; $i++) {
            $d = strpos($chars, $g[$i]) * ($i % 2 === 0 ? 1 : 2);
            $sum += intdiv($d, 36) + $d % 36;
        }
        $want = $chars[(36 - $sum % 36) % 36];
        if ($g[14] !== $want) {
            return 'GSTIN check digit is wrong — please retype it.';
        }

        return null;
    }
}

if (! function_exists('perPage')) {
    function perPage(): int
    {
        try {
            // Rows-per-page picker (?per_page=) wins and sticks in the session;
            // otherwise the admin setting applies. Web lists only.
            $q = (int) (request('per_page') ?? 0);
            if ($q > 0) {
                $v = max(5, min(100, $q));
                session(['app.per_page' => $v]);

                return $v;
            }
            $s = (int) session('app.per_page', 0);
            if ($s > 0) return max(5, min(100, $s));
            $v = (int) setting('app.per_page', 20);

            return max(5, min(100, $v ?: 20));
        } catch (Throwable $e) {
            return 20;
        }
    }
}

if (! function_exists('currency_name')) {
    function currency_name(?string $code = null): string
    {
        $code = strtoupper($code ?: setting('app.currency', 'INR'));

        return ['INR' => 'Rupees', 'USD' => 'US Dollars', 'EUR' => 'Euros', 'GBP' => 'Pounds Sterling', 'AED' => 'UAE Dirhams', 'SAR' => 'Saudi Riyals', 'CAD' => 'Canadian Dollars', 'AUD' => 'Australian Dollars'][$code] ?? $code;
    }
}

if (! function_exists('d')) {
    // Display any date/datetime (Carbon, string, null) using the configured display format.
    function d($date, ?string $fmt = null): string
    {
        if (! $date) {
            return '';
        }
        $fmt = $fmt ?: setting('app.date_format', 'Y-m-d');
        try {
            return $date instanceof DateTimeInterface ? $date->format($fmt) : Carbon::parse($date)->format($fmt);
        } catch (Throwable $e) {
            return is_scalar($date) ? substr((string) $date, 0, 10) : '';
        }
    }
}

if (! function_exists('indian_states')) {
    function indian_states(): array
    {
        return ['Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh', 'Delhi', 'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jammu and Kashmir', 'Jharkhand', 'Karnataka', 'Kerala', 'Ladakh', 'Lakshadweep', 'Madhya Pradesh', 'Maharashtra', 'Manipur', 'Meghalaya', 'Mizoram', 'Nagaland', 'Odisha', 'Puducherry', 'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana', 'Tripura', 'Uttar Pradesh', 'Uttarakhand', 'West Bengal', 'Andaman and Nicobar Islands', 'Chandigarh', 'Dadra and Nagar Haveli and Daman and Diu', 'Other Territory', 'Foreign Country'];
    }
}

if (! function_exists('fy')) {
    // Indian financial year (April–March) for a date: "2026-27".
    function fy($date = null): string
    {
        try {
            $d = $date instanceof DateTimeInterface ? Carbon::instance($date) : Carbon::parse($date ?? now());
        } catch (Throwable $e) {
            $d = now();
        }
        $y = (int) $d->format('Y');
        $start = $d->month >= 4 ? $y : $y - 1;

        return $start.'-'.substr((string) ($start + 1), -2);
    }
}

if (! function_exists('tds_sections')) {
    // TDS sections with default rates, one per line as CODE:RATE.
    // Returns [code => rate]; falls back to the common business sections.
    function tds_sections(): array
    {
        $raw = setting('tax.tds_sections', '');
        $lines = preg_split('/[\r\n]+/', (string) $raw) ?: [];
        $out = [];
        foreach ($lines as $ln) {
            $ln = trim($ln);
            if ($ln === '' || ! str_contains($ln, ':')) {
                continue;
            }
            [$code, $rate] = array_map('trim', explode(':', $ln, 2));
            if ($code !== '' && is_numeric($rate)) {
                $out[strtoupper($code)] = (float) $rate;
            }
        }

        return $out ?: ['194C' => 2.0, '194J' => 10.0, '194I' => 2.0, '194H' => 2.0, '194A' => 10.0];
    }
}
if (! function_exists('amount_words')) {    // Indian-system number to words for invoice totals (uses configured currency).
    function amount_words($amount): string
    {
        $n = (int) round((float) $amount);
        if ($n === 0) {
            return 'Zero '.currency_name();
        }
        $w = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
            'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $t = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
        $two = fn ($x) => $x < 20 ? $w[$x] : trim($t[intdiv($x, 10)].' '.$w[$x % 10]);
        $three = fn ($x) => (intdiv($x, 100) > 0 ? $w[intdiv($x, 100)].' Hundred ' : '').$two($x % 100);
        $parts = [];
        $cr = intdiv($n, 10000000);
        $n %= 10000000;
        $lk = intdiv($n, 100000);
        $n %= 100000;
        $th = intdiv($n, 1000);
        $n %= 1000;
        if ($cr) {
            $parts[] = $three($cr).' Crore';
        }
        if ($lk) {
            $parts[] = $two($lk).' Lakh';
        }
        if ($th) {
            $parts[] = $two($th).' Thousand';
        }
        if ($n) {
            $parts[] = $three($n);
        }

        return trim(implode(' ', $parts)).' '.currency_name();
    }
}

if (! function_exists('fmt_size')) {
    // Human file size for the file manager (KB / MB).
    function fmt_size($bytes): string
    {
        $b = (int) $bytes;

        return $b >= 1048576 ? number_format($b / 1048576, 1).' MB' : number_format($b / 1024, 1).' KB';
    }
}

if (! function_exists('file_ext')) {
    function file_ext(?string $name): string
    {
        return strtolower(pathinfo($name ?? '', PATHINFO_EXTENSION));
    }
}

if (! function_exists('file_icon_class')) {
    // Colour class for the file-manager tile badge, by extension.
    function file_icon_class(string $ext): string
    {
        if ($ext === 'pdf') {
            return 'fi-pdf';
        }
        if (in_array($ext, ['doc', 'docx', 'txt', 'rtf', 'odt'])) {
            return 'fi-doc';
        }
        if (in_array($ext, ['xls', 'xlsx', 'csv', 'ods'])) {
            return 'fi-xls';
        }
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'])) {
            return 'fi-img';
        }
        if (in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz'])) {
            return 'fi-zip';
        }
        if (in_array($ext, ['mp4', 'mov', 'avi', 'mkv'])) {
            return 'fi-vid';
        }

        return 'fi-file';
    }
}

if (! function_exists('file_icon_label')) {
    function file_icon_label(string $ext): string
    {
        if ($ext === 'jpeg') {
            return 'JPG';
        }

        return strtoupper(substr($ext ?: 'FILE', 0, 4));
    }
}

if (! function_exists('setting_lines')) {
    // Configurable one-per-line lists (expense categories, payment methods, units).
    function setting_lines(string $key, array $default): array
    {
        $raw = setting($key, '');
        $lines = preg_split('/[\r\n,]+/', (string) $raw) ?: [];
        $lines = array_values(array_unique(array_filter(array_map('trim', $lines))));

        return $lines ?: $default;
    }
}

if (! function_exists('email_template_defaults')) {
    // Default subject/body for every editable email ({{tokens}} filled at send time).
    function email_template_defaults(): array
    {
        return [
            'email.invoice_subject' => 'Invoice {{invoice_number}} from {{company_name}}',
            'email.invoice_body' => "Dear {{client_name}},\n\nPlease find your invoice {{invoice_number}} below:\n\n{{items}}\n\nSubtotal: {{subtotal}}\nGST: {{gst}}\nTotal: {{total}}\nPaid: {{paid}}\nBalance due: {{balance}}\nDue: {{due_date}}\n\nView and download it here: {{link}}\n\nThank you for your business.",
            'email.receipt_subject' => 'Payment receipt {{receipt_number}} from {{company_name}}',
            'email.receipt_body' => "Dear {{client_name}},\n\nThank you — we received {{amount}} by {{method}} on {{date}}.\n\nReceipt: {{receipt_number}}\nAgainst invoice: {{invoice_number}}\n\nView and download your receipt here: {{link}}",
            'email.proposal_subject' => 'Proposal {{proposal_number}} from {{company_name}}',
            'email.proposal_body' => "Dear {{client_name}},\n\nPlease review our proposal {{proposal_number}} worth {{total}}.\n\nOpen it here, draw your signature to accept, or decline: {{link}}\n\nValid until {{valid_until}}. We look forward to working with you.",
            'email.reset_subject' => 'Reset your {{app_name}} password',
            'email.reset_body' => "Hello,\n\nYou asked to reset your {{app_name}} password. Use the button below — the link expires in 60 minutes.\n\nIf you did not ask for this, just ignore this email.",
            'email.reminder_subject' => '{{app_name}} daily digest — {{date}}',
            'email.reminder_body' => "Morning team,\n\nHere is what needs attention today ({{date}}):\n\n{{lines}}\n\n— {{app_name}}",
            'email.overdue_subject' => '{{notice}}{{invoice_number}} — {{balance}} due',
            'email.overdue_body' => "Dear {{client_name}},\n\n{{notice}}invoice {{invoice_number}} for {{total}} still shows a balance of {{balance}} (due {{due_date}}).\n\nYou can view it and pay online here: {{link}}\n\nThank you,\n{{company_name}}",
            'email.followup_subject' => 'Still interested? {{proposal_number}} expires {{valid_until}}',
            'email.followup_body' => "Dear {{client_name}},\n\nJust checking in on proposal {{proposal_number}} worth {{total}} — it stays open until {{valid_until}}.\n\nReview and sign here: {{link}}\n\nHappy to revise scope or pricing if something changed.\n\nThank you,\n{{company_name}}",
            'email.statement_subject' => 'Account statement from {{company_name}} — {{balance}} due',
            'email.statement_body' => "Dear {{client_name}},\n\nPlease find your account statement below.\n\nBilled: {{billed}}\nReceived: {{received}}\nBalance due: {{balance}}\n\n{{rows}}\n{{paylinks}}\nThank you,\n{{company_name}}",
        ];
    }
}

if (! function_exists('render_template')) {
    // Fill a {{token}} email template from settings (unknown tokens left as-is).
    function render_template(string $key, array $data = []): string
    {
        $defaults = email_template_defaults();
        $tpl = setting($key, $defaults[$key] ?? '');
        foreach ($data as $k => $v) {
            $tpl = str_replace('{{'.$k.'}}', (string) ($v ?? ''), $tpl);
        }

        return $tpl;
    }
}

if (! function_exists('email_placeholders')) {
    // Documented tokens per template, shown under each editor.
    function email_placeholders(string $group): array
    {
        return [
            'invoice' => ['company_name', 'client_name', 'invoice_number', 'items', 'subtotal', 'gst', 'total', 'paid', 'balance', 'due_date', 'link'],
            'receipt' => ['company_name', 'client_name', 'receipt_number', 'amount', 'method', 'date', 'invoice_number', 'link'],
            'proposal' => ['company_name', 'client_name', 'proposal_number', 'total', 'valid_until', 'link'],
            'reset' => ['app_name'],
            'reminder' => ['app_name', 'date', 'lines'],
            'overdue' => ['company_name', 'client_name', 'invoice_number', 'total', 'balance', 'due_date', 'link', 'notice', 'level'],
            'followup' => ['company_name', 'client_name', 'proposal_number', 'total', 'valid_until', 'link'],
            'statement' => ['company_name', 'client_name', 'billed', 'received', 'balance', 'rows', 'paylinks'],
        ][$group] ?? [];
    }
}
if (! function_exists('activity')) {
    // Who-did-what log. $subject is any main model; safe to call anywhere.
    function activity(string $action, $subject = null, ?string $description = null): void
    {
        try {
            if (app()->runningInConsole()) {
                return;
            } // seeders/migrations stay out of the log
            $u = auth()->user();
            $type = $subject ? class_basename($subject) : '—';
            $label = $description;
            if ($subject) {
                $label = $subject->company ?? $subject->title ?? $subject->number ?? $subject->name ?? ('#'.$subject->id);
                $bits = array_filter([$type, is_string($label) ? $label : null]);
                $label = implode(' ', $bits);
            }
            $verbs = ['created' => 'created', 'updated' => 'updated', 'status' => 'moved', 'trashed' => 'moved to Trash', 'deleted' => 'deleted', 'restored' => 'restored'];
            $verb = $verbs[$action] ?? $action;
            Activity::create([
                'user_id' => $u?->id, 'actor' => $u ? 'staff' : 'client', 'action' => $action,
                'subject_type' => $type, 'subject_id' => $subject->id ?? null,
                'label' => is_string($label) ? mb_substr($label, 0, 255) : null,
                'description' => $description ? (ucfirst($verb).': '.$label.' — '.$description) : ucfirst($verb).': '.$label,
            ]);
        } catch (Throwable $e) {
        }
    }
}

if (! function_exists('totp_secret')) {
    // 160-bit secret for authenticator apps (no library needed).
    function totp_secret(): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $s = '';
        for ($i = 0; $i < 32; $i++) {
            $s .= $alphabet[random_int(0, 31)];
        }

        return $s;
    }
}

if (! function_exists('totp_verify')) {
    // RFC 6238 TOTP check (SHA1, 30s steps, ±$window steps grace).
    function totp_verify(string $secret, string $code, int $window = 1): bool
    {
        $code = trim($code);
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split(strtoupper(str_replace(' ', '', $secret))) as $ch) {
            $v = strpos($alphabet, $ch);
            if ($v === false) {
                return false;
            }
            $bits .= str_pad(decbin($v), 5, '0', STR_PAD_LEFT);
        }
        $key = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) < 8) {
                break;
            }
            $key .= chr(bindec($byte));
        }
        if ($key === '') {
            return false;
        }
        $t = (int) floor(time() / 30);
        for ($i = -$window; $i <= $window; $i++) {
            $hash = hash_hmac('sha1', pack('N*', 0, $t + $i), $key, true);
            $off = ord($hash[19]) & 15;
            $otp = ((ord($hash[$off]) & 127) << 24) | (ord($hash[$off + 1]) << 16) | (ord($hash[$off + 2]) << 8) | ord($hash[$off + 3]);
            if (hash_equals(str_pad((string) ($otp % 1000000), 6, '0', STR_PAD_LEFT), $code)) {
                return true;
            }
        }

        return false;
    }
}

if (! function_exists('brand_contact')) {
    // Company contact line for invoice/receipt documents (email · phone · website).
    function brand_contact(array $brand): string
    {
        $bits = array_filter([$brand['email'] ?? '', $brand['phone'] ?? '', $brand['website'] ?? '']);

        return implode(' · ', $bits);
    }
}

if (! function_exists('bank_details')) {
    // Bank transfer details for invoices (empty array = hide the block).
    function bank_details(): array
    {
        $d = [
            'name' => setting('bank.name', ''),
            'account_name' => setting('bank.account_name', ''),
            'account_number' => setting('bank.account_number', ''),
            'ifsc' => setting('bank.ifsc', ''),
            'branch' => setting('bank.branch', ''),
            'upi_id' => setting('bank.upi_id', ''),
        ];

        return array_filter($d) ? $d : [];
    }
}

if (! function_exists('notify_roles')) {
    // Fan an in-app notification out to everyone holding one of these roles.
    // Silent no-op in console contexts (seeders/migrations stay quiet).
    function notify_roles(array $roles, $notification): void
    {
        try {
            if (app()->runningInConsole()) {
                return;
            }
            User::where('is_active', true)->whereIn('role', $roles)
                ->each(fn ($u) => $u->notify($notification));
        } catch (Throwable $e) {
        }
    }
}

if (! function_exists('csv_response')) {
    // Stream a collection/arrays of rows as a CSV download. $rows may be a
    // collection of objects or arrays; the first row's keys become the header.
    function csv_response(string $filename, $rows, ?array $headers = null)
    {
        $rows = collect($rows)->map(fn ($r) => is_object($r) ? (array) $r : $r);

        return response()->streamDownload(function () use ($rows, $headers) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel opens the file without garbling Indian text.
            fwrite($out, "\xEF\xBB\xBF");
            if ($headers) {
                fputcsv($out, $headers);
            } elseif ($rows->isNotEmpty()) {
                fputcsv($out, array_keys($rows->first()));
            }
            foreach ($rows as $row) {
                // Formula-injection guard: a cell starting with = + - @ could
                // execute as a formula in Excel/Sheets. Prefix with an apostrophe
                // (Excel hides it and treats the cell as text).
                fputcsv($out, array_map(function ($v) {
                    $v = is_scalar($v) || $v === null ? $v : '';
                    if (is_string($v) && $v !== '' && str_contains('=+-@', $v[0])) {
                        return "'".$v;
                    }

                    return $v;
                }, $row));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

if (! function_exists('csv_rows_from_file')) {
    // Read an uploaded CSV into an array of assoc rows keyed by (trimmed,
    // lowercased) header. Returns [] when the file has no usable header.
    function csv_rows_from_file(string $path): array
    {
        $fh = fopen($path, 'r');
        if ($fh === false) {
            return [];
        }
        // Skip a UTF-8 BOM if present.
        $bom = fread($fh, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($fh);
        }
        $header = fgetcsv($fh);
        if (! $header || count(array_filter($header)) === 0) {
            fclose($fh);

            return [];
        }
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/[^a-z0-9_]+/i', '_', (string) $h))), $header);
        $rows = [];
        while (($data = fgetcsv($fh)) !== false) {
            if (count(array_filter($data, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $rows[] = array_combine($header, array_pad($data, count($header), ''));
        }
        fclose($fh);

        return $rows;
    }
}
