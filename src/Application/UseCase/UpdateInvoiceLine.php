<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Application\UseCase;

use Flexgrid\Modules\AdminCore\Context\TenantContext;
use Flexgrid\Modules\AdminCore\Contract\TransactionManagerInterface;
use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminCore\ValueObject\TaxRate;
use Flexgrid\Modules\AdminInvoice\Application\Command\UpdateInvoiceLineCommand;
use Flexgrid\Modules\AdminInvoice\Contract\InvoiceRepositoryInterface;
use Flexgrid\Modules\AdminInvoice\Domain\Model\Invoice;
use Flexgrid\Modules\AdminInvoice\Domain\Model\InvoiceLine;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\Quantity;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\TaxCategory;
use Flexgrid\Modules\AdminInvoice\Exception\InvoiceNotFoundException;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceCalculator;
use Flexgrid\Utils\_Time;

final class UpdateInvoiceLine
{
    /** @var TenantContext */
    private $tenantContext;
    /** @var TransactionManagerInterface */
    private $transactions;
    /** @var InvoiceRepositoryInterface */
    private $invoices;
    /** @var InvoiceCalculator */
    private $calculator;
    /** @var _Time */
    private $clock;

    public function __construct(
        TenantContext $tenantContext,
        TransactionManagerInterface $transactions,
        InvoiceRepositoryInterface $invoices,
        InvoiceCalculator $calculator,
        _Time $clock
    ) {
        $this->tenantContext = $tenantContext;
        $this->transactions = $transactions;
        $this->invoices = $invoices;
        $this->calculator = $calculator;
        $this->clock = $clock;
    }

    public function execute(UpdateInvoiceLineCommand $command): Invoice
    {
        return $this->transactions->transactional(function () use ($command): Invoice {
            $invoice = $this->invoices->findByPublicIdForUpdate(
                $this->tenantContext->getTenantId(),
                $command->getInvoicePublicId()
            );
            if ($invoice === null) {
                throw new InvoiceNotFoundException('Factuur niet gevonden.');
            }

            $existingLine = $this->findLine($invoice, $command->getLinePublicId());
            $invoice->replaceLine(new InvoiceLine(
                $command->getLinePublicId(),
                $command->getDescription(),
                Quantity::fromDecimal($command->getQuantity()),
                $command->getUnit(),
                Money::fromDecimal($command->getUnitPrice(), $invoice->getCurrency()),
                TaxRate::fromPercentage($command->getTaxRate()),
                new TaxCategory($command->getTaxCategory()),
                $existingLine->getPosition(),
                $existingLine->getCatalogItemPublicId()
            ));
            $this->invoices->updateDraft(
                $invoice,
                $this->calculator->calculate($invoice),
                (int)$this->clock->get()
            );

            return $invoice;
        });
    }

    private function findLine(Invoice $invoice, string $linePublicId): InvoiceLine
    {
        foreach ($invoice->getLines() as $line) {
            if ($line->getPublicId() === $linePublicId) {
                return $line;
            }
        }

        throw new \DomainException('Factuurregel bestaat niet.');
    }
}
