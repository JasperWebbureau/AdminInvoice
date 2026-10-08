<?php

declare(strict_types=1);

if (!defined('__ROOTDIR__')) {
    define('__ROOTDIR__', dirname(__DIR__, 4));
}
if (!defined('__SLASH__')) {
    define('__SLASH__', DIRECTORY_SEPARATOR);
}

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/Flexgrid/src/Html/Attributes.php';
require_once dirname(__DIR__, 3) . '/Flexgrid/src/Utils/_Dir.php';
require_once dirname(__DIR__, 3) . '/Flexgrid/src/Utils/_File.php';
require_once dirname(__DIR__, 3) . '/Flexgrid/src/Utils/Files/LessFile.php';
require_once dirname(__DIR__, 3) . '/Flexgrid/src/Response/Assets/AssetManager.php';
require_once dirname(__DIR__, 3) . '/Flexgrid/src/Response/Response.php';
require_once dirname(__DIR__, 3) . '/Flexgrid/src/Response/PageResponse.php';
require_once dirname(__DIR__, 3) . '/Flexgrid/src/Html/Table/TrustedHtml.php';
require_once dirname(__DIR__, 3) . '/Flexgrid/src/Html/Table/TableRenderer.php';
require_once dirname(__DIR__) . '/src/Controller/AdminInvoiceCatalogController.php';

use Flexgrid\Modules\AdminInvoice\Controller\AdminInvoiceCatalogController;

$controller = new AdminInvoiceCatalogController();
$method = new ReflectionMethod($controller, 'table');
$method->setAccessible(true);
$items = [
    [
        'public_id' => 'group-one', 'name' => 'Basic', 'item_type' => 'group',
        'parent_public_id' => '', 'variant_key' => '', 'description' => 'Basic',
        'currency' => 'EUR', 'unit_price' => '0', 'unit' => 'groep', 'active' => 1,
    ],
    [
        'public_id' => 'item-one', 'name' => '<script>variant</script>', 'item_type' => 'item',
        'parent_public_id' => 'group-one', 'variant_key' => '0_2',
        'description' => 'Basic 0-2', 'currency' => 'EUR', 'unit_price' => '25.00',
        'unit' => 'maand', 'active' => 1,
    ],
];
$table = $method->invoke($controller, $items, $items);
ob_start();
include dirname(__DIR__) . '/src/Templates/Catalog/Index.php';
$overview = (string)ob_get_clean();
adminInvoiceAssert(strpos($overview, 'data-fg-table="admin-invoice-catalog"') !== false
    && strpos($overview, 'Nieuw factuuritem') === false
    && strpos($overview, '<form') === false,
    'Het catalogusoverzicht toont de gedeelde tabel zonder editorformulier.');
adminInvoiceAssert(strpos($overview, '&lt;script&gt;variant&lt;/script&gt;') !== false
    && strpos($overview, '<script>variant</script>') === false,
    'Catalogusnamen worden door TableRenderer veilig weergegeven.');

$overview = true;
ob_start();
include dirname(__DIR__) . '/src/Templates/Catalog/HeaderActions.php';
$headerActions = (string)ob_get_clean();
adminInvoiceAssert(strpos($headerActions, 'Nieuw factuuritem') !== false
    && strpos($headerActions, 'Nieuwe keuzegroep') !== false,
    'De aanmaakacties staan in de globale admin-header.');
$overview = false;
ob_start();
include dirname(__DIR__) . '/src/Templates/Catalog/HeaderActions.php';
$headerActions = (string)ob_get_clean();
adminInvoiceAssert(strpos($headerActions, 'Terug naar factuuritems') !== false,
    'De bewerkpagina heeft een terugweg in de globale admin-header.');

$current = $items[0];
$currentType = 'group';
$groups = [$items[0]];
$choiceFunctions = ['chooseBasic' => ['label' => 'Basic op auto-aantal']];
$selectedParentId = '';
$groupVariants = [];
$variantTable = $method->invoke($controller, [$items[1]], $items, 'Varianten van Basic');
$deleteReason = 'Deze groep is gekoppeld.';
$saveAction = 'catalog-save';
$deleteAction = 'catalog-delete';
ob_start();
include dirname(__DIR__) . '/src/Templates/Catalog/Edit.php';
$editor = (string)ob_get_clean();
adminInvoiceAssert(strpos($editor, 'data-fg-table="admin-invoice-catalog-variants"') !== false
    && strpos($editor, 'action="catalog-save"') !== false
    && strpos($editor, 'name="choose_function"') !== false
    && strpos($editor, '/Flexgrid/AdminInvoiceCatalog/items?parent_id=group-one') !== false,
    'De groepsdetailpagina toont eerst varianten en daarna het groepsformulier.');

$current = null;
$currentType = 'item';
$variantTable = null;
ob_start();
include dirname(__DIR__) . '/src/Templates/Catalog/Edit.php';
$create = (string)ob_get_clean();
adminInvoiceAssert(strpos($create, 'name="unit_price"') !== false
    && strpos($create, 'name="parent_public_id"') !== false
    && strpos($create, 'invoice-item-parent') !== false
    && strpos($create, 'data-fg-table=') === false,
    'Het aanmaakformulier staat op een eigen pagina en behoudt variantkeuze en prijsvelden.');

echo "AdminInvoice catalog template tests passed.\n";
