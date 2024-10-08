<?php

namespace Brix\Tax\Type;

class T_TaxJournal
{

    public string $date;

    public string $direction;

    public string $senderName;

    public string $recipientName;

    public string $invoiceNumber;

    public ?float $net_amount_debit;
    public ?float $net_amount_credit;

    public ?float $vat_debit;
    public ?float $vat_credit;

    public int $vat_rate;

    public string $counterpartyVatNumber;

    public string $description;

    public string $classification;


    public string $file;
}
