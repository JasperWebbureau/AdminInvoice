<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Flexgrid\Modules\AdminCore\ValueObject\Currency;
use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminInvoice\Application\ReadModel\InvoiceDetailContext;
use Flexgrid\Modules\AdminInvoice\Contract\InvoiceDetailExtensionInterface;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\InvoiceStatus;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\PaymentStatus;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceDetailExtensionLoader;

final class InvoiceTestDetailExtension implements InvoiceDetailExtensionInterface
{
    public function render(InvoiceDetailContext $context): string
    {
        return '<aside data-test-extension>' . htmlspecialchars($context->getNumber(), ENT_QUOTES, 'UTF-8') . '</aside>';
    }
}

$context = new InvoiceDetailContext(
    'invoice-extension-test',
    '2026-0099',
    InvoiceStatus::FINALIZED,
    PaymentStatus::PARTIALLY_PAID,
    new Money(12100, Currency::euro())
);
$loader = new InvoiceDetailExtensionLoader(__DIR__ . '/missing-modules', [InvoiceTestDetailExtension::class]);
$html = $loader->render($context);

adminInvoiceAssert(strpos($html, 'data-test-extension') !== false, 'Detail-extensie moet door de generieke loader worden gerenderd.');
adminInvoiceAssert(strpos($html, '2026-0099') !== false, 'Detail-extensie moet de beperkte factuurcontext ontvangen.');
adminInvoiceAssert($context->canRegisterPayments(), 'Definitieve factuurcontext moet betalingen toestaan.');

$discovery = new InvoiceDetailExtensionLoader(dirname(__DIR__, 2));
$classesMethod = new ReflectionMethod($discovery, 'classes');
$classesMethod->setAccessible(true);
adminInvoiceAssert(
    in_array(
        'Flexgrid\\Modules\\AdminPayment\\Integration\\Invoice\\InvoiceDetailExtension',
        $classesMethod->invoke($discovery),
        true
    ),
    'Conventiegebaseerde ontdekking moet de geinstalleerde Payment-extensie vinden.'
);

$credited = new InvoiceDetailContext(
    'invoice-extension-credited',
    '2026-0100',
    InvoiceStatus::CREDITED,
    PaymentStatus::PAID,
    new Money(12100, Currency::euro())
);
adminInvoiceAssert(!$credited->canRegisterPayments(), 'Gecrediteerde factuur mag alleen betaalhistorie tonen.');

$controller = (string)file_get_contents(dirname(__DIR__) . '/src/Controller/AdminInvoiceController.php');
adminInvoiceAssert(
    strpos($controller, 'Flexgrid\\Modules\\AdminPayment\\') === false,
    'AdminInvoice-controller mag geen concrete Payment-module importeren.'
);
$template = (string)file_get_contents(dirname(__DIR__) . '/src/Templates/Detail/Index.php');
adminInvoiceAssert(strpos($template, 'data-admin-invoice-detail-extensions') !== false, 'Factuurdetail mist de generieke extensieslot.');

echo "AdminInvoice detail extension tests passed.\n";
