<?php

namespace Brix\Tax\Analytics;

class TaxOverviewRow
{

    public $year;

    public $month;

    public float $netIncome = 0;

    public float $netExpenses = 0;

    public float $vatIn = 0;

    public float $vatPaid = 0;

    public float $sumNet = 0;

    public float $sumVat = 0;


}
