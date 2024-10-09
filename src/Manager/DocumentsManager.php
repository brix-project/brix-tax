<?php

namespace Brix\Tax\Manager;
use Brix\Core\Type\BrixEnv;
use Brix\Tax\Tables\AccountsSuppliers\AccountsSuppliersEntity;
use Brix\Tax\Tables\AccountsSuppliers\AccountsSuppliersTable;
use Brix\Tax\Tables\Payments\PaymentsEntity;
use Brix\Tax\Tables\Payments\PaymentsTable;
use Brix\Tax\Type\T_TaxConfig;
use Brix\Tax\Type\T_TaxMeta;
use Brix\Tax\Type\T_TaxMetaPayment;
use Lack\Keystore\KeyStore;
use Lack\OpenAi\LackOpenAiFacet;
use Micx\SDK\Docfusion\DocfusionClient;
use Micx\SDK\Docfusion\DocfusionFacade;
use Phore\Cli\Output\Out;
use Phore\FileSystem\PhoreDirectory;
use Phore\FileSystem\PhoreFile;
use Smalot\PdfParser\Parser;

class DocumentsManager
{

    private PhoreDirectory $documentsDir;
    private DocfusionClient $docfusionClient;
    private T_TaxConfig $config;
    private PaymentsTable $paymentsTable;

    public function __construct(
        private BrixEnv $brixEnv,
        private AccountsSuppliersTable $accountsSuppliersTable
    )
    {

        $this->config = $this->brixEnv->brixConfig->get("tax", T_TaxConfig::class);
        $this->docfusionClient = new DocfusionClient(KeyStore::Get()->getAccessKey("docfusion_subscription"), KeyStore::Get()->getAccessKey("docfusion"));
        $this->documentsDir = $this->brixEnv->rootDir->withRelativePath($this->config->documents_dir)->assertDirectory(true);
        $this->paymentsTable = new PaymentsTable(PaymentsEntity::class, $this->brixEnv->rootDir->withFileName("payments.csv")->touch());
    }


    private function removeEmptyDirectories($rootDir) {
        foreach (phore_dir($rootDir)->genWalk() as $dir) {
            if ( ! $dir->isDirectory())
                continue;
            if (count($dir->asDirectory()->getListSorted()) === 0) {
                $dir->asDirectory()->rmDir();
                continue;
            }
            $this->removeEmptyDirectories($dir);
        }
    }


    private function idGen(string $input) :string {
        return strtoupper(preg_replace('/[\s\-_.\/]/', '', $input));
    }


    private function connectPayments(T_TaxMeta $meta) : T_TaxMeta{
        foreach ($this->paymentsTable->getGenerator() as $curPayment) {
            /* @var $curPayment PaymentsEntity */
            if ($curPayment->date < $meta->invoiceDate) {
                continue;
            }
            if (strlen(trim ($meta->invoiceNumber)) < 4)
                continue; // Invoice number too short

            if ($meta->direction === "inbound" && $curPayment->amount > 0)
                continue;

            if ($meta->direction === "outbound" && $curPayment->amount < 0)
                continue;



            if (strpos($this->idGen($curPayment->references), $this->idGen($meta->invoiceNumber)) === false)
                continue;

            if ($curPayment->invoiceFile !== "")
                continue; // Payment already assigned

            Out::TextSuccess("New Payment found for: $meta->file: " . $curPayment->amount);
            $meta->payments[] = new T_TaxMetaPayment($curPayment->paymentId, $curPayment->date, $curPayment->references, $curPayment->amount);
            $curPayment->invoiceFile = $meta->file;
            $curPayment->invoiceDiff = $curPayment->amount - $meta->invoiceTotal;
            $this->paymentsTable->save();

        }
        return $meta;
    }


