<?php

namespace Brix\Tax\Tables\AccountsSuppliers;

use Brix\Tax\Helper\CSVEntityTable;

/**
 * @AccountsSuppliersTable<AccountSuppliersEntity>
 */
class AccountsSuppliersTable extends CSVEntityTable
{

    
    public function __construct(string $filePath)
    {
        parent::__construct(AccountsSuppliersEntity::class, $filePath);
        $this->addKeyDefinition(["vatId"]);
        $this->addKeyDefinition(["supplierId"]);
    }

    
    private function normalizeVatNumber(string $vatNumber): string
    {
        return trim(strtoupper(preg_replace("/[^a-zA-Z0-9]/", "", $vatNumber)));
    }
    
    
    public function addObject($object): void
    {
        $object->vatId = $this->normalizeVatNumber($object->vatId);
        parent::addObject($object); // TODO: Change the autogenerated stub
    }

    public function getSupplierByVatNr(string $vatId): ?AccountsSuppliersEntity
    {
        $vatId = $this->normalizeVatNumber($vatId);
        return $this->select(["vatId" => $vatId]);
    }
    public function nextId() {
        $maxId = 0;
        foreach ($this->getData() as $row) {
            if (preg_match("/S(\d+)$/", $row->supplierId, $matches)) {
                $id = intval($matches[1]);
                if ($id > $maxId) {
                    $maxId = $id;
                }
            }
        }
        return $maxId + 1;
    }

}