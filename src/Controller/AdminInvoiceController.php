<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Controller;

use Flexgrid\Event\AjaxEvent;
use Flexgrid\Flexgrid;
use Flexgrid\Modules\AdminInvoice\Application\Command\AddInvoiceLineCommand;
use Flexgrid\Modules\AdminInvoice\Application\Command\CreateInvoiceCommand;
use Flexgrid\Modules\AdminInvoice\Application\Command\UpdateDraftInvoiceCommand;
use Flexgrid\Modules\AdminInvoice\Application\Command\UpdateInvoiceLineCommand;
use Flexgrid\Modules\AdminInvoice\Application\Query\InvoiceListQuery;
use Flexgrid\Modules\AdminInvoice\Application\ReadModel\InvoiceDetailContext;
use Flexgrid\Modules\AdminInvoice\Domain\Model\Invoice;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\InvoiceStatus;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\PaymentStatus;
use Flexgrid\Modules\AdminInvoice\Service\AdminInvoiceFactory;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceCustomerProviderLoader;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceCustomerPrefill;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceDetailExtensionLoader;
use Flexgrid\Modules\AdminPdf\ValueObject\PdfDocument;
use Flexgrid\Response\AjaxResponse;
use Flexgrid\Response\PageResponse;
use Flexgrid\Response\TemplateResponse;
use Flexgrid\Utils\Request\Request;

/**
 * @FG\Controller [name=AdminInvoice,type=Module,icon=fas fa-file-invoice,level=2,administrationPanel=true,administrationLabel=Facturen,administrationRoute=invoices,administrationPriority=40]
 */
final class AdminInvoiceController
{
    public function index()
    {

        return $this->invoices();
    }

    public function invoices()
    {
        appendIconAndTitleToHeader('fas fa-file-invoice', 'Facturen', 'Administratie');
        $this->registerAdminAssets();
        PageResponse::addAsset('Flexgrid/Flexgrid/src/Html/Table/Css/Table.scss');
        PageResponse::addAsset('Flexgrid/Flexgrid/src/Html/Table/Js/Table.js');
        \Flexgrid\Modules\AdminCore\Service\AdminHeader::AdminAddHeader([new TemplateResponse(
            'Flexgrid/Modules/AdminInvoice/src/Templates/Invoices/HeaderActions.php',
            ['createUrl' => $this->moduleUrl('create')]
        )], 'invoices');

        $syncAction = '';
        $factory = '\\Flexgrid\\Modules\\AdminPayment\\Integration\\Invoice\\InvoicePaymentFactory';
        $action = '\\Flexgrid\\Modules\\AdminPayment\\Integration\\Invoice\\InvoicePaymentAction';
        $paymentVisible = \Flexgrid\Modules\AdminCore\Integration\Flexgrid\Service\AdminModuleState::isEnabled('AdminPayment')
            && \Flexgrid\Modules\AdminCore\Integration\Flexgrid\Service\AdminModuleInterfaceVisibility::isVisibleForCurrentUser('AdminPayment');
        if ($paymentVisible && class_exists($factory) && $factory::mollieLinksEnabled()) {
            $event = new AjaxEvent($action, 'synchronizeLinks');
            $event->setMinimumAccessLevel(2);
            $syncAction = $event->getName();
        }

        return new TemplateResponse('Flexgrid/Modules/AdminInvoice/src/Templates/Invoices/Index.php', [
            'content' => (string)$this->renderContent($this->queryFromRequest()),
            'mollieSyncAction' => $syncAction,
        ]);
    }

    public function reviewQueue()
    {
        appendIconAndTitleToHeader('fas fa-list-check', 'Concepten controleren', 'Administratie');
        $this->registerAdminAssets();
        \Flexgrid\Modules\AdminCore\Service\AdminHeader::AdminAddHeader([new TemplateResponse(
            'Flexgrid/Modules/AdminInvoice/src/Templates/ReviewQueue/HeaderActions.php',
            ['overviewUrl' => $this->moduleUrl('invoices')]
        )]);
        $rows = [];
        foreach (AdminInvoiceFactory::createReviewQueue()->recent() as $entry) {
            $id = (string)$entry['public_id'];
            $rows[] = [
                'id' => $id,
                'cells' => [
                    'issue_date' => (string)$entry['issue_date'],
                    'customer' => (string)$entry['customer_name'],
                    'origin' => (string)$entry['origin'],
                    'total' => (string)$entry['currency'] . ' ' . (string)$entry['amount'],
                ],
                'actions' => [
                    ['href' => $this->moduleUrl('edit/' . rawurlencode($id)), 'label' => 'Bewerken / definitief maken', 'show_label' => true],
                    ['href' => $this->moduleUrl('previewPdf/' . rawurlencode($id)), 'label' => 'Concept-PDF', 'show_label' => true,
                        'attributes' => ['target' => '_blank', 'rel' => 'noopener']],
                ],
            ];
        }
        return new TemplateResponse('Flexgrid/Modules/AdminInvoice/src/Templates/ReviewQueue/Index.php', [
            'table' => [
                'id' => 'admin-invoice-review-queue', 'label' => 'Te controleren concepten',
                'columns' => [
                    ['key' => 'issue_date', 'label' => 'Factuurdatum'],
                    ['key' => 'customer', 'label' => 'Klant'],
                    ['key' => 'origin', 'label' => 'Herkomst'],
                    ['key' => 'total', 'label' => 'Totaal incl. btw', 'align' => 'right'],
                ],
                'rows' => $rows,
                'empty' => ['title' => 'Geen concepten', 'message' => 'Er zijn geen conceptfacturen om te controleren.', 'icon' => 'fas fa-list-check'],
            ],
            'rowCount' => count($rows),
        ]);
    }

