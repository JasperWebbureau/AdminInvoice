<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/bootstrap.php';

use Flexgrid\Modules\AdminCore\Context\TenantContext;
use Flexgrid\Modules\AdminCore\Contract\PublicIdGeneratorInterface;
use Flexgrid\Modules\AdminCore\Infrastructure\PdoTransactionManager;
use Flexgrid\Modules\AdminCore\Infrastructure\PdoNumberSequence;
use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminCore\ValueObject\TaxRate;
use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminInvoice\Application\Command\CreateInvoiceCommand;
use Flexgrid\Modules\AdminInvoice\Application\Command\UpdateInvoiceLineCommand;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\CreateInvoice;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\CorrectInvoiceIssueDate;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\DeleteDraftInvoice;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\CreateFullCreditNote;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\FinalizeInvoice;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\UpdateInvoiceLine;
use Flexgrid\Modules\AdminInvoice\Domain\Model\InvoiceLine;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\CalendarDate;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\InvoiceStatus;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\Quantity;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\TaxCategory;
use Flexgrid\Modules\AdminInvoice\Infrastructure\Persistence\PdoInvoiceRepository;
use Flexgrid\Modules\AdminInvoice\Infrastructure\Persistence\PdoCreditNoteRepository;
use Flexgrid\Modules\AdminInvoice\Service\CreditNoteNumberFormatter;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceCalculator;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceNumberFormatter;
use Flexgrid\Utils\_Time;

final class IntegrationInvoiceIdGenerator implements PublicIdGeneratorInterface
{
    /** @var int */
    private $next = 1;

    public function generate(): string
    {
        return 'integration-' . $this->next++;
    }
}

function integrationInvoiceCommand(string $source = '', string $externalId = ''): CreateInvoiceCommand
{
    return new CreateInvoiceCommand(
        'EUR',
        '2026-09-18',
        '2026-10-18',
        ['name' => 'Integratieklant', 'email' => 'integration@example.test', 'source_public_id' => 'customer-integration-public'],
        ['line_1' => 'Teststraat 1', 'postal_code' => '1000 AA', 'city' => 'Utrecht', 'country_code' => 'NL'],
        [[
            'description' => 'Integratieregel',
            'quantity' => '1.5',
            'unit' => 'uur',
            'unit_price' => '20.00',
            'tax_rate' => '21',
            'tax_category' => 'standard',
            'catalog_item_public_id' => 'catalog-integration-public',
        ]],
        '',
        '',
        $source,
        $externalId
    );
}

$projectRoot = dirname(__DIR__, 4);
require $projectRoot . '/.env.php';
if (!isset($db['host'], $db['name'], $db['username'], $db['password'])) {
    throw new RuntimeException('Databaseconfiguratie ontbreekt in .env.php.');
}

$connection = new PDO(
    'mysql:host=' . $db['host'] . ';dbname=' . $db['name'],
    $db['username'],
    $db['password'],
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);
unset($db);

$tenantId = new TenantId('integration-admin-invoice');
$repository = new PdoInvoiceRepository($connection);
$useCase = new CreateInvoice(
    new TenantContext($tenantId),
    new IntegrationInvoiceIdGenerator(),
    new PdoTransactionManager($connection),
    $repository,
    new InvoiceCalculator(),
    new _Time(1770000000)
);

