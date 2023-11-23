<?php

namespace Brix\Tax\Analytics;

use Brix\Tax\Manager\JournalManager;

interface AnalyticsInterface
{

    public function process(JournalManager $journalManager, string $year) : array;

}
