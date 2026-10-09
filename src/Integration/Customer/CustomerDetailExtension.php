<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Integration\Customer;

use Flexgrid\Event\AjaxEvent;
use Flexgrid\Modules\AdminCustomer\Application\ReadModel\CustomerDetailContext;
use Flexgrid\Modules\AdminCustomer\Contract\CustomerDetailExtensionInterface;
use Flexgrid\Modules\AdminInvoice\Controller\AdminRecurringInvoiceController;
use Flexgrid\Modules\AdminInvoice\Service\AdminInvoiceFactory;
use Flexgrid\Response\TemplateResponse;

final class CustomerDetailExtension implements CustomerDetailExtensionInterface
{
    public function render(CustomerDetailContext $context): string
    {
        $moduleBaseUrl = rtrim(__DOMAIN__, '/') . '/Flexgrid/AdminInvoice/';
        $historyTable = AdminInvoiceFactory::createCustomerInvoiceHistoryPresenter()->present(
            AdminInvoiceFactory::createListCustomerDocuments()->execute($context->getPublicId(), 5),
            $moduleBaseUrl . 'edit',
            $moduleBaseUrl . 'detail',
            $moduleBaseUrl . 'creditNote'
        );
        $recurring = AdminInvoiceFactory::createRecurringInvoices();
        $memberships = $recurring->membersForCustomer($context->getPublicId());
        foreach ($memberships as &$membership) {
            $membership['items'] = $recurring->itemsForCustomerPlan(
                (string)$membership['plan_public_id'], $context->getPublicId()
            );
        }
        unset($membership);
        $assigned = [];
        foreach ($memberships as $membership) {
            $assigned[$membership['plan_public_id']] = true;
        }
        $availablePlans = [];
        $items = AdminInvoiceFactory::createCatalog()->allActiveAssignable();
        foreach ($memberships as &$membership) {
            $assignedIds = [];
            $currency = '';
            foreach ($membership['items'] as $assignedItem) {
                $assignedIds[$assignedItem['catalog_item_public_id']] = true;
                if ((int)$assignedItem['active'] === 1 && $currency === '') {
                    $currency = (string)($assignedItem['currency'] ?? '');
                }
            }
            $membership['selectable_items'] = array_values(array_filter(
                $items,
                static function (array $item) use ($assignedIds, $currency): bool {
                    return !isset($assignedIds[$item['public_id']])
                        && ($currency === '' || $item['currency'] === $currency);
                }
            ));
        }
        unset($membership);
        foreach ($recurring->all() as $plan) {
            if ((string)$plan['customer_public_id'] === '' && (int)$plan['active'] === 1
                && !isset($assigned[$plan['public_id']])) {
                $plan['upcoming_date'] = $recurring->upcomingDateForPlan($plan);
                $availablePlans[] = $plan;
            }
        }
        $enrollEvent = new AjaxEvent(AdminRecurringInvoiceController::class, 'enrollCustomer');
        $enrollEvent->setMinimumAccessLevel(2);
        $toggleEvent = new AjaxEvent(AdminRecurringInvoiceController::class, 'toggleCustomer');
        $toggleEvent->setMinimumAccessLevel(2);
        $addItemEvent = new AjaxEvent(AdminRecurringInvoiceController::class, 'addCustomerItem');
        $addItemEvent->setMinimumAccessLevel(2);
        $toggleItemEvent = new AjaxEvent(AdminRecurringInvoiceController::class, 'toggleCustomerItem');
        $toggleItemEvent->setMinimumAccessLevel(2);

        return (string)new TemplateResponse(
            'Flexgrid/Modules/AdminInvoice/src/Templates/Customer/DetailExtension.php',
            [
                'customerName' => $context->getDisplayName(),
                'createInvoiceUrl' => $moduleBaseUrl . 'create/'
                    . rawurlencode($context->getPublicId()),
                'canCreateInvoice' => $context->isActive(),
                'isActive' => $context->isActive(),
                'historyTable' => $historyTable,
                'customerPublicId' => $context->getPublicId(),
                'availablePlans' => $availablePlans,
                'memberships' => $memberships,
                'catalogItems' => $items,
                'enrollAction' => $enrollEvent->getName(),
                'toggleMembershipAction' => $toggleEvent->getName(),
                'addItemAction' => $addItemEvent->getName(),
                'toggleItemAction' => $toggleItemEvent->getName(),
            ]
        );
    }
}
