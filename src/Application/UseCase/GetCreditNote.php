<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Application\UseCase;

use Flexgrid\Modules\AdminCore\Context\TenantContext;
use Flexgrid\Modules\AdminInvoice\Contract\CreditNoteRepositoryInterface;
use Flexgrid\Modules\AdminInvoice\Domain\Model\CreditNote;
use Flexgrid\Modules\AdminInvoice\Exception\CreditNoteNotFoundException;

final class GetCreditNote
{
    /** @var TenantContext */ private $tenantContext;
    /** @var CreditNoteRepositoryInterface */ private $creditNotes;

    public function __construct(TenantContext $tenantContext, CreditNoteRepositoryInterface $creditNotes)
    {
        $this->tenantContext = $tenantContext;
        $this->creditNotes = $creditNotes;
    }

    public function execute(string $publicId): CreditNote
    {
        $publicId = trim($publicId);
        if ($publicId === '' || strlen($publicId) > 64) {
            throw new \InvalidArgumentException('Een geldige publieke creditnota-id is verplicht.');
        }

        $creditNote = $this->creditNotes->findByPublicId(
            $this->tenantContext->getTenantId(),
            $publicId
        );
        if ($creditNote === null) {
            throw new CreditNoteNotFoundException('Creditnota niet gevonden.');
        }

        return $creditNote;
    }
}
