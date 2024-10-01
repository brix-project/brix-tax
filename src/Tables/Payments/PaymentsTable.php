<?php

namespace Brix\Tax\Tables\Payments;

use Brix\Tax\Helper\CSVEntityTable;

class PaymentsTable extends CSVEntityTable
{

    public function getNextId(string $date)
    {
        $rand = strtoupper(phore_random_str(5));
        $year = date("Y", strtotime($date));
        $key =  "P-$rand-$year";
        if ($this->select(["paymentId" => $key]) !== null) {
            return $this->getNextId($date);
        }
        return $key;

    }

    public function addPayment(PaymentsEntity $payment) {
        $orig = $this->select(["date" => $payment->date, "amount" => $payment->amount, "currency" => $payment->currency, "references"=>$payment->references]);
        if ($orig !== null) {
            return;
        }
        $payment->paymentId = $this->getNextId($payment->date);
        $this->addEntity($payment);
        $this->save();
    }

}
