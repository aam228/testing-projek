<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;

//use App\Models\TransferStokviews;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransferlokasiImport implements ToCollection, WithStartRow
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
				// save to detail excel save...
				$detail_id = DB::table('product_locations')->insertGetId([
					'st_id' => $r[0],
					'pl_code' => $r[1],
					'pl_name' => $r[2],
					'pl_description' => $r[3],
					'pl_delete' => "0",
					'pl_default' => "0",
					'pl_adjustment' => "0",
					'created_at' => date('Y-m-d H:i:s'),
					'updated_at' => date('Y-m-d H:i:s'),
					//'u_id' => Auth::user()->id,
				]);
				
			$status=1;
            //$data_id[] = $detail_id;
        }
        if ($status >= 0) {
            return '200'; 
        } else {
            $this->rows = -1;
            return '400';
        }
    }
	
	public function getRowCount(): int
    {
        return $this->rows;
    }
    
}
