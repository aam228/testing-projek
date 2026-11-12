<?php

namespace App\Imports;

use App\Models\Brand;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class BrandImport implements ToModel, WithHeadingRow
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
        return new Brand([
            'br_name' => $row['nama'],
            'br_description' => $row['deskripsi'],
            'br_delete' => '0',
        ]);
    }
}
