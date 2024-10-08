<?php

namespace Brix\Tax\Manager;

class ExportManager
{

    public function __construct(
        private BrixEnv $brixEnv,
        private AccountsSuppliersTable $accountsSuppliersTable
    )
    {
    }
    
    
    
    public function export() {
        $this->accountsSuppliersTable->export();
    }
    
}