<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Application\UseCase;

use Flexgrid\Modules\AdminInvoice\Domain\Model\CreditNote;
use Flexgrid\Modules\AdminInvoice\Service\CreditNotePdfViewModelFactory;
use Flexgrid\Modules\AdminInvoice\Service\InvoicePdfTemplateSelector;
use Flexgrid\Modules\AdminPdf\Contract\DocumentRendererInterface;
use Flexgrid\Modules\AdminPdf\ValueObject\PdfDocument;
use Flexgrid\Modules\AdminPdf\ValueObject\PdfRenderOptions;

final class GenerateCreditNotePdf
{
    /** @var DocumentRendererInterface */ private $renderer;
    /** @var InvoicePdfTemplateSelector */ private $templates;
    /** @var CreditNotePdfViewModelFactory */ private $viewModels;
    /** @var PdfRenderOptions */ private $options;
    /** @var string */ private $configuredTemplate;
    /** @var array */ private $branding;

    public function __construct(
        DocumentRendererInterface $renderer,
        InvoicePdfTemplateSelector $templates,
        CreditNotePdfViewModelFactory $viewModels,
        PdfRenderOptions $options,
        string $configuredTemplate,
        array $branding
    ) {
        $this->renderer = $renderer;
        $this->templates = $templates;
        $this->viewModels = $viewModels;
        $this->options = $options;
        $this->configuredTemplate = $configuredTemplate;
        $this->branding = $branding;
    }

    public function execute(CreditNote $creditNote): PdfDocument
    {
        $number = preg_replace('/[^A-Za-z0-9_-]+/', '-', $creditNote->getNumber());
        $number = trim((string)$number, '-_');
        if ($number === '') {
            throw new \UnexpectedValueException('Creditnotanummer kan niet als PDF-bestandsnaam worden gebruikt.');
        }

        return $this->renderer->render(
            $this->templates->select($this->configuredTemplate),
            $this->viewModels->create($creditNote, $this->branding),
            $this->options,
            'creditnota-' . $number . '.pdf'
        );
    }
}
