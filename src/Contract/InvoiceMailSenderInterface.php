<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Contract;

use Flexgrid\Modules\AdminPdf\ValueObject\PdfDocument;

interface InvoiceMailSenderInterface
{
    public function send(string $recipient, string $subject, string $message, PdfDocument $attachment): void;
}