    public function create($args = [])
    {
        appendIconAndTitleToHeader('fas fa-file-circle-plus', 'Nieuwe factuur', 'Administratie');
        $this->registerAdminAssets(true);
        $this->appendPageActions('back');
        $now = time();
        $prefill = (new InvoiceCustomerPrefill())->resolve(
            $this->customerProvider(),
            $this->routeArgument($args)
        );

        return new TemplateResponse('Flexgrid/Modules/AdminInvoice/src/Templates/Create/Index.php', [
            'createAction' => $this->ajaxAction('store'),
            'overviewUrl' => $this->moduleUrl('invoices'),
            'issueDate' => date('Y-m-d', $now),
            'dueDate' => date('Y-m-d', strtotime('+30 days', $now)),
            'customer' => $prefill['customer'],
            'address' => $prefill['address'],
            'prefillNotice' => $prefill['notice'],
            'customerSelector' => $this->renderCustomerSelector($prefill['customer']),
        ]);
    }

    public function store()
    {
        try {
            $request = new Request();
            $invoice = AdminInvoiceFactory::createCreateInvoice()->execute(new CreateInvoiceCommand(
                $this->requestString($request, 'currency') ?: 'EUR',
                $this->requestString($request, 'issue_date'),
                $this->requestString($request, 'due_date'),
                $this->requestArray($request, 'customer'),
                $this->requestArray($request, 'address'),
                [],
                $this->requestString($request, 'customer_reference'),
                $this->requestString($request, 'notes')
            ));

            $response = new AjaxResponse();
            $response->success = true;
            $response->redirect = $this->moduleUrl('edit/' . rawurlencode($invoice->getPublicId()));

            return $response;
        } catch (\Throwable $throwable) {
            return $this->errorResponse($throwable);
        }
    }

    public function edit($args = [])
    {
        $publicId = $this->routeArgument($args);

        try {
            $invoice = AdminInvoiceFactory::createGetInvoice()->execute($publicId);
        } catch (\Throwable $throwable) {
            return $this->invoices();
        }
        if (in_array($invoice->getStatus()->getValue(), [InvoiceStatus::FINALIZED, InvoiceStatus::CREDITED], true)) {
            return $this->renderDetail($invoice);
        }
        if ($invoice->getStatus()->getValue() !== InvoiceStatus::DRAFT) {
            return $this->invoices();
        }

        appendIconAndTitleToHeader('fas fa-file-invoice', 'Factuur bewerken', 'Administratie');
        $this->registerAdminAssets(true);
        $viewModel = AdminInvoiceFactory::createEditorPresenter()->present($invoice, $this->editorActions());
        $viewModel['customerSelector'] = $this->renderCustomerSelector($viewModel['customer']);
        $viewModel['customerEditUrl'] = $this->draftCustomerEditUrl($invoice);
        $viewModel['sourceQuoteUrl'] = $this->sourceQuoteUrl($invoice);
        $viewModel['catalogItems'] = AdminInvoiceFactory::createCatalog()->allActiveLines();
        \Flexgrid\Modules\AdminCore\Service\AdminHeader::AdminAddHeader([new TemplateResponse(
            'Flexgrid/Modules/AdminInvoice/src/Templates/Editor/HeaderActions.php',
            [
                'overviewUrl' => $this->moduleUrl('invoices'),
                'previewUrl' => $this->moduleUrl('previewPdf/' . rawurlencode($invoice->getPublicId())),
                'finalizeAction' => $viewModel['actions']['finalize'],
                'publicId' => $viewModel['publicId'],
                'canFinalize' => $viewModel['canFinalize'],
                'deleteAction' => $this->ajaxAction('deleteDraft'),
            ]
        )]);

        return new TemplateResponse('Flexgrid/Modules/AdminInvoice/src/Templates/Editor/Index.php', [
            'content' => (string)new TemplateResponse(
                'Flexgrid/Modules/AdminInvoice/src/Templates/Editor/Content.php',
                $viewModel
            ),
            'statusLabel' => $viewModel['statusLabel'],
            'publicId' => $viewModel['publicId'],
            'canFinalize' => $viewModel['canFinalize'],
            'finalizeAction' => $viewModel['actions']['finalize'],
            'deleteAction' => $this->ajaxAction('deleteDraft'),
            'previewUrl' => $this->moduleUrl('previewPdf/' . rawurlencode($invoice->getPublicId())),
            'overviewUrl' => $this->moduleUrl('invoices'),
        ]);
    }

