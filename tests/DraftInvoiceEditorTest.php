<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/src/Application/UseCase/RefreshDraftInvoiceCustomer.php';

use Flexgrid\Modules\AdminCore\Context\TenantContext;
use Flexgrid\Modules\AdminCore\Contract\PublicIdGeneratorInterface;
use Flexgrid\Modules\AdminCore\Contract\TransactionManagerInterface;
use Flexgrid\Modules\AdminCore\ValueObject\Currency;
use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminInvoice\Application\Command\AddInvoiceLineCommand;
use Flexgrid\Modules\AdminInvoice\Application\Command\UpdateDraftInvoiceCommand;
use Flexgrid\Modules\AdminInvoice\Application\Command\UpdateInvoiceLineCommand;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\AddInvoiceLine;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\CorrectInvoiceIssueDate;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\DeleteDraftInvoice;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\GetInvoice;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\RemoveInvoiceLine;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\RefreshDraftInvoiceCustomer;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\UpdateDraftInvoice;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\UpdateInvoiceLine;
use Flexgrid\Modules\AdminInvoice\Contract\InvoiceRepositoryInterface;
use Flexgrid\Modules\AdminInvoice\Domain\Model\Invoice;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\AddressSnapshot;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\CalendarDate;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\InvoiceTotals;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\PartySnapshot;
use Flexgrid\Modules\AdminInvoice\Exception\InvoiceNotFoundException;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceCalculator;
use Flexgrid\Utils\_Time;

final class EditorInvoiceTransactions implements TransactionManagerInterface
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

final class EditorInvoiceIds implements PublicIdGeneratorInterface
{
    public function generate(): string
    {
        return 'editor-line-1';
    }
}

final class EditorInvoiceRepository implements InvoiceRepositoryInterface
{
    /** @var Invoice|null */
    public $invoice;
    /** @var InvoiceTotals|null */
    public $totals;
    /** @var int|null */
    public $updatedAt;
    /** @var int */
    public $lockCount = 0;

    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }

    public function insert(Invoice $invoice, InvoiceTotals $totals, int $createdAt): void
    {
        throw new RuntimeException('not used');
    }

    public function findByPublicId(TenantId $tenantId, string $publicId): ?Invoice
    {
        return $this->matches($tenantId, $publicId) ? $this->invoice : null;
    }

    public function findByPublicIdForUpdate(TenantId $tenantId, string $publicId): ?Invoice
    {
        $this->lockCount++;
        return $this->findByPublicId($tenantId, $publicId);
    }

    public function findByExternalReference(TenantId $tenantId, string $source, string $externalId): ?Invoice
    {
        return null;
    }

    public function updateDraft(Invoice $invoice, InvoiceTotals $totals, int $updatedAt): void
    {
        $this->invoice = $invoice;
        $this->totals = $totals;
        $this->updatedAt = $updatedAt;
    }

    public function finalize(Invoice $invoice): void
    {
        throw new RuntimeException('not used');
    }

    public function correctIssueDate(Invoice $invoice, int $updatedAt): void
    {
        $this->invoice = $invoice;
        $this->updatedAt = $updatedAt;
    }

    public function deleteDraft(Invoice $invoice): void
    {
        adminInvoiceAssert($this->invoice === $invoice, 'Alleen de geladen conceptfactuur mag worden verwijderd.');
        $this->invoice = null;
    }

    private function matches(TenantId $tenantId, string $publicId): bool
    {
        return $this->invoice !== null && $this->invoice->getTenantId()->equals($tenantId)
            && $this->invoice->getPublicId() === $publicId;
    }
}

$tenant = new TenantId('editor-tenant');
$invoice = new Invoice(
    'editor-invoice',
    $tenant,
    new Currency('EUR'),
    new PartySnapshot('Eerste klant'),
    new AddressSnapshot('Oud 1', '1000 AA', 'Utrecht', 'NL'),
    new CalendarDate('2026-09-18'),
    new CalendarDate('2026-10-18')
);
$repository = new EditorInvoiceRepository($invoice);
$transactions = new EditorInvoiceTransactions();
$context = new TenantContext($tenant);
$calculator = new InvoiceCalculator();
$clock = new _Time(1770001234);

