<?php
declare(strict_types=1);
namespace Flexgrid\Modules\AdminInvoice\Integration\Quote;
use Flexgrid\App\Settings\Settings;use Flexgrid\Database\Connection;use Flexgrid\Modules\AdminInvoice\Application\Command\CreateInvoiceCommand;use Flexgrid\Modules\AdminInvoice\Contract\InvoiceDraftCreatorInterface;use Flexgrid\Modules\AdminInvoice\Service\AdminInvoiceFactory;use Flexgrid\Modules\AdminQuote\Application\ReadModel\QuoteInvoiceConversionResult;use Flexgrid\Modules\AdminQuote\Application\ReadModel\QuoteInvoiceDraft;use Flexgrid\Modules\AdminQuote\Contract\QuoteInvoiceConverterInterface;use Flexgrid\Modules\AdminQuote\Contract\QuoteInvoiceScheduleInterface;use Flexgrid\Utils\_Time;
final class InvoiceConverter implements QuoteInvoiceConverterInterface, QuoteInvoiceScheduleInterface
{
    private $creator,$clock,$configuredDueDays,$baseUrl,$connection;
    public function __construct(?InvoiceDraftCreatorInterface $creator=null,?_Time $clock=null,?int $dueDays=null,string $baseUrl='',?\PDO $connection=null){$this->creator=$creator;$this->clock=$clock?:new _Time();$this->configuredDueDays=$dueDays;$this->baseUrl=rtrim($baseUrl,'/');$this->connection=$connection;}
    public function convert(QuoteInvoiceDraft $draft):QuoteInvoiceConversionResult
    {
        $timestamp=(int)$this->clock->get();$issueDate=date('Y-m-d',$timestamp);$dueDate=date('Y-m-d',strtotime('+'.$this->dueDays().' days',$timestamp));$notes='Aangemaakt vanuit offerte '.$draft->getQuoteNumber().'.';if($draft->getNotes()!==''){$notes.="\n\n".$draft->getNotes();}
        $invoice=$this->creator()->execute(new CreateInvoiceCommand($draft->getCurrency(),$issueDate,$dueDate,$draft->getCustomer(),$draft->getAddress(),$draft->getLines(),$draft->getReference(),$notes,'admin_quote',$draft->getQuotePublicId()));
        $base=$this->baseUrl!==''?$this->baseUrl:(defined('__DOMAIN__')?rtrim((string)constant('__DOMAIN__'),'/'):'');return new QuoteInvoiceConversionResult($invoice->getPublicId(),$base.'/Flexgrid/AdminInvoice/edit/'.rawurlencode($invoice->getPublicId()));
    }
    public function createInstallment(QuoteInvoiceDraft $draft,string $kind,string $requestId):QuoteInvoiceConversionResult
    {
        if(!in_array($kind,['advance','interim','final'],true)||preg_match('/^[a-f0-9]{32}$/D',$requestId)!==1){throw new \InvalidArgumentException('Ongeldig type of aanvraag-id voor deelfactuur.');}
        $timestamp=(int)$this->clock->get();$issueDate=date('Y-m-d',$timestamp);$dueDate=date('Y-m-d',strtotime('+'.$this->dueDays().' days',$timestamp));
        $notes='Aangemaakt vanuit offerte '.$draft->getQuoteNumber().'.';if($draft->getNotes()!==''){$notes.="\n\n".$draft->getNotes();}
        $externalId=$draft->getQuotePublicId().':'.$kind.':'.$requestId;
        if(strlen($externalId)>128){throw new \InvalidArgumentException('Offerte-id is te lang voor factuurkoppeling.');}
        $invoice=$this->creator()->execute(new CreateInvoiceCommand($draft->getCurrency(),$issueDate,$dueDate,$draft->getCustomer(),$draft->getAddress(),$draft->getLines(),$draft->getReference(),$notes,'admin_quote',$externalId));
        return new QuoteInvoiceConversionResult($invoice->getPublicId(),$this->invoiceUrl($invoice->getPublicId(),'draft'));
    }
    public function listForQuote(string $tenantId,string $quotePublicId):array
    {
        $connection=$this->connection?:Connection::getConnections();
        $prefix=$quotePublicId.':';
        $statement=$connection->prepare('SELECT `id`,`public_id`,`invoice_number`,`status`,`currency`,`issue_date`,`net_total_minor`,`tax_total_minor`,`gross_total_minor`,`external_id` FROM `admin_invoice` WHERE `tenant_id`=:tenant AND `source`=:source AND (`external_id`=:quote OR SUBSTR(`external_id`,1,:prefix_length)=:prefix) ORDER BY `created_at`,`id`');
        $statement->execute([':tenant'=>$tenantId,':source'=>'admin_quote',':quote'=>$quotePublicId,':prefix_length'=>strlen($prefix),':prefix'=>$prefix]);
        $lineStatement=$connection->prepare('SELECT `tax_category`,`tax_rate_basis_points`,`net_total_minor` FROM `admin_invoice_line` WHERE `tenant_id`=:tenant AND `invoice_id`=:invoice');
        $result=[];
        foreach($statement->fetchAll(\PDO::FETCH_ASSOC) as $row){
            $lineStatement->execute([':tenant'=>$tenantId,':invoice'=>(int)$row['id']]);$groups=[];
            foreach($lineStatement->fetchAll(\PDO::FETCH_ASSOC) as $line){$key=(string)$line['tax_category'].':'.(int)$line['tax_rate_basis_points'];$groups[$key]=($groups[$key]??0)+(int)$line['net_total_minor'];}
            $status=(string)$row['status'];$external=(string)$row['external_id'];$parts=explode(':',$external);$kind=count($parts)>=3?$parts[count($parts)-2]:'full';
            $result[]=['public_id'=>(string)$row['public_id'],'invoice_number'=>(string)($row['invoice_number']??''),'status'=>$status,'currency'=>(string)$row['currency'],'issue_date'=>(string)$row['issue_date'],'net_minor'=>(int)$row['net_total_minor'],'tax_minor'=>(int)$row['tax_total_minor'],'gross_minor'=>(int)$row['gross_total_minor'],'external_id'=>$external,'kind'=>$kind,'tax_groups'=>$groups,'url'=>$this->invoiceUrl((string)$row['public_id'],$status)];
        }
        return $result;
    }
    private function invoiceUrl(string $publicId,string $status):string{$base=$this->baseUrl!==''?$this->baseUrl:(defined('__DOMAIN__')?rtrim((string)constant('__DOMAIN__'),'/'):'');$route=$status==='draft'?'edit':'detail';return$base.'/Flexgrid/AdminInvoice/'.$route.'/'.rawurlencode($publicId);}
    private function creator():InvoiceDraftCreatorInterface{if($this->creator===null){$this->creator=AdminInvoiceFactory::createCreateInvoice();}return$this->creator;}
    private function dueDays():int{if($this->configuredDueDays!==null){return$this->validDueDays($this->configuredDueDays);}try{new Settings();$setting=Settings::get('billing_invoice_due_days');$value=is_array($setting)&&isset($setting['value'])?$setting['value']:30;$days=is_int($value)||(is_string($value)&&preg_match('/^[0-9]+$/D',$value)===1)?(int)$value:30;}catch(\Throwable $throwable){$days=30;}return$this->validDueDays($days);}
    private function validDueDays(int $days):int{return$days>=0&&$days<=365?$days:30;}
}
