<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Flexgrid\Modules\AdminCore\Context\TenantContext;
use Flexgrid\Modules\AdminCore\Contract\PublicIdGeneratorInterface;
use Flexgrid\Modules\AdminCore\Contract\TransactionManagerInterface;
use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminInvoice\Application\Command\CreateInvoiceCommand;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\CreateInvoice;
use Flexgrid\Modules\AdminInvoice\Contract\InvoiceRepositoryInterface;
use Flexgrid\Modules\AdminInvoice\Domain\Model\Invoice;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\InvoiceTotals;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceCalculator;
use Flexgrid\Utils\_Time;

final class SequentialInvoiceIdGenerator implements PublicIdGeneratorInterface
{
    /** @var int */
    private $next = 1;

    public function generate(): string
    {
        return 'generated-' . $this->next++;
    }
}

final class TraceInvoiceTransactions implements TransactionManagerInterface
{
    /** @var string[] */
    public $trace = [];

    public function transactional(callable $operation)
    {
        $this->trace[] = 'begin';
        try {
            $result = $operation();
            $this->trace[] = 'commit';
            return $result;
        } catch (Throwable $throwable) {
            $this->trace[] = 'rollback';
            throw $throwable;
        }
    }
}

final class MemoryInvoiceRepository implements InvoiceRepositoryInterface
{
    /** @var Invoice[] */
    public $invoices = [];
    /** @var InvoiceTotals|null */
    public $lastTotals;
    /** @var int|null */
    public $lastCreatedAt;
    /** @var int */
    public $insertCount = 0;
    /** @var bool */
    public $failInsert = false;

    public function insert(Invoice $invoice, InvoiceTotals $totals, int $createdAt): void
    {
        if ($this->failInsert) {
            throw new RuntimeException('insert failed');
        }
        $this->invoices[] = $invoice;
        $this->lastTotals = $totals;
        $this->lastCreatedAt = $createdAt;
        $this->insertCount++;
    }

    public function findByPublicId(TenantId $tenantId, string $publicId): ?Invoice
    {
        foreach ($this->invoices as $invoice) {
            if ($invoice->getTenantId()->equals($tenantId) && $invoice->getPublicId() === $publicId) {
                return $invoice;
            }
        }

        return null;
    }

    public function findByPublicIdForUpdate(TenantId $tenantId, string $publicId): ?Invoice
    {
        return $this->findByPublicId($tenantId, $publicId);
    }

    public function findByExternalReference(TenantId $tenantId, string $source, string $externalId): ?Invoice
    {
        foreach ($this->invoices as $invoice) {
            if ($invoice->getTenantId()->equals($tenantId)
                && $invoice->getSource() === $source
                && $invoice->getExternalId() === $externalId
            ) {
                return $invoice;
            }
        }

        return null;
    }

    public function updateDraft(Invoice $invoice, InvoiceTotals $totals, int $updatedAt): void
    {
        foreach ($this->invoices as $index => $stored) {
            if ($stored->getTenantId()->equals($invoice->getTenantId())
                && $stored->getPublicId() === $invoice->getPublicId()
            ) {
                $this->invoices[$index] = $invoice;
                $this->lastTotals = $totals;
                return;
            }
        }

        throw new RuntimeException('invoice not found');
    }

    public function finalize(Invoice $invoice): void
    {
        throw new RuntimeException('not used');
    }

    public function correctIssueDate(Invoice $invoice, int $updatedAt): void
    {
        throw new RuntimeException('not used');
    }

    public function deleteDraft(Invoice $invoice): void
    {
        throw new RuntimeException('not used');
    }
}

function createInvoiceCommand(array $lines = null, string $externalId = 'order-500'): CreateInvoiceCommand
{
    return new CreateInvoiceCommand(
        'EUR',
        '2026-09-18',
        '2026-10-18',
        ['name' => 'Webshopklant', 'email' => 'klant@example.test', 'source_public_id' => 'customer-public-create'],
        ['line_1' => 'Markt 1', 'postal_code' => '1000 AA', 'city' => 'Utrecht', 'country_code' => 'NL'],
        $lines === null ? [
            [
                'description' => 'Product',
                'quantity' => '2',
                'unit' => 'stuk',
                'unit_price' => '10.00',
                'tax_rate' => '21',
                'tax_category' => 'standard',
            ],
            [
                'description' => 'Boek',
                'quantity' => '1',
                'unit' => 'stuk',
                'unit_price' => '5.00',
                'tax_rate' => '9',
                'tax_category' => 'reduced',
            ],
        ] : $lines,
        'customer-10',
        '',
        'webshop',
        $externalId
    );
}

$repository = new MemoryInvoiceRepository();
$transactions = new TraceInvoiceTransactions();
$useCase = new CreateInvoice(
    new TenantContext(new TenantId('tenant-create-test')),
    new SequentialInvoiceIdGenerator(),
    $transactions,
    $repository,
    new InvoiceCalculator(),
    new _Time(1234567890)
);

$invoice = $useCase->execute(createInvoiceCommand());
adminInvoiceAssert($invoice->getPublicId() === 'generated-1', 'Use-case moet publieke ids via de generator maken.');
adminInvoiceAssert(count($invoice->getLines()) === 2, 'Use-case moet alle regels aan het aggregate toevoegen.');
adminInvoiceAssert($invoice->getTenantId()->toString() === 'tenant-create-test', 'Tenant moet uitsluitend uit TenantContext komen.');
adminInvoiceAssert($invoice->getCustomerSnapshot()->getSourcePublicId() === 'customer-public-create', 'CreateInvoice moet geselecteerde publieke Customer-id in de snapshot bewaren.');
adminInvoiceAssert($repository->insertCount === 1, 'Factuur en regels moeten via één repositoryinsert worden opgeslagen.');
adminInvoiceAssert($repository->lastCreatedAt === 1234567890, 'Use-case moet de geïnjecteerde _Time gebruiken.');
adminInvoiceAssert($repository->lastTotals->getGross()->getMinorUnits() === 2965, 'Use-case moet exacte totalen aan persistence doorgeven.');
adminInvoiceAssert($transactions->trace === ['begin', 'commit'], 'CreateInvoice moet één transactiegrens gebruiken.');

$retry = $useCase->execute(createInvoiceCommand());
adminInvoiceAssert($retry === $invoice, 'Gelijke externe referentie moet idempotent dezelfde factuur teruggeven.');
adminInvoiceAssert($repository->insertCount === 1, 'Idempotente retry mag geen tweede factuur opslaan.');

adminInvoiceAssertThrows(InvalidArgumentException::class, function () use ($useCase): void {
    $useCase->execute(createInvoiceCommand([[
        'description' => 'Float mag niet',
        'quantity' => 1.5,
        'unit' => 'stuk',
        'unit_price' => '1.00',
        'tax_rate' => '21',
        'tax_category' => 'standard',
    ]], 'order-float'));
}, 'Publieke geld/hoeveelheidsgrens mag geen floats accepteren.');

$failingRepository = new MemoryInvoiceRepository();
$failingRepository->failInsert = true;
$failingTransactions = new TraceInvoiceTransactions();
$failingUseCase = new CreateInvoice(
    new TenantContext(new TenantId('tenant-failure-test')),
    new SequentialInvoiceIdGenerator(),
    $failingTransactions,
    $failingRepository,
    new InvoiceCalculator(),
    new _Time(1234567890)
);
adminInvoiceAssertThrows(RuntimeException::class, function () use ($failingUseCase): void {
    $failingUseCase->execute(createInvoiceCommand());
}, 'Persistencefout moet uit CreateInvoice terugkomen.');
adminInvoiceAssert($failingTransactions->trace === ['begin', 'rollback'], 'Persistencefout moet de use-case terugrollen.');

echo "AdminInvoice CreateInvoice tests passed.\n";