$updated = (new UpdateDraftInvoice($context, $transactions, $repository, $calculator, $clock))->execute(
    new UpdateDraftInvoiceCommand(
        'editor-invoice',
        '2026-09-20',
        '2026-10-20',
        ['name' => 'Nieuwe klant', 'email' => 'nieuw@example.test', 'source_public_id' => 'customer-public-update'],
        ['line_1' => 'Nieuw 2', 'postal_code' => '2000 BB', 'city' => 'Eindhoven', 'country_code' => 'NL'],
        'project-42',
        'Interne notitie'
    )
);
adminInvoiceAssert($updated->getCustomerSnapshot()->getName() === 'Nieuwe klant', 'Draftupdate moet klantsnapshot vervangen.');
adminInvoiceAssert($updated->getCustomerSnapshot()->getSourcePublicId() === 'customer-public-update', 'Draftupdate moet geselecteerde publieke Customer-id bewaren.');
adminInvoiceAssert($updated->getIssueDate()->getValue() === '2026-09-20', 'Draftupdate moet factuurdatum wijzigen.');
adminInvoiceAssert($updated->getCustomerReference() === 'project-42', 'Draftupdate moet klantreferentie wijzigen.');
adminInvoiceAssert($repository->updatedAt === 1770001234, 'Draftupdate moet de geïnjecteerde klok gebruiken.');

$withLine = (new AddInvoiceLine(
    $context,
    new EditorInvoiceIds(),
    $transactions,
    $repository,
    $calculator,
    $clock
))->execute(new AddInvoiceLineCommand(
    'editor-invoice',
    'Ontwikkeling',
    '2',
    'uur',
    '50,00',
    '21',
    'standard'
));
adminInvoiceAssert(count($withLine->getLines()) === 1, 'Regel-use-case moet een regel toevoegen.');
adminInvoiceAssert($repository->totals->getNet()->getMinorUnits() === 10000, 'Regel moet exact in minor units worden berekend.');
adminInvoiceAssert($repository->totals->getGross()->getMinorUnits() === 12100, 'Regel moet btw en brutototaal herberekenen.');

$changedLine = (new UpdateInvoiceLine($context, $transactions, $repository, $calculator, $clock))->execute(
    new UpdateInvoiceLineCommand(
        'editor-invoice',
        'editor-line-1',
        'Aangepaste ontwikkeling',
        '3',
        'uur',
        '40,00',
        '9',
        'reduced'
    )
);
adminInvoiceAssert($changedLine->getLines()[0]->getPublicId() === 'editor-line-1', 'Regelupdate moet de publieke regel-id behouden.');
adminInvoiceAssert($changedLine->getLines()[0]->getPosition() === 10, 'Regelupdate moet de positie behouden.');
adminInvoiceAssert($changedLine->getLines()[0]->getDescription() === 'Aangepaste ontwikkeling', 'Regelupdate moet de omschrijving vervangen.');
adminInvoiceAssert($repository->totals->getNet()->getMinorUnits() === 12000, 'Regelupdate moet het nettototaal herberekenen.');
adminInvoiceAssert($repository->totals->getGross()->getMinorUnits() === 13080, 'Regelupdate moet gewijzigd btw-tarief exact herberekenen.');

$withoutLine = (new RemoveInvoiceLine($context, $transactions, $repository, $calculator, $clock))->execute(
    'editor-invoice',
    'editor-line-1'
);
adminInvoiceAssert($withoutLine->getLines() === [], 'Verwijder-use-case moet de regel uit het aggregate verwijderen.');
adminInvoiceAssert($repository->totals->getGross()->getMinorUnits() === 0, 'Verwijderen moet de totalen opnieuw berekenen.');
adminInvoiceAssert($repository->lockCount === 4, 'Iedere mutatie moet de tenantgebonden factuur vergrendeld laden.');
adminInvoiceAssert($transactions->trace === ['begin', 'commit', 'begin', 'commit', 'begin', 'commit', 'begin', 'commit'], 'Iedere mutatie moet één transactie gebruiken.');

adminInvoiceAssertThrows(InvoiceNotFoundException::class, function () use ($repository): void {
    (new GetInvoice(
        new TenantContext(new TenantId('andere-tenant')),
        $repository
    ))->execute('editor-invoice');
}, 'Andere tenant mag de factuur niet ophalen.');

$delete = new DeleteDraftInvoice($context, $transactions, $repository);
adminInvoiceAssertThrows(InvoiceNotFoundException::class, function () use ($delete): void {
    $delete->execute('onbekend');
}, 'Onbekend concept mag niet worden verwijderd.');
adminInvoiceAssertThrows(InvoiceNotFoundException::class, function () use ($repository, $transactions): void {
    (new DeleteDraftInvoice(new TenantContext(new TenantId('andere-tenant')), $transactions, $repository))
        ->execute('editor-invoice');
}, 'Andere tenant mag het concept niet verwijderen.');
$delete->execute('editor-invoice');
adminInvoiceAssert($repository->invoice === null, 'Concept moet worden verwijderd.');
adminInvoiceAssert($transactions->trace[count($transactions->trace) - 1] === 'commit', 'Verwijderen moet transactie afsluiten.');

