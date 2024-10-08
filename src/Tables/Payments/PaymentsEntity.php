<?php

namespace Brix\Tax\Tables\Payments;

class PaymentsEntity
{

    public function __construct(

        /**
         * P-XYZAB-2024
         * @var string|null
         */
        public ?string $paymentId = null,

        /**
         * The Account
         *
         * @var string
         */
        public string $accountId = '',

        /**
         * DD-MM-YYYY
         *
         * @var string
         */
        public string $date,



        public float $amount,

        public string $currency,


        public float $amountOrig,

        public string $currencyOrig,

        public string $partnerName,

        public string $partnerAccount,

        public string $references,

    )
    {

    }

    public string $invoiceFile = "";

    public float|null $invoiceDiff = null;

    public string $comment = '';

}
