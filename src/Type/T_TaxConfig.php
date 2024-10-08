<?php

namespace Brix\Tax\Type;

class T_TaxConfig
{

    /**
     * @var string
     */
    public string $documents_dir = "./documents";

    public string $payments_dir = "./payments";
    
    public string $export_dir = "./export";

    /**
     * @var string
     */
    public string $my_vat_id = "DE123456789";

}
