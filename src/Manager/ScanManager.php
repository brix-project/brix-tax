<?php

namespace Brix\Tax\Manager;
use Brix\Tax\Type\T_TaxConfig;
use Brix\Tax\Type\T_TaxMeta;
use Lack\OpenAi\LackOpenAiFacet;
use Phore\FileSystem\PhoreDirectory;
use Phore\FileSystem\PhoreFile;
use Smalot\PdfParser\Parser;

class ScanManager
{

    public function __construct(public LackOpenAiFacet $openAi)
    {
    }


    public function scan(string|PhoreFile $file) : ?T_TaxMeta
    {
        $file = phore_file($file);

        $metaFile = phore_file($file . ".tax.yml");
        if ($metaFile->exists()) {
            return null;
        }

        /**
        $parser = new Parser();
        $invoiceText = $parser->parseFile($file)->getText();
*/
        $invoiceText = phore_exec("pdftotext :file -", ["file" => $file]);


        $aiClient = $this->openAi;
        $taxConfig = $aiClient->promptDataStruct($invoiceText, T_TaxMeta::class);

        $taxConfig->file = $file->getUri();

        $metaFile->set_yaml((array)$taxConfig);
        return $taxConfig;
    }


}
