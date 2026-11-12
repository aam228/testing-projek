<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Hash;

class SearchController extends Controller
{
	//protected $table = 'customer_coins';
	//protected $setting_apps = DB::table("setting_apps")->where("id","=",1)->first();
	protected $url_server = 'https://topsystem.id/';
	
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
		
		//$setting_apps = DB::table("setting_apps")->where("id","=",1)->first();
		
		$cust_id 	= $request->cust_id;
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
		
		// save log traffic
		$data_log = [
			'cust_id' => $request->cust_id,
			'ta_page_access' => "Searching",
			'ta_description' => "Mengakses laman pencarian",
			//'ta_lookup_id' => $request->product_id,
			'created_at' => date("Y-m-d H:i:s"),
			'ip_address' => $request->ip(),
		];
		$save_log = DB::table("traffic_apps")->insertGetId(array_merge($data_log));
		
		$score_mission = array();
		// cek kelengkapan data profile
		if(empty($row_cust->cust_bday) || empty($row_cust->cust_phone) || empty($row_cust->cust_email) || $row_cust->cust_phone_verify!=1 || $row_cust->cust_email_verify!='1' || empty($row_cust->cust_name)){
			$score_mission[] = [
				'mission_type' => 0,
				'mission_title' => 'Lengkapi Data Diri',
				'mission_description' => 'Isi seluruh data mu secara lengkap serta alamat, dan selesaikan misi lainnya!',
				'mission_icon' => url("public/upload/icon_users.png"),
			];
		}
		// cek game
		$query = DB::table('games')
		->select('games.id', 'gm_name', 'gm_slug', 'gm_description', 'gm_image', 'gm_view', 'gm_time', 'gm_active', 'games.created_at', 'u_name', 'gm_score')
		->leftJoin('users', 'users.id', '=', 'games.user_input')
		->where('games.gm_active', '=', "1")
		->where('games.gm_delete', '=', "0")
		->orderBy("games.created_at",'DESC')
		->get();
		$ngame=0;
		foreach ($query as $row) {
			$cek_usergame = DB::table("game_users")->where("gm_id","=",$row->id)->where("cust_id","=",$cust_id)->first();
				if(empty($cek_usergame)){
					$ngame++;
				}
		}
		if($ngame>0){
			$score_mission[] = [
				'mission_type' => 1,
				'mission_title' => 'Mainkan game',
				'mission_description' => 'Mainkan game untuk menambah score mu, dan selesaikan misi lainnya!',
				'mission_icon' => url("public/upload/icon_games.png"),
			];
		}	
		// cek forum
		$query_data = DB::table('forums')
		->select('forums.id', 'fo_name', 'fo_slug', 'fo_description', 'fo_image', 'fo_view', 'fo_active', 'forums.created_at', 'u_name')
		->leftJoin('users', 'users.id', '=', 'forums.user_input')
		->where('forums.fo_active', '=', "1")
		->where('forums.fo_delete', '=', "0")
        ->orderBy("forums.created_at",'DESC')
		->get();
		$nforum=0;
		foreach ($query_data as $row) {
			$join_cek = DB::table("forum_joins")->where("cust_id","=",$cust_id)->where("fo_id","=",$row->id)->first();
			if(!$join_cek){$nforum++;}
		}
		if($nforum>0){
			$score_mission[] = [
				'mission_type' => 2,
				'mission_title' => 'Join forum',
				'mission_description' => 'Segera join forum untuk menambah score mu, dan selesaikan misi lainnya!',
				'mission_icon' => url("public/upload/icon_forums.png"),
			];
		}
		
		//$expired_coin = date("Y")."-12-31";
		/*
		$search_event = array();
		$search_forum = array();
		$search_promo = array();
		$search_blog = array();
		$search_game = array();
		*/
		// key serching
		$key_search = array();
		$key_search[]= [
				'search_title' => 'event',
				'search_detail' => $this->CekEvent(),
		];
		$key_search[]= [
				'search_title' => 'forum',
				'search_detail' => $this->getForum($cust_id),
		];
		$key_search[]= [
				'search_title' => 'promo',
				'search_detail' => $this->getPromo(),
		];
		$key_search[]= [
				'search_title' => 'blog',
				'search_detail' => $this->getBlog(),
		];
		$key_search[]= [
				'search_title' => 'game',
				'search_detail' => $this->getGames($cust_id),
		];
		
