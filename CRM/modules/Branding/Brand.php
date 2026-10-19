<?php

namespace Modules\Branding;

use Modules\Settings\SettingStore;

class Brand
{
    public function __construct(protected SettingStore $settings) {}

    public function get(string $key, $default = null)
    {
        $map = [
            'company_name' => 'brand.company_name',
            'tagline' => 'brand.tagline',
            'primary_color' => 'brand.primary_color',
            'accent_color' => 'brand.accent_color',
            'link_color' => 'brand.link_color',
            'background_color' => 'brand.background_color',
            'surface_color' => 'brand.surface_color',
            'sidebar_bg' => 'brand.sidebar_bg',
            'sidebar_text' => 'brand.sidebar_text',
            'header_bg' => 'brand.header_bg',
            'text_color' => 'brand.text_color',
            'text_secondary' => 'brand.text_secondary',
            'border_color' => 'brand.border_color',
            'success_color' => 'brand.success_color',
            'warning_color' => 'brand.warning_color',
            'danger_color' => 'brand.danger_color',
            'info_color' => 'brand.info_color',
            'logo_path' => 'brand.logo_path',
            'logo_dark_path' => 'brand.logo_dark_path',
            'favicon_path' => 'brand.favicon_path',
            'login_heading' => 'brand.login_heading',
            'login_subtext' => 'brand.login_subtext',
            'address' => 'company.address',
            'gstin' => 'company.gstin',
            'state' => 'company.state',
            'email' => 'company.email',
            'phone' => 'company.phone',
            'website' => 'company.website',
        ];
        $settingKey = $map[$key] ?? $key;
        $fallback = config('branding.'.$key, $default);
        $val = $this->settings->get($settingKey, $fallback);
        // Empty string means "not set" at every layer (DB row, .env seed):
        // fall through to the default instead of blanking the brand.
        if ($val !== null && $val !== '') {
            return $val;
        }

        return $fallback !== null && $fallback !== '' ? $fallback : $default;
    }

    public function all(): array
    {
        return [
            'company_name' => $this->get('company_name'),
            'tagline' => $this->get('tagline'),
            'primary_color' => $this->get('primary_color', '#4f46e5'),
            'accent_color' => $this->get('accent_color', '#06b6d4'),
            'link_color' => $this->get('link_color', ''),
            'background_color' => $this->get('background_color', 'transparent'),
            'surface_color' => $this->get('surface_color', 'rgba(255,255,255,.70)'),
            'sidebar_bg' => $this->get('sidebar_bg', 'rgba(255,255,255,.64)'),
            'sidebar_text' => $this->get('sidebar_text', '#101623'),
            'header_bg' => $this->get('header_bg', ''),
            'text_color' => $this->get('text_color', '#101623'),
            'text_secondary' => $this->get('text_secondary', '#33405c'),
            'border_color' => $this->get('border_color', 'rgba(255,255,255,.72)'),
            'success_color' => $this->get('success_color', '#0d7a45'),
            'warning_color' => $this->get('warning_color', '#9a6208'),
            'danger_color' => $this->get('danger_color', '#c0271d'),
            'info_color' => $this->get('info_color', '#1a5fd0'),
            'logo_path' => $this->get('logo_path', ''),
            'logo_dark_path' => $this->get('logo_dark_path', ''),
            'favicon_path' => $this->get('favicon_path', ''),
            'login_heading' => $this->get('login_heading', ''),
            'login_subtext' => $this->get('login_subtext', ''),
            'address' => $this->get('address', ''),
            'gstin' => $this->get('gstin', ''),
            'state' => $this->get('state', ''),
            'email' => $this->get('email', ''),
            'phone' => $this->get('phone', ''),
            'website' => $this->get('website', ''),
            'app_name' => $this->settings->get('app.name', config('app.name')),
        ];
    }
}
