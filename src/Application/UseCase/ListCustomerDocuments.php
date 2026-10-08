<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Application\UseCase;

use Flexgrid\Modules\AdminCore\Context\TenantContext;
use Flexgrid\Modules\AdminInvoice\Contract\InvoiceCustomerHistoryRepositoryInterface;

final class ListCustomerDocuments
{
    /** @var TenantContext */ private $tenantContext;
    /** @var InvoiceCustomerHistoryRepositoryInterface */ private $documents;

    public function __construct(
        TenantContext $tenantContext,
        InvoiceCustomerHistoryRepositoryInterface $documents
    ) {
        $this->tenantContext = $tenantContext;
        $this->documents = $documents;
    }

    public function execute(string $customerPublicId, int $limit = 5): array
    {
        $customerPublicId = trim($customerPublicId);
        if ($customerPublicId === '' || strlen($customerPublicId) > 64) {
            throw new \InvalidArgumentException('Klantdocumenten vereisen een geldige publieke klant-id.');
        }
        if ($limit < 1 || $limit > 20) {
            throw new \InvalidArgumentException('Klantdocumenten ondersteunen een limiet van 1 tot en met 20.');
        }

        return $this->documents->findRecentByCustomer(
            $this->tenantContext->getTenantId(),
            $customerPublicId,
            $limit
        );
    }
}
