<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
//use App\Models\Artikel;

class DashboardAppsController extends Controller
{
    public function getData(Request $request)
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
		if (empty($cust_id)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'cust_id tidak boleh kosong'
                ]
            ];
            return response()->json($data);
        }
		
		// tampilkan data user
		$raw_user = DB::table("customers")->where("id","=",$cust_id)->where("cust_delete","=","0")->first();
		if (empty($raw_user)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'customer tidak terdaftar'
                ]
            ];
            return response()->json($data);
        }
		
		// update cust_app_versions
		if(!empty($request->version) && $raw_user->cust_app_versions != $request->version){
			$updated_version = [
				'cust_app_versions' => $request->version,
			];
			DB::table("customers")->where('id', $raw_user->id)->update($updated_version);
		}
		
		// update token
		if($request->cust_token!=''){
			$data_token = ['cust_token_id' => $request->cust_token];
			$save_update = DB::table("customers")->where('id', $raw_user->id)->update($data_token);
		}
		
		if(empty($raw_user->cust_avatar)){
			if($raw_user->cust_gender == 'm'){
				$avatar_profile = "https://www.topsystem.id/upload/avatar/AV1.png";
			} else {
				$avatar_profile = "https://www.topsystem.id/upload/avatar/AV51.png";
			}
		} else {
			$avatar_profile = $raw_user->cust_avatar;
		}
		
		// cek kelengkapan data customers
		if($raw_user->cust_name!='' && $raw_user->cust_phone!='' && $raw_user->cust_email!='' && $raw_user->cust_bday && $raw_user->cust_gender!=''){
			$full_cust = '1';
		} else {
			$full_cust = '0';
		}
		
		$icon_level = url('public/upload/badge_member.png');
		$join_log=array();
		// cek referal code 
		//if($raw_user->cust_referal_code && $raw_user->cust_flag_referal==1){$refcode=$raw_user->cust_referal_code;} else {$refcode='';}
		if($raw_user->cust_referal_code && $raw_user->cust_flag_referal==1){
			$refcode=$raw_user->cust_referal_code;
			// cek jumlah cuser yang menggunakan referal
			//$jumlah_referal = DB::table("customer_referals")->where("cust_id","=",$raw_user->id)->count();
			$sql_referal = DB::table("customer_referals")->where("cust_id","=",$raw_user->id)->get();
			$jumlah_referal = count($sql_referal);
			if($sql_referal){
				foreach($sql_referal as $r_ref){
					$join_log[] = array(
						'join_member_date' => $r_ref->created_at,
						'join_member_description' => 'user baru menggunakan kode referal anda',
					);
				}
			}
		} else {
			$refcode=''; 
			$jumlah_referal=0;
		}
		
		$member_referal = DB::table("customer_referals")->where("cust_id_ref","=",$raw_user->id)->first();
		if($member_referal){$refcode_member=$member_referal->cf_code;} else {$refcode_member='';}
		
		// peringkat users
		$q_ranking_all = DB::table('customers')
		->select('customers.id', 'cust_name', 'cust_score', 'cust_coin')
		->where('cust_phone_verify', '=', '1')
		//->where('cust_score', '>=', $raw_user->cust_score)
		->where('cust_score', '>', $raw_user->cust_score)
		->where('id', '!=', $raw_user->id)
		->where('cust_delete', '=', "0")
        ->orderBy("cust_score",'DESC')
		->orderBy("cust_email_verify",'DESC')
		->orderBy("customers.id",'ASC')
		->get();
		if(count($q_ranking_all)>0){
			$ranking_cust = (int)(count($q_ranking_all)+1);
		} else {
			$ranking_cust = 1;
		}
		// all member apps
		$all_customer = DB::table('customers')
		->select('customers.id', 'cust_name', 'cust_score', 'cust_coin')
		->where('cust_phone_verify', '=', '1')
		->where('cust_delete', '=', "0")
        ->orderBy("cust_score",'DESC')
		->orderBy("cust_email_verify",'DESC')
		->orderBy("customers.id",'ASC')
		->get();
		
		$level_customer = $raw_user->cust_membership_type;
		// cek level master 
		$arr_level_apps = DB::table("apps_levels")
			->select('id', 'al_level')
			->where("al_min","<=",$raw_user->cust_score)
			->where("al_max",">=",$raw_user->cust_score)
			->first();
		if($arr_level_apps){
			if($raw_user->cust_membership_type!=$arr_level_apps){
				// update level user
				$updated_level = [
					'cust_membership_type' => $arr_level_apps->al_level,
				];
				DB::table("customers")->where('id', $raw_user->id)->update($updated_level);
				$level_customer = $arr_level_apps->al_level;
			}
			if($arr_level_apps->al_level=='ROOKIE PLAYER'){$icon_level = url('public/upload/rookie.png');} else
			if($arr_level_apps->al_level=='PRO PLAYER'){$icon_level = url('public/upload/pro.png');} else
			if($arr_level_apps->al_level=='ELITE PLAYER'){$icon_level = url('public/upload/top.png');}
		}
		
		// cek kota customer
		$cust_city=NULL;
		$arr_address = DB::table("customer_address")->where(["cust_id" => $raw_user->id, "ca_delete" => '0'])->first();
		if($arr_address){
			$arr_city = DB::table("zip_codes")->where(["id" => $arr_address->zip_id])->first();
			if($arr_city){
				$cust_city = $arr_city->zc_city;
			}
		}
		
		// tampilkan data customer
		$array_customer = [
			'cust_id' => $raw_user->id,
            'cust_name' => $raw_user->cust_name,
            'cust_phone' => $raw_user->cust_phone,
            'cust_email' => $raw_user->cust_email,
			'cust_bday' => $raw_user->cust_bday,
			'cust_gender' => $raw_user->cust_gender,
            //'cust_level' => $raw_user->cust_membership_type,
            'cust_level' => $level_customer,
            'cust_coin' => (int)$raw_user->cust_coin,
			'cust_score' => (int)$raw_user->cust_score,
			'cust_verify_phone' => (int)$raw_user->cust_phone_verify,
			'cust_verify_email' => (int)$raw_user->cust_email_verify,
			'cust_profile' => (int)$full_cust,
			'cust_icon_level' => $icon_level,
			'cust_refcode' => $refcode,
			'cust_refcode_join' => (int)$jumlah_referal,
			'cust_refcode_join_log' => $join_log,
			'cust_refcode_member' => $refcode_member,
			'cust_avatar' => $avatar_profile,
			//'cust_rank' => $ranking_cust,
			'cust_rank' => 'Peringkat '.number_format($ranking_cust,0,',','.').' dari '.number_format(count($all_customer),0,',','.').' member',
			'cust_role' => $raw_user->cust_role,
			'cust_city' => $cust_city,
			'cust_reseller' => $raw_user->cust_reseller,
        ];
		
		
		$has_lookup_ids = false;
		try {
			$has_lookup_ids = \Illuminate\Support\Facades\Schema::hasColumn('banner_apps', 'ba_lookup_ids');
		} catch (\Throwable $e) {}

		$select_fields = 'ts_banner_apps.id as pid, ba_kategori, ba_title, ba_link, ba_file, ba_description, created_at, ba_type, ba_lookup_id' . ($has_lookup_ids ? ', ba_lookup_ids' : '');
        $banner_apps = DB::table('banner_apps')->selectRaw($select_fields)
        ->where('ba_kategori', '=', 'statis')
		->where('ba_delete', '=', '0')
		->where('ba_start', '<=', date("Y-m-d"))
		->where('ba_end', '>=', date("Y-m-d"))
		/*
		->where(function($w) use($kategori) {
			if (!empty($kategori)) {
				$w->where('ba_kategori', '=', $kategori);
			}
		})
		*/
		->orderBy('created_at','DESC')
        ->get();
        $results = array();
        if (!empty($banner_apps)) {
            foreach ($banner_apps as $row) {
                /*if (!empty($row->p_main_image)) {
                    $image_url = "https://topsystem.id/api/product/600/";
                    $image[] = $row->p_main_image;
                }*/
				// https://www.topsystem.id/upload/event/1717382789.png
				if (!empty($row->ba_file)) {
                    $ba_file = 'https://www.topsystem.id/upload/event/'.$row->ba_file;
                } else {
					$ba_file = '';
				}

                $results[] = [
                    'banner_id' => $row->pid,
                    'banner_kategori' => $row->ba_kategori,
                    'banner_title' => $row->ba_title,
                    'banner_link' => $row->ba_link,
                    'banner_icon' => $ba_file,
					'banner_brand' => $this->cek_brands($row->pid),
					'banner_subsubcat' => $this->cek_subsubcat($row->pid),
					'banner_description' => $row->ba_description,
					'type' => $row->ba_type,
					'detail_id' => $row->ba_lookup_id,
					'detail_ids' => ($has_lookup_ids && !empty($row->ba_lookup_ids)) ? $row->ba_lookup_ids : $row->ba_lookup_id,
                ];
            }
        }
		
		// icon menu apps
		$icon_menu = DB::table('menu_apps')
		->select('id', 'ma_name', 'ma_slug', 'ma_icon', 'ma_active', 'created_at')
		->where("ma_type","=","home2")
        ->where('ma_active', '=', '1')
		->where('ma_delete', '=', '0')
		->orderBy("ma_sort","ASC")
        ->get();
        $result_menu = array();
        if (!empty($icon_menu)) {
            foreach ($icon_menu as $row_icon) {
				if (!empty($row_icon->ma_icon)) {
                    $ma_icon = 'https://www.topsystem.id/upload/event/'.$row_icon->ma_icon;
                } else {
					$ma_icon = '';
				}

                $result_menu[] = [
                    'menu_id' => $row_icon->id,
                    'menu_name' => $row_icon->ma_name,
                    'menu_slug' => $row_icon->ma_slug,
                    'menu_icon' => $ma_icon,
                ];
            }
        }
		
		
		//if(date("Y-m-d")>='2024-10-01'){$app_icon='TopscoreSepuluh2';} else {$app_icon='MainActivity';}
		// icon aplikasi
		$icon_default = DB::table("apps_icons")
			->select('ai_name')
			->where("ai_start","<=",date("Y-m-d"))
			->where("ai_end",">=",date("Y-m-d"))
			->where("ai_active","=","1")
			->where("ai_default","=","1")
			->where("ai_delete","=","0")
			->first();
		$icon_active = DB::table("apps_icons")
			->select('ai_name')
			->where("ai_start","<=",date("Y-m-d"))
			->where("ai_end",">=",date("Y-m-d"))
			->where("ai_active","=","1")
			->where("ai_default","=","0")
			->where("ai_delete","=","0")
			->first();
		if($icon_active){ $app_icon = $icon_active->ai_name; } else { $app_icon = $icon_default->ai_name; }
		// cek device user login kusus android minimal version Topscore V1.0.82
		$cust_version = explode(".",$raw_user->cust_app_versions);
		//if($raw_user->cust_platform == 'android' && end($cust_version)>=82){
		/*
		if($raw_user->cust_platform == 'android' && $cust_version[0]>=1 && end($cust_version)>=82){
			$app_icon=$app_icon;
		} else { 
			$app_icon = $icon_default->ai_name; 
		}
		*/
		if($raw_user->cust_platform == 'android'){
			if(($cust_version[0]==1 && end($cust_version)>=82)){
				$app_icon=$app_icon;
			} else 
			if($cust_version[0]>=2){
				$app_icon=$app_icon;
			} else {
				$app_icon = $icon_default->ai_name; 
			}
			
		} else { 
			$app_icon = $icon_default->ai_name; 
		}
		
		// save log traffic
		$data_log = [
			'cust_id' => $cust_id,
			'ta_page_access' => "Dashboard",
			'ta_description' => "Mengakses laman dashbooard aplikasi",
			'created_at' => date("Y-m-d H:i:s"),
			'ip_address' => $request->ip(),
		];
		$save_log = DB::table("traffic_apps")->insertGetId(array_merge($data_log));
		
		// cek minimal koin dan minimal GWP
		$cek_gwp = DB::table('product_discounts')->select('product_discounts.id', 'db_value')
				  ->leftJoin('discount_bandrol', 'discount_bandrol.id', '=', 'product_discounts.db_id')
				  ->where('std_id', '=', '20')
				  ->where('pd_start', '<=', date("Y-m-d"))
				  ->where('pd_date', '>=', date("Y-m-d"))
				  ->where('pd_start_time', '<=', date("H:i:s"))
				  ->where('pd_end_time', '>=', date("H:i:s"))
				  ->whereIn('pd_type', ['fix_special'])
				  ->orderBy("db_value","ASC")
				  ->first();
        
		$min_gwp = 0;
		$flag_gwp = 0;
		$gwp_value = array();
		
		//  cek buy1get1
		$cek_buy1get1 = DB::table('product_discounts')->select('product_discounts.id', 'db_value')
				  ->leftJoin('discount_bandrol', 'discount_bandrol.id', '=', 'product_discounts.db_id')
				  ->where('std_id', '=', '20')
				  ->where('pd_start', '<=', date("Y-m-d"))
				  ->where('pd_date', '>=', date("Y-m-d"))
				  ->where('pd_start_time', '<=', date("H:i:s"))
				  ->where('pd_end_time', '>=', date("H:i:s"))
				  ->whereIn('pd_type', ['b1g1'])
				  ->orderBy("db_value","ASC")
				  ->first();
		if($cek_buy1get1){$flag_gwp=0;} else {$flag_gwp=$flag_gwp;}
		
		$check_pos = DB::table("pos_transactions")
				//->where(['cust_id'=>$cust_id, 'is_apps'=>'1'])
				->where(['cust_id'=>$cust_id])
				->whereNotIn('pos_status', ['CANCEL','UNPAID','REFUND'])
				->get();
        //if(count($check_pos)>0){$min_coin=10000;} else {$min_coin=100000;}
		//$min_coin=20000;
		$min_coin=1;
		
        $data = [
            'status' => [
                'code' => 200,
                'description' => 'Success',
                //'app_icon' => 'MainActivity', // TopscoreSepuluh2, MainActivity
                'app_icon' => $app_icon,
                //'min_coin' => 20000,
                'min_coin' => $min_coin,
				'min_gwp' => $min_gwp,
				'gwp_value' => $gwp_value,
				'flag_gwp' => $flag_gwp,
				//'flag_gwp' => 0,
				'banner' => $results,
				'customer' => $array_customer,
				'menu' => $result_menu,
				'brand' => $this->allBrand(),
				'event' => $this->CekEvent(),
				'product' => $this->getArticle($request->cust_id),
				'blog' => $this->getBlog(),
				'forum' => $this->getForum($cust_id),
				'games' => $this->getGames($cust_id),
				'youtube' => $this->CekVideo($cust_id),
            ],
            //'results' => $results
        ];
        return response()->json($data);
    }
	
	private function cek_brands($banner_id)
	{
		$cek = DB::table('banner_app_brands')
			   ->select("br_id","br_name")
			   ->leftJoin('brands', 'brands.id', '=', 'banner_app_brands.br_id')
			   ->where("bn_id","=",$banner_id)->get();
		$no=0; $brand='';
		foreach($cek as $raw){
			$brand.=$raw->br_name.', ';
		}
		return rtrim($brand,', ');
	}
	
	private function cek_subsubcat($banner_id)
	{
		$cek = DB::table('banner_app_brand_details')
			   ->select("pssc_id","pssc_name")
			   ->leftJoin('banner_app_brands', 'banner_app_brands.id', '=', 'banner_app_brand_details.bnb_id')
			   ->leftJoin('product_sub_sub_categories', 'product_sub_sub_categories.id', '=', 'banner_app_brand_details.pssc_id')
			   ->where("bn_id","=",$banner_id)->groupBy('pssc_id')->get();
		$no=0; $subsubcat='';
		foreach($cek as $raw){
			$subsubcat.=$raw->pssc_name.', ';
		}
		return rtrim($subsubcat,', ');
	}
	
	private function allBrand()
    {
        $banner_apps = DB::table('brands')
		->selectRaw('ts_brands.id as pid, br_name, br_slug, br_description, br_image, br_banner, created_at')
		->where('br_show_apps', '=', '1')
		->where('br_delete', '=', '0')
		/*->where(function($w) use($kategori) {
			if (!empty($kategori)) {
				$w->where('ba_kategori', '=', $kategori);
			}
		})*/
		->orderBy("br_sort","ASC")
        ->get();
        $results = array();
        if (!empty($banner_apps)) {
            foreach ($banner_apps as $row) {
				if($row->br_banner!=''){
					$url_banner = "https://topsystem.id/public/upload/brand/".$row->br_banner;
				} else {
					if($row->br_image!=''){
						$url_banner = "https://topsystem.id/public/upload/brand/".$row->br_image;
					} else {
						$url_banner = "";
					}
				}
					if($row->br_image!=''){
						$url_banner = "https://topsystem.id/api/brand/thumbs/".$row->br_image;
					} else {
						$url_banner = "";
					}

                $results[] = [
                    'brand_id' => $row->pid,
                    'brand_title' => $row->br_name,
                    'brand_slug' => $row->br_slug,
                    'brand_icon' => $url_banner,
					'brand_description' => $row->br_description,
                ];
            }
        }
		/*
        $data = [
            'status' => [
                'code' => 200,
                'description' => 'Success'
            ],
            'results' => $results
        ];
		*/
        return $results;
    }
	
	// cek event
	private function CekEvent()
    {
		$type_event = 'upcomming';
        $query_data = DB::table("event_tops")
		->selectRaw('ts_event_tops.id as pid, et_title, et_start_date, et_start_time, et_end_date, et_end_time, et_address, et_banner, et_price, et_terms, et_description, et_quota, et_lat, et_lang, et_type, et_link_online, et_score, et_category')
		->where('et_delete', '=', '0')
		->whereRaw("CONCAT(ts_event_tops.et_start_date,' ',ts_event_tops.et_start_time) >= '".date('Y-m-d H:i:s')."'")
		/*
		->where(function($w) use($type_event, $id_event) {
			if (!empty($type_event)) {
				if($type_event == "berlangsung"){
					$w->where('et_start_date', '>=',date("Y-m-d"));
					$w->where('et_start_time', '<=',date("H:i:s"));
					$w->where('et_end_date', '<=',date("Y-m-d"));
					$w->where('et_end_time', '>=',date("H:i:s"));
				} else
				if($type_event == "upcomming"){
					$w->whereRaw("CONCAT(ts_event_tops.et_start_date,' ',ts_event_tops.et_start_time) >= '".date('Y-m-d H:i:s')."'");
				} else
				{
					$w->whereRaw("CONCAT(ts_event_tops.et_end_date,' ',ts_event_tops.et_end_time) <= '".date('Y-m-d H:i:s')."'");
				}
			}
			if (!empty($id_event)) {
				$w->where('id', '=',$id_event);
			}
		})
		*/
		->orderBy('et_end_date')
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
				
				if($row->et_type == "online"){
					$link_webinar = $row->et_link_online;
				} else {
					$link_webinar = "";
				}
				
				
				// cek jika event coming soon
				$event_coming = '0';
				$start_date_check = $row->et_start_date.' '.$row->et_start_time;
				$end_date_check = date("Y-m-d H:i:s", strtotime('-14 days', strtotime($start_date_check)));
				if($end_date_check<=date("Y-m-d H:i:s")){$event_coming=0;} else {$event_coming=1;}
				
				if($row->et_category!="eksekutif"){
				$results[] = [
					'event_id' => $row->pid,
					'event_type' => $row->et_type,
					'event_title' => $row->et_title,
					'event_start_date' => $row->et_start_date,
					'event_start_time' => $row->et_start_time,
					'event_end_date' => $row->et_end_date,
					'event_end_time' => $row->et_end_time,
					'event_address' => $row->et_address,
					'event_banner' => 'https://topsystem.id/upload/event/'.$row->et_banner,
					'event_price' => $row->et_price,
					'event_quota' => $quota,
					'event_user_order' => $jumlah_user,
					'event_terms' => $row->et_terms,
					'event_description' => $row->et_description,
					'event_lat' => $row->et_lat,
					'event_lang' => $row->et_lang,
					'event_score' => $row->et_score,
					'additional_form' => $array_form_sub,
					'event_coming_soon' => $event_coming,
					'event_open_reg' => $end_date_check,
                ];
				}
            }

        }
		
        return $results;
    }
	
	// cek artikel terbaru
	private function getArticle($cust_id='')
    {
        $exception = DB::table('exception_locations')->select('pl_code')
        ->leftJoin('product_locations', 'product_locations.id', '=', 'exception_locations.pl_id')->get();
        $exc_location = array();
        foreach ($exception as $exc) {
            $exc_location[] = $exc->pl_code;
        }
		
		
		// Param pencarian artikel
		/*
		$sub_kat = $request->sub_kat;
		$subsub_kat = $request->subsub_kat;
		$brand = $request->brand;
		$product_name = $request->product_name;
		$sorting_artikel  = $request->sorting_artikel; // sorting artikel (baru, terlaris, dilihat)
		*/
		$sub_kat = "";
		$subsub_kat = "";
		$brand = "";
		$product_name = "";
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
		->groupBy('products.p_name')
		->orderBy($sort_orders,'DESC')
		//->limit('100')
        //->get();
		->paginate(10);
		*/
		
		$stock = DB::table('product_stocks')->selectRaw('ts_product_stocks.id as pid, pc_name as category, br_name as brand, p_name as article, p_color as color, sz_name as size, ps_price_tag, p_price_tag, ps_sell_price, p_sell_price, p_main_image, p_image, psc_name, pssc_name, sold, SUM(ts_product_stocks.ps_qty) as stock')
        ->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
        ->leftJoin('product_categories', 'product_categories.id', '=', 'products.pc_id')
		->leftJoin('product_sub_categories', 'product_sub_categories.id', '=', 'products.psc_id')
		->leftJoin('product_sub_sub_categories', 'product_sub_sub_categories.id', '=', 'products.pssc_id')
        ->leftJoin('brands', 'brands.id', '=', 'products.br_id')
        ->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
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
		//->limit('100')
        //->get();
		->paginate(10);
		
        $results = array();
        if (!empty($stock)) {
            foreach ($stock as $row) {
                $price = null;
                $price_tag = null;
                $store = null;
                $image_url = "https://topsystem.id/api/product/600/";
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
				
				// cek customers
				$add_persen = 0;
				if($cust_id){
					$arr_customer = DB::table("customers")->where("id","=",$cust_id)->first();
					if($arr_customer){
						// jika sebagai reseller
						if($arr_customer->cust_reseller==1){
							// kusus brand topscore {$row->brand}
							if($row->brand == "TOPSCORE"){
								$add_persen = 0;
								$persen_potongan = 20;
								$sell_price_persen = ($price_tag * ($persen_potongan/100)); // default reseller 20%
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
				
                $results[] = [
                    'product_id' => $row->pid,
                    'product_category' => $row->category,
                    'product_brand' => $row->brand,
                    'product_sub_cat' => $row->psc_name,
                    'product_subsub_cat' => $row->pssc_name,
                    'product_article' => $row->article,
                    'product_stock' => $row->stock,
                    //'product_price_tag' => $row->p_price_tag,
					//'product_sell_price' => $row->p_sell_price,
					'product_price_tag' => $price_tag,
					'product_sell_price' => $sell_price,
					'product_discount' => round($persen_potongan,2),
					'additional_discount' => round($add_persen),
					'product_ratting' => 0, //$ratting,
                    'product_image_url' => $image_url,
                    'product_image' => $image,
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
		->paginate(5);
        $results = array();
        if (!empty($query_data)) {
            foreach ($query_data as $row) {
                if (!empty($row->bct_image)) {
                    $image_url = "https://topsystem.id/api/blog/600/".$row->bct_image;
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
	
	// get video youtube
	private function CekVideo($cust_id)
    {
		$query_data = DB::table('youtube')
		->selectRaw('ts_youtube.id as pid, yt_title, yt_url, yt_description, created_at, yt_view')
		->where('yt_active', '=', '1')
		->where('yt_delete', '=', '0')		
		->orderBy('id','DESC')
		->limit('3')
        ->get();
        $results = array();
        if (!empty($query_data)) {
            foreach ($query_data as $row) {
				$row_views = DB::table("youtube_views")->where("yt_id","=",$row->pid)->where("cust_id","=",$cust_id)->first();
				if($row_views){
					$flag_view = 1;
				} else {
					$flag_view = 0;
				}
				
				$youtube_url_id = explode("=",$row->yt_url);
				$youtube_image = "https://img.youtube.com/vi/".$youtube_url_id[1]."/sddefault.jpg";
                $results[] = [
					'youtube_id' => $row->pid,
					'youtube_title' => $row->yt_title,
					'youtube_url' => $row->yt_url,
					'youtube_url_code' => $youtube_url_id[1],
                    'youtube_description' => $row->yt_description,
					'youtube_viewers' => $row->yt_view,
					'youtube_view' => $flag_view,
					'youtube_image' => $youtube_image,
					
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
		->paginate(5);
        $results = array();
        if (!empty($query_data)) {
            foreach ($query_data as $row) {
                if (!empty($row->fo_image)) {
                    //$image_url = "https://topsystem.id/api/blog/600/".$row->fo_image;
                    $image_url = "https://topsystem.id/upload/forum/".$row->fo_image;
                } else {
					$image_url = '';
				}
                
				// cek join
				$join_cek = DB::table("forum_joins")->where("cust_id","=",$cust_id)->where("fo_id","=",$row->id)->first();
				if($join_cek){$flag_join = 1;} else {$flag_join = 0;}
				
				if($row->fo_description){$fo_description=$row->fo_description;} else {$fo_description='';}
				
                $results[] = [
                    'forum_id' => $row->id,
					'forum_name' => $row->fo_name,
					'forum_slug' => $row->fo_slug,
					//'forum_description' => $row->fo_description,
					'forum_description' => $fo_description,
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
		->where(DB::raw("CONCAT(gm_startdate,' ',gm_starttime)"), '<=', date("Y-m-d H:i:s"))
		->where(DB::raw("CONCAT(gm_enddate,' ',gm_endtime)"), '>=', date("Y-m-d H:i:s"))
		->orderBy("games.created_at",'DESC')
		->paginate(5);
		
        $results = array();
		if (!empty($query)) {
            foreach ($query as $row) {
                $cek_usergame = DB::table("game_users")->where("gm_id","=",$row->id)->where("cust_id","=",$cust_id)->first();
				if(empty($cek_usergame)){
                if (!empty($row->gm_image)) {
                    //$image_url = "https://topsystem.id/api/blog/600/".$row->gm_image;
                    $image_url = "https://topsystem.id/upload/game/".$row->gm_image;
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
					foreach($query_det as $row_detail){
						if (!empty($row_detail->gmd_image)) {
							$image_url_det = "https://topsystem.id/api/blog/600/".$row_detail->gmd_image;
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
					'games_detail' => $result_details,
                ];
			    }
            }
        }
		
        return $results;
    }
    
    // tampilkan jumlah pesanan yang sedang aktif
	public function cekPesananactive(Request $request)
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
		
		// tampilkan data
		$data = [
            'status' => [
            	'code' => 200,
                'description' => 'Success'
        	],
			'order_status' => [
				'unpaid' => (int)$this->orderStatus($cust_id, 'belum bayar'),
				'onproccess' => (int)$this->orderStatus($cust_id, 'diproses'),
				'indelivery' => (int)$this->orderStatus($cust_id, 'dikirim'),
				'done' => (int)$this->orderStatus($cust_id, 'selesai'),
				'cancel' => (int)$this->orderStatus($cust_id, 'batal'),
			]
        ];
        return response()->json($data);
	}
	
	private function orderStatus($cust_id, $pos_status)
	{
		$query_data = DB::table("pos_transactions")
		->selectRaw('ts_pos_transactions.id as pid, pos_invoice, st_id, st_id_ref, st_name, pos_real_price, pos_admin_cost, pos_another_cost, pos_member_cost, pos_status, pos_note, ts_pos_transactions.created_at, external_id, va_number, payment_status, pos_status, no_resi_expedisi, expedisi, pos_discount, pos_shipping, pos_cust_address, pt_callback_date,  json_pg')
		->leftJoin('stores', 'stores.id', '=', 'pos_transactions.st_id_ref')
		->where('cust_id', '=', $cust_id)
		->where('is_apps', '=', 1)
		->where(function($w) use($pos_status) {
			if (!empty($pos_status)) {
				if($pos_status == "diproses"){
					$w->whereIn('pos_transactions.pos_status', ['PAID','WAITING FOR CONFIRMATION','IN PROGRESS', 'SHIPPING NUMBER', 'NAMESET']);
				}
				if($pos_status == "dikirim"){
					$w->whereIn('pos_transactions.pos_status', ['IN DELIVERY']);
				}
				if($pos_status == "selesai"){
					$w->whereIn('pos_transactions.pos_status', ['DONE']);
				}
				if($pos_status == "belum bayar"){
					$w->whereIn('pos_transactions.pos_status', ['UNPAID']);
				}
				if($pos_status == "batal"){
					$w->whereIn('pos_transactions.pos_status', ['CANCEL']);
				}
			}
			//$w->whereRaw('(SELECT COUNT(*) ts_pos_transaction_details WHERE 1 AND pt_id=ts_pos_transactions.id)>0');
		})
		->orderBy('pos_transactions.created_at','DESC')
        //->get();
		->count();
		return $query_data;
	}
}
