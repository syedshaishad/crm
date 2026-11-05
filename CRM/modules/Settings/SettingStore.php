<?php

namespace Modules\Settings;

use Illuminate\Support\Facades\DB;

class SettingStore
{
    /** Per-request memo: once the DB proves unreachable, stop re-dialling it. */
    protected static bool $dead = false;

    /** All settings are loaded once, so views do not trigger hundreds of queries. */
    protected ?array $values = null;

    public function get(string $key, $default = null)
    {
        if (static::$dead) {
            return $default ?? $this->envFallback($key);
        }
        $this->load();
        if (! array_key_exists($key, $this->values ?? [])) {
            return $default ?? $this->envFallback($key);
        }

        return $this->values[$key];
    }

    public function set(string $key, $value, string $group = 'general'): void
    {
        $stored = is_scalar($value) || $value === null ? (string) $value : json_encode($value);
        \DB::table('settings')->updateOrInsert(
            ['key' => $key],
            ['value' => $stored, 'group' => $group, 'updated_at' => now(), 'created_at' => now()]
        );
        if ($this->values !== null) {
            $this->values[$key] = $stored;
        }
    }

    public function all(?string $group = null): array
    {
        if (static::$dead) {
            return [];
        }
        if ($group === null) {
            $this->load();

            return $this->values ?? [];
        }
        try {
            return DB::table('settings')->where('group', $group)->pluck('value', 'key')->all();
        } catch (\Throwable $e) {
            static::$dead = true;

            return [];
        }
    }

    protected function load(): void
    {
        if ($this->values !== null || static::$dead) {
            return;
        }
        try {
            $this->values = DB::table('settings')->pluck('value', 'key')->all();
        } catch (\Throwable $e) {
            static::$dead = true;
            $this->values = [];
        }
    }

    /** Drop the in-memory snapshot between requests/jobs in long-running workers. */
    public function flush(): void
    {
        $this->values = null;
        static::$dead = false;
    }

    protected function envFallback(string $key)
    {
        $map = [
            'app.name' => config('app.name'),
            'brand.company_name' => config('branding.company_name'),
            'brand.primary_color' => config('branding.primary_color'),
            'brand.accent_color' => config('branding.accent_color'),
            'brand.link_color' => config('branding.link_color'),
            'brand.background_color' => config('branding.background_color'),
            'brand.surface_color' => config('branding.surface_color'),
            'brand.sidebar_bg' => config('branding.sidebar_bg'),
            'brand.sidebar_text' => config('branding.sidebar_text'),
            'brand.header_bg' => config('branding.header_bg'),
            'brand.text_color' => config('branding.text_color'),
            'brand.text_secondary' => config('branding.text_secondary'),
            'brand.border_color' => config('branding.border_color'),
            'brand.success_color' => config('branding.success_color'),
            'brand.warning_color' => config('branding.warning_color'),
            'brand.danger_color' => config('branding.danger_color'),
            'brand.info_color' => config('branding.info_color'),
            'company.gstin' => config('branding.gstin'),
        ];

        return $map[$key] ?? null;
    }
}
