<?php

declare(strict_types=1);

require dirname(__DIR__, 4) . '/autoload.php';

use Brix\Tax\Analytics\BwaGenerator;
use Brix\Tax\Manager\JournalManager;
use Brix\Tax\Tables\AccountsSuppliers\AccountsSuppliersEntity;
use Brix\Tax\Tables\AccountsSuppliers\AccountsSuppliersTable;
use Brix\Tax\Type\T_TaxJournal;

$directory = sys_get_temp_dir() . '/bwa-test-' . bin2hex(random_bytes(4));
mkdir($directory);
file_put_contents($directory . '/suppliers.csv', "supplierId,name,vatId,lastSeen,kostenartKey\n");
file_put_contents($directory . '/cost-types.csv', "key,beschreibung,bwaZeile\nmaterial,Material,Materialaufwand / Fremdleistungen\nsoftware,Software,Individuelle Kosten\nsonstige,Sonstige,Sonstige betriebliche Aufwendungen\n");

$suppliers = new AccountsSuppliersTable($directory . '/suppliers.csv');
$suppliers->addObject(new AccountsSuppliersEntity('supplier-S1', 'Supplier', 'DE123', '2026-01-01', 'software'));
$journal = new JournalManager();

$revenue = new T_TaxJournal();
$revenue->date = '2026-01-01';
$revenue->direction = 'outbound';
$revenue->net_amount_credit = 1000.0;
$revenue->net_amount_debit = null;
$journal->journal[] = $revenue;

$creditNote = clone $revenue;
$creditNote->date = '2026-01-02';
$creditNote->net_amount_credit = -100.0;
$journal->journal[] = $creditNote;

$expense = new T_TaxJournal();
$expense->date = '2026-01-03';
$expense->direction = 'inbound';
$expense->counterpartyVatNumber = 'DE123';
$expense->net_amount_debit = 200.0;
$expense->net_amount_credit = null;
$journal->journal[] = $expense;

$unassignedExpense = clone $expense;
$unassignedExpense->date = '2026-01-04';
$unassignedExpense->counterpartyVatNumber = 'DE999';
$unassignedExpense->net_amount_debit = 50.0;
$unassignedExpense->file = '';
$journal->journal[] = $unassignedExpense;

$futureRevenue = clone $revenue;
$futureRevenue->date = '2026-12-01';
$futureRevenue->net_amount_credit = 500.0;
$journal->journal[] = $futureRevenue;

$rows = (new BwaGenerator($suppliers, $directory . '/cost-types.csv'))->process(
    $journal,
    '2026',
    '2026-11-30'
);
assert($rows[0]['Nettobetrag'] === '900,00 EUR');
assert($rows[1]['BWA-Position'] === '= Gesamtleistung');
assert($rows[2]['BWA-Position'] === '- Materialaufwand / Fremdleistungen');
assert($rows[3]['BWA-Position'] === '= Rohertrag');
assert($rows[4]['BWA-Position'] === '= Betrieblicher Rohertrag');
assert($rows[5]['BWA-Position'] === '- Individuelle Kosten');
assert($rows[5]['Nettobetrag'] === '200,00 EUR');
assert($rows[6]['BWA-Position'] === '- Sonstige betriebliche Aufwendungen');
assert($rows[6]['Nettobetrag'] === '50,00 EUR');
assert($rows[7]['BWA-Position'] === '= Gesamtkosten');
assert($rows[7]['Nettobetrag'] === '250,00 EUR');
assert($rows[8]['BWA-Position'] === '= Betriebsergebnis');
assert($rows[8]['Nettobetrag'] === '650,00 EUR');

echo "BwaGeneratorTest OK\n";
