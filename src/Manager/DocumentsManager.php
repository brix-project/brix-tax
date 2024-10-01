<?php

namespace Brix\Tax\Manager;
use Brix\Core\Type\BrixEnv;
use Brix\Tax\Tables\AccountsSuppliers\AccountsSuppliersEntity;
use Brix\Tax\Tables\AccountsSuppliers\AccountsSuppliersTable;
use Brix\Tax\Type\T_TaxConfig;
use Brix\Tax\Type\T_TaxMeta;
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

    public function __construct(
        private BrixEnv $brixEnv,
        private AccountsSuppliersTable $accountsSuppliersTable
    )
    {

        $this->config = $this->brixEnv->brixConfig->get("tax", T_TaxConfig::class);
        $this->docfusionClient = new DocfusionClient(KeyStore::Get()->getAccessKey("docfusion_subscription"), KeyStore::Get()->getAccessKey("docfusion"));
        $this->documentsDir = $this->brixEnv->rootDir->withRelativePath($this->config->documents_dir)->assertDirectory(true);
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
        $targetOrigFile = "{$this->brixEnv->rootDir}/{$this->config->documents_dir}/{$invYear}/{$supplier->supplierId}/{$invDate}__{$invoiceNumberSlug}.{$origFileExt}";
        $targetMetaFile = $targetOrigFile . ".tax.yml";

        Out::TextInfo("Moving: $origFile -> $targetOrigFile");
        phore_file($targetOrigFile)->getDirname()->assertDirectory(true);
        phore_file($origFile)->rename($targetOrigFile);
        phore_file($yamlFile)->rename($targetMetaFile);


    }

    public function scan() {
        foreach ($this->documentsDir->genWalk("*.*", true) as $file) {
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

            $data = $this->scanSingleFile($file);
            if ($data === null) {
                echo " - already scanned";
                continue;
            }
            $this->indexDocument($metaFile);
            echo " - scanned";
        }
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


}
