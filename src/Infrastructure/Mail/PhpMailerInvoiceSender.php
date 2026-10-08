<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Infrastructure\Mail;

use Flexgrid\Modules\AdminInvoice\Contract\InvoiceMailSenderInterface;
use Flexgrid\Modules\AdminPdf\ValueObject\PdfDocument;
use Flexgrid\Utils\_Mail;

final class PhpMailerInvoiceSender implements InvoiceMailSenderInterface
{
    /** @var callable */
    private $mailFactory;

    public function __construct(?callable $mailFactory = null)
    {
        $this->mailFactory = $mailFactory ?: static function (): _Mail {
            return new _Mail();
        };
    }

    public function send(string $recipient, string $subject, string $message, PdfDocument $attachment): void
    {

        if (!defined('__MAIL_FROM__') || !filter_var((string)__MAIL_FROM__, FILTER_VALIDATE_EMAIL)) {
       //     throw new \LogicException('Configureer eerst een geldig afzendadres voor factuurmails.');
        }
        //$mailer = new _Mail();

        $mailer = ($this->mailFactory)();

        $mailer->clearAddresses();
        $mailer->setCharset('UTF-8')
            ->setReciever($recipient)
            ->setSubject($subject)
            ->setMessage($message)
             ->setStringAttachment($attachment->getContent(), $attachment->getFileName(), $attachment->getMimeType())
            ->sendOrFail(true);
    }
}
