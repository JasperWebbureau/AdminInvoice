<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Service;

use Flexgrid\Modules\AdminPdf\Contract\TemplateResolverInterface;

final class InvoicePdfTemplateSelector
{
    /** @var TemplateResolverInterface */ private $templates;

    public function __construct(TemplateResolverInterface $templates)
    {
        $this->templates = $templates;
    }

    public function select(string $configuredName): string
    {
        $name = strtolower(trim($configuredName));
        if (preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $name) === 1) {
            $template = $name . '.php';
            try {
                $this->templates->resolve($template);
                return $template;
            } catch (\RuntimeException $exception) {
                // Een verwijderde projecttemplate valt veilig terug op de moduledefault.
            }
        }

        $this->templates->resolve('default.php');
        return 'default.php';
    }
}
