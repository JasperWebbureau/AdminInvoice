<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once $projectRoot . '/Flexgrid/Flexgrid/src/Utils/_Mail.php';
require_once $moduleRoot . '/src/Infrastructure/Mail/PhpMailerInvoiceSender.php';

use Flexgrid\Modules\AdminInvoice\Infrastructure\Mail\PhpMailerInvoiceSender;
use Flexgrid\Modules\AdminPdf\ValueObject\PdfDocument;
use Flexgrid\Utils\_Mail;
use PHPMailer\PHPMailer\PHPMailer;

defined('__MAIL_FROM__') || define('__MAIL_FROM__', 'administratie@example.test');
defined('__MAIL_USER__') || define('__MAIL_USER__', 'Administratie');
defined('__MAIL_TO__') || define('__MAIL_TO__', 'standaard@example.test');
defined('__MAIL_SMTP_HOST__') || define('__MAIL_SMTP_HOST__', '');
defined('__MAIL_CATCH_ALL__') || define('__MAIL_CATCH_ALL__', 'opvang@example.test');

final class RecordingInvoicePhpMailer extends PHPMailer
{
    public $shouldFail = false;
    public $sendCount = 0;

    public function send()
    {
        ++$this->sendCount;
        if ($this->shouldFail) {
            $this->ErrorInfo = 'Testverzending mislukt';
            return false;
        }
        return true;
    }
}

$transport = new RecordingInvoicePhpMailer();
$sender = new PhpMailerInvoiceSender(static function () use ($transport): _Mail {
    return new _Mail($transport);
});
$sender->send('klant@example.test', 'Factuur 2026-001', 'Uw factuur', new PdfDocument('%PDF-test', 'factuur.pdf', 1));

$recipients = array_column($transport->getToAddresses(), 0);
$expectedRecipient = __MAIL_CATCH_ALL__ !== '' ? __MAIL_CATCH_ALL__ : 'klant@example.test';
adminInvoiceAssert($transport->sendCount === 1 && $recipients === [$expectedRecipient],
    'Factuurmail mag niet ook naar de standaardontvanger gaan; de catch-all blijft actief.');
adminInvoiceAssert($transport->CharSet === 'UTF-8' && $transport->Subject === 'Factuur 2026-001'
    && $transport->Body === 'Uw factuur' && $transport->ContentType === 'text/plain',
    'Onderwerp, tekst en tekenset moeten via de gedeelde mailutility behouden blijven.');
$attachments = $transport->getAttachments();
adminInvoiceAssert(count($attachments) === 1 && $attachments[0][0] === '%PDF-test'
    && $attachments[0][1] === 'factuur.pdf' && $attachments[0][4] === 'application/pdf'
    && $attachments[0][5] === true, 'De PDF moet rechtstreeks uit het geheugen als bijlage meegaan.');

$failedTransport = new RecordingInvoicePhpMailer();
$failedTransport->shouldFail = true;
$failedSender = new PhpMailerInvoiceSender(static function () use ($failedTransport): _Mail {
    return new _Mail($failedTransport);
});
adminInvoiceAssertThrows(RuntimeException::class, static function () use ($failedSender): void {
    $failedSender->send('klant@example.test', 'Factuur', 'Uw factuur', new PdfDocument('%PDF-test', 'factuur.pdf', 1));
}, 'Een mislukte factuurmail moet als fout bij de verzendregistratie terechtkomen.');

echo "AdminInvoice mail sender tests passed.\n";
