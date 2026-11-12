<?php

namespace App\Exports;

use App\Models\DebtList;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class DebtExport implements FromCollection , withHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function headings(): array
    {
        return ["STORE", "SUPPLIER", "BRAND", "INVOICE", "TANGGAL", "JATUH TEMPO", "DPP", "VAT", "TOTAL", "PAYMENT VALUE"];
    }
    
    public function collection()
    {
        return collect(DebtList::getExport());
    }
}