    private function indexDocument(string $yamlFile, bool $resetConnections = false) {
        $origFile = preg_replace("/\.tax\.yml$/", "", $yamlFile);
        $origFileExt = pathinfo($origFile, PATHINFO_EXTENSION);

        $yamlFile = phore_file($yamlFile);


        if ($resetConnections) {
            $data = $yamlFile->get_yaml();
            $data["payments"] = [];
            $yamlFile->set_yaml($data);

        }
        $data = $yamlFile->get_yaml(T_TaxMeta::class);
        // Determine direction
        if ($this->config->my_vat_id === $data->senderVatNumber) {
            $data->direction = "outbound";
        } else {
            $data->direction = "inbound";
        }
        $yamlFile->set_yaml((array)$data);


        // Validate Sums
        $sum = $data->invoiceNet + $data->invoiceVatTotal;
        if (number_format($sum, 2) != number_format($data->invoiceTotal, 2)) {
            Out::TextDanger("Sum mismatch: $sum != {$data->invoiceTotal} for **$yamlFile**");
        }


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
        $relTargetOrigFile = "{$invYear}/{$supplier->supplierId}/{$invDate}__{$invoiceNumberSlug}.{$origFileExt}";
        $targetOrigFile = "{$this->brixEnv->rootDir}/{$this->config->documents_dir}/{$relTargetOrigFile}";
        $targetMetaFile = $targetOrigFile . ".tax.yml";

        //Out::TextInfo("Moving: $origFile -> $targetOrigFile");
        phore_file($targetOrigFile)->getDirname()->assertDirectory(true);
        phore_file($origFile)->rename($targetOrigFile);

        // Connect Payments
        $obj = $yamlFile->get_yaml(T_TaxMeta::class);
        $obj->file = $relTargetOrigFile;
        $obj = $this->connectPayments($obj);
        $yamlFile->set_yaml($obj);

        phore_file($yamlFile)->rename($targetMetaFile);


    }

    public function scan(bool $resetConnections = false) {

        if ($resetConnections) {
            Out::TextWarning("Resetting all connections.");
            foreach ($this->paymentsTable->getGenerator() as $curPayment) {
                /* @var $curPayment PaymentsEntity */
                $curPayment->invoiceFile = "";
                $curPayment->invoiceDiff = null;
            }
            $this->paymentsTable->save();
        }

        Out::TextInfo("Scanning documents in: " . $this->documentsDir);
        $i = 0;
        $iNew = 0;
        foreach ($this->documentsDir->genWalk("*.*", true) as $file) {
            if ( ! preg_match("/\.(pdf|docx|doc|jpg|png|jpeg)$/", $file)) {
                continue;
            }
            $metaFile = phore_file($file . ".tax.yml");
            if ($metaFile->exists()) {
                $this->indexDocument($metaFile, $resetConnections);
                $i++;
                continue;
            }

            Out::TextInfo("Scanning: $file");
            $data = $this->scanSingleFile($file);

            if ($data === null) {
                Out::TextDanger("No meta data found for: $file");
                continue;
            }
            $iNew++;
            $this->indexDocument($metaFile, $resetConnections);

        }
        Out::TextSuccess("Scanned $i documents. Found $iNew new documents.");
        $this->accountsSuppliersTable->sort("supplierId");
        $this->accountsSuppliersTable->save();
        $this->removeEmptyDirectories($this->brixEnv->rootDir->withRelativePath($this->config->documents_dir));
    }


    private function scanSingleFile(string|PhoreFile $file) : ?T_TaxMeta
    {
        $file = phore_file($file);

        $metaFile = phore_file($file . ".tax.yml");
        if ($metaFile->exists()) {
            return null;
        }


        $docfusionFacade = new DocfusionFacade($this->docfusionClient);



        $taxConfig = $docfusionFacade->promptFileWithCast($file->getUri(), cast: T_TaxMeta::class);


        $taxConfig->file = $file->getUri();

        $metaFile->set_yaml((array)$taxConfig);
        return $taxConfig;
    }


    public function getJournalManager(string $from=null, string $till = null) : JournalManager {
        $j =  new JournalManager();
        foreach ($this->documentsDir->genWalk("*.tax.yml", true) as $doc) {
            $meta = phore_file($doc)->get_yaml(T_TaxMeta::class);
            if ($from !== null && $meta->invoiceDate < $from)
                continue;
            if ($till !== null && $meta->invoiceDate > $till)
                continue;

            $j->addEntry($meta);
        }
        return $j;
    }


    public function createExport(PhoreDirectory $exportDir, $fromDate, $tillDate) {
        $journalManager = new JournalManager($this->brixEnv, $this->config->my_vat_id);
        foreach ($this->documentsDir->genWalk("*.tax.yml", true) as $doc) {
            $meta = phore_file($doc)->get_yaml(T_TaxMeta::class);
            if ($meta->invoiceDate < $fromDate || $meta->invoiceDate > $tillDate)
                continue;

            $doc->asFile()->copyTo($exportDir->withRelativePath($meta->file. ".tax.yml"));
            $this->documentsDir->withRelativePath($meta->file)->asFile()->copyTo($exportDir->withRelativePath($meta->file));
        }
    }


}