$connection->beginTransaction();
try {
    $externalInvoice = $useCase->execute(integrationInvoiceCommand('webshop', 'order-integration-1'));
    adminInvoiceAssert($repository->findByPublicId($tenantId, $externalInvoice->getPublicId())
        ->getLines()[0]->getCatalogItemPublicId() === 'catalog-integration-public',
        'Herkomst van een gekopieerd catalogusitem moet na hydration bewaard blijven.');
    $loaded = $repository->findByPublicId($tenantId, $externalInvoice->getPublicId());
    adminInvoiceAssert($loaded !== null, 'PDO-repository moet opgeslagen factuur tenantgebonden terugvinden.');
    adminInvoiceAssert($loaded->getTenantId()->equals($tenantId), 'Gehydrateerde factuur moet dezelfde tenant behouden.');
    adminInvoiceAssert($loaded->getCustomerSnapshot()->getSourcePublicId() === 'customer-integration-public', 'PDO-repository moet Customer-bron-id in snapshot behouden.');
    adminInvoiceAssert(count($loaded->getLines()) === 1, 'PDO-repository moet factuurregels tenantgebonden hydrateren.');
    adminInvoiceAssert($loaded->getLines()[0]->calculateGross()->getMinorUnits() === 3630, 'Gehydrateerde regel moet exact herberekenbaar blijven.');

    $originalLine = $loaded->getLines()[0];
    $createdAtStatement = $connection->prepare(
        'SELECT `created_at` FROM `admin_invoice_line` WHERE `tenant_id` = :tenant_id AND `public_id` = :public_id'
    );
    $createdAtStatement->execute([
        ':tenant_id' => $tenantId->toString(),
        ':public_id' => $originalLine->getPublicId(),
    ]);
    $originalCreatedAt = (int)$createdAtStatement->fetchColumn();

    $locked = $repository->findByPublicIdForUpdate($tenantId, $externalInvoice->getPublicId());
    adminInvoiceAssert($locked !== null, 'PDO-repository moet een draft binnen de actieve transactie kunnen vergrendelen.');
    $locked->updateDraftDetails(
        new CalendarDate('2026-09-20'),
        new CalendarDate('2026-10-20'),
        'integratie-project',
        'Bijgewerkt via aggregate-save'
    );
    $locked->addLine(new InvoiceLine(
        'integration-added-line',
        'Extra regel',
        Quantity::fromDecimal('1'),
        'stuk',
        Money::fromDecimal('10.00', $locked->getCurrency()),
        TaxRate::fromPercentage('21'),
        new TaxCategory(TaxCategory::STANDARD),
        20
    ));
    $repository->updateDraft($locked, (new InvoiceCalculator())->calculate($locked), 1770000100);

    $updated = $repository->findByPublicId($tenantId, $externalInvoice->getPublicId());
    adminInvoiceAssert($updated !== null && count($updated->getLines()) === 2, 'Aggregate-save moet alle factuurregels terugschrijven.');
    adminInvoiceAssert($updated->getNotes() === 'Bijgewerkt via aggregate-save', 'Aggregate-save moet draftvelden terugschrijven.');
    adminInvoiceAssert((new InvoiceCalculator())->calculate($updated)->getGross()->getMinorUnits() === 4840, 'Aggregate-save moet exacte nieuwe totalen bewaren.');
    $createdAtStatement->execute([
        ':tenant_id' => $tenantId->toString(),
        ':public_id' => $originalLine->getPublicId(),
    ]);
    adminInvoiceAssert((int)$createdAtStatement->fetchColumn() === $originalCreatedAt, 'Aggregate-save moet created_at van bestaande regels behouden.');

    $changed = (new UpdateInvoiceLine(
        new TenantContext($tenantId),
        new PdoTransactionManager($connection),
        $repository,
        new InvoiceCalculator(),
        new _Time(1770000200)
    ))->execute(new UpdateInvoiceLineCommand(
        $externalInvoice->getPublicId(),
        'integration-added-line',
        'Aangepaste extra regel',
        '2',
        'stuk',
        '5,00',
        '9',
        'reduced'
    ));
    adminInvoiceAssert($changed->getLines()[1]->getPublicId() === 'integration-added-line', 'PDO-regelupdate moet publieke id behouden.');
    $changedReloaded = $repository->findByPublicId($tenantId, $externalInvoice->getPublicId());
    adminInvoiceAssert($changedReloaded !== null && $changedReloaded->getLines()[1]->getDescription() === 'Aangepaste extra regel', 'PDO-regelupdate moet gewijzigd veld terughydrateren.');
    adminInvoiceAssert((new InvoiceCalculator())->calculate($changedReloaded)->getGross()->getMinorUnits() === 4720, 'PDO-regelupdate moet gewijzigde 9%-totalen exact bewaren.');

    $finalized = (new FinalizeInvoice(
        new TenantContext($tenantId),
        new PdoTransactionManager($connection),
        new PdoNumberSequence($connection),
        $repository,
        new InvoiceCalculator(),
        new InvoiceNumberFormatter(),
        new _Time(1770000300)
    ))->execute($externalInvoice->getPublicId());
    adminInvoiceAssert($finalized->getInvoiceNumber() === '2026-0001', 'PDO-finalisatie moet het eerste tenant-/jaarnummer reserveren.');
    $finalizedReloaded = $repository->findByPublicId($tenantId, $externalInvoice->getPublicId());
    adminInvoiceAssert($finalizedReloaded !== null && $finalizedReloaded->getStatus()->getValue() === InvoiceStatus::FINALIZED, 'PDO-finalisatie moet definitieve status terughydrateren.');
    adminInvoiceAssert($finalizedReloaded->getInvoiceSequenceValue() === 1, 'PDO-finalisatie moet de ruwe sequencewaarde bewaren.');
    adminInvoiceAssert($finalizedReloaded->getFinalizedTotals()->getGross()->getMinorUnits() === 4720, 'PDO-finalisatie moet de exacte totalensnapshot bewaren.');

    (new CorrectInvoiceIssueDate(
        new TenantContext($tenantId),
        new PdoTransactionManager($connection),
        $repository,
        new _Time(1770000400)
    ))->execute($externalInvoice->getPublicId(), '2026-09-19');
    $corrected = $repository->findByPublicId($tenantId, $externalInvoice->getPublicId());
    adminInvoiceAssert($corrected !== null && $corrected->getIssueDate()->getValue() === '2026-09-19',
        'PDO-repository moet de gecorrigeerde definitieve factuurdatum terugladen.');
    adminInvoiceAssert($corrected->getInvoiceNumber() === '2026-0001'
        && $corrected->getFinalizedTotals()->getGross()->getMinorUnits() === 4720,
        'Datumcorrectie mag nummer en totalensnapshot niet wijzigen.');

    $creditRepository = new PdoCreditNoteRepository($connection);
    $creditNote = (new CreateFullCreditNote(
        new TenantContext($tenantId),
        new IntegrationInvoiceIdGenerator(),
        new PdoTransactionManager($connection),
        new PdoNumberSequence($connection),
        $repository,
        $creditRepository,
        new InvoiceCalculator(),
        new CreditNoteNumberFormatter(),
        new _Time((int)strtotime('2026-09-20 12:00:00 UTC'))
    ))->execute($externalInvoice->getPublicId(), 'Volledige integratiecreditering.');
    adminInvoiceAssert($creditNote->getNumber() === 'CN-2026-0001', 'Creditnota moet een eigen eerste tenant-/jaarnummer reserveren.');
    adminInvoiceAssert($creditNote->getTotals()->getGross()->getMinorUnits() === -4720, 'Creditnota moet het definitieve factuurtotaal exact negatief spiegelen.');
    $creditReloaded = $creditRepository->findByPublicId($tenantId, $creditNote->getPublicId());
    adminInvoiceAssert($creditReloaded !== null && count($creditReloaded->getLines()) === 2, 'PDO-creditnotarepository moet alle regels hydrateren.');
    adminInvoiceAssert($creditReloaded->getOriginalInvoiceNumber() === '2026-0001', 'Creditnota moet het bronfactuurnummer bewaren.');
    adminInvoiceAssert($creditRepository->findByPublicId(new TenantId('integration-other-tenant'), $creditNote->getPublicId()) === null, 'Andere tenant mag creditnota niet lezen.');
    $creditedInvoice = $repository->findByPublicId($tenantId, $externalInvoice->getPublicId());
    adminInvoiceAssert($creditedInvoice !== null && $creditedInvoice->getStatus()->getValue() === InvoiceStatus::CREDITED, 'Bronfactuurstatus moet atomair gecrediteerd zijn.');

    $retry = $useCase->execute(integrationInvoiceCommand('webshop', 'order-integration-1'));
    adminInvoiceAssert($retry->getPublicId() === $externalInvoice->getPublicId(), 'Externe retry moet dezelfde opgeslagen factuur retourneren.');

    $firstNormal = $useCase->execute(integrationInvoiceCommand());
    $secondNormal = $useCase->execute(integrationInvoiceCommand());
    adminInvoiceAssert($firstNormal->getPublicId() !== $secondNormal->getPublicId(), 'Facturen zonder externe referentie mogen naast elkaar bestaan.');

    $deleteDraft = new DeleteDraftInvoice(
        new TenantContext($tenantId),
        new PdoTransactionManager($connection),
        $repository
    );
    adminInvoiceAssertThrows(\DomainException::class, function () use ($deleteDraft, $externalInvoice): void {
        $deleteDraft->execute($externalInvoice->getPublicId());
    }, 'Definitieve of gecrediteerde facturen mogen niet worden verwijderd.');
    $deleteDraft->execute($firstNormal->getPublicId());
    adminInvoiceAssert($repository->findByPublicId($tenantId, $firstNormal->getPublicId()) === null,
        'Verwijderd concept mag niet meer worden geladen.');
    $deletedLines = $connection->prepare(
        'SELECT COUNT(*) FROM `admin_invoice_line` WHERE `tenant_id` = :tenant_id AND `public_id` = :public_id'
    );
    $deletedLines->execute([':tenant_id' => $tenantId->toString(), ':public_id' => $firstNormal->getLines()[0]->getPublicId()]);
    adminInvoiceAssert((int)$deletedLines->fetchColumn() === 0, 'Verwijderen moet ook alle conceptregels opruimen.');

    adminInvoiceAssert(
        $repository->findByPublicId(new TenantId('integration-other-tenant'), $externalInvoice->getPublicId()) === null,
        'Tenant A mag factuur van tenant B niet lezen.'
    );

    $countStatement = $connection->prepare('SELECT COUNT(*) FROM `admin_invoice` WHERE `tenant_id` = :tenant_id');
    $countStatement->execute([':tenant_id' => $tenantId->toString()]);
    adminInvoiceAssert((int)$countStatement->fetchColumn() === 2, 'Verwijderd concept mag niet blijven staan.');

    $nullStatement = $connection->prepare(
        'SELECT COUNT(*) FROM `admin_invoice` '
        . 'WHERE `tenant_id` = :tenant_id AND `source` IS NULL AND `external_id` IS NULL AND `invoice_number` IS NULL'
    );
    $nullStatement->execute([':tenant_id' => $tenantId->toString()]);
    adminInvoiceAssert((int)$nullStatement->fetchColumn() === 1, 'Lege optionele unique velden moeten als SQL NULL worden opgeslagen.');

    $connection->rollBack();
} catch (Throwable $throwable) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
    throw $throwable;
}

$cleanupStatement = $connection->prepare('SELECT COUNT(*) FROM `admin_invoice` WHERE `tenant_id` = :tenant_id');
$cleanupStatement->execute([':tenant_id' => $tenantId->toString()]);
adminInvoiceAssert((int)$cleanupStatement->fetchColumn() === 0, 'Integratietest mag geen factuurfixtures achterlaten.');
$creditCleanupStatement = $connection->prepare('SELECT COUNT(*) FROM `admin_credit_note` WHERE `tenant_id` = :tenant_id');
$creditCleanupStatement->execute([':tenant_id' => $tenantId->toString()]);
adminInvoiceAssert((int)$creditCleanupStatement->fetchColumn() === 0, 'Integratietest mag geen creditnotafixtures achterlaten.');

echo "AdminInvoice PDO repository tests passed.\n";
