<?php

namespace Brix\Tax\Analytics;

use Brix\Tax\Manager\JournalManager;

class AccountOverview implements AnalyticsInterface
{


    public function process(JournalManager $journalManager, string $year): array
    {
        // Create a list of all accounts
        $accounts = [];

        foreach ($journalManager->getJournal($year) as $entry) {
            $vatId = $entry->counterpartyVatNumber;
            $name = $entry->senderName;
            if ( ! $vatId) {
                $vatId = "NO VAT ID";
                $name = "NO VAT ID";
            }
            if ( ! isset($accounts[$vatId])) {

                $accounts[$vatId] = [
                    "name" => $name,
                    "vatNumber" => $vatId,
                    "netIncome" => 0,
                    "netExpenses" => 0,
                    "vatIn" => 0,
                    "vatPaid" => 0,
                    "sumNet" => 0,
                    "sumVat" => 0,
                ];
            }

            $curAccount = &$accounts[$vatId];

            $curAccount["netIncome"] += $entry->net_amount_credit;
            $curAccount["netExpenses"] += $entry->net_amount_debit;
            $curAccount["vatIn"] += $entry->vat_credit;
            $curAccount["vatPaid"] += $entry->vat_debit;
            $curAccount["sumNet"] += $entry->net_amount_credit - $entry->net_amount_debit;
            $curAccount["sumVat"] += $entry->vat_credit - $entry->vat_debit;



        }
        return array_values($accounts);
    }

}
