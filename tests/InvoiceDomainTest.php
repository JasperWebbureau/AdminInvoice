<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Flexgrid\Modules\AdminCore\ValueObject\Currency;
use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminCore\ValueObject\TaxRate;
use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminInvoice\Domain\Model\Invoice;
use Flexgrid\Modules\AdminInvoice\Domain\Model\InvoiceLine;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\AddressSnapshot;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\CalendarDate;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\InvoiceStatus;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\PartySnapshot;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\Quantity;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\TaxCategory;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceCalculator;

function createTestInvoice(Currency $currency): Invoice
{
    return new Invoice(
        'invoice-public-id',
        new TenantId('tenant-a'),
        $currency,
        new PartySnapshot('Voorbeeld B.V.', 'Debiteuren', 'finance@example.test', '', '12345678', 'NL001234567B01'),
        new AddressSnapshot('Dorpsstraat 1', '1234 AB', 'Amsterdam', 'NL'),
        new CalendarDate('2026-09-18'),
        new CalendarDate('2026-10-18'),
        'customer-42',
        'Bedankt voor de opdracht.',
        'webshop',
        'order-1001'
    );
}

$currency = Currency::euro();
$calculator = new InvoiceCalculator();

$quantity = Quantity::fromDecimal('1,2345');
adminInvoiceAssert($quantity->getScaledValue() === 12345, 'Hoeveelheid moet exact met schaal 4 worden opgeslagen.');
adminInvoiceAssert($quantity->format() === '1,2345', 'Hoeveelheid moet zonder floatverlies formatteren.');
adminInvoiceAssert(Quantity::fromDecimal('-2.5')->getScaledValue() === -25000, 'Negatieve hoeveelheid moet ondersteund zijn.');
adminInvoiceAssertThrows(InvalidArgumentException::class, function (): void {
    Quantity::fromDecimal('1.23456');
}, 'Meer dan vier decimalen moeten worden geweigerd.');

$party = new PartySnapshot('Klant B.V.', 'Inkoop', 'inkoop@example.test');
$partyRoundTrip = PartySnapshot::fromArray($party->toArray());
adminInvoiceAssert($partyRoundTrip->getName() === 'Klant B.V.', 'PartySnapshot moet versioned kunnen roundtrippen.');
$linkedParty = new PartySnapshot('Gekoppelde klant', '', '', '', '', '', 'customer-public-1');
adminInvoiceAssert($linkedParty->getSourcePublicId() === 'customer-public-1', 'PartySnapshot v2 moet publieke Customer-bron-id bewaren.');
adminInvoiceAssert(PartySnapshot::fromArray([
    'schema_version' => 1,
    'name' => 'Bestaande snapshot',
])->getSourcePublicId() === '', 'Bestaande versie-1-snapshots moeten zonder bron-id leesbaar blijven.');
$address = new AddressSnapshot('Markt 10', '1000 AA', 'Utrecht', 'nl', 'Bus 2');
$addressRoundTrip = AddressSnapshot::fromArray($address->toArray());
adminInvoiceAssert($addressRoundTrip->getCountryCode() === 'NL', 'AddressSnapshot moet de landcode normaliseren.');
adminInvoiceAssertThrows(InvalidArgumentException::class, function () use ($party): void {
    $data = $party->toArray();
    $data['schema_version'] = 99;
    PartySnapshot::fromArray($data);
}, 'Onbekende snapshotversies moeten expliciet worden geweigerd.');

$emptyInvoice = createTestInvoice($currency);
$emptyTotals = $calculator->calculate($emptyInvoice);
adminInvoiceAssert($emptyTotals->getGross()->getMinorUnits() === 0, 'Factuur zonder regels moet nul totalen opleveren.');
adminInvoiceAssert($emptyTotals->getTaxSummaries() === [], 'Factuur zonder regels heeft geen btw-groepen.');
adminInvoiceAssertThrows(DomainException::class, function () use ($emptyInvoice, $emptyTotals): void {
    $emptyInvoice->finalize('2026-0001', 1, 1, $emptyTotals);
}, 'Factuur zonder regels mag niet worden gefinaliseerd.');

