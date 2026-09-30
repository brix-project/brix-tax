<?php

namespace Brix\Tax;

use Brix\Core\AbstractBrixCommand;
use Brix\Tax\Analytics\AccountOverview;
use Brix\Tax\Analytics\BwaGenerator;
use Brix\Tax\Analytics\TaxOverview;
use Brix\Tax\Analytics\UstWertblatt;
use Brix\Tax\Manager\DocumentsManager;
use Brix\Tax\Manager\InboundPaymentsManager;
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

    public PhoreDirectory $documentsDir;

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


    public function scan(string $resetConnections = "") {
        $scanManager = new DocumentsManager($this->brixEnv, $this->accountsSuppliersTable);
        $scanManager->scan($resetConnections === "yes");
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
    public function bwa(int $year = null) {
        if ($year === null) {
            $year = (int)date("Y");
        }

        $journalManager = new JournalManager();
        foreach ($this->scanDir->genWalk("*.tax.yml", true) as $file) {
            $meta = phore_file($file)->get_yaml(T_TaxMeta::class);
            $journalManager->addEntry($meta);
        }

        $endDate = sprintf('%d-12-31', $year);
        if ($year === (int)date('Y')) {
            $endDate = date('Y-m-t', strtotime('first day of last month'));
        }

        $costTypesFile = (string)$this->brixEnv->rootDir->withFileName("accounts-kostenarten.csv");
        $generator = new BwaGenerator($this->accountsSuppliersTable, $costTypesFile);
        $rows = $generator->process($journalManager, (string)$year, $endDate);
        $amountWidth = max(array_map(fn(array $row): int => mb_strwidth($row['Nettobetrag']), $rows));
        foreach ($rows as &$row) {
            $row['Nettobetrag'] = str_repeat(' ', $amountWidth - mb_strwidth($row['Nettobetrag'])) . $row['Nettobetrag'];
        }
        unset($row);

        $formattedEndDate = date('d.m.Y', strtotime($endDate));
        echo "BWA-Export vom 01.01.$year bis $formattedEndDate\n\n";
        Out::Table($rows);
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
    
    public function export(string $from, string $to) {

        $exportDir = $this->brixEnv->rootDir->withRelativePath($this->config->export_dir)->withRelativePath("{$from}__{$to}")->assertDirectory(true);
        $scanManager = new DocumentsManager($this->brixEnv, $this->accountsSuppliersTable);
        $scanManager->createExport($exportDir, $from, $to);
        
        $journalManager = $scanManager->getJournalManager($from, $to);
        
        $journalFile = $exportDir->withFileName("journal.csv");
        $journalManager->updateJournal($journalFile);
        
        $accountOverview = new AccountOverview();
        $exportDir->withFileName("account_overview.csv")->set_csv($accountOverview->process($journalManager, date("Y", strtotime($from))));
        
        $taxOverview = new TaxOverview();
        $exportDir->withFileName("tax_overview.csv")->set_csv($taxOverview->process($journalManager, date("Y", strtotime($from))));
        
        $ustWertblatt = new UstWertblatt();
        $exportDir->withFileName("ust_wertblatt.csv")->set_csv($ustWertblatt->process($journalManager, "outbound"));
        
        $exportDir->withFileName("vorsteueraufstellung.csv")->set_csv($ustWertblatt->process($journalManager, "inbound"));
        
    }
    
    
    public function payment_list(string $direction = "outbound", string $year = null) {
        if ($year === null) {
            $year = date("Y");
        }
        $inboundPaymentsManager = new InboundPaymentsManager($this->scanDir);
        Out::Table($list = $inboundPaymentsManager->getList($direction, $year));
        $outByMonth = [];
        foreach ($list as $item) {
            $month = date("Y-m", strtotime($item["Invoice Date"]));
            if ( ! isset($outByMonth[$month])) {
                $outByMonth[$month] = [
                    "Month" => $month,
                    "Invoices" => 0,
                    "Amount" => 0,
                    "Paid" => 0,
                    "Open" => 0
                ];
            }
            $outByMonth[$month]["Invoices"]++;
            $outByMonth[$month]["Amount"] += $item["Invoice Amount"];
            $outByMonth[$month]["Paid"] += $item["Amount Paid"];
            $outByMonth[$month]["Open"] += $item["Invoice Amount"] - $item["Amount Paid"];
            
        }
        
        // Sort by Month
        ksort($outByMonth);
        
        Out::Table($outByMonth);
    }
    
}
