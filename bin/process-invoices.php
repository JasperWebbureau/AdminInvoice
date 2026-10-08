<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
$projectRoot = dirname(__DIR__, 4);
chdir($projectRoot);
require_once $projectRoot . '/Files/Boot/.env.php';
require_once $projectRoot . '/vendor/autoload.php';
require_once $projectRoot . '/Flexgrid/Flexgrid/src/Autoloader.php';
spl_autoload_register('Autoloader::autoload');
\Flexgrid\Database\Connection::addDatabase($db['name'], $db['host'], $db['username'], $db['password']);
unset($db);
$result = \Flexgrid\Modules\AdminInvoice\Service\AdminInvoiceFactory::createProcessing()->run();
$extension = \Flexgrid\Modules\AdminInvoice\Service\InvoiceCollectionExtensionLoader::load($projectRoot . '/Flexgrid/Modules');
$collections = $extension !== null ? $extension->processDue(date('Y-m-d')) : ['failed' => 0];
echo json_encode(['invoices' => $result, 'collections' => $collections], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($result['failed'] > 0 || $collections['failed'] > 0 ? 1 : 0);
