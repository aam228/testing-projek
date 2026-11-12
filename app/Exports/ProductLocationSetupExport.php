<?php

namespace App\Exports;

use App\Models\ProductLocationSetup;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProductLocationSetupExport implements FromCollection , withHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */
    protected $store;

    function __construct($store) 
    {
        $this->store = $store;
    }

    public function headings(): array
    {
        return ["RAK LOKASI", "ARTIKEL", "BRAND", "WARNA", "SIZE", "BARCODE", "QTY"];
    }
    
    public function collection()
    {
        return collect(ProductLocationSetup::getExport($this->store));
    }
}
