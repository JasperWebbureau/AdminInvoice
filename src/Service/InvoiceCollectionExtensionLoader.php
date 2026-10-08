<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Service;

use Flexgrid\Modules\AdminInvoice\Contract\InvoiceCollectionExtensionInterface;
use Flexgrid\Modules\AdminCore\Integration\Flexgrid\Service\AdminModuleState;

final class InvoiceCollectionExtensionLoader
{
    public static function load(string $modulesRoot): ?InvoiceCollectionExtensionInterface
    {
        $extensions = [];
        foreach (glob(rtrim($modulesRoot, '/\\') . '/*/src/Integration/Invoice/InvoiceCollectionExtension.php') ?: [] as $file) {
            $module = basename(dirname($file, 4));
            if (preg_match('/^[A-Za-z][A-Za-z0-9]*$/D', $module) !== 1) { continue; }
            $class = 'Flexgrid\\Modules\\' . $module . '\\Integration\\Invoice\\InvoiceCollectionExtension';
            if (class_exists(AdminModuleState::class) && !AdminModuleState::isEnabledForClass($class)) { continue; }
            if (!class_exists($class)) { continue; }
            $extension = new $class();
            if (!$extension instanceof InvoiceCollectionExtensionInterface) {
                throw new \LogicException('Ongeldige incasso-uitbreiding.');
            }
            $extensions[] = $extension;
        }
        if (count($extensions) > 1) { throw new \LogicException('Er kan maar één automatische incassoprovider actief zijn.'); }
        return $extensions[0] ?? null;
    }
}
