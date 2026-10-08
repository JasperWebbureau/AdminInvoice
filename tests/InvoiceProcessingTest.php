<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/Contract/InvoiceCollectionExtensionInterface.php';
require_once __DIR__ . '/../src/Service/InvoiceProcessing.php';

use Flexgrid\Modules\AdminCore\ValueObject\Currency;
use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminCore\ValueObject\TaxRate;
use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminInvoice\Domain\Model\Invoice;
use Flexgrid\Modules\AdminInvoice\Domain\Model\InvoiceLine;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\AddressSnapshot;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\CalendarDate;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\PartySnapshot;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\Quantity;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\TaxCategory;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceCalculator;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceDispatchService;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceProcessing;
use Flexgrid\Modules\AdminInvoice\Service\InvoicePdfTemplateSelector;
use Flexgrid\Modules\AdminInvoice\Service\InvoicePdfViewModelFactory;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\GenerateInvoicePdf;
use Flexgrid\Modules\AdminInvoice\Contract\InvoiceMailSenderInterface;
use Flexgrid\Modules\AdminInvoice\Contract\InvoiceCollectionExtensionInterface;
use Flexgrid\Modules\AdminPdf\Contract\DocumentRendererInterface;
use Flexgrid\Modules\AdminPdf\Infrastructure\FilesystemTemplateResolver;
use Flexgrid\Modules\AdminPdf\ValueObject\PdfDocument;
use Flexgrid\Modules\AdminPdf\ValueObject\PdfRenderOptions;

final class ProcessingTestRenderer implements DocumentRendererInterface
{
    public function render(string $template, array $viewModel, PdfRenderOptions $options, string $fileName): PdfDocument
    { return new PdfDocument('%PDF-test', $fileName, 1); }
}
final class ProcessingTestMailer implements InvoiceMailSenderInterface
{
    public $messages = [];
    public $fail = false;
    public function send(string $recipient, string $subject, string $message, PdfDocument $attachment): void
    {
        if ($this->fail) { throw new RuntimeException('Mailprovider unavailable'); }
        $this->messages[] = $message;
    }
}
final class ProcessingTestCollection implements InvoiceCollectionExtensionInterface
{
    public $notified = [];
    public function prepare(Invoice $invoice): string { return 'Incassoaankondiging: bedrag en datum.'; }
    public function noticeSent(Invoice $invoice): void { $this->notified[] = $invoice->getPublicId(); }
    public function processDue(string $today): array { return []; }
}

$processingPdo = new PDO('sqlite::memory:');
$processingPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$processingPdo->exec('CREATE TABLE admin_invoice (tenant_id TEXT, public_id TEXT, created_at INTEGER)');
$processingPdo->exec('CREATE TABLE admin_invoice_processing (tenant_id TEXT, invoice_public_id TEXT, status TEXT, automatic_approval INTEGER, error TEXT, created_at INTEGER, updated_at INTEGER, UNIQUE(tenant_id,invoice_public_id))');
$processingPdo->exec('CREATE TABLE admin_invoice_dispatch (id INTEGER PRIMARY KEY, tenant_id TEXT, invoice_public_id TEXT, kind TEXT, recipient TEXT, status TEXT, subject TEXT, error TEXT, sent_at INTEGER, created_at INTEGER, updated_at INTEGER, UNIQUE(tenant_id,invoice_public_id,kind))');
$processingTenant = new TenantId('processing-tenant');
$processingInvoices = [];
$processingAutomatic = false;
$processingMake = static function (string $id, int $createdAt = 0) use (&$processingInvoices, $processingPdo, $processingTenant): Invoice {
    $currency = Currency::euro();
    $invoice = new Invoice($id, $processingTenant, $currency,
        new PartySnapshot('Testklant', '', 'klant@example.test'), new AddressSnapshot('Straat 1', '1000 AA', 'Utrecht', 'NL'),
        new CalendarDate('2026-10-01'), new CalendarDate('2026-10-15'), '', '', 'admin_recurring', $id);
    $invoice->addLine(new InvoiceLine('line-' . $id, 'Maandpakket', Quantity::fromDecimal('1'), 'maand',
        Money::fromDecimal('10.00', $currency), TaxRate::fromPercentage('21'), new TaxCategory('standard'), 10));
    $processingInvoices[$id] = $invoice;
    $processingPdo->prepare('INSERT INTO admin_invoice VALUES (?,?,?)')->execute([$processingTenant->toString(), $id, $createdAt ?: time()]);
    return $invoice;
};
$processingFinalize = static function (string $id) use (&$processingInvoices): Invoice {
    $invoice = $processingInvoices[$id];
    $invoice->finalize('2026-' . $id, 1, time(), (new InvoiceCalculator())->calculate($invoice));
    return $invoice;
};
$processingRoot = dirname(__DIR__) . '/src/Templates/Pdf';
$processingPdf = new GenerateInvoicePdf(new ProcessingTestRenderer(),
    new InvoicePdfTemplateSelector(new FilesystemTemplateResolver([$processingRoot])),
    new InvoicePdfViewModelFactory(), new PdfRenderOptions('A4', 'portrait', 'DejaVu Sans', [$processingRoot]), 'default', []);
$processingMailer = new ProcessingTestMailer();
$processingDispatch = new InvoiceDispatchService($processingPdo, $processingTenant, $processingPdf, $processingMailer);
$processingCollection = new ProcessingTestCollection();
$processing = new InvoiceProcessing($processingPdo, $processingTenant,
    static function ($id) use (&$processingInvoices) { return $processingInvoices[$id]; }, $processingFinalize,
    $processingDispatch, $processingCollection, static function () use (&$processingAutomatic): bool { return $processingAutomatic; });

