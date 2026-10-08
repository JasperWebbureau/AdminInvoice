<?php

declare(strict_types=1);

$moduleRoot = dirname(__DIR__);
$modulesRoot = dirname($moduleRoot);
$projectRoot = dirname(__DIR__, 4);

require_once $modulesRoot . '/AdminCore/tests/bootstrap.php';
require_once $projectRoot . '/vendor/autoload.php';
foreach ([
    'Contract/DocumentRendererInterface.php',
    'Contract/TemplateResolverInterface.php',
    'ValueObject/PdfRenderOptions.php',
    'ValueObject/PdfDocument.php',
    'Infrastructure/FilesystemTemplateResolver.php',
    'Infrastructure/DompdfDocumentRenderer.php',
] as $file) {
    require_once $modulesRoot . '/AdminPdf/src/' . $file;
}

foreach ([
    'Domain/ValueObject/InvoiceStatus.php',
    'Domain/ValueObject/PaymentStatus.php',
    'Domain/ValueObject/TaxCategory.php',
    'Domain/ValueObject/Quantity.php',
    'Domain/ValueObject/CalendarDate.php',
    'Domain/ValueObject/PartySnapshot.php',
    'Domain/ValueObject/AddressSnapshot.php',
    'Domain/ValueObject/TaxSummary.php',
    'Domain/ValueObject/InvoiceTotals.php',
    'Domain/Model/InvoiceLine.php',
    'Domain/Model/Invoice.php',
    'Domain/Model/CreditNote.php',
    'Service/InvoiceCalculator.php',
    'Service/CreditNoteNumberFormatter.php',
    'Service/ScssBrandColorReader.php',
    'Service/ProjectInvoiceBranding.php',
    'Service/InvoicePdfTemplateSelector.php',
    'Service/InvoicePdfViewModelFactory.php',
    'Service/CreditNotePdfViewModelFactory.php',
    'Service/CreditNotePresenter.php',
    'Contract/InvoiceRepositoryInterface.php',
    'Contract/InvoiceDraftCreatorInterface.php',
    'Contract/CreditNoteRepositoryInterface.php',
    'Exception/DuplicateExternalReferenceException.php',
    'Exception/InvoiceNotFoundException.php',
    'Exception/CreditNoteNotFoundException.php',
    'Application/Command/CreateInvoiceCommand.php',
    'Application/Command/UpdateDraftInvoiceCommand.php',
    'Application/Command/AddInvoiceLineCommand.php',
    'Application/Command/UpdateInvoiceLineCommand.php',
    'Application/UseCase/CreateInvoice.php',
    'Application/UseCase/CreateFullCreditNote.php',
    'Application/UseCase/GetInvoice.php',
    'Application/UseCase/GetCreditNote.php',
    'Application/UseCase/GenerateInvoicePdf.php',
    'Application/UseCase/GenerateCreditNotePdf.php',
    'Service/InvoiceNumberFormatter.php',
    'Application/UseCase/FinalizeInvoice.php',
    'Application/UseCase/UpdateDraftInvoice.php',
    'Application/UseCase/AddInvoiceLine.php',
    'Application/UseCase/UpdateInvoiceLine.php',
    'Application/UseCase/RemoveInvoiceLine.php',
    'Application/UseCase/DeleteDraftInvoice.php',
    'Application/UseCase/CorrectInvoiceIssueDate.php',
    'Infrastructure/Persistence/PdoInvoiceRepository.php',
    'Infrastructure/Persistence/PdoCreditNoteRepository.php',
    'Application/Query/InvoiceListQuery.php',
    'Application/ReadModel/InvoiceListItem.php',
    'Application/ReadModel/InvoiceOverviewSummary.php',
    'Application/ReadModel/InvoiceListResult.php',
    'Application/ReadModel/InvoiceDetailContext.php',
    'Application/ReadModel/InvoiceCustomerSelection.php',
    'Contract/InvoiceDetailExtensionInterface.php',
    'Contract/InvoiceCustomerProviderInterface.php',
    'Contract/RecurringInvoiceLinesExtensionInterface.php',
    'Contract/RecurringInvoiceAdditionalLinesExtensionInterface.php',
    'Contract/InvoiceListRepositoryInterface.php',
    'Contract/InvoiceCustomerHistoryRepositoryInterface.php',
    'Application/UseCase/ListInvoices.php',
    'Application/UseCase/ListCustomerDocuments.php',
    'Infrastructure/Persistence/PdoInvoiceListRepository.php',
    'Service/InvoiceOverviewPresenter.php',
    'Service/CustomerInvoiceHistoryPresenter.php',
    'Service/InvoiceEditorPresenter.php',
    'Service/InvoiceDetailExtensionLoader.php',
    'Service/InvoiceCustomerProviderLoader.php',
    'Service/RecurringInvoiceLinesExtensionLoader.php',
    'Service/InvoiceCustomerPrefill.php',
    'Contract/InvoiceMailSenderInterface.php',
    'Service/InvoiceDispatchService.php',
    'Service/InvoiceReviewQueue.php',
] as $file) {
    require_once $moduleRoot . '/src/' . $file;
}

function adminInvoiceAssert($condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function adminInvoiceAssertThrows(string $exceptionClass, callable $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable $throwable) {
        if ($throwable instanceof $exceptionClass) {
            return;
        }

        throw new RuntimeException($message . ' Ontvangen: ' . get_class($throwable));
    }

    throw new RuntimeException($message . ' Er werd geen exception gegooid.');
}