$finalizedInvoice = new Invoice(
    'finalized-invoice', $tenant, new Currency('EUR'), new PartySnapshot('Klant'),
    new AddressSnapshot('Straat 1', '1000 AA', 'Utrecht', 'NL'),
    new CalendarDate('2026-09-18'), new CalendarDate('2026-10-18')
);
$finalizedRepository = new EditorInvoiceRepository($finalizedInvoice);
(new AddInvoiceLine($context, new EditorInvoiceIds(), $transactions, $finalizedRepository, $calculator, $clock))
    ->execute(new AddInvoiceLineCommand('finalized-invoice', 'Regel', '1', 'stuk', '10,00', '21', 'standard'));
$finalizedInvoice->finalize('2026-0001', 1, 1770001234, $calculator->calculate($finalizedInvoice));
adminInvoiceAssertThrows(DomainException::class, function () use ($context, $transactions, $finalizedRepository): void {
    (new DeleteDraftInvoice($context, $transactions, $finalizedRepository))->execute('finalized-invoice');
}, 'Een definitieve factuur mag niet worden verwijderd.');
adminInvoiceAssert($finalizedRepository->invoice === $finalizedInvoice, 'Definitieve factuur moet bewaard blijven.');

$correctDate = new CorrectInvoiceIssueDate($context, $transactions, $finalizedRepository, $clock);
$correctDate->execute('finalized-invoice', '2026-09-21');
adminInvoiceAssert($finalizedInvoice->getIssueDate()->getValue() === '2026-09-21', 'Definitieve factuurdatum moet corrigeerbaar zijn.');
adminInvoiceAssert($finalizedInvoice->getInvoiceNumber() === '2026-0001', 'Correctie mag het factuurnummer niet hernummeren.');
adminInvoiceAssert($finalizedRepository->updatedAt === 1770001234, 'Datumcorrectie moet via de repository worden opgeslagen.');
adminInvoiceAssertThrows(InvoiceNotFoundException::class, function () use ($transactions, $finalizedRepository, $clock): void {
    (new CorrectInvoiceIssueDate(new TenantContext(new TenantId('andere-tenant')), $transactions, $finalizedRepository, $clock))
        ->execute('finalized-invoice', '2026-09-22');
}, 'Andere tenant mag de factuurdatum niet corrigeren.');

$refreshInvoice=new Invoice('refresh-invoice',$tenant,new Currency('EUR'),new PartySnapshot('Oud','','','','','','customer-1'),new AddressSnapshot('Oud 1','1000 AA','Utrecht','NL'),new CalendarDate('2026-09-18'),new CalendarDate('2026-10-18'));
$refreshRepository=new EditorInvoiceRepository($refreshInvoice);
$refreshCustomer=new RefreshDraftInvoiceCustomer($context,$transactions,$refreshRepository,$calculator,$clock);
$selection=new \Flexgrid\Modules\AdminInvoice\Application\ReadModel\InvoiceCustomerSelection('customer-1',['name'=>'Nieuw'],['line_1'=>'Nieuw 2','postal_code'=>'2000 BB','city'=>'Rotterdam','country_code'=>'NL']);
$refreshCustomer->execute('refresh-invoice',$selection);
adminInvoiceAssert($refreshInvoice->getCustomerSnapshot()->getName()==='Nieuw'&&$refreshInvoice->getBillingAddressSnapshot()->toArray()['line_1']==='Nieuw 2','Conceptfactuur moet klantsnapshot bijwerken.');
adminInvoiceAssertThrows(DomainException::class,function()use($refreshCustomer):void{$refreshCustomer->execute('refresh-invoice',new \Flexgrid\Modules\AdminInvoice\Application\ReadModel\InvoiceCustomerSelection('customer-2',['name'=>'Anders'],['line_1'=>'Straat','postal_code'=>'1000 AA','city'=>'Utrecht','country_code'=>'NL']));},'Andere klant mag snapshot niet vervangen.');
$refreshInvoice->addLine(new \Flexgrid\Modules\AdminInvoice\Domain\Model\InvoiceLine('refresh-line','Werk',\Flexgrid\Modules\AdminInvoice\Domain\ValueObject\Quantity::fromDecimal('1'),'stuk',new \Flexgrid\Modules\AdminCore\ValueObject\Money(1000,new Currency('EUR')),new \Flexgrid\Modules\AdminCore\ValueObject\TaxRate(2100),new \Flexgrid\Modules\AdminInvoice\Domain\ValueObject\TaxCategory('standard'),10));
$refreshInvoice->finalize('FAC-REFRESH',1,1770001234,$calculator->calculate($refreshInvoice));
adminInvoiceAssertThrows(DomainException::class,function()use($refreshCustomer,$selection):void{$refreshCustomer->execute('refresh-invoice',$selection);},'Definitieve factuur mag geen nieuwe klantsnapshot krijgen.');
echo "AdminInvoice draft editor tests passed.\n";
