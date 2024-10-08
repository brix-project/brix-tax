<?php

namespace Brix\Tax\Type;

class T_TaxMetaPayment
{
    
    public function __construct( 
        public string $paymentId,

        /**
         * @var string|null
         */
        public string|null $paymentDate = null,

        /**
         * @var string|null
         */
        public string|null $description = null,

        /**
         * @var float|null
         */
        public float|null $paymentAmount = null,
    )
    {
    }

   
    
}