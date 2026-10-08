<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Flexgrid\Modules\AdminCore\ValueObject\Currency;
use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminCore\ValueObject\TaxRate;
use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\GenerateInvoicePdf;
use Flexgrid\Modules\AdminInvoice\Contract\InvoiceMailSenderInterface;
use Flexgrid\Modules\AdminInvoice\Domain\Model\Invoice;
use Flexgrid\Modules\AdminInvoice\Domain\Model\InvoiceLine;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\AddressSnapshot;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\CalendarDate;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\PartySnapshot;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\Quantity;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\TaxCategory;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceCalculator;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceDispatchService;
use Flexgrid\Modules\AdminInvoice\Service\InvoicePdfTemplateSelector;
use Flexgrid\Modules\AdminInvoice\Service\InvoicePdfViewModelFactory;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceReviewQueue;
use Flexgrid\Modules\AdminPdf\Contract\DocumentRendererInterface;
use Flexgrid\Modules\AdminPdf\Infrastructure\FilesystemTemplateResolver;
use Flexgrid\Modules\AdminPdf\ValueObject\PdfDocument;
use Flexgrid\Modules\AdminPdf\ValueObject\PdfRenderOptions;

final class WorkflowTestRenderer implements DocumentRendererInterface
{
    public function render(string $template, array $viewModel, PdfRenderOptions $options, string $fileName): PdfDocument
    {
        return new PdfDocument('%PDF-test', $fileName, 1);
    }
}
final class WorkflowTestMailer implements InvoiceMailSenderInterface
{
    public $sent = [];
    public $failNext = false;
    public function send(string $recipient, string $subject, string $message, PdfDocument $attachment): void
    {
        if ($this->failNext) {
            $this->failNext = false;
            throw new RuntimeException('Testmail mislukt.');
        }
        $this->sent[] = [$recipient, $subject, $attachment->getFileName()];
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE admin_invoice_dispatch (id INTEGER PRIMARY KEY, tenant_id TEXT, invoice_public_id TEXT, kind TEXT, recipient TEXT, status TEXT, subject TEXT, error TEXT, sent_at INTEGER, created_at INTEGER, updated_at INTEGER, UNIQUE(tenant_id, invoice_public_id, kind))');
$pdo->exec('CREATE TABLE admin_invoice (id INTEGER PRIMARY KEY, tenant_id TEXT, public_id TEXT, customer_snapshot TEXT, source TEXT, external_id TEXT, issue_date TEXT, gross_total_minor INTEGER, currency TEXT, created_at INTEGER, status TEXT)');
$pdo->exec('CREATE TABLE admin_invoice_recurring (tenant_id TEXT, public_id TEXT, name TEXT)');
$pdo->exec("INSERT INTO admin_invoice_recurring VALUES ('workflow-tenant','plan','Maandelijks pakket')");
$currency = Currency::euro();
$tenant = new TenantId('workflow-tenant');
$invoice = new Invoice('workflow-invoice', $tenant, $currency,
    new PartySnapshot('Voorbeeld BV', '', 'klant@example.test'),
    new AddressSnapshot('Straat 1', '1000 AA', 'Amsterdam', 'NL'),
    new CalendarDate('2026-08-01'), new CalendarDate('2026-08-15'));
$invoice->addLine(new InvoiceLine('workflow-line', 'Dienst', Quantity::fromDecimal('1'), 'stuk',
    Money::fromDecimal('10.00', $currency), TaxRate::fromPercentage('21'),
    new TaxCategory(TaxCategory::STANDARD), 10));
$invoice->finalize('2026-0099', 99, 1770004321, (new InvoiceCalculator())->calculate($invoice));
$templateRoot = dirname(__DIR__) . '/src/Templates/Pdf';
$resolver = new FilesystemTemplateResolver([$templateRoot]);
$pdf = new GenerateInvoicePdf(new WorkflowTestRenderer(), new InvoicePdfTemplateSelector($resolver),
    new InvoicePdfViewModelFactory(), new PdfRenderOptions('A4', 'portrait', 'DejaVu Sans', [$templateRoot]),
    'default', []);
$mailer = new WorkflowTestMailer();
$dispatch = new InvoiceDispatchService($pdo, $tenant, $pdf, $mailer);
$dispatch->send($invoice, 'invoice', 'klant@example.test');
adminInvoiceAssert(count($mailer->sent) === 1 && $dispatch->history('workflow-invoice')[0]['status'] === 'sent',
    'Factuurmail moet exact eenmaal worden verstuurd en geregistreerd.');
adminInvoiceAssertThrows(DomainException::class, static function () use ($dispatch, $invoice): void {
    $dispatch->send($invoice, 'invoice', 'klant@example.test');
}, 'Dezelfde factuur mag niet ongemerkt opnieuw worden verstuurd.');
$dispatch->send($invoice, 'reminder', 'klant@example.test');
adminInvoiceAssert(count($mailer->sent) === 2 && count($dispatch->history('workflow-invoice')) === 2,
    'Een vervallen, onbetaalde factuur mag na verzending één herinnering krijgen.');
adminInvoiceAssertThrows(DomainException::class, static function () use ($dispatch, $invoice): void {
    $dispatch->send($invoice, 'reminder', 'klant@example.test');
}, 'Een tweede herinnering mag niet stilzwijgend verzonden worden.');
$dispatch->send($invoice, 'invoice', 'klant@example.test', true);
adminInvoiceAssert(count($mailer->sent) === 3 && count($dispatch->history('workflow-invoice')) === 3,
    'Een bevestigde handmatige herkansing moet als aparte poging worden bewaard.');
$mailer->failNext = true;
adminInvoiceAssertThrows(RuntimeException::class, static function () use ($dispatch, $invoice): void {
    $dispatch->send($invoice, 'invoice', 'klant@example.test', true);
}, 'Een mislukte herkansing moet zichtbaar als fout worden geregistreerd.');
$attempts = $dispatch->history('workflow-invoice');
adminInvoiceAssert(count($attempts) === 4 && $attempts[3]['status'] === 'failed'
    && $attempts[3]['attempt'] === 3 && $attempts[0]['status'] === 'sent',
    'Een mislukte herkansing mag de eerder verzonden poging niet overschrijven.');
$dispatch->send($invoice, 'invoice', 'klant@example.test', true);
adminInvoiceAssert(count($dispatch->history('workflow-invoice')) === 5
    && count($mailer->sent) === 4,
    'Na een mislukte poging moet bewust opnieuw mailen mogelijk zijn.');
$dispatch->send($invoice, 'reminder', 'klant@example.test', true);
adminInvoiceAssert(count($dispatch->history('workflow-invoice')) === 6,
    'Ook een herinnering moet na expliciete bevestiging opnieuw kunnen worden gemaild.');
$pending = $pdo->prepare('INSERT INTO admin_invoice_dispatch (tenant_id,invoice_public_id,kind,recipient,status,subject,error,sent_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)');
$pending->execute(['workflow-tenant', 'workflow-invoice', 'invoice:5', 'klant@example.test', 'pending', 'Factuur', null, null, time(), time()]);
adminInvoiceAssertThrows(DomainException::class, static function () use ($dispatch, $invoice): void {
    $dispatch->send($invoice, 'invoice', 'klant@example.test', true);
}, 'Een nog lopende verzending mag niet direct worden gedupliceerd.');
$agePending = $pdo->prepare('UPDATE admin_invoice_dispatch SET updated_at=1 WHERE kind=?');
$agePending->execute(['invoice:5']);
$dispatch->send($invoice, 'invoice', 'klant@example.test', true);
adminInvoiceAssert(count($dispatch->history('workflow-invoice')) === 8,
    'Een oude poging met onbekende uitkomst moet na expliciete bevestiging herstelbaar zijn.');
for ($attempt = 7; $attempt <= 10; ++$attempt) {
    $dispatch->send($invoice, 'invoice', 'klant@example.test', true);
}
$attempts = $dispatch->history('workflow-invoice');
adminInvoiceAssert(end($attempts)['attempt'] === 10,
    'Ook latere handmatige pogingen moeten correct in de historie verschijnen.');

$insert = $pdo->prepare('INSERT INTO admin_invoice (tenant_id,public_id,customer_snapshot,source,external_id,issue_date,gross_total_minor,currency,created_at,status) VALUES (?,?,?,?,?,?,?,?,?,?)');
$insert->execute(['workflow-tenant', 'draft-one', '{"name":"Klant A"}', 'admin_recurring', 'plan:date', '2026-09-01', 1210, 'EUR', 100, 'draft']);
$insert->execute(['other-tenant', 'draft-other', '{"name":"Andere klant"}', 'admin_recurring', 'plan:date', '2026-09-01', 1210, 'EUR', 101, 'draft']);
$queue = (new InvoiceReviewQueue($pdo, $tenant))->recent();
adminInvoiceAssert(count($queue) === 1 && $queue[0]['origin'] === 'Herhaalschema: Maandelijks pakket'
    && $queue[0]['customer_name'] === 'Klant A' && $queue[0]['amount'] === '12,10',
    'Controlelijst moet tenantgebonden, begrijpelijke conceptgegevens tonen.');

$rows = $queue;
$editBaseUrl = '/Flexgrid/AdminInvoice/edit';
$previewBaseUrl = '/Flexgrid/AdminInvoice/previewPdf';
$overviewUrl = '/Flexgrid/AdminInvoice/invoices';
$rowCount = count($queue);
$table = ['columns' => [['key' => 'origin', 'label' => 'Herkomst']],
    'rows' => [['cells' => ['origin' => $queue[0]['origin']], 'actions' => [
        ['href' => $previewBaseUrl . '/draft-one', 'label' => 'Concept-PDF', 'icon' => 'file-pdf']]]]];
ob_start();
include dirname(__DIR__) . '/src/Templates/ReviewQueue/Index.php';
$queueHtml = (string)ob_get_clean();
adminInvoiceAssert(strpos($queueHtml, 'Maandelijks pakket') !== false
    && strpos($queueHtml, 'previewPdf/draft-one') !== false
    && strpos($queueHtml, '<grid>') !== false,
    'Controlelijst moet herkomst, concept-PDF en Flexgrid-panel tonen.');

$report = ['date' => '2026-09-28', 'counts' => ['ready' => 1, 'skipped' => 0, 'failed' => 0],
    'rows' => [['issue_date' => '2026-09-28', 'plan_name' => 'Maandelijks pakket',
        'customer_id' => 'customer-1', 'customer_name' => 'Klant A',
        'lines' => ['Hosting'], 'gross' => '12,10', 'currency' => 'EUR',
        'status' => 'ready', 'message' => '']]];
$runAction = 'invoice.run';
$plansUrl = '/Flexgrid/AdminRecurringInvoice/plans';
$previewTable = ['columns' => [['key' => 'lines', 'label' => 'Regels']],
    'rows' => [['cells' => ['lines' => 'Hosting']]]];
ob_start();
include dirname(__DIR__) . '/src/Templates/Recurring/Preview.php';
$previewHtml = (string)ob_get_clean();
adminInvoiceAssert(strpos($previewHtml, 'Er is niets opgeslagen') !== false
    && strpos($previewHtml, 'Hosting') !== false
    && strpos($previewHtml, '<grid>') !== false,
    'Proefrun moet gevolgen en verwachte regels duidelijk tonen.');

$history = $dispatch->history('workflow-invoice');
$dispatchAction = 'invoice.dispatch';
$publicId = 'workflow-invoice';
$recipient = 'klant@example.test';
$canSend = false;
$canRemind = false;
$mailCatchAll = '';
ob_start();
include dirname(__DIR__) . '/src/Templates/Detail/DispatchPanel.php';
$dispatchHtml = (string)ob_get_clean();
adminInvoiceAssert(strpos($dispatchHtml, 'Verzonden') !== false
    && strpos($dispatchHtml, '<header') === false
    && strpos($dispatchHtml, '<footer') === false,
    'Verzendpaneel moet de historie tonen zonder gereserveerde HTML-tags.');
$canSend = true;
$canRemind = true;
$invoiceRepeat = true;
$reminderRepeat = true;
$mailInProgress = false;
ob_start();
include dirname(__DIR__) . '/src/Templates/Detail/DispatchPanel.php';
$repeatHtml = (string)ob_get_clean();
adminInvoiceAssert(strpos($repeatHtml, 'Factuur opnieuw mailen') !== false
    && strpos($repeatHtml, 'Herinnering opnieuw mailen') !== false
    && substr_count($repeatHtml, 'name="confirm_repeat"') === 2
    && strpos($repeatHtml, 'poging 2') !== false,
    'Handmatig herhalen moet begrijpelijk bevestigd kunnen worden en alle pogingen tonen.');

echo "AdminInvoice workflow tests passed.\n";
