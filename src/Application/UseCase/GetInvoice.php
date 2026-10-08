<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Application\UseCase;

use Flexgrid\Modules\AdminCore\Context\TenantContext;
use Flexgrid\Modules\AdminInvoice\Contract\InvoiceRepositoryInterface;
use Flexgrid\Modules\AdminInvoice\Domain\Model\Invoice;
use Flexgrid\Modules\AdminInvoice\Exception\InvoiceNotFoundException;

final class GetInvoice
{
    /** @var TenantContext */
    private $tenantContext;
    /** @var InvoiceRepositoryInterface */
    private $invoices;

    public function __construct(TenantContext $tenantContext, InvoiceRepositoryInterface $invoices)
    {
        $this->tenantContext = $tenantContext;
        $this->invoices = $invoices;
    }

    public function execute(string $publicId): Invoice
    {
        $invoice = $this->invoices->findByPublicId(
            $this->tenantContext->getTenantId(),
            $this->requiredId($publicId)
        );
        if ($invoice === null) {
            throw new InvoiceNotFoundException('Factuur niet gevonden.');
        }

        return $invoice;
    }

    private function requiredId(string $publicId): string
    {
        $publicId = trim($publicId);
        if ($publicId === '' || strlen($publicId) > 64) {
            throw new \InvalidArgumentException('Een geldige publieke factuur-id is verplicht.');
        }

        return $publicId;
    }
}
