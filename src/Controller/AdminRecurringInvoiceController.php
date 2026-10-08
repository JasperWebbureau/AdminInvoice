<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Controller;

use Flexgrid\Event\AjaxEvent;
use Flexgrid\Flexgrid;
use Flexgrid\Html\Table\TrustedHtml;
use Flexgrid\Modules\AdminInvoice\Service\AdminInvoiceFactory;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceCustomerProviderLoader;
use Flexgrid\Response\AjaxResponse;
use Flexgrid\Response\PageResponse;
use Flexgrid\Response\TemplateResponse;
use Flexgrid\Utils\Request\Request;

/**
 * @FG\Controller [name=AdminRecurringInvoice,type=Module,icon=fas fa-repeat,level=2,administrationPanel=true,administrationLabel="Herhaalschema's",administrationRoute=plans,administrationPriority=43]
 */
final class AdminRecurringInvoiceController
{
    public function index()
    {
        return $this->plans();
    }

    public function plans()
    {
        return $this->render('', false);
    }

    public function create()
    {
        return $this->render('', true);
    }

    public function edit($args = [])
    {
        $id = is_array($args) ? (string)($args[0] ?? '') : (string)$args;
        return $this->render($id, true);
    }

    public function preview()
    {
        appendIconAndTitleToHeader('fas fa-magnifying-glass-chart', 'Proefrun herhaalschema’s', 'Administratie');
        PageResponse::addAsset('Flexgrid/Flexgrid/src/Html/Admin/Css/AdminUi.scss');
        PageResponse::addAsset('Flexgrid/Flexgrid/src/Html/Table/Css/Table.scss');
        PageResponse::addAsset('Flexgrid/Flexgrid/src/Html/Table/Js/Table.js');
        \Flexgrid\Modules\AdminCore\Service\AdminHeader::AdminAddHeader([new TemplateResponse(
            'Flexgrid/Modules/AdminInvoice/src/Templates/Recurring/PreviewHeaderActions.php'
        )]);
        $report = AdminInvoiceFactory::createRecurringInvoices()->previewDue(date('Y-m-d'));
        return new TemplateResponse('Flexgrid/Modules/AdminInvoice/src/Templates/Recurring/Preview.php', [
            'report' => $report,
            'previewTable' => $this->previewTable($report['rows'] ?? []),
            'runAction' => $this->action('runDue'),
            'plansUrl' => '/Flexgrid/AdminRecurringInvoice/plans',
            'invoicesUrl' => '/Flexgrid/AdminInvoice/invoices?status=draft',
        ]);
    }

    public function save(): AjaxResponse
    {
        try {
            $request = new Request();
            $customer = $request->get('customer', []);
            AdminInvoiceFactory::createRecurringInvoices()->save([
                'public_id' => $request->getString('public_id'),
                'name' => $request->getString('name'),
                'customer_public_id' => is_array($customer)
                    ? trim((string)($customer['source_public_id'] ?? '')) : '',
                'catalog_item_public_id' => $request->getString('catalog_item_public_id'),
                'next_issue_date' => $request->getString('next_issue_date'),
                'frequency_months' => $request->getInt('frequency_months'),
                'due_days' => $request->getInt('due_days'),
                'active' => $request->getBoolean('active'),
            ]);
            $response = new AjaxResponse();
            $response->success = true;
            $response->redirect = '/Flexgrid/AdminRecurringInvoice/plans';
            return $response;
        } catch (\Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function runDue(): AjaxResponse
    {
        try {
            $result = AdminInvoiceFactory::createRecurringInvoices()->runDue(date('Y-m-d'));
            $response = new AjaxResponse();
            $response->success = true;
            $response->notifications = [
                '<div class="notification notification--success" fade="5000">'
                . (int)$result['created'] . ' factuurconcept(en) verwerkt; '
                . (int)$result['skipped'] . ' periode(n) zonder factuurregel overgeslagen; '
                . (int)$result['failed'] . ' fout(en). Controleer de klantkoppelingen.</div>',
            ];
            $response->redirect = '/Flexgrid/AdminRecurringInvoice/plans';
            return $response;
        } catch (\Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function toggle(): AjaxResponse
    {
        try {
            $request = new Request();
            AdminInvoiceFactory::createRecurringInvoices()->setActive(
                $request->getString('public_id'), $request->getBoolean('active')
            );
            $response = new AjaxResponse();
            $response->success = true;
            $response->redirect = '/Flexgrid/AdminRecurringInvoice/plans';
            return $response;
        } catch (\Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function enrollCustomer(): AjaxResponse
    {
        try {
            $request = new Request();
            $customerId = $request->getString('customer_public_id');
            AdminInvoiceFactory::createRecurringInvoices()->enroll(
                $request->getString('plan_public_id'), $customerId
            );
            $response = new AjaxResponse();
            $response->success = true;
            $response->redirect = '/Flexgrid/AdminCustomer/edit/' . rawurlencode($customerId);
            return $response;
        } catch (\Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function enrollFromPlan(): AjaxResponse
    {
        try {
            $request = new Request();
            $customer = $request->get('customer', []);
            $customerId = is_array($customer)
                ? trim((string)($customer['source_public_id'] ?? '')) : '';
            if ($customerId === '') {
                throw new \InvalidArgumentException('Zoek en selecteer eerst een klant.');
            }
            $planId = $request->getString('plan_public_id');
            AdminInvoiceFactory::createRecurringInvoices()->enroll($planId, $customerId);
            $response = new AjaxResponse();
            $response->success = true;
            $response->redirect = '/Flexgrid/AdminRecurringInvoice/edit/' . rawurlencode($planId);
            return $response;
        } catch (\Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function unlinkCustomer(): AjaxResponse
    {
        try {
            $request = new Request();
            $planId = $request->getString('plan_public_id');
            AdminInvoiceFactory::createRecurringInvoices()->unlinkCustomer(
                $planId, $request->getString('customer_public_id')
            );
            $response = new AjaxResponse();
            $response->success = true;
            $response->redirect = '/Flexgrid/AdminRecurringInvoice/edit/' . rawurlencode($planId);
            return $response;
        } catch (\Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function toggleCustomer(): AjaxResponse
    {
        try {
            $request = new Request();
            $customerId = $request->getString('customer_public_id');
            AdminInvoiceFactory::createRecurringInvoices()->setMemberActive(
                $request->getString('plan_public_id'), $customerId, $request->getBoolean('active')
            );
            $response = new AjaxResponse();
            $response->success = true;
            $response->redirect = '/Flexgrid/AdminCustomer/edit/' . rawurlencode($customerId);
            return $response;
        } catch (\Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function addCustomerItem(): AjaxResponse
    {
        try {
            $request = new Request();
            $customerId = $request->getString('customer_public_id');
            AdminInvoiceFactory::createRecurringInvoices()->addItem(
                $request->getString('plan_public_id'), $customerId,
                $request->getString('catalog_item_public_id')
            );
            $response = new AjaxResponse();
            $response->success = true;
            $response->redirect = '/Flexgrid/AdminCustomer/edit/' . rawurlencode($customerId);
            return $response;
        } catch (\Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function toggleCustomerItem(): AjaxResponse
    {
        try {
            $request = new Request();
            $customerId = $request->getString('customer_public_id');
            AdminInvoiceFactory::createRecurringInvoices()->setItemActive(
                $request->getString('plan_public_id'), $customerId,
                $request->getString('catalog_item_public_id'), $request->getBoolean('active')
            );
            $response = new AjaxResponse();
            $response->success = true;
            $response->redirect = '/Flexgrid/AdminCustomer/edit/' . rawurlencode($customerId);
            return $response;
        } catch (\Throwable $exception) {
            return $this->error($exception);
        }
    }

    private function render(string $publicId, bool $showEditor)
    {
        appendIconAndTitleToHeader('fas fa-repeat', 'Terugkerende facturen', 'Administratie');
        PageResponse::addAsset('Flexgrid/Flexgrid/src/Html/Admin/Css/AdminUi.scss');
        PageResponse::addAsset('Flexgrid/Flexgrid/src/Html/Table/Css/Table.scss');
        PageResponse::addAsset('Flexgrid/Flexgrid/src/Html/Table/Js/Table.js');
        \Flexgrid\Modules\AdminCore\Service\AdminHeader::AdminAddHeader([new TemplateResponse(
            'Flexgrid/Modules/AdminInvoice/src/Templates/Recurring/HeaderActions.php',
            ['showEditor' => $showEditor]
        )]);
        PageResponse::addAsset('Flexgrid/Modules/AdminInvoice/src/Templates/CustomerSelector/Css/CustomerSelector.scss');
        PageResponse::addAsset('Flexgrid/Modules/AdminInvoice/src/Templates/CustomerSelector/Js/CustomerSelector.js');
        $plans = AdminInvoiceFactory::createRecurringInvoices();
        $catalog = AdminInvoiceFactory::createCatalog();
        $current = $publicId !== '' ? $plans->find($publicId) : null;
        $selectedCatalogItemId = $current !== null
            ? (string)$current['catalog_item_public_id']
            : (new Request())->getString('catalog_item_id');
        $provider = (new InvoiceCustomerProviderLoader(dirname(__DIR__, 3)))->getProvider();
        $legacy = $current !== null && (string)$current['customer_public_id'] !== '';
        $requestedCustomerId = $legacy
            ? (string)$current['customer_public_id']
            : '';
        $selected = $provider !== null && $requestedCustomerId !== ''
            ? $provider->find($requestedCustomerId) : null;
        if ($current !== null && $selected === null) {
            $current['customer_public_id'] = '';
        }
        $party = $selected !== null ? $selected->getParty() : [];
        $party['source_public_id'] = $selected !== null ? $selected->getPublicId() : '';
        $linkedCustomers = [];
        if ($current !== null && !$legacy) {
            foreach ($plans->membersForPlan($publicId) as $member) {
                $customerId = (string)$member['customer_public_id'];
                $linked = $provider !== null ? $provider->find($customerId) : null;
                $partyForMember = $linked !== null ? $linked->getParty() : [];
                $member['customer_name'] = trim((string)($partyForMember['name'] ?? ''));
                if ($member['customer_name'] === '') {
                    $member['customer_name'] = 'Klant niet beschikbaar';
                }
                $member['active_item_count'] = count(array_filter(
                    $plans->itemsForCustomerPlan($publicId, $customerId),
                    static function (array $item): bool { return (int)$item['active'] === 1; }
                ));
                $linkedCustomers[] = $member;
            }
            usort($linkedCustomers, static function (array $a, array $b): int {
                return strcasecmp((string)$a['customer_name'], (string)$b['customer_name']);
            });
        }
        $rows = $plans->all();
        $memberCounts = $plans->memberCounts();
        $itemsById = [];
        foreach ($catalog->all() as $item) {
            $itemsById[$item['public_id']] = $item['name'];
        }
        foreach ($rows as &$row) {
            $isLegacy = (string)$row['customer_public_id'] !== '';
            $customer = $isLegacy && $provider !== null
                ? $provider->find((string)$row['customer_public_id']) : null;
            $row['customer_name'] = $isLegacy
                ? ($customer !== null ? (string)($customer->getParty()['name'] ?? '') : 'Klant niet beschikbaar')
                : (string)(int)($memberCounts[$row['public_id']]['active_total'] ?? 0) . ' actieve klanten';
            $row['member_error_count'] = $isLegacy
                ? 0 : (int)($memberCounts[$row['public_id']]['error_total'] ?? 0);
            $row['item_name'] = $isLegacy
                ? (string)($itemsById[$row['catalog_item_public_id']] ?? 'Item niet beschikbaar')
                : 'Per klant ingesteld';
        }
        unset($row);
        return new TemplateResponse('Flexgrid/Modules/AdminInvoice/src/Templates/Recurring/Index.php', [
            'showEditor' => $showEditor,
            'plans' => $rows,
            'planTable' => $this->planTable($rows),
            'linkedCustomerTable' => $this->linkedCustomerTable($linkedCustomers, $publicId),
            'items' => $catalog->allActiveLines(),
            'current' => $current,
            'linkedCustomers' => $linkedCustomers,
            'legacy' => $legacy,
            'selectedCustomerId' => $selected !== null ? $selected->getPublicId() : '',
            'selectedCatalogItemId' => $selectedCatalogItemId,
            'customerSelector' => $provider !== null ? (string)new TemplateResponse(
                'Flexgrid/Modules/AdminInvoice/src/Templates/CustomerSelector/Selector.php', [
                    'searchAction' => $this->customerSearchAction(),
                    'currentCustomer' => $party,
                ]
            ) : '',
            'saveAction' => $this->action('save'),
            'runAction' => $this->action('runDue'),
            'previewUrl' => '/Flexgrid/AdminRecurringInvoice/preview',
            'toggleAction' => $this->action('toggle'),
            'enrollFromPlanAction' => $this->action('enrollFromPlan'),
            'unlinkCustomerAction' => $this->action('unlinkCustomer'),
            'customerSearchAction' => $this->customerSearchAction(),
        ]);
    }

    private function planTable(array $plans): array
    {
        $rows = [];
        $toggleAction = $this->action('toggle');
        foreach ($plans as $plan) {
            $id = (string)$plan['public_id'];
            $active = (int)$plan['active'] === 1;
            $error = trim((string)($plan['last_error'] ?? ''));
            $memberErrors = (int)($plan['member_error_count'] ?? 0);
            $customerNote = (string)$plan['customer_public_id'] !== '' ? 'Individueel schema'
                : ($memberErrors > 0 ? $memberErrors . ' fout(en) bij klantkoppelingen' : '');
            $toggle = '<button class="button button-secondary button-small" type="button" ajax="true" action="'
                . $this->escape($toggleAction) . '" public_id="' . $this->escape($id)
                . '" active="' . ($active ? '0' : '1') . '" use-waiting-icon>'
                . ($active ? 'Pauzeren' : 'Hervatten') . '</button>';
            $rows[] = [
                'id' => $id,
                'cells' => [
                    'name' => ['value' => (string)$plan['name'], 'title' => true,
                        'secondary' => $error !== '' ? 'Laatste fout: ' . $error : ''],
                    'customers' => ['value' => (string)($plan['customer_name'] ?? ''), 'secondary' => $customerNote],
                    'item' => (string)($plan['item_name'] ?? 'Per klant ingesteld'),
                    'date' => (string)$plan['next_issue_date'],
                    'frequency' => [1 => 'Maandelijks', 3 => 'Per kwartaal', 12 => 'Jaarlijks'][(int)$plan['frequency_months']] ?? 'Onbekend',
                    'status' => ['value' => $active ? 'Actief' : 'Gepauzeerd', 'badge' => $active ? 'success' : 'warning'],
                ],
                'actions' => [
                    ['href' => '/Flexgrid/AdminRecurringInvoice/edit/' . rawurlencode($id), 'label' => 'Bewerken', 'show_label' => true],
                    new TrustedHtml($toggle),
                ],
            ];
        }
        return [
            'id' => 'admin-recurring-plans', 'label' => 'Geplande facturen',
            'columns' => [
                ['key' => 'name', 'label' => 'Schema'], ['key' => 'customers', 'label' => 'Klanten'],
                ['key' => 'item', 'label' => 'Inhoud'], ['key' => 'date', 'label' => 'Start / ritme'],
                ['key' => 'frequency', 'label' => 'Herhaling'], ['key' => 'status', 'label' => 'Status'],
            ],
            'rows' => $rows,
            'empty' => ['title' => 'Nog geen herhaalschema’s', 'message' => 'Maak het eerste herhaalschema aan.', 'icon' => 'fas fa-repeat'],
        ];
    }

    private function linkedCustomerTable(array $customers, string $planId): array
    {
        $rows = [];
        $unlinkAction = $this->action('unlinkCustomer');
        foreach ($customers as $member) {
            $customerId = (string)$member['customer_public_id'];
            $active = (int)$member['active'] === 1;
            $error = trim((string)($member['last_error'] ?? ''));
            $unlink = '<button class="button button-secondary button-small" type="button" ajax="true" action="'
                . $this->escape($unlinkAction) . '" plan_public_id="' . $this->escape($planId)
                . '" customer_public_id="' . $this->escape($customerId)
                . '" alert="Deze klant van het schema ontkoppelen? De items van deze klant binnen dit schema worden verwijderd. Bestaande facturen blijven staan." use-waiting-icon>Ontkoppelen</button>';
            $rows[] = [
                'id' => $customerId,
                'cells' => [
                    'customer' => ['value' => (string)$member['customer_name'], 'title' => true,
                        'secondary' => $member['customer_name'] === 'Klant niet beschikbaar' ? $customerId : ''],
                    'items' => ['value' => (int)$member['active_item_count'] . ' actief'
                        . ((int)$member['active_item_count'] === 1 ? ' item' : ' items'),
                        'secondary' => (int)$member['active_item_count'] === 0 ? 'Controleer automatische regels in de proefrun.' : ''],
                    'date' => (string)$member['next_issue_date'],
                    'status' => ['value' => $active ? 'Actief' : 'Gepauzeerd',
                        'badge' => $active ? 'success' : 'warning',
                        'secondary' => $error !== '' ? 'Fout: ' . $error : ''],
                ],
                'actions' => [
                    ['href' => '/Flexgrid/AdminCustomer/edit/' . rawurlencode($customerId),
                        'label' => 'Klant bewerken: ' . (string)$member['customer_name'], 'icon' => 'fas fa-pen'],
                    new TrustedHtml($unlink),
                ],
            ];
        }
        return [
            'id' => 'admin-recurring-customers', 'label' => 'Gekoppelde klanten',
            'columns' => [
                ['key' => 'customer', 'label' => 'Klant'], ['key' => 'items', 'label' => 'Items'],
                ['key' => 'date', 'label' => 'Volgende factuurdatum'], ['key' => 'status', 'label' => 'Status'],
            ],
            'rows' => $rows,
            'empty' => ['title' => 'Geen klanten gekoppeld', 'message' => 'Zoek hieronder een klant om te beginnen.', 'icon' => 'fas fa-users'],
        ];
    }

    private function previewTable(array $previewRows): array
    {
        $rows = [];
        foreach ($previewRows as $index => $row) {
            $lines = $row['lines'] ?? [];
            $rows[] = [
                'id' => (string)$index,
                'cells' => [
                    'date' => (string)$row['issue_date'],
                    'plan' => ['value' => (string)$row['plan_name'],
                        'secondary' => (string)($row['customer_name'] ?: $row['customer_id'])],
                    'lines' => $lines !== [] ? implode(' · ', array_map('strval', $lines)) : (string)$row['message'],
                    'gross' => $row['gross'] !== '' ? (string)$row['currency'] . ' ' . (string)$row['gross'] : '—',
                    'status' => ['value' => ['ready' => 'Klaar', 'skipped' => 'Overgeslagen', 'failed' => 'Fout'][(string)$row['status']] ?? (string)$row['status'],
                        'badge' => ['ready' => 'success', 'skipped' => 'neutral', 'failed' => 'warning'][(string)$row['status']] ?? 'neutral'],
                ],
            ];
        }
        return [
            'id' => 'admin-recurring-preview', 'label' => 'Uitkomst van de proefrun',
            'columns' => [
                ['key' => 'date', 'label' => 'Factuurdatum'], ['key' => 'plan', 'label' => 'Schema / klant'],
                ['key' => 'lines', 'label' => 'Regels of uitleg'],
                ['key' => 'gross', 'label' => 'Verwacht totaal incl. btw'], ['key' => 'status', 'label' => 'Resultaat'],
            ],
            'rows' => $rows,
            'empty' => ['title' => 'Niets aan de beurt', 'message' => 'Er zijn vandaag geen actieve schema’s of klantkoppelingen aan de beurt.', 'icon' => 'fas fa-calendar-check'],
        ];
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function customerSearchAction(): string
    {
        $event = new AjaxEvent(AdminInvoiceController::class, 'searchCustomers');
        $event->setMinimumAccessLevel(2);
        return $event->getName();
    }

    private function action(string $method): string
    {
        $event = new AjaxEvent(self::class, $method);
        $event->setMinimumAccessLevel(2);
        return $event->getName();
    }

    private function error(\Throwable $exception): AjaxResponse
    {
        $response = new AjaxResponse();
        $response->success = false;
        $response->notifications = [
            '<div class="notification notification--error">'
            . htmlspecialchars($exception->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>',
        ];
        return $response;
    }
}
