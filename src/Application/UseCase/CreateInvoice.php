<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Application\UseCase;

use Flexgrid\Modules\AdminCore\Context\TenantContext;
use Flexgrid\Modules\AdminCore\Contract\PublicIdGeneratorInterface;
use Flexgrid\Modules\AdminCore\Contract\TransactionManagerInterface;
use Flexgrid\Modules\AdminCore\ValueObject\Currency;
use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminCore\ValueObject\TaxRate;
use Flexgrid\Modules\AdminInvoice\Application\Command\CreateInvoiceCommand;
use Flexgrid\Modules\AdminInvoice\Contract\InvoiceRepositoryInterface;
use Flexgrid\Modules\AdminInvoice\Contract\InvoiceDraftCreatorInterface;
use Flexgrid\Modules\AdminInvoice\Domain\Model\Invoice;
use Flexgrid\Modules\AdminInvoice\Domain\Model\InvoiceLine;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\AddressSnapshot;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\CalendarDate;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\PartySnapshot;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\Quantity;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\TaxCategory;
use Flexgrid\Modules\AdminInvoice\Exception\DuplicateExternalReferenceException;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceCalculator;
use Flexgrid\Utils\_Time;

final class CreateInvoice implements InvoiceDraftCreatorInterface
{
    /** @var TenantContext */
    private $tenantContext;
    /** @var PublicIdGeneratorInterface */
    private $publicIds;
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
        PublicIdGeneratorInterface $publicIds,
        TransactionManagerInterface $transactions,
        InvoiceRepositoryInterface $invoices,
        InvoiceCalculator $calculator,
        _Time $clock
    ) {
        $this->tenantContext = $tenantContext;
        $this->publicIds = $publicIds;
        $this->transactions = $transactions;
        $this->invoices = $invoices;
        $this->calculator = $calculator;
        $this->clock = $clock;
    }

    public function execute(CreateInvoiceCommand $command): Invoice
    {
        $tenantId = $this->tenantContext->getTenantId();

        try {
            return $this->transactions->transactional(function () use ($command, $tenantId): Invoice {
                if ($command->getSource() !== '') {
                    $existing = $this->invoices->findByExternalReference(
                        $tenantId,
                        $command->getSource(),
                        $command->getExternalId()
                    );
                    if ($existing !== null) {
                        return $existing;
                    }
                }

                $invoice = $this->buildInvoice($command);
                $totals = $this->calculator->calculate($invoice);
                $this->invoices->insert($invoice, $totals, (int)$this->clock->get());

                return $invoice;
            });
        } catch (DuplicateExternalReferenceException $exception) {
            if ($command->getSource() !== '') {
                $existing = $this->invoices->findByExternalReference(
                    $tenantId,
                    $command->getSource(),
                    $command->getExternalId()
                );
                if ($existing !== null) {
                    return $existing;
                }
            }

            throw $exception;
        }
    }

    private function buildInvoice(CreateInvoiceCommand $command): Invoice
    {
        $currency = new Currency($command->getCurrency());
        $party = $command->getCustomerSnapshot();
        $address = $command->getBillingAddressSnapshot();
        $invoice = new Invoice(
            $this->publicIds->generate(),
            $this->tenantContext->getTenantId(),
            $currency,
            new PartySnapshot(
                $this->text($party, 'name', true),
                $this->text($party, 'contact_name'),
                $this->text($party, 'email'),
                $this->text($party, 'phone'),
                $this->text($party, 'registration_number'),
                $this->text($party, 'tax_number'),
                $this->text($party, 'source_public_id')
            ),
            new AddressSnapshot(
                $this->text($address, 'line_1'),
                $this->text($address, 'postal_code'),
                $this->text($address, 'city'),
                $this->text($address, 'country_code'),
                $this->text($address, 'line_2'),
                $this->text($address, 'region')
            ),
            new CalendarDate($command->getIssueDate()),
            new CalendarDate($command->getDueDate()),
            $command->getCustomerReference(),
            $command->getNotes(),
            $command->getSource(),
            $command->getExternalId()
        );

        foreach ($command->getLines() as $index => $line) {
            if (!is_array($line)) {
                throw new \InvalidArgumentException('Iedere factuurregel moet een array zijn.');
            }
            $invoice->addLine(new InvoiceLine(
                $this->publicIds->generate(),
                $this->text($line, 'description', true),
                Quantity::fromDecimal($this->decimal($line, 'quantity')),
                $this->text($line, 'unit', true),
                Money::fromDecimal($this->decimal($line, 'unit_price'), $currency),
                TaxRate::fromPercentage($this->decimal($line, 'tax_rate')),
                new TaxCategory($this->text($line, 'tax_category', true)),
                $this->position($line, $index),
                $this->text($line, 'catalog_item_public_id')
            ));
        }

        return $invoice;
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

    private function decimal(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value) && !is_int($value)) {
            throw new \InvalidArgumentException('Veld ' . $key . ' moet een decimale string zijn; floats zijn niet toegestaan.');
        }

        return trim((string)$value);
    }

    private function position(array $line, int $index): int
    {
        if (!array_key_exists('position', $line)) {
            return ($index + 1) * 10;
        }
        $position = $line['position'];
        if (is_int($position)) {
            return $position;
        }
        if (!is_string($position) || preg_match('/^[0-9]+$/D', $position) !== 1) {
            throw new \InvalidArgumentException('Regelpositie moet een niet-negatief geheel getal zijn.');
        }

        return (int)$position;
    }
}
