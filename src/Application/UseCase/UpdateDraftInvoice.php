<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Application\UseCase;

use Flexgrid\Modules\AdminCore\Context\TenantContext;
use Flexgrid\Modules\AdminCore\Contract\TransactionManagerInterface;
use Flexgrid\Modules\AdminInvoice\Application\Command\UpdateDraftInvoiceCommand;
use Flexgrid\Modules\AdminInvoice\Contract\InvoiceRepositoryInterface;
use Flexgrid\Modules\AdminInvoice\Domain\Model\Invoice;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\AddressSnapshot;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\CalendarDate;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\PartySnapshot;
use Flexgrid\Modules\AdminInvoice\Exception\InvoiceNotFoundException;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceCalculator;
use Flexgrid\Utils\_Time;

final class UpdateDraftInvoice
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

    public function execute(UpdateDraftInvoiceCommand $command): Invoice
    {
        return $this->transactions->transactional(function () use ($command): Invoice {
            $invoice = $this->lockInvoice($command->getPublicId());
            $invoice->updateCustomerSnapshot(
                $this->party($command->getCustomerSnapshot()),
                $this->address($command->getBillingAddressSnapshot())
            );
            $invoice->updateDraftDetails(
                new CalendarDate($command->getIssueDate()),
                new CalendarDate($command->getDueDate()),
                $command->getCustomerReference(),
                $command->getNotes()
            );
            $this->invoices->updateDraft(
                $invoice,
                $this->calculator->calculate($invoice),
                (int)$this->clock->get()
            );

            return $invoice;
        });
    }

    private function lockInvoice(string $publicId): Invoice
    {
        $invoice = $this->invoices->findByPublicIdForUpdate(
            $this->tenantContext->getTenantId(),
            trim($publicId)
        );
        if ($invoice === null) {
            throw new InvoiceNotFoundException('Factuur niet gevonden.');
        }

        return $invoice;
    }

    private function party(array $data): PartySnapshot
    {
        return new PartySnapshot(
            $this->text($data, 'name', true),
            $this->text($data, 'contact_name'),
            $this->text($data, 'email'),
            $this->text($data, 'phone'),
            $this->text($data, 'registration_number'),
            $this->text($data, 'tax_number'),
            $this->text($data, 'source_public_id')
        );
    }

    private function address(array $data): AddressSnapshot
    {
        return new AddressSnapshot(
            $this->text($data, 'line_1', true),
            $this->text($data, 'postal_code', true),
            $this->text($data, 'city', true),
            $this->text($data, 'country_code', true),
            $this->text($data, 'line_2'),
            $this->text($data, 'region')
        );
    }

    private function text(array $data, string $key, bool $required = false): string
    {
        $value = $data[$key] ?? '';
        if (!is_string($value) && !is_int($value)) {
            throw new \InvalidArgumentException('Veld ' . $key . ' moet tekst bevatten.');
        }
        $value = trim((string)$value);
        if ($required && $value === '') {
            throw new \InvalidArgumentException('Veld ' . $key . ' is verplicht.');
        }

        return $value;
    }
}