    public function detail($args = [])
    {
        try {
            $invoice = AdminInvoiceFactory::createGetInvoice()->execute($this->routeArgument($args));
        } catch (\Throwable $throwable) {
            return $this->invoices();
        }

        if ($invoice->getStatus()->getValue() === InvoiceStatus::DRAFT) {
            return $this->edit($invoice->getPublicId());
        }
        if (!in_array($invoice->getStatus()->getValue(), [InvoiceStatus::FINALIZED, InvoiceStatus::CREDITED], true)) {
            return $this->invoices();
        }

        return $this->renderDetail($invoice);
    }

    public function pdf($args = [])
    {
        try {
            $invoice = AdminInvoiceFactory::createGetInvoice()->execute($this->routeArgument($args));
            $document = AdminInvoiceFactory::createGenerateInvoicePdf()->execute($invoice);
        } catch (\Throwable $throwable) {
            return $this->invoices();
        }

        $this->streamPdf($document);
        return null;
    }

    public function previewPdf($args = [])
    {
        try {
            $invoice = AdminInvoiceFactory::createGetInvoice()->execute($this->routeArgument($args));
            $document = AdminInvoiceFactory::createGenerateInvoicePdf()->preview($invoice);
        } catch (\Throwable $throwable) {
            return $this->invoices();
        }

        $this->streamPdf($document);
        return null;
    }

    public function finalize()
    {
        try {
            $request = new Request();
            $invoice = AdminInvoiceFactory::createFinalizeInvoice()->execute(
                $this->requestString($request, 'public_id')
            );
            if (AdminInvoiceFactory::processingReady()) { AdminInvoiceFactory::createProcessing()->enroll($invoice); }

            $response = new AjaxResponse();
            $response->success = true;
            $response->redirect = $this->moduleUrl('detail/' . rawurlencode($invoice->getPublicId()));

            return $response;
        } catch (\Throwable $throwable) {
            return $this->errorResponse($throwable);
        }
    }

    public function deleteDraft(): AjaxResponse
    {
        try {
            $request = new Request();
            AdminInvoiceFactory::createDeleteDraftInvoice()->execute(
                $this->requestString($request, 'public_id') ?: $this->requestString($request, 'public-id')
            );

            $response = new AjaxResponse();
            $response->success = true;
            $response->redirect = $this->moduleUrl('invoices');
            return $response;
        } catch (\Throwable $throwable) {
            return $this->errorResponse($throwable);
        }
    }

    public function correctIssueDate(): AjaxResponse
    {
        try {
            $request = new Request();
            $invoice = AdminInvoiceFactory::createCorrectInvoiceIssueDate()->execute(
                $this->requestString($request, 'public_id'),
                $this->requestString($request, 'issue_date')
            );
            $response = new AjaxResponse();
            $response->success = true;
            $response->redirect = $this->requestString($request, 'view') === 'overview'
                ? $this->overviewUrl($this->queryFromRequest())
                : $this->moduleUrl('detail/' . rawurlencode($invoice->getPublicId()));
            return $response;
        } catch (\Throwable $throwable) {
            return $this->errorResponse($throwable);
        }
    }

