<?php

namespace Brix\Tax\Manager;

use Brix\Tax\Type\T_TaxJournal;
use Brix\Tax\Type\T_TaxMeta;

class JournalManager
{


    public function __construct(public string $ownVatNr = "")
    {
        $this->ownVatNr = trim (strtoupper(str_replace(" ", "", $ownVatNr)));
    }


    protected function isOwnVatNr(string $vatNr) : bool
    {
        $vatNr = trim (strtoupper(str_replace(" ", "", $vatNr)));
        return $vatNr === $this->ownVatNr;
    }


    /**
     * @var T_TaxJournal[]
     */
    public array $journal = [];

    public function addEntry(T_TaxMeta $meta) {

        $j = new T_TaxJournal();

        $j->date = $meta->invoiceDate;
        $j->senderName = $meta->senderName;
        $j->recipientName = $meta->recipientName;
        $j->invoiceNumber = $meta->invoiceNumber;

        if ( ! $this->isOwnVatNr($meta->senderVatNumber)) {
            // Foreign Invoice -> add to debit
            $j->net_amount_debit = $meta->invoiceNet;
            $j->vat_debit = $meta->invoiceVatTotal;
            $j->net_amount_credit = null;
            $j->vat_credit = null;
            $j->type = "inbound invoice";
        } else {
            $j->net_amount_credit = $meta->invoiceNet;
            $j->vat_credit = $meta->invoiceVatTotal;
            $j->net_amount_debit = null;
            $j->vat_debit = null;
            $j->type = "outbound invoice";
        }


        $j->vat_rate = $meta->invoiceVatRate;
        $j->counterpartyVatNumber = $meta->senderVatNumber;
        $j->description = $meta->description;
        $j->classification = $meta->classification;
        $j->file = $meta->file;

        $this->journal[] = $j;
    }


    protected function sort() {
        usort($this->journal, function(T_TaxJournal $a, T_TaxJournal $b) {
            return strcmp($a->date, $b->date);
        });
    }

    /**
     * @param $year
     * @param $month
     * @return T_TaxJournal[]
     */
    public function getJournal(int $year=null, int $month=null) : array {
        $this->sort();
        $ret = [];
        foreach ($this->journal as $entry) {
            if ($year !== null && (int)substr($entry->date, 0, 4) !== $year)
                continue;
            if ($month !== null && (int)substr($entry->date, 5, 2) !== $month)
                continue;
            $ret[] = $entry;
        }
        return $ret;
    }

    public function updateJournal($file, $year=null) {
        $data = $this->journal;
        // Skip all entries that are not in the given year
        if ($year !== null) {
            $data = array_filter($data, function(T_TaxJournal $a) use ($year) {
                return substr($a->date, 0, 4) === $year;
            });
        }
        $file = phore_file($file);
        $file->set_csv(phore_dehydrate($data));
    }




}
