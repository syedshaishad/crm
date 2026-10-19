<?php

namespace Modules\Installer;

class EnvWriter
{
    public function write(array $values): void
    {
        $envPath = base_path('.env');
        $example = base_path('.env.example');
        if (! file_exists($envPath) && file_exists($example)) {
            copy($example, $envPath);
        }
        $content = file_exists($envPath) ? file_get_contents($envPath) : '';
        foreach ($values as $key => $val) {
            $val = $this->format($val);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
            $line = $key.'='.$val;
            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, $line, $content);
            } else {
                $content = rtrim($content)."\n".$line."\n";
            }
        }
        // Backup previous .env before overwrite
        if (file_exists($envPath)) {
            copy($envPath, base_path('.env.backup.'.date('YmdHis')));
        }
        file_put_contents($envPath, $content);
    }

    protected function format($val): string
    {
        $val = (string) ($val ?? '');
        if ($val === '' || $val === 'null') {
            return '""';
        }
        if (preg_match('/\s|#|"|\'/', $val)) {
            return '"'.str_replace('"', '\"', $val).'"';
        }

        return $val;
    }

    public function setInstalled(): void
    {
        $this->write(['APP_INSTALLED' => 'true', 'APP_DEBUG' => 'false']);
        @mkdir(dirname(config('installer.lock_file')), 0755, true);
        file_put_contents(config('installer.lock_file'), json_encode(['installed_at' => date('c'), 'version' => config('version.version', '1.0.0')]));
    }
}
