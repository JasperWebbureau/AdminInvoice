<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Service;

use Flexgrid\App\Settings\Settings;

/** Tenantgebonden voorkeur; ontbrekende instellingen betekenen handmatige controle. */
final class InvoiceProcessingSettings
{
    public static function automaticFinalization(): bool
    {
        new Settings();
        $setting = Settings::get(self::key());
        return is_array($setting) && (string)($setting['value'] ?? '') === '1';
    }

    public static function save(bool $enabled): void
    {
        new Settings();
        Settings::get(self::key(), ['value' => '0', 'label' => 'Maandfacturen automatisch goedkeuren', 'input_type' => '']);
        Settings::set(self::key(), $enabled ? '1' : '0');
        (new Settings())->save();
    }

    private static function key(): string
    {
        if (!defined('__ADMIN_TENANT_ID__')) {
            throw new \LogicException('De administratietenant ontbreekt.');
        }
        return 'administration_invoice_auto_finalize_' . hash('sha256', (string)__ADMIN_TENANT_ID__);
    }
}
