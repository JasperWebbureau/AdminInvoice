<?php
declare(strict_types=1);
namespace Flexgrid\Modules\AdminInvoice\Contract;
use Flexgrid\Modules\AdminInvoice\Application\Command\CreateInvoiceCommand;use Flexgrid\Modules\AdminInvoice\Domain\Model\Invoice;
interface InvoiceDraftCreatorInterface{public function execute(CreateInvoiceCommand $command):Invoice;}
