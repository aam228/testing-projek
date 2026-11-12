<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Hash;

class PromoController extends Controller
{
	protected $table = 'product_discounts';
	protected $url_server = 'https://topsystem.id/';
	//protected $url_server = 'https://devtop.topsystem.id/';
	
	private function checkData($table, $select, $where)
    {
        $affected = DB::table($table)
            ->select($select)
            ->where($where)
            ->get()->first();
        return $affected;
    }
	
	// Cek View data
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
        
        // save log traffic
		$data_log = [
			'cust_id' => $request->cust_id,
			'ta_page_access' => "Promo",
			'ta_description' => "Mengakses laman promo aplikasi",
			'created_at' => date("Y-m-d H:i:s"),
			'ip_address' => $request->ip(),
		];
		$save_log = DB::table("traffic_apps")->insertGetId(array_merge($data_log));
        
		/*
		$cust_id = $request->cust_id; // optional...
		if(empty($cust_id)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id harus diisi'
                ]
            ];
            return response()->json($data);
		}
		
		$row_cust = DB::table("customers")->where("id","=",$cust_id)->first();
		if(!$row_cust){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data customer tidak ditemukan'
                ]
            ];
            return response()->json($data);
		}
		*/
		
		$promo_id = $request->promo_id;
        $query_data = DB::table($this->table)
		->select('product_discounts.id as pid', 'product_discounts.created_at', 'st_id', 'std_id', 'pd_name', 'pd_type', 'pd_value', 'pd_date', 'no_mom', 'pd_banner', 'pd_tnc', 'pd_use')
		->where('std_id', '=', '20') // kusus apps
		//->whereNotIn('pd_type', ['fix_special','b1g1'])
		->whereNotIn('pd_type', ['fix_special'])
		//->where('pd_date', '>=', date("Y-m-d"))
		/*
		->where('pd_start', '<=', date("Y-m-d"))
		->where('pd_date', '>=', date("Y-m-d"))
		->where('pd_start_time', '<=', date("H:i:s"))
		->where('pd_end_time', '>=', date("H:i:s"))
		*/
		->whereRaw("CONCAT(ts_product_discounts.pd_start,' ',ts_product_discounts.pd_start_time)<='".date("Y-m-d H:i:s")."'")
        ->whereRaw("CONCAT(ts_product_discounts.pd_date,' ',ts_product_discounts.pd_end_time)>='".date("Y-m-d H:i:s")."'")
		->where('pd_flash_sale',"=","0") // selain flash sale
		/*
		->where(function($w) use($promo_id) {
			if(!empty($promo_id)){
				$w->where('product_discounts.id', '=', $promo_id);
			}
		})
		*/
		->whereNotNull("pd_banner")
		->groupBy('no_mom')
		->orderBy('id','DESC')
        ->get();
        $results = array();
        if (!empty($query_data)) {
            foreach ($query_data as $row) {
                // cek TNC promo
				if($row->pd_tnc){$promo_tnc=$row->pd_tnc;} else {$promo_tnc='';}
				if($row->pd_use){$promo_use=$row->pd_use;} else {$promo_use='';}
				$results[] = [
					'promo_id' => $row->pid,
					'promo_name' => $row->pd_name,
					'promo_type' => $row->pd_type,
					'promo_value' => $row->pd_value,
					'promo_expired' => $row->pd_date,
					'promo_banner' => $this->url_server.'public/upload/promo/'.$row->pd_banner,
					'promo_create' => $row->created_at,
					'promo_tnc' => $promo_tnc,
					'promo_use' => $promo_use,
                ];
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
	
	// detailPromo
	public function detailPromo(Request $request)
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


		$promo_id = $request->promo_id;
		if (!$promo_id) {
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field promo_id tidak boleh kosong'
                ]
            ];
            return response()->json($data);
        }

