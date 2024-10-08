<?php

namespace Brix\Tax\Manager;

use Brix\Tax\Type\T_TaxMeta;
use Phore\FileSystem\PhoreDirectory;

class InboundPaymentsManager
{

    public function __construct(private PhoreDirectory $documentDir)
    {
        // This is a constructor
    }
    
    
    
    public function getList(string $direction = "outbound", string $year= null) : array
    {
        $full = [];
        foreach ($this->documentDir->genWalk("*.tax.yml", true) as $docs) {
            $data = $docs->get_yaml(T_TaxMeta::class);
            /* @var $data T_TaxMeta */
            if ($data->direction !== $direction)
                continue;
            if ($year !== null && date("Y", strtotime($data->invoiceDate)) !== $year)
                continue;
            $ret["Invoice Id"] = $data->invoiceNumber;
            $ret["Recipient"] = $data->recipientName;
            $ret["Sender"] = $data->senderName;
            $ret["Invoice Date"] = $data->invoiceDate;
            $ret["Due Date"] = $data->invoiceDueDate;
            $ret["Invoice Amount"] = $data->invoiceTotal;
            $ret["Amount Paid"] = 0;
            $ret["Payment Date"] = "";
            foreach ($data->payments as $payment) {
                $ret["Amount Paid"] += $payment->paymentAmount;
                $ret["Payment Date"] = $payment->paymentDate;
            }
            $full[] = $ret;
        }
        usort($full, fn($a, $b) => strtotime($a["Due Date"]) <=> strtotime($b["Due Date"]));
        
        
        return $full;
    }
    
}