<?php

namespace Brix\Tax;

use Brix\Core\AbstractBrixCommand;
use Brix\Tax\Analytics\AccountOverview;
use Brix\Tax\Analytics\TaxOverview;
use Brix\Tax\Manager\DocumentsManager;
use Brix\Tax\Manager\JournalManager;
use Brix\Tax\Manager\ScanManager;
use Brix\Tax\Tables\AccountsSuppliers\AccountsSuppliersEntity;
use Brix\Tax\Tables\AccountsSuppliers\AccountsSuppliersTable;
use Brix\Tax\Tables\Payments\PaymentsScanManager;
use Brix\Tax\Type\T_TaxConfig;
use Brix\Tax\Type\T_TaxMeta;
use Lack\Keystore\KeyStore;
use Micx\SDK\Docfusion\DocfusionClient;
use Phore\Cli\Output\Out;
use Phore\FileSystem\PhoreDirectory;

class Tax extends AbstractBrixCommand
{

    public T_TaxConfig $config;

    public PhoreDirectory $scanDir;

    public AccountsSuppliersTable $accountsSuppliersTable;

    public function __construct()
    {
        parent::__construct();
        $this->config = $this->brixEnv->brixConfig->get(
            "tax",
            T_TaxConfig::class,
            file_get_contents(__DIR__ . "/config_tpl.yml")
        );
        $this->scanDir = $this->brixEnv->rootDir->withRelativePath($this->config->documents_dir)->assertDirectory(true);
        $this->accountsSuppliersTable = new AccountsSuppliersTable($this->brixEnv->rootDir->withFileName("accounts-suppliers.csv")->touch());
    }


    public function scan() {
        $scanManager = new DocumentsManager($this->brixEnv, $this->accountsSuppliersTable);
        $scanManager->scan();
    }


    public function payments_scan() {
        $paymentsScanManager = new PaymentsScanManager($this->brixEnv);
        $paymentsScanManager->scanPayments();
    }


    public function update_journal(string $year = null) {
        if ($year === null) {
            $year = date("Y");
        }

        $journalManager = new JournalManager($this->config->my_vat_id);
        foreach($this->scanDir->genWalk("*.tax.yml", true) as $file) {
            $meta = phore_file($file)->get_yaml(T_TaxMeta::class);
            $journalManager->addEntry($meta);
        }

        $journalFile = phore_file($this->brixEnv->rootDir . "/journal-$year.csv");

        $journalManager->updateJournal($journalFile, $year);

    }

    public function analytics (int $year = null) {
        if ($year === null) {
            $year = date("Y");
        }
        $journalManager = new JournalManager($this->config->my_vat_id);
        foreach($this->scanDir->genWalk("*.tax.yml", true) as $file) {
            $meta = phore_file($file)->get_yaml(T_TaxMeta::class);
            $journalManager->addEntry($meta);
        }

        $analytics = new TaxOverview();

        Out::Table($analytics->process($journalManager, $year));

    }
    public function account_overview (int $year = null) {
        if ($year === null) {
            $year = date("Y");
        }
        $journalManager = new JournalManager($this->config->my_vat_id);
        foreach($this->scanDir->genWalk("*.tax.yml", true) as $file) {
            $meta = phore_file($file)->get_yaml(T_TaxMeta::class);
            $journalManager->addEntry($meta);
        }

        $analytics = new AccountOverview();

        Out::Table($analytics->process($journalManager, $year));

    }
}
