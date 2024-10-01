<?php

namespace Brix\Tax\Tables\AccountsSuppliers;

class AccountsSuppliersEntity
{

    
    public function __construct(
        /**
         * The id of the supplier in the accounting system
         *
         * Format:
         *
         * name-of-supplier-<id>
         *
         * @var string|null
         */
        public ?string $supplierId,

        public string  $name,
        public string  $vatId,
    
        public string  $lastSeen
    )
    {
        // Clean VAT Number (replay non alpha numeric characters)
        
    }
    
    
    public function getNewSupplierId(AccountsSuppliersTable $table, string $supplierName): string
    {
        $supplierName = strtolower($supplierName);
        $supplierName = preg_replace("/[^a-z0-9]/", "-", $supplierName);
        $supplierName = preg_replace("/-+/", "-", $supplierName);
        $supplierName = trim($supplierName, "-");
        
       
        return $supplierName . "-S" . $table->nextId();
    }
    

    
}