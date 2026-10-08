<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Flexgrid\Modules\AdminCore\Context\TenantContext;
use Flexgrid\Modules\AdminCore\Contract\NumberSequenceInterface;
use Flexgrid\Modules\AdminCore\Contract\TransactionManagerInterface;
use Flexgrid\Modules\AdminCore\ValueObject\Currency;
use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminCore\ValueObject\TaxRate;
use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\FinalizeInvoice;
use Flexgrid\Modules\AdminInvoice\Contract\InvoiceRepositoryInterface;
use Flexgrid\Modules\AdminInvoice\Domain\Model\Invoice;
use Flexgrid\Modules\AdminInvoice\Domain\Model\InvoiceLine;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\AddressSnapshot;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\CalendarDate;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\InvoiceStatus;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\InvoiceTotals;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\PartySnapshot;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\Quantity;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\TaxCategory;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceCalculator;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceNumberFormatter;
use Flexgrid\Utils\_Time;

final class FinalizationTransactions implements TransactionManagerInterface
{
    /** @var bool */ public $active = false;
    /** @var string[] */ public $trace = [];

    public function transactional(callable $operation)
    {
        $this->active = true;
        $this->trace[] = 'begin';
        try {
            $result = $operation();
            $this->trace[] = 'commit';
            $this->active = false;
            return $result;
        } catch (Throwable $throwable) {
            $this->trace[] = 'rollback';
            $this->active = false;
            throw $throwable;
        }
    }
}

final class FinalizationSequences implements NumberSequenceInterface
{
    /** @var FinalizationTransactions */ private $transactions;
    /** @var int */ public $next = 1;
    /** @var array<string,mixed> */ public $lastReservation = [];

    public function __construct(FinalizationTransactions $transactions)
    {
        $this->transactions = $transactions;
    }

    public function reserve(TenantId $tenantId, string $sequenceKey, string $periodKey): int
    {
        adminInvoiceAssert($this->transactions->active, 'Nummerreservering moet binnen de finalisatietransactie vallen.');
        $this->lastReservation = [$tenantId->toString(), $sequenceKey, $periodKey];
        return $this->next++;
    }
}

final class FinalizationRepository implements InvoiceRepositoryInterface
{
    /** @var Invoice */ public $invoice;
    /** @var int */ public $finalizeCount = 0;
    /** @var FinalizationTransactions */ private $transactions;

    public function __construct(Invoice $invoice, FinalizationTransactions $transactions)
    {
        $this->invoice = $invoice;
        $this->transactions = $transactions;
    }

    public function insert(Invoice $invoice, InvoiceTotals $totals, int $createdAt): void { throw new RuntimeException('not used'); }
    public function findByPublicId(TenantId $tenantId, string $publicId): ?Invoice
    {
        return $this->matches($tenantId, $publicId) ? $this->invoice : null;
    }
    public function findByPublicIdForUpdate(TenantId $tenantId, string $publicId): ?Invoice
    {
        adminInvoiceAssert($this->transactions->active, 'Factuur moet binnen de transactie worden vergrendeld.');
        return $this->findByPublicId($tenantId, $publicId);
    }
    public function findByExternalReference(TenantId $tenantId, string $source, string $externalId): ?Invoice { return null; }
    public function updateDraft(Invoice $invoice, InvoiceTotals $totals, int $updatedAt): void { throw new RuntimeException('not used'); }
    public function correctIssueDate(Invoice $invoice, int $updatedAt): void { throw new RuntimeException('not used'); }
    public function deleteDraft(Invoice $invoice): void { throw new RuntimeException('not used'); }
    public function finalize(Invoice $invoice): void
    {
        adminInvoiceAssert($this->transactions->active, 'Definitieve opslag moet binnen dezelfde transactie vallen.');
        $this->invoice = $invoice;
        $this->finalizeCount++;
    }

    private function matches(TenantId $tenantId, string $publicId): bool
    {
        return $this->invoice->getTenantId()->equals($tenantId)
            && $this->invoice->getPublicId() === $publicId;
    }
}

$tenant = new TenantId('finalization-tenant');
$currency = Currency::euro();
$invoice = new Invoice(
    'invoice-to-finalize',
    $tenant,
    $currency,
    new PartySnapshot('Finalisatieklant'),
    new AddressSnapshot('Straat 1', '1000 AA', 'Utrecht', 'NL'),
    new CalendarDate('2026-09-18'),
    new CalendarDate('2026-10-18')
);
$invoice->addLine(new InvoiceLine(
    'final-line',
    'Werkzaamheden',
    Quantity::fromDecimal('2'),
    'uur',
    Money::fromDecimal('50.00', $currency),
    TaxRate::fromPercentage('21'),
    new TaxCategory(TaxCategory::STANDARD),
    10
));

$transactions = new FinalizationTransactions();
$sequences = new FinalizationSequences($transactions);
$repository = new FinalizationRepository($invoice, $transactions);
$finalized = (new FinalizeInvoice(
    new TenantContext($tenant),
    $transactions,
    $sequences,
    $repository,
    new InvoiceCalculator(),
    new InvoiceNumberFormatter(),
    new _Time(1770004321)
))->execute('invoice-to-finalize');

adminInvoiceAssert($finalized->getStatus()->getValue() === InvoiceStatus::FINALIZED, 'Use-case moet de factuur finaliseren.');
adminInvoiceAssert($finalized->getInvoiceNumber() === '2026-0001', 'Use-case moet het standaardfactuurnummer formatteren.');
adminInvoiceAssert($finalized->getInvoiceSequenceValue() === 1, 'Use-case moet de ruwe sequencewaarde bewaren.');
adminInvoiceAssert($finalized->getFinalizedAt() === 1770004321, 'Use-case moet de geïnjecteerde klok gebruiken.');
adminInvoiceAssert($finalized->getFinalizedTotals()->getGross()->getMinorUnits() === 12100, 'Finalisatietotalen moeten exact worden vastgezet.');
adminInvoiceAssert($sequences->lastReservation === ['finalization-tenant', 'invoice', '2026'], 'Sequence moet per tenant en factuurjaar reserveren.');
adminInvoiceAssert($repository->finalizeCount === 1, 'Repository moet exact één definitieve update uitvoeren.');
adminInvoiceAssert($transactions->trace === ['begin', 'commit'], 'Finalisatie moet in één transactie committen.');

adminInvoiceAssertThrows(DomainException::class, function () use ($tenant, $transactions, $sequences, $repository): void {
    (new FinalizeInvoice(
        new TenantContext($tenant),
        $transactions,
        $sequences,
        $repository,
        new InvoiceCalculator(),
        new InvoiceNumberFormatter(),
        new _Time(1770005000)
    ))->execute('invoice-to-finalize');
}, 'Een definitieve factuur mag niet opnieuw worden gefinaliseerd.');
adminInvoiceAssert($transactions->trace === ['begin', 'commit', 'begin', 'rollback'], 'Mislukte herfinalisatie moet rollbacken.');
adminInvoiceAssert($repository->finalizeCount === 1, 'Mislukte herfinalisatie mag niet opnieuw opslaan.');

$formatter = new InvoiceNumberFormatter();
adminInvoiceAssert($formatter->format('2026', 42) === '2026-0042', 'Formatter moet minimaal vier cijfers gebruiken.');
adminInvoiceAssertThrows(InvalidArgumentException::class, function () use ($formatter): void {
    $formatter->format('boekjaar', 1);
}, 'Formatter moet een ongeldige periode weigeren.');

echo "AdminInvoice finalization tests passed.\n";
