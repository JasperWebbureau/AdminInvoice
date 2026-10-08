<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Form\Input;

use Flexgrid\Form\Input\SelectInput;

final class InvoicePdfTemplateInput extends SelectInput
{
    public function __construct($name, $formName = null)
    {
        parent::__construct($name, $formName);
        $this->setOptions($this->discoverTemplates());
    }

    private function discoverTemplates(): array
    {
        $roots = [
            rtrim((string)__ROOTDIR__, '/\\') . '/Templates/AdminInvoice/Pdf',
            dirname(__DIR__, 2) . '/Templates/Pdf',
        ];
        $names = [];
        foreach ($roots as $root) {
            foreach (glob($root . '/*.php') ?: [] as $file) {
                $name = strtolower((string)pathinfo($file, PATHINFO_FILENAME));
                if (preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $name) === 1) {
                    $names[$name] = $name === 'default'
                        ? 'Standaard'
                        : ucfirst(str_replace(['-', '_'], ' ', $name));
                }
            }
        }
        if (!isset($names['default'])) {
            $names['default'] = 'Standaard';
        }
        ksort($names, SORT_NATURAL | SORT_FLAG_CASE);

        return ['default' => $names['default']] + array_diff_key($names, ['default' => true]);
    }
}
