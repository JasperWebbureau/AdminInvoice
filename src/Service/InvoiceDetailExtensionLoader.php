<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Service;

use Flexgrid\Modules\AdminInvoice\Application\ReadModel\InvoiceDetailContext;
use Flexgrid\Modules\AdminInvoice\Contract\InvoiceDetailExtensionInterface;

/**
 * Ontdekt optionele module-uitbreidingen zonder een concrete module te importeren.
 */
final class InvoiceDetailExtensionLoader
{
    /** @var string */ private $modulesRoot;
    /** @var string[] */ private $extensionClasses;

    public function __construct(string $modulesRoot, array $extensionClasses = [])
    {
        $this->modulesRoot = rtrim(str_replace('\\', '/', $modulesRoot), '/');
        $this->extensionClasses = $extensionClasses;
    }

    public function render(InvoiceDetailContext $context): string
    {
        $html = '';
        foreach ($this->classes() as $class) {
            $state = '\\Flexgrid\\Modules\\AdminCore\\Integration\\Flexgrid\\Service\\AdminModuleState';
            $visibility = '\\Flexgrid\\Modules\\AdminCore\\Integration\\Flexgrid\\Service\\AdminModuleInterfaceVisibility';
            if ((class_exists($state) && !$state::isEnabledForClass($class))
                || (class_exists($visibility) && !$visibility::isVisibleForCurrentUserClass($class))) {
                continue;
            }
            if (!class_exists($class)) {
                continue;
            }
            $extension = new $class();
            if (!$extension instanceof InvoiceDetailExtensionInterface) {
                throw new \LogicException($class . ' moet InvoiceDetailExtensionInterface implementeren.');
            }
            $html .= $extension->render($context);
        }

        return $html;
    }

    /** @return string[] */
    private function classes(): array
    {
        if ($this->extensionClasses !== []) {
            return array_values(array_unique(array_filter($this->extensionClasses, 'is_string')));
        }
        if ($this->modulesRoot === '' || !is_dir($this->modulesRoot)) {
            return [];
        }

        $classes = [];
        $pattern = $this->modulesRoot . '/*/src/Integration/Invoice/InvoiceDetailExtension.php';
        foreach (glob($pattern) ?: [] as $file) {
            $module = basename(dirname($file, 4));
            if (preg_match('/^[A-Za-z][A-Za-z0-9]*$/D', $module) !== 1 || $module === 'AdminInvoice') {
                continue;
            }
            $classes[] = 'Flexgrid\\Modules\\' . $module . '\\Integration\\Invoice\\InvoiceDetailExtension';
        }

        sort($classes);
        return array_values(array_unique($classes));
    }
}