    public function dispatch(): AjaxResponse
    {
        try {
            $request = new Request();
            $publicId = $this->requestString($request, 'public_id');
            $invoice = AdminInvoiceFactory::createGetInvoice()->execute($publicId);
            $kind = $this->requestString($request, 'kind');
            $collection = $kind === 'invoice'
                ? \Flexgrid\Modules\AdminInvoice\Service\InvoiceCollectionExtensionLoader::load(dirname(__DIR__, 3)) : null;
            $notice = $collection !== null ? $collection->prepare($invoice) : '';
            AdminInvoiceFactory::createDispatchService()->send(
                $invoice,
                $kind,
                $this->requestString($request, 'recipient'),
                $this->requestString($request, 'confirm_repeat') === '1',
                $notice
            );
            if ($kind === 'invoice') {
                if ($collection !== null) { $collection->noticeSent($invoice); }
                if (AdminInvoiceFactory::processingReady()) { AdminInvoiceFactory::createProcessing()->completeAfterDispatch($publicId); }
            }
            $response = new AjaxResponse();
            $response->success = true;
            $response->redirect = $this->moduleUrl('detail/' . rawurlencode($publicId));
            return $response;
        } catch (\Throwable $throwable) {
            $response = new AjaxResponse();
            $response->success = false;
            $message = $throwable instanceof \DomainException || $throwable instanceof \InvalidArgumentException
                || $throwable instanceof \LogicException ? $throwable->getMessage() : 'Verzenden is mislukt. Vernieuw de factuurpagina en controleer de verzendpoging voordat je opnieuw mailt.';
            $response->error = $message;
            $response->notifications = [
                '<div class="notification notification--error">'
                . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>',
            ];
            return $response;
        }
    }

    public function credit()
    {
        try {
            $request = new Request();
            $creditNote = AdminInvoiceFactory::createFullCreditNote()->execute(
                $this->requestString($request, 'public_id'),
                $this->requestString($request, 'reason')
            );

            $response = new AjaxResponse();
            $response->success = true;
            $response->redirect = $this->moduleUrl(
                'creditNote/' . rawurlencode($creditNote->getPublicId())
            );

            return $response;
        } catch (\Throwable $throwable) {
            return $this->errorResponse($throwable);
        }
    }

    public function creditNote($args = [])
    {
        try {
            $creditNote = AdminInvoiceFactory::createGetCreditNote()->execute($this->routeArgument($args));
        } catch (\Throwable $throwable) {
            return $this->invoices();
        }

        appendIconAndTitleToHeader(
            'fas fa-file-circle-minus',
            'Creditnota ' . $creditNote->getNumber(),
            'Administratie'
        );
        $this->registerAdminAssets();
        PageResponse::addAsset('Flexgrid/Modules/AdminInvoice/src/Templates/Editor/Css/Editor.scss');
        $viewModel = AdminInvoiceFactory::createCreditNotePresenter()->present($creditNote);
        $this->appendPageActions('credit', [
            'pdfUrl' => $this->moduleUrl('creditPdf/' . rawurlencode($creditNote->getPublicId())),
            'invoiceUrl' => $this->moduleUrl('detail/' . rawurlencode($creditNote->getOriginalInvoicePublicId())),
        ]);

        return new TemplateResponse('Flexgrid/Modules/AdminInvoice/src/Templates/CreditNote/Index.php', [
            'content' => (string)new TemplateResponse(
                'Flexgrid/Modules/AdminInvoice/src/Templates/CreditNote/Content.php',
                $viewModel
            ),
            'displayNumber' => $viewModel['displayNumber'],
            'pdfUrl' => $this->moduleUrl('creditPdf/' . rawurlencode($creditNote->getPublicId())),
            'invoiceUrl' => $this->moduleUrl(
                'detail/' . rawurlencode($creditNote->getOriginalInvoicePublicId())
            ),
            'overviewUrl' => $this->moduleUrl('invoices'),
        ]);
    }

    public function creditPdf($args = [])
    {
        try {
            $creditNote = AdminInvoiceFactory::createGetCreditNote()->execute($this->routeArgument($args));
            $document = AdminInvoiceFactory::createGenerateCreditNotePdf()->execute($creditNote);
        } catch (\Throwable $throwable) {
            return $this->invoices();
        }

        $this->streamPdf($document);
        return null;
    }

    public function updateDraft()
    {
        try {
            $request = new Request();
            $invoice = AdminInvoiceFactory::createUpdateDraftInvoice()->execute(new UpdateDraftInvoiceCommand(
                $this->requestString($request, 'public_id'),
                $this->requestString($request, 'issue_date'),
                $this->requestString($request, 'due_date'),
                $this->requestArray($request, 'customer'),
                $this->requestArray($request, 'address'),
                $this->requestString($request, 'customer_reference'),
                $this->requestString($request, 'notes')
            ));

            return $this->editorSuccessResponse($invoice);
        } catch (\Throwable $throwable) {
            return $this->errorResponse($throwable);
        }
    }

    public function refreshDraftCustomer(): AjaxResponse
    {
        try {
            $publicId = $this->requestString(new Request(), 'public_id');
            $invoice = AdminInvoiceFactory::createGetInvoice()->execute($publicId);
            $sourceId = $invoice->getCustomerSnapshot()->getSourcePublicId();
            $provider = $this->customerProvider();
            $selection = $sourceId !== '' && $provider !== null ? $provider->find($sourceId) : null;
            if ($selection === null) {
                throw new \DomainException('Geen actieve gekoppelde klant met factuuradres gevonden.');
            }
            $updated = AdminInvoiceFactory::createRefreshDraftInvoiceCustomer()->execute($publicId, $selection);
            $response = $this->editorSuccessResponse($updated);
            $response->notifications = ['<div class="notification notification--success" fade="2400">Klantgegevens in conceptfactuur vernieuwd.</div>'];
            return $response;
        } catch (\Throwable $throwable) {
            return $this->errorResponse($throwable);
        }
    }

