<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Service;

use Flexgrid\App\Settings\Settings;
use Flexgrid\Database\Connection;
use Flexgrid\Modules\AdminCore\Context\TenantContext;
use Flexgrid\Modules\AdminCore\Infrastructure\PdoTransactionManager;
use Flexgrid\Modules\AdminCore\Infrastructure\PdoNumberSequence;
use Flexgrid\Modules\AdminCore\Infrastructure\UuidV4Generator;
use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\AddInvoiceLine;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\CreateInvoice;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\CorrectInvoiceIssueDate;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\DeleteDraftInvoice;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\CreateFullCreditNote;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\GetInvoice;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\FinalizeInvoice;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\GenerateCreditNotePdf;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\GenerateInvoicePdf;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\GetCreditNote;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\ListInvoices;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\ListCustomerDocuments;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\RemoveInvoiceLine;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\UpdateDraftInvoice;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\RefreshDraftInvoiceCustomer;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\UpdateInvoiceLine;
use Flexgrid\Modules\AdminInvoice\Infrastructure\Persistence\PdoInvoiceListRepository;
use Flexgrid\Modules\AdminInvoice\Infrastructure\Persistence\PdoInvoiceRepository;
use Flexgrid\Modules\AdminInvoice\Infrastructure\Persistence\PdoCreditNoteRepository;
use Flexgrid\Modules\AdminInvoice\Infrastructure\Mail\PhpMailerInvoiceSender;
use Flexgrid\Modules\AdminPdf\Infrastructure\DompdfDocumentRenderer;
use Flexgrid\Modules\AdminPdf\Infrastructure\FilesystemTemplateResolver;
use Flexgrid\Modules\AdminPdf\ValueObject\PdfRenderOptions;
use Flexgrid\Utils\Files\ImageFile;
use Flexgrid\Utils\_Time;

final class AdminInvoiceFactory
{
    public static function createListInvoices(): ListInvoices
    {
        return new ListInvoices(
            self::tenantContext(),
            new PdoInvoiceListRepository(Connection::getConnections())
        );
    }

    public static function createListCustomerDocuments(): ListCustomerDocuments
    {
        return new ListCustomerDocuments(
            self::tenantContext(),
            new PdoInvoiceListRepository(self::connection())
        );
    }

    public static function createOverviewPresenter(): InvoiceOverviewPresenter
    {
        return new InvoiceOverviewPresenter(new _Time());
    }

    public static function createCustomerInvoiceHistoryPresenter(): CustomerInvoiceHistoryPresenter
    {
        return new CustomerInvoiceHistoryPresenter(new InvoiceOverviewPresenter(new _Time()));
    }

    public static function createCreateInvoice(): CreateInvoice
    {
        $connection = self::connection();

        return new CreateInvoice(
            self::tenantContext(),
            new UuidV4Generator(),
            new PdoTransactionManager($connection),
            new PdoInvoiceRepository($connection),
            new InvoiceCalculator(),
            new _Time()
        );
    }

    public static function createCatalog(): InvoiceCatalog
    {
        return new InvoiceCatalog(
            self::connection(), self::tenantContext()->getTenantId(), new UuidV4Generator()
        );
    }

    public static function createRecurringInvoices(): RecurringInvoices
    {
        $connection = self::connection();
        $tenant = self::tenantContext()->getTenantId();
        return new RecurringInvoices(
            $connection,
            $tenant,
            new UuidV4Generator(),
            new InvoiceCatalog($connection, $tenant, new UuidV4Generator()),
            self::createCreateInvoice(),
            (new InvoiceCustomerProviderLoader(dirname(__DIR__, 3)))->getProvider(),
            (new RecurringInvoiceLinesExtensionLoader(rtrim((string)__ROOTDIR__, '/\\') . '/App'))->getExtension(),
            static function (\Flexgrid\Modules\AdminInvoice\Domain\Model\Invoice $invoice): void {
                if (self::processingReady()) { self::createProcessing()->enroll($invoice); }
                elseif (InvoiceProcessingSettings::automaticFinalization()) {
                    throw new \LogicException('Installeer eerst de factuurverwerking voordat automatische goedkeuring wordt gebruikt.');
                }
            }
        );
    }

    public static function createReviewQueue(): InvoiceReviewQueue
    {
        return new InvoiceReviewQueue(self::connection(), self::tenantContext()->getTenantId());
    }

    public static function createProcessing(): InvoiceProcessing
    {
        return new InvoiceProcessing(self::connection(), self::tenantContext()->getTenantId(),
            static function (string $id) { return self::createGetInvoice()->execute($id); },
            static function (string $id) { return self::createFinalizeInvoice()->execute($id); },
            self::createDispatchService(), InvoiceCollectionExtensionLoader::load(dirname(__DIR__, 3)),
            static function (): bool { return InvoiceProcessingSettings::automaticFinalization(); });
    }

    public static function processingReady(): bool
    {
        try { self::connection()->query('SELECT 1 FROM `admin_invoice_processing` LIMIT 1'); return true; }
        catch (\PDOException $exception) { return false; }
    }

    public static function createDispatchService(): InvoiceDispatchService
    {
        return new InvoiceDispatchService(
            self::connection(),
            self::tenantContext()->getTenantId(),
            self::createGenerateInvoicePdf(),
            new PhpMailerInvoiceSender()
        );
    }

    public static function createGetInvoice(): GetInvoice
    {
        return new GetInvoice(
            self::tenantContext(),
            new PdoInvoiceRepository(self::connection())
        );
    }

    public static function createFinalizeInvoice(): FinalizeInvoice
    {
        $connection = self::connection();

        return new FinalizeInvoice(
            self::tenantContext(),
            new PdoTransactionManager($connection),
            new PdoNumberSequence($connection),
            new PdoInvoiceRepository($connection),
            new InvoiceCalculator(),
            new InvoiceNumberFormatter(),
            new _Time()
        );
    }

    public static function createUpdateDraftInvoice(): UpdateDraftInvoice
    {
        $connection = self::connection();

        return new UpdateDraftInvoice(
            self::tenantContext(),
            new PdoTransactionManager($connection),
            new PdoInvoiceRepository($connection),
            new InvoiceCalculator(),
            new _Time()
        );
    }

    public static function createRefreshDraftInvoiceCustomer(): RefreshDraftInvoiceCustomer
    {
        $connection = self::connection();
        return new RefreshDraftInvoiceCustomer(self::tenantContext(),new PdoTransactionManager($connection),new PdoInvoiceRepository($connection),new InvoiceCalculator(),new _Time());
    }

    public static function createDeleteDraftInvoice(): DeleteDraftInvoice
    {
        $connection = self::connection();

        return new DeleteDraftInvoice(
            self::tenantContext(),
            new PdoTransactionManager($connection),
            new PdoInvoiceRepository($connection)
        );
    }

    public static function createCorrectInvoiceIssueDate(): CorrectInvoiceIssueDate
    {
        $connection = self::connection();
        return new CorrectInvoiceIssueDate(
            self::tenantContext(),
            new PdoTransactionManager($connection),
            new PdoInvoiceRepository($connection),
            new _Time()
        );
    }

    public static function createAddInvoiceLine(): AddInvoiceLine
    {
        $connection = self::connection();

        return new AddInvoiceLine(
            self::tenantContext(),
            new UuidV4Generator(),
            new PdoTransactionManager($connection),
            new PdoInvoiceRepository($connection),
            new InvoiceCalculator(),
            new _Time()
        );
    }

    public static function createRemoveInvoiceLine(): RemoveInvoiceLine
    {
        $connection = self::connection();

        return new RemoveInvoiceLine(
            self::tenantContext(),
            new PdoTransactionManager($connection),
            new PdoInvoiceRepository($connection),
            new InvoiceCalculator(),
            new _Time()
        );
    }

    public static function createUpdateInvoiceLine(): UpdateInvoiceLine
    {
        $connection = self::connection();

        return new UpdateInvoiceLine(
            self::tenantContext(),
            new PdoTransactionManager($connection),
            new PdoInvoiceRepository($connection),
            new InvoiceCalculator(),
            new _Time()
        );
    }

    public static function createEditorPresenter(): InvoiceEditorPresenter
    {
        return new InvoiceEditorPresenter(new InvoiceCalculator());
    }

    public static function createCreditNotePresenter(): CreditNotePresenter
    {
        return new CreditNotePresenter();
    }

    public static function createGenerateInvoicePdf(): GenerateInvoicePdf
    {
        $projectRoot = rtrim((string)__ROOTDIR__, '/\\');
        $projectTemplates = $projectRoot . '/Templates/AdminInvoice/Pdf';
        $moduleTemplates = dirname(__DIR__) . '/Templates/Pdf';
        $resolver = new FilesystemTemplateResolver(self::existingTemplateRoots($projectTemplates, $moduleTemplates));

        new Settings();
        $branding = (new ProjectInvoiceBranding($projectRoot, new ScssBrandColorReader()))->create(
            self::companySettings(),
            self::companyLogoPath()
        );

        return new GenerateInvoicePdf(
            new DompdfDocumentRenderer($resolver),
            new InvoicePdfTemplateSelector($resolver),
            new InvoicePdfViewModelFactory(),
            new PdfRenderOptions('A4', 'portrait', 'DejaVu Sans', array_merge(
                self::existingTemplateRoots($projectTemplates, $moduleTemplates),
                [$projectRoot . '/Files']
            )),
            self::settingValue('administration_invoice_pdf_template', 'default'),
            $branding,
            new DompdfDocumentRenderer(new FilesystemTemplateResolver([$moduleTemplates])),
            static function (\Flexgrid\Modules\AdminInvoice\Domain\Model\Invoice $invoice): ?array {
                $provider = '\\Flexgrid\\Modules\\AdminPayment\\Integration\\Invoice\\InvoicePdfPaymentLink';
                return class_exists($provider) ? (new $provider())->forInvoice($invoice) : null;
            }
        );
    }

    public static function createFullCreditNote(): CreateFullCreditNote
    {
        $connection = self::connection();

        return new CreateFullCreditNote(
            self::tenantContext(),
            new UuidV4Generator(),
            new PdoTransactionManager($connection),
            new PdoNumberSequence($connection),
            new PdoInvoiceRepository($connection),
            new PdoCreditNoteRepository($connection),
            new InvoiceCalculator(),
            new CreditNoteNumberFormatter(),
            new _Time()
        );
    }

    public static function createGetCreditNote(): GetCreditNote
    {
        return new GetCreditNote(
            self::tenantContext(),
            new PdoCreditNoteRepository(self::connection())
        );
    }

    public static function createGenerateCreditNotePdf(): GenerateCreditNotePdf
    {
        $projectRoot = rtrim((string)__ROOTDIR__, '/\\');
        $projectTemplates = $projectRoot . '/Templates/AdminInvoice/Pdf';
        $moduleTemplates = dirname(__DIR__) . '/Templates/Pdf';
        $resolver = new FilesystemTemplateResolver(self::existingTemplateRoots($projectTemplates, $moduleTemplates));

        new Settings();
        $branding = (new ProjectInvoiceBranding($projectRoot, new ScssBrandColorReader()))->create(
            self::companySettings(),
            self::companyLogoPath()
        );

        return new GenerateCreditNotePdf(
            new DompdfDocumentRenderer($resolver),
            new InvoicePdfTemplateSelector($resolver),
            new CreditNotePdfViewModelFactory(),
            new PdfRenderOptions('A4', 'portrait', 'DejaVu Sans', array_merge(
                self::existingTemplateRoots($projectTemplates, $moduleTemplates),
                [$projectRoot . '/Files']
            )),
            self::settingValue(
                'administration_credit_note_pdf_template',
                self::settingValue('administration_invoice_pdf_template', 'default')
            ),
            $branding
        );
    }

    private static function connection(): \PDO
    {
        return Connection::getConnections();
    }

    private static function existingTemplateRoots(string $project, string $module): array
    {
        return is_dir($project) ? [$project, $module] : [$module];
    }

    private static function tenantContext(): TenantContext
    {
        if (!defined('__ADMIN_TENANT_ID__')) {
            throw new \LogicException('Definieer __ADMIN_TENANT_ID__ expliciet voor de Admin-modules.');
        }

        return new TenantContext(new TenantId((string)constant('__ADMIN_TENANT_ID__')));
    }

    private static function companySettings(): array
    {
        $settings = [];
        foreach ([
            'name', 'street', 'postal', 'city', 'country', 'vat_nr', 'kvk',
            'iban', 'iban_holder', 'email', 'tel', 'domain',
        ] as $key) {
            try {
                $value = Settings::Company($key);
            } catch (\Throwable $throwable) {
                $value = '';
            }
            $settings[$key] = is_string($value) || is_int($value) ? (string)$value : '';
        }

        return $settings;
    }

    private static function companyLogoPath(): string
    {
        try {
            $value = Settings::Company('logo');
            if ((!is_string($value) && !is_int($value)) || trim((string)$value) === '') {
                return '';
            }
            $file = ImageFile::getImageFile($value);
            return $file->exists() ? $file->getPath() : '';
        } catch (\Throwable $throwable) {
            return '';
        }
    }

    private static function settingValue(string $key, string $fallback): string
    {
        $setting = Settings::get($key);
        if (!is_array($setting) || !isset($setting['value'])) {
            return $fallback;
        }
        $value = $setting['value'];

        return is_string($value) || is_int($value) ? trim((string)$value) : $fallback;
    }
}
