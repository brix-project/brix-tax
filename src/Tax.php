<?php

namespace Brix\Tax;

use Brix\Core\AbstractBrixCommand;
use Brix\Tax\Analytics\AccountOverview;
use Brix\Tax\Analytics\TaxOverview;
use Brix\Tax\Manager\JournalManager;
use Brix\Tax\Manager\ScanManager;
use Brix\Tax\Tables\AccountsSuppliers\AccountsSuppliersEntity;
use Brix\Tax\Tables\AccountsSuppliers\AccountsSuppliersTable;
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
        $this->scanDir = $this->brixEnv->rootDir->withRelativePath($this->config->scan_dir)->assertDirectory(true);
        $this->accountsSuppliersTable = new AccountsSuppliersTable($this->brixEnv->rootDir->withFileName("accounts-suppliers.csv")->touch());
    }

    
    private function indexDocument(string $yamlFile) {
        $origFile = preg_replace("/\.tax\.yml$/", "", $yamlFile);
        $origFileExt = pathinfo($origFile, PATHINFO_EXTENSION);
        
        $data = phore_file($yamlFile)->get_yaml(T_TaxMeta::class);
        
        // Query for VAT ID
        $supplier = $this->accountsSuppliersTable->getSupplierByVatNr($data->senderVatNumber);
        if ($supplier === null) {
            $supplier = new AccountsSuppliersEntity(supplierId: null, name: $data->senderName, vatId: $data->senderVatNumber, lastSeen: date("Y-m-d", strtotime($data->invoiceDate)));
            $supplier->supplierId = $supplier->getNewSupplierId($this->accountsSuppliersTable, $data->senderName);
            $this->accountsSuppliersTable->addObject($supplier);
            $this->accountsSuppliersTable->sort("supplierId");
            $this->accountsSuppliersTable->save();
            Out::TextSuccess("Added new supplier: " . $supplier->supplierId);
        }
        $invYear = date("Y", strtotime($data->invoiceDate));
        $invDate = date("Y-m-d", strtotime($data->invoiceDate));
        $invoiceNumberSlug = preg_replace("/[^a-zA-Z0-9]/", "", $data->invoiceNumber);
        $targetOrigFile = "{$this->brixEnv->rootDir}/{$this->config->scan_dir}/{$invYear}/{$supplier->supplierId}/{$invDate}__{$invoiceNumberSlug}.{$origFileExt}";
        $targetMetaFile = $targetOrigFile . ".tax.yml";
        
        Out::TextInfo("Moving: $origFile -> $targetOrigFile");
        phore_file($targetOrigFile)->getDirname()->assertDirectory(true);
        phore_file($origFile)->rename($targetOrigFile);
        phore_file($yamlFile)->rename($targetMetaFile);
        
    }
    
    public function scan() {
        $scanManager = new ScanManager(new DocfusionClient(KeyStore::Get()->getAccessKey("docfusion_subscription"), KeyStore::Get()->getAccessKey("docfusion")));
        foreach ($this->scanDir->genWalk("*.*", true) as $file) {
            if ( ! preg_match("/\.(pdf|docx|doc|jpg|png|jpeg)$/", $file)) {
                continue;
            }
            echo "\nScanning: $file";
            $metaFile = phore_file($file . ".tax.yml");
            if ($metaFile->exists()) {
                $this->indexDocument($metaFile);
                echo " - already scanned";
                continue;
            }
            
            $data = $scanManager->scan($file);
            if ($data === null) {
                echo " - already scanned";
                continue;
            }
            $this->indexDocument($metaFile);
            echo " - scanned";
        }
        $this->accountsSuppliersTable->sort("supplierId");
        $this->accountsSuppliersTable->save();

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
