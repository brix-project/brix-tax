<?php

namespace Brix\Tax\Analytics;

use Brix\Tax\Manager\JournalManager;

class TaxOverview implements AnalyticsInterface
{





    public function process(JournalManager $journalManager, string $year) : array
    {

        $ret = [];

        $qret = [];
        $total = new TaxOverviewRow();
        $total->year = $year;

        // ERstelle zusätzlich für jedes Quartal eine Zeile sowie für jeden monat

        for($q=0; $q<=3; $q++) {
            $qo = new TaxOverviewRow();
            $qo->year = $year;
            $qo->month = "Q" . ($q + 1);

            for ($i = 1; $i <= 3; $i++) {
                $r = new TaxOverviewRow();
                $r->year = $year;
                $r->month = $q * 3 + $i;
                foreach ($journalManager->getJournal($year, $r->month) as $entry) {
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

                $qo->netIncome += $r->netIncome;
                $qo->netExpenses += $r->netExpenses;
                $qo->vatIn += $r->vatIn;
                $qo->vatPaid += $r->vatPaid;
                $qo->sumNet += $r->sumNet;
                $qo->sumVat += $r->sumVat;


                $ret[] = $r;
            }
            $qret[] = $qo;
        }

        $ret = array_merge($ret, $qret);
        $ret[] = $total;
        return $ret;

    }
}
