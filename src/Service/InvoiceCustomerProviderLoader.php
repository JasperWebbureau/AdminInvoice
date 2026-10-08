<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Service;

use Flexgrid\Modules\AdminInvoice\Contract\InvoiceCustomerProviderInterface;

/** Ontdekt hooguit één optionele Customer-provider zonder concrete module-import. */
final class InvoiceCustomerProviderLoader
{
    /** @var string */ private $modulesRoot;
    /** @var string[] */ private $providerClasses;

    public function __construct(string $modulesRoot, array $providerClasses = [])
    {
        $this->modulesRoot = rtrim(str_replace('\\', '/', $modulesRoot), '/');
        $this->providerClasses = $providerClasses;
    }

    public function getProvider(): ?InvoiceCustomerProviderInterface
    {
        $providers = [];
        foreach ($this->classes() as $class) {
            if (!class_exists($class)) {
                continue;
            }
            $provider = new $class();
            if (!$provider instanceof InvoiceCustomerProviderInterface) {
                throw new \LogicException($class . ' moet InvoiceCustomerProviderInterface implementeren.');
            }
            $providers[] = $provider;
        }
        if (count($providers) > 1) {
            throw new \LogicException('Er mag maar één Invoice-klantprovider actief zijn.');
        }
        return $providers[0] ?? null;
    }

    /** @return string[] */
    private function classes(): array
    {
        if ($this->providerClasses !== []) {
            return array_values(array_unique(array_filter($this->providerClasses, 'is_string')));
        }
        if ($this->modulesRoot === '' || !is_dir($this->modulesRoot)) {
            return [];
        }
        $classes = [];
        foreach (glob($this->modulesRoot . '/*/src/Integration/Invoice/CustomerSelectionProvider.php') ?: [] as $file) {
            $module = basename(dirname($file, 4));
            if (preg_match('/^[A-Za-z][A-Za-z0-9]*$/D', $module) !== 1 || $module === 'AdminInvoice') {
                continue;
            }
            require_once $file;
            $classes[] = 'Flexgrid\\Modules\\' . $module . '\\Integration\\Invoice\\CustomerSelectionProvider';
        }
        sort($classes);
        return array_values(array_unique($classes));
    }
}
