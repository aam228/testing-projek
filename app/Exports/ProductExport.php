<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProductExport implements FromCollection , withHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function headings(): array
    {
        return ["NAMA_PRODUK", "KATEGORI", "SUB_KATEGORI", "SUB_SUB_KATEGORI", "BRAND", "SUPPLIER", "UNIT", "GENDER", "SEASON", "AGING", "WARNA", "WARNA_ARTIKEL", "HARGA_BANDEROL", "HARGA_BELI", "HARGA_JUAL", "SIZE", "BARCODE"];
    }
    
    public function collection()
    {
        return collect(Product::getExport());
    }
}
