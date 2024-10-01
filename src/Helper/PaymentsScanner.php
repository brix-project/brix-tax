<?php

namespace Brix\Tax\Helper;

interface PaymentsScanner
{

    /**
     * Return the new Entity Objects
     *
     * @param $path
     * @return PaymentEntity[]
     */
    function scanPayments() : array;

}
