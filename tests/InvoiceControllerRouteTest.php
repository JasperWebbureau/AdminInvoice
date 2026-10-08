<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/src/Controller/AdminInvoiceController.php';
require_once dirname(__DIR__) . '/src/Controller/AdminRecurringInvoiceController.php';
require_once dirname(__DIR__, 3) . '/Flexgrid/src/Autowire/Parser/FGAnnotationParser.php';

use Flexgrid\Modules\AdminInvoice\Controller\AdminInvoiceController;
use Flexgrid\Modules\AdminInvoice\Controller\AdminRecurringInvoiceController;
use Flexgrid\Autowire\Parser\FGAnnotationParser;

$recurringMeta = (new FGAnnotationParser())->parseController((new ReflectionClass(AdminRecurringInvoiceController::class))->getDocComment());
adminInvoiceAssert(method_exists(AdminRecurringInvoiceController::class, 'create'),
    'Herhaalschema’s moeten een aparte aanmaakroute hebben.');
adminInvoiceAssert(
    ($recurringMeta['administrationLabel'] ?? '') === "Herhaalschema's"
    && ($recurringMeta['administrationRoute'] ?? '') === 'plans'
    && (int)($recurringMeta['administrationPriority'] ?? 0) === 43,
    'De herhaalschemalink moet als losse label-, route- en prioriteitsvelden worden ontdekt.'
);

$controller = new AdminInvoiceController();
$routeArgument = new ReflectionMethod($controller, 'routeArgument');
$routeArgument->setAccessible(true);

$publicId = 'cf05ba81-9c08-435c-8088-6e00c7f53b46';

adminInvoiceAssert(
    $routeArgument->invoke($controller, [$publicId]) === $publicId,
    'De editor moet de eerste routewaarde uit het Flexgrid argument-array lezen.'
);
adminInvoiceAssert(
    $routeArgument->invoke($controller, $publicId) === $publicId,
    'De editor moet een directe scalaire routewaarde blijven ondersteunen.'
);
adminInvoiceAssert(
    $routeArgument->invoke($controller, []) === '',
    'Een ontbrekend routeargument moet als een lege waarde worden behandeld.'
);

$controllerSource = (string)file_get_contents(dirname(__DIR__) . '/src/Controller/AdminInvoiceController.php');
foreach (['function credit()', 'function creditNote(', 'function creditPdf(', 'function previewPdf(', 'function reviewQueue(', 'function dispatch('] as $routeMethod) {
    adminInvoiceAssert(
        strpos($controllerSource, $routeMethod) !== false,
        'Controller mist creditnotaroute: ' . $routeMethod
    );
}
adminInvoiceAssert(
    strpos($controllerSource, "createGenerateInvoicePdf()->preview") !== false
    && strpos($controllerSource, "'previewPdf/'") !== false,
    'De conceptpreview moet als aparte tenantgebonden PDF-route beschikbaar zijn.'
);
adminInvoiceAssert(
    strpos($controllerSource, "createFullCreditNote()->execute") !== false,
    'De AJAX-creditactie moet de transactionele use-case aanroepen.'
);

echo "Invoice controller route tests passed.\n";
