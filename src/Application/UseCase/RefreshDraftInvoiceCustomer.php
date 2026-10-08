<?php
declare(strict_types=1);
namespace Flexgrid\Modules\AdminInvoice\Application\UseCase;

use Flexgrid\Modules\AdminCore\Context\TenantContext;
use Flexgrid\Modules\AdminCore\Contract\TransactionManagerInterface;
use Flexgrid\Modules\AdminInvoice\Application\ReadModel\InvoiceCustomerSelection;
use Flexgrid\Modules\AdminInvoice\Contract\InvoiceRepositoryInterface;
use Flexgrid\Modules\AdminInvoice\Domain\Model\Invoice;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\AddressSnapshot;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\PartySnapshot;
use Flexgrid\Modules\AdminInvoice\Exception\InvoiceNotFoundException;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceCalculator;
use Flexgrid\Utils\_Time;

final class RefreshDraftInvoiceCustomer
{
    private $tenant,$transactions,$invoices,$calculator,$clock;
    public function __construct(TenantContext $tenant,TransactionManagerInterface $transactions,InvoiceRepositoryInterface $invoices,InvoiceCalculator $calculator,_Time $clock){$this->tenant=$tenant;$this->transactions=$transactions;$this->invoices=$invoices;$this->calculator=$calculator;$this->clock=$clock;}
    public function execute(string $publicId,InvoiceCustomerSelection $selection):Invoice
    {
        return $this->transactions->transactional(function()use($publicId,$selection):Invoice{
            $invoice=$this->invoices->findByPublicIdForUpdate($this->tenant->getTenantId(),trim($publicId));
            if($invoice===null){throw new InvoiceNotFoundException('Factuur niet gevonden.');}
            if($invoice->getCustomerSnapshot()->getSourcePublicId()!==$selection->getPublicId()){throw new \DomainException('De gekoppelde klant is gewijzigd. Vernieuw de factuurpagina.');}
            $party=$selection->getParty();$address=$selection->getBillingAddress();
            $invoice->updateCustomerSnapshot(new PartySnapshot((string)$party['name'],(string)($party['contact_name']??''),(string)($party['email']??''),(string)($party['phone']??''),(string)($party['registration_number']??''),(string)($party['tax_number']??''),$selection->getPublicId()),new AddressSnapshot((string)$address['line_1'],(string)$address['postal_code'],(string)$address['city'],(string)$address['country_code'],(string)($address['line_2']??''),(string)($address['region']??'')));
            $this->invoices->updateDraft($invoice,$this->calculator->calculate($invoice),(int)$this->clock->get());
            return $invoice;
        });
    }
}
