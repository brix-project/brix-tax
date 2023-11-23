<?php

namespace Brix\Tax\Analytics;

use Brix\Tax\Manager\JournalManager;

class TaxOverview implements AnalyticsInterface
{





    public function process(JournalManager $journalManager, string $year) : array
    {

        $ret = [];

        $total = new TaxOverviewRow();
        $total->year = $year;

        for($i=1; $i<=12; $i++) {
            $r = new TaxOverviewRow();
            $r->year = $year;
            $r->month = $i;
            foreach ($journalManager->getJournal($year, $i) as $entry) {
                $r->netIncome += $entry->net_amount_credit;
                $r->netExpenses += $entry->net_amount_debit;
                $r->vatIn += $entry->vat_credit;
                $r->vatPaid += $entry->vat_debit;
                $r->sumNet += $entry->net_amount_credit - $entry->net_amount_debit;
                $r->sumVat += $entry->vat_credit - $entry->vat_debit;
            }
            $total->netIncome += $r->netIncome;
            $total->netExpenses += $r->netExpenses;
            $total->vatIn += $r->vatIn;
            $total->vatPaid += $r->vatPaid;
            $total->sumNet += $r->sumNet;
            $total->sumVat += $r->sumVat;

            $ret[] = $r;
        }

        $ret[] = $total;
        return $ret;

    }
}