    public function addLine()
    {
        try {
            $request = new Request();
            $taxChoice = explode('|', $this->requestString($request, 'tax_choice'), 2);
            $invoice = AdminInvoiceFactory::createAddInvoiceLine()->execute(new AddInvoiceLineCommand(
                $this->requestString($request, 'public_id'),
                $this->requestString($request, 'description'),
                $this->requestString($request, 'quantity'),
                $this->requestString($request, 'unit'),
                $this->requestString($request, 'unit_price'),
                (string)($taxChoice[0] ?? ''),
                (string)($taxChoice[1] ?? '')
            ));

            return $this->editorSuccessResponse($invoice);
        } catch (\Throwable $throwable) {
            return $this->errorResponse($throwable);
        }
    }

    public function addCatalogItem()
    {
        try {
            $request = new Request();
            $publicId = $this->requestString($request, 'public_id');
            $invoice = AdminInvoiceFactory::createGetInvoice()->execute($publicId);
            $line = AdminInvoiceFactory::createCatalog()->line(
                $this->requestString($request, 'catalog_item_public_id'),
                $invoice->getCurrency()->getCode()
            );
            $invoice = AdminInvoiceFactory::createAddInvoiceLine()->execute(new AddInvoiceLineCommand(
                $publicId,
                $line['description'],
                $line['quantity'],
                $line['unit'],
                $line['unit_price'],
                $line['tax_rate'],
                $line['tax_category'],
                $this->requestString($request, 'catalog_item_public_id')
            ));
            return $this->editorSuccessResponse($invoice);
        } catch (\Throwable $throwable) {
            return $this->errorResponse($throwable);
        }
    }

    public function removeLine()
    {
        try {
            $request = new Request();
            $invoice = AdminInvoiceFactory::createRemoveInvoiceLine()->execute(
                $this->requestString($request, 'public_id'),
                $this->requestString($request, 'line_id')
            );

            return $this->editorSuccessResponse($invoice);
        } catch (\Throwable $throwable) {
            return $this->errorResponse($throwable);
        }
    }

    public function updateLine()
    {
        try {
            $request = new Request();
            $taxChoice = explode('|', $this->requestString($request, 'tax_choice'), 2);
            $invoice = AdminInvoiceFactory::createUpdateInvoiceLine()->execute(new UpdateInvoiceLineCommand(
                $this->requestString($request, 'public_id'),
                $this->requestString($request, 'line_id'),
                $this->requestString($request, 'description'),
                $this->requestString($request, 'quantity'),
                $this->requestString($request, 'unit'),
                $this->requestString($request, 'unit_price'),
                (string)($taxChoice[0] ?? ''),
                (string)($taxChoice[1] ?? '')
            ));

            return $this->editorSuccessResponse($invoice);
        } catch (\Throwable $throwable) {
            return $this->errorResponse($throwable);
        }
    }

    public function refresh()
    {
        $query = $this->queryFromRequest();
        $response = new AjaxResponse();
        $response->success = true;
        $response->setContainer(
            '[data-admin-invoice-content]',
            (string)$this->renderContent($query)
        );
        $response->replaceUrl = $this->overviewUrl($query);

        return $response;
    }

    public function searchCustomers(): AjaxResponse
    {
        $query = substr($this->requestString(new Request(), 'q'), 0, 120);
        $provider = $this->customerProvider();
        $selections = $provider !== null && strlen($query) >= 2
            ? $provider->search($query, 10)
            : [];
        $response = new AjaxResponse();
        $response->success = true;
        $response->setContainer(
            '[data-admin-invoice-customer-results]',
            (string)new TemplateResponse(
                'Flexgrid/Modules/AdminInvoice/src/Templates/CustomerSelector/Results.php',
                ['query' => strlen($query) >= 2 ? $query : '', 'selections' => $selections]
            )
        );
        return $response;
    }

    private function renderContent(InvoiceListQuery $query): TemplateResponse
    {
        $data = AdminInvoiceFactory::createListInvoices()->execute($query);
        $viewModel = AdminInvoiceFactory::createOverviewPresenter()->present(
            $data,
            $this->refreshAction(),
            $this->moduleUrl('edit'),
            $this->moduleUrl('detail'),
            $this->moduleUrl('creditNote'),
            $this->ajaxAction('correctIssueDate'),
            $this->ajaxAction('deleteDraft')
        );

        return new TemplateResponse('Flexgrid/Modules/AdminInvoice/src/Templates/Invoices/Content.php', $viewModel);
    }

