<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Application\UseCase;

use Flexgrid\Modules\AdminInvoice\Domain\Model\Invoice;
use Flexgrid\Modules\AdminInvoice\Service\InvoicePdfTemplateSelector;
use Flexgrid\Modules\AdminInvoice\Service\InvoicePdfViewModelFactory;
use Flexgrid\Modules\AdminPdf\Contract\DocumentRendererInterface;
use Flexgrid\Modules\AdminPdf\ValueObject\PdfDocument;
use Flexgrid\Modules\AdminPdf\ValueObject\PdfRenderOptions;

final class GenerateInvoicePdf
{
    /** @var DocumentRendererInterface */ private $renderer;
    /** @var InvoicePdfTemplateSelector */ private $templates;
    /** @var InvoicePdfViewModelFactory */ private $viewModels;
    /** @var PdfRenderOptions */ private $options;
    /** @var string */ private $configuredTemplate;
    /** @var array */ private $branding;
    /** @var DocumentRendererInterface|null */ private $previewRenderer;
    /** @var callable|null */ private $paymentLinkProvider;

    public function __construct(
        DocumentRendererInterface $renderer,
        InvoicePdfTemplateSelector $templates,
        InvoicePdfViewModelFactory $viewModels,
        PdfRenderOptions $options,
        string $configuredTemplate,
        array $branding,
        ?DocumentRendererInterface $previewRenderer = null,
        ?callable $paymentLinkProvider = null
    ) {
        $this->renderer = $renderer;
        $this->templates = $templates;
        $this->viewModels = $viewModels;
        $this->options = $options;
        $this->configuredTemplate = $configuredTemplate;
        $this->branding = $branding;
        $this->previewRenderer = $previewRenderer;
        $this->paymentLinkProvider = $paymentLinkProvider;
    }

    public function execute(Invoice $invoice): PdfDocument
    {
        $template = $this->templates->select($this->configuredTemplate);
        $viewModel = $this->viewModels->create($invoice, $this->branding);
        if ($this->paymentLinkProvider !== null) {
            $payment = ($this->paymentLinkProvider)($invoice);
            if (is_array($payment)) {
                $viewModel['invoice'] = array_merge($viewModel['invoice'], $payment);
            }
        }
        $number = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string)$invoice->getInvoiceNumber());
        $number = trim((string)$number, '-_');
        if ($number === '') {
            throw new \UnexpectedValueException('Factuurnummer kan niet als PDF-bestandsnaam worden gebruikt.');
        }

        return $this->renderer->render(
            $template,
            $viewModel,
            $this->options,
            'factuur-' . $number . '.pdf'
        );
    }

    public function preview(Invoice $invoice): PdfDocument
    {
        $template = $this->previewRenderer !== null
            ? 'default.php'
            : $this->templates->select($this->configuredTemplate);
        $viewModel = $this->viewModels->createPreview($invoice, $this->branding);
        $publicId = preg_replace('/[^A-Za-z0-9_-]+/', '-', $invoice->getPublicId());
        $publicId = trim((string)$publicId, '-_');

        return ($this->previewRenderer ?? $this->renderer)->render(
            $template,
            $viewModel,
            $this->options,
            'conceptfactuur-' . ($publicId !== '' ? $publicId : 'voorbeeld') . '.pdf'
        );
    }
}