		try {
		// promo_id bisa berupa banyak id dipisah koma (multi lookup banner);
		// promo utk data header promo = id pertama, sisanya dipakai ekspansi no_mom di bawah
		$promo_ids_req = array_values(array_filter(array_map('intval', explode(',', (string) $promo_id))));
		if (!count($promo_ids_req)) { $promo_ids_req = [(int) $promo_id]; }
		$promo_id = $promo_ids_req[0];
		// cek apakah ada promo akrif yang dicari (product_discounts)
		$arr_promo  = DB::table($this->table)
		->select('product_discounts.id as pid', 'product_discounts.created_at', 'st_id', 'std_id', 'pd_name', 'pd_type', 'pd_value', 'pd_date', 'no_mom', 'pd_banner', 'pd_tnc', 'pd_use')
		->where('id', '=', $promo_id)
		->where('std_id', '=', '20') // kusus apps
		//->whereNotIn('pd_type', ['fix_special','b1g1'])
		->whereNotIn('pd_type', ['fix_special'])
		//->where('pd_date', '>=', date("Y-m-d"))
		->first();
		// data promo tidak ditemukan
		if(!$arr_promo){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data promo tidak ditemukan'
                ]
            ];
            return response()->json($data);
		}
		// promo sudah tidak aktif
		if($arr_promo->pd_date<date("Y-m-d")){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data promo sudah tidak aktif'
                ]
            ];
            return response()->json($data);
		}

		
		$exception = DB::table('exception_locations')->select('pl_code')
        ->leftJoin('product_locations', 'product_locations.id', '=', 'exception_locations.pl_id')->get();
        $exc_location = array();
        foreach ($exception as $exc) {
            $exc_location[] = $exc->pl_code;
        }
		
		// cek promo by no MOM — match PERSIS, bukan prefix.
		$all_promo_ids = $promo_ids_req;
		try {
			if (\Illuminate\Support\Facades\Schema::hasColumn('banner_apps', 'ba_lookup_ids')) {
				$banner_lookup = DB::table('banner_apps')
					->select('ba_lookup_ids')
					->where('ba_delete', '=', '0')
					->where('ba_type', '=', 'Promo')
					->where('ba_start', '<=', date("Y-m-d"))
					->where('ba_end', '>=', date("Y-m-d"))
					->where(function($w) use ($promo_ids_req) {
						$first = true;
						foreach ($promo_ids_req as $pid) {
							if ($first) { $w->where('ba_lookup_id', '=', $pid); $first = false; }
							else { $w->orWhere('ba_lookup_id', '=', $pid); }
							$w->orWhereRaw('FIND_IN_SET(?, ba_lookup_ids)', [$pid]);
						}
					})
					->pluck('ba_lookup_ids');
				foreach ($banner_lookup as $blist) {
					$all_promo_ids = array_merge($all_promo_ids, array_filter(array_map('intval', explode(',', (string) $blist))));
				}
			}
		} catch (\Throwable $e) {}
		$all_promo_ids = array_values(array_unique($all_promo_ids));
		$rows_mom = DB::table('product_discounts')->select('no_mom')
			->whereIn('id', $all_promo_ids)
			->where('std_id', '=', '20')
			->whereNotIn('pd_type', ['fix_special'])
			->get();
		$no_moms = [];
		foreach ($rows_mom as $m) {
			if (!empty($m->no_mom)) {
				$no_moms[] = $m->no_mom;
			}
		}
		$no_moms = array_values(array_unique($no_moms));

		$by_mom = DB::table('product_discounts')->select('id')
				  ->where('std_id', '=', '20')
				  ->whereNotIn('pd_type', ['fix_special'])
				  ->where(function($q) use ($no_moms, $all_promo_ids) {
					  if (!empty($no_moms)) {
						  $q->whereIn('no_mom', $no_moms)
						    ->orWhereIn('id', $all_promo_ids);
					  } else {
						  $q->whereIn('id', $all_promo_ids);
					  }
				  })
				  ->whereRaw("CONCAT(ts_product_discounts.pd_start,' ',COALESCE(ts_product_discounts.pd_start_time, '00:00:00'))<='".date("Y-m-d H:i:s")."'")
				  ->whereRaw("CONCAT(ts_product_discounts.pd_date,' ',COALESCE(ts_product_discounts.pd_end_time, '23:59:59'))>='".date("Y-m-d H:i:s")."'")
				  ->pluck('id')
				  ->toArray();

		$in_pdid = !empty($by_mom) ? $by_mom : $all_promo_ids;
		
        /*
		$data_products = DB::table('product_discount_details')
		->selectRaw("ts_product_discount_details.id, ts_product_discount_details.pst_id, pc_name as category, br_name as brand, p_name as article, p_color as color, sz_name as size, ps_price_tag, p_price_tag, ps_sell_price, p_sell_price, p_main_image, p_image, psc_name, pssc_name, sold, SUM(ts_product_location_setups.pls_qty) as stock, CONCAT(p_name,' (',br_name,')') as p_name_brand, pd_type, pd_value")
		->leftJoin('product_discounts', 'product_discounts.id', '=', 'product_discount_details.pd_id') // cek product_discounts
		->leftJoin('product_stocks', 'product_stocks.id', '=', 'product_discount_details.pst_id')
		->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
		->leftJoin('product_categories', 'product_categories.id', '=', 'products.pc_id')
		->leftJoin('product_sub_categories', 'product_sub_categories.id', '=', 'products.psc_id')
		->leftJoin('product_sub_sub_categories', 'product_sub_sub_categories.id', '=', 'products.pssc_id')
        ->leftJoin('brands', 'brands.id', '=', 'products.br_id')
        ->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
        //  cek stok
        ->leftJoin('product_location_setups', 'product_location_setups.pst_id', '=', 'product_stocks.id')
        ->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
        ->whereNotIn('pl_code', $exc_location)
		->where('pls_qty', '>', '0')
		->whereNotNull('p_main_image')
		->where('p_price_tag', '>', '0')
		->where('p_delete', '=', '0')
		->whereNotNull('p_main_image')
		//->where('wishlists.cust_id', '=', $cust_id)
		//->where('product_discount_details.pd_id', '=', $promo_id)
		->whereIn('product_discount_details.pd_id', $in_pdid)
		->where('products.p_delete', '=', '0')
		->groupBy('p_name_brand')
        ->orderBy("products.p_name","ASC")
		//->orderBy("products.p_name","ASC") 
		//->limit('100')
        //->get();
        ->paginate(200);
		*/
		
		$cust_id = $request->cust_id;
		if($cust_id){$page_limit=10;} else {$page_limit=200;}
		
		$data_products = DB::table('product_discount_details')
		->selectRaw("ts_product_discount_details.id, ts_product_discount_details.pst_id, pc_name as category, br_name as brand, p_name as article, p_color as color, sz_name as size, ps_price_tag, p_price_tag, ps_sell_price, p_sell_price, p_main_image, p_image, psc_name, pssc_name, sold, SUM(ts_product_stocks.ps_qty) as stock, CONCAT(p_name,' (',br_name,')') as p_name_brand, pd_type, pd_value")
		->leftJoin('product_discounts', 'product_discounts.id', '=', 'product_discount_details.pd_id') // cek product_discounts
		->leftJoin('product_stocks', 'product_stocks.id', '=', 'product_discount_details.pst_id')
		->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
		->leftJoin('product_categories', 'product_categories.id', '=', 'products.pc_id')
		->leftJoin('product_sub_categories', 'product_sub_categories.id', '=', 'products.psc_id')
		->leftJoin('product_sub_sub_categories', 'product_sub_sub_categories.id', '=', 'products.pssc_id')
        ->leftJoin('brands', 'brands.id', '=', 'products.br_id')
        ->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
        //  cek stok
        //->leftJoin('product_location_setups', 'product_location_setups.pst_id', '=', 'product_stocks.id')
        //->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
        //->whereNotIn('pl_code', $exc_location)
		->where('product_stocks.ps_qty', '>', '0')
		->whereNotNull('p_main_image')
		->where('p_price_tag', '>', '0')
		->where('p_delete', '=', '0')
		->whereNotNull('p_main_image')
		->whereIn('product_discount_details.pd_id', $in_pdid)
		->where('products.p_delete', '=', '0')
		->groupBy('p_name_brand')
        //->orderBy("products.p_name","ASC")
        ->orderBy(DB::raw('RAND()'))
        ->paginate($page_limit);
		
		$pid = '';
        $results = array();
		$address = array();
        if (count($data_products)>0) {
            foreach ($data_products as $row) {
				$price = null;
                $price_tag = null;
                $store = null;
                $image_url = $this->url_server."api/product/600/";
                $image = \App\Helpers\ProductImageHelper::parseImages($row->p_main_image, $row->p_image);
                
				/*
				if (!empty($row->ps_sell_price)) {
                    $price = $row->ps_sell_price;
                } else {
                    $price = $row->p_sell_price;
                }
				// harga bandrol ***
                if (!empty($row->ps_price_tag)) {
                    $price_tag = $row->ps_price_tag;
                } else {
                    $price_tag = $row->p_price_tag;
                }
				*/
				
				$price_tag = $row->p_price_tag; // harga bandrol
				// cek diskon
				/*
				if($arr_promo->pd_type == "percent"){
					$nilai_diskon = $price_tag * ($arr_promo->pd_value /100);
					$sell_price = $price_tag - $nilai_diskon;
				} else
				if($arr_promo->pd_type == "amount"){
					$sell_price = $price_tag - $arr_promo->pd_value;
				} else
				if($arr_promo->pd_type == "fix"){
					$sell_price = $arr_promo->pd_value;
				} else {
					$sell_price = $sell_price;
				}
				*/
				
				$sell_price = $price_tag;
				$flag_buy1get1 = 0;
				if($row->pd_type == "percent"){
					$nilai_diskon = $price_tag * ($row->pd_value /100);
					$sell_price = $price_tag - $nilai_diskon;
				} else
				if($row->pd_type == "amount"){
					$sell_price = $price_tag - $row->pd_value;
				} else
				if($row->pd_type == "fix"){
					$sell_price = $row->pd_value;
				} else
				if($row->pd_type == "b1g1"){
					//$sell_price = $price_tag+10000;
					$sell_price = $price_tag;
					$flag_buy1get1 = 1;
					//$price_tag = $price_tag+10000;
					$price_tag = $price_tag;
				}
				
				if ($flag_buy1get1 == 0) {
					$check_b1g1 = DB::table('product_discount_details')
						->leftJoin('product_discounts', 'product_discounts.id', '=', 'product_discount_details.pd_id')
						->where('product_discount_details.pst_id', '=', $row->pst_id)
						->where('std_id', '=', '20')
						->where('pd_type', '=', 'b1g1')
						->whereRaw("CONCAT(ts_product_discounts.pd_start,' ',COALESCE(ts_product_discounts.pd_start_time, '00:00:00'))<='" . date("Y-m-d H:i:s") . "'")
						->whereRaw("CONCAT(ts_product_discounts.pd_date,' ',COALESCE(ts_product_discounts.pd_end_time, '23:59:59'))>='" . date("Y-m-d H:i:s") . "'")
						->exists();
					if ($check_b1g1) {
						$flag_buy1get1 = 1;
					}
				}

				if(!empty($price_tag) && $price_tag > 0 && $price_tag != $sell_price){
					$persen_potongan = 100 - (($sell_price / $price_tag) * 100);
				} else {
					$persen_potongan = 0;
				}
				
				// tampilkan jumlah warna tersedia
				$stock_color = DB::table('product_stocks')
				->selectRaw("ts_product_stocks.id as pid, pc_name as category, br_name as brand, p_name as article, p_color as color, sz_name as size, ps_price_tag, p_price_tag, ps_sell_price, p_sell_price, p_main_image, p_image, psc_name, pssc_name, sold, SUM(ts_product_location_setups.pls_qty) as stock, CONCAT(p_name,' (',br_name,')') as p_name_brand, CONCAT(p_name,' (',p_color,')') as p_name_color")
				->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
				->leftJoin('product_categories', 'product_categories.id', '=', 'products.pc_id')
				->leftJoin('product_sub_categories', 'product_sub_categories.id', '=', 'products.psc_id')
				->leftJoin('product_sub_sub_categories', 'product_sub_sub_categories.id', '=', 'products.pssc_id')
				->leftJoin('brands', 'brands.id', '=', 'products.br_id')
				->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
				//cek stok
				->leftJoin('product_location_setups', 'product_location_setups.pst_id', '=', 'product_stocks.id')
				->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
				->whereNotIn('pl_code', $exc_location)
				//->whereIn('product_locations.st_id', ['2'])
				->where('pls_qty', '>', '0')
				->whereNotNull('p_main_image')
				->where('p_price_tag', '>', '0')
				->where('p_delete', '=', '0')
				->whereNotNull('p_main_image')
				->where('p_name', '=', $row->article)
				->groupBy('p_name_color')
				->get();
				
				$image = array();
				$no_color = 0;
				foreach($stock_color as $row_color){
				    $no_color++;
					$img = \App\Helpers\ProductImageHelper::parseSingleImage($row_color->p_main_image, $row_color->p_image);
					if (!empty($img) && !in_array($img, $image, true)) {
						$image[] = $img;
					}
				}
				$image_url = $this->url_server."api/product/600/";
				
				if($flag_buy1get1==1){$label_promo='B1G1';} else {$label_promo='';}
				if(count($stock_color)>0){
    				$results[] = [
                        //'wishlist_id' => $row->id,
                        'product_id' => $row->pst_id,
                        'category' => $row->category,
                        'brand' => $row->brand,
                        'sub_cat' => $row->psc_name,
                        'subsub_cat' => $row->pssc_name,
                        'article' => $row->article,
                        'stock' => $row->stock,
                        'price_tag' => $price_tag,
    					'sell_price' => $sell_price,
    					'discount' => round($persen_potongan),
    					'sold' => $row->sold,
    					'ratting' => 5, //$ratting,
    					'total_ulasan' => 0,
    					'jumlah_warna' => count($stock_color),
                        'image_url' => $image_url,
                        'image' => $image,
    					'flag_buy1get1' => $flag_buy1get1,
    					'label_promo' => $label_promo,
                    ];
				}
            }
			
			// cek TNC promo
			if($arr_promo->pd_tnc){$promo_tnc=$arr_promo->pd_tnc;} else {$promo_tnc='';}
			if($arr_promo->pd_use){$promo_use=$arr_promo->pd_use;} else {$promo_use='';}
			
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success'
				],
				'promo' => [
					'promo_id' => $arr_promo->pid,
					'promo_name' => $arr_promo->pd_name,
					'promo_type' => $arr_promo->pd_type,
					'promo_value' => $arr_promo->pd_value,
					'promo_expired' => $arr_promo->pd_date,
					'promo_banner' => $this->url_server.'public/upload/promo/'.$arr_promo->pd_banner,
					'promo_create' => $arr_promo->created_at,
					'promo_tnc' => $promo_tnc,
					'promo_use' => $promo_use,
				],
				'promo_detail' => $results,
			];
        } else {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data tidak ditemukan'
				],
				'promo' => [
					'promo_id' => $arr_promo->pid,
					'promo_name' => $arr_promo->pd_name,
					'promo_type' => $arr_promo->pd_type,
					'promo_value' => $arr_promo->pd_value,
					'promo_expired' => $arr_promo->pd_date,
					'promo_banner' => $this->url_server.'public/upload/promo/'.$arr_promo->pd_banner,
					'promo_create' => $arr_promo->created_at,
				],
				'promo_detail' => $results,
			];
		}
        return response()->json($data);
		} catch (\Throwable $e) {
			return response()->json([
				'status' => [
					'code' => 500,
					'description' => $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')'
				]
			], 500);
		}
    }
    
    // klaim benefit dan GWP di aplikasi
	public function benefitCustomer(Request $request)
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
		$product_id = str_replace(" ","",$request->product_id);
		$store_id = str_replace(" ","",$request->store_id);
		
		if(empty($cust_id) || empty($product_id) || empty($store_id)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id, product_id dan store_id tidak boleh kosong',
                ]
            ];
            return response()->json($data);
		}
		
		$arr_customer = DB::table("customers")->where('id', $cust_id)->where('cust_delete', "0")->first();
		if(empty($arr_customer)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data customer tidak ditemukan'
                ]
            ];
            return response()->json($data);
		}
		
		$total_invoice = $request->total_invoice;		
		$produk_level = array();
		$produk_gwp = array();
		
		$in_product_id = explode(",",$product_id);
		$in_store_id = explode(",",$store_id);
		
		// kusus benetif customer jika audah level PRO dan ELITE
		if(in_array($arr_customer->cust_membership_type,array("PRO PLAYER","ELITE PLAYER"))){
		// cek belanja produk
		$arr_product = DB::table("product_stocks")
			->select("product_stocks.id", "p_name", "p_color")
			->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
			->leftJoin('product_sub_categories', 'product_sub_categories.id', '=', 'products.psc_id')
			//->where('cust_id', $cust_id)
			->whereIn('product_stocks.id', $in_product_id)
			->where('psc_name','=','SHOES') // kusus sepatu dapat free kaos kaki
			->get();
		if(count($arr_product)>'0'){
			// cek bin pengecualian
			$exception = DB::table('exception_locations')->select('pl_code')
			->leftJoin('product_locations', 'product_locations.id', '=', 'exception_locations.pl_id')->get();
			$exc_location = array();
			foreach ($exception as $exc) {
				$exc_location[] = $exc->pl_code;
			}
			
			// cek stok free kaos kaki untuk customer 
			$stock = DB::table('product_stocks')
			->selectRaw("ts_product_stocks.id as pid, pc_name as category, br_name as brand, p_name as article, p_color as color, sz_name as size, ps_price_tag, p_price_tag, ps_sell_price, p_sell_price, p_main_image, p_image, psc_name, pssc_name, sold, SUM(ts_product_location_setups.pls_qty) as stock, CONCAT(p_name,' (',br_name,')') as p_name_brand, SUM(ts_products.p_ratting) as tot_ratting, COUNT(ts_products.id) as tot_ratting_data, SUM(ts_products.p_user_ratting) as tot_ratting_user, st_name, ts_stores.id as store_id, ts_product_locations.id as pl_id, ts_products.id as p_id")
			->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
			->leftJoin('product_categories', 'product_categories.id', '=', 'products.pc_id')
			->leftJoin('product_sub_categories', 'product_sub_categories.id', '=', 'products.psc_id')
			->leftJoin('product_sub_sub_categories', 'product_sub_sub_categories.id', '=', 'products.pssc_id')
			->leftJoin('brands', 'brands.id', '=', 'products.br_id')
			->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
			//cek stok
			->leftJoin('product_location_setups', 'product_location_setups.pst_id', '=', 'product_stocks.id')
			->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
			->leftJoin('stores', 'stores.id', '=', 'product_locations.st_id')
			->whereNotIn('pl_code', $exc_location)
			->whereIn('product_locations.st_id', $in_store_id)
			->where('pls_qty', '>', '0')
			->whereNotNull('p_main_image')
			->where('p_price_tag', '>', '0')
			->where('p_delete', '=', '0')
			->whereNotNull('p_main_image')
			->where('products.id', '=', '5234') // kusus kaos kaki
			//->groupBy('p_name_brand')
			->first();
			// jika tersedia stok
			if($stock){
                $image = \App\Helpers\ProductImageHelper::parseImages($stock->p_main_image, $stock->p_image);
				
				if($stock->pid){
				$produk_level[] = ['product_id' => $stock->pid,
								 'article' => $stock->article,
								 //'product_name' => $stock->article,
								 'color' => $stock->color,
								 'size' => $stock->size,
								 'image_url' => $this->url_server.'api/product/600/',
								 'image' => $image,
								 'store_id' => $stock->store_id,
								 'store_name' => $stock->st_name,
								 'type' => "non_gwp",
								];
			    }
			}
		}
		
		} // end free kaos kaki sesuai level user
		
		
		
		// cek produk GWP
		$exception = DB::table('exception_locations')->select('pl_code')
        ->leftJoin('product_locations', 'product_locations.id', '=', 'exception_locations.pl_id')->get();
        $exc_location = array();
        foreach ($exception as $exc) {
            $exc_location[] = $exc->pl_code;
        }
		
		
		// cek promo by no MOM
		$by_mom = DB::table('product_discounts')->select('product_discounts.id')
				  ->leftJoin('discount_bandrol', 'discount_bandrol.id', '=', 'product_discounts.db_id')
				  //->where("no_mom","=",$arr_promo->no_mom)
				  ->where('std_id', '=', '20')
				  //->where('pd_date', '>=', date("Y-m-d"))
				  ->where('pd_start', '<=', date("Y-m-d"))
				  ->where('pd_date', '>=', date("Y-m-d"))
				  ->where('pd_start_time', '<=', date("H:i:s"))
				  ->where('pd_end_time', '>=', date("H:i:s"))
				  ->where('db_value', '<=', $total_invoice)
				  ->whereIn('pd_type', ['fix_special'])
				  ->get();
        $in_pdid = array();
        foreach ($by_mom as $mom) {
            $in_pdid[] = $mom->id;
        }
		
		
        $data_products = DB::table('product_discount_details')
		->selectRaw("ts_product_discount_details.id, ts_product_discount_details.pst_id, pc_name as category, br_name as brand, p_name as article, p_color as color, sz_name as size, ps_price_tag, p_price_tag, ps_sell_price, p_sell_price, p_main_image, p_image, psc_name, pssc_name, sold, SUM(ts_product_location_setups.pls_qty) as stock, CONCAT(p_name,' (',br_name,')') as p_name_brand, pd_type, pd_value, ts_products.id as product_id, ts_product_stocks.id as ps_id, ts_stores.id as store_id, ts_stores.st_name")
		->leftJoin('product_discounts', 'product_discounts.id', '=', 'product_discount_details.pd_id') // cek product_discounts
		->leftJoin('product_stocks', 'product_stocks.id', '=', 'product_discount_details.pst_id')
		->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
		->leftJoin('product_categories', 'product_categories.id', '=', 'products.pc_id')
		->leftJoin('product_sub_categories', 'product_sub_categories.id', '=', 'products.psc_id')
		->leftJoin('product_sub_sub_categories', 'product_sub_sub_categories.id', '=', 'products.pssc_id')
        ->leftJoin('brands', 'brands.id', '=', 'products.br_id')
        ->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
        //  cek stok
        ->leftJoin('product_location_setups', 'product_location_setups.pst_id', '=', 'product_stocks.id')
        ->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
		->leftJoin('stores', 'stores.id', '=', 'product_locations.st_id')
        ->whereNotIn('pl_code', $exc_location)
		->where('pls_qty', '>', '0')
		->whereNotNull('p_main_image')
		->where('p_price_tag', '>', '0')
		->where('p_delete', '=', '0')
		->whereNotNull('p_main_image')
		->whereIn('product_discount_details.pd_id', $in_pdid)
		->where('products.p_delete', '=', '0')
		->whereIn('product_locations.st_id', $in_store_id)
		//->groupBy('p_name_brand')
		//->groupBy('products.id')
		->groupBy('product_stocks.id')
        ->orderBy("products.p_name","ASC")
		->orderBy("products.p_color","ASC")
		->orderBy("stores.id","ASC")
        //->paginate(10);
		->get();
				
		$pid = '';
        $results = array();
		$address = array();
        if (count($data_products)>0) {
            foreach ($data_products as $row) {
				$image_url = $this->url_server."api/product/600/";
				$singleImg = \App\Helpers\ProductImageHelper::parseSingleImage($row->p_main_image, $row->p_image);
				$image = !empty($singleImg) ? [$singleImg] : [];
				
				/*
				$results[] = [
                    'product_id' => $row->pst_id,
                    'category' => $row->category,
                    'brand' => $row->brand,
                    'sub_cat' => $row->psc_name,
                    'subsub_cat' => $row->pssc_name,
                    'article' => $row->article,
                    'stock' => $row->stock,
                    'price_tag' => $price_tag,
					'sell_price' => $sell_price,
					'discount' => round($persen_potongan),
					'sold' => $row->sold,
					'ratting' => 0, //$ratting,
					'total_ulasan' => 0,
					'jumlah_warna' => count($stock_color),
                    'image_url' => $image_url,
                    'image' => $image,
                ];
				*/
				$produk_gwp[] = [
					'product_id' => $row->pst_id,
					'article' => $row->article,
					'color' => $row->color,
					'size' => $row->size,
					'image_url' => $image_url,
					'image' => $image,
					'store_id' => $row->store_id,
					'store_name' => $row->st_name,
					'type' => "gwp",
				];
            }
        }
		// END PRODUK GWP
		
		//echo count($data_product);
		if(count($produk_level) ==0 && count($produk_gwp) ==0){
			$json_data = [
				'status' => [
					'code' => 400,
					'description' => 'Data produk tidak ditemukan!',
					'claim_level' => $produk_level,
					'claim_gwp' => $produk_gwp,
				]
			];
		} 
		// ada data promo dan benefit customer
		else {
			$json_data = [
				'status' => [
					'code' => 200,
					'description' => 'Data produk ditemukan, segera klaim produk pilihan kamu!',
					'claim_level' => $produk_level,
					'claim_gwp' => $produk_gwp,
				]
			];
		}
		
		return response()->json($json_data);
	}
}