    private function queryFromRequest(): InvoiceListQuery
    {
        $request = new Request();
        $status = $this->requestString($request, 'status');
        $paymentStatus = $this->requestString($request, 'payment_status');
        $sort = $this->requestString($request, 'sort');
        $direction = strtolower($this->requestString($request, 'direction'));
        $period = \Flexgrid\Modules\AdminCore\Service\AdminPeriod::selected();
        $documentType = $this->requestString($request, 'document_type');
        $perPage = $request->getInt('per_page');

        return new InvoiceListQuery(
            substr($this->requestString($request, 'q'), 0, 120),
            in_array($status, InvoiceStatus::values(), true) ? $status : '',
            in_array($paymentStatus, PaymentStatus::values(), true) ? $paymentStatus : '',
            $period['year'],
            in_array($sort, InvoiceListQuery::sorts(), true) ? $sort : 'issue_date',
            in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc',
            max(1, $request->getInt('page')),
            in_array($perPage, InvoiceListQuery::pageSizes(), true) ? $perPage : 10,
            in_array($documentType, InvoiceListQuery::documentTypes(), true) ? $documentType : '',
            $period['quarter']
        );
    }

    private function requestString(Request $request, string $key): string
    {
        $value = $request->get($key, '');
        if (!is_string($value) && !is_int($value)) {
            return '';
        }

        return trim(strip_tags((string)$value));
    }

    private function requestArray(Request $request, string $key): array
    {
        $values = $request->get($key, []);
        if (!is_array($values)) {
            return [];
        }

        $clean = [];
        foreach ($values as $name => $value) {
            if ((is_string($name) || is_int($name)) && (is_string($value) || is_int($value))) {
                $clean[(string)$name] = trim(strip_tags((string)$value));
            }
        }

        return $clean;
    }

    private function routeArgument($args, int $position = 0): string
    {
        $value = is_array($args)
            ? ($args[$position] ?? '')
            : ($position === 0 ? $args : '');

        return is_string($value) || is_int($value)
            ? trim((string)$value)
            : '';
    }

    private function refreshAction(): string
    {
        $event = new AjaxEvent(self::class, 'refresh');
        $event->setMinimumAccessLevel(2);

        return $event->getName();
    }

    private function ajaxAction(string $method): string
    {
        $event = new AjaxEvent(self::class, $method);
        $event->setMinimumAccessLevel(2);

        return $event->getName();
    }

    private function editorActions(): array
    {
        return [
            'update' => $this->ajaxAction('updateDraft'),
            'add_line' => $this->ajaxAction('addLine'),
            'add_catalog_item' => $this->ajaxAction('addCatalogItem'),
            'update_line' => $this->ajaxAction('updateLine'),
            'remove_line' => $this->ajaxAction('removeLine'),
            'finalize' => $this->ajaxAction('finalize'),
            'refresh_customer' => $this->ajaxAction('refreshDraftCustomer'),
        ];
    }

