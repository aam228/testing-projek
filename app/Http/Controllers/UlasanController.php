<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
//use App\Models\User;
use Hash;

class UlasanController extends Controller
{
	protected $table = 'ratting_apps';
	
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
		
		$cust_id = $request->cust_id;
		$lookup_id = $request->pos_id;
		$ra_value = $request->ratting; // nilai ratting (0 sd 5)
		$ra_description = $request->description;
		$ra_type = $request->type_data;
		if($cust_id=='' || $lookup_id=='' || $ra_description==''){
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Field cust_id, pos_id, ratting dan description harus di isi'
				],
				//'results' => $results
			];
		} else {
			// proses save
			$data = [
				'cust_id' => $cust_id,
				'lookup_id' => $lookup_id,
				'ra_type' => $ra_type,
				'ra_value' => $ra_value,
				'ra_description' => $ra_description,
				'created_at' => date("Y-m-d H:i:s"),
			];
						
			$check = $this->checkData($this->table,'id', ['cust_id' => $cust_id, 'lookup_id' => $lookup_id, 'ra_type' => $ra_type]);
			if(empty($check)){
				$data_upload = array();
				if ($request->hasFile('file_upload')) {				
						$request->validate([
							'file_upload' => 'required|file|mimes:jpg,jpeg,png,gif',
						]);
						$image = $request->file('file_upload');
						$input['fileName'] = 'docfile'.rand(10000,99999).time().'.'.$image->extension();
			
						$destinationPath = public_path('/ulasan_file');
						$image->move($destinationPath, $input['fileName']);
						
						$data_upload = ['ra_file' => $input['fileName']];
				}
				// save database...
				$saves = DB::table($this->table)->insertGetId(array_merge($data, $data_upload));
				    
				    $arr_transaksi = DB::table("pos_transactions")->where("id","=",$lookup_id)->first();
					if($arr_transaksi && $ra_type == "pos"){
						$nilai_score = 1;
						
						$arr_customer = DB::table("customers")->where("id","=",$arr_transaksi->cust_id)->first();
						if($arr_customer){
							$cust_score_update = $arr_customer->cust_score + $nilai_score;
							DB::table("customers")->where('id', $arr_customer->id)->update(['cust_score'=>$cust_score_update]);
							// input score log
							$data_score = [
								'cust_id' => $cust_id,
								'cscore_type' => 'pos',
								'cscore_lookup_id' => $arr_transaksi->id,
								'cscore_description' => 'Ulasan belanja no order '.$arr_transaksi->pos_invoice,
								'cscore_value' => $nilai_score,
								'created_at' => date("Y-m-d H:i:s"),
							];
							DB::table("customer_scores")->insertGetId(array_merge($data_score));
						}
					}
				
					$data = [
						'status' => [
							'code' => 200,
							'description' => 'Success'
						],
						//'results' => $results
					];
			} else {
				$data = [
					'status' => [
						'code' => 400,
						'description' => 'Customer sudah pernah melakukan ratting untuk transaksi ini'
					],
					//'results' => $results
				];
			}
		}
        return response()->json($data);

	}
	
	// list view ulasan
	public function ViewUlasan(Request $request)
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

		$cust_id = $request->cust_id;
		$product_id = $request->product_id;
		$type_data = $request->type_data; // event dan pos
		
		// tampilkan untuk POS
		if($type_data == 'pos'){
			$arr_product = DB::table("product_stocks")
				->select("p_name","p_color","p_id","br_id")
				->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
				->where("product_stocks.id","=",$product_id)
				/*->where(function($w) use ($product_id){
					if ($product_id != '') {
					  $w->where('product_stocks.id', '=', $product_id);
					}
				})*/
				->get()->first();
			//if(!empty($arr_product)){
			if(1==1){
				$query_data = DB::table($this->table)
				->selectRaw('ts_ratting_apps.id as pid, ra_value, ra_description, ra_file, ts_ratting_apps.created_at, cust_name, cust_email, external_id, pos_invoice, ts_products.p_name, ts_products.p_color, ts_product_stocks.id as product_id, p_main_image, p_image, cust_avatar, cust_gender, cust_reseller, ts_customers.id as cust_id, cust_phone')
				->leftJoin('pos_transactions', 'pos_transactions.id', '=', 'ratting_apps.lookup_id')
				->leftJoin('pos_transaction_details', 'pos_transaction_details.pt_id', '=', 'pos_transactions.id')
				->leftJoin('product_stocks', 'product_stocks.id', '=', 'pos_transaction_details.pst_id')
				->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
				->leftJoin('customers', 'customers.id', '=', 'ratting_apps.cust_id')
				->where('ra_type', '=', 'pos')
				//->where('products.p_name', '=', $arr_product->p_name)
				//->where('products.br_id', '=', $arr_product->br_id)
				//->where('pos_transactions.cust_id', '=', $cust_id)
				->where(function($w) use ($cust_id, $product_id, $arr_product){
					if ($cust_id != '') {
					  $w->where('pos_transactions.cust_id', '=', $cust_id);
					}
					if ($product_id != '') {
					  //$w->where('product_stocks.id', '=', $product_id);
					  $w->where('products.p_name', '=', $arr_product->p_name);
					}
				})
				//->groupBy('lookup_id')
				->orderBy('ratting_apps.created_at','DESC')
				->limit(50)
				->get();
				
				// tampilkan semua ulasan
				if(count($query_data) == 0){
			    /*
			    $query_data = DB::table($this->table)
				->selectRaw('ts_ratting_apps.id as pid, ra_value, ra_description, ra_file, ts_ratting_apps.created_at, cust_name, cust_email, external_id, pos_invoice, ts_products.p_name, ts_products.p_color, ts_product_stocks.id as product_id, p_main_image, p_image, cust_avatar, cust_gender, cust_reseller, ts_customers.id as cust_id, cust_phone')
				->leftJoin('pos_transactions', 'pos_transactions.id', '=', 'ratting_apps.lookup_id')
				->leftJoin('pos_transaction_details', 'pos_transaction_details.pt_id', '=', 'pos_transactions.id')
				->leftJoin('product_stocks', 'product_stocks.id', '=', 'pos_transaction_details.pst_id')
				->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
				->leftJoin('customers', 'customers.id', '=', 'ratting_apps.cust_id')
				->where('ra_type', '=', 'pos')
				// ra_value
				->where('ra_value', '=', '5')
				->where(function($w) use ($cust_id, $product_id, $arr_product){
					if ($cust_id != '') {
					  $w->where('pos_transactions.cust_id', '=', $cust_id);
					}
				})
				//->groupBy('lookup_id')
				->orderBy('ratting_apps.created_at','DESC')
				->limit(20)
				->get();
				*/
                $query_data = DB::table('ratting_apps as ra')
                    ->select([
                        'ra.id as pid',
                        'ra.ra_value',
                        'ra.ra_description',
                        'ra.ra_file',
                        'ra.created_at',
                        'c.cust_name',
                        'c.cust_email',
                        'pt.external_id',
                        'pt.pos_invoice',
                        'p.p_name',
                        'p.p_color',
                        'ps.id as product_id',
                        'p.p_main_image',
                        'p.p_image',
                        'c.cust_avatar',
                        'c.cust_gender',
                        'c.cust_reseller',
                        'c.id as cust_id',
                        'c.cust_phone',
                    ])
                    ->leftJoin('pos_transactions as pt', 'pt.id', '=', 'ra.lookup_id')
                    ->leftJoin('pos_transaction_details as ptd', 'ptd.pt_id', '=', 'pt.id')
                    ->leftJoin('product_stocks as ps', 'ps.id', '=', 'ptd.pst_id')
                    ->leftJoin('products as p', 'p.id', '=', 'ps.p_id')
                    ->leftJoin('customers as c', 'c.id', '=', 'ra.cust_id')
                    ->where('ra.ra_type', 'pos')
                    ->where('ra.ra_value', 5)
                    ->when(!empty($cust_id), function($q) use ($cust_id){
                        $q->where('pt.cust_id', $cust_id);
                    })
                    ->orderBy('ra.created_at', 'DESC')
                    ->limit(20)
                    ->get();
				
				}
				
				$image=array();
				$image_url = '';
				$results = array();
				if (!empty($query_data)) {
					//for($a=1; $a<=3; $a++){
					$image=array();
					foreach ($query_data as $row) {
						if(!empty($row->ra_file)){
							$banner = url('public/ulasan_file').'/'.$row->ra_file;
						} else {
							$banner = '';
						}
						if($row->cust_email!=''){$cust_email = $row->cust_email;} else {$cust_email='';}
						
						$image_url = "https://topsystem.id/api/product/600/";
						$image = \App\Helpers\ProductImageHelper::parseImages($row->p_main_image ?? null, $row->p_image ?? null);
						
						// avatar customer
						if(empty($row->cust_avatar)){
							if($row->cust_gender == 'm'){
								$avatar_profile = "https://www.topsystem.id/upload/avatar/AV1.png";
							} else {
								$avatar_profile = "https://www.topsystem.id/upload/avatar/AV51.png";
							}
						} else {
							$avatar_profile = $row->cust_avatar;
						}
						
						//if($row->cust_name){$nama_user=$row->cust_name;} else {$nama_user='User '.$cust_id;}
						//if($row->cust_name){$nama_user=substr_replace($row->cust_name, "***",- 3);} else {$nama_user='User '.$cust_id;}
						if($row->cust_name==$row->cust_phone){$nama_user=substr_replace($row->cust_name, "***",- 3);} else {
                            if($row->cust_name){ $nama_user=$row->cust_name; } else { $nama_user='User '.$row->cust_id; }
                        }
						
						$results[] = [
							'ulasan_id' => $row->pid,
							'ratting' => $row->ra_value,
							'description' => $row->ra_description,
							//'cust_name' => $row->cust_name,
							'cust_name' => $nama_user,
							'cust_email' => $cust_email,
							'external_id' => $row->external_id,
							'pos_invoice' => $row->pos_invoice,
							'banner' => $banner,
							'product_name' => $row->p_name.' '.$row->p_color,
							'product_id' => $row->product_id,
							'image_url' => $image_url,
							'image' => $image,
							'created_at' => $row->created_at,
							'cust_avatar' => $avatar_profile,
							'cust_reseller' => $row->cust_reseller,
						];
					}
					//}
					
		
				$data = [
					'status' => [
						'code' => 200,
						'flag_ulasan' => count($results),
						'description' => 'Success'
					],
					'results' => $results
				];
				
				} else {
					$data = [
						'status' => [
							'code' => 400,
							'flag_ulasan' => 0,
							'description' => 'Data not found'
						]
					];
				}
			} 
			// tidak ada data artikel di temukan
			else {
				$data = [
						'status' => [
						'code' => 400,
						'flag_ulasan' => 0,
						'description' => 'Data not found'
					]
				];
			}
		
		} // end POS
		/*
		else {
				$data = [
						'status' => [
						'code' => 400,
						'flag_ulasan' => 0,
						'description' => 'Data not found'
					]
				];
		}
		*/
		else 
		if($type_data == 'event'){
				/*
				$data = [
						'status' => [
						'code' => 400,
						'flag_ulasan' => 0,
						'description' => 'Data not found'
					]
				];
				*/
				$query_data = DB::table($this->table)
				->selectRaw('ts_ratting_apps.id as pid, ra_value, ra_description, ra_file, ts_ratting_apps.created_at, cust_name, cust_email, external_id, eo_name, eo_email, eo_phone, eo_price, eo_qty, eo_total, et_title, et_banner, et_price, eo_invoice, ts_event_tops.id as product_id, cust_avatar, cust_gender, cust_reseller, ts_customers.id as cust_id, cust_phone')
				->leftJoin('event_orders', 'event_orders.id', '=', 'ratting_apps.lookup_id')
				->leftJoin('event_tops', 'event_tops.id', '=', 'event_orders.et_id')
				->leftJoin('customers', 'customers.id', '=', 'ratting_apps.cust_id')
				->where('ra_type', '=', 'event')
				->where(function($w) use ($cust_id, $product_id){
					if ($cust_id != '') {
					  $w->where('ratting_apps.cust_id', '=', $cust_id);
					}
					if ($product_id != '') {
					  $w->where('event_tops.id', '=', $product_id);
					  //$w->where('event_orders.id', '=', $product_id);
					}
				})
				->orderBy('ratting_apps.created_at','DESC')
				->get();
				$results = array();
				if (!empty($query_data)) {
				    //$image = [];
					foreach ($query_data as $row) {
					    $image = [];
						if(!empty($row->ra_file)){
							$banner = url('public/ulasan_file').'/'.$row->ra_file;
						} else {
							$banner = '';
						}
						if($row->cust_email!=''){$cust_email = $row->cust_email;} else {$cust_email='';}
						
						/*
						if (!empty($row->p_main_image)) {
							$image_url = "https://topsystem.id/api/product/600/";
							$image[] = $row->p_main_image;
						}
						if (!empty($row->p_image) AND $row->p_image != '||||||') {
							$exp = explode('|', $row->p_image);
							$total = count($exp);
							for ($i = 0; $i < $total; $i++) {
								if (!empty($exp[$i])) {
									$image[] = $exp[$i];
								}
							}
						}
						*/
						// https://www.topsystem.id/upload/event/1724553540.jpg
						$image_url = "https://www.topsystem.id/upload/event/";
						$image[] = $row->et_banner ?? '';

						// avatar customer
						if(empty($row->cust_avatar)){
							if($row->cust_gender == 'm'){
								$avatar_profile = "https://www.topsystem.id/upload/avatar/AV1.png";
							} else {
								$avatar_profile = "https://www.topsystem.id/upload/avatar/AV51.png";
							}
						} else {
							$avatar_profile = $row->cust_avatar;
						}
                        
                        //if($row->cust_name){$nama_user=$row->cust_name;} else {$nama_user='User '.$cust_id;}
                        // substr_replace($name, "***",- 3)
                        //if($row->cust_name){$nama_user=substr_replace($row->cust_name, "***",- 3);} else {$nama_user='User '.$cust_id;}
                        if($row->cust_name===$row->cust_phone){$nama_user=substr_replace($row->cust_name, "***",- 3);} else {
                            if($row->cust_name){ $nama_user=$row->cust_name; } else { $nama_user='User '.$row->cust_id; }
                        }
                        
                        
            			$results[] = [
            				'ulasan_id' => $row->pid,
            				'ratting' => $row->ra_value,
            				'description' => $row->ra_description,
            				//'cust_name' => $row->eo_name,
            				'cust_name' => $nama_user,
            				'cust_email' => $row->eo_email,
            				'external_id' => $row->external_id,
            				'pos_invoice' => $row->eo_invoice,
            				'banner' => $banner,
            				'product_name' => $row->et_title,
            				'product_id' => $row->product_id,
            				'image_url' => $image_url,
            				'image' => $image,
            				'created_at' => $row->created_at,
            				'cust_avatar' => $avatar_profile,
            				'cust_reseller' => $row->cust_reseller,
            			];
						
					}
				}
			
			$data = [
				'status' => [
					'code' => 200,
					'flag_ulasan' => count($results),
					'description' => 'Success'
				],
				'results' => $results
			];
		}
        return response()->json($data);
    }
	
}
