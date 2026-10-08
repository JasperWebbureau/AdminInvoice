<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Flexgrid\Modules\AdminCore\Context\TenantContext;
use Flexgrid\Modules\AdminCore\Contract\NumberSequenceInterface;
use Flexgrid\Modules\AdminCore\Contract\PublicIdGeneratorInterface;
use Flexgrid\Modules\AdminCore\Contract\TransactionManagerInterface;
use Flexgrid\Modules\AdminCore\ValueObject\Currency;
use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminCore\ValueObject\TaxRate;
use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\CreateFullCreditNote;
use Flexgrid\Modules\AdminInvoice\Contract\CreditNoteRepositoryInterface;
use Flexgrid\Modules\AdminInvoice\Contract\InvoiceRepositoryInterface;
use Flexgrid\Modules\AdminInvoice\Domain\Model\CreditNote;
use Flexgrid\Modules\AdminInvoice\Domain\Model\Invoice;
use Flexgrid\Modules\AdminInvoice\Domain\Model\InvoiceLine;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\AddressSnapshot;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\CalendarDate;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\InvoiceStatus;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\InvoiceTotals;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\PartySnapshot;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\Quantity;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\TaxCategory;
use Flexgrid\Modules\AdminInvoice\Service\CreditNoteNumberFormatter;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceCalculator;
use Flexgrid\Utils\_Time;

final class CreditNoteTransactions implements TransactionManagerInterface
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

final class CreditNoteIds implements PublicIdGeneratorInterface
{
    /** @var string[] */ private $ids;
    public function __construct(array $ids) { $this->ids = $ids; }
    public function generate(): string
    {
        $id = array_shift($this->ids);
        if (!is_string($id)) {
            throw new RuntimeException('Geen test-id beschikbaar.');
        }
        return $id;
    }
}

final class CreditNoteSequences implements NumberSequenceInterface
{
    /** @var CreditNoteTransactions */ private $transactions;
    /** @var array */ public $reservation = [];
    public function __construct(CreditNoteTransactions $transactions) { $this->transactions = $transactions; }
    public function reserve(TenantId $tenantId, string $sequenceKey, string $periodKey): int
    {
        adminInvoiceAssert($this->transactions->active, 'Creditnotanummer moet binnen de transactie worden gereserveerd.');
        $this->reservation = [$tenantId->toString(), $sequenceKey, $periodKey];
        return 7;
    }
}

final class CreditSourceInvoices implements InvoiceRepositoryInterface
{
    /** @var Invoice */ public $invoice;
    /** @var CreditNoteTransactions */ private $transactions;
    public function __construct(Invoice $invoice, CreditNoteTransactions $transactions)
    {
        $this->invoice = $invoice;
        $this->transactions = $transactions;
    }
    public function insert(Invoice $invoice, InvoiceTotals $totals, int $createdAt): void { throw new RuntimeException('not used'); }
    public function findByPublicId(TenantId $tenantId, string $publicId): ?Invoice { return null; }
    public function findByPublicIdForUpdate(TenantId $tenantId, string $publicId): ?Invoice
    {
        adminInvoiceAssert($this->transactions->active, 'Bronfactuur moet binnen de transactie worden vergrendeld.');
        return $this->invoice->getTenantId()->equals($tenantId) && $this->invoice->getPublicId() === $publicId
            ? $this->invoice
            : null;
    }
    public function findByExternalReference(TenantId $tenantId, string $source, string $externalId): ?Invoice { return null; }
    public function updateDraft(Invoice $invoice, InvoiceTotals $totals, int $updatedAt): void { throw new RuntimeException('not used'); }
    public function correctIssueDate(Invoice $invoice, int $updatedAt): void { throw new RuntimeException('not used'); }
    public function finalize(Invoice $invoice): void { throw new RuntimeException('not used'); }
    public function deleteDraft(Invoice $invoice): void { throw new RuntimeException('not used'); }
}

final class MemoryCreditNotes implements CreditNoteRepositoryInterface
{
    /** @var CreditNoteTransactions */ private $transactions;
    /** @var CreditNote|null */ public $creditNote;
    /** @var int */ public $invoiceUpdates = 0;
    public function __construct(CreditNoteTransactions $transactions) { $this->transactions = $transactions; }
    public function insert(CreditNote $creditNote, int $createdAt): void
    {
        adminInvoiceAssert($this->transactions->active, 'Creditnota-opslag moet binnen de transactie vallen.');
        adminInvoiceAssert($createdAt === $creditNote->getFinalizedAt(), 'Aanmaak- en finalisatietijd moeten gelijk zijn.');
        $this->creditNote = $creditNote;
    }
    public function markOriginalInvoiceCredited(Invoice $invoice, int $updatedAt): void
    {
        adminInvoiceAssert($this->transactions->active, 'Statuswijziging bronfactuur moet binnen dezelfde transactie vallen.');
        adminInvoiceAssert($invoice->getStatus()->getValue() === InvoiceStatus::CREDITED, 'Bronfactuur moet in het domein gecrediteerd zijn.');
        $this->invoiceUpdates++;
    }
    public function findByPublicId(TenantId $tenantId, string $publicId): ?CreditNote
    {
        return $this->creditNote !== null
            && $this->creditNote->getTenantId()->equals($tenantId)
            && $this->creditNote->getPublicId() === $publicId
            ? $this->creditNote
            : null;
    }
}