$processing->enroll($processingMake('manual'));
adminInvoiceAssert($processing->run()['review'] === 1 && count($processingMailer->messages) === 0,
    'Standaard blijven concepten staan voor handmatige controle, zonder mail of incasso.');
$processingAutomatic = true;
adminInvoiceAssert($processing->run()['review'] === 1, 'Later inschakelen keurt eerder aangemaakte concepten niet goed.');
$processingFinalize('manual');
adminInvoiceAssert($processing->run()['sent'] === 1 && $processingCollection->notified === ['manual'],
    'Handmatig goedgekeurde maandfactuur wordt verzonden en daarna aangekondigd voor incasso.');
adminInvoiceAssert(strpos($processingMailer->messages[0], 'Incassoaankondiging') !== false,
    'De incassoaankondiging moet in de factuurmail staan.');
$processing->enroll($processingMake('automatic'));
adminInvoiceAssert($processing->run()['sent'] === 1 && $processingInvoices['automatic']->getInvoiceNumber() !== null,
    'Alleen nieuwe concepten kunnen met de expliciete instelling automatisch definitief worden.');
$processing->run();
adminInvoiceAssert(count($processingMailer->messages) === 2, 'Herhaalde runs versturen geen dubbele factuurmail.');
$processing->enroll($processingMake('recovered-old', time() - 86400));
adminInvoiceAssert($processing->run()['review'] === 1, 'Een hersteld oud concept wordt niet alsnog automatisch goedgekeurd.');
$processing->enroll($processingMake('disabled'));
$processingAutomatic = false;
adminInvoiceAssert($processing->run()['review'] === 2, 'Uitschakelen blokkeert ook nog niet verwerkte automatische concepten.');
$processingAutomatic = true;
$processingMailer->fail = true;
adminInvoiceAssert($processing->run()['failed'] === 1 && $processing->find('disabled')['status'] === 'failed',
    'Een mislukte verzending blijft zichtbaar en plant geen incasso.');
adminInvoiceAssert(!in_array('disabled', $processingCollection->notified, true), 'Geen incasso na mislukte aankondiging.');
$processingMailer->fail = false;
adminInvoiceAssert($processing->run()['sent'] === 0, 'Een fout wordt nooit stilzwijgend opnieuw geprobeerd.');
$processing->resume('disabled');
adminInvoiceAssert($processing->run()['failed'] === 1 && count($processingMailer->messages) === 2,
    'Ook hervatten herhaalt een eerdere mailpoging niet automatisch.');
adminInvoiceAssertThrows(DomainException::class, static function () use ($processingDispatch, $processingInvoices): void {
    $processingDispatch->send($processingInvoices['automatic'], 'invoice', 'ander@example.test', true, 'Incassoaankondiging');
}, 'Incassoaankondiging mag niet naar een willekeurig ander adres worden gestuurd.');
$processingOther = new InvoiceProcessing($processingPdo, new TenantId('other'),
    static function () { throw new RuntimeException('No foreign invoice access'); }, $processingFinalize,
    $processingDispatch, null, static function (): bool { return true; });
adminInvoiceAssert($processingOther->run()['sent'] === 0 && $processingOther->find('manual') === null,
    'De verwerkingswachtrij blijft tenantgebonden.');
echo "AdminInvoice processing tests passed.\n";
if (class_exists(\Flexgrid\Response\PageResponse::class)) {
    $automatic = false; $ready = true;
    $actions = ['save' => 'save-settings', 'run' => 'run-processing'];
    $table = ['columns' => [['key' => 'customer', 'label' => 'Klant']],
        'rows' => [['cells' => ['customer' => '<script>unsafe</script>']]]];
    ob_start(); include dirname(__DIR__) . '/src/Templates/Processing/Index.php';
    $processingHtml = (string)ob_get_clean();
    adminInvoiceAssert(strpos($processingHtml, '<grid>') !== false && strpos($processingHtml, '--cw:6') !== false
        && strpos($processingHtml, 'name="automatic" value="1" checked') === false
        && strpos($processingHtml, '&lt;script&gt;unsafe&lt;/script&gt;') !== false,
        'Verwerkingsscherm gebruikt gedeelde panels en tabel, veilige output en handmatige standaard.');
    $configured = true; $enabled = false; $days = 14;
    ob_start(); include dirname(__DIR__, 2) . '/AdminPayment/src/Templates/DirectDebit/Index.php';
    $collectionHtml = (string)ob_get_clean();
    adminInvoiceAssert(strpos($collectionHtml, '<grid>') !== false
        && strpos($collectionHtml, 'name="enabled" value="1" checked') === false
        && strpos($collectionHtml, 'value="14"') !== false, 'Incassobeheer start uit met een zichtbare aankondigingstermijn.');
    $debitCustomer = null; $active = true; $customerId = 'customer-one'; $actions['setup'] = 'setup-debit';
    ob_start(); include dirname(__DIR__, 2) . '/AdminPayment/src/Templates/DirectDebit/Customer.php';
    $customerHtml = (string)ob_get_clean();
    adminInvoiceAssert(strpos($customerHtml, 'EUR 0,01') !== false && strpos($customerHtml, 'Toestemmingslink maken') !== false,
        'Klantpaneel legt de eenmalige toestemmingsbetaling uit voordat een link wordt gemaakt.');
    foreach ([$processingHtml, $collectionHtml, $customerHtml] as $html) {
        adminInvoiceAssert(!preg_match('~</?(header|footer)\b~i', $html), 'Nieuwe adminpanelen gebruiken geen gereserveerde HTML-tags.');
    }
    echo "AdminInvoice processing UI tests passed.\n";
}
