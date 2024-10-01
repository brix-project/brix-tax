<?php

namespace Brix\Tax\Tables\Payments;

use Brix\Core\Type\BrixEnv;
use Brix\Tax\Helper\PaymentsScanner;
use Phore\Cli\Output\Out;
use Phore\FileSystem\PhoreDirectory;

class PaymentsScanManager
{

    private PhoreDirectory $paymentsDir;

    private PaymentsTable $paymentsTable;

    public function __construct(private BrixEnv $env)
    {
        $this->paymentsDir = $this->env->rootDir->join($this->env->brixConfig->get("payments_dir", "./payments"))->assertDirectory(true);
        $this->paymentsTable = new PaymentsTable($this->env->rootDir->withFileName("payments.csv")->touch());
    }


    public function scanPayments() {

        foreach ($this->paymentsDir->genWalk("__SCANNER__.php", true) as $scannerFile) {
            Out::TextInfo("Found scanner: $scannerFile");
            $scanner = require $scannerFile;
            if ( ! $scanner instanceof PaymentsScanner)
                throw new \InvalidArgumentException("Scanner must implement PaymentsScanner in file '$scannerFile'");
            foreach ($scanner->scanPayments() as $payment) {

                $this->paymentsTable->addPayment($payment);
            }
        }

    }





}
