<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Controller;

use Flexgrid\Event\AjaxEvent;
use Flexgrid\Flexgrid;
use Flexgrid\Modules\AdminInvoice\Service\AdminInvoiceFactory;
use Flexgrid\Response\AjaxResponse;
use Flexgrid\Response\PageResponse;
use Flexgrid\Response\TemplateResponse;
use Flexgrid\Utils\Request\Request;

/**
 * @FG\Controller [name=AdminInvoiceCatalog,type=Module,icon=fas fa-list,level=2,administrationPanel=true,administrationLabel=Factuuritems,administrationRoute=items,administrationPriority=42]
 */
final class AdminInvoiceCatalogController
{
    public function index()
    {
        return $this->items();
    }

    public function items()
    {
        $request = new Request();
        if ($request->getString('new') === '1'
            || $request->getString('type') === 'group' || $request->getString('parent_id') !== '') {
            return $this->renderEditor('');
        }
        return $this->renderOverview();
    }

    public function edit($args = [])
    {
        $id = is_array($args) ? (string)($args[0] ?? '') : (string)$args;
        return $this->renderEditor($id);
    }

    public function save(): AjaxResponse
    {
        try {
            $request = new Request();
            $tax = explode('|', $request->getString('tax_choice'), 2);
            AdminInvoiceFactory::createCatalog()->save([
                'public_id' => $request->getString('public_id'),
                'name' => $request->getString('name'),
                'item_type' => $request->getString('item_type'),
                'parent_public_id' => $request->getString('parent_public_id'),
                'variant_key' => $request->getString('variant_key'),
                'choose_function' => $request->getString('choose_function'),
                'description' => $request->getString('description'),
                'quantity' => $request->getString('quantity'),
                'unit' => $request->getString('unit'),
                'unit_price' => $request->getString('unit_price'),
                'currency' => $request->getString('currency'),
                'tax_rate' => $tax[0] ?? '',
                'tax_category' => $tax[1] ?? '',
                'active' => $request->getBoolean('active'),
            ]);
            $response = new AjaxResponse();
            $response->success = true;
            $response->redirect = '/Flexgrid/AdminInvoiceCatalog/items';
            return $response;
        } catch (\Throwable $exception) {
            $response = new AjaxResponse();
            $response->success = false;
            $response->notifications = [
                '<div class="notification notification--error">'
                . htmlspecialchars($exception->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>',
            ];
            return $response;
        }
    }

    public function delete(): AjaxResponse
    {
        try {
            $request = new Request();
            AdminInvoiceFactory::createCatalog()->delete($request->getString('public_id'));
            $response = new AjaxResponse();
            $response->success = true;
            $response->redirect = '/Flexgrid/AdminInvoiceCatalog/items';
            return $response;
        } catch (\Throwable $exception) {
            $response = new AjaxResponse();
            $response->success = false;
            $response->notifications = [
                '<div class="notification notification--error">'
                . htmlspecialchars($exception->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>',
            ];
            return $response;
        }
    }

    private function renderOverview()
    {
        appendIconAndTitleToHeader('fas fa-list', 'Factuuritems', 'Administratie');
        PageResponse::addAsset('Flexgrid/Flexgrid/src/Html/Admin/Css/AdminUi.scss');
        \Flexgrid\Modules\AdminCore\Service\AdminHeader::AdminAddHeader([new TemplateResponse(
            'Flexgrid/Modules/AdminInvoice/src/Templates/Catalog/HeaderActions.php',
            ['overview' => true]
        )]);
        $catalog = AdminInvoiceFactory::createCatalog();
        $items = $catalog->all();
        return new TemplateResponse('Flexgrid/Modules/AdminInvoice/src/Templates/Catalog/Index.php', [
            'table' => $this->table($items, $items),
        ]);
    }

    private function renderEditor(string $publicId)
    {
        appendIconAndTitleToHeader('fas fa-list', 'Factuuritem bewerken', 'Administratie');
        PageResponse::addAsset('Flexgrid/Flexgrid/src/Html/Admin/Css/AdminUi.scss');
        $catalog = AdminInvoiceFactory::createCatalog();
        $current = $publicId !== '' ? $catalog->find($publicId) : null;
        if ($publicId !== '' && $current === null) {
            throw new \InvalidArgumentException('Dit factuuritem bestaat niet. Ga terug naar het overzicht.');
        }
        $items = $catalog->all();
        $groups = array_values(array_filter($items, function (array $item) use ($catalog): bool {
            return $catalog->isGroup($item);
        }));
        $type = $current !== null ? ($catalog->isGroup($current) ? 'group' : 'item')
            : ((new Request())->getString('type') === 'group' ? 'group' : 'item');
        \Flexgrid\Modules\AdminCore\Service\AdminHeader::AdminAddHeader([new TemplateResponse(
            'Flexgrid/Modules/AdminInvoice/src/Templates/Catalog/HeaderActions.php',
            ['overview' => false]
        )]);
        $requestedParentId = $current !== null
            ? (string)($current['parent_public_id'] ?? '')
            : (new Request())->getString('parent_id');
        $selectedParent = null;
        foreach ($groups as $group) {
            if ((string)$group['public_id'] === $requestedParentId) {
                $selectedParent = $group;
                break;
            }
        }
        $choices = $catalog->choiceFunctions();
        $groupVariants = [];
        foreach ($groups as $group) {
            $groupVariants[(string)$group['public_id']] =
                $choices[$group['choose_function']]['variants'] ?? [];
        }
        $children = $current !== null && $type === 'group'
            ? $catalog->children((string)$current['public_id'], false) : [];
        return new TemplateResponse('Flexgrid/Modules/AdminInvoice/src/Templates/Catalog/Edit.php', [
            'current' => $current,
            'currentType' => $type,
            'groups' => $groups,
            'choiceFunctions' => $choices,
            'selectedParentId' => $selectedParent !== null ? $selectedParent['public_id'] : '',
            'groupVariants' => $groupVariants,
            'variantTable' => $type === 'group' && $current !== null
                ? $this->table($children, $items, 'Varianten van ' . (string)$current['name']) : null,
            'deleteReason' => $current !== null
                ? $catalog->deletionBlockReason((string)$current['public_id']) : null,
            'saveAction' => $this->action('save'),
            'deleteAction' => $this->action('delete'),
        ]);
    }

    private function table(array $items, array $allItems, string $label = 'Factuuritems'): array
    {
        $groups = [];
        foreach ($allItems as $candidate) {
            if ((string)$candidate['item_type'] === 'group') {
                $groups[(string)$candidate['public_id']] = (string)$candidate['name'];
            }
        }
        $rows = [];
        foreach ($items as $item) {
            $id = (string)$item['public_id'];
            $url = '/Flexgrid/AdminInvoiceCatalog/edit/' . rawurlencode($id);
            $isGroup = (string)$item['item_type'] === 'group';
            $parentId = (string)($item['parent_public_id'] ?? '');
            $kind = $isGroup ? 'Keuzegroep' : ($parentId !== '' ? 'Variant' : 'Gewoon item');
            $rows[] = [
                'id' => $id,
                'url' => $url,
                'cells' => [
                    'name' => [
                        'value' => (string)$item['name'], 'title' => true,
                        'secondary' => $parentId !== ''
                            ? (($groups[$parentId] ?? 'Onbekende groep') . ' · ' . (string)$item['variant_key']) : '',
                    ],
                    'kind' => ['value' => $kind, 'badge' => $isGroup ? 'info' : 'neutral'],
                    'description' => $isGroup ? 'Kiest automatisch een variant' : (string)$item['description'],
                    'price' => $isGroup ? 'Per variant'
                        : (string)$item['currency'] . ' ' . (string)$item['unit_price'] . ' / ' . (string)$item['unit'],
                    'status' => [
                        'value' => (int)$item['active'] === 1 ? 'Beschikbaar' : 'Gepauzeerd',
                        'badge' => (int)$item['active'] === 1 ? 'success' : 'warning',
                    ],
                ],
                'actions' => [[
                    'href' => $url, 'icon' => 'fas fa-pen', 'label' => 'Bewerken: ' . (string)$item['name'],
                    'title' => 'Bewerken: ' . (string)$item['name'],
                ]],
            ];
        }
        return [
            'id' => $label === 'Factuuritems' ? 'admin-invoice-catalog' : 'admin-invoice-catalog-variants',
            'label' => $label,
            'columns' => [
                ['key' => 'name', 'label' => 'Naam'],
                ['key' => 'kind', 'label' => 'Soort'],
                ['key' => 'description', 'label' => 'Factuuromschrijving'],
                ['key' => 'price', 'label' => 'Prijs excl. btw'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            'rows' => $rows,
            'empty' => [
                'title' => $label === 'Factuuritems' ? 'Nog geen factuuritems' : 'Nog geen varianten',
                'message' => $label === 'Factuuritems'
                    ? 'Maak een gewoon item of een keuzegroep aan.'
                    : 'Voeg een variant toe om de keuzegroep bruikbaar te maken.',
                'icon' => 'fas fa-list',
            ],
        ];
    }

    private function action(string $method): string
    {
        $event = new AjaxEvent(self::class, $method);
        $event->setMinimumAccessLevel(2);
        return $event->getName();
    }
}
