<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SystemMailer
{
    public function settings(): Collection
    {
        return DB::table('system_settings')->pluck('setting_value', 'setting_key');
    }

    public function isReady(Collection|array $settings): bool
    {
        foreach (['smtp_host', 'smtp_port', 'smtp_from_email'] as $key) {
            if (blank($settings[$key] ?? null)) {
                return false;
            }
        }

        if (filled($settings['smtp_username'] ?? null) && blank($settings['smtp_password'] ?? null)) {
            return false;
        }

        return filter_var($settings['smtp_from_email'], FILTER_VALIDATE_EMAIL) !== false;
    }

    public function configure(Collection|array|null $settings = null): bool
    {
        $settings ??= $this->settings();
        if (! $this->isReady($settings)) {
            return false;
        }

        $encryption = $settings['smtp_encryption'] ?? 'tls';
        $username = trim((string) ($settings['smtp_username'] ?? ''));
        $password = filled($settings['smtp_password'] ?? null)
            ? decrypt($settings['smtp_password'])
            : null;

        Config::set('mail.mailers.smtp', [
            'transport' => 'smtp',
            'scheme' => $encryption === 'ssl' ? 'smtps' : 'smtp',
            'host' => $settings['smtp_host'],
            'port' => (int) $settings['smtp_port'],
            'auto_tls' => $encryption !== 'none',
            'username' => $username !== '' ? $username : null,
            'password' => $password,
            'timeout' => 30,
        ]);
        Config::set('mail.default', 'smtp');
        Config::set('mail.from', [
            'address' => strtolower(trim((string) $settings['smtp_from_email'])),
            'name' => $settings['smtp_from_name'] ?? 'PRIMA',
        ]);
        Mail::purge('smtp');

        return true;
    }
}
