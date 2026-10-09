<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/flexgrid/src/Html/Table/TrustedHtml.php';
require_once dirname(__DIR__, 3) . '/flexgrid/src/Html/Table/TableRenderer.php';

use Flexgrid\Modules\AdminInvoice\Application\ReadModel\InvoiceCustomerSelection;
use Flexgrid\Modules\AdminInvoice\Contract\InvoiceCustomerProviderInterface;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceCustomerProviderLoader;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceCustomerPrefill;

if (!function_exists('t')) {
    function t(string $key, string $fallback): string
    {
        return $fallback;
    }
}

final class InvoiceTestCustomerProvider implements InvoiceCustomerProviderInterface
{
    public function search(string $query, int $limit = 10): array
    {
        return [$this->selection('<script>Klant</script>')];
    }
    public function find(string $publicId): ?InvoiceCustomerSelection
    {
        return $publicId === 'customer-select-1' ? $this->selection('Klant B.V.') : null;
    }
    private function selection(string $name): InvoiceCustomerSelection
    {
        return new InvoiceCustomerSelection('customer-select-1', [
            'name' => $name,
            'contact_name' => 'Ada Admin',
            'email' => 'ada@example.test',
            'phone' => '030-1234567',
            'registration_number' => '12345678',
            'tax_number' => 'NL001234567B01',
        ], [
            'line_1' => 'Markt 1',
            'line_2' => '',
            'postal_code' => '1000 AA',
            'city' => 'Utrecht',
            'region' => '',
            'country_code' => 'NL',
        ]);
    }
}

$loader = new InvoiceCustomerProviderLoader(__DIR__ . '/missing-modules', [InvoiceTestCustomerProvider::class]);
$provider = $loader->getProvider();
adminInvoiceAssert($provider instanceof InvoiceCustomerProviderInterface, 'Loader moet de optionele klantprovider leveren.');
adminInvoiceAssert($provider->find('customer-select-1')->getParty()['name'] === 'Klant B.V.', 'Providercontract moet een snapshotselectie leveren.');

$discovery = new InvoiceCustomerProviderLoader(dirname(__DIR__, 2));
$classes = new ReflectionMethod($discovery, 'classes');
$classes->setAccessible(true);
adminInvoiceAssert(
    in_array(
        'Flexgrid\\Modules\\AdminCustomer\\Integration\\Invoice\\CustomerSelectionProvider',
        $classes->invoke($discovery),
        true
    ),
    'Conventieloader moet de geïnstalleerde AdminCustomer-provider ontdekken.'
);
adminInvoiceAssert(
    $discovery->getProvider() instanceof InvoiceCustomerProviderInterface,
    'Ontdekte AdminCustomer-provider moet via de module-autoloader instantieerbaar zijn.'
);

$prefill = (new InvoiceCustomerPrefill())->resolve($provider, 'customer-select-1');
adminInvoiceAssert($prefill['customer']['source_public_id'] === 'customer-select-1', 'Voorinvulling moet de publieke Customer-bron-id behouden.');
adminInvoiceAssert($prefill['customer']['name'] === 'Klant B.V.', 'Voorinvulling moet de klantsnapshot overnemen.');
adminInvoiceAssert($prefill['address']['city'] === 'Utrecht', 'Voorinvulling moet het primaire factuuradres overnemen.');
adminInvoiceAssert($prefill['notice'] === '', 'Geldige klantvoorinvulling mag geen waarschuwing tonen.');

$customer = $prefill['customer'];
$address = $prefill['address'];
$prefillNotice = $prefill['notice'];
$customerSelector = '';
$createAction = 'invoice-create-event';
$overviewUrl = '/Flexgrid/AdminInvoice/invoices';
$issueDate = '2026-09-18';
$dueDate = '2026-10-18';
ob_start();
include dirname(__DIR__) . '/src/Templates/Create/Index.php';
$createHtml = (string)ob_get_clean();
adminInvoiceAssert(strpos($createHtml, 'value="customer-select-1"') !== false, 'Vooringevuld formulier moet de Customer-bron-id posten.');
adminInvoiceAssert(strpos($createHtml, 'value="Klant B.V."') !== false, 'Vooringevuld formulier moet de klantnaam tonen.');
adminInvoiceAssert(strpos($createHtml, 'value="Utrecht"') !== false, 'Vooringevuld formulier moet het factuuradres tonen.');
foreach (['address[line_1]', 'address[postal_code]', 'address[city]', 'address[country_code]', 'customer[contact_name]'] as $optionalField) {
    preg_match('/<input[^>]*name="' . preg_quote($optionalField, '/') . '"[^>]*>/', $createHtml, $input);
    adminInvoiceAssert(isset($input[0]) && strpos($input[0], ' required') === false, 'Adres en contactpersoon mogen geen verplichte formuliervelden zijn.');
}
$optionalSelection = new InvoiceCustomerSelection('customer-optional', ['name' => 'Zonder adres of contact'], []);
adminInvoiceAssert($optionalSelection->getBillingAddress() === [], 'Klantselectie moet een ontbrekend adres accepteren.');

$missingPrefill = (new InvoiceCustomerPrefill())->resolve($provider, 'missing-customer');
adminInvoiceAssert($missingPrefill['customer']['source_public_id'] === '', 'Ongeldige klantvoorinvulling mag geen bronkoppeling bewaren.');
adminInvoiceAssert($missingPrefill['address']['country_code'] === 'NL', 'Handmatige fallback moet een veilige standaardlandcode houden.');
adminInvoiceAssert($missingPrefill['notice'] !== '', 'Ongeldige klantvoorinvulling moet duidelijk naar handmatige invoer terugvallen.');

$selections = $provider->search('Klant');
$query = 'Klant';
ob_start();
include dirname(__DIR__) . '/src/Templates/CustomerSelector/Results.php';
$resultsHtml = (string)ob_get_clean();
adminInvoiceAssert(strpos($resultsHtml, '<script>Klant</script>') === false, 'Zoekresultaten mogen geen HTML injecteren.');
adminInvoiceAssert(strpos($resultsHtml, '&lt;script&gt;Klant&lt;/script&gt;') !== false, 'Klantnaam moet escaped zichtbaar blijven.');
adminInvoiceAssert(strpos($resultsHtml, 'data-customer-public-id="customer-select-1"') !== false, 'Resultaat moet de publieke Customer-id aanbieden.');
adminInvoiceAssert(strpos($resultsHtml, 'data-address-city="Utrecht"') !== false, 'Resultaat moet de factuuradressnapshot aanbieden.');

$currentCustomer = ['source_public_id' => 'customer-select-1', 'name' => '<Gekoppeld>'];
$searchAction = 'customer-search-event';
ob_start();
include dirname(__DIR__) . '/src/Templates/CustomerSelector/Selector.php';
$selectorHtml = (string)ob_get_clean();
adminInvoiceAssert(strpos($selectorHtml, 'ajax="true"') !== false, 'Klantzoeker moet Flexgrids declaratieve AJAX gebruiken.');
adminInvoiceAssert(strpos($selectorHtml, 'action="customer-search-event"') !== false, 'Klantzoeker moet de AJAX-eventnaam gebruiken.');
adminInvoiceAssert(strpos($selectorHtml, 'Gekoppeld aan &lt;Gekoppeld&gt;') !== false, 'Bestaande koppeling moet escaped worden getoond.');

$controller = (string)file_get_contents(dirname(__DIR__) . '/src/Controller/AdminInvoiceController.php');
adminInvoiceAssert(
    strpos($controller, 'Flexgrid\\Modules\\AdminCustomer\\') === false,
    'AdminInvoice-controller mag geen concrete AdminCustomer-module importeren.'
);
adminInvoiceAssert(
    strpos($controller, 'public function create($args = [])') !== false
    && strpos($controller, 'new InvoiceCustomerPrefill()') !== false,
    'Factuuraanmaak moet een optionele publieke Customer-id via de provider kunnen voorinvullen.'
);

$customerName = '<script>Klant</script>';
$createInvoiceUrl = '/Flexgrid/AdminInvoice/create/customer-select-1';
$canCreateInvoice = true;
$isActive = true;
$hasPrimaryBillingAddress = false;
$historyTable = [
    'id' => 'admin-customer-invoice-history',
    'label' => 'Recente documenten',
    'compact' => true,
    'columns' => [['key' => 'document', 'label' => 'Document']],
    'rows' => [],
    'empty' => ['title' => 'Nog geen documenten', 'message' => '', 'icon' => 'fas fa-file-invoice'],
];
ob_start();
include dirname(__DIR__) . '/src/Templates/Customer/DetailExtension.php';
$extensionHtml = (string)ob_get_clean();
adminInvoiceAssert(strpos($extensionHtml, '<script>Klant</script>') === false, 'Klantdetailextensie moet de klantnaam escapen.');
adminInvoiceAssert(strpos($extensionHtml, '&lt;script&gt;Klant&lt;/script&gt;') !== false, 'Escapete klantnaam moet zichtbaar blijven.');
adminInvoiceAssert(strpos($extensionHtml, 'button button-publish') !== false, 'Nieuwe factuur moet als duidelijke primaire actie worden getoond.');
adminInvoiceAssert(strpos($extensionHtml, 'button-outline') === false, 'Klantdetailextensie mag geen onzichtbare outlineknop gebruiken.');

echo "AdminInvoice customer selection tests passed.\n";
