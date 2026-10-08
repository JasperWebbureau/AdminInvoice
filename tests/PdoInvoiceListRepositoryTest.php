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
use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminInvoice\Application\Command\CreateInvoiceCommand;
use Flexgrid\Modules\AdminInvoice\Application\Query\InvoiceListQuery;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\CreateInvoice;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\CreateFullCreditNote;
use Flexgrid\Modules\AdminInvoice\Infrastructure\Persistence\PdoCreditNoteRepository;
use Flexgrid\Modules\AdminInvoice\Infrastructure\Persistence\PdoInvoiceListRepository;
use Flexgrid\Modules\AdminInvoice\Infrastructure\Persistence\PdoInvoiceRepository;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceCalculator;
use Flexgrid\Modules\AdminInvoice\Service\CreditNoteNumberFormatter;
use Flexgrid\Utils\_Time;

final class InvoiceListIntegrationIds implements PublicIdGeneratorInterface
{
    /** @var int */ private $next = 1;
    public function generate(): string { return 'list-integration-' . $this->next++; }
}

function invoiceListIntegrationCommand(int $number, string $customerSourcePublicId = ''): CreateInvoiceCommand
{
    $day = str_pad((string)$number, 2, '0', STR_PAD_LEFT);
    return new CreateInvoiceCommand(
        'EUR',
        '2026-09-' . $day,
        '2026-10-' . $day,
        ['name' => 'Klant ' . $day, 'source_public_id' => $customerSourcePublicId],
        ['line_1' => 'Straat ' . $number, 'postal_code' => '1000 AA', 'city' => 'Utrecht', 'country_code' => 'NL'],
        [[
            'description' => 'Regel ' . $number,
            'quantity' => '1',
            'unit' => 'stuk',
            'unit_price' => (string)$number . '.00',
            'tax_rate' => '21',
            'tax_category' => 'standard',
        ]]
    );
}

