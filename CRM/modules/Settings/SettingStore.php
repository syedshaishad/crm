<?php

namespace Modules\Settings;

use Illuminate\Support\Facades\DB;

class SettingStore
{
    /** Per-request memo: once the DB proves unreachable, stop re-dialling it. */
    protected static bool $dead = false;

    public function get(string $key, $default = null)
    {
        if (static::$dead) {
            return $default ?? $this->envFallback($key);
        }
        try {
            $row = DB::table('settings')->where('key', $key)->first();
            if (! $row) {
                return $default ?? $this->envFallback($key);
            }

            return $row->value;
        } catch (\Throwable $e) {
            static::$dead = true;

            return $default ?? $this->envFallback($key);
        }
    }

    public function set(string $key, $value, string $group = 'general'): void
    {
        \DB::table('settings')->updateOrInsert(
            ['key' => $key],
            ['value' => is_scalar($value) || $value === null ? (string) $value : json_encode($value), 'group' => $group, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    public function all(?string $group = null): array
    {
        if (static::$dead) {
            return [];
        }
        try {
            $q = \DB::table('settings');
            if ($group) {
                $q->where('group', $group);
            }

            return $q->pluck('value', 'key')->all();
        } catch (\Throwable $e) {
            static::$dead = true;

            return [];
        }
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
