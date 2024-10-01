<?php

namespace Brix\Tax\Tables\Payments;

use Brix\Core\Type\BrixEnv;
use Brix\Tax\Helper\PaymentsScanner;
use Brix\Tax\Type\T_TaxConfig;
use Phore\Cli\Output\Out;
use Phore\FileSystem\PhoreDirectory;

class PaymentsScanManager
{

    private PhoreDirectory $paymentsDir;

    private PaymentsTable $paymentsTable;

    public function __construct(private BrixEnv $env)
    {
        $config = $this->env->brixConfig->get("tax", T_TaxConfig::class);
        $this->paymentsDir = $this->env->rootDir->join($config->payments_dir)->assertDirectory(true);
        $this->paymentsTable = new PaymentsTable(PaymentsEntity::class, $this->env->rootDir->withFileName("payments.csv")->touch());
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