$invoice = createTestInvoice($currency);
$invoice->addLine(new InvoiceLine(
    'line-1',
    'Ontwerpwerk',
    Quantity::fromDecimal('1.2345'),
    'uur',
    Money::fromDecimal('10.01', $currency),
    TaxRate::fromPercentage('21'),
    new TaxCategory(TaxCategory::STANDARD),
    10
));
$invoice->addLine(new InvoiceLine(
    'line-2',
    'Boek',
    Quantity::fromDecimal('2'),
    'stuk',
    Money::fromDecimal('5.00', $currency),
    TaxRate::fromPercentage('9'),
    new TaxCategory(TaxCategory::REDUCED),
    20
));
$invoice->addLine(new InvoiceLine(
    'line-3',
    'Verlegde dienstverlening',
    Quantity::fromDecimal('1'),
    'dienst',
    Money::fromDecimal('100.00', $currency),
    TaxRate::fromPercentage('0'),
    new TaxCategory(TaxCategory::REVERSE_CHARGE),
    30
));
$invoice->addLine(new InvoiceLine(
    'line-4',
    'Korting',
    Quantity::fromDecimal('1'),
    'stuk',
    Money::fromDecimal('-1.00', $currency),
    TaxRate::fromPercentage('21'),
    new TaxCategory(TaxCategory::STANDARD),
    40
));
adminInvoiceAssertThrows(DomainException::class, function () use ($invoice, $currency): void {
    $invoice->addLine(new InvoiceLine(
        'duplicate-position',
        'Dubbele positie',
        Quantity::fromDecimal('1'),
        'stuk',
        Money::fromDecimal('1.00', $currency),
        TaxRate::fromPercentage('21'),
        new TaxCategory(TaxCategory::STANDARD),
        40
    ));
}, 'Regelposities moeten binnen een factuur uniek zijn.');

$totals = $calculator->calculate($invoice);
adminInvoiceAssert($invoice->getLines()[0]->calculateNet()->getMinorUnits() === 1236, 'Netto regelbedrag moet half-up afronden.');
adminInvoiceAssert($invoice->getLines()[0]->calculateTax()->getMinorUnits() === 260, 'Btw moet per regel half-up afronden.');
adminInvoiceAssert($totals->getNet()->getMinorUnits() === 12136, 'Netto documenttotaal moet de afgeronde regels sommeren.');
adminInvoiceAssert($totals->getTax()->getMinorUnits() === 329, 'Btw-totaal moet meerdere tarieven en korting sommeren.');
adminInvoiceAssert($totals->getGross()->getMinorUnits() === 12465, 'Brutototaal moet netto plus btw zijn.');
adminInvoiceAssert(count($totals->getTaxSummaries()) === 3, '21%, 9% en verlegd moeten afzonderlijke btw-groepen vormen.');

adminInvoiceAssertThrows(InvalidArgumentException::class, function () use ($currency): void {
    new InvoiceLine(
        'bad-tax',
        'Ongeldige verlegging',
        Quantity::fromDecimal('1'),
        'stuk',
        Money::fromDecimal('1.00', $currency),
        TaxRate::fromPercentage('21'),
        new TaxCategory(TaxCategory::REVERSE_CHARGE),
        1
    );
}, 'Verlegde btw met een positief tarief moet worden geweigerd.');

adminInvoiceAssertThrows(InvalidArgumentException::class, function () use ($invoice): void {
    $invoice->addLine(new InvoiceLine(
        'usd-line',
        'Andere valuta',
        Quantity::fromDecimal('1'),
        'stuk',
        Money::fromDecimal('1.00', new Currency('USD')),
        TaxRate::fromPercentage('0'),
        new TaxCategory(TaxCategory::ZERO),
        50
    ));
}, 'Regels met een andere valuta moeten worden geweigerd.');

$invoice->finalize('2026-0001', 1, 1770000000, $totals);
adminInvoiceAssert($invoice->getStatus()->getValue() === InvoiceStatus::FINALIZED, 'Factuur moet na finalisatie definitief zijn.');
adminInvoiceAssert($invoice->getInvoiceNumber() === '2026-0001', 'Definitief factuurnummer moet worden vastgelegd.');
adminInvoiceAssert($invoice->getInvoiceSequenceValue() === 1, 'Ruwe nummerreekswaarde moet worden vastgelegd.');
adminInvoiceAssert($invoice->getFinalizedTotals() === $totals, 'Definitieve totalen moeten als snapshot worden vastgelegd.');
adminInvoiceAssertThrows(DomainException::class, function () use ($invoice): void {
    $invoice->updateNotes('Dit mag niet meer.');
}, 'Definitieve factuur moet immutable zijn.');
adminInvoiceAssertThrows(DomainException::class, function () use ($invoice): void {
    $invoice->removeLine('line-1');
}, 'Regels van een definitieve factuur mogen niet worden verwijderd.');

$cancelled = createTestInvoice($currency);
$cancelled->cancelDraft();
adminInvoiceAssert($cancelled->getStatus()->getValue() === InvoiceStatus::CANCELLED, 'Conceptfactuur moet annuleerbaar zijn.');

echo "AdminInvoice domain tests passed.\n";
