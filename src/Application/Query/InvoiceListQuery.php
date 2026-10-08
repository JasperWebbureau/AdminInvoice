<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Application\Query;

use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\InvoiceStatus;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\PaymentStatus;

final class InvoiceListQuery
{
    private const SORTS = ['invoice_number', 'customer', 'issue_date', 'due_date', 'gross_total', 'status'];
    private const PAGE_SIZES = [10, 25, 50];
    private const DOCUMENT_TYPES = ['', 'invoice', 'credit_note'];

    /** @var string */
    private $search;
    /** @var string */
    private $status;
    /** @var string */
    private $paymentStatus;
    /** @var int|null */
    private $year;
    /** @var string */
    private $sort;
    /** @var string */
    private $direction;
    /** @var int */
    private $page;
    /** @var int */
    private $perPage;
    /** @var string */
    private $documentType;
    private $quarter;

    public function __construct(
        string $search = '',
        string $status = '',
        string $paymentStatus = '',
        ?int $year = null,
        string $sort = 'issue_date',
        string $direction = 'desc',
        int $page = 1,
        int $perPage = 10,
        string $documentType = '',
        ?int $quarter = null
    ) {
        $search = trim($search);
        $status = trim($status);
        $paymentStatus = trim($paymentStatus);
        $sort = strtolower(trim($sort));
        $direction = strtolower(trim($direction));
        $documentType = strtolower(trim($documentType));

        if (strlen($search) > 120) {
            throw new \InvalidArgumentException('Zoekterm mag maximaal 120 tekens bevatten.');
        }
        if ($status !== '' && !in_array($status, InvoiceStatus::values(), true)) {
            throw new \InvalidArgumentException('Ongeldig factuurstatusfilter.');
        }
        if ($paymentStatus !== '' && !in_array($paymentStatus, PaymentStatus::values(), true)) {
            throw new \InvalidArgumentException('Ongeldig betaalstatusfilter.');
        }
        if ($year !== null && ($year < 1900 || $year > 2200)) {
            throw new \InvalidArgumentException('Ongeldig factuurjaar.');
        }
        if ($quarter !== null && ($quarter < 1 || $quarter > 4 || $year === null)) {
            throw new \InvalidArgumentException('Ongeldig factuurkwartaal.');
        }
        if (!in_array($sort, self::SORTS, true)) {
            throw new \InvalidArgumentException('Ongeldige sorteerkolom.');
        }
        if (!in_array($direction, ['asc', 'desc'], true)) {
            throw new \InvalidArgumentException('Ongeldige sorteerrichting.');
        }
        if ($page < 1) {
            throw new \InvalidArgumentException('Paginanummer moet positief zijn.');
        }
        if (!in_array($perPage, self::PAGE_SIZES, true)) {
            throw new \InvalidArgumentException('Ongeldige paginagrootte.');
        }
        if (!in_array($documentType, self::DOCUMENT_TYPES, true)) {
            throw new \InvalidArgumentException('Ongeldig documenttypefilter.');
        }

        $this->search = $search;
        $this->status = $status;
        $this->paymentStatus = $paymentStatus;
        $this->year = $year;
        $this->sort = $sort;
        $this->direction = $direction;
        $this->page = $page;
        $this->perPage = $perPage;
        $this->documentType = $documentType;
        $this->quarter = $quarter;
    }

    public function getSearch(): string { return $this->search; }
    public function getStatus(): string { return $this->status; }
    public function getPaymentStatus(): string { return $this->paymentStatus; }
    public function getYear(): ?int { return $this->year; }
    public function getQuarter(): ?int { return $this->quarter; }
    public function getSort(): string { return $this->sort; }
    public function getDirection(): string { return $this->direction; }
    public function getPage(): int { return $this->page; }
    public function getPerPage(): int { return $this->perPage; }
    public function getDocumentType(): string { return $this->documentType; }

    /** @return string[] */
    public static function sorts(): array { return self::SORTS; }

    /** @return int[] */
    public static function pageSizes(): array { return self::PAGE_SIZES; }

    /** @return string[] */
    public static function documentTypes(): array { return self::DOCUMENT_TYPES; }
}
