<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/src/Service/InvoiceCatalog.php';
require_once dirname(__DIR__) . '/src/Service/RecurringInvoices.php';

use Flexgrid\Modules\AdminCore\Contract\PublicIdGeneratorInterface;
use Flexgrid\Modules\AdminCore\ValueObject\Currency;
use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminInvoice\Application\Command\CreateInvoiceCommand;
use Flexgrid\Modules\AdminInvoice\Application\ReadModel\InvoiceCustomerSelection;
use Flexgrid\Modules\AdminInvoice\Contract\InvoiceCustomerProviderInterface;
use Flexgrid\Modules\AdminInvoice\Contract\InvoiceDraftCreatorInterface;
use Flexgrid\Modules\AdminInvoice\Contract\RecurringInvoiceLinesExtensionInterface;
use Flexgrid\Modules\AdminInvoice\Contract\RecurringInvoiceAdditionalLinesExtensionInterface;
use Flexgrid\Modules\AdminInvoice\Domain\Model\Invoice;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\AddressSnapshot;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\CalendarDate;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\PartySnapshot;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceCatalog;
use Flexgrid\Modules\AdminInvoice\Service\RecurringInvoices;
use Flexgrid\Modules\AdminInvoice\Service\RecurringInvoiceLinesExtensionLoader;

final class CatalogTestIds implements PublicIdGeneratorInterface
{
    private $number = 0;
    public function generate(): string { return 'test-' . ++$this->number; }
}

final class CatalogTestCustomers implements InvoiceCustomerProviderInterface
{
    public $available = true;
    public $blockedId = '';
    public function search(string $query, int $limit = 10): array { return []; }
    public function find(string $publicId): ?InvoiceCustomerSelection
    {
        return $this->available && $publicId !== $this->blockedId
            && in_array($publicId, ['customer-1', 'customer-2'], true)
            ? new InvoiceCustomerSelection($publicId, ['name' => 'Voorbeeld klant ' . $publicId], [
                'line_1' => $publicId === 'customer-2' ? '' : 'Teststraat 1',
                'postal_code' => $publicId === 'customer-2' ? '' : '1000 AA',
                'city' => $publicId === 'customer-2' ? '' : 'Amsterdam',
                'country_code' => $publicId === 'customer-2' ? '' : 'NL',
            ]) : null;
    }
}

final class CatalogTestCreator implements InvoiceDraftCreatorInterface
{
    public $commands = [];
    public function execute(CreateInvoiceCommand $command): Invoice
    {
        $this->commands[] = $command;
        $party = $command->getCustomerSnapshot();
        $address = $command->getBillingAddressSnapshot();
        return new Invoice(
            'invoice-' . count($this->commands), new TenantId('one'),
            new Currency($command->getCurrency()),
            new PartySnapshot($party['name'], '', '', '', '', '', $party['source_public_id']),
            new AddressSnapshot($address['line_1'], $address['postal_code'],
                $address['city'], $address['country_code']),
            new CalendarDate($command->getIssueDate()), new CalendarDate($command->getDueDate())
        );
    }
}

final class CatalogTestLinesExtension implements RecurringInvoiceLinesExtensionInterface
{
    public $validated = false;
    public function validate(array $plan, InvoiceCustomerSelection $customer, InvoiceCatalog $catalog): void
    {
        $this->validated = true;
    }

    public function resolve(array $plan, InvoiceCustomerSelection $customer, InvoiceCatalog $catalog, string $issueDate): ?array
    {
        $line = $catalog->line((string)$plan['catalog_item_public_id'], 'EUR');
        $line['quantity'] = '7';
        $line['description'] = 'Zeven klikken';
        return [$line];
    }
}

final class CatalogTestAdditionalLinesExtension implements RecurringInvoiceLinesExtensionInterface, RecurringInvoiceAdditionalLinesExtensionInterface
{
    public $include = true;

    public function validate(array $plan, InvoiceCustomerSelection $customer, InvoiceCatalog $catalog): void {}

    public function resolve(array $plan, InvoiceCustomerSelection $customer, InvoiceCatalog $catalog, string $issueDate): ?array
    {
        return null;
    }

    public function additionalLines(
        array $plan,
        InvoiceCustomerSelection $customer,
        InvoiceCatalog $catalog,
        string $issueDate,
        array $assignedItemPublicIds,
        array $existingLines
    ): array {
        if (!$this->include) {
            return [];
        }
        return ['currency' => 'EUR', 'lines' => [[
            'description' => 'Automatische kosten', 'quantity' => '2', 'unit' => 'klik',
            'unit_price' => '0.50', 'tax_rate' => '21', 'tax_category' => 'standard',
        ]]];
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE admin_invoice_catalog_item (
    tenant_id TEXT, public_id TEXT, name TEXT, item_type TEXT, parent_public_id TEXT,
    variant_key TEXT, choose_function TEXT, description TEXT, quantity TEXT, unit TEXT,
    unit_price TEXT, currency TEXT, tax_rate TEXT, tax_category TEXT, active INTEGER,
    created_at INTEGER, updated_at INTEGER, UNIQUE(tenant_id, public_id))');
$pdo->exec('CREATE TABLE admin_invoice_recurring (
    id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id TEXT, public_id TEXT, name TEXT,
    customer_public_id TEXT, catalog_item_public_id TEXT, next_issue_date TEXT,
    day_of_month INTEGER, frequency_months INTEGER, due_days INTEGER, active INTEGER,
    last_error TEXT, created_at INTEGER, updated_at INTEGER, UNIQUE(tenant_id, public_id))');
$pdo->exec('CREATE TABLE admin_invoice_recurring_member (
    id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id TEXT, plan_public_id TEXT,
    customer_public_id TEXT, next_issue_date TEXT, active INTEGER, items_initialized INTEGER,
    last_error TEXT, created_at INTEGER, updated_at INTEGER,
    UNIQUE(tenant_id, plan_public_id, customer_public_id))');
$pdo->exec('CREATE TABLE admin_invoice_recurring_member_item (
    id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id TEXT, plan_public_id TEXT,
    customer_public_id TEXT, catalog_item_public_id TEXT, active INTEGER,
    created_at INTEGER, updated_at INTEGER,
    UNIQUE(tenant_id, plan_public_id, customer_public_id, catalog_item_public_id))');
$pdo->exec('CREATE TABLE admin_invoice_line (
    tenant_id TEXT, catalog_item_public_id TEXT)');
$ids = new CatalogTestIds();
$tenant = new TenantId('one');
$catalog = new InvoiceCatalog($pdo, $tenant, $ids);
$itemId = $catalog->save([
    'name' => 'Hosting', 'description' => 'Hosting {periode}', 'quantity' => '1',
    'unit' => 'maand', 'unit_price' => '12,50', 'currency' => 'EUR',
    'tax_rate' => '21', 'tax_category' => 'standard', 'active' => true,
]);
adminInvoiceAssert(count($catalog->all(true)) === 1, 'Catalogusitem moet voor eigen tenant beschikbaar zijn.');
adminInvoiceAssert($catalog->line($itemId, 'EUR')['unit_price'] === '12,50', 'Prijs moet exact als decimale string worden overgenomen.');
adminInvoiceAssertThrows(InvalidArgumentException::class, function () use ($catalog, $itemId): void {
    $catalog->line($itemId, 'USD');
}, 'Valuta van factuur en catalogusitem moet gelijk zijn.');
adminInvoiceAssert((new InvoiceCatalog($pdo, new TenantId('two'), $ids))->all() === [], 'Catalogus mag geen andere tenant tonen.');

$customers = new CatalogTestCustomers();
$creator = new CatalogTestCreator();
$plans = new RecurringInvoices($pdo, $tenant, $ids, $catalog, $creator, $customers);
$planId = $plans->save([
    'name' => 'Hosting klant', 'customer_public_id' => 'customer-1',
    'catalog_item_public_id' => $itemId, 'next_issue_date' => '2026-01-31',
    'frequency_months' => 1, 'due_days' => 14, 'active' => true,
]);
$preview = $plans->previewDue('2026-01-31');
adminInvoiceAssert($preview['counts']['ready'] === 1 && $preview['rows'][0]['gross'] === '15,13',
    'Proefrun moet dezelfde regels en afgeronde brutoprijs tonen.');
adminInvoiceAssert($creator->commands === [] && $plans->find($planId)['next_issue_date'] === '2026-01-31',
    'Proefrun mag geen factuur maken of schemadatum verschuiven.');
$result = $plans->runDue('2026-01-31');
adminInvoiceAssert($result['created'] === 1 && count($creator->commands) === 1, 'Vervallen schema moet één concept aanmaken.');
adminInvoiceAssert($plans->find($planId)['next_issue_date'] === '2026-02-28', 'Maandeinde moet op de laatste dag van februari blijven.');
adminInvoiceAssert($creator->commands[0]->getLines()[0]['description'] === 'Hosting 01-2026', 'Periode moet in de regelomschrijving komen.');
adminInvoiceAssert($creator->commands[0]->getSource() === 'admin_recurring', 'Herkomst moet idempotent zijn.');
adminInvoiceAssert($plans->runDue('2026-01-31')['created'] === 0, 'Dagtaak mag dezelfde periode niet opnieuw verwerken.');
$customers->available = false;
$failed = $plans->runDue('2026-02-28');
adminInvoiceAssert($failed['failed'] === 1, 'Ontbrekende klant moet zichtbaar als fout terugkomen.');
adminInvoiceAssert($plans->find($planId)['next_issue_date'] === '2026-02-28', 'Mislukte periode moet later opnieuw kunnen.');
$customers->available = true;
adminInvoiceAssertThrows(InvalidArgumentException::class, function () use ($plans, $itemId, $tenant, $ids, $pdo, $catalog, $creator, $customers): void {
    $conflict = new RecurringInvoices($pdo, $tenant, $ids, $catalog, $creator, $customers);
    $conflictId = $conflict->save([
        'name' => 'Conflicterend gedeeld schema', 'catalog_item_public_id' => $itemId,
        'next_issue_date' => date('Y-m-d'), 'frequency_months' => 1, 'due_days' => 14, 'active' => true,
    ]);
    $conflict->enroll($conflictId, 'customer-1');
}, 'Een gedeelde koppeling mag geen actief individueel schema voor hetzelfde item dupliceren.');
$plans->setActive($planId, false);

$customers->available = true;
$sharedCreator = new CatalogTestCreator();
$shared = new RecurringInvoices($pdo, $tenant, $ids, $catalog, $sharedCreator, $customers);
$today = date('Y-m-d');
$sharedId = $shared->save([
    'name' => 'Hosting gedeeld', 'catalog_item_public_id' => $itemId,
    'next_issue_date' => $today, 'frequency_months' => 1, 'due_days' => 14, 'active' => true,
]);
$shared->enroll($sharedId, 'customer-1');
$shared->enroll($sharedId, 'customer-2');
adminInvoiceAssert($shared->previewDue($today)['counts']['ready'] === 2 && $sharedCreator->commands === [],
    'Proefrun moet alle gedeelde klantkoppelingen zonder opslag tonen.');
adminInvoiceAssert(count($shared->membersForCustomer('customer-1')) === 1,
    'Klant moet een gedeeld schema als koppeling zien.');
$sharedResult = $shared->runDue($today);
adminInvoiceAssert($sharedResult['created'] === 2 && count($sharedCreator->commands) === 2,
    'Eén gedeeld schema moet voor beide klanten een afzonderlijk concept maken.');
adminInvoiceAssert($sharedCreator->commands[0]->getExternalId()
    !== $sharedCreator->commands[1]->getExternalId(),
    'Idempotentie moet per schema, klant en factuurdatum uniek zijn.');
adminInvoiceAssert($shared->runDue($today)['created'] === 0,
    'De dagtaak mag dezelfde gedeelde koppeling niet dubbel verwerken.');
$customers->blockedId = 'customer-2';
$nextSharedDate = $shared->membersForCustomer('customer-1')[0]['next_issue_date'];
$partial = $shared->runDue($nextSharedDate);
adminInvoiceAssert($partial['created'] === 1 && $partial['failed'] === 1,
    'Een fout bij een klant mag de andere klant niet tegenhouden.');
adminInvoiceAssert($shared->membersForCustomer('customer-2')[0]['next_issue_date'] === $nextSharedDate,
    'Een mislukte klantkoppeling mag de datum niet overslaan.');
$customers->blockedId = '';
$shared->setMemberActive($sharedId, 'customer-2', false);
adminInvoiceAssert((int)$shared->membersForCustomer('customer-2')[0]['active'] === 0,
    'Een klant moet afzonderlijk gepauzeerd kunnen worden.');
$futureDate = $shared->membersForCustomer('customer-1')[0]['next_issue_date'];
$shared->setActive($sharedId, false);
$shared->setActive($sharedId, true);
adminInvoiceAssert($shared->membersForCustomer('customer-1')[0]['next_issue_date'] === $futureDate,
    'Schema hervatten mag een reeds verwerkte factuurdatum niet opnieuw inplannen.');
adminInvoiceAssertThrows(InvalidArgumentException::class, function () use ($shared, $sharedId): void {
    $shared->enroll($sharedId, 'customer-1');
}, 'Een klant mag niet dubbel aan hetzelfde schema worden gekoppeld.');
$shared->setActive($sharedId, false);

$addonId = $catalog->save([
    'name' => 'Extra optie', 'description' => 'Extra optie {periode}', 'quantity' => '1',
    'unit' => 'maand', 'unit_price' => '3,00', 'currency' => 'EUR',
    'tax_rate' => '21', 'tax_category' => 'standard', 'active' => true,
]);
$shared->addItem($sharedId, 'customer-1', $addonId);
adminInvoiceAssert(count($shared->itemsForCustomerPlan($sharedId, 'customer-1')) === 2,
    'Een bestaand gedeeld schema moet zijn oorspronkelijke item behouden als een extra item wordt toegevoegd.');
$shared->save([
    'public_id' => $sharedId, 'name' => 'Hosting gedeeld',
    'next_issue_date' => $today, 'frequency_months' => 1, 'due_days' => 14, 'active' => false,
]);
adminInvoiceAssert($shared->find($sharedId)['catalog_item_public_id'] === ''
    && count($shared->itemsForCustomerPlan($sharedId, 'customer-2')) === 1,
    'Opslaan moet het oude schema-item eerst naar bestaande klantkoppelingen overzetten.');
$multiCreator = new CatalogTestCreator();
$multi = new RecurringInvoices($pdo, $tenant, $ids, $catalog, $multiCreator, $customers);
$multiId = $multi->save([
    'name' => 'Maandfactuur', 'next_issue_date' => $today,
    'frequency_months' => 1, 'due_days' => 14, 'active' => true,
]);
adminInvoiceAssert($multi->find($multiId)['catalog_item_public_id'] === '',
    'Een nieuw gedeeld schema mag geen vast item vereisen.');
$multi->enroll($multiId, 'customer-1');
adminInvoiceAssert($multi->runDue($today)['failed'] === 1 && $multiCreator->commands === [],
    'Een schema zonder items mag geen lege conceptfactuur maken.');
$multi->addItem($multiId, 'customer-1', $itemId);
$multi->addItem($multiId, 'customer-1', $addonId);
$multi->enroll($multiId, 'customer-2');
$multi->addItem($multiId, 'customer-2', $addonId);
adminInvoiceAssert(count($multi->itemsForCustomerPlan($multiId, 'customer-1')) === 2,
    'Een klantkoppeling moet meerdere items kunnen bevatten.');
adminInvoiceAssert(count(array_filter($multi->allMemberItems(), function (array $row) use ($multiId): bool {
    return $row['plan_public_id'] === $multiId;
})) === 3, 'Klantitems moeten zonder losse query per klant beschikbaar zijn voor projectoverzichten.');
$multiRun = $multi->runDue($today);
$lineCounts = array_map(static function (CreateInvoiceCommand $command): int {
    return count($command->getLines());
}, $multiCreator->commands);
sort($lineCounts);
adminInvoiceAssert($multiRun['created'] === 2 && $lineCounts === [1, 2],
    'Eén schema moet per klant diens eigen items op één conceptfactuur verzamelen.');
$otherId = $multi->save([
    'name' => 'Tweede schema', 'next_issue_date' => $today,
    'frequency_months' => 1, 'due_days' => 14, 'active' => true,
]);
$multi->enroll($otherId, 'customer-1');
adminInvoiceAssertThrows(InvalidArgumentException::class, function () use ($multi, $otherId, $itemId): void {
    $multi->addItem($otherId, 'customer-1', $itemId);
}, 'Hetzelfde item mag niet op twee actieve schema\'s van dezelfde klant worden gefactureerd.');
$multi->setActive($otherId, false);
$multi->setItemActive($multiId, 'customer-1', $addonId, false);
adminInvoiceAssert((int)$multi->itemsForCustomerPlan($multiId, 'customer-1')[1]['active'] === 0,
    'Een extra item moet afzonderlijk pauzeerbaar zijn.');
$multi->setActive($multiId, false);
adminInvoiceAssert(count($multi->membersForPlan($multiId)) === 2,
    'Het schema-overzicht moet beide gekoppelde klanten kunnen tonen.');
$previousInvoiceCount = count($multiCreator->commands);
$multi->unlinkCustomer($multiId, 'customer-2');
$remainingMemberItems = $pdo->prepare(
    'SELECT COUNT(*) FROM admin_invoice_recurring_member_item '
    . 'WHERE tenant_id=? AND plan_public_id=? AND customer_public_id=?'
);
$remainingMemberItems->execute(['one', $multiId, 'customer-2']);
adminInvoiceAssert(count($multi->membersForPlan($multiId)) === 1
    && (int)$remainingMemberItems->fetchColumn() === 0,
    'Ontkoppelen moet alleen deze klantkoppeling en diens schema-items verwijderen.');
adminInvoiceAssert(count($multiCreator->commands) === $previousInvoiceCount,
    'Ontkoppelen mag reeds gemaakte facturen niet aanpassen.');
adminInvoiceAssertThrows(InvalidArgumentException::class, function () use ($multi, $multiId): void {
    $multi->unlinkCustomer($multiId, 'customer-2');
}, 'Een ontbrekende klantkoppeling mag niet opnieuw worden verwijderd.');
adminInvoiceAssertThrows(InvalidArgumentException::class, function () use ($plans, $planId): void {
    $plans->unlinkCustomer($planId, 'customer-1');
}, 'Een individueel oud schema mag niet als gedeelde klantkoppeling worden ontkoppeld.');

$choiceCatalog = new InvoiceCatalog($pdo, $tenant, $ids, [
    'chooseTest' => [
        'label' => 'Testkeuze',
        'variants' => ['small' => 'Klein', 'large' => 'Groot'],
        'callback' => static function ($customer, array $group, array $children, string $date): array {
            $variant = $customer->getPublicId() === 'customer-1' ? 'small' : 'large';
            foreach ($children as $child) {
                if ($child['variant_key'] === $variant) {
                    return ['catalog_item_public_id' => $child['public_id']];
                }
            }
            throw new RuntimeException('Testvariant ontbreekt.');
        },
    ],
]);
$groupId = $choiceCatalog->save([
    'item_type' => 'group', 'name' => 'Basic', 'choose_function' => 'chooseTest', 'active' => true,
]);
$smallId = $choiceCatalog->save([
    'name' => 'Basic klein', 'parent_public_id' => $groupId, 'variant_key' => 'small',
    'description' => 'Basic klein {periode}', 'quantity' => '1', 'unit' => 'maand',
    'unit_price' => '10,00', 'currency' => 'EUR', 'tax_rate' => '21',
    'tax_category' => 'standard', 'active' => true,
]);
$largeId = $choiceCatalog->save([
    'name' => 'Basic groot', 'parent_public_id' => $groupId, 'variant_key' => 'large',
    'description' => 'Basic groot {periode}', 'quantity' => '1', 'unit' => 'maand',
    'unit_price' => '20,00', 'currency' => 'EUR', 'tax_rate' => '21',
    'tax_category' => 'standard', 'active' => true,
]);
adminInvoiceAssert(count($choiceCatalog->allActiveAssignable()) >= 1
    && count($choiceCatalog->children($groupId)) === 2,
    'De groep moet op de klantpagina kiesbaar zijn en twee varianten bevatten.');
adminInvoiceAssertThrows(InvalidArgumentException::class, function () use ($choiceCatalog, $groupId): void {
    $choiceCatalog->line($groupId, 'EUR');
}, 'Een keuzegroep mag niet als gewone factuurregel worden toegevoegd.');
$choiceCreator = new CatalogTestCreator();
$choicePlans = new RecurringInvoices($pdo, $tenant, $ids, $choiceCatalog, $choiceCreator, $customers);
$choicePlanId = $choicePlans->save([
    'name' => 'Basic maandelijks', 'next_issue_date' => $today,
    'frequency_months' => 1, 'due_days' => 14, 'active' => true,
]);
$choicePlans->enroll($choicePlanId, 'customer-1');
$choicePlans->enroll($choicePlanId, 'customer-2');
$choicePlans->addItem($choicePlanId, 'customer-1', $groupId);
$choicePlans->addItem($choicePlanId, 'customer-2', $groupId);
adminInvoiceAssert($choicePlans->previewDue($today)['counts']['ready'] === 2
    && $choiceCreator->commands === [],
    'Proefrun moet ook keuzegroepen per klant oplossen zonder concepten te maken.');
adminInvoiceAssert($choicePlans->runDue($today)['created'] === 2,
    'Een keuzegroep moet voor iedere klant een concept maken.');
$chosenIds = array_map(static function (CreateInvoiceCommand $command): string {
    return $command->getLines()[0]['catalog_item_public_id'];
}, $choiceCreator->commands);
sort($chosenIds);
$expectedIds = [$smallId, $largeId];
sort($expectedIds);
adminInvoiceAssert($chosenIds === $expectedIds,
    'De groep moet per klant het gekozen kinditem op het concept plaatsen.');
$choicePlans->setActive($choicePlanId, false);
$invalidChoiceCatalog = new InvoiceCatalog($pdo, $tenant, $ids, [
    'chooseTest' => ['callback' => static function () use ($itemId): array {
        return ['catalog_item_public_id' => $itemId];
    }],
]);
adminInvoiceAssertThrows(UnexpectedValueException::class, function () use (
    $invalidChoiceCatalog, $groupId, $customers, $today
): void {
    $invalidChoiceCatalog->choose($invalidChoiceCatalog->find($groupId),
        $customers->find('customer-1'), $today);
}, 'Een keuzefunctie mag geen item buiten haar eigen groep teruggeven.');
$skipCatalog = new InvoiceCatalog($pdo, $tenant, $ids, [
    'chooseTest' => ['callback' => static function () { return false; }],
]);
$skipCreator = new CatalogTestCreator();
$skipPlans = new RecurringInvoices($pdo, $tenant, $ids, $skipCatalog, $skipCreator, $customers);
$skipId = $skipPlans->save([
    'name' => 'Overslaan', 'next_issue_date' => $today,
    'frequency_months' => 1, 'due_days' => 14, 'active' => true,
]);
$skipPlans->enroll($skipId, 'customer-1');
$skipPlans->addItem($skipId, 'customer-1', $groupId);
adminInvoiceAssert($skipPlans->previewDue($today)['counts']['skipped'] === 1,
    'Proefrun moet een door de keuzefunctie overgeslagen periode tonen.');
$skipResult = $skipPlans->runDue($today);
adminInvoiceAssert($skipResult['skipped'] === 1 && $skipCreator->commands === [],
    'False uit een keuzefunctie moet de periode zonder lege factuur overslaan.');
$skipPlans->setActive($skipId, false);

$extensionCreator = new CatalogTestCreator();
$linesExtension = new CatalogTestLinesExtension();
$extensionPlans = new RecurringInvoices(
    $pdo, $tenant, $ids, $catalog, $extensionCreator, new CatalogTestCustomers(), $linesExtension
);
$extensionPlans->save([
    'name' => 'Dynamisch', 'customer_public_id' => 'customer-1',
    'catalog_item_public_id' => $itemId, 'next_issue_date' => '2026-04-01',
    'frequency_months' => 1, 'due_days' => 14, 'active' => true,
]);
adminInvoiceAssert($extensionPlans->previewDue('2026-04-01')['rows'][0]['lines'][0] === 'Zeven klikken',
    'Proefrun moet ook projectmatig berekende regels tonen.');
$extensionPlans->runDue('2026-04-01');
adminInvoiceAssert($linesExtension->validated, 'Projectadapter moet schema-instellingen al bij opslaan valideren.');
adminInvoiceAssert($extensionCreator->commands[0]->getLines()[0]['quantity'] === '7',
    'Projectadapter moet het aantal van een terugkerende factuurregel kunnen bepalen.');
$additionalCreator = new CatalogTestCreator();
$additionalExtension = new CatalogTestAdditionalLinesExtension();
$additionalPlans = new RecurringInvoices(
    $pdo, $tenant, $ids, $catalog, $additionalCreator, new CatalogTestCustomers(), $additionalExtension
);
$additionalId = $additionalPlans->save([
    'name' => 'Alleen automatische kosten', 'catalog_item_public_id' => '',
    'next_issue_date' => $today, 'frequency_months' => 1, 'due_days' => 14, 'active' => true,
]);
$additionalPlans->enroll($additionalId, 'customer-2');
$additionalPreview = array_values(array_filter($additionalPlans->previewDue($today)['rows'], static function (array $row): bool {
    return $row['plan_name'] === 'Alleen automatische kosten';
}));
adminInvoiceAssert(count($additionalPreview) === 1 && $additionalPreview[0]['status'] === 'ready'
    && $additionalPreview[0]['lines'] === ['Automatische kosten'],
    'Een projectadapter moet ook zonder toegewezen item een extra regel kunnen toevoegen.');
$additionalPlans->runDue($today);
$additionalCommand = null;
foreach ($additionalCreator->commands as $command) {
    if (strpos($command->getExternalId(), $additionalId . ':customer-2:') === 0) {
        $additionalCommand = $command;
        break;
    }
}
adminInvoiceAssert($additionalCommand !== null
    && $additionalCommand->getLines()[0]['description'] === 'Automatische kosten',
    'De extra regel moet in het concept terechtkomen zonder per-klant-item.');
$additionalExtension->include = false;
$nextPreview = array_values(array_filter(
    $additionalPlans->previewDue(date('Y-m-d', strtotime('+1 month', strtotime($today))))['rows'],
    static function (array $row): bool { return $row['plan_name'] === 'Alleen automatische kosten'; }
));
adminInvoiceAssert(count($nextPreview) === 1 && $nextPreview[0]['status'] === 'skipped',
    'Zonder toegewezen items of extra kosten moet de proefrun geen lege factuur tonen.');
adminInvoiceAssert(
    (new RecurringInvoiceLinesExtensionLoader(dirname(__DIR__, 4) . '/App'))->getExtension()
        instanceof RecurringInvoiceLinesExtensionInterface,
    'Projectadapter moet zonder concrete App-import vanuit AdminInvoice worden ontdekt.'
);

$unusedId = $catalog->save([
    'name' => 'Tijdelijk', 'description' => 'Tijdelijk', 'quantity' => '1',
    'unit' => 'stuk', 'unit_price' => '1.00', 'currency' => 'EUR',
    'tax_rate' => '21', 'tax_category' => 'standard', 'active' => true,
]);
$pdo->prepare('INSERT INTO admin_invoice_line (tenant_id,catalog_item_public_id) VALUES (?, ?)')
    ->execute(['two', $unusedId]);
adminInvoiceAssert($catalog->deletionBlockReason($unusedId) === null,
    'Een ongebruikt item moet verwijderbaar zijn; verwijzingen van andere tenants tellen niet mee.');
$guardedCatalog = new InvoiceCatalog($pdo, $tenant, $ids, [], static function (array $item) use ($unusedId): ?string {
    return $item['public_id'] === $unusedId ? 'Projectkoppeling bestaat nog.' : null;
});
adminInvoiceAssert($guardedCatalog->deletionBlockReason($unusedId) === 'Projectkoppeling bestaat nog.',
    'Een project moet externe cataloguskoppelingen kunnen beschermen zonder de Admin-module te wijzigen.');
$catalog->delete($unusedId);
adminInvoiceAssert($catalog->find($unusedId) === null,
    'Verwijderen moet het ongebruikte item echt uit de catalogus halen.');
adminInvoiceAssert($catalog->deletionBlockReason($groupId) !== null,
    'Een groep met varianten mag niet worden verwijderd.');
adminInvoiceAssert($catalog->deletionBlockReason($smallId) !== null,
    'Een variant van een gekoppelde groep mag niet worden verwijderd.');
adminInvoiceAssertThrows(InvalidArgumentException::class, function () use ($catalog, $itemId): void {
    $catalog->delete($itemId);
}, 'Een item in een herhaalschema mag niet worden verwijderd.');
$invoicedId = $catalog->save([
    'name' => 'Gefactureerd', 'description' => 'Gefactureerd', 'quantity' => '1',
    'unit' => 'stuk', 'unit_price' => '1.00', 'currency' => 'EUR',
    'tax_rate' => '21', 'tax_category' => 'standard', 'active' => true,
]);
$pdo->prepare('INSERT INTO admin_invoice_line (tenant_id,catalog_item_public_id) VALUES (?, ?)')
    ->execute(['one', $invoicedId]);
adminInvoiceAssertThrows(InvalidArgumentException::class, function () use ($catalog, $invoicedId): void {
    $catalog->delete($invoicedId);
}, 'Een item dat op een factuurregel staat mag niet worden verwijderd.');

echo "AdminInvoice catalog and recurring tests passed.\n";
