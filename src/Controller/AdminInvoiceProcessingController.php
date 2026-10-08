<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Controller;

use Flexgrid\Event\AjaxEvent;
use Flexgrid\Modules\AdminCore\Service\AdminHeader;
use Flexgrid\Modules\AdminInvoice\Service\AdminInvoiceFactory;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceProcessingSettings;
use Flexgrid\Response\AjaxResponse;
use Flexgrid\Response\PageResponse;
use Flexgrid\Response\TemplateResponse;
use Flexgrid\Utils\Request\Request;

/**
 * @FG\Controller [name=AdminInvoiceProcessing,type=Module,icon=fas fa-gears,level=5,administrationPanel=true,administrationLabel=Factuurverwerking,administrationRoute=index,administrationPriority=44]
 */
final class AdminInvoiceProcessingController
{
    public function index()
    {
        appendIconAndTitleToHeader('fas fa-gears', 'Factuurverwerking', 'Administratie');
        PageResponse::addAsset('Flexgrid/Flexgrid/src/Html/Admin/Css/AdminUi.scss');
        AdminHeader::AdminAddHeader([
            ['href' => '/Flexgrid/AdminInvoice/invoices', 'label' => 'Facturen'],
            ['href' => '/Flexgrid/AdminRecurringInvoice/plans', 'label' => 'Herhaalschema’s'],
        ]);
        $actions = [];
        foreach (['save', 'run', 'resume'] as $method) {
            $event = new AjaxEvent(self::class, $method); $event->setMinimumAccessLevel(5);
            $actions[$method] = $event->getName();
        }
        $tableRows = [];
        if (AdminInvoiceFactory::processingReady()) {
            foreach (AdminInvoiceFactory::createProcessing()->overview() as $row) {
                $party = json_decode((string)$row['customer_snapshot'], true);
                $url = '/Flexgrid/AdminInvoice/' . ($row['invoice_status'] === 'draft' ? 'edit/' : 'detail/') . rawurlencode($row['invoice_public_id']);
                $labels = ['review' => $row['invoice_status'] === 'draft' ? 'Ter controle' : 'Klaar voor verzending',
                    'processing' => 'Verwerking loopt / controleren', 'sent' => 'Verstuurd', 'failed' => 'Controle nodig'];
                $tableRows[] = ['id' => $row['invoice_public_id'], 'url' => $url, 'cells' => [
                    'invoice' => $row['invoice_number'] ?: 'Concept', 'customer' => $party['name'] ?? '',
                    'status' => $labels[$row['status']] ?? 'Controle nodig', 'error' => $row['error'] ?? ''],
                    'actions' => in_array($row['status'], ['failed', 'processing'], true) ? [[
                        'tag' => 'button', 'label' => 'Verwerking hervatten', 'icon' => 'fas fa-rotate-right',
                        'attributes' => ['ajax' => 'true', 'action' => $actions['resume'], 'invoice_public_id' => $row['invoice_public_id'],
                            'alert' => 'Verwerking hervatten na controle? Mislukte e-mails worden niet automatisch opnieuw verstuurd.'],
                    ]] : []];
            }
        }
        return new TemplateResponse('Flexgrid/Modules/AdminInvoice/src/Templates/Processing/Index.php',
            ['automatic' => InvoiceProcessingSettings::automaticFinalization(), 'actions' => $actions,
                'ready' => AdminInvoiceFactory::processingReady(), 'table' => ['id' => 'invoice-processing',
                    'label' => 'Factuurverwerking', 'columns' => [['key' => 'invoice', 'label' => 'Factuur'], ['key' => 'customer', 'label' => 'Klant'],
                        ['key' => 'status', 'label' => 'Verwerking'], ['key' => 'error', 'label' => 'Aandacht']],
                    'rows' => $tableRows, 'empty' => ['title' => 'Nog geen facturen in verwerking', 'message' => 'Nieuwe concepten uit herhaalschema’s verschijnen hier.']]]);
    }

    public function save(): AjaxResponse
    {
        if ((new Request())->getBoolean('automatic') && !AdminInvoiceFactory::processingReady()) {
            $response = new AjaxResponse(); $response->success = false;
            $response->notifications = ['<div class="notification notification--error">Installeer eerst de factuurverwerking.</div>'];
            return $response;
        }
        InvoiceProcessingSettings::save((new Request())->getBoolean('automatic'));
        $response = new AjaxResponse(); $response->success = true;
        $response->redirect = '/Flexgrid/AdminInvoiceProcessing/index';
        return $response;
    }

    public function resume(): AjaxResponse
    {
        try {
            AdminInvoiceFactory::createProcessing()->resume((new Request())->getString('invoice_public_id'));
            $response = new AjaxResponse(); $response->success = true; $response->redirect = '/Flexgrid/AdminInvoiceProcessing/index';
        } catch (\Throwable $exception) {
            $response = new AjaxResponse(); $response->success = false;
            $response->notifications = ['<div class="notification notification--error">' . htmlspecialchars($exception->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>'];
        }
        return $response;
    }

    public function run(): AjaxResponse
    {
        try {
            $result = AdminInvoiceFactory::createProcessing()->run();
            $response = new AjaxResponse(); $response->success = $result['failed'] === 0;
            $message = $result['sent'] . ' facturen verstuurd; ' . $result['review'] . ' concepten wachten op controle; '
                . $result['failed'] . ' facturen vereisen aandacht.';
            $response->notifications = ['<div class="notification">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</div>'];
            $response->redirect = '/Flexgrid/AdminInvoice/invoices';
            return $response;
        } catch (\Throwable $exception) {
            $response = new AjaxResponse(); $response->success = false;
            $response->notifications = ['<div class="notification notification--error">Factuurverwerking kon niet worden gestart. Controleer de installatie en de factuurstatus.</div>'];
            return $response;
        }
    }
}