        $data = [
            'status' => [
                'code' => 200,
                'description' => 'Success'
            ],
			'score_mission' => $score_mission,
			'key_search' => [
				'search_product' => $this->getArticle($cust_id),
				'search_event' => $this->CekEvent(),
				'search_forum' => $this->getForum($cust_id),
				'search_promo' => $this->getPromo(),
				'search_blog' => $this->getBlog(),
				'search_game' => $this->getGames($cust_id),
			],
			//'key_search' => $key_search,
        ];		
		return response()->json($data);
	}
	
	// cek product
	// cek artikel terbaru
	private function getArticle($cust_id='')
    {
        $exception = DB::table('exception_locations')->select('pl_code')
        ->leftJoin('product_locations', 'product_locations.id', '=', 'exception_locations.pl_id')->get();
        $exc_location = array();
        foreach ($exception as $exc) {
            $exc_location[] = $exc->pl_code;
        }
		
		
		$sub_kat = "";
		$subsub_kat = "";
		$brand = "";
		$product_name = "";
		$sorting_artikel  = "dilihat";
		if(!empty($sorting_artikel)){
			if($sorting_artikel == "baru"){
				//$sort_orders = 'purchase_order_article_details.id';
				$sort_orders = 'products.created_at';
			} else 
			if($sorting_artikel == "terlaris"){
				$sort_orders = 'products.sold';
			} else 
			if($sorting_artikel == "dilihat"){
				$sort_orders = 'products.sold';
			} else {
				$sort_orders = 'products.sold';
			}
		} else {
			$sort_orders = 'products.sold';
		}
		/*
        $stock = DB::table('product_stocks')->selectRaw('ts_product_stocks.id as pid, pc_name as category, br_name as brand, p_name as article, p_color as color, sz_name as size, ps_price_tag, p_price_tag, ps_sell_price, p_sell_price, p_main_image, p_image, psc_name, pssc_name, sold, SUM(ts_product_location_setups.pls_qty) as stock')
        ->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
        ->leftJoin('product_categories', 'product_categories.id', '=', 'products.pc_id')
		->leftJoin('product_sub_categories', 'product_sub_categories.id', '=', 'products.psc_id')
		->leftJoin('product_sub_sub_categories', 'product_sub_sub_categories.id', '=', 'products.pssc_id')
        ->leftJoin('brands', 'brands.id', '=', 'products.br_id')
        ->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
        // cek stok
		->leftJoin('product_location_setups', 'product_location_setups.pst_id', '=', 'product_stocks.id')
        ->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
        ->whereNotIn('pl_code', $exc_location)
		->whereNotIn('product_locations.st_id', array('3', '4', '5', '10', '11', '15'))
		->where('product_location_setups.pls_qty', '>', '0')
		->where('p_delete', '=', '0')
		->whereNotNull('p_main_image')
		->where(function($w) use($sub_kat, $subsub_kat, $brand, $product_name) {
			if (!empty($sub_kat)) {
				$sub_details = explode(",",str_replace(", ",",",$sub_kat));
				$w->whereIn('product_sub_categories.psc_name', $sub_details);
			}
			if (!empty($subsub_kat)) {
				$subsub_details = explode(",",str_replace(", ",",",$subsub_kat));
				$w->whereIn('product_sub_sub_categories.pssc_name', $subsub_details);
			}
			if (!empty($product_name)) {
				$arr_product_name = explode(" ",$product_name);
				foreach($arr_product_name as $nama_produk){
					$w->where(DB::raw("CONCAT(p_name, ' ', br_name, ' ',psc_name,' ',pssc_name,' ',br_name)"), 'LIKE', "%$nama_produk%");
				}
			}
			if (!empty($brand)) {
				$brand_details = explode(",",str_replace(", ",",",$brand));
				$w->whereIn('brands.br_name', $brand_details);
			}
		})

		->groupBy('products.p_name')
		->orderBy($sort_orders,'DESC')
		->limit('5')
        ->get();
		//->paginate(10);
		*/
		
		$stock = DB::table('product_stocks')
		->selectRaw('ts_product_stocks.id as pid, pc_name as category, br_name as brand, br_id, p_name as article, p_color as color, sz_name as size, ps_price_tag, p_price_tag, ps_sell_price, p_sell_price, p_main_image, p_image, psc_name, pssc_name, sold, SUM(ts_product_stocks.ps_qty) as stock, CONCAT(ts_products.p_name,br_id) product_brand')
        ->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
        ->leftJoin('product_categories', 'product_categories.id', '=', 'products.pc_id')
		->leftJoin('product_sub_categories', 'product_sub_categories.id', '=', 'products.psc_id')
		->leftJoin('product_sub_sub_categories', 'product_sub_sub_categories.id', '=', 'products.pssc_id')
        ->leftJoin('brands', 'brands.id', '=', 'products.br_id')
        ->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
        // cek stok
		->where('product_stocks.ps_qty', '>', '0')
		->where('p_delete', '=', '0')
		->whereNotNull('p_main_image')
		->where(function($w) use($sub_kat, $subsub_kat, $brand, $product_name) {
			if (!empty($sub_kat)) {
				$sub_details = explode(",",str_replace(", ",",",$sub_kat));
				$w->whereIn('product_sub_categories.psc_name', $sub_details);
			}
			if (!empty($subsub_kat)) {
				$subsub_details = explode(",",str_replace(", ",",",$subsub_kat));
				$w->whereIn('product_sub_sub_categories.pssc_name', $subsub_details);
			}
			if (!empty($product_name)) {
				$arr_product_name = explode(" ",$product_name);
				foreach($arr_product_name as $nama_produk){
					//$w->where(DB::raw("CONCAT(p_name, ' ', br_name, ' ',psc_name,' ',pssc_name,' ',p_description, ' ',p_hashtag)"), 'LIKE', "%$nama_produk%");
					$w->where(DB::raw("CONCAT(
					    COALESCE(p_name, ''), ' ',
                        COALESCE(br_name, ''), ' ',
                        COALESCE(psc_name, ''), ' ',
                        COALESCE(pssc_name, ''), ' ',
                        COALESCE(p_description, ''), ' ',
                        COALESCE(p_hashtag, '')
					)"), 'LIKE', "%$nama_produk%");
				}
			}
			if (!empty($brand)) {
				$brand_details = explode(",",str_replace(", ",",",$brand));
				$w->whereIn('brands.br_name', $brand_details);
			}
		})
		//->groupBy('products.p_name')
		->groupBy('product_brand')
		->orderBy($sort_orders,'DESC')
		->limit('5')
        ->get();
		
        $results = array();
        if (!empty($stock)) {
            foreach ($stock as $row) {
                $price = null;
                $price_tag = null;
                $store = null;
                $image_url = $this->url_server."api/product/600/";
                $image = \App\Helpers\ProductImageHelper::parseImages($row->p_main_image, $row->p_image);
                if (!empty($row->ps_sell_price)) {
                    $price = $row->ps_sell_price;
                } else {
                    $price = $row->p_sell_price;
                }
                if (!empty($row->ps_price_tag)) {
                    $price_tag = $row->ps_price_tag;
                } else {
                    $price_tag = $row->p_price_tag;
                }
                $check_discount = DB::table('product_discounts')->select('pd_type', 'pd_value')
                    ->leftJoin('product_discount_details', 'product_discount_details.pd_id', '=', 'product_discounts.id')
                    ->where('product_discounts.std_id', '=', '9')
                    ->where('product_discount_details.pst_id', '=', $row->pid)
                    //->where('product_discounts.pd_date', '>=', date('Y-m-d'))
                    ->where('pd_start', '<=', date("Y-m-d"))
    				->where('pd_date', '>=', date("Y-m-d"))
    				->where('pd_start_time', '<=', date("H:i:s"))
    				->where('pd_end_time', '>=', date("H:i:s"))
                    ->get()->first();
                if (!empty($check_discount)) {
                  $cd = $check_discount;
                  $discount = 0;
                  if ($cd->pd_type == 'percent') {
                    $discount = $price_tag - (($price_tag/100) * $cd->pd_value);
                  } else if ($cd->pd_type == 'amount') {
                    $discount = $price_tag - $cd->pd_value;
                  } else {
                    $discount = $price_tag;
                  }
                  $price = $discount;
                }
				
				// persentase potongan harga
				if($row->p_price_tag!=$row->p_sell_price && $row->p_price_tag!='' && $row->p_sell_price!=''){
					$persen_potongan = (($row->p_price_tag - $row->p_sell_price) / $row->p_price_tag) * 100;
				} else {
					$persen_potongan = 0;
				}
				
				// cek diskon aktif apps
				$arr_promo = DB::table("product_discount_details")
				->select('product_discount_details.id', 'product_discount_details.pst_id', 'pd_value', 'pd_type')
				->leftJoin('product_discounts', 'product_discounts.id', '=', 'product_discount_details.pd_id')
				->leftJoin('product_stocks', 'product_stocks.id', '=', 'product_discount_details.pst_id')
				->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
				->where('std_id', '=', '20') // kusus apps
				//->where('pd_date', '>=', date("Y-m-d"))
				->where('pd_start', '<=', date("Y-m-d"))
				->where('pd_date', '>=', date("Y-m-d"))
				->where('pd_start_time', '<=', date("H:i:s"))
				->where('pd_end_time', '>=', date("H:i:s"))
				->where('products.p_name', '=', $row->article)
				->where('products.p_delete', '=', '0')
				->first();
				
				$price_tag = $row->p_price_tag; // harga bandrol
				$sell_price = $row->p_sell_price; // harga jual
				// cek diskon
				if($arr_promo){
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
					
					if($price_tag!=$sell_price){
						$persen_potongan = 100 - (($sell_price / $price_tag) * 100);
					} else {
						$persen_potongan = 0;
					}
				}
				/*
                $results[] = [
                    'product_id' => $row->pid,
                    'product_category' => $row->category,
                    'product_brand' => $row->brand,
                    'product_sub_cat' => $row->psc_name,
                    'product_subsub_cat' => $row->pssc_name,
                    'product_article' => $row->article,
                    'product_stock' => $row->stock,
					'product_price_tag' => $price_tag,
					'product_sell_price' => $sell_price,
					'product_discount' => round($persen_potongan,2),
					'product_ratting' => 0, //$ratting,
                    'product_image_url' => $image_url,
                    'product_image' => $image,
                ];
				*/
				$arr_wishlist = DB::table("wishlists")
					->where('pst_id', $row->pid)
					->where('cust_id', $cust_id)
					->where('ws_delete', '=', '0')
					->first();
				if($arr_wishlist){$flag_wishlist = 1;} else {$flag_wishlist=0;}
				// ******* //
				
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
				->orderBy('products.created_at','DESC')
				->get();
				
				$image = array();
				foreach($stock_color as $row_color){
					$img = \App\Helpers\ProductImageHelper::parseSingleImage($row_color->p_main_image, $row_color->p_image);
					if (!empty($img)) {
						$image[] = $img;
					}
				}
				$image_url = "https://topsystem.id/api/product/600/";
				
				// cek customers
				$add_persen = 0;
				if($cust_id){
					$arr_customer = DB::table("customers")->where("id","=",$cust_id)->first();
					if($arr_customer){
						// jika sebagai reseller
						if($arr_customer->cust_reseller==1){
							// kusus brand topscore {$row->brand}
							if($row->brand == "TOPSCORE"){
								//$add_persen = 0;
								//$persen_potongan = 20;
								//$sell_price_persen = ($price_tag * ($persen_potongan/100)); // default reseller 20%
								$add_persen = 20;
								$persen_potongan = 0;
								$sell_price_persen = ($price_tag * ($add_persen/100)); // default reseller 20%
								$sell_price = $price_tag - $sell_price_persen;
							} else {
								$add_persen = 5;
								$sell_price_persen = ($sell_price * ($add_persen/100)); // default reseller 5%
								$new_sell_price = $sell_price - $sell_price_persen;
								$sell_price = round($new_sell_price);
							}
							
						}
					}
				} else {
					$persen_potongan = $persen_potongan;
					$add_persen = 0;
				}
				
				if($row->brand == "TOPSCORE"){$flag_sold=0;} else {$flag_sold=1;}
                $results[] = [
                    'product_id' => $row->pid,
                    'category' => $row->category,
                    'brand' => $row->brand,
                    'sub_cat' => $row->psc_name,
                    'subsub_cat' => $row->pssc_name,
                    'article' => $row->article,
                    'stock' => $row->stock,
					'price_tag' => $price_tag,
					'sell_price' => $sell_price,
					'discount' => round($persen_potongan),
					'additional_discount' => round($add_persen),
					'sold' => $row->sold,
					'ratting' => 0, //$ratting,
					'total_ulasan' => 0,
					'wishlist' => $flag_wishlist,
					'jumlah_warna' => count($stock_color),
                    'image_url' => $image_url,
                    'image' => $image,
                    'flag_sold' => $flag_sold,
                ];

            }

        }
        return $results;
    }
	
	// cek event
	private function CekEvent()
    {
		$type_event = 'upcomming';
        $query_data = DB::table("event_tops")
		->selectRaw('ts_event_tops.id as pid, et_title, et_start_date, et_start_time, et_end_date, et_end_time, et_address, et_banner, et_price, et_terms, et_description, et_quota, et_lat, et_lang, et_type, et_link_online, et_score')
		->where('et_delete', '=', '0')
		->whereRaw("CONCAT(ts_event_tops.et_start_date,' ',ts_event_tops.et_start_time) >= '".date('Y-m-d H:i:s')."'")
		->orderBy('et_end_date')
		->limit(3)
        ->get();
        $results = array();
        if (!empty($query_data)) {
            foreach ($query_data as $row) {
                $event_users = DB::table("event_orders")
							   ->where("et_id","=",$row->pid)
							   ->where("payment_status","=","1")
							   ->sum('eo_qty');
				if($event_users>0){
					$jumlah_user = (int)$event_users;
				} else {
					$jumlah_user = 0;
				}
				
				if(empty($row->et_quota)){$quota=0;} else {$quota=$row->et_quota;}
				
				if($type_event == "upcomming"){
				    $jumlah_user = $jumlah_user;
				    $quota = $quota;
				} else
				if($type_event == "berlangsung"){
				    $jumlah_user = 0;
				    $quota = 0;
				} else {
				    $jumlah_user = 0;
				    $quota = 0;
				}
				
				// data additional form
                $add_form = DB::table("event_master_forms")
							->where("et_id","=",$row->pid)
							->where("emf_delete","=","0")
							->orderBy('emf_title')
							->get();
				$array_form = array();
				$array_form_sub = array();
				/*
				if($add_form){
					foreach($add_form as $rform){
						$array_form_sub = array();
						if(!empty($rform->emf_option)){
							$emf_option = explode(", ",$rform->emf_option);
						} else { $emf_option=array(); }
						$array_form []= [
							'form_id' => $rform->id,
							'form_title' => $rform->emf_title,
							'form_type' => $rform->emf_type,
							'form_option' => $emf_option,
							'form_required' => $rform->emf_required,
						];
						$array_form_sub = $array_form;
					}
				}
				*/
				if($row->et_type == "online"){
					$link_webinar = $row->et_link_online;
				} else {
					$link_webinar = "";
				}
				
				// cek jika event coming soon
				$event_coming = '0';
				$start_date_check = $row->et_start_date.' '.$row->et_start_time;
				$end_date_check = date("Y-m-d H:i:s", strtotime('-3 days', strtotime($start_date_check)));
				if($end_date_check<=date("Y-m-d H:i:s")){$event_coming=0;} else {$event_coming=1;}
				
                $results[] = [
					'event_id' => $row->pid,
					'event_type' => $row->et_type,
					'event_title' => $row->et_title,
					'event_start_date' => $row->et_start_date,
					'event_start_time' => $row->et_start_time,
					'event_end_date' => $row->et_end_date,
					'event_end_time' => $row->et_end_time,
					'event_address' => $row->et_address,
					'event_banner' => $this->url_server."upload/event/".$row->et_banner,
					'event_price' => $row->et_price,
					'event_quota' => $quota,
					'event_user_order' => $jumlah_user,
					'event_terms' => $row->et_terms,
					'event_description' => $row->et_description,
					'event_lat' => $row->et_lat,
					'event_lang' => $row->et_lang,
					'event_score' => $row->et_score,
					//'additional_form' => $array_form_sub,
					'event_coming_soon' => $event_coming,
					'event_open_reg' => $end_date_check,
                ];
            }

        }
		
        return $results;
    }
	
	// get blog
	private function getBlog()
    {
        //->get();
		$query_data = DB::table('blog_contents')
		->select('blog_contents.id', 'bct_title', 'bct_slug', 'bct_image', 'bct_content', 'bct_keywords', 'bct_views', 'blog_contents.created_at', 'bc_name', 'bc_slug', 'u_name', 'bc_id')
        ->leftJoin('blog_categories', 'blog_categories.id', '=', 'blog_contents.bc_id')
		->leftJoin('users', 'users.id', '=', 'blog_contents.u_id')
        ->orderBy('blog_contents.created_at','DESC')
		->limit(3)
		->get();
        $results = array();
        if (!empty($query_data)) {
            foreach ($query_data as $row) {
                if (!empty($row->bct_image)) {
                    $image_url = $this->url_server."api/blog/600/".$row->bct_image;
                } else {
					$image_url = '';
				}
                $results[] = [
                    'blog_id' => $row->id,
					'blog_category' => $row->bc_name,
					'blog_title' => $row->bct_title,
					'blog_slug' => $row->bct_slug,
					'blog_content' => $row->bct_content,
					'blog_view' => $row->bct_views,
                    'blog_image' => $image_url,
					'blog_admin' => $row->u_name,
					'blog_date' => $row->created_at,
                ];
            }

        }
		
        return $results;
    }
	
	// get forum
	private function getForum($cust_id)
    {
		$query_data = DB::table('forums')
		->select('forums.id', 'fo_name', 'fo_slug', 'fo_description', 'fo_image', 'fo_view', 'fo_active', 'forums.created_at', 'u_name')
		->leftJoin('users', 'users.id', '=', 'forums.user_input')
		->where('forums.fo_active', '=', "1")
		->where('forums.fo_delete', '=', "0")
        ->orderBy("forums.created_at",'DESC')
		//->paginate(5);
		->limit(3)
		->get();
        $results = array();
        if (!empty($query_data)) {
            foreach ($query_data as $row) {
                if (!empty($row->fo_image)) {
					$image_url = $this->url_server."upload/forum/".$row->fo_image;
                } else {
					$image_url = '';
				}
                
				// cek join
				$join_cek = DB::table("forum_joins")->where("cust_id","=",$cust_id)->where("fo_id","=",$row->id)->first();
				if($join_cek){$flag_join = 1;} else {$flag_join = 0;}
				
                $results[] = [
                    'forum_id' => $row->id,
					'forum_name' => $row->fo_name,
					'forum_slug' => $row->fo_slug,
					'forum_description' => $row->fo_description,
					'forum_view' => (int)$row->fo_view,
                    'forum_image' => $image_url,
					'forum_admin' => $row->u_name,
					'forum_join' => $flag_join,
					'forum_date' => $row->created_at,
                ];
            }

        }
		
        return $results;
    }
	
	
	// get games
	private function getGames($cust_id)
    {
		$query = DB::table('games')
		->select('games.id', 'gm_name', 'gm_slug', 'gm_description', 'gm_image', 'gm_view', 'gm_time', 'gm_active', 'games.created_at', 'u_name', 'gm_score')
		->leftJoin('users', 'users.id', '=', 'games.user_input')
		->where('games.gm_active', '=', "1")
		->where('games.gm_delete', '=', "0")
		->orderBy("games.created_at",'DESC')
		//->paginate(5);
		->limit(3)
		->get();
		
        $results = array();
		if (!empty($query)) {
            foreach ($query as $row) {
				$cek_usergame = DB::table("game_users")->where("gm_id","=",$row->id)->where("cust_id","=",$cust_id)->first();
				if(empty($cek_usergame)){
				
                if (!empty($row->gm_image)) {
					$image_url = $this->url_server."upload/game/".$row->gm_image;
                } else {
					$image_url = '';
				}

					// tampilkan detail games *****
					$query_det = DB::table('game_details')
					->select('game_details.id', 'gmd_description', 'gmd_image', 'gmd_option_1', 'gmd_option_2', 'gmd_option_3', 'gmd_option_4', 'gmd_option_5', 'gmd_true', 'game_details.created_at')
					->leftJoin('users', 'users.id', '=', 'game_details.user_input')
					->where('gm_id', '=', $row->id)
					->where('gmd_delete', '=', "0")
					->orderBy("game_details.created_at",'ASC')
					->get();
					$result_details = array();
					/*
					foreach($query_det as $row_detail){
						if (!empty($row_detail->gmd_image)) {
							$image_url_det = $this->url_server."api/blog/600/".$row_detail->gmd_image;
						} else {
							$image_url_det = '';
						}
						$result_details[] = [
							'gmdetail_id' => $row_detail->id,
							'gmdetail_name' => $row_detail->gmd_description,
							'gmdetail_option_1' => $row_detail->gmd_option_1,
							'gmdetail_option_2' => $row_detail->gmd_option_2,
							'gmdetail_option_3' => $row_detail->gmd_option_3,
							'gmdetail_option_4' => $row_detail->gmd_option_4,
							'gmdetail_option_5' => $row_detail->gmd_option_5,
							'gmdetail_true' => $row_detail->gmd_true,
							'gmdetail_image' => $image_url_det,
							//'games_date' => $row_detail->created_at,
						];
					}
					*/

                $results[] = [
                    'games_id' => $row->id,
					'games_name' => $row->gm_name,
					'games_slug' => $row->gm_slug,
					'games_description' => $row->gm_description,
					'games_time' => (int)$row->gm_time,
					'games_view' => (int)$row->gm_view,
					'games_score' => (int)$row->gm_score,
                    'games_image' => $image_url,
					'games_admin' => $row->u_name,
					'games_date' => $row->created_at,
					//'games_detail' => $result_details,
                ];
				}
			
			}
        }
		
        return $results;
    }
	
	// cek promo
	private function getPromo()
    {
		$query = DB::table("product_discounts")
		->select('product_discounts.id as pid', 'product_discounts.created_at', 'st_id', 'std_id', 'pd_name', 'pd_type', 'pd_value', 'pd_date', 'no_mom', 'pd_banner')
		->where('std_id', '=', '20') // kusus apps
		->where('pd_date', '>=', date("Y-m-d"))
		->where('pd_start', '<=', date("Y-m-d"))
		->where('pd_start_time', '<=', date("H:i:s"))
		->where('pd_end_time', '>=', date("H:i:s"))
		->orderBy('id','DESC')
		->limit(3)
		->get();
		
        $results = array();
		if (!empty($query)) {
            foreach ($query as $row) {
				$results[] = [
					'promo_id' => $row->pid,
					'promo_name' => $row->pd_name,
					'promo_type' => $row->pd_type,
					'promo_value' => $row->pd_value,
					'promo_expired' => $row->pd_date,
					'promo_banner' => $this->url_server.'public/upload/promo/'.$row->pd_banner,
					'promo_create' => $row->created_at,
                ];
			}
        }
		
        return $results;
    }
	
	
	// tampilkan list pencarian artikel produk
	public function searchProduct(Request $request)
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

        $exception = DB::table('exception_locations')->select('pl_code')
        ->leftJoin('product_locations', 'product_locations.id', '=', 'exception_locations.pl_id')->get();
        $exc_location = array();
        foreach ($exception as $exc) {
            $exc_location[] = $exc->pl_code;
        }
		
		$sub_kat = "";
		$subsub_kat = "";
		$brand = "";
		$product_name = $request->product_name;
		// jika produk name kosong
		if(empty($product_name)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data product_name tidak boleh kosong'
                ]
            ];
            return response()->json($data);
		}
		
		
		$sorting_artikel  = "baru";
		if(!empty($sorting_artikel)){
			if($sorting_artikel == "baru"){
				//$sort_orders = 'purchase_order_article_details.id';
				$sort_orders = 'products.created_at';
			} else 
			if($sorting_artikel == "terlaris"){
				$sort_orders = 'products.sold';
			} else 
			if($sorting_artikel == "dilihat"){
				$sort_orders = 'products.sold';
			} else {
				$sort_orders = 'products.sold';
			}
		} else {
			$sort_orders = 'products.sold';
		}
		
        $stock = DB::table('product_stocks')
        ->selectRaw('ts_product_stocks.id as pid, pc_name as category, br_name as brand, br_id, p_name as article, p_color as color, sz_name as size, ps_price_tag, p_price_tag, ps_sell_price, p_sell_price, p_main_image, p_image, psc_name, pssc_name, sold, SUM(ts_product_location_setups.pls_qty) as stock, CONCAT(ts_products.p_name,br_id) product_brand, p_id')
        ->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
        ->leftJoin('product_categories', 'product_categories.id', '=', 'products.pc_id')
		->leftJoin('product_sub_categories', 'product_sub_categories.id', '=', 'products.psc_id')
		->leftJoin('product_sub_sub_categories', 'product_sub_sub_categories.id', '=', 'products.pssc_id')
        ->leftJoin('brands', 'brands.id', '=', 'products.br_id')
        ->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
        // cek stok
		->leftJoin('product_location_setups', 'product_location_setups.pst_id', '=', 'product_stocks.id')
        ->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
        ->whereNotIn('pl_code', $exc_location)
        //->whereIn('product_locations.st_id', ['1'])
		->whereNotIn('product_locations.st_id', array('3', '4', '5', '10', '11', '15'))
		->where('product_location_setups.pls_qty', '>', '0')
		->where('p_delete', '=', '0')
		->whereNotNull('p_main_image')
		->where(function($w) use($sub_kat, $subsub_kat, $brand, $product_name) {
			if (!empty($sub_kat)) {
				$sub_details = explode(",",str_replace(", ",",",$sub_kat));
				$w->whereIn('product_sub_categories.psc_name', $sub_details);
			}
			if (!empty($subsub_kat)) {
				$subsub_details = explode(",",str_replace(", ",",",$subsub_kat));
				$w->whereIn('product_sub_sub_categories.pssc_name', $subsub_details);
			}
			if (!empty($product_name)) {
				$arr_product_name = explode(" ",$product_name);
				foreach($arr_product_name as $nama_produk){
					$w->where(DB::raw("CONCAT(p_name, ' ', br_name, ' ',psc_name,' ',pssc_name,' ',br_name)"), 'LIKE', "%$nama_produk%");
				}
			}
			if (!empty($brand)) {
				$brand_details = explode(",",str_replace(", ",",",$brand));
				$w->whereIn('brands.br_name', $brand_details);
			}
		})
		//->groupBy('products.p_name')
		->groupBy('product_brand')
		->orderBy($sort_orders,'DESC')
		//->limit('10')
        //->get();
		->paginate(10);
        $results = array();
        if (!empty($stock)) {
            foreach ($stock as $row) {
                $price = null;
                $price_tag = null;
                $image_url = null;
                $store = null;
                $image_url = $this->url_server."api/product/600/";
                $image = \App\Helpers\ProductImageHelper::parseImages($row->p_main_image, $row->p_image);
                if (!empty($row->ps_sell_price)) {
                    $price = $row->ps_sell_price;
                } else {
                    $price = $row->p_sell_price;
                }
                if (!empty($row->ps_price_tag)) {
                    $price_tag = $row->ps_price_tag;
                } else {
                    $price_tag = $row->p_price_tag;
                }
                $check_discount = DB::table('product_discounts')->select('pd_type', 'pd_value')
                    ->leftJoin('product_discount_details', 'product_discount_details.pd_id', '=', 'product_discounts.id')
                    ->where('product_discounts.std_id', '=', '9')
                    ->where('product_discount_details.pst_id', '=', $row->pid)
                    //->where('product_discounts.pd_date', '>=', date('Y-m-d'))
                    ->where('pd_start', '<=', date("Y-m-d"))
    				->where('pd_date', '>=', date("Y-m-d"))
    				->where('pd_start_time', '<=', date("H:i:s"))
    				->where('pd_end_time', '>=', date("H:i:s"))
                    ->get()->first();
                if (!empty($check_discount)) {
                  $cd = $check_discount;
                  $discount = 0;
                  if ($cd->pd_type == 'percent') {
                    $discount = $price_tag - (($price_tag/100) * $cd->pd_value);
                  } else if ($cd->pd_type == 'amount') {
                    $discount = $price_tag - $cd->pd_value;
                  } else {
                    $discount = $price_tag;
                  }
                  $price = $discount;
                }
				
				// persentase potongan harga
				if($row->p_price_tag!=$row->p_sell_price && $row->p_price_tag!='' && $row->p_sell_price!=''){
					$persen_potongan = (($row->p_price_tag - $row->p_sell_price) / $row->p_price_tag) * 100;
				} else {
					$persen_potongan = 0;
				}
				
				// cek diskon aktif apps
				$arr_promo = DB::table("product_discount_details")
				->select('product_discount_details.id', 'product_discount_details.pst_id', 'pd_value', 'pd_type')
				->leftJoin('product_discounts', 'product_discounts.id', '=', 'product_discount_details.pd_id')
				->leftJoin('product_stocks', 'product_stocks.id', '=', 'product_discount_details.pst_id')
				->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
				->where('std_id', '=', '20') // kusus apps
				//->where('pd_date', '>=', date("Y-m-d"))
				->where('pd_start', '<=', date("Y-m-d"))
				->where('pd_date', '>=', date("Y-m-d"))
				->where('pd_start_time', '<=', date("H:i:s"))
				->where('pd_end_time', '>=', date("H:i:s"))
				->where('products.p_name', '=', $row->article)
				->where('products.p_delete', '=', '0')
				->first();
				
				$price_tag = $row->p_price_tag; // harga bandrol
				$sell_price = $row->p_sell_price; // harga jual
				// cek diskon
				if($arr_promo){
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
					
					if($price_tag!=$sell_price){
						$persen_potongan = 100 - (($sell_price / $price_tag) * 100);
					} else {
						$persen_potongan = 0;
					}
				}
				/*
                $results[] = [
                    'product_id' => $row->pid,
                    'product_category' => $row->category,
                    'product_brand' => $row->brand,
                    'product_sub_cat' => $row->psc_name,
                    'product_subsub_cat' => $row->pssc_name,
                    'product_article' => $row->article,
                    'product_stock' => $row->stock,
					'product_price_tag' => $price_tag,
					'product_sell_price' => $sell_price,
					'product_discount' => round($persen_potongan,2),
					'product_ratting' => 0, //$ratting,
                    'product_image_url' => $image_url,
                    'product_image' => $image,
                ];
				*/
				$arr_wishlist = DB::table("wishlists")
					->where('pst_id', $row->pid)
					->where('cust_id', $cust_id)
					->where('ws_delete', '=', '0')
					->first();
				if($arr_wishlist){$flag_wishlist = 1;} else {$flag_wishlist=0;}
				// ******* //
				
				// tampilkan jumlah warna tersedia
				$stock_color = DB::table('product_stocks')
				->selectRaw("ts_product_stocks.id as pid, pc_name as category, br_name as brand, p_name as article, p_color as color, sz_name as size, ps_price_tag, p_price_tag, ps_sell_price, p_sell_price, p_main_image, p_image, psc_name, pssc_name, sold, SUM(ts_product_location_setups.pls_qty) as stock, CONCAT(p_name,' (',br_name,')') as p_name_brand, CONCAT(p_name,' (',p_color,')',br_id) as p_name_color")
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
				->where('br_id', '=', $row->br_id)
				->groupBy('p_name_color')
				//->orderBy('product_stocks.id','DESC')
				->orderBy('products.created_at','DESC')
				->get();
				
				$image = array();
				foreach($stock_color as $row_color){
					$img = \App\Helpers\ProductImageHelper::parseSingleImage($row_color->p_main_image, $row_color->p_image);
					if (!empty($img)) {
						$image[] = $img;
					}
				}
				$image_url = "https://topsystem.id/api/product/600/";
				
				// cek IPB RUN
				$jersey_ipb = 0;
				if(in_array($row->p_id,['13797','13798','13799','13800'])){ $jersey_ipb++; }

				// cek customers
				$add_persen = 0;
				if($cust_id && $jersey_ipb==0){
					$arr_customer = DB::table("customers")->where("id","=",$cust_id)->first();
					if($arr_customer){
						// jika sebagai reseller
						if($arr_customer->cust_reseller==1){
							// kusus brand topscore {$row->brand}
							if($row->brand == "TOPSCORE"){
								//$add_persen = 0;
								//$persen_potongan = 20;
								//$sell_price_persen = ($price_tag * ($persen_potongan/100)); // default reseller 20%
								$add_persen = 20;
								$persen_potongan = 0;
								$sell_price_persen = ($price_tag * ($add_persen/100)); // default reseller 20%
								$sell_price = $price_tag - $sell_price_persen;
							} else {
								$add_persen = 5;
								$sell_price_persen = ($sell_price * ($add_persen/100)); // default reseller 5%
								$new_sell_price = $sell_price - $sell_price_persen;
								$sell_price = round($new_sell_price);
							}
							
						}
					}
				} else {
					$persen_potongan = $persen_potongan;
					$add_persen = 0;
				}
				
				if($row->brand == "TOPSCORE"){$flag_sold=0;} else {$flag_sold=1;}
                $results[] = [
                    'product_id' => $row->pid,
                    'category' => $row->category,
                    'brand' => $row->brand,
                    'sub_cat' => $row->psc_name,
                    'subsub_cat' => $row->pssc_name,
                    'article' => $row->article,
                    'stock' => $row->stock,
					'price_tag' => $price_tag,
					'sell_price' => $sell_price,
					'discount' => round($persen_potongan),
					'additional_discount' => round($add_persen),
					'sold' => $row->sold,
					'ratting' => 0, //$ratting,
					'total_ulasan' => 0,
					'wishlist' => $flag_wishlist,
					'jumlah_warna' => count($stock_color),
                    'image_url' => $image_url,
                    'image' => $image,
                    'flag_sold' => $flag_sold,
                ];

            }

        }
		
		// search event ***
		$type_event = 'upcomming';
        $query_event = DB::table("event_tops")
		->selectRaw('ts_event_tops.id as pid, et_title, et_start_date, et_start_time, et_end_date, et_end_time, et_address, et_banner, et_price, et_terms, et_description, et_quota, et_lat, et_lang, et_type, et_link_online, et_score')
		->where('et_delete', '=', '0')
		->whereRaw("CONCAT(ts_event_tops.et_start_date,' ',ts_event_tops.et_start_time) >= '".date('Y-m-d H:i:s')."'")
		->where(function($w) use($product_name) {
			if (!empty($product_name)) {
				$arr_product_name = explode(" ",$product_name);
				foreach($arr_product_name as $nama_produk){
					$w->where(DB::raw("CONCAT(et_title)"), 'LIKE', "%$nama_produk%");
				}
			}
		})
		->orderBy('et_end_date')
		->limit(3)
        ->get();
        $result_event = array();
        if (!empty($query_event)) {
            foreach ($query_event as $row) {
                $event_users = DB::table("event_orders")
							   ->where("et_id","=",$row->pid)
							   ->where("payment_status","=","1")
							   ->sum('eo_qty');
				if($event_users>0){
					$jumlah_user = (int)$event_users;
				} else {
					$jumlah_user = 0;
				}
				
				if(empty($row->et_quota)){$quota=0;} else {$quota=$row->et_quota;}
				
				if($type_event == "upcomming"){
				    $jumlah_user = $jumlah_user;
				    $quota = $quota;
				} else
				if($type_event == "berlangsung"){
				    $jumlah_user = 0;
				    $quota = 0;
				} else {
				    $jumlah_user = 0;
				    $quota = 0;
				}
				
				if($row->et_type == "online"){
					$link_webinar = $row->et_link_online;
				} else {
					$link_webinar = "";
				}
				
				// cek jika event coming soon
				$event_coming = '0';
				$start_date_check = $row->et_start_date.' '.$row->et_start_time;
				$end_date_check = date("Y-m-d H:i:s", strtotime('-3 days', strtotime($start_date_check)));
				if($end_date_check<=date("Y-m-d H:i:s")){$event_coming=0;} else {$event_coming=1;}
				
                $result_event[] = [
					'event_id' => $row->pid,
					'event_type' => $row->et_type,
					'event_title' => $row->et_title,
					'event_start_date' => $row->et_start_date,
					'event_start_time' => $row->et_start_time,
					'event_end_date' => $row->et_end_date,
					'event_end_time' => $row->et_end_time,
					'event_address' => $row->et_address,
					'event_banner' => $this->url_server."upload/event/".$row->et_banner,
					'event_price' => $row->et_price,
					'event_quota' => $quota,
					'event_user_order' => $jumlah_user,
					'event_terms' => $row->et_terms,
					'event_description' => $row->et_description,
					'event_lat' => $row->et_lat,
					'event_lang' => $row->et_lang,
					'event_score' => $row->et_score,
					'event_coming_soon' => $event_coming,
					'event_open_reg' => $end_date_check,
                ];
            }

        }
		
		// search blog
		$query_blog = DB::table('blog_contents')
		->select('blog_contents.id', 'bct_title', 'bct_slug', 'bct_image', 'bct_content', 'bct_keywords', 'bct_views', 'blog_contents.created_at', 'bc_name', 'bc_slug', 'u_name', 'bc_id')
        ->leftJoin('blog_categories', 'blog_categories.id', '=', 'blog_contents.bc_id')
		->leftJoin('users', 'users.id', '=', 'blog_contents.u_id')
        ->orderBy('blog_contents.created_at','DESC')
		->where(function($w) use($product_name) {
			if (!empty($product_name)) {
				$arr_product_name = explode(" ",$product_name);
				foreach($arr_product_name as $nama_produk){
					$w->where(DB::raw("CONCAT(bct_title)"), 'LIKE', "%$nama_produk%");
				}
			}
		})
		->limit(3)
		->get();
        $result_blog = array();
        if (!empty($query_blog)) {
            foreach ($query_blog as $row) {
                if (!empty($row->bct_image)) {
                    $image_url = $this->url_server."api/blog/600/".$row->bct_image;
                } else {
					$image_url = '';
				}
                $result_blog[] = [
                    'blog_id' => $row->id,
					'blog_category' => $row->bc_name,
					'blog_title' => $row->bct_title,
					'blog_slug' => $row->bct_slug,
					//'blog_content' => $row->bct_content,
					'blog_view' => $row->bct_views,
                    'blog_image' => $image_url,
					'blog_admin' => $row->u_name,
					'blog_date' => $row->created_at,
                ];
            }

        }

		
        //return $results;
		// if(count($stock)>0 || count($query_event)>0 || count($query_blog)>0){
		if(count($results)>0 || count($result_event)>0 || count($result_blog)>0){
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success'
				],
				'search_data' => [
					'product' => $results,
					'event' => $result_event,
					'blog' => $result_blog,
				]
			];
		} else {
			$data = [
				'status' => [
					'code' => 404,
					'description' => 'Data tidak ditemukan'
				],
				/*
				'search_data' => [
					'product' => $results,
					'event' => $result_event,
					'blog' => $result_blog,
				]
				*/
			];
		}
		return response()->json($data);

    }
    
    // search product by live keywords ***
	public function searchProductlive(Request $request)
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

		$sub_kat = "";
		$subsub_kat = "";
		$brand = "";
		$product_name = $request->product_name;
		// jika produk name kosong
		if(empty($product_name)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data product_name tidak boleh kosong'
                ]
            ];
            return response()->json($data);
		}
		
		
		$sorting_artikel  = "baru";
		if(!empty($sorting_artikel)){
			if($sorting_artikel == "baru"){
				$sort_orders = 'products.created_at';
			} else 
			if($sorting_artikel == "terlaris"){
				$sort_orders = 'products.sold';
			} else 
			if($sorting_artikel == "dilihat"){
				$sort_orders = 'products.sold';
			} else {
				$sort_orders = 'products.sold';
			}
		} else {
			$sort_orders = 'products.sold';
		}
		
        $stock = DB::table('product_stocks')->selectRaw('ts_product_stocks.id as pid, pc_name as category, br_name as brand, p_name as article, p_color as color, sz_name as size, ps_price_tag, p_price_tag, ps_sell_price, p_sell_price, p_main_image, p_image, psc_name, pssc_name, sold')
        ->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
        ->leftJoin('product_categories', 'product_categories.id', '=', 'products.pc_id')
		->leftJoin('product_sub_categories', 'product_sub_categories.id', '=', 'products.psc_id')
		->leftJoin('product_sub_sub_categories', 'product_sub_sub_categories.id', '=', 'products.pssc_id')
        ->leftJoin('brands', 'brands.id', '=', 'products.br_id')
        ->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
		->where('p_delete', '=', '0')
		->whereNotNull('p_main_image')
		->where(function($w) use($sub_kat, $subsub_kat, $brand, $product_name) {
			if (!empty($sub_kat)) {
				$sub_details = explode(",",str_replace(", ",",",$sub_kat));
				$w->whereIn('product_sub_categories.psc_name', $sub_details);
			}
			if (!empty($subsub_kat)) {
				$subsub_details = explode(",",str_replace(", ",",",$subsub_kat));
				$w->whereIn('product_sub_sub_categories.pssc_name', $subsub_details);
			}
			if (!empty($product_name)) {
				$arr_product_name = explode(" ",$product_name);
				foreach($arr_product_name as $nama_produk){
					$w->where(DB::raw("CONCAT(p_name, ' ', br_name, ' ',psc_name,' ',pssc_name,' ',br_name)"), 'LIKE', "%$nama_produk%");
				}
			}
			if (!empty($brand)) {
				$brand_details = explode(",",str_replace(", ",",",$brand));
				$w->whereIn('brands.br_name', $brand_details);
			}
		})

		->groupBy('products.p_name')
		->orderBy($sort_orders,'DESC')
		//->limit('10')
        //->get();
		->paginate(5);
        $results = array();
        if (!empty($stock)) {
            foreach ($stock as $row) {                
				$results[] = [
                    'product_id' => $row->pid,
                    //'category' => $row->category,
                    //'brand' => $row->brand,
                    //'sub_cat' => $row->psc_name,
                    //'subsub_cat' => $row->pssc_name,
                    'article' => $row->article,
                    /*
					'stock' => $row->stock,
					'price_tag' => $price_tag,
					'sell_price' => $sell_price,
					'discount' => round($persen_potongan),
					'sold' => $row->sold,
					'ratting' => 0, //$ratting,
					'total_ulasan' => 0,
					'wishlist' => $flag_wishlist,
					'jumlah_warna' => count($stock_color),
                    'image_url' => $image_url,
                    'image' => $image,
					*/
                ];

            }

        }
		
		// search event ***
		$type_event = 'upcomming';
        $query_event = DB::table("event_tops")
		->selectRaw('ts_event_tops.id as pid, et_title, et_start_date, et_start_time, et_end_date, et_end_time, et_address, et_banner, et_price, et_terms, et_description, et_quota, et_lat, et_lang, et_type, et_link_online, et_score')
		->where('et_delete', '=', '0')
		->whereRaw("CONCAT(ts_event_tops.et_start_date,' ',ts_event_tops.et_start_time) >= '".date('Y-m-d H:i:s')."'")
		->where(function($w) use($product_name) {
			if (!empty($product_name)) {
				$arr_product_name = explode(" ",$product_name);
				foreach($arr_product_name as $nama_produk){
					$w->where(DB::raw("CONCAT(et_title)"), 'LIKE', "%$nama_produk%");
				}
			}
		})
		->orderBy('et_end_date')
		->limit(3)
        ->get();
        $result_event = array();
        if (!empty($query_event)) {
            foreach ($query_event as $row) {
                $event_users = DB::table("event_orders")
							   ->where("et_id","=",$row->pid)
							   ->where("payment_status","=","1")
							   ->sum('eo_qty');
				if($event_users>0){
					$jumlah_user = (int)$event_users;
				} else {
					$jumlah_user = 0;
				}
				
				if(empty($row->et_quota)){$quota=0;} else {$quota=$row->et_quota;}
				
				if($type_event == "upcomming"){
				    $jumlah_user = $jumlah_user;
				    $quota = $quota;
				} else
				if($type_event == "berlangsung"){
				    $jumlah_user = 0;
				    $quota = 0;
				} else {
				    $jumlah_user = 0;
				    $quota = 0;
				}
				
				if($row->et_type == "online"){
					$link_webinar = $row->et_link_online;
				} else {
					$link_webinar = "";
				}
                $result_event[] = [
					'event_id' => $row->pid,
					//'event_type' => $row->et_type,
					'event_title' => $row->et_title,
					//'event_start_date' => $row->et_start_date,
					//'event_start_time' => $row->et_start_time,
					//'event_end_date' => $row->et_end_date,
					//'event_end_time' => $row->et_end_time,
					//'event_address' => $row->et_address,
					//'event_banner' => $this->url_server."upload/event/".$row->et_banner,
					//'event_price' => $row->et_price,
					//'event_quota' => $quota,
					//'event_user_order' => $jumlah_user,
					//'event_terms' => $row->et_terms,
					//'event_description' => $row->et_description,
					//'event_lat' => $row->et_lat,
					//'event_lang' => $row->et_lang,
					//'event_score' => $row->et_score,
                ];
            }

        }
		
		// search blog
		$query_blog = DB::table('blog_contents')
		->select('blog_contents.id', 'bct_title', 'bct_slug', 'bct_image', 'bct_content', 'bct_keywords', 'bct_views', 'blog_contents.created_at', 'bc_name', 'bc_slug', 'u_name', 'bc_id')
        ->leftJoin('blog_categories', 'blog_categories.id', '=', 'blog_contents.bc_id')
		->leftJoin('users', 'users.id', '=', 'blog_contents.u_id')
        ->orderBy('blog_contents.created_at','DESC')
		->where(function($w) use($product_name) {
			if (!empty($product_name)) {
				$arr_product_name = explode(" ",$product_name);
				foreach($arr_product_name as $nama_produk){
					$w->where(DB::raw("CONCAT(bct_title)"), 'LIKE', "%$nama_produk%");
				}
			}
		})
		->limit(3)
		->get();
        $result_blog = array();
        if (!empty($query_blog)) {
            foreach ($query_blog as $row) {
                if (!empty($row->bct_image)) {
                    $image_url = $this->url_server."api/blog/600/".$row->bct_image;
                } else {
					$image_url = '';
				}
                $result_blog[] = [
                    'blog_id' => $row->id,
					//'blog_category' => $row->bc_name,
					'blog_title' => $row->bct_title,
					//'blog_slug' => $row->bct_slug,
					//'blog_content' => $row->bct_content,
					//'blog_view' => $row->bct_views,
                    //'blog_image' => $image_url,
					//'blog_admin' => $row->u_name,
					//'blog_date' => $row->created_at,
                ];
            }

        }

		if(count($results)>0 || count($result_event)>0 || count($result_blog)>0){
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success'
				],
				'search_data' => [
					'product' => $results,
					'event' => $result_event,
					'blog' => $result_blog,
				]
			];
		} else {
			$data = [
				'status' => [
					'code' => 404,
					'description' => 'Data tidak ditemukan'
				],
			];
		}
		return response()->json($data);

    }
    
    // search produk trending (bulan ini )
	public function productTrending(Request $request)
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
		
		/*
		$products = DB::table('product_stocks')
			->select('product_stocks.id', 'p_name', 'br_name')
			->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
			->leftJoin('brands', 'brands.id', '=', 'products.br_id')
			->where('p_delete',"=","0")
			->groupBy('p_name')
			->orderBy("p_sold_month","DESC")
			->limit(10)
			->get();
		*/
		$products = DB::table('product_stocks')
			->select('product_stocks.id', 'p_name', 'br_name')
			->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
			->leftJoin('brands', 'brands.id', '=', 'products.br_id')
			->where('p_delete',"=","0")
			->where('ps_qty',">","0")
			->whereNotNull('p_main_image')
			->groupBy('p_name')
			->orderBy("p_sold_month","DESC")
			->limit(10)
			->get();
		$data_product = array();
		foreach($products as $row){
			$data_product[]=[
				'product_id' => $row->id,
				'brand' => $row->br_name,
				'article' => $row->p_name,
			];
		}
		
		$data = [
			'status' => [
				'code' => 200,
				'description' => 'data artikel yang lagi trending'
			],
			'artikel' => $data_product,
		];
		return response()->json($data);
	}
}