$projectRoot = dirname(__DIR__, 4);
require $projectRoot . '/.env.php';
$connection = new PDO(
    'mysql:host=' . $db['host'] . ';dbname=' . $db['name'],
    $db['username'],
    $db['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
unset($db);

$tenantId = new TenantId('integration-admin-invoice-list');
$idsGenerator = new InvoiceListIntegrationIds();
$create = new CreateInvoice(
    new TenantContext($tenantId),
    $idsGenerator,
    new PdoTransactionManager($connection),
    new PdoInvoiceRepository($connection),
    new InvoiceCalculator(),
    new _Time(1770000000)
);
$lists = new PdoInvoiceListRepository($connection);

$connection->beginTransaction();
try {
    for ($number = 1; $number <= 12; $number++) {
        $create->execute(invoiceListIntegrationCommand(
            $number,
            $number <= 2 ? 'history-customer-public-id' : ''
        ));
    }

    $pageTwo = $lists->search($tenantId, new InvoiceListQuery('', 'draft', '', 2026, 'issue_date', 'asc', 2, 10));
    adminInvoiceAssert($pageTwo->getTotal() === 12, 'PDO-lijst moet tenantgebonden totaal tellen.');
    adminInvoiceAssert($pageTwo->getPage() === 2 && count($pageTwo->getItems()) === 2, 'PDO-lijst moet correct pagineren.');
    adminInvoiceAssert($pageTwo->getItems()[0]->getIssueDate() === '2026-09-11', 'PDO-lijst moet whitelist-sortering toepassen.');
    $quarterThree = $lists->search($tenantId, new InvoiceListQuery('', '', '', 2026, 'issue_date', 'desc', 1, 25, '', 3));
    $quarterTwo = $lists->search($tenantId, new InvoiceListQuery('', '', '', 2026, 'issue_date', 'desc', 1, 25, '', 2));
    adminInvoiceAssert($quarterThree->getTotal() === 12 && $quarterTwo->getTotal() === 0, 'Factuurlijst moet op kwartaal begrenzen.');
    adminInvoiceAssert($lists->getSummaries($tenantId, 2026, 3)[0]->getInvoiceCount() === 12, 'Factuursamenvatting moet hetzelfde kwartaal gebruiken.');
    adminInvoiceAssert($lists->getSummaries($tenantId, 2026, 2) === [], 'Leeg kwartaal mag geen factuursamenvatting bevatten.');

    $search = $lists->search($tenantId, new InvoiceListQuery('Klant 03'));
    adminInvoiceAssert($search->getTotal() === 1, 'PDO-lijst moet in klantsnapshot kunnen zoeken.');
    adminInvoiceAssert($search->getItems()[0]->getCustomerName() === 'Klant 03', 'PDO-lijst moet klantnaam uit snapshot lezen.');
    adminInvoiceAssert(
        $lists->search(new TenantId('other-list-tenant'), new InvoiceListQuery())->getTotal() === 0,
        'PDO-lijst mag geen facturen van een andere tenant tonen.'
    );
    adminInvoiceAssert($lists->getAvailableYears($tenantId) === [2026], 'Jaarfilter moet alleen tenantjaren tonen.');

    $ids = $connection->prepare('SELECT `id`, `public_id` FROM `admin_invoice` WHERE `tenant_id` = :tenant_id ORDER BY `issue_date` ASC LIMIT 2');
    $ids->execute([':tenant_id' => $tenantId->toString()]);
    $invoiceRows = $ids->fetchAll();
    $invoiceIds = array_map('intval', array_column($invoiceRows, 'id'));
    $finalize = $connection->prepare(
        'UPDATE `admin_invoice` SET `status` = \'final\', `payment_status` = :payment_status, '
        . '`invoice_number` = :invoice_number, `invoice_sequence_value` = :sequence_value, `finalized_at` = 1770000000 '
        . 'WHERE `id` = :id AND `tenant_id` = :tenant_id'
    );
    $finalize->execute([':payment_status' => 'paid', ':invoice_number' => '2026-0001', ':sequence_value' => 1, ':id' => $invoiceIds[0], ':tenant_id' => $tenantId->toString()]);
    $finalize->execute([':payment_status' => 'unpaid', ':invoice_number' => '2026-0002', ':sequence_value' => 2, ':id' => $invoiceIds[1], ':tenant_id' => $tenantId->toString()]);

    $summaries = $lists->getSummaries($tenantId, 2026);
    adminInvoiceAssert(count($summaries) === 1 && $summaries[0]->getInvoiceCount() === 12, 'Samenvatting moet per valuta groeperen.');
    adminInvoiceAssert($summaries[0]->getRevenueNetMinor() === 300, 'Omzet mag alleen definitieve nettobedragen sommeren.');
    adminInvoiceAssert($summaries[0]->getPaidGrossMinor() === 121, 'Betaald moet alleen definitieve betaalde bruto bedragen sommeren.');
    adminInvoiceAssert($summaries[0]->getOutstandingGrossMinor() === 242, 'Openstaand moet alleen definitieve onbetaalde bruto bedragen sommeren.');

    $creditNote = (new CreateFullCreditNote(
        new TenantContext($tenantId),
        $idsGenerator,
        new PdoTransactionManager($connection),
        new PdoNumberSequence($connection),
        new PdoInvoiceRepository($connection),
        new PdoCreditNoteRepository($connection),
        new InvoiceCalculator(),
        new CreditNoteNumberFormatter(),
        new _Time(strtotime('2026-10-15 12:00:00 UTC'))
    ))->execute((string)$invoiceRows[0]['public_id'], 'Volledige overzichtscreditering.');

    $customerHistory = $lists->findRecentByCustomer($tenantId, 'history-customer-public-id', 5);
    adminInvoiceAssert(count($customerHistory) === 3, 'Klanthistorie moet twee facturen en de afgeleide creditnota vinden.');
    adminInvoiceAssert($customerHistory[0]->isCreditNote(), 'Klanthistorie moet nieuwste document eerst tonen.');
    adminInvoiceAssert(count($lists->findRecentByCustomer($tenantId, 'history-customer-public-id', 2)) === 2, 'Klanthistorie moet de server-side limiet respecteren.');
    adminInvoiceAssert($lists->findRecentByCustomer($tenantId, 'other-customer-public-id', 5) === [], 'Klanthistorie mag geen documenten van een andere klant tonen.');
    adminInvoiceAssert($lists->findRecentByCustomer(new TenantId('other-list-tenant'), 'history-customer-public-id', 5) === [], 'Klanthistorie moet strikt tenantgebonden blijven.');

    $allDocuments = $lists->search($tenantId, new InvoiceListQuery('', '', '', 2026, 'issue_date', 'desc', 1, 25));
    adminInvoiceAssert($allDocuments->getTotal() === 13, 'Gecombineerd overzicht moet twaalf facturen en één creditnota tellen.');

    $creditNotes = $lists->search($tenantId, new InvoiceListQuery('', '', '', 2026, 'issue_date', 'desc', 1, 10, 'credit_note'));
    adminInvoiceAssert($creditNotes->getTotal() === 1, 'Documenttypefilter moet uitsluitend creditnota’s teruggeven.');
    adminInvoiceAssert($creditNotes->getItems()[0]->isCreditNote(), 'Creditnotarij moet als creditnota gehydrateerd worden.');
    adminInvoiceAssert($creditNotes->getItems()[0]->getInvoiceNumber() === $creditNote->getNumber(), 'Creditnotanummer moet in het documentoverzicht staan.');
    adminInvoiceAssert($creditNotes->getItems()[0]->getOriginalInvoiceNumber() === '2026-0001', 'Creditnotarij moet het bronfactuurnummer bevatten.');
    adminInvoiceAssert($creditNotes->getItems()[0]->getGrossTotalMinor() === -121, 'Creditnotarij moet het negatieve documenttotaal behouden.');

    $creditSearch = $lists->search($tenantId, new InvoiceListQuery($creditNote->getNumber()));
    adminInvoiceAssert($creditSearch->getTotal() === 1 && $creditSearch->getItems()[0]->isCreditNote(), 'Zoeken op creditnotanummer moet het creditdocument vinden.');

    $reasonSearch = $lists->search($tenantId, new InvoiceListQuery('overzichtscreditering'));
    adminInvoiceAssert($reasonSearch->getTotal() === 1 && $reasonSearch->getItems()[0]->isCreditNote(), 'Zoeken moet ook de creditreden meenemen.');

    $sourceSearch = $lists->search($tenantId, new InvoiceListQuery('2026-0001'));
    $sourceSearchHasCredit = false;
    foreach ($sourceSearch->getItems() as $sourceSearchItem) {
        $sourceSearchHasCredit = $sourceSearchHasCredit || $sourceSearchItem->isCreditNote();
    }
    adminInvoiceAssert($sourceSearchHasCredit, 'Zoeken op bronfactuurnummer moet de gekoppelde creditnota vinden.');

    adminInvoiceAssert(
        $lists->search(new TenantId('other-list-tenant'), new InvoiceListQuery())->getTotal() === 0,
        'Gecombineerde documentquery mag ook na creditering geen andere tenant zien.'
    );

    $invoiceDocuments = $lists->search($tenantId, new InvoiceListQuery('', '', '', 2026, 'issue_date', 'desc', 1, 25, 'invoice'));
    adminInvoiceAssert($invoiceDocuments->getTotal() === 12, 'Factuurfilter mag creditnota’s uitsluiten.');

    $connection->rollBack();
} catch (Throwable $throwable) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
    throw $throwable;
}

$cleanup = $connection->prepare('SELECT COUNT(*) FROM `admin_invoice` WHERE `tenant_id` = :tenant_id');
$cleanup->execute([':tenant_id' => $tenantId->toString()]);
adminInvoiceAssert((int)$cleanup->fetchColumn() === 0, 'Lijstintegratietest mag geen fixtures achterlaten.');
$creditCleanup = $connection->prepare('SELECT COUNT(*) FROM `admin_credit_note` WHERE `tenant_id` = :tenant_id');
$creditCleanup->execute([':tenant_id' => $tenantId->toString()]);
adminInvoiceAssert((int)$creditCleanup->fetchColumn() === 0, 'Lijstintegratietest mag geen creditnotafixtures achterlaten.');

echo "AdminInvoice PDO list tests passed.\n";
