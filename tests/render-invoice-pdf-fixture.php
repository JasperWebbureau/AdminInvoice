<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/InvoicePdfTest.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\GenerateInvoicePdf;
use Flexgrid\Modules\AdminInvoice\Service\InvoicePdfTemplateSelector;
use Flexgrid\Modules\AdminInvoice\Service\InvoicePdfViewModelFactory;
use Flexgrid\Modules\AdminInvoice\Service\ProjectInvoiceBranding;
use Flexgrid\Modules\AdminInvoice\Service\ScssBrandColorReader;
use Flexgrid\Modules\AdminPdf\Infrastructure\DompdfDocumentRenderer;
use Flexgrid\Modules\AdminPdf\Infrastructure\FilesystemTemplateResolver;
use Flexgrid\Modules\AdminPdf\ValueObject\PdfRenderOptions;

$outputDirectory = dirname(__DIR__, 4) . '/tmp/pdfs';
if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0775, true) && !is_dir($outputDirectory)) {
    throw new RuntimeException('Tijdelijke PDF-map kon niet worden aangemaakt.');
}

$previewBranding = (new ProjectInvoiceBranding($projectRoot, new ScssBrandColorReader()))->create(
    $branding['company'],
    $projectRoot . '/Files/Images/settings/logo_5 (1).png'
);
$previewResolver = new FilesystemTemplateResolver([$projectTemplates, $moduleTemplates]);
$previewGenerator = new GenerateInvoicePdf(
    new DompdfDocumentRenderer($previewResolver),
    new InvoicePdfTemplateSelector($previewResolver),
    new InvoicePdfViewModelFactory(),
    new PdfRenderOptions('A4', 'portrait', 'DejaVu Sans', [$projectTemplates, $moduleTemplates]),
    'default',
    $previewBranding
);
$viewModel = (new InvoicePdfViewModelFactory())->create($invoice, $previewBranding);
$document = $previewGenerator->execute($invoice);
$pdfPath = $outputDirectory . '/admininvoice-default-fixture.pdf';
if (file_put_contents($pdfPath, $document->getContent()) !== $document->getSize()) {
    throw new RuntimeException('Factuurfixture kon niet volledig worden geschreven.');
}

$renderTemplate = static function (string $path, array $data): string {
    extract($data, EXTR_SKIP);
    ob_start();
    include $path;
    return (string)ob_get_clean();
};
$options = new Options();
$options->setPdfBackend('GD');
$options->setIsRemoteEnabled(false);
$options->setIsPhpEnabled(false);
$options->setIsJavascriptEnabled(false);
$options->setDefaultFont('DejaVu Sans');
$options->setChroot([$projectTemplates, $moduleTemplates]);
$preview = new Dompdf($options);
$preview->loadHtml($renderTemplate($moduleTemplates . '/default.php', $viewModel), 'UTF-8');
$preview->setPaper('A4', 'portrait');
$preview->render();
$previewContent = $preview->output(['type' => 'png', 'page' => 1]);
$previewPath = $outputDirectory . '/admininvoice-default-fixture-page-1.png';
if (!is_string($previewContent) || file_put_contents($previewPath, $previewContent) !== strlen($previewContent)) {
    throw new RuntimeException('Factuurfixture-preview kon niet volledig worden geschreven.');
}

echo $pdfPath . PHP_EOL;
echo $previewPath . PHP_EOL;
