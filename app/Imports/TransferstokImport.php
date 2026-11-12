<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;

//use Illuminate\Database\Eloquent\Factories\HasFactory;
//use Illuminate\Database\Eloquent\Model;
use App\Models\TransferStokviews;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransferstokImport implements ToCollection, WithStartRow
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
        
        // save data ke table transfer stok
		$stf_code = 'TF'.date('YmdHis').str_pad(rand(0, pow(10, 3)-1), 3, '0', STR_PAD_LEFT);
		$stf_id = DB::table('stock_transfers')->insertGetId([
			'st_id_start' => "2",
			'st_id_end' => "2",
			'u_id' => Auth::user()->id,
			'stf_code' => $stf_code,
			'stf_status' => "3",
			'created_at' => date('Y-m-d H:i:s'),
			'updated_at' => date('Y-m-d H:i:s'),
		]);
        
        //$stf_id="123";
        foreach ($row as $r) {
            if ($r[0] == null) {
                return null;
            }
            // CEK DATA PRODUCT LOCATION SETUPS
			/*$row_pls = DB::table('product_location_setups')
	            ->select('product_location_setups.pst_id as pst_id', 'product_location_setups.pl_id as pl_id')
                ->where('product_location_setups.id', '=', $r[0]) // cek sesuai ID nya...
                ->first();*/
            $row_pls = $this->get_rowdata($r[0]);
			// jika ada data ditemukan
			//if($row_pls->pst_id!=''){
			if($row_pls['pst_id']!=''){
			//if (!empty($row_pls)) {
				// save to detail excel save...
				$detail_id = DB::table('log_transfer_excel_details')->insertGetId([
					'pls_id' => $r[0],
					'stf_id' => $stf_id,
					'pst_id' => $row_pls['pst_id'],
					'pl_id' => $row_pls['pl_id'],
					'stfd_qty' => $r[8],
					'stock_default' => $row_pls['pls_qty'],
					'stfd_status' => "0",
					'rak_lokasi' => $r[1],
					'artikel' => $r[2],
					'brand' => $r[3],
					'warna' => $r[4],
					'size' => $r[5],
					'barcode' => $r[6],
					'qty_stok' => $r[7],
					'qty_transfer' => $r[8],
					'created_at' => date('Y-m-d H:i:s'),
					'updated_at' => date('Y-m-d H:i:s'),
					'u_id' => Auth::user()->id,
				]);
				// save ke table transfer stok details...
				if($row_pls['pls_qty']>=$r[8]){
					$stok_mutasi=$r[8];
				} else {
					$stok_mutasi=$row_pls['pls_qty'];
				}
				if($row_pls['pls_qty']>0){
					$trasdet_id = DB::table('stock_transfer_details')->insertGetId([
						'stf_id' => $stf_id,
						'pst_id' => $row_pls['pst_id'],
						'pl_id' => $row_pls['pl_id'],
						//'stfd_qty' => $r[8],
						'stfd_qty' => $stok_mutasi,
						'stfd_status' => "0",
						'created_at' => date('Y-m-d H:i:s'),
						'updated_at' => date('Y-m-d H:i:s'),
					]);
					// update minus stok pada data stok awal store...
					//$this->minus_stock($r[0],$r[8]);
					$this->minus_stock($r[0],$stok_mutasi);
				}
				/*$detail_id = DB::table('log_transfer_excel_copy')->insertGetId([
                    'lte_name' => $r[0],
                    'lte_qty' => $r[1],
                    'lte_code' => $r[2],
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                */
				
				
			}
			$status=1;
            $data_id[] = $detail_id;
        }
        if ($status >= 0) {
            return '200'; 
            //return $stf_id;
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
    
    private function minus_stock($id,$qty)
	{
		$update_stok = DB::update(DB::raw('UPDATE ts_product_location_setups SET pls_qty=pls_qty-'.$qty.' WHERE id ="'.$id.'"'));
	}
    
    public function get_rowdata($id)
	{
		$array = array();
	    /*$data_row = DB::table('product_location_setups')
	            ->select('product_location_setups.pst_id as pst_id', 'product_location_setups.pl_id as pl_id')
                ->where('product_location_setups.id', '=', $id)
                ->first();
                //->get();*/
        $data_row = TransferStokviews::where('id', '=', $id);
        if (!empty($data_row->first()->id)) {
            $array['pst_id'] = $data_row->first()->pst_id;
    		$array['pl_id'] = $data_row->first()->pl_id;
    		$array['pls_qty'] = $data_row->first()->pls_qty;
        } else {
            $array['pst_id'] = '';
    		$array['pl_id'] = '';
    		$array['pls_qty'] = '';
        }
        //$array['pst_id'] = '';
    	//$array['pl_id'] = '';
    	//$array['pls_qty'] = '';
        //$array['pst_id'] = $data_row->pst_id;
    	//$array['pl_id'] = $data_row->pl_id;
    	//$array['pls_qty'] = $data_row->pls_qty;
		return $array;
	}
}