$tenant = new TenantId('credit-tenant');
$currency = Currency::euro();
$invoice = new Invoice(
    'original-invoice',
    $tenant,
    $currency,
    new PartySnapshot('Creditklant B.V.', 'Financiën', 'finance@example.test'),
    new AddressSnapshot('Markt 1', '1000 AA', 'Utrecht', 'NL'),
    new CalendarDate('2026-09-18'),
    new CalendarDate('2026-10-18'),
    'PO-42'
);
$invoice->addLine(new InvoiceLine(
    'original-line-1', 'Werkzaamheden', Quantity::fromDecimal('2'), 'uur',
    Money::fromDecimal('50.00', $currency), TaxRate::fromPercentage('21'),
    new TaxCategory(TaxCategory::STANDARD), 10
));
$invoice->addLine(new InvoiceLine(
    'original-line-2', 'Boek', Quantity::fromDecimal('1'), 'stuk',
    Money::fromDecimal('20.00', $currency), TaxRate::fromPercentage('9'),
    new TaxCategory(TaxCategory::REDUCED), 20
));
$calculator = new InvoiceCalculator();
$invoice->finalize('2026-0042', 42, 1770004321, $calculator->calculate($invoice));

$transactions = new CreditNoteTransactions();
$sequences = new CreditNoteSequences($transactions);
$credits = new MemoryCreditNotes($transactions);
$timestamp = (int)strtotime('2026-09-20 12:00:00 UTC');
$useCase = new CreateFullCreditNote(
    new TenantContext($tenant),
    new CreditNoteIds(['credit-line-1', 'credit-line-2', 'credit-note-1']),
    $transactions,
    $sequences,
    new CreditSourceInvoices($invoice, $transactions),
    $credits,
    $calculator,
    new CreditNoteNumberFormatter(),
    new _Time($timestamp)
);

$creditNote = $useCase->execute('original-invoice', 'Opdracht volledig geannuleerd.');
adminInvoiceAssert($creditNote->getNumber() === 'CN-2026-0007', 'Creditnota moet een eigen herkenbaar nummer krijgen.');
adminInvoiceAssert($creditNote->getOriginalInvoiceNumber() === '2026-0042', 'Creditnota moet naar het immutable bronnummer verwijzen.');
adminInvoiceAssert($creditNote->getTotals()->getNet()->getMinorUnits() === -12000, 'Creditnota moet het volledige nettobedrag negatief spiegelen.');
adminInvoiceAssert($creditNote->getTotals()->getTax()->getMinorUnits() === -2280, 'Creditnota moet btw per oorspronkelijke regel negatief spiegelen.');
adminInvoiceAssert($creditNote->getTotals()->getGross()->getMinorUnits() === -14280, 'Creditnota moet het volledige brutobedrag negatief spiegelen.');
adminInvoiceAssert(count($creditNote->getTotals()->getTaxSummaries()) === 2, 'Meerdere btw-groepen moeten behouden blijven.');
adminInvoiceAssert($creditNote->getLines()[0]->getUnitPrice()->getMinorUnits() === -5000, 'Regelprijs moet negatief worden gekopieerd.');
adminInvoiceAssert($invoice->getStatus()->getValue() === InvoiceStatus::CREDITED, 'Bronfactuur moet na opslag gecrediteerd zijn.');
adminInvoiceAssert($credits->invoiceUpdates === 1, 'Bronfactuurstatus moet exact eenmaal worden opgeslagen.');
adminInvoiceAssert($sequences->reservation === ['credit-tenant', 'credit_note', '2026'], 'Creditnota moet een eigen tenant-/jaarreeks gebruiken.');
adminInvoiceAssert($transactions->trace === ['begin', 'commit'], 'Creditnota en bronstatus moeten samen committen.');

adminInvoiceAssertThrows(DomainException::class, function () use ($useCase): void {
    $useCase->execute('original-invoice', 'Nogmaals crediteren.');
}, 'Een reeds gecrediteerde factuur mag niet opnieuw volledig worden gecrediteerd.');
adminInvoiceAssert(array_slice($transactions->trace, -2) === ['begin', 'rollback'], 'Mislukte hercreditering moet rollbacken.');

adminInvoiceAssertThrows(InvalidArgumentException::class, function () use ($useCase): void {
    $useCase->execute('original-invoice', '');
}, 'Een creditnota zonder reden moet vóór de transactie worden geweigerd.');

echo "AdminInvoice full credit note tests passed.\n";
