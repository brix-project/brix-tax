<?php

namespace Brix\Tax\Manager;
use Brix\Tax\Type\T_TaxConfig;
use Brix\Tax\Type\T_TaxMeta;
use Lack\OpenAi\LackOpenAiFacet;
use Micx\SDK\Docfusion\DocfusionClient;
use Micx\SDK\Docfusion\DocfusionFacade;
use Phore\FileSystem\PhoreDirectory;
use Phore\FileSystem\PhoreFile;
use Smalot\PdfParser\Parser;

class ScanManager
{

    public function __construct(public DocfusionClient $docfusionClient)
    {
    }


    public function scan(string|PhoreFile $file) : ?T_TaxMeta
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