    private function renderDetail(Invoice $invoice): TemplateResponse
    {
        appendIconAndTitleToHeader(
            'fas fa-file-invoice',
            'Factuur ' . (string)$invoice->getInvoiceNumber(),
            'Administratie'
        );
        $this->registerAdminAssets();
        PageResponse::addAsset('Flexgrid/Modules/AdminInvoice/src/Templates/Editor/Css/Editor.scss');
        PageResponse::addAsset('Flexgrid/Modules/AdminInvoice/src/Templates/Detail/Js/CreditNote.js');
        $viewModel = AdminInvoiceFactory::createEditorPresenter()->present($invoice, []);
        $viewModel['correctIssueDateAction'] = $this->ajaxAction('correctIssueDate');
        $viewModel['sourceQuoteUrl'] = $this->sourceQuoteUrl($invoice);
        $totals = $invoice->getFinalizedTotals();
        if ($totals === null || $invoice->getInvoiceNumber() === null) {
            throw new \LogicException('Een definitief factuurdetail vereist vastgezette totalen en een nummer.');
        }
        $detailExtensions = (new InvoiceDetailExtensionLoader(dirname(__DIR__, 3)))->render(
            new InvoiceDetailContext(
                $invoice->getPublicId(),
                $invoice->getInvoiceNumber(),
                $invoice->getStatus()->getValue(),
                $invoice->getPaymentStatus()->getValue(),
                $totals->getGross()
            )
        );
        $dispatches = AdminInvoiceFactory::createDispatchService()->history($invoice->getPublicId());
        $sentInvoice = false;
        $latestInvoice = null;
        $latestReminder = null;
        foreach ($dispatches as $dispatch) {
            $sentInvoice = $sentInvoice || ($dispatch['kind'] === 'invoice' && $dispatch['status'] === 'sent');
            if ($dispatch['kind'] === 'invoice') {
                $latestInvoice = $dispatch;
            } elseif ($dispatch['kind'] === 'reminder') {
                $latestReminder = $dispatch;
            }
        }

        $invoiceInProgress = $latestInvoice !== null && $latestInvoice['status'] === 'pending'
            && (int)$latestInvoice['updated_at'] > time() - 60;
        $reminderInProgress = $latestReminder !== null && $latestReminder['status'] === 'pending'
            && (int)$latestReminder['updated_at'] > time() - 60;
        $finalized = $invoice->getStatus()->getValue() === InvoiceStatus::FINALIZED;
        $this->appendPageActions('detail', [
            'pdfUrl' => $this->moduleUrl('pdf/' . rawurlencode($invoice->getPublicId())),
            'canCredit' => $invoice->getStatus()->getValue() === InvoiceStatus::FINALIZED,
        ]);

        return new TemplateResponse('Flexgrid/Modules/AdminInvoice/src/Templates/Detail/Index.php', [
            'content' => (string)new TemplateResponse(
                'Flexgrid/Modules/AdminInvoice/src/Templates/Detail/Content.php',
                $viewModel
            ),
            'displayNumber' => $viewModel['displayNumber'],
            'statusLabel' => $viewModel['statusLabel'],
            'pdfUrl' => $this->moduleUrl('pdf/' . rawurlencode($invoice->getPublicId())),
            'canCredit' => $invoice->getStatus()->getValue() === InvoiceStatus::FINALIZED,
            'creditAction' => $this->ajaxAction('credit'),
            'publicId' => $invoice->getPublicId(),
            'overviewUrl' => $this->moduleUrl('invoices'),
            'detailExtensions' => $detailExtensions,
            'dispatchPanel' => (string)new TemplateResponse(
                'Flexgrid/Modules/AdminInvoice/src/Templates/Detail/DispatchPanel.php', [
                    'history' => $dispatches,
                    'dispatchAction' => $this->ajaxAction('dispatch'),
                    'publicId' => $invoice->getPublicId(),
                    'recipient' => (string)($invoice->getCustomerSnapshot()->toArray()['email'] ?? ''),
                    'mailCatchAll' => defined('__MAIL_CATCH_ALL__')
                        ? trim((string)constant('__MAIL_CATCH_ALL__')) : '',
                    'canSend' => $finalized && !$invoiceInProgress,
                    'invoiceRepeat' => $latestInvoice !== null,
                    'canRemind' => $sentInvoice && !$reminderInProgress && $finalized
                        && $invoice->getDueDate()->getValue() < date('Y-m-d')
                        && in_array($invoice->getPaymentStatus()->getValue(),
                            [PaymentStatus::UNPAID, PaymentStatus::PARTIALLY_PAID], true),
                    'reminderRepeat' => $latestReminder !== null,
                    'mailInProgress' => $invoiceInProgress || $reminderInProgress,
                ]
            ),
        ]);
    }

