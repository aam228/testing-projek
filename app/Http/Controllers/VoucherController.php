<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Hash;

class VoucherController extends Controller
{
	protected $table = 'apps_vouchers';
	protected $url_server = 'https://topsystem.id/';
	
	private function checkData($table, $select, $where)
    {
        $affected = DB::table($table)
            ->select($select)
            ->where($where)
            ->get()->first();
        return $affected;
    }
	
	// Cek View data
	public function CekVoucher(Request $request)
    {
		$client_id = $request->client_id;
        $api_key = $request->api_key;

		$validate = DB::table('api_credentials')
        ->where([
            'client_id' => $client_id,
            'client_api' => $api_key
        ])->exists();
        if (!$validate) {
            $data = [
                'status' => [
                    'code' => 401,
                    'description' => 'Credential id dan api key tidak valid'
                ]
            ];
            return response()->json($data);
        }

		$cust_id 	= $request->cust_id;
		
		$cust_reseller = 0;
		$arr_customer = DB::table("customers")->where("id","=",$cust_id)->first();
		if($arr_customer){
			if($arr_customer->cust_reseller==1){ $cust_reseller=1; } else { $cust_reseller=0; }
		} else {$cust_reseller=0;}
		// abaikan customer reseller 
		$cust_reseller = 0;
		
        $query_data = DB::table($this->table)
		->selectRaw('ts_apps_vouchers.id as pid, avc_code, avc_discount, avc_min_order, avc_type, avc_status, avc_cashback, avc_due_date, created_at, avc_title, avc_title, avc_banner, avc_terms, avc_category, avc_to, avc_quota')
		/*
		->leftJoin('products', 'products.id', '=', 'favorit.p_id')
		->leftJoin('product_categories', 'product_categories.id', '=', 'products.pc_id')
        ->leftJoin('brands', 'brands.id', '=', 'products.br_id')
		->where('cust_id', '=', $cust_id)
		*/
		//->where('avc_due_date', '>=', date("Y-m-d"))
		/*
		->where('avc_start', '<=', date("Y-m-d"))
		->where('avc_due_date', '>=', date("Y-m-d"))
		->where('acv_start_time', '<=', date("H:i:s"))
		->where('avc_end_time', '>=', date("H:i:s"))
		*/
		->whereRaw("CONCAT(ts_apps_vouchers.avc_start,' ',ts_apps_vouchers.acv_start_time)<='".date("Y-m-d H:i:s")."'")
		->whereRaw("CONCAT(ts_apps_vouchers.avc_due_date,' ',ts_apps_vouchers.avc_end_time)>='".date("Y-m-d H:i:s")."'")
		->where('avc_status', '=', '1')
		->where('avc_delete', '=', '0')
		/*->where(function($w) use($kategori) {
			if (!empty($kategori)) {
				$w->where('ba_kategori', '=', $kategori);
			}
		})*/
		->orderBy('avc_code')
        ->get();
        
        $product_id = $request->product_id; // opsional
        
        $results = array();
        if (!empty($query_data)) {
            foreach ($query_data as $row) {
				// cek customer kusus customer
				if($row->avc_to == "customer"){
					$arr_cust_voucher = DB::table("apps_voucher_customers")
						->select("id")
						->where("cust_id","=",$cust_id)
						->where("avc_id","=",$row->pid)
						->where("avcust_status","=","1")
						->where("avcust_delete","=","0")
						->first();
					//if($arr_cust_voucher){$show=1;} else {$show=0;}
					if($arr_cust_voucher){
						// cek transaksi menggunakan voucher...
						$arr_pos = DB::table("pos_transactions")
							->select("id")
							->where("cust_id","=",$cust_id)
							->where("voucher_apps_id","=",$row->pid)
							->first();
						if($arr_pos){$show=0;} else { $show=1; }
						$show=1;
					} else {
						$show=0;
					}
				} else
				if($row->avc_to == "produk"){
					$product_id = $request->product_id;
					if($product_id){
						$product_in = explode(",",str_replace(" ","",$product_id));
						/*
						$cek_voucher_product=DB::table("apps_voucher_products")
						->select("id")
						->where("avc_id","=",$row->pid)
						->whereIn('apps_voucher_products.p_id', $product_in)
						->where("avp_delete","=","0")
						->first();
						*/
						$cek_voucher_product=DB::table("apps_voucher_products")
						->select("apps_voucher_products.id")
						->leftJoin('product_stocks', 'product_stocks.p_id', '=', 'apps_voucher_products.p_id')
						->where("avc_id","=",$row->pid)
						->whereIn('product_stocks.id', $product_in)
						->where("avp_delete","=","0")
						->first();
						if($cek_voucher_product){$show=1;} else {$show=0;}
					} else {
						$show=1; // aktifka  nol lagi
					}
				} else
				// kusus ulang tahun
				if($row->avc_to == "ultah"){
					// cek cust ultah
					$arr_customer = DB::table("customers")->where("id","=",$cust_id)->first();
					if($arr_customer){
						// cek sudah pernah digunakan dalam tahun sekarang?
						$cek_transaksiultah = DB::table("pos_transactions")
						->select('pos_transactions.id')
						->where("cust_id","=",$cust_id)
						->where("voucher_apps_id","=",$row->pid)
						->whereRaw("LEFT(ts_pos_transactions.created_at,4)='".date("Y")."'")
						->get();
						if($arr_customer->cust_bday && count($cek_transaksiultah) == 0){
							if(substr($arr_customer->cust_bday,-5) == date("m-d")){$show=1;} else {$show=0;}
						} else {$show=0;}
					} else {$show=0;}
				}
				else { $show=1; }
                
                // cek jika ada kuota voucher
				if($row->avc_quota>0){
					$cek_transaksi_voucher = DB::table("pos_transactions")
						->select('pos_transactions.id')
						->where("cust_id","=",$cust_id)
						->where("voucher_apps_id","=",$row->pid)
						//->whereRaw("LEFT(ts_pos_transactions.created_at,4)='".date("Y")."'")
						->get();
					if(count($cek_transaksi_voucher)<$row->avc_quota){$show=1;} else {$show=0;}
				} else {$show=$show;}
                
				// check product stock
				//$arr_pst = $this->checkData('product_stocks','id', ['p_id' => $row->product_id]);
				if(!empty($row->avc_banner)){
					//$banner = 'https://topsystem.id/upload/event/'.$row->avc_banner;
					$banner = $this->url_server.'upload/event/'.$row->avc_banner;
				} else {
					$banner = '';
				}
				
				$jersey_ipb = 0;
				// cek jika ada produk ID
				if($product_id){
					$product_in = explode(",",str_replace(" ","",$product_id));
					// cek promo produk...
					$arr_promo  = DB::table("product_discounts")
					->select('product_discounts.id as pid', 'product_discounts.created_at', 'st_id', 'std_id', 'pd_name', 'pd_type', 'pd_value', 'pd_date', 'no_mom', 'pd_banner', 'pd_tnc', 'pd_use')
					->leftJoin('product_discount_details', 'product_discount_details.pd_id', '=', 'product_discounts.id')
					->whereRaw("CONCAT(ts_product_discounts.pd_start,' ',ts_product_discounts.pd_start_time)<='".date("Y-m-d H:i:s")."'")
					->whereRaw("CONCAT(ts_product_discounts.pd_date,' ',ts_product_discounts.pd_end_time)>='".date("Y-m-d H:i:s")."'")
					->where('std_id', '=', '20') // kusus apps
					->whereIn('product_discount_details.pst_id', $product_in)
					//->whereNotIn('pd_type', ['fix_special'])
					->whereNotIn('pd_type', ['fix_special','percent'])
					->first();
					// jika ada promo aktif
					if($arr_promo){ $show=0; } else { $show=$show; }

					// cek apakah produk IPB run ??? 
					$cek_ipbrun = DB::table("product_stocks")
						->whereIn('product_stocks.id', $product_in)
						->whereIn('product_stocks.p_id', ['13797','13798','13799','13800'])
						->first();
					if($cek_ipbrun){$jersey_ipb++;}
					//if(in_array($arr_product->id,['13797','13798','13799','13800'])){ $jersey_ipb++; }

				} else { $show=$show; }
				
				// 362588
				if($cust_id=="362588" && (int)$row->avc_cashback == 1){
                    $results[] = [
    					'voucher_id' => $row->pid,
    					'category' => $row->avc_category,
    					'discount_title' => $row->avc_title,
    					'discount' => $row->avc_discount,
                        'min_order' => $row->avc_min_order,
                        'discount_type' => $row->avc_type,
    					'cashback' => (int)$row->avc_cashback,
                        'status_discount' => $row->avc_status,
                        'banner' => $banner,
                        'terms' => $row->avc_terms,
    					'due_date' => $row->avc_due_date,
                    ];
				} else
				
				if($show==1 && $jersey_ipb==0 && $cust_reseller==0){
                    $results[] = [
    					'voucher_id' => $row->pid,
    					'category' => $row->avc_category,
    					'discount_title' => $row->avc_title,
    					'discount' => $row->avc_discount,
                        'min_order' => $row->avc_min_order,
                        'discount_type' => $row->avc_type,
    					'cashback' => (int)$row->avc_cashback,
                        'status_discount' => $row->avc_status,
                        'banner' => $banner,
                        'terms' => $row->avc_terms,
    					'due_date' => $row->avc_due_date,
                    ];
				}
            }

        $data = [
            'status' => [
                'code' => 200,
                'description' => 'Success'
            ],
            'results' => $results
        ];
        } else {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data not found'
				]
			];
		}
        return response()->json($data);
    }

}
