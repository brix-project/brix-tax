<?php

namespace Brix\Tax\Analytics;

use Brix\Tax\Manager\JournalManager;

class UstWertblatt
{

    
    public function process(JournalManager $journalManager, string $direction) : array
    {
        $ret = [];
        foreach ($journalManager->getJournal() as $entry) {
            if ($entry->direction !== $direction) {
                continue;
            }
            $r = new UStWertblattRow();
            $r->date = $entry->date;
            $r->direction = $entry->direction;
            $r->invoiceNumber = $entry->invoiceNumber;
            if ($direction === "outbound") {
                $r->customerName = $entry->recipientName;
                $r->netAmount = $entry->net_amount_credit;
                $r->vatRate = $entry->vat_rate;
                $r->vatAmount = $entry->vat_credit;
                $r->totalAmount = $entry->net_amount_credit + $entry->vat_credit;
            }      
            else {
                $r->customerName = $entry->senderName;
                   $r->netAmount = $entry->net_amount_debig;
                $r->vatRate = $entry->vat_rate;
                $r->vatAmount = $entry->vat_debit;
                $r->totalAmount = $entry->net_amount_debit + $entry->vat_debit;
            }
                
            
            $ret[] = $r;
        }
        return $ret;
    }
}