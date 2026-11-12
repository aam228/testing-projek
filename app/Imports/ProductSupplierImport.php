<?php

namespace App\Imports;

use App\Models\ProductSupplier;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProductSupplierImport implements ToModel, WithHeadingRow
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        if ($row['nama'] == null) {
            return null;
        }
        return new ProductSupplier([
            'ps_name' => $row['nama'],
            'ps_email' => $row['email'],
            'ps_phone' => $row['telepon'],
            'ps_address' => $row['alamat'],
            'ps_description' => $row['deskripsi'],
            'ps_delete' => '0',
        ]);
    }
}
