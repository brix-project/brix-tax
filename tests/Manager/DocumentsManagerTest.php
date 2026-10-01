<?php

declare(strict_types=1);

require dirname(__DIR__, 4) . '/autoload.php';

use Brix\Tax\Manager\DocumentsManager;
use Brix\Tax\Tables\Payments\PaymentsEntity;
use Brix\Tax\Tables\Payments\PaymentsTable;
use Brix\Tax\Type\T_TaxMeta;

$paymentsFile = tempnam(sys_get_temp_dir(), 'documents-manager-test-');
unlink($paymentsFile);
$payments = new PaymentsTable(PaymentsEntity::class, $paymentsFile);

$assignedPayment = new PaymentsEntity('P-ASSIGNED-2026', 'TEST', '2026-07-14', 2591.82, 'EUR', 2591.82, 'EUR', 'Roland Distl', 'DE123', 'Re X-629 v. 07.07.26');
$assignedPayment->invoiceFile = '2026/_leuffen/2026-07-07__X629.pdf';
$payments->addObject($assignedPayment);

$otherPayment = new PaymentsEntity('P-OTHER-2026', 'TEST', '2026-07-15', 2591.82, 'EUR', 2591.82, 'EUR', 'Other', 'DE456', 'Re X-629');
$otherPayment->invoiceFile = '2026/other.pdf';
$payments->addObject($otherPayment);
$payments->save();

$managerReflection = new ReflectionClass(DocumentsManager::class);
$manager = $managerReflection->newInstanceWithoutConstructor();
$paymentsProperty = $managerReflection->getProperty('paymentsTable');
$paymentsProperty->setValue($manager, $payments);

$meta = new T_TaxMeta();
$meta->invoiceNumber = 'X-629';
$meta->invoiceDate = '2026-07-07';
$meta->invoiceTotal = 2591.82;
$meta->direction = 'outbound';
$meta->file = '2026/_leuffen/2026-07-07__X629.pdf';
$meta->payments = [];

$connectPayments = $managerReflection->getMethod('connectPayments');
$result = $connectPayments->invoke($manager, $meta);
$result = $connectPayments->invoke($manager, $result);

assert(count($result->payments) === 1);
assert($result->payments[0]->paymentId === 'P-ASSIGNED-2026');
assert($result->payments[0]->paymentAmount === 2591.82);
assert($otherPayment->invoiceFile === '2026/other.pdf');

unlink($paymentsFile);
echo "DocumentsManagerTest OK\n";
