<?php

namespace Brix\Tax\Analytics;

use Brix\Tax\Helper\CSVEntityTable;
use Brix\Tax\Manager\JournalManager;
use Brix\Tax\Tables\AccountsSuppliers\AccountsSuppliersTable;
use Brix\Tax\Type\T_CostType;

class BwaGenerator implements AnalyticsInterface
{
    private AccountsSuppliersTable $suppliers;
    private CSVEntityTable $costTypes;

    public function __construct(AccountsSuppliersTable $suppliers, string $costTypesFile)
    {
        $this->suppliers = $suppliers;
        $this->costTypes = new CSVEntityTable(T_CostType::class, $costTypesFile);
    }

    public function process(JournalManager $journalManager, string $year, ?string $endDate = null): array
    {
        $expenses = [];
        $unassigned = 0.0;
        $revenue = 0.0;

        $costTypesByKey = [];
        foreach ($this->costTypes->getGenerator() as $costType) {
            $costTypesByKey[$costType->key] = $costType;
            if ($costType->bwaZeile !== '' && !array_key_exists($costType->bwaZeile, $expenses)) {
                $expenses[$costType->bwaZeile] = 0.0;
            }
        }

        foreach ($journalManager->getJournal((int)$year) as $entry) {
            if ($endDate !== null && $entry->date > $endDate) {
                continue;
            }
            if ($entry->direction === 'outbound') {
                $revenue += (float)$entry->net_amount_credit;
                continue;
            }

            $amount = (float)$entry->net_amount_debit;
            $supplier = $this->suppliers->getSupplierByVatNr($entry->counterpartyVatNumber);
            if ($supplier === null && $entry->file !== '') {
                $supplier = $this->suppliers->getSupplierById(basename(dirname($entry->file)));
            }
            $costTypeKey = $supplier?->kostenartKey;
            $costType = $costTypeKey ? ($costTypesByKey[$costTypeKey] ?? null) : null;

            if ($costType === null || !array_key_exists($costType->bwaZeile, $expenses)) {
                $unassigned += $amount;
                continue;
            }
            $expenses[$costType->bwaZeile] += $amount;
        }

        $otherOperatingExpenses = 'Sonstige betriebliche Aufwendungen';
        $expenses[$otherOperatingExpenses] = ($expenses[$otherOperatingExpenses] ?? 0.0) + $unassigned;

        $rows = [
            $this->row('Umsatzerlöse', $revenue),
            $this->row('= Gesamtleistung', $revenue),
        ];
        $materialCosts = 0.0;
        foreach ($expenses as $bwaLine => $amount) {
            $rows[] = $this->row('- ' . $bwaLine, $amount);
            if (str_starts_with($bwaLine, 'Materialaufwand')) {
                $materialCosts += $amount;
                $grossProfit = $revenue - $materialCosts;
                $rows[] = $this->row('= Rohertrag', $grossProfit);
                $rows[] = $this->row('= Betrieblicher Rohertrag', $grossProfit);
            }
        }

        $operatingCosts = array_sum($expenses) - $materialCosts;
        $rows[] = $this->row('= Gesamtkosten', $operatingCosts);
        $rows[] = $this->row('= Betriebsergebnis', $revenue - array_sum($expenses));

        return $rows;
    }

    private function row(string $position, float $amount): array
    {
        return [
            'BWA-Position' => $position,
            'Nettobetrag' => number_format($amount, 2, ',', '.') . ' EUR',
        ];
    }
}
