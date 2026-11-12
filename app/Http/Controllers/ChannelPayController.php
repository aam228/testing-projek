<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Hash;

class ChannelPayController extends Controller
{
	//protected $table = 'apps_points';
	
	private function checkData($table, $select, $where)
    {
        $affected = DB::table($table)
            ->select($select)
            ->where($where)
            ->get()->first();
        return $affected;
    }
	
	public function index(Request $request)
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
        
        $versi_app = '';
		$cust_id = $request->cust_id; // cek customer data...
		if($cust_id){
			$arr_customer = DB::table("customers")->select('cust_app_versions')->where("id","=",$cust_id)->first();
			if($arr_customer && !empty($arr_customer->cust_app_versions)){
				$versi_app = str_replace(['.'],[''],$arr_customer->cust_app_versions);
			}
		}
		
		// items, address_id, amount
		$items = $request->items;
		$address_id = $request->address_id;
		$amount = $request->amount;
		
		$cod_item=0; $cod_address=0; $cod_amount=0;
		
		// cek settingan COD
		$arr_cod = DB::table("cod_settings")->where("cod_delete","=","0")->first();
		// cek product ***
		$array_lp = array();
		$syarat_produk = 0;
		if($arr_cod){
			$array_lp = DB::table("cod_products")
				->where('cod_id', '=', $arr_cod->id)
				->where('codp_delete', '=', "0")
				->pluck('p_id')
				->toArray();
			
			if(count($array_lp) > 0){
				$syarat_produk = 1;
			}
		}
		
		// cek item details (BATCH QUERY - cegah table scan berulang)
		$item_details = json_decode($items);
		$data_item=0; $syarat_item=0;
		$event=0;
		if($item_details && is_array($item_details)){
			$names = [];
			foreach($item_details as $lookup){
				if(!empty($lookup->product_name)){
					$names[] = $lookup->product_name;
				}
			}
			$products_map = [];
			if(!empty($names)){
				$prods = DB::table("products")
					->select('id', 'p_name', 'p_color')
					->whereIn('p_name', array_unique($names))
					->where('p_delete', '0')
					->get();
				foreach($prods as $p){
					$products_map[$p->p_name . '_' . $p->p_color] = $p->id;
				}
			}

    		foreach ($item_details as $key => $lookup) {
    			$product_name = $lookup->product_name ?? '';
    			$color = $lookup->color ?? '';
				$map_key = $product_name . '_' . $color;
				
				if(isset($products_map[$map_key])){
					$data_item++;
					$p_id = $products_map[$map_key];
    				if($syarat_produk==1){
    					if(in_array($p_id, $array_lp)){
    						$syarat_item++;
    					}
    				} else { 
						$syarat_item++; 
					} 
    			}
    		}
		} else { 
			$event=1; 
		}
		if($data_item > 0 && $syarat_item == $data_item){
			$cod_item = 1;
		} // syarat COD
		
		// cek wilayah (1 query JOIN)
		if($address_id && $arr_cod){
    		$arr_adress = DB::table("customer_address")
				->leftJoin("zip_codes", "zip_codes.id", "=", "customer_address.zip_id")
				->select("zip_codes.zc_province")
				->where("customer_address.id","=",$address_id)
				->first();
				
    		// syarat COD
    		if($arr_cod->cod_city && $arr_adress){
				$prov = strtolower($arr_adress->zc_province ?? '');
    			if(in_array($prov, explode(",", strtolower($arr_cod->cod_city)))){
    				$cod_address=1;
    			} else {
    				$cod_address=0;
    			}
    		} else { 
				$cod_address=1; 
			}
		}
    	// cek amount
    	if($arr_cod && $amount<=$arr_cod->cod_maks && $amount>=$arr_cod->cod_min){
			$cod_amount=1;
		} else {
			$cod_amount=0;
		} // syarat COD
        
		$cat_pg = DB::table('pg_category_payget')->select('*')
                        ->where('status', '=', '1')
                        ->where(function($w) use($versi_app, $request, $arr_cod, $cod_item, $cod_address, $cod_amount, $event) {
							if (empty($versi_app) || $versi_app < "207") {
								$w->where('category_name', '!=', 'COD');
							}
							
							// COD tidak aktif
							if(empty($arr_cod) || $arr_cod->cod_status==0){
								$w->where('category_name', '!=', 'COD');
							} else {							
								// cek jika ada tambahan data posting (items, address_id, amount)
								if($request->items && $request->address_id && $request->amount>0){
									if($arr_cod->cod_status==1){
										if($cod_item==0 || $cod_address==0 || $cod_amount==0){
											$w->where('category_name', '!=', 'COD');
										}
									}
								}
							}
							
							if($event==1){
								$w->where('code_category_payget', '=', 'qrcode'); // kusus event hanya QRIS
							}
						})
						->orderBy('sort')->get();
		$cat_ids = $cat_pg->pluck('id')->toArray();
		$all_methods = DB::table('pg_metod_payget')->select('*')
						->where('status', '=', '1')
						->whereIn('id_category_payget', $cat_ids)
						->orderBy('sort')->get()
						->groupBy('id_category_payget');

		$base_url = url('public/pg') . '/';
		$cat_arrays = array();
		foreach($cat_pg as $rpg){
				    $cat_detail = isset($all_methods[$rpg->id]) ? $all_methods[$rpg->id] : [];
					$pg_option_select = [];
					foreach($cat_detail as $rsub){
						$pg_option_select []= [
							'id' => $rsub->id,
							'bank_code' => $rsub->bank_code,
							'payment_name' => $rsub->payment_name,
							'payment_description' => $rsub->payment_description,
							'img' => $base_url . str_replace("svg","png",$rsub->img),
							'fee' => $rsub->fee,
							'fee_percent' => $rsub->fee_percent,
							'ppn' => $rsub->ppn,
							'limit_min' => $rsub->limit_min,
							'limit_max' => $rsub->limit_max,
						];
					}
					$cat_arrays[]= [
						'pg_name' => $rpg->category_name,
						'pg_code' => $rpg->code_category_payget,
						'pg_options' => $pg_option_select
					];
		}

        $data = [
            'status' => 200,
            'channel' => $cat_arrays
        ];
        return response()->json($data);
    }
}
