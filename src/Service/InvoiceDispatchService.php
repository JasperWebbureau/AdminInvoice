<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Service;

use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminInvoice\Contract\InvoiceMailSenderInterface;
use Flexgrid\Modules\AdminInvoice\Domain\Model\Invoice;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\InvoiceStatus;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\PaymentStatus;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\GenerateInvoicePdf;

final class InvoiceDispatchService
{
    private $connection;
    private $tenant;
    private $pdf;
    private $mailer;

    public function __construct(
        \PDO $connection,
        TenantId $tenant,
        GenerateInvoicePdf $pdf,
        InvoiceMailSenderInterface $mailer
    ) {
        $this->connection = $connection;
        $this->tenant = $tenant;
        $this->pdf = $pdf;
        $this->mailer = $mailer;
    }

    public function history(string $invoicePublicId): array
    {
        $statement = $this->connection->prepare(
            'SELECT `kind`,`recipient`,`status`,`subject`,`error`,`sent_at`,`created_at`,`updated_at` '
            . 'FROM `admin_invoice_dispatch` WHERE `tenant_id`=:tenant '
            . 'AND `invoice_public_id`=:invoice ORDER BY `created_at`,`id`'
        );
        $statement->execute([':tenant' => $this->tenant->toString(), ':invoice' => $invoicePublicId]);
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            if (preg_match('/^(invoice|reminder)(?::([2-9]|[1-9][0-9]+))?$/D', $row['kind'], $matches)) {
                $row['kind'] = $matches[1];
                $row['attempt'] = isset($matches[2]) ? (int)$matches[2] : 1;
            }
        }
        unset($row);
        return $rows;
    }

    public function send(Invoice $invoice, string $kind, string $recipient, bool $confirmRepeat = false, string $notice = ''): void
    {
        if (!$invoice->getTenantId()->equals($this->tenant)
            || $invoice->getStatus()->getValue() !== InvoiceStatus::FINALIZED) {
            throw new \DomainException('Alleen een definitieve factuur kan worden verzonden of herinnerd.');
        }
        if (!in_array($kind, ['invoice', 'reminder'], true)) {
            throw new \InvalidArgumentException('Onbekende verzendactie.');
        }
        $recipient = trim($recipient);
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || strlen($recipient) > 255) {
            throw new \InvalidArgumentException('Vul een geldig e-mailadres van de klant in.');
        }
        if ($notice !== '' && ($kind !== 'invoice'
            || strcasecmp($recipient, (string)$invoice->getCustomerSnapshot()->toArray()['email']) !== 0)) {
            throw new \DomainException('De incassoaankondiging moet naar het factuur-e-mailadres van deze klant worden verstuurd.');
        }
        if ($kind === 'reminder') {
            if ($invoice->getDueDate()->getValue() >= date('Y-m-d')
                || !in_array($invoice->getPaymentStatus()->getValue(),
                    [PaymentStatus::UNPAID, PaymentStatus::PARTIALLY_PAID], true)) {
                throw new \DomainException('Een herinnering kan alleen bij een vervallen, onbetaalde factuur.');
            }
            if (!$this->wasSent($invoice->getPublicId(), 'invoice')) {
                throw new \DomainException('Verstuur eerst de oorspronkelijke factuur.');
            }
        }

        $latestAttempt = null;
        $attemptNumber = 0;
        foreach ($this->history($invoice->getPublicId()) as $attempt) {
            if ($attempt['kind'] === $kind) {
                $latestAttempt = $attempt;
                $attemptNumber = max($attemptNumber, (int)$attempt['attempt']);
            }
        }
        if ($latestAttempt !== null) {
            if (!$confirmRepeat) {
                throw new \DomainException('Deze e-mail is al eerder geprobeerd. Bevestig bewust dat je opnieuw wilt mailen.');
            }
            if ($latestAttempt['status'] === 'pending' && (int)$latestAttempt['updated_at'] > time() - 900) {
                throw new \DomainException('De vorige verzendpoging wordt mogelijk nog verwerkt. Probeer het na 15 minuten opnieuw.');
            }
        }
        $storageKind = $attemptNumber === 0 ? $kind : $kind . ':' . ($attemptNumber + 1);
        $number = (string)$invoice->getInvoiceNumber();
        $subject = $kind === 'invoice' ? 'Factuur ' . $number : 'Betalingsherinnering factuur ' . $number;
        $message = $kind === 'invoice'
            ? "Beste klant,\n\nBijgevoegd vindt u factuur " . $number . "."
                . ($notice !== '' ? "\n\n" . $notice : '') . "\n\nMet vriendelijke groet,\nAdministratie"
            : "Beste klant,\n\nVolgens onze administratie staat factuur " . $number
                . " nog (gedeeltelijk) open. In de bijlage vindt u de factuur. Heeft u al betaald, dan kunt u deze herinnering negeren.\n\nMet vriendelijke groet,\nAdministratie";
        $document = $this->pdf->execute($invoice);
        $now = time();

        try {
            $insert = $this->connection->prepare(
                'INSERT INTO `admin_invoice_dispatch` '
                . '(`tenant_id`,`invoice_public_id`,`kind`,`recipient`,`status`,`subject`,`error`,`sent_at`,`created_at`,`updated_at`) '
                . 'VALUES (:tenant,:invoice,:kind,:recipient,:status,:subject,NULL,NULL,:created,:updated)'
            );
            $insert->execute([
                ':tenant' => $this->tenant->toString(), ':invoice' => $invoice->getPublicId(),
                ':kind' => $storageKind, ':recipient' => $recipient, ':status' => 'pending',
                ':subject' => $subject, ':created' => $now, ':updated' => $now,
            ]);
        } catch (\PDOException $exception) {
            throw new \DomainException('Deze factuur of herinnering is al verzonden of wordt verwerkt.');
        }
        try {
            $this->mailer->send($recipient, $subject, $message, $document);
            $this->setStatus($invoice->getPublicId(), $storageKind, 'sent', '', time());
        } catch (\Throwable $exception) {
            $this->setStatus($invoice->getPublicId(), $storageKind, 'failed', substr($exception->getMessage(), 0, 1000), null);
            throw new \RuntimeException('Verzenden is mislukt. Controleer de verzendstatus voordat u opnieuw probeert.', 0, $exception);
        }
    }

    private function wasSent(string $invoicePublicId, string $kind): bool
    {
        foreach ($this->history($invoicePublicId) as $row) {
            if ($row['kind'] === $kind && $row['status'] === 'sent') {
                return true;
            }
        }
        return false;
    }

    private function setStatus(string $invoicePublicId, string $kind, string $status, string $error, ?int $sentAt): void
    {
        $statement = $this->connection->prepare(
            'UPDATE `admin_invoice_dispatch` SET `status`=:status,`error`=:error,`sent_at`=:sent_at,'
            . '`updated_at`=:updated WHERE `tenant_id`=:tenant AND `invoice_public_id`=:invoice AND `kind`=:kind'
        );
        $statement->execute([
            ':status' => $status, ':error' => $error ?: null, ':sent_at' => $sentAt,
            ':updated' => time(), ':tenant' => $this->tenant->toString(),
            ':invoice' => $invoicePublicId, ':kind' => $kind,
        ]);
    }
}