    private function streamPdf(PdfDocument $document): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: ' . $document->getMimeType());
        header('Content-Disposition: inline; filename="' . $document->getFileName() . '"');
        header('Content-Length: ' . (string)$document->getSize());
        header('Cache-Control: private, no-store, max-age=0');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        echo $document->getContent();
        exit;
    }

    private function editorSuccessResponse(Invoice $invoice): AjaxResponse
    {
        $viewModel = AdminInvoiceFactory::createEditorPresenter()->present($invoice, $this->editorActions());
        $viewModel['customerSelector'] = $this->renderCustomerSelector($viewModel['customer']);
        $viewModel['customerEditUrl'] = $this->draftCustomerEditUrl($invoice);
        $viewModel['sourceQuoteUrl'] = $this->sourceQuoteUrl($invoice);
        $viewModel['catalogItems'] = AdminInvoiceFactory::createCatalog()->allActiveLines();
        $response = new AjaxResponse();
        $response->success = true;
        $response->notifications = [
            '<div class="notification notification--success" fade="2400">Factuur opgeslagen.</div>',
        ];
        $response->setContainer(
            '[data-admin-invoice-editor]',
            (string)new TemplateResponse(
                'Flexgrid/Modules/AdminInvoice/src/Templates/Editor/Content.php',
                $viewModel
            )
        );
        $response->setContainer(
            '[data-admin-invoice-finalize]',
            (string)new TemplateResponse('Flexgrid/Modules/AdminInvoice/src/Templates/Editor/FinalizeButton.php', [
                'finalizeAction' => $viewModel['actions']['finalize'],
                'publicId' => $viewModel['publicId'],
                'canFinalize' => $viewModel['canFinalize'],
            ]),
            true
        );
        $response->setContainer(
            '[data-admin-invoice-preview]',
            (string)new TemplateResponse('Flexgrid/Modules/AdminInvoice/src/Templates/Editor/PreviewButton.php', [
                'previewUrl' => $this->moduleUrl('previewPdf/' . rawurlencode($invoice->getPublicId())),
            ]),
            true
        );

        return $response;
    }

    private function renderCustomerSelector(array $currentCustomer): string
    {
        if ($this->customerProvider() === null) {
            return '';
        }
        return (string)new TemplateResponse(
            'Flexgrid/Modules/AdminInvoice/src/Templates/CustomerSelector/Selector.php',
            [
                'searchAction' => $this->ajaxAction('searchCustomers'),
                'currentCustomer' => $currentCustomer,
            ]
        );
    }

    private function draftCustomerEditUrl(Invoice $invoice): string
    {
        $source = $invoice->getCustomerSnapshot()->getSourcePublicId();
        return $invoice->getStatus()->getValue() === InvoiceStatus::DRAFT && $source !== ''
            ? rtrim(__DOMAIN__, '/') . '/Flexgrid/AdminCustomer/edit/' . rawurlencode($source)
            : '';
    }

    private function sourceQuoteUrl(Invoice $invoice): string
    {
        if ($invoice->getSource() !== 'admin_quote' || $invoice->getExternalId() === '') {
            return '';
        }
        $parts = explode(':', $invoice->getExternalId(), 2);
        return rtrim(__DOMAIN__, '/') . '/Flexgrid/AdminQuote/detail/' . rawurlencode($parts[0]);
    }

    private function customerProvider()
    {
        return (new InvoiceCustomerProviderLoader(dirname(__DIR__, 3)))->getProvider();
    }

    private function errorResponse(\Throwable $throwable): AjaxResponse
    {
        $response = new AjaxResponse();
        $response->success = false;
        $message = $throwable instanceof \InvalidArgumentException
            || $throwable instanceof \DomainException
            || $throwable instanceof \Flexgrid\Modules\AdminInvoice\Exception\InvoiceNotFoundException
            || $throwable instanceof \Flexgrid\Modules\AdminInvoice\Exception\CreditNoteNotFoundException
            ? $throwable->getMessage()
            : 'De factuur kon niet worden opgeslagen.';
        $response->error = $message;
        $response->notifications = [
            '<div class="notification notification--error" fade="6000">'
            . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</div>',
        ];

        return $response;
    }

    private function appendPageActions(string $mode, array $extra = []): void
    {
        \Flexgrid\Modules\AdminCore\Service\AdminHeader::AdminAddHeader([new TemplateResponse(
            'Flexgrid/Modules/AdminInvoice/src/Templates/PageActions.php',
            $extra + ['mode' => $mode, 'overviewUrl' => $this->moduleUrl('invoices')]
        )]);
    }

    private function registerAdminAssets(bool $editor = false): void
    {
        PageResponse::addAsset('Flexgrid/Flexgrid/src/Html/Admin/Css/AdminUi.scss');
        if ($editor) {
            PageResponse::addAsset('Flexgrid/Modules/AdminInvoice/src/Templates/Editor/Css/Editor.scss');
            PageResponse::addAsset('Flexgrid/Modules/AdminInvoice/src/Templates/Editor/Js/Editor.js');
            PageResponse::addAsset('Flexgrid/Modules/AdminInvoice/src/Templates/CustomerSelector/Css/CustomerSelector.scss');
            PageResponse::addAsset('Flexgrid/Modules/AdminInvoice/src/Templates/CustomerSelector/Js/CustomerSelector.js');
        }
    }

    private function moduleUrl(string $path): string
    {
        return rtrim(__DOMAIN__, '/') . '/Flexgrid/AdminInvoice/' . ltrim($path, '/');
    }

    private function overviewUrl(InvoiceListQuery $query): string
    {
        $parameters = array_filter([
            'q' => $query->getSearch(),
            'status' => $query->getStatus(),
            'payment_status' => $query->getPaymentStatus(),
            'sort' => $query->getSort(),
            'direction' => $query->getDirection(),
            'page' => $query->getPage(),
            'per_page' => $query->getPerPage(),
            'document_type' => $query->getDocumentType(),
        ], function ($value): bool {
            return $value !== '' && $value !== null;
        });

        return __DOMAIN__ . '/Flexgrid/AdminInvoice/invoices?' . http_build_query($parameters);
    }
}
