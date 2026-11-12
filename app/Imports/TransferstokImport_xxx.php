<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Illuminate\Support\Facades\DB;

class TransferstokImport_xxx implements ToCollection, WithStartRow
{
    private $rows = 0;
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */

    public function startRow(): int
    {
        return 2;
    }

    public function collection(Collection $row)
    {
        ++$this->rows;
        $data_id = array();
        $status = 0;
        foreach ($row as $r) {
            if ($r[0] == null) {
                return null;
            }
            //$pc_id = null;

            $p_id = DB::table('log_transfer_excel')->insertGetId([
                'lte_name' => $r[0],
                'lte_qty' => $r[1],
                'lte_code' => $r[2],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
			$status=1;
            $data_id[] = $p_id;
        }
        if ($status >= 0) {
            return '200'; 
        } else {
            //DB::table('product_stocks')->whereIn('p_id', $data_id)->delete();
            //DB::table('products')->whereIn('id', $data_id)->delete();
            $this->rows = -1;
            return '400';
        }
    }

    public function getRowCount(): int
    {
        return $this->rows;
    }
}
