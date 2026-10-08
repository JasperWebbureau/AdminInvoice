<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Service;

use Flexgrid\Modules\AdminInvoice\Contract\RecurringInvoiceLinesExtensionInterface;

/** Ontdekt hooguit één projectadapter zonder projectafhankelijkheid in AdminInvoice. */
final class RecurringInvoiceLinesExtensionLoader
{
    private $appRoot;

    public function __construct(string $appRoot)
    {
        $this->appRoot = rtrim(str_replace('\\', '/', $appRoot), '/');
    }

    public function getExtension(): ?RecurringInvoiceLinesExtensionInterface
    {
        $extensions = [];
        foreach (glob($this->appRoot . '/*/Integration/Invoice/RecurringInvoiceLines.php') ?: [] as $file) {
            $module = basename(dirname($file, 3));
            if (preg_match('/^[A-Za-z][A-Za-z0-9]*$/D', $module) !== 1) {
                continue;
            }
            $class = 'App\\' . $module . '\\Integration\\Invoice\\RecurringInvoiceLines';
            require_once $file;
            $extension = new $class();
            if (!$extension instanceof RecurringInvoiceLinesExtensionInterface) {
                throw new \LogicException($class . ' moet RecurringInvoiceLinesExtensionInterface implementeren.');
            }
            $extensions[] = $extension;
        }
        if (count($extensions) > 1) {
            throw new \LogicException('Er mag maar één terugkerende-factuuradapter actief zijn.');
        }
        return $extensions[0] ?? null;
    }
}
