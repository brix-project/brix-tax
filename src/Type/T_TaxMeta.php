<?php

namespace Brix\Tax\Type;

class T_TaxMeta
{

    /**
     * "invoice" or "credit note"
     *
     * @var string
     */
    public $type;

    /**
     * The sender Name or Organization Name
     * 
     * Search for it in the footer of the document.
     *
     * @var string
     */
    public $senderName;

    /**
     * Leave empty
     * 
     * @var string|null
     */
    public $direction = null;

    /**
     * Merchant VAT Number (Umsatzsteuer-ID, USt-IDNr. USt.Id o.ä.) or Steuernummer (St.-Nr.:, Steuernummer: etc) only if VAT Number is not available
     * 
     * Example: VAT Number: DE123456789, IE12345678, 122345567
     * Example: Steuernummer: 122/1234/1334 or 1234567890 (only if no VAT Number is available)
     * 
     * If neither VAT Number nor Steuernummer is available, set to empty string
     * 
     * If there are multiple VAT Numbers or Steuernummern on the document, take the one from the footer!
     * 
     * @var string
     */
    public $senderVatNumber;

    /**
     * The recipient name or organization name
     *
     * Search for it in the top section of the document.
     * 
     * @var string
     */
    public $recipientName;

    /**
     * The Unique Customer Number (Kundennummer) of the recipient
     * @var string
     */
    public $customerNumber;

    /**
     * Unique invoice number / Rechnungs-Nr. e.g.
     * @var string
     */
    public $invoiceNumber;

    /**
     * Invoice date in format YYYY-MM-DD
     * @var string
     */
    public $invoiceDate;

    /**
     * The due date of the invoice
     * @var string
     */
    public $invoiceDueDate;

    /**
     * The Currency of the invoice (e.g. EUR or USD)
     * @var string
     */
    public $invoiceCurrency;

    /**
     * The total amount of the invoice in the currency of the invoice including VAT
     * (Brutto-Rechnungsbetrag, Brutto Betrag, Rechungsbetrag). 
     * 
     * This is not the payment amount, but the total amount of the invoice.
     *
     * @var float
     */
    public float $invoiceTotal;

    /**
     * The VAT amount of the invoice in the currency of the invoice
     *
     * @var float
     */
    public float $invoiceVatTotal;


    /**
     * The VAT rate of the invoice in percent
     * 
     * If Tax to be paid on reverse charge basis (Reverse Charge), set this to 0
     * 
     * @var int
     */
    public int $invoiceVatRate;

    /**
     * The net amount of the invoice in the currency of the invoice without VAT
     * 
     * If Tax to be paid on reverse charge basis (Reverse Charge), set this to the invoiceTotal
     *
     * @var float
     */
    public float $invoiceNet;


    /**
     * How to classify this invoice: "goods", "services", "travel", "advertising", "other"
     *
     * @var string
     */
    public string $classification;

    /**
     * A short description of the type of goods or services for the journal
     *
     * @var string
     */
    public string $description;

    /**
     * Leave empty array
     * 
     * @var T_TaxMetaPayment[] 
     */
    public array $payments = [];

    public string $file;
}
