<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Helpers\ProductImageHelper;

class DashboardAppsduaController extends Controller
{
	protected $url_server = 'https://topsystem.id/';
	
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
		
		$dashboard_type = $request->dashboard_type;
		$key_search = $request->key_search;
		$cust_id = $request->cust_id;
		if (empty($cust_id) || empty($dashboard_type)){
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
		if($raw_user->cust_referal_code && $raw_user->cust_flag_referal==1){
			$refcode=$raw_user->cust_referal_code;
			// cek jumlah cuser yang menggunakan referal
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
		/*
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
		*/
		$q_ranking_all = Cache::remember('ranking_all_' . $raw_user->id, now()->addHours(24), function () use ($raw_user) {
		return DB::table('customers')
			->select('customers.id', 'cust_name', 'cust_score', 'cust_coin')
			->where('cust_phone_verify', '=', '1')
			->where('cust_score', '>', $raw_user->cust_score)
			->where('id', '!=', $raw_user->id)
			->where('cust_delete', '=', "0")
			->orderBy("cust_score",'DESC')
			->orderBy("cust_email_verify",'DESC')
			->orderBy("customers.id",'ASC')
			->get();
		});
		if(count($q_ranking_all)>0){
			$ranking_cust = (int)(count($q_ranking_all)+1);
		} else {
			$ranking_cust = 1;
		}
		// all member apps
		/*
		$all_customer = DB::table('customers')
		->select('customers.id', 'cust_name', 'cust_score', 'cust_coin')
		->where('cust_phone_verify', '=', '1')
		->where('cust_delete', '=', "0")
        ->orderBy("cust_score",'DESC')
		->orderBy("cust_email_verify",'DESC')
		->orderBy("customers.id",'ASC')
		->get();
		*/
		
		/*
		$all_customer = DB::table('customers')
		->select('customers.id', 'cust_name', 'cust_score', 'cust_coin')
		->where('cust_phone_verify', '=', '1')
		->where('cust_delete', '=', "0")
        ->orderBy("cust_score",'DESC')
		->orderBy("cust_email_verify",'DESC')
		->orderBy("customers.id",'ASC')
		->count();
		*/
		//$all_customer=33622;
		$all_customer = Cache::remember('all_customer_count', now()->addDay(), function () {
		return DB::table('customers')
			->select('customers.id', 'cust_name', 'cust_score', 'cust_coin')
			->where('cust_phone_verify', '=', '1')
			->where('cust_delete', '=', "0")
			->orderBy("cust_score", 'DESC')
			->orderBy("cust_email_verify", 'DESC')
			->orderBy("customers.id", 'ASC')
			->count();
		});
		
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
			
			$check_level = DB::table("apps_levels")->where("al_level","=",$level_customer)->first();
			if($check_level){
				if($check_level->al_max<100000000){
					$up_levelcheck = DB::table("apps_levels")->where("al_min","=",($check_level->al_max+1))->first();
					$selisih_score = ($check_level->al_max - $raw_user->cust_score)+1;
					$cust_barprogress = round((($raw_user->cust_score/($check_level->al_max+1)) * 100),2);
					$text_uplevel = 'Ayo '.$selisih_score.' Score lagi naik level ke '.ucwords(strtolower($up_levelcheck->al_level)).'!';
				} else {
					$cust_barprogress = 99.99;
					$text_uplevel = 'Kamu sudah mencapai level maksimal';
				}
				//$cust_barprogress = $raw_user->cust_score;
			} else {
				
			}
			
			/*
			if($arr_level_apps->al_level=='ROOKIE PLAYER'){$icon_level = url('public/upload/rookie.png');} else
			if($arr_level_apps->al_level=='PRO PLAYER'){$icon_level = url('public/upload/pro.png');} else
			if($arr_level_apps->al_level=='ELITE PLAYER'){$icon_level = url('public/upload/top.png');}
			*/
			if($arr_level_apps->al_level=='ROOKIE PLAYER'){$icon_level = url('public/upload/badge-rookie.webp');} else
			if($arr_level_apps->al_level=='PRO PLAYER'){$icon_level = url('public/upload/badge-pro.webp');} else
			if($arr_level_apps->al_level=='ELITE PLAYER'){$icon_level = url('public/upload/badge-elite.webp');}
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
			//'cust_rank' => 'Peringkat '.number_format($ranking_cust,0,',','.').' dari '.number_format(count($all_customer),0,',','.').' member',
			//'cust_rank' => 'Peringkat '.number_format($ranking_cust,0,',','.').' dari 29.162 member',
			'cust_rank' => 'Peringkat '.number_format($ranking_cust,0,',','.').' dari '.number_format($all_customer,0,',','.').' member',
			'cust_role' => $raw_user->cust_role,
			'cust_city' => $cust_city,
			'cust_barprogress' => $cust_barprogress,
			'cust_uplevel' => $text_uplevel,
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
				if (!empty($row->ba_file)) {
                    $ba_file = $this->url_server.'upload/event/'.$row->ba_file;
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

        // *** cek diskon lastcall aplikasi ***
        $arr_lastcall = DB::table("product_discounts")
		->select('product_discounts.id as pid', 'product_discounts.created_at', 'st_id', 'std_id', 'pd_name', 'pd_type', 'pd_value', 'pd_date', 'no_mom', 'pd_banner', 'pd_tnc', 'pd_use')
		->where('std_id', '=', '20') // kusus apps
		->whereNotIn('pd_type', ['fix_special'])
		->whereRaw("CONCAT(ts_product_discounts.pd_start,' ',ts_product_discounts.pd_start_time)<='".date("Y-m-d H:i:s")."'")
        ->whereRaw("CONCAT(ts_product_discounts.pd_date,' ',ts_product_discounts.pd_end_time)>='".date("Y-m-d H:i:s")."'")
		->where('pd_flash_sale',"=","0") // selain flash sale
		//->where('pd_name', 'LIKE', "%last call%")
		->where('pd_name', 'LIKE', "%last stock%")
		->groupBy('no_mom')
		->orderBy('id','DESC')
        ->first();
		if($arr_lastcall){$id_last_call=$arr_lastcall->pid;} else {$id_last_call='';}

		// icon menu apps
		$icon_menu = DB::table('menu_apps')
		->select('id', 'ma_name', 'ma_slug', 'ma_icon', 'ma_active', 'created_at')
		->where("ma_type","=","home2")
        ->where('ma_active', '=', '1')
		->where('ma_delete', '=', '0')
		->orderBy("ma_sort","ASC")
        ->get();
        $result_menu = array();
        $show_new=0; $show_old=0; $show_challenge_menu=0; $show_forum_menu=0;
        if (!empty($icon_menu)) {
            foreach ($icon_menu as $row_icon) {
				if (!empty($row_icon->ma_icon)) {
                    $ma_icon = $this->url_server.'upload/event/'.$row_icon->ma_icon;
                } else {
					$ma_icon = '';
				}
                
                if($row_icon->id=='10'){
                    $id_last_call_new= $id_last_call;; //'1261'; //$id_last_call;
                } else {
                    $id_last_call_new='';
                }
                
                /*
                $result_menu[] = [
                    'menu_id' => $row_icon->id,
                    'menu_name' => $row_icon->ma_name,
                    'menu_slug' => $row_icon->ma_slug,
                    'menu_icon' => $ma_icon,
                    'menu_reference' => "$id_last_call_new",
                ];
                */
                
                /*
                if($raw_user->cust_app_versions=="2.0.12" && $row_icon->id==10){
					$show_new = 1;
				} else 
				if($raw_user->cust_app_versions!="2.0.12" && $row_icon->id==6){
					$show_old = 1;
				}
				*/
				
				$versi_app = str_replace(".","",$raw_user->cust_app_versions);
				
				$versi_awal  = substr($versi_app,0,2);
				$versi_akhir = substr('0'.(substr($versi_app,2,10)),-2);
				$versi_app = (int)$versi_awal.$versi_akhir;
				
				if($versi_app>="2012" && $row_icon->id==10){
					$show_new = 1;
				} else 
				if($versi_app<"2012" && $row_icon->id==6){
					$show_old = 1;
				} else
				if($versi_app>="2100" && $row_icon->id==11){ // 
					$show_challenge_menu=1;
				} else
				if($versi_app<"2100" && $row_icon->id==2){ // 
					$show_forum_menu=1;
				}
				
				
				
				if(($show_old==1 && $row_icon->id=="6") || ($show_new==1 && $row_icon->id=="10") || ($show_challenge_menu==1 && $row_icon->id=="11") || ($show_forum_menu==1 && $row_icon->id=="2") ){
					$result_menu[] = [
						'menu_id' => $row_icon->id,
						'menu_name' => $row_icon->ma_name,
						'menu_slug' => $row_icon->ma_slug,
						'menu_icon' => $ma_icon,
						'menu_reference' => "$id_last_call_new",
					];
				} else {
					if(!in_array($row_icon->id,['6','10','11','2'])){
						$result_menu[] = [
							'menu_id' => $row_icon->id,
							'menu_name' => $row_icon->ma_name,
							'menu_slug' => $row_icon->ma_slug,
							'menu_icon' => $ma_icon,
							'menu_reference' => "$id_last_call_new",
						];
					}
				}
                
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
				  /*
				  ->where('pd_start', '<=', date("Y-m-d"))
				  ->where('pd_date', '>=', date("Y-m-d"))
				  ->where('pd_start_time', '<=', date("H:i:s"))
				  ->where('pd_end_time', '>=', date("H:i:s"))
				  */
				  ->whereRaw("CONCAT(ts_product_discounts.pd_start,' ',ts_product_discounts.pd_start_time)<='".date("Y-m-d H:i:s")."'")
		          ->whereRaw("CONCAT(ts_product_discounts.pd_date,' ',ts_product_discounts.pd_end_time)>='".date("Y-m-d H:i:s")."'")
				  ->whereIn('pd_type', ['fix_special'])
				  ->orderBy("db_value","ASC")
				  ->first();
		$min_gwp = 0;
		$gwp_value = array();
		
		// cek jika ada flash sale
		$arr_flashsale = DB::table('product_discounts')->select('id', 'pd_start', 'pd_date', 'pd_start_time', 'pd_end_time')
				  ->where('std_id', '=', '20') // kusus aplikasi
				  ->whereNotIn('pd_type', ['fix_special'])
				  /*
				  ->where('pd_start', '<=', date("Y-m-d"))
				  ->where('pd_date', '>=', date("Y-m-d"))
				  ->where('pd_start_time', '<=', date("H:i:s"))
				  ->where('pd_end_time', '>=', date("H:i:s"))
				  */
				  ->whereRaw("CONCAT(ts_product_discounts.pd_start,' ',ts_product_discounts.pd_start_time)<='".date("Y-m-d H:i:s")."'")
		          ->whereRaw("CONCAT(ts_product_discounts.pd_date,' ',ts_product_discounts.pd_end_time)>='".date("Y-m-d H:i:s")."'")
				  ->where('pd_flash_sale', '=', "1") // kusus flash sale
				  ->groupBy('no_mom')
				  ->first();
		if($arr_flashsale){
			$flag_flashsale = 1;
			$start_flashsale = $arr_flashsale->pd_start_time;
			$end_flashsale = $arr_flashsale->pd_end_time;
		} else {
			$flag_flashsale=0;
			$start_flashsale = '';
			$end_flashsale = '';
		}
		
		$check_pos = DB::table("pos_transactions")
				->where(['cust_id'=>$cust_id])
				->whereNotIn('pos_status', ['CANCEL','UNPAID','REFUND'])
				->get();
		//if(count($check_pos)>0){$min_coin=20000;} else {$min_coin=1000000;}
		//$min_coin=20000;
		
		$arr_setting_apps = DB::table("setting_apps")->where("id","=","1")->first();
		if(count($check_pos)>0){$min_coin=$arr_setting_apps->min_coin;} else {$min_coin=400000;}
		//$min_coin = $arr_setting_apps->min_coin;
		//$min_coin = 200000;
		$min_coin = 1;
		$min_coin_order = $arr_setting_apps->min_coin_order;
		
		// cek topic customer
		if($raw_user->cust_topic){$topic_customer = $raw_user->cust_topic;} else {$topic_customer='';}
		//if(substr($raw_user->created_at,0,10) < "2024-10-01"){$cust_created_at='2024-10-10';} else {$cust_created_at=substr($raw_user->created_at,0,10);}
		$arr_month = ['','januari', 'februari', 'maret', 'april', 'mei', 'juni', 'juli', 'agustus', 'september', 'oktober', 'november', 'desember'];
		if(substr($raw_user->created_at,0,10) < "2024-10-01"){
			$cust_created_at='2024-10-10';
			$topic_recom = $arr_month[10].'_'.substr($cust_created_at,0,4);
		} else {
			$cust_created_at=substr($raw_user->created_at,0,10);
			$topic_recom = $arr_month[(int)substr($cust_created_at,5,2)].'_'.substr($cust_created_at,0,4);
		}
		
		if($raw_user->cust_device_id){$cust_device_id = $raw_user->cust_device_id;} else {$cust_device_id='';}
		if($raw_user->cust_token_id){$cust_token_id = $raw_user->cust_token_id;} else {$cust_token_id='';}
		
		// cek game challenge
		$gamechallenge = DB::table("game_challenges")
			->select('id', 'gc_name', 'gc_description', 'gc_start', 'gc_end', 'gc_popup', 'gc_banner_top', 'gc_banner_middle', 'gc_gift_description', 'gc_active', 'gc_banner_home')
			->where("gc_start","<=",date("Y-m-d"))
			->where("gc_end",">=",date("Y-m-d"))
			->where("gc_active","=",'1')
			->where("gc_delete","=",'0')
			->orderBy("id","DESC")
			->first();
		if($gamechallenge){
		    $open_gamechallenge=1;
		    $banner_gamechallenge = $this->url_server.'upload/challenge/'.$gamechallenge->gc_banner_home;
		    //$banner_gamechallenge = $this->url_server.'upload/event/banner-akses-10-10.webp';
		} else { $open_gamechallenge=0; $banner_gamechallenge=NULL; }
		
		// default respon json
		$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success',
					'app_icon' => $app_icon,
					'min_coin' => $min_coin,
					'min_coin_order' => $min_coin_order,
					'min_gwp' => $min_gwp,
					'gwp_value' => $gwp_value,
					'flag_flashsale' => $flag_flashsale,
					'start_fs' => $start_flashsale,
					'end_fs' => $end_flashsale,
					'fee_services' => 0,
					'topic_customer' => $topic_customer,
					'reg_customer' => $cust_created_at,
					'topic_recom' => $topic_recom,
					'deviceid_customer' => $cust_device_id,
					'token_customer' => $cust_token_id,
					'open_gamechallenge' => $open_gamechallenge,
					'banner_gamechallenge' => $banner_gamechallenge,
				],
			];
		
		if($dashboard_type == 'banner'){
			$data = array_merge($data,array('banner' => $results));
			
			/*
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success',
					'app_icon' => $app_icon,
					'min_coin' => 20000,
					'min_gwp' => $min_gwp,
					'banner' => $results,
					//'customer' => $array_customer,
					//'menu' => $result_menu,
					//'brand' => $this->allBrand(),
					//'event' => $this->CekEvent(),
					//'product' => $this->getArticle(),
					//'blog' => $this->getBlog(),
					//'forum' => $this->getForum($cust_id),
					//'games' => $this->getGames($cust_id),
					//'youtube' => $this->CekVideo($cust_id),
				],
			];
			*/
		}
		if($dashboard_type == 'customer'){
			$data = array_merge($data,array('customer' => $array_customer));
			/*
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success',
					'app_icon' => $app_icon,
					'min_coin' => 20000,
					'min_gwp' => $min_gwp,
					//'banner' => $results,
					'customer' => $array_customer,
					//'menu' => $result_menu,
					//'brand' => $this->allBrand(),
					//'event' => $this->CekEvent(),
					//'product' => $this->getArticle(),
					//'blog' => $this->getBlog(),
					//'forum' => $this->getForum($cust_id),
					//'games' => $this->getGames($cust_id),
					//'youtube' => $this->CekVideo($cust_id),
				],
			];
			*/
		}
		if($dashboard_type == 'menu'){
			$data = array_merge($data,array('menu' => $result_menu));
			/*
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success',
					'app_icon' => $app_icon,
					'min_coin' => 20000,
					'min_gwp' => $min_gwp,
					//'banner' => $results,
					//'customer' => $array_customer,
					'menu' => $result_menu,
					//'brand' => $this->allBrand(),
					//'event' => $this->CekEvent(),
					//'product' => $this->getArticle(),
					//'blog' => $this->getBlog(),
					//'forum' => $this->getForum($cust_id),
					//'games' => $this->getGames($cust_id),
					//'youtube' => $this->CekVideo($cust_id),
				],
			];
			*/
		}
		if($dashboard_type == 'brand'){
			$data = array_merge($data,array('brand' => $this->allBrand()));
			/*
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success',
					'app_icon' => $app_icon,
					'min_coin' => 20000,
					'min_gwp' => $min_gwp,
					//'banner' => $results,
					//'customer' => $array_customer,
					//'menu' => $result_menu,
					'brand' => $this->allBrand(),
					//'event' => $this->CekEvent(),
					//'product' => $this->getArticle(),
					//'blog' => $this->getBlog(),
					//'forum' => $this->getForum($cust_id),
					//'games' => $this->getGames($cust_id),
					//'youtube' => $this->CekVideo($cust_id),
				],
			];
			*/
		}
		if($dashboard_type == 'event'){
			$data = array_merge($data,array('event' => $this->CekEvent($cust_id, $key_search)));
			/*
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success',
					'app_icon' => $app_icon,
					'min_coin' => 20000,
					'min_gwp' => $min_gwp,
					//'banner' => $results,
					//'customer' => $array_customer,
					//'menu' => $result_menu,
					//'brand' => $this->allBrand(),
					'event' => $this->CekEvent(),
					//'product' => $this->getArticle(),
					//'blog' => $this->getBlog(),
					//'forum' => $this->getForum($cust_id),
					//'games' => $this->getGames($cust_id),
					//'youtube' => $this->CekVideo($cust_id),
				],
			];
			*/
		}
		if($dashboard_type == 'product'){
			$data = array_merge($data,array('product' => $this->getArticle($cust_id, $key_search)));
			/*
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success',
					'app_icon' => $app_icon,
					'min_coin' => 20000,
					'min_gwp' => $min_gwp,
					//'banner' => $results,
					//'customer' => $array_customer,
					//'menu' => $result_menu,
					//'brand' => $this->allBrand(),
					//'event' => $this->CekEvent(),
					'product' => $this->getArticle(),
					//'blog' => $this->getBlog(),
					//'forum' => $this->getForum($cust_id),
					//'games' => $this->getGames($cust_id),
					//'youtube' => $this->CekVideo($cust_id),
				],
			];
			*/
		}
		if($dashboard_type == 'blog'){
			$data = array_merge($data,array('blog' => $this->getBlog()));
			/*
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success',
					'app_icon' => $app_icon,
					'min_coin' => 20000,
					'min_gwp' => $min_gwp,
					//'banner' => $results,
					//'customer' => $array_customer,
					//'menu' => $result_menu,
					//'brand' => $this->allBrand(),
					//'event' => $this->CekEvent(),
					//'product' => $this->getArticle(),
					'blog' => $this->getBlog(),
					//'forum' => $this->getForum($cust_id),
					//'games' => $this->getGames($cust_id),
					//'youtube' => $this->CekVideo($cust_id),
				],
			];
			*/
		}
		if($dashboard_type == 'forum'){
			$data = array_merge($data,array('forum' => $this->getForum($cust_id)));
			/*
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success',
					'app_icon' => $app_icon,
					'min_coin' => 20000,
					'min_gwp' => $min_gwp,
					//'banner' => $results,
					//'customer' => $array_customer,
					//'menu' => $result_menu,
					//'brand' => $this->allBrand(),
					//'event' => $this->CekEvent(),
					//'product' => $this->getArticle(),
					//'blog' => $this->getBlog(),
					'forum' => $this->getForum($cust_id),
					//'games' => $this->getGames($cust_id),
					//'youtube' => $this->CekVideo($cust_id),
				],
			];
			*/
		}
		if($dashboard_type == 'games'){
			$data = array_merge($data,array('games' => $this->getGames($cust_id)));
			/*
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success',
					'app_icon' => $app_icon,
					'min_coin' => 20000,
					'min_gwp' => $min_gwp,
					//'banner' => $results,
					//'customer' => $array_customer,
					//'menu' => $result_menu,
					//'brand' => $this->allBrand(),
					//'event' => $this->CekEvent(),
					//'product' => $this->getArticle(),
					//'blog' => $this->getBlog(),
					//'forum' => $this->getForum($cust_id),
					'games' => $this->getGames($cust_id),
					//'youtube' => $this->CekVideo($cust_id),
				],
			];
			*/
		}
		if($dashboard_type == 'youtube'){
			$data = array_merge($data,array('youtube' => $this->CekVideo($cust_id)));
			/*
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success',
					'app_icon' => $app_icon,
					'min_coin' => 20000,
					'min_gwp' => $min_gwp,
					//'banner' => $results,
					//'customer' => $array_customer,
					//'menu' => $result_menu,
					//'brand' => $this->allBrand(),
					//'event' => $this->CekEvent(),
					//'product' => $this->getArticle(),
					//'blog' => $this->getBlog(),
					//'forum' => $this->getForum($cust_id),
					//'games' => $this->getGames($cust_id),
					'youtube' => $this->CekVideo($cust_id),
				],
			];
			*/
		}
		
		// ce voucher CekVoucher($cust_id)
		if($dashboard_type == 'voucher'){
			$data = array_merge($data,array('voucher' => $this->CekVoucher($cust_id)));
		}
		
		// produk kamu banget nih getYourproduct
		if($dashboard_type == 'your_product'){
			$data = array_merge($data,array('your_product' => $this->getYourproduct($cust_id)));
			//$data = array_merge($data,array('your_product' => []));
		}
		
		// produk official brand getBrandproduct
		if($dashboard_type == 'shoes_socks_sandals'){
			$data = array_merge($data,array('shoes_product' => $this->getBrandproduct($cust_id,['SHOES', 'SANDALS', 'SOCKS'])));
		}
		if($dashboard_type == 'jersey_shirt_pants'){
			$data = array_merge($data,array('jersey_product' => $this->getBrandproduct($cust_id,['TOP', 'BOTTOM'])));
		}
		if($dashboard_type == 'accessories'){
			$data = array_merge($data,array('accessories_product' => $this->getBrandproduct($cust_id,['OTHERS', 'ACCESSORIES', 'EQUIPMENT', 'BALL', 'BAG'])));
		}
		
		// flash sale (detailPromo($cust_id))
		if($dashboard_type == 'flash_sale'){
			$data = array_merge($data,array('flash_sale' => $this->detailPromo($cust_id)));
		}
		
		// checkUlasan($cust_id)
		if($dashboard_type == 'review'){
			$data = array_merge($data,array('review' => $this->checkUlasan($cust_id)));
		}
		
		// newUpdate($cust_id)newUpdate($cust_id)
		if($dashboard_type == 'new_update'){
			$data = array_merge($data,array('new_update' => $this->newUpdate($cust_id)));
		}
		
		// newSeeproduct($cust_id)
		if($dashboard_type == 'last_viewed'){
			//$data = array_merge($data,array('last_viewed' => $this->newSeeproduct($cust_id)));
			$data = array_merge($data,array('last_viewed' => []));
		}
		
		if($dashboard_type == 'last_stock'){
			$data = array_merge($data,array('last_stock' => $this->detailLaststock($cust_id)));
		}
		
		/*
        $data = [
            'status' => [
                'code' => 200,
                'description' => 'Success',
                //'app_icon' => 'MainActivity', // TopscoreSepuluh2, MainActivity
                'app_icon' => $app_icon,
                'min_coin' => 20000,
				'min_gwp' => $min_gwp,
				'banner' => $results,
				'customer' => $array_customer,
				'menu' => $result_menu,
				'brand' => $this->allBrand(),
				'event' => $this->CekEvent(),
				'product' => $this->getArticle(),
			 	'blog' => $this->getBlog(),
				'forum' => $this->getForum($cust_id),
				'games' => $this->getGames($cust_id),
				'youtube' => $this->CekVideo($cust_id),
            ],
        ];
		*/
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
	
	/*
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
	*/
	
	private function cek_subsubcat($banner_id)
	{
		$cek = DB::table('banner_app_brand_details')
			   ->select("pssc_id","psc_name")
			   ->leftJoin('banner_app_brands', 'banner_app_brands.id', '=', 'banner_app_brand_details.bnb_id')
			   //->leftJoin('product_sub_sub_categories', 'product_sub_sub_categories.id', '=', 'banner_app_brand_details.pssc_id')
			   ->leftJoin('product_sub_categories', 'product_sub_categories.id', '=', 'banner_app_brand_details.pssc_id')
			   ->where("bn_id","=",$banner_id)
			   ->groupBy('pssc_id')
			   ->get();
		$no=0; $subsubcat='';
		foreach($cek as $raw){
		    $subsubcat.=$raw->psc_name.', ';
			//$subsubcat.=$raw->pssc_name.', ';
		}
		return rtrim($subsubcat,', ');
	}
	
	
	private function allBrand()
    {
        //Cache::forget('dashboard_brand');
        
        return Cache::remember('dashboard_brand', now()->addHours(24), function () {
        $banner_apps = DB::table('brands')
		->selectRaw('ts_brands.id as pid, br_name, br_slug, br_description, br_image, br_banner, created_at')
		->whereNotNull('br_image')
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
					$url_banner = $this->url_server."public/upload/brand/".$row->br_banner;
				} else {
					if($row->br_image!=''){
						$url_banner = $this->url_server."public/upload/brand/".$row->br_image;
					} else {
						$url_banner = "";
					}
				}
					if($row->br_image!=''){
						$url_banner = $this->url_server."api/brand/thumbs/".$row->br_image;
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
        });
    }
	
	// cek event
	private function CekEvent($cust_id, $key_search='')
    {
		$type_event = '2';
        $query_data = DB::table("event_tops")
		->selectRaw('ts_event_tops.id as pid, et_title, et_start_date, et_start_time, et_end_date, et_end_time, et_address, et_banner, et_price, et_terms, et_description, et_quota, et_lat, et_lang, et_type, et_link_online, et_score, et_maps_url, et_category, evt_category, evt_name, et_city, et_tournament')
		->leftJoin('event_types', 'event_types.id', '=', 'event_tops.evt_id')
		->where('et_delete', '=', '0')
		->whereRaw("CONCAT(ts_event_tops.et_start_date,' ',ts_event_tops.et_start_time) >= '".date('Y-m-d H:i:s')."'")
		//->orderBy('et_end_date')
		->orderBy('et_sort','ASC')
		->orderBy('evt_id','DESC')
		->orderBy('et_end_date','ASC')
        ->get();
        $results = array();
        if (!empty($query_data)) {
            $event_ids = $query_data->pluck('pid')->filter()->unique()->toArray();

            // Batch 1: event_orders sums & counts
            $orders_sum_by_et_id = [];
            $orders_count_by_et_id = [];
            if (!empty($event_ids)) {
                $order_sums = DB::table("event_orders")
                    ->selectRaw("et_id, COUNT(*) as cnt, SUM(eo_qty) as total_qty")
                    ->whereIn("et_id", $event_ids)
                    ->where("payment_status", "=", "1")
                    ->groupBy("et_id")
                    ->get();
                foreach ($order_sums as $os) {
                    $orders_sum_by_et_id[$os->et_id] = (int)$os->total_qty;
                    $orders_count_by_et_id[$os->et_id] = (int)$os->cnt;
                }
            }

            // Batch 2: event_master_forms
            $forms_by_et_id = [];
            $emf_ids = [];
            if (!empty($event_ids)) {
                $all_forms = DB::table("event_master_forms")
                    ->whereIn("et_id", $event_ids)
                    ->where("emf_delete", "=", "0")
                    ->orderBy('emf_sort')
                    ->get();
                foreach ($all_forms as $af) {
                    $forms_by_et_id[$af->et_id][] = $af;
                    $emf_ids[] = $af->id;
                }
            }

            // Batch 3: event_form_answers
            $quota_answers = [];
            if (!empty($event_ids) && !empty($emf_ids)) {
                $all_answers = DB::table("event_form_answers")
                    ->selectRaw("event_form_answers.et_id, event_form_answers.emf_id, efa_answer, COUNT(*) as jml")
                    ->leftJoin('event_orders', 'event_orders.id', '=', 'event_form_answers.eo_id')
                    ->where("payment_status", "=", "1")
                    ->whereIn("event_form_answers.et_id", $event_ids)
                    ->whereIn("event_form_answers.emf_id", array_unique($emf_ids))
                    ->groupBy("event_form_answers.et_id", "event_form_answers.emf_id", "efa_answer")
                    ->get();
                foreach ($all_answers as $ans) {
                    $key = $ans->et_id . '|||' . $ans->emf_id . '|||' . $ans->efa_answer;
                    $quota_answers[$key] = (int)$ans->jml;
                }
            }

            // Batch 4: user registration check
            $registered_events = [];
            if (!empty($event_ids) && !empty($cust_id)) {
                $all_registered = DB::table("event_orders")
                    ->where("u_id", '=', $cust_id)
                    ->where("payment_status", '=', '1')
                    ->whereIn("et_id", $event_ids)
                    ->pluck('et_id')
                    ->toArray();
                $registered_events = array_flip($all_registered);
            }

            // Batch 5: rating ulasan
            $arr_ratting = DB::table("ratting_apps")
                ->selectRaw('ts_ratting_apps.id as pid, ra_value, ra_description, ra_file, ts_ratting_apps.created_at, cust_name, cust_email, external_id, eo_name, eo_email, eo_phone, eo_price, eo_qty, eo_total, et_title, et_banner, et_price, eo_invoice, ts_event_tops.id as product_id')
                ->leftJoin('event_orders', 'event_orders.id', '=', 'ratting_apps.lookup_id')
                ->leftJoin('event_tops', 'event_tops.id', '=', 'event_orders.et_id')
                ->leftJoin('customers', 'customers.id', '=', 'ratting_apps.cust_id')
                ->where('ra_type', '=', 'event')
                ->orderBy('ratting_apps.created_at','DESC')
                ->first();
            $result_ratting = array();
            if ($arr_ratting) {
                $banner = !empty($arr_ratting->ra_file) ? url('public/ulasan_file').'/'.$arr_ratting->ra_file : '';
                $cust_email = $arr_ratting->cust_email ?: '';
                $image_url = "https://www.topsystem.id/upload/event/";
                $image = [isset($arr_ratting->et_banner) ? $arr_ratting->et_banner : ''];
                $result_ratting = [
                    'ulasan_id' => $arr_ratting->pid,
                    'ratting' => $arr_ratting->ra_value,
                    'description' => $arr_ratting->ra_description,
                    'cust_name' => $arr_ratting->eo_name ?? '',
                    'cust_email' => $arr_ratting->eo_email ?? '',
                    'external_id' => $arr_ratting->external_id ?? '',
                    'pos_invoice' => $arr_ratting->eo_invoice ?? '',
                    'banner' => $banner,
                    'product_name' => $arr_ratting->et_title ?? '',
                    'product_id' => $arr_ratting->product_id ?? 0,
                    'image_url' => $image_url,
                    'image' => $image,
                    'created_at' => $arr_ratting->created_at,
                ];
            }

            // Batch 6: teams
            $teams_by_et_id = [];
            if (!empty($event_ids)) {
                $all_teams = DB::table("event_teams")
                    ->select('event_teams.id', 'eteam_name', 'et_id')
                    ->whereIn('et_id', $event_ids)
                    ->where('eteam_delete', '=', '0')
                    ->orderBy('event_teams.eteam_name', 'ASC')
                    ->get();
                foreach ($all_teams as $tm) {
                    $teams_by_et_id[$tm->et_id][] = [
                        'team_id' => $tm->id,
                        'team_name' => $tm->eteam_name,
                    ];
                }
            }

            // Batch 7: tickets
            $tickets_by_et_id = [];
            if (!empty($event_ids)) {
                $all_tickets = DB::table("event_tickets")
                    ->select('event_tickets.id', 'eticket_name', 'eticket_price', 'eticket_quota', 'eticket_description', 'eticket_start_date', 'eticket_end_date', 'eticket_start_time', 'eticket_end_time', 'eticket_team', 'eticket_active', 'et_id')
                    ->whereIn('et_id', $event_ids)
                    ->where('eticket_active', '=', '1')
                    ->where('eticket_delete', '=', '0')
                    ->orderBy('event_tickets.eticket_team', 'DESC')
                    ->orderBy('event_tickets.eticket_name', 'ASC')
                    ->get();
                foreach ($all_tickets as $tck) {
                    $tickets_by_et_id[$tck->et_id][] = $tck;
                }
            }

            $arr_customer = DB::table("customers")->where("id","=",$cust_id)->first();

            foreach ($query_data as $row) {
                $event_users = isset($orders_sum_by_et_id[$row->pid]) ? $orders_sum_by_et_id[$row->pid] : 0;
				if($event_users>0){
					$jumlah_user = (int)$event_users;
				} else {
					$jumlah_user = 0;
				}

				if(empty($row->et_quota)){$quota=0;} else {$quota=$row->et_quota;}
				
				if($type_event == "2"){ // upcoming
				    $jumlah_user = $jumlah_user;
				    $quota = $quota;
				} else
				if($type_event == "1"){ // berlangsung
				    $jumlah_user = 0;
				    $quota = 0;
				} else { // selesai
				    $jumlah_user = 0;
				    $quota = 0;
				}
				
				// jika mencari salah 1 data event
				if (!empty($id_event)) {
				    $jumlah_user = (int)$event_users;
				    $quota = $row->et_quota;
				    
				    // cek jika sudah full, jadikan nol
					if($quota<=$jumlah_user){$quota=0; $jumlah_user=0;}
				    
				    if($row->et_start_date.' '.$row->et_start_time <= date("Y-m-d H:i:s")){
						$quota = 0;
					}
				} else {
					// cek jika sudah full, jadikan nol
					if(empty($row->et_quota)){$quota = (int)$event_users+10;} else {
					    if($row->et_quota<=(int)$event_users){$quota=0; $jumlah_user=0;}
					}
				}
				
				// data additional form
                $add_form = isset($forms_by_et_id[$row->pid]) ? $forms_by_et_id[$row->pid] : [];
				$array_form = array();
				$array_form_sub = array();
				if(!empty($add_form)){
					foreach($add_form as $rform){
						$array_form_sub = array();
						if(!empty($rform->emf_option)){
							$emf_option = explode(", ",$rform->emf_option);
						} else { $emf_option=array(); }
						
						// jika ada quota
						if(count($emf_option)>1){
							$opsi_jawaban=array();
							foreach($emf_option as $opilihan){
								$opsi_pilihan = explode("#",$opilihan);
								// jika ada quota
								if(count($opsi_pilihan)>1){
									// cek sudah berapa quota terpakai
                                    $q_key = $row->pid . '|||' . $rform->id . '|||' . $opsi_pilihan[0];
									$count_answers = isset($quota_answers[$q_key]) ? $quota_answers[$q_key] : 0;
									if($count_answers < $opsi_pilihan[1]){$sform=1;} else {$sform=0;}
								} else {$sform=1;}
								
								if($sform==1){
									$opsi_jawaban[] = $opsi_pilihan[0];
								}
							}
						} else {
							$opsi_jawaban = $emf_option;
						}
						
						$array_form []= [
							'form_id' => $rform->id,
							'form_title' => $rform->emf_title,
							'form_type' => $rform->emf_type,
							//'form_option' => $emf_option,
							'form_option' => $opsi_jawaban,
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
				
				$cust_terdaftar = isset($registered_events[$row->pid]) ? 1 : 0;
				
				// https://www.google.com/maps/search/?api=1&query=28.6139,77.2090
				if($row->et_maps_url){
				    $et_maps_url = $row->et_maps_url;
				} else {
				    $et_maps_url = "https://www.google.com/maps/search/?api=1&query=".$row->et_lat.','.$row->et_lang;
				}
				
				$result_registration = array();
				
				// cek jika event coming soon
				$event_coming = '0';
				$start_date_check = $row->et_start_date.' '.$row->et_start_time;
				//$end_date_check = date("Y-m-d H:i:s", strtotime('-14 days', strtotime($start_date_check)));
				$end_date_check = date("Y-m-d H:i:s", strtotime('-90 days', strtotime($start_date_check)));
				if($end_date_check<=date("Y-m-d H:i:s")){$event_coming=0;} else {$event_coming=1;}
				
				// cek team event
				$result_team = isset($teams_by_et_id[$row->pid]) ? $teams_by_et_id[$row->pid] : [];
				
				// cek ticket event
				$price_ticket = array();
				$result_ticket = array();
				$event_ticket = isset($tickets_by_et_id[$row->pid]) ? $tickets_by_et_id[$row->pid] : [];
				if (!empty($event_ticket)) {
                    $t_user_order_count = isset($orders_count_by_et_id[$row->pid]) ? $orders_count_by_et_id[$row->pid] : 0;
					foreach($event_ticket as $row_ticket){
						$result_ticket[] = [
							'ticket_id' => $row_ticket->id,
							'ticket_name' => $row_ticket->eticket_name,
							'ticket_price' => $row_ticket->eticket_price,
							'ticket_quota' => $row_ticket->eticket_quota,
							'ticket_description' => $row_ticket->eticket_description,
							'ticket_start' => $row_ticket->eticket_start_date.' '.$row_ticket->eticket_start_time,
							'ticket_end' => $row_ticket->eticket_end_date.' '.$row_ticket->eticket_end_time,
							'ticket_team' => $row_ticket->eticket_team,
							'ticket_user_order' => $t_user_order_count,
						];
						$price_ticket[] = $row_ticket->eticket_price;
					}
				}
				
				// cek min dan max price
				if(count($price_ticket)>1){
					$price_range = 'Rp '.number_format(min($price_ticket),0,',','.').' - '.'Rp '.number_format(max($price_ticket),0,',','.');
				} else {
					$price_range = 'Rp '.number_format($row->et_price,0,',','.');
				}
				
				if($row->et_category!="eksekutif"){
				
                $results[] = [
					'event_id' => $row->pid,
					//'evt_category' => $row->evt_category, 
					'event_category' => $row->evt_name,
					'event_type' => $row->et_type,
					'event_title' => $row->et_title,
					'event_start_date' => $row->et_start_date,
					'event_start_time' => $row->et_start_time,
					'event_end_date' => $row->et_end_date,
					'event_end_time' => $row->et_end_time,
					'event_address' => $row->et_address,
					'event_banner' => 'https://topsystem.id/upload/event/'.$row->et_banner,
					'event_price' => $row->et_price,
					'event_quota' => $quota, // tambahan
					'event_user_order' => $jumlah_user, // tambahan
					'event_terms' => $row->et_terms,
					'event_description' => $row->et_description,
					'event_lat' => $row->et_lat,
					'event_lang' => $row->et_lang,
					'event_score' => $row->et_score,
					'maps_url' => $et_maps_url,
					'event_city' => $row->et_city,
					//'event_webinar' => $link_webinar,
					'additional_form' => $array_form_sub,
					'cust_terdaftar' => $cust_terdaftar,
					'ratting' => $result_ratting,
					'registrasi_user' => $result_registration,
					'event_coming_soon' => $event_coming,
					'event_open_reg' => $end_date_check,
					'event_tournament' => $row->et_tournament,
					'event_team' => $result_team,
					'event_ticket' => $result_ticket,
					'event_price_detail' => $price_ticket,
					'event_price_text' => $price_range,
                ];
				}
            }

        }
		
        return $results;
    }
	
	// cek artikel terbaru
	private function getArticle($cust_id, $key_search='')
    {
        /*
        $exception = DB::table('exception_locations')->select('pl_code')
        ->leftJoin('product_locations', 'product_locations.id', '=', 'exception_locations.pl_id')->get();
        $exc_location = array();
        foreach ($exception as $exc) {
            $exc_location[] = $exc->pl_code;
        }
        */
        $exc_location = Cache::remember('exception_locations', now()->addHours(24), function () {
            return DB::table('exception_locations')
			->leftJoin('product_locations', 'product_locations.id', '=', 'exception_locations.pl_id')
			//->whereRaw("pl_code!=''")
			->pluck('pl_code')
			->toArray();
        });
		
		
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
		
        $stock = DB::table('product_stocks')
		->selectRaw('ts_product_stocks.id as pid, pc_name as category, br_name as brand, p_name as article, p_color as color, sz_name as size, ps_price_tag, p_price_tag, ps_sell_price, p_sell_price, p_main_image, p_image, psc_name, pssc_name, sold, SUM(ts_product_stocks.ps_qty) as stock, p_slug')
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
            $pids = $stock->pluck('pid')->filter()->unique()->toArray();
            $articles = $stock->pluck('article')->filter()->unique()->toArray();
            $brands = $stock->pluck('brand')->filter()->unique()->toArray();

            // Batch 1: check_discount
            $discounts_by_pid = [];
            if (!empty($pids)) {
                $now_date = date("Y-m-d");
                $now_time = date("H:i:s");
                $all_discounts = DB::table('product_discounts')
                    ->select('product_discount_details.pst_id', 'pd_type', 'pd_value')
                    ->leftJoin('product_discount_details', 'product_discount_details.pd_id', '=', 'product_discounts.id')
                    ->where('product_discounts.std_id', '=', '9')
                    ->whereIn('product_discount_details.pst_id', $pids)
                    ->where('pd_start', '<=', $now_date)
                    ->where('pd_date', '>=', $now_date)
                    ->where('pd_start_time', '<=', $now_time)
                    ->where('pd_end_time', '>=', $now_time)
                    ->get();
                foreach ($all_discounts as $cd) {
                    if (!isset($discounts_by_pid[$cd->pst_id])) {
                        $discounts_by_pid[$cd->pst_id] = $cd;
                    }
                }
            }

            // Batch 2: wishlists
            $wishlists_by_pid = [];
            if (!empty($pids) && !empty($cust_id)) {
                $all_wishlists = DB::table("wishlists")
                    ->whereIn('pst_id', $pids)
                    ->where('cust_id', $cust_id)
                    ->where('ws_delete', '=', '0')
                    ->pluck('pst_id')
                    ->toArray();
                $wishlists_by_pid = array_flip($all_wishlists);
            }

            // Batch 3: promo aktif apps
            $promos_by_pst = [];
            if (!empty($articles)) {
                $now_dt = date("Y-m-d H:i:s");
                $all_promos = DB::table("product_discount_details")
                    ->select('product_discount_details.id', 'product_discount_details.pst_id', 'pd_value', 'pd_type', 'products.p_name')
                    ->leftJoin('product_discounts', 'product_discounts.id', '=', 'product_discount_details.pd_id')
                    ->leftJoin('product_stocks', 'product_stocks.id', '=', 'product_discount_details.pst_id')
                    ->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
                    ->where('std_id', '=', '20')
                    ->whereRaw("CONCAT(ts_product_discounts.pd_start,' ',ts_product_discounts.pd_start_time)<='$now_dt'")
                    ->whereRaw("CONCAT(ts_product_discounts.pd_date,' ',ts_product_discounts.pd_end_time)>='$now_dt'")
                    ->whereIn('products.p_name', $articles)
                    ->where('products.p_delete', '=', '0')
                    ->get();
                foreach ($all_promos as $promo) {
                    if (!isset($promos_by_pst[$promo->pst_id])) {
                        $promos_by_pst[$promo->pst_id] = $promo;
                    }
                }
            }

            // Batch 4: stock colors
            $stock_colors_by_key = [];
            if (!empty($articles) && !empty($brands)) {
                $all_stock_colors = DB::table('product_stocks')
                    ->selectRaw("ts_product_stocks.id as pid, pc_name as category, br_name as brand, p_name as article, p_color as color, sz_name as size, ps_price_tag, p_price_tag, ps_sell_price, p_sell_price, p_main_image, p_image, psc_name, pssc_name, sold, SUM(ts_product_location_setups.pls_qty) as stock, CONCAT(p_name,' (',br_name,')') as p_name_brand, CONCAT(p_name,' (',p_color,')') as p_name_color")
                    ->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
                    ->leftJoin('product_categories', 'product_categories.id', '=', 'products.pc_id')
                    ->leftJoin('product_sub_categories', 'product_sub_categories.id', '=', 'products.psc_id')
                    ->leftJoin('product_sub_sub_categories', 'product_sub_sub_categories.id', '=', 'products.pssc_id')
                    ->leftJoin('brands', 'brands.id', '=', 'products.br_id')
                    ->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
                    ->leftJoin('product_location_setups', 'product_location_setups.pst_id', '=', 'product_stocks.id')
                    ->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
                    ->whereNotIn('pl_code', $exc_location)
                    ->where('pls_qty', '>', '0')
                    ->whereNotNull('p_main_image')
                    ->where('p_price_tag', '>', '0')
                    ->where('p_delete', '=', '0')
                    ->whereIn('brands.br_name', $brands)
                    ->whereIn('products.p_name', $articles)
                    ->groupBy('p_name_color')
                    ->get();
                foreach ($all_stock_colors as $sc) {
                    $key = $sc->article . '|||' . $sc->brand;
                    $stock_colors_by_key[$key][] = $sc;
                }
            }

            // Batch 5: ratings
            $ratings_by_article = [];
            if (!empty($articles)) {
                $all_ratings = DB::table("products")
                    ->select('id', 'p_name', 'p_ratting', 'p_user_ratting')
                    ->whereIn("p_name", $articles)
                    ->where("p_delete", "=", "0")
                    ->get();
                foreach ($all_ratings as $rt) {
                    $ratings_by_article[$rt->p_name][] = $rt;
                }
            }

            $arr_customer_cached = null;
            if ($cust_id) {
                $arr_customer_cached = DB::table("customers")->where("id", "=", $cust_id)->first();
            }

            foreach ($stock as $row) {
                $price = null;
                $price_tag = null;
                $store = null;
                $image_url = $this->url_server."api/product/600/";
                $image = ProductImageHelper::parseImages($row->p_main_image, $row->p_image);
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
                $check_discount = isset($discounts_by_pid[$row->pid]) ? $discounts_by_pid[$row->pid] : null;
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
				$arr_promo = isset($promos_by_pst[$row->pid]) ? $promos_by_pst[$row->pid] : null;
				
				$price_tag = $row->p_price_tag; // harga bandrol
				$sell_price = $row->p_sell_price; // harga jual
				$flag_buy1get1 = 0;
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
					} else
					if($arr_promo->pd_type == "b1g1"){ // jika B1G1
						$sell_price = $price_tag; // harga bandrol sebagai harga jual...
						$flag_buy1get1 = 1;
					}
					else {
						$sell_price = $sell_price;
					}
					
					if($price_tag!=$sell_price){
						$persen_potongan = 100 - (($sell_price / $price_tag) * 100);
					} else {
						$persen_potongan = 0;
					}
				}
				
				// tampilkan jumlah warna tersedia
				$key_color = $row->article . '|||' . $row->brand;
				$stock_color = isset($stock_colors_by_key[$key_color]) ? $stock_colors_by_key[$key_color] : [];
				
				$image = array();
				foreach($stock_color as $row_color){
					$img = ProductImageHelper::parseSingleImage($row_color->p_main_image, $row_color->p_image);
					if (!empty($img)) {
						$image[] = $img;
					}
				}
				$image_url = "https://topsystem.id/api/product/600/";
				
				// cek ratting produk [new function]
				$qproducts = isset($ratings_by_article[$row->article]) ? $ratings_by_article[$row->article] : [];
				$tot_ratting = 0;
    			$tot_ratting_user = 0;
    			$jml_data_product = 0;
			    foreach($qproducts as $raw_ratting){
			        if($raw_ratting->p_ratting>0){
        				$tot_ratting += $raw_ratting->p_ratting;
            			$tot_ratting_user += $raw_ratting->p_user_ratting;
            			$jml_data_product++;
			        }
			    }
				if($tot_ratting>0 && $jml_data_product>0){
				    $tot_ratting = round($tot_ratting/$jml_data_product,2);
				}
				
				if($tot_ratting==0){
				    $tot_ratting = 5;
				}
				
				$cashback_coin = (1/100) * $sell_price;
				if($flag_buy1get1==1){$label_promo = 'B1G1';} else {$label_promo = '';}
				
				$flag_wishlist = isset($wishlists_by_pid[$row->pid]) ? 1 : 0;
				
				// cek customers
				$add_persen = 0;
				if($cust_id && !$arr_promo){
					$arr_customer = $arr_customer_cached;
					if($arr_customer){
						// jika sebagai reseller
						if($arr_customer->cust_reseller==1){
							// kusus brand topscore {$row->brand}
							if($row->brand == "TOPSCORE"){
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
				$flag_sold=0;
				if(count($stock_color)>0){
					$results[] = [
						/*
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
						'product_sold' => $row->sold,
						'product_ratting' => $tot_ratting, //$ratting,
						'product_total_ulasan' => $tot_ratting_user,
						'product_image_url' => $image_url,
						'product_image' => $image,
						'product_wishlist' => $flag_wishlist,
						'product_jumlah_warna' => count($stock_color),
						'product_flag_buy1get1' => $flag_buy1get1,
						'product_cashback_coin' => round($cashback_coin),
						'product_label_promo' => $label_promo,
						*/
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
						'ratting' => $tot_ratting,
						'total_ulasan' => $tot_ratting_user,
						'wishlist' => $flag_wishlist,
						'jumlah_warna' => count($stock_color),
						'image_url' => $image_url,
						'image' => $image,
						'flag_buy1get1' => $flag_buy1get1,
						'cashback_coin' => round($cashback_coin),
						'label_promo' => $label_promo,
						'flag_sold' => $flag_sold,
						'slug' => $row->p_slug,
					];
				}
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
        if (!empty($query_data) && count($query_data) > 0) {
            $yt_ids = $query_data->pluck('pid')->toArray();
            $yt_views = [];
            if (!empty($yt_ids)) {
                $views = DB::table("youtube_views")->whereIn("yt_id", $yt_ids)->where("cust_id", $cust_id)->pluck('yt_id')->toArray();
                $yt_views = array_flip($views);
            }
            foreach ($query_data as $row) {
				$flag_view = isset($yt_views[$row->pid]) ? 1 : 0;
				
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
        if (!empty($query_data) && count($query_data) > 0) {
            $fo_ids = $query_data->pluck('id')->toArray();
            $fo_joins = [];
            if (!empty($fo_ids)) {
                $joins = DB::table("forum_joins")->whereIn("fo_id", $fo_ids)->where("cust_id", $cust_id)->pluck('fo_id')->toArray();
                $fo_joins = array_flip($joins);
            }
            foreach ($query_data as $row) {
                if (!empty($row->fo_image)) {
                    $image_url = $this->url_server."upload/forum/".$row->fo_image;
                } else {
					$image_url = '';
				}
                
				// cek join
				$flag_join = isset($fo_joins[$row->id]) ? 1 : 0;
				
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
		if (!empty($query) && count($query) > 0) {
		    $gm_ids = $query->pluck('id')->toArray();
		    
		    $gm_users = [];
		    $game_details_grouped = [];
		    
		    if (!empty($gm_ids)) {
		        $users = DB::table("game_users")->whereIn("gm_id", $gm_ids)->where("cust_id", $cust_id)->pluck('gm_id')->toArray();
		        $gm_users = array_flip($users);
		        
		        $all_details = DB::table('game_details')
					->select('game_details.id', 'gmd_description', 'gmd_image', 'gmd_option_1', 'gmd_option_2', 'gmd_option_3', 'gmd_option_4', 'gmd_option_5', 'gmd_true', 'game_details.created_at', 'gm_id')
					->leftJoin('users', 'users.id', '=', 'game_details.user_input')
					->whereIn('gm_id', $gm_ids)
					->where('gmd_delete', '=', "0")
					->orderBy("game_details.created_at",'ASC')
					->get();
				foreach ($all_details as $det) {
				    $game_details_grouped[$det->gm_id][] = $det;
				}
		    }

            foreach ($query as $row) {
                if(!isset($gm_users[$row->id])){
                    if (!empty($row->gm_image)) {
                        $image_url = $this->url_server."upload/game/".$row->gm_image;
                    } else {
                        $image_url = '';
                    }

					// tampilkan detail games *****
					$query_det = isset($game_details_grouped[$row->id]) ? $game_details_grouped[$row->id] : [];
					$result_details = array();
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
	
	// cek voucher
	private function CekVoucher($cust_id)
    {
        $query_data = DB::table("apps_vouchers")
		->selectRaw('ts_apps_vouchers.id as pid, avc_code, avc_discount, avc_min_order, avc_type, avc_status, avc_cashback, avc_due_date, created_at, avc_title, avc_banner, avc_terms, avc_category, avc_to')
		/*
		->where('avc_start', '<=', date("Y-m-d"))
		->where('avc_due_date', '>=', date("Y-m-d"))
		->where('acv_start_time', '<=', date("H:i:s"))
		->where('avc_end_time', '>=', date("H:i:s"))
		*/
		->whereRaw("CONCAT(ts_apps_vouchers.avc_start,' ',ts_apps_vouchers.acv_start_time)<='".date("Y-m-d H:i:s")."'")
		->whereRaw("CONCAT(ts_apps_vouchers.avc_due_date,' ',ts_apps_vouchers.avc_end_time)>='".date("Y-m-d H:i:s")."'")
		->where('avc_delete', '=', '0')
		->where('avc_status', '=', '1')
		//->where('avc_min_order', '<=', '20000')
		->orderBy('avc_code')
        ->get();
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
					if($arr_cust_voucher){$show=1;} else {$show=0;}
				} else
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
				} else
				if($row->avc_to == "produk"){
					$show=1; // jadikan nol lagi
				}
				else { $show=1; }
				
				// check product stock
				if(!empty($row->avc_banner)){
					$banner = $this->url_server.'upload/event/'.$row->avc_banner;
				} else {
					$banner = '';
				}
				
				if($show==1){
					$results[] = [
						'voucher_id' => $row->pid,
						'discount_title' => $row->avc_title,
						'discount' => $row->avc_discount,
						'min_order' => $row->avc_min_order,
						'discount_type' => $row->avc_type,
						'cashback' => (int)$row->avc_cashback,
						'status_discount' => $row->avc_status,
						'banner' => $banner,
						'terms' => $row->avc_terms,
						'due_date' => $row->avc_due_date,
						'category' => $row->avc_category,
					];
				}
            }
		}
        return $results;
    }
	
	// produk kamu banget nih
	private function getYourproduct($cust_id, $key_search='')
    {
        /*
        $exception = DB::table('exception_locations')->select('pl_code')
        ->leftJoin('product_locations', 'product_locations.id', '=', 'exception_locations.pl_id')->get();
        $exc_location = array();
        foreach ($exception as $exc) {
            $exc_location[] = $exc->pl_code;
        }
        */
        $exc_location = Cache::remember('exception_locations', now()->addHours(24), function () {
            return DB::table('exception_locations')
			->leftJoin('product_locations', 'product_locations.id', '=', 'exception_locations.pl_id')
			//->whereRaw("pl_code!=''")
			->pluck('pl_code')
			->toArray();
        });
		
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
		
		$in_category = ['RUNING', 'FG', 'IN', 'JERSEY', 'LIFESTYLE']; // default...
		$arr_customer = DB::table("customers")->where("id","=",$cust_id)->first();
		if($arr_customer){
			/*
			if($arr_customer->cust_select_category){
				$in_category = explode(", ",$arr_customer->cust_select_category);
			}
			*/
			if($arr_customer->cust_select_category){
				$cat_replace = str_replace(["  ",", "],["",","],$arr_customer->cust_select_category);
				$in_category = explode(",",$cat_replace);
			}
		} 
		
		$cacheKey = 'get_yourproduct' . md5(json_encode([
			'cust_id'      => $cust_id,
			'page'         => request('page', 1) // biar cache per halaman paginate
		]));
		
		//Cache::forget($cacheKey);
		
		return Cache::remember($cacheKey, now()->addHours(1), function () use ($cust_id, $key_search, $sub_kat, $subsub_kat, $brand, $product_name, $sorting_artikel, $sort_orders, $in_category, $exc_location) {
		
		$stock = DB::table('product_stocks')
		->selectRaw('ts_product_stocks.id as pid, pc_name as category, br_name as brand, p_name as article, p_color as color, sz_name as size, ps_price_tag, p_price_tag, ps_sell_price, p_sell_price, p_main_image, p_image, psc_name, pssc_name, sold, SUM(ts_product_stocks.ps_qty) as stock, p_slug')
        ->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
        ->leftJoin('product_categories', 'product_categories.id', '=', 'products.pc_id')
		->leftJoin('product_sub_categories', 'product_sub_categories.id', '=', 'products.psc_id')
		->leftJoin('product_sub_sub_categories', 'product_sub_sub_categories.id', '=', 'products.pssc_id')
        ->leftJoin('brands', 'brands.id', '=', 'products.br_id')
        ->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
        // cek stok
		//->leftJoin('product_location_setups', 'product_location_setups.pst_id', '=', 'product_stocks.id')
        //->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
        //->whereNotIn('pl_code', $exc_location)
        //->whereIn('product_locations.st_id', ['1'])
		//->whereNotIn('product_locations.st_id', array('3', '4', '5', '10', '11', '15'))
		->where('product_stocks.ps_qty', '>', '0')
		->where('p_delete', '=', '0')
		->whereNotNull('p_main_image')
		->whereIn('product_sub_sub_categories.pssc_name', $in_category) // kamu banget nih bro...
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
		->paginate(10);
		
        $results = array();
        if (!empty($stock)) {
            $pids = $stock->pluck('pid')->filter()->unique()->toArray();
            $articles = $stock->pluck('article')->filter()->unique()->toArray();
            $brands = $stock->pluck('brand')->filter()->unique()->toArray();

            // Batch 1: check_discount
            $discounts_by_pid = [];
            if (!empty($pids)) {
                $now_date = date("Y-m-d");
                $now_time = date("H:i:s");
                $all_discounts = DB::table('product_discounts')
                    ->select('product_discount_details.pst_id', 'pd_type', 'pd_value')
                    ->leftJoin('product_discount_details', 'product_discount_details.pd_id', '=', 'product_discounts.id')
                    ->where('product_discounts.std_id', '=', '9')
                    ->whereIn('product_discount_details.pst_id', $pids)
                    ->where('pd_start', '<=', $now_date)
                    ->where('pd_date', '>=', $now_date)
                    ->where('pd_start_time', '<=', $now_time)
                    ->where('pd_end_time', '>=', $now_time)
                    ->get();
                foreach ($all_discounts as $cd) {
                    if (!isset($discounts_by_pid[$cd->pst_id])) {
                        $discounts_by_pid[$cd->pst_id] = $cd;
                    }
                }
            }

            // Batch 2: wishlists
            $wishlists_by_pid = [];
            if (!empty($pids) && !empty($cust_id)) {
                $all_wishlists = DB::table("wishlists")
                    ->whereIn('pst_id', $pids)
                    ->where('cust_id', $cust_id)
                    ->where('ws_delete', '=', '0')
                    ->pluck('pst_id')
                    ->toArray();
                $wishlists_by_pid = array_flip($all_wishlists);
            }

            // Batch 3: promo aktif apps
            $promos_by_pst = [];
            if (!empty($articles)) {
                $now_dt = date("Y-m-d H:i:s");
                $all_promos = DB::table("product_discount_details")
                    ->select('product_discount_details.id', 'product_discount_details.pst_id', 'pd_value', 'pd_type', 'products.p_name')
                    ->leftJoin('product_discounts', 'product_discounts.id', '=', 'product_discount_details.pd_id')
                    ->leftJoin('product_stocks', 'product_stocks.id', '=', 'product_discount_details.pst_id')
                    ->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
                    ->where('std_id', '=', '20')
                    ->whereRaw("CONCAT(ts_product_discounts.pd_start,' ',ts_product_discounts.pd_start_time)<='$now_dt'")
                    ->whereRaw("CONCAT(ts_product_discounts.pd_date,' ',ts_product_discounts.pd_end_time)>='$now_dt'")
                    ->whereIn('products.p_name', $articles)
                    ->where('products.p_delete', '=', '0')
                    ->get();
                foreach ($all_promos as $promo) {
                    if (!isset($promos_by_pst[$promo->pst_id])) {
                        $promos_by_pst[$promo->pst_id] = $promo;
                    }
                }
            }

            // Batch 4: stock colors
            $stock_colors_by_key = [];
            if (!empty($articles) && !empty($brands)) {
                $all_stock_colors = DB::table('product_stocks')
                    ->selectRaw("ts_product_stocks.id as pid, pc_name as category, br_name as brand, p_name as article, p_color as color, sz_name as size, ps_price_tag, p_price_tag, ps_sell_price, p_sell_price, p_main_image, p_image, psc_name, pssc_name, sold, SUM(ts_product_location_setups.pls_qty) as stock, CONCAT(p_name,' (',br_name,')') as p_name_brand, CONCAT(p_name,' (',p_color,')') as p_name_color")
                    ->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
                    ->leftJoin('product_categories', 'product_categories.id', '=', 'products.pc_id')
                    ->leftJoin('product_sub_categories', 'product_sub_categories.id', '=', 'products.psc_id')
                    ->leftJoin('product_sub_sub_categories', 'product_sub_sub_categories.id', '=', 'products.pssc_id')
                    ->leftJoin('brands', 'brands.id', '=', 'products.br_id')
                    ->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
                    ->leftJoin('product_location_setups', 'product_location_setups.pst_id', '=', 'product_stocks.id')
                    ->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
                    ->whereNotIn('pl_code', $exc_location)
                    ->where('pls_qty', '>', '0')
                    ->whereNotNull('p_main_image')
                    ->where('p_price_tag', '>', '0')
                    ->where('p_delete', '=', '0')
                    ->whereIn('brands.br_name', $brands)
                    ->whereIn('products.p_name', $articles)
                    ->groupBy('p_name_color')
                    ->get();
                foreach ($all_stock_colors as $sc) {
                    $key = $sc->article . '|||' . $sc->brand;
                    $stock_colors_by_key[$key][] = $sc;
                }
            }

            // Batch 5: ratings
            $ratings_by_article = [];
            if (!empty($articles)) {
                $all_ratings = DB::table("products")
                    ->select('id', 'p_name', 'p_ratting', 'p_user_ratting')
                    ->whereIn("p_name", $articles)
                    ->where("p_delete", "=", "0")
                    ->get();
                foreach ($all_ratings as $rt) {
                    $ratings_by_article[$rt->p_name][] = $rt;
                }
            }

            $arr_customer_cached = null;
            if ($cust_id) {
                $arr_customer_cached = DB::table("customers")->where("id", "=", $cust_id)->first();
            }

            foreach ($stock as $row) {
                $price = null;
                $price_tag = null;
                $image_url = null;
                $store = null;
                $image_url = $this->url_server."api/product/600/";
                $image = ProductImageHelper::parseImages($row->p_main_image, $row->p_image);
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
                $check_discount = isset($discounts_by_pid[$row->pid]) ? $discounts_by_pid[$row->pid] : null;
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
				$arr_promo = isset($promos_by_pst[$row->pid]) ? $promos_by_pst[$row->pid] : null;
				
				$price_tag = $row->p_price_tag; // harga bandrol
				$sell_price = $row->p_sell_price; // harga jual
				$flag_buy1get1 = 0;
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
					} else
					if($arr_promo->pd_type == "b1g1"){ // jika B1G1
						$sell_price = $price_tag; // harga bandrol sebagai harga jual...
						$flag_buy1get1 = 1;
					}
					else {
						$sell_price = $sell_price;
					}
					
					if($price_tag!=$sell_price){
						$persen_potongan = 100 - (($sell_price / $price_tag) * 100);
					} else {
						$persen_potongan = 0;
					}
				}
				
				// tampilkan jumlah warna tersedia
				$key_color = $row->article . '|||' . $row->brand;
				$stock_color = isset($stock_colors_by_key[$key_color]) ? $stock_colors_by_key[$key_color] : [];
				
				$image = array();
				foreach($stock_color as $row_color){
					$img = ProductImageHelper::parseSingleImage($row_color->p_main_image, $row_color->p_image);
					if (!empty($img)) {
						$image[] = $img;
					}
				}
				$image_url = "https://topsystem.id/api/product/600/";
				
				// cek ratting produk [new function]
				$qproducts = isset($ratings_by_article[$row->article]) ? $ratings_by_article[$row->article] : [];
				$tot_ratting = 0;
    			$tot_ratting_user = 0;
    			$jml_data_product = 0;
			    foreach($qproducts as $raw_ratting){
			        if($raw_ratting->p_ratting>0){
        				$tot_ratting += $raw_ratting->p_ratting;
            			$tot_ratting_user += $raw_ratting->p_user_ratting;
            			$jml_data_product++;
			        }
			    }
				if($tot_ratting>0 && $jml_data_product>0){
				    $tot_ratting = round($tot_ratting/$jml_data_product,2);
				}
				
				if($tot_ratting==0){
				    $tot_ratting = 5;
				}
				
				$cashback_coin = (1/100) * $sell_price;
				if($flag_buy1get1==1){$label_promo = 'B1G1';} else {$label_promo = '';}
				
				$flag_wishlist = isset($wishlists_by_pid[$row->pid]) ? 1 : 0;
				
				// cek customers
				$add_persen = 0;
				if($cust_id && !$arr_promo){
					$arr_customer = $arr_customer_cached;
					if($arr_customer){
						// jika sebagai reseller
						if($arr_customer->cust_reseller==1){
							// kusus brand topscore {$row->brand}
							if($row->brand == "TOPSCORE"){
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
				$flag_sold=0;
				if(count($stock_color)>0){
					$results[] = [
						/*
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
						'product_sold' => $row->sold,
						'product_ratting' => $tot_ratting, //$ratting,
						'product_total_ulasan' => $tot_ratting_user,
						'product_image_url' => $image_url,
						'product_image' => $image,
						'product_wishlist' => $flag_wishlist,
						'product_jumlah_warna' => count($stock_color),
						'product_flag_buy1get1' => $flag_buy1get1,
						'product_cashback_coin' => round($cashback_coin),
						'product_label_promo' => $label_promo,
						*/
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
						'ratting' => $tot_ratting,
						'total_ulasan' => $tot_ratting_user,
						'wishlist' => $flag_wishlist,
						'jumlah_warna' => count($stock_color),
						'image_url' => $image_url,
						'image' => $image,
						'flag_buy1get1' => $flag_buy1get1,
						'cashback_coin' => round($cashback_coin),
						'label_promo' => $label_promo,
						'flag_sold' => $flag_sold,
						'slug' => $row->p_slug,
					];
				}
            }

        }
        return $results;
        
		});
    }
	
	// produk official brand
	private function getBrandproduct($cust_id, $key_search)
    {
        /*
        $exception = DB::table('exception_locations')->select('pl_code')
        ->leftJoin('product_locations', 'product_locations.id', '=', 'exception_locations.pl_id')->get();
        $exc_location = array();
        foreach ($exception as $exc) {
            $exc_location[] = $exc->pl_code;
        }
        */
        $exc_location = Cache::remember('exception_locations', now()->addHours(24), function () {
            return DB::table('exception_locations')
			->leftJoin('product_locations', 'product_locations.id', '=', 'exception_locations.pl_id')
			//->whereRaw("pl_code!=''")
			->pluck('pl_code')
			->toArray();
        });
		
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
		$in_category = ['RUNING', 'FG', 'IN', 'JERSEY', 'LIFESTYLE']; // default...
		$arr_customer = DB::table("customers")->where("id","=",$cust_id)->first();
		if($arr_customer){
			if($arr_customer->cust_select_category){
				$in_category = explode(", ",$arr_customer->cust_select_category);
			}
		} 
		*/
		
		$in_category = $key_search;
		
		$cacheKey = 'get_productcategory' . md5(json_encode([
			'in_category'      => $in_category,
			'page'         => request('page', 1) // biar cache per halaman paginate
		]));

		return Cache::remember($cacheKey, now()->addHours(1), function () use ($cust_id, $key_search, $sub_kat, $subsub_kat, $brand, $product_name, $sorting_artikel, $sort_orders, $in_category, $exc_location) {
		
		$stock = DB::table('product_stocks')
		->selectRaw('ts_product_stocks.id as pid, pc_name as category, br_name as brand, p_name as article, p_color as color, sz_name as size, ps_price_tag, p_price_tag, ps_sell_price, p_sell_price, p_main_image, p_image, psc_name, pssc_name, sold, SUM(ts_product_stocks.ps_qty) as stock, p_slug')
        ->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
        ->leftJoin('product_categories', 'product_categories.id', '=', 'products.pc_id')
		->leftJoin('product_sub_categories', 'product_sub_categories.id', '=', 'products.psc_id')
		->leftJoin('product_sub_sub_categories', 'product_sub_sub_categories.id', '=', 'products.pssc_id')
        ->leftJoin('brands', 'brands.id', '=', 'products.br_id')
        ->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
        // cek stok
		//->leftJoin('product_location_setups', 'product_location_setups.pst_id', '=', 'product_stocks.id')
        //->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
        //->whereNotIn('pl_code', $exc_location)
        //->whereIn('product_locations.st_id', ['1'])
		//->whereNotIn('product_locations.st_id', array('3', '4', '5', '10', '11', '15'))
		->where('product_stocks.ps_qty', '>', '0')
		->where('p_delete', '=', '0')
		->whereNotNull('p_main_image')
		//->whereIn('product_sub_sub_categories.pssc_name', $in_category) // kamu banget nih bro...
		->whereIn('product_sub_categories.psc_name', $in_category)
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
		->paginate(10);
		
        $results = array();
        if (!empty($stock)) {
            $pids = $stock->pluck('pid')->filter()->unique()->toArray();
            $articles = $stock->pluck('article')->filter()->unique()->toArray();
            $brands = $stock->pluck('brand')->filter()->unique()->toArray();

            // Batch 1: check_discount
            $discounts_by_pid = [];
            if (!empty($pids)) {
                $now_date = date("Y-m-d");
                $now_time = date("H:i:s");
                $all_discounts = DB::table('product_discounts')
                    ->select('product_discount_details.pst_id', 'pd_type', 'pd_value')
                    ->leftJoin('product_discount_details', 'product_discount_details.pd_id', '=', 'product_discounts.id')
                    ->where('product_discounts.std_id', '=', '9')
                    ->whereIn('product_discount_details.pst_id', $pids)
                    ->where('pd_start', '<=', $now_date)
                    ->where('pd_date', '>=', $now_date)
                    ->where('pd_start_time', '<=', $now_time)
                    ->where('pd_end_time', '>=', $now_time)
                    ->get();
                foreach ($all_discounts as $cd) {
                    if (!isset($discounts_by_pid[$cd->pst_id])) {
                        $discounts_by_pid[$cd->pst_id] = $cd;
                    }
                }
            }

            // Batch 2: wishlists
            $wishlists_by_pid = [];
            if (!empty($pids) && !empty($cust_id)) {
                $all_wishlists = DB::table("wishlists")
                    ->whereIn('pst_id', $pids)
                    ->where('cust_id', $cust_id)
                    ->where('ws_delete', '=', '0')
                    ->pluck('pst_id')
                    ->toArray();
                $wishlists_by_pid = array_flip($all_wishlists);
            }

            // Batch 3: promo aktif apps
            $promos_by_pst = [];
            if (!empty($articles)) {
                $now_dt = date("Y-m-d H:i:s");
                $all_promos = DB::table("product_discount_details")
                    ->select('product_discount_details.id', 'product_discount_details.pst_id', 'pd_value', 'pd_type', 'products.p_name')
                    ->leftJoin('product_discounts', 'product_discounts.id', '=', 'product_discount_details.pd_id')
                    ->leftJoin('product_stocks', 'product_stocks.id', '=', 'product_discount_details.pst_id')
                    ->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
                    ->where('std_id', '=', '20')
                    ->whereRaw("CONCAT(ts_product_discounts.pd_start,' ',ts_product_discounts.pd_start_time)<='$now_dt'")
                    ->whereRaw("CONCAT(ts_product_discounts.pd_date,' ',ts_product_discounts.pd_end_time)>='$now_dt'")
                    ->whereIn('products.p_name', $articles)
                    ->where('products.p_delete', '=', '0')
                    ->get();
                foreach ($all_promos as $promo) {
                    if (!isset($promos_by_pst[$promo->pst_id])) {
                        $promos_by_pst[$promo->pst_id] = $promo;
                    }
                }
            }

            // Batch 4: stock colors
            $stock_colors_by_key = [];
            if (!empty($articles) && !empty($brands)) {
                $all_stock_colors = DB::table('product_stocks')
                    ->selectRaw("ts_product_stocks.id as pid, pc_name as category, br_name as brand, p_name as article, p_color as color, sz_name as size, ps_price_tag, p_price_tag, ps_sell_price, p_sell_price, p_main_image, p_image, psc_name, pssc_name, sold, SUM(ts_product_location_setups.pls_qty) as stock, CONCAT(p_name,' (',br_name,')') as p_name_brand, CONCAT(p_name,' (',p_color,')') as p_name_color")
                    ->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
                    ->leftJoin('product_categories', 'product_categories.id', '=', 'products.pc_id')
                    ->leftJoin('product_sub_categories', 'product_sub_categories.id', '=', 'products.psc_id')
                    ->leftJoin('product_sub_sub_categories', 'product_sub_sub_categories.id', '=', 'products.pssc_id')
                    ->leftJoin('brands', 'brands.id', '=', 'products.br_id')
                    ->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
                    ->leftJoin('product_location_setups', 'product_location_setups.pst_id', '=', 'product_stocks.id')
                    ->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
                    ->whereNotIn('pl_code', $exc_location)
                    ->where('pls_qty', '>', '0')
                    ->whereNotNull('p_main_image')
                    ->where('p_price_tag', '>', '0')
                    ->where('p_delete', '=', '0')
                    ->whereIn('brands.br_name', $brands)
                    ->whereIn('products.p_name', $articles)
                    ->groupBy('p_name_color')
                    ->get();
                foreach ($all_stock_colors as $sc) {
                    $key = $sc->article . '|||' . $sc->brand;
                    $stock_colors_by_key[$key][] = $sc;
                }
            }

            // Batch 5: ratings
            $ratings_by_article = [];
            if (!empty($articles)) {
                $all_ratings = DB::table("products")
                    ->select('id', 'p_name', 'p_ratting', 'p_user_ratting')
                    ->whereIn("p_name", $articles)
                    ->where("p_delete", "=", "0")
                    ->get();
                foreach ($all_ratings as $rt) {
                    $ratings_by_article[$rt->p_name][] = $rt;
                }
            }

            $arr_customer_cached = null;
            if ($cust_id) {
                $arr_customer_cached = DB::table("customers")->where("id", "=", $cust_id)->first();
            }

            foreach ($stock as $row) {
                $price = null;
                $price_tag = null;
                $store = null;
                $image_url = $this->url_server."api/product/600/";
                $image = ProductImageHelper::parseImages($row->p_main_image, $row->p_image);
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
                $check_discount = isset($discounts_by_pid[$row->pid]) ? $discounts_by_pid[$row->pid] : null;
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
				$arr_promo = isset($promos_by_pst[$row->pid]) ? $promos_by_pst[$row->pid] : null;
				
				$price_tag = $row->p_price_tag; // harga bandrol
				$sell_price = $row->p_sell_price; // harga jual
				$flag_buy1get1 = 0;
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
					} else
					if($arr_promo->pd_type == "b1g1"){ // jika B1G1
						$sell_price = $price_tag; // harga bandrol sebagai harga jual...
						$flag_buy1get1 = 1;
					}
					else {
						$sell_price = $sell_price;
					}
					
					if($price_tag!=$sell_price){
						$persen_potongan = 100 - (($sell_price / $price_tag) * 100);
					} else {
						$persen_potongan = 0;
					}
				}
				
				// tampilkan jumlah warna tersedia
				$key_color = $row->article . '|||' . $row->brand;
				$stock_color = isset($stock_colors_by_key[$key_color]) ? $stock_colors_by_key[$key_color] : [];
				
				$image = array();
				foreach($stock_color as $row_color){
					$img = ProductImageHelper::parseSingleImage($row_color->p_main_image, $row_color->p_image);
					if (!empty($img)) {
						$image[] = $img;
					}
				}
				$image_url = "https://topsystem.id/api/product/600/";
				
				// cek ratting produk [new function]
				$qproducts = isset($ratings_by_article[$row->article]) ? $ratings_by_article[$row->article] : [];
				$tot_ratting = 0;
    			$tot_ratting_user = 0;
    			$jml_data_product = 0;
			    foreach($qproducts as $raw_ratting){
			        if($raw_ratting->p_ratting>0){
        				$tot_ratting += $raw_ratting->p_ratting;
            			$tot_ratting_user += $raw_ratting->p_user_ratting;
            			$jml_data_product++;
			        }
			    }
				if($tot_ratting>0 && $jml_data_product>0){
				    $tot_ratting = round($tot_ratting/$jml_data_product,2);
				}
				
				if($tot_ratting==0){
				    $tot_ratting = 5;
				}
				
				$cashback_coin = (1/100) * $sell_price;
				if($flag_buy1get1==1){$label_promo = 'B1G1';} else {$label_promo = '';}
				
				$flag_wishlist = isset($wishlists_by_pid[$row->pid]) ? 1 : 0;
				
				// cek customers
				$add_persen = 0;
				if($cust_id && !$arr_promo){
					$arr_customer = $arr_customer_cached;
					if($arr_customer){
						// jika sebagai reseller
						if($arr_customer->cust_reseller==1){
							// kusus brand topscore {$row->brand}
							if($row->brand == "TOPSCORE"){
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
				$flag_sold=0;
				if(count($stock_color)>0){
					$results[] = [
						/*
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
						'product_sold' => $row->sold,
						'product_ratting' => $tot_ratting, //$ratting,
						'product_total_ulasan' => $tot_ratting_user,
						'product_image_url' => $image_url,
						'product_image' => $image,
						'product_wishlist' => $flag_wishlist,
						'product_jumlah_warna' => count($stock_color),
						'product_flag_buy1get1' => $flag_buy1get1,
						'product_cashback_coin' => round($cashback_coin),
						'product_label_promo' => $label_promo,
						*/
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
						'ratting' => $tot_ratting,
						'total_ulasan' => $tot_ratting_user,
						'wishlist' => $flag_wishlist,
						'jumlah_warna' => count($stock_color),
						'image_url' => $image_url,
						'image' => $image,
						'flag_buy1get1' => $flag_buy1get1,
						'cashback_coin' => round($cashback_coin),
						'label_promo' => $label_promo,
						'flag_sold' => $flag_sold,
						'slug' => $row->p_slug,
					];
				}
            }

        }
        return $results;
        
		});
    }
    
    // flash sale promo
	public function detailPromo($cust_id)
    {
		/*
		$exception = DB::table('exception_locations')->select('pl_code')
        ->leftJoin('product_locations', 'product_locations.id', '=', 'exception_locations.pl_id')->get();
        $exc_location = array();
        foreach ($exception as $exc) {
            $exc_location[] = $exc->pl_code;
        }
        */
        $exc_location = Cache::remember('exception_locations', now()->addHours(24), function () {
            return DB::table('exception_locations')
			->leftJoin('product_locations', 'product_locations.id', '=', 'exception_locations.pl_id')
			//->whereRaw("pl_code!=''")
			->pluck('pl_code')
			->toArray();
        });
		
		// cek promo by no MOM
		$by_mom = DB::table('product_discounts')->select('id')
				  //->where("no_mom","=",$arr_promo->no_mom)
				  ->where('std_id', '=', '20') // kusus aplikasi
				  ->whereNotIn('pd_type', ['fix_special'])
				  /*
				  ->where('pd_start', '<=', date("Y-m-d"))
				  ->where('pd_date', '>=', date("Y-m-d"))
				  ->where('pd_start_time', '<=', date("H:i:s"))
				  ->where('pd_end_time', '>=', date("H:i:s"))
				  */
				  ->whereRaw("CONCAT(ts_product_discounts.pd_start,' ',ts_product_discounts.pd_start_time)<='".date("Y-m-d H:i:s")."'")
		          ->whereRaw("CONCAT(ts_product_discounts.pd_date,' ',ts_product_discounts.pd_end_time)>='".date("Y-m-d H:i:s")."'")
				  ->where('pd_flash_sale', '=', "1") // kusus flash sale
				  //->groupBy('no_mom')
				  ->get();
		if(!$by_mom){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data promo flash sale tidak ditemukan!'
                ]
            ];
            return response()->json($data);
		}

        $in_pdid = array();
        foreach ($by_mom as $mom) {
            $in_pdid[] = $mom->id;
        }
		
		
		if($cust_id){$page_limit=10;} else {$page_limit=200;}
		
		$data_products = DB::table('product_discount_details')
		->selectRaw("ts_product_discount_details.id, ts_product_discount_details.pst_id, pc_name as category, br_name as brand, p_name as article, p_color as color, sz_name as size, ps_price_tag, p_price_tag, ps_sell_price, p_sell_price, p_main_image, p_image, psc_name, pssc_name, sold, SUM(ts_product_stocks.ps_qty) as stock, CONCAT(p_name,' (',br_name,')') as p_name_brand, pd_type, pd_value, p_slug")
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
        ->orderBy("products.p_name","ASC")
        ->paginate(10);
		
		$pid = '';
        $results = array();
		$address = array();
        if (count($data_products)>0) {
            $articles = $data_products->pluck('article')->filter()->unique()->toArray();
            $brands = $data_products->pluck('brand')->filter()->unique()->toArray();

            $stock_colors_by_key = [];
            if (!empty($articles) && !empty($brands)) {
                $all_stock_colors = DB::table('product_stocks')
                    ->selectRaw("ts_product_stocks.id as pid, pc_name as category, br_name as brand, p_name as article, p_color as color, sz_name as size, ps_price_tag, p_price_tag, ps_sell_price, p_sell_price, p_main_image, p_image, psc_name, pssc_name, sold, SUM(ts_product_location_setups.pls_qty) as stock, CONCAT(p_name,' (',br_name,')') as p_name_brand, CONCAT(p_name,' (',p_color,')') as p_name_color, p_slug")
                    ->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
                    ->leftJoin('product_categories', 'product_categories.id', '=', 'products.pc_id')
                    ->leftJoin('product_sub_categories', 'product_sub_categories.id', '=', 'products.psc_id')
                    ->leftJoin('product_sub_sub_categories', 'product_sub_sub_categories.id', '=', 'products.pssc_id')
                    ->leftJoin('brands', 'brands.id', '=', 'products.br_id')
                    ->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
                    ->leftJoin('product_location_setups', 'product_location_setups.pst_id', '=', 'product_stocks.id')
                    ->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
                    ->whereNotIn('pl_code', $exc_location)
                    ->where('pls_qty', '>', '0')
                    ->whereNotNull('p_main_image')
                    ->where('p_price_tag', '>', '0')
                    ->where('p_delete', '=', '0')
                    ->whereIn('brands.br_name', $brands)
                    ->whereIn('products.p_name', $articles)
                    ->groupBy('p_name_color')
                    ->get();
                foreach ($all_stock_colors as $sc) {
                    $key = $sc->article . '|||' . $sc->brand;
                    $stock_colors_by_key[$key][] = $sc;
                }
            }

            foreach ($data_products as $row) {
				$price = null;
                $price_tag = null;
                $store = null;
                $image_url = $this->url_server."api/product/600/";
                $image = ProductImageHelper::parseImages($row->p_main_image, $row->p_image);
                
				
				$price_tag = $row->p_price_tag; // harga bandrol
				
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
					$sell_price = $price_tag;
					$flag_buy1get1 = 1;
				}
				else {
					$sell_price = $sell_price;
				}
				
				if($price_tag!=$sell_price){
					$persen_potongan = 100 - (($sell_price / $price_tag) * 100);
				} else {
					$persen_potongan = 0;
				}
				
				// tampilkan jumlah warna tersedia
				$key_color = $row->article . '|||' . $row->brand;
				$stock_color = isset($stock_colors_by_key[$key_color]) ? $stock_colors_by_key[$key_color] : [];
				
				$image = array();
				foreach($stock_color as $row_color){
					$img = ProductImageHelper::parseSingleImage($row_color->p_main_image, $row_color->p_image);
					if (!empty($img)) {
						$image[] = $img;
					}
				}
				$image_url = $this->url_server."api/product/600/";
				
				if($flag_buy1get1==1){$label_promo='B1G1';} else {$label_promo='';}
				if($row->brand == "TOPSCORE"){$flag_sold=0;} else {$flag_sold=1;}
				$flag_sold=0;
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
					'additional_discount' => 0, // kusus promo additional diskon=0
					'sold' => $row->sold,
					'ratting' => 0, //$ratting,
					'total_ulasan' => 0,
					'jumlah_warna' => count($stock_color),
                    'image_url' => $image_url,
                    'image' => $image,
					'flag_buy1get1' => $flag_buy1get1,
					'label_promo' => $label_promo,
					'flag_sold' => $flag_sold,
					'slug' => $row->p_slug,
                ];
            }
			
			
			// cek TNC promo
			/*
			if($arr_promo->pd_tnc){$promo_tnc=$arr_promo->pd_tnc;} else {$promo_tnc='';}
			if($arr_promo->pd_use){$promo_use=$arr_promo->pd_use;} else {$promo_use='';}
			*/
			
			$data = $results;
			/*
			$data = [
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
			*/
        } 
		// data produk tidak ada
		/*
		else {
			$data = [
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
		*/
        return $results;
    }
    
    // cek ulasan pending
	private function checkUlasan($cust_id)
	{
		$query_pos = DB::table("pos_transactions")
		->select('pos_transactions.id as id', 'pos_invoice', 'pos_transactions.created_at')
		->where('cust_id', '=', $cust_id)
		->where('is_apps', '=', 1)
		//->whereNotIn('pos_transactions.pos_status', ['CANCEL','UNPAID'])
		->whereIn('pos_transactions.pos_status', ['DONE'])
		->orderBy('pos_transactions.created_at','DESC')
        ->get();
		$result_pos = array();
		if(!empty($query_pos) && count($query_pos) > 0){
		    $pos_ids = $query_pos->pluck('id')->toArray();
		    
		    $ratting_pos_ids = [];
		    if (!empty($pos_ids)) {
		        $ratting_pos = DB::table('ratting_apps')
		            ->where('ra_type', '=', 'pos')
		            ->where('cust_id', '=', $cust_id)
		            ->whereIn('lookup_id', $pos_ids)
		            ->pluck('lookup_id')->toArray();
		        $ratting_pos_ids = array_flip($ratting_pos);
		    }

		    $pos_details_grouped = [];
		    if (!empty($pos_ids)) {
		        $all_pos_details = DB::table("pos_transaction_details")
		            ->select('p_name', 'p_image', 'br_name', 'p_main_image', 'p_slug', 'p_color', 'sz_name', 'pos_transaction_details.pt_id')
		            ->leftJoin('product_stocks', 'product_stocks.id', '=', 'pos_transaction_details.pst_id')
		            ->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
		            ->leftJoin('brands', 'brands.id', '=', 'products.br_id')
		            ->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
		            ->whereIn("pos_transaction_details.pt_id", $pos_ids)
		            ->get();
		            
		        foreach ($all_pos_details as $det) {
		            if (!isset($pos_details_grouped[$det->pt_id])) {
		                $pos_details_grouped[$det->pt_id] = $det;
		            }
		        }
		    }

			foreach($query_pos as $raw){
				$check_product = isset($pos_details_grouped[$raw->id]) ? $pos_details_grouped[$raw->id] : null;
				// cek sudah ratting
				$check_ratting = isset($ratting_pos_ids[$raw->id]) ? 1 : 0;
				// jika belum memberikan ratting dan produk ketemu
				if($check_ratting==0 && $check_product){
					/*
					$image_url = '';
					$image = array();
	
					if (!empty($check_product->p_main_image)) {
						$image_url = $this->url_server."api/product/600/".$check_product->p_main_image;
						$image[] = $check_product->p_main_image;
					}
					if (!empty($check_product->p_image) AND $check_product->p_image != '||||||') {
						$exp = explode('|', $check_product->p_image);
						$total = count($exp);
						for ($i = 0; $i < $total; $i++) {
							if (!empty($exp[$i])) {
								$image[] = $exp[$i];
							}
						}
					}
					*/
					$image_url = $this->url_server."api/product/600/".$check_product->p_main_image;
					$result_pos[] = [
						'id' => $raw->id,
						'type_data' => 'pos',
						'name' => $check_product->br_name.' '.$check_product->p_name.' ('.$check_product->p_color.') ('.$check_product->sz_name.')',
						'image_url' => $image_url,
						//'image' => $image,
						'created_at' => $raw->created_at,
					];
				}
			}
		}
		// $image_url = "https://www.topsystem.id/upload/event/";
		
		// ulasan event check
		$query_event = DB::table("event_orders")
		->select('event_orders.id as id', 'eo_invoice', 'event_orders.created_at', 'et_banner', 'et_title')
		->leftJoin('event_tops', 'event_tops.id', '=', 'event_orders.et_id')
		->where('event_orders.u_id', '=', $cust_id)
		->where('payment_status', '=', 1)
		->where('et_end_date', '<', date("Y-m-d"))
		->orderBy('event_orders.created_at','DESC')
        ->get();
		$result_event=array();
		if(!empty($query_event) && count($query_event) > 0){
		    $event_ids = $query_event->pluck('id')->toArray();
		    
		    $ratting_event_ids = [];
		    if (!empty($event_ids)) {
		        $ratting_event = DB::table('ratting_apps')
		            ->where('ra_type', '=', 'event')
		            ->where('cust_id', '=', $cust_id)
		            ->whereIn('lookup_id', $event_ids)
		            ->pluck('lookup_id')->toArray();
		        $ratting_event_ids = array_flip($ratting_event);
		    }

			foreach($query_event as $raw){
				// cek sudah ratting
				$check_ratting = isset($ratting_event_ids[$raw->id]) ? 1 : 0;
				// jika belum memberikan ratting
				if($check_ratting==0){
					$image_url = $this->url_server."upload/event/".$raw->et_banner;
					//$image = $raw->et_banner;
					
					$result_event[] = [
						'id' => $raw->id,
						'type_data' => 'event',
						'name' => $raw->et_title,
						'image_url' => $image_url,
						//'image' => $image,
						'created_at' => $raw->created_at,
					];
				}
			}
		}
		
		$result_data = array_merge($result_pos,$result_event);
		return $result_data;
	}
	
	// new update (event dan promo)
	private function newUpdate($cust_id)
	{
		$query_event = DB::table("event_tops")
		->selectRaw('ts_event_tops.id as id, et_title, et_start_date, et_start_time, et_end_date, et_end_time, et_address, et_banner, et_price, et_terms, et_description, et_quota, et_lat, et_lang, et_type, et_link_online, et_score, et_maps_url, et_category, evt_category, evt_name, et_city, et_tournament, ts_event_tops.created_at')
		->leftJoin('event_types', 'event_types.id', '=', 'event_tops.evt_id')
		->where('et_delete', '=', '0')
		->whereRaw("CONCAT(ts_event_tops.et_start_date,' ',ts_event_tops.et_start_time) >= '".date('Y-m-d H:i:s')."'")
		->whereRaw("LEFT(ts_event_tops.created_at,10) = '".date('Y-m-d')."'") // hanya yang di input hari ini dan avent masih aktif
		->where('et_category', '!=', 'eksekutif') // selain eksekutif
		->orderBy('et_end_date')
        ->get();
		
		$result_event=array();
		if($query_event){
			foreach($query_event as $raw){
				$image_url = $this->url_server."upload/event/".$raw->et_banner;
				$result_event[] = [
					'id' => $raw->id,
					'label_data' => 'event',
					'name' => $raw->et_title,
					'image_url' => $image_url,
					//'created_at' => $raw->created_at,
					'created_at' => $raw->et_end_date.' '.$raw->et_end_time,
				];
			}
		}

		// cek promo terbaru
        $query_promo = DB::table("product_discounts")
		->select('product_discounts.id as id', 'product_discounts.created_at', 'st_id', 'std_id', 'pd_name', 'pd_type', 'pd_value', 'pd_date', 'no_mom', 'pd_banner', 'pd_tnc', 'pd_use', 'created_at')
		->where('std_id', '=', '20') // kusus apps
		->whereNotIn('pd_type', ['fix_special'])
		/*
		->where('pd_start', '<=', date("Y-m-d"))
		->where('pd_date', '>=', date("Y-m-d"))
		->where('pd_start_time', '<=', date("H:i:s"))
		->where('pd_end_time', '>=', date("H:i:s"))
		*/
		->whereRaw("CONCAT(ts_product_discounts.pd_start,' ',ts_product_discounts.pd_start_time)<='".date("Y-m-d H:i:s")."'")
        ->whereRaw("CONCAT(ts_product_discounts.pd_date,' ',ts_product_discounts.pd_end_time)>='".date("Y-m-d H:i:s")."'")
		->where('pd_flash_sale', '=', "0") // selain flash sale
		->whereRaw("LEFT(ts_product_discounts.created_at,10) = '".date('Y-m-d')."'") // hanya yang di input hari ini
		->groupBy('no_mom')
		->orderBy('id','DESC')
        ->get();
		$result_promo=array();
		if($query_promo){
			foreach($query_promo as $raw){
				$image_url = $this->url_server."upload/promo/".$raw->pd_banner;
				$result_promo[] = [
					'id' => $raw->id,
					'label_data' => 'promo',
					//'name' => $raw->pd_name,
					'name' => '',
					'image_url' => $image_url,
					'created_at' => $raw->created_at,
				];
			}
		}

		$result_data = array_merge($result_event,$result_promo);
		return $result_data;
	}
	
	// produk baru dilihat
	private function newSeeproduct($cust_id)
    {
        /*
        $exception = DB::table('exception_locations')->select('pl_code')
        ->leftJoin('product_locations', 'product_locations.id', '=', 'exception_locations.pl_id')->get();
        $exc_location = array();
        foreach ($exception as $exc) {
            $exc_location[] = $exc->pl_code;
        }
        */
        $exc_location = Cache::remember('exception_locations', now()->addHours(24), function () {
            return DB::table('exception_locations')
			->leftJoin('product_locations', 'product_locations.id', '=', 'exception_locations.pl_id')
			//->whereRaw("pl_code!=''")
			->pluck('pl_code')
			->toArray();
        });
		
		$cacheKey = 'get_productsee' . md5(json_encode([
			'cust_id'      => $cust_id,
			'page'         => request('page', 1) // biar cache per halaman paginate
		]));

		return Cache::remember($cacheKey, now()->addHours(24), function () use ($cust_id, $exc_location) {
		
		$stock = DB::table('traffic_apps')
		->selectRaw('ts_product_stocks.id as pid, pc_name as category, br_name as brand, p_name as article, p_color as color, sz_name as size, ps_price_tag, p_price_tag, ps_sell_price, p_sell_price, p_main_image, p_image, psc_name, pssc_name, sold, SUM(ts_product_stocks.ps_qty) as stock, p_slug')
		->leftJoin('product_stocks', 'product_stocks.id', '=', 'traffic_apps.ta_lookup_id')
		->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
        ->leftJoin('product_categories', 'product_categories.id', '=', 'products.pc_id')
		->leftJoin('product_sub_categories', 'product_sub_categories.id', '=', 'products.psc_id')
		->leftJoin('product_sub_sub_categories', 'product_sub_sub_categories.id', '=', 'products.pssc_id')
        ->leftJoin('brands', 'brands.id', '=', 'products.br_id')
        ->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
        // cek stok
		->where('product_stocks.ps_qty', '>', '0')
		->where('p_delete', '=', '0')
		->where('ta_page_access',"=","Detail Produk") // kusus produk yang dilihat dari log traffic aplikasi..
		->where('traffic_apps.cust_id',"=",$cust_id)
		->whereNotNull('p_main_image')
		->groupBy('products.p_name')
		->orderBy("traffic_apps.created_at",'DESC')
		->paginate(10);
		
        $results = array();
        if (!empty($stock)) {
            $pids = $stock->pluck('pid')->filter()->unique()->toArray();
            $articles = $stock->pluck('article')->filter()->unique()->toArray();
            $brands = $stock->pluck('brand')->filter()->unique()->toArray();

            // Batch 1: check_discount
            $discounts_by_pid = [];
            if (!empty($pids)) {
                $now_date = date("Y-m-d");
                $now_time = date("H:i:s");
                $all_discounts = DB::table('product_discounts')
                    ->select('product_discount_details.pst_id', 'pd_type', 'pd_value')
                    ->leftJoin('product_discount_details', 'product_discount_details.pd_id', '=', 'product_discounts.id')
                    ->where('product_discounts.std_id', '=', '9')
                    ->whereIn('product_discount_details.pst_id', $pids)
                    ->where('pd_start', '<=', $now_date)
                    ->where('pd_date', '>=', $now_date)
                    ->where('pd_start_time', '<=', $now_time)
                    ->where('pd_end_time', '>=', $now_time)
                    ->get();
                foreach ($all_discounts as $cd) {
                    if (!isset($discounts_by_pid[$cd->pst_id])) {
                        $discounts_by_pid[$cd->pst_id] = $cd;
                    }
                }
            }

            // Batch 2: wishlists
            $wishlists_by_pid = [];
            if (!empty($pids) && !empty($cust_id)) {
                $all_wishlists = DB::table("wishlists")
                    ->whereIn('pst_id', $pids)
                    ->where('cust_id', $cust_id)
                    ->where('ws_delete', '=', '0')
                    ->pluck('pst_id')
                    ->toArray();
                $wishlists_by_pid = array_flip($all_wishlists);
            }

            // Batch 3: promo aktif apps
            $promos_by_pst = [];
            if (!empty($articles)) {
                $now_dt = date("Y-m-d H:i:s");
                $all_promos = DB::table("product_discount_details")
                    ->select('product_discount_details.id', 'product_discount_details.pst_id', 'pd_value', 'pd_type', 'products.p_name')
                    ->leftJoin('product_discounts', 'product_discounts.id', '=', 'product_discount_details.pd_id')
                    ->leftJoin('product_stocks', 'product_stocks.id', '=', 'product_discount_details.pst_id')
                    ->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
                    ->where('std_id', '=', '20')
                    ->whereRaw("CONCAT(ts_product_discounts.pd_start,' ',ts_product_discounts.pd_start_time)<='$now_dt'")
                    ->whereRaw("CONCAT(ts_product_discounts.pd_date,' ',ts_product_discounts.pd_end_time)>='$now_dt'")
                    ->whereIn('products.p_name', $articles)
                    ->where('products.p_delete', '=', '0')
                    ->get();
                foreach ($all_promos as $promo) {
                    if (!isset($promos_by_pst[$promo->pst_id])) {
                        $promos_by_pst[$promo->pst_id] = $promo;
                    }
                }
            }

            // Batch 4: stock colors
            $stock_colors_by_key = [];
            if (!empty($articles) && !empty($brands)) {
                $all_stock_colors = DB::table('product_stocks')
                    ->selectRaw("ts_product_stocks.id as pid, pc_name as category, br_name as brand, p_name as article, p_color as color, sz_name as size, ps_price_tag, p_price_tag, ps_sell_price, p_sell_price, p_main_image, p_image, psc_name, pssc_name, sold, SUM(ts_product_location_setups.pls_qty) as stock, CONCAT(p_name,' (',br_name,')') as p_name_brand, CONCAT(p_name,' (',p_color,')') as p_name_color")
                    ->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
                    ->leftJoin('product_categories', 'product_categories.id', '=', 'products.pc_id')
                    ->leftJoin('product_sub_categories', 'product_sub_categories.id', '=', 'products.psc_id')
                    ->leftJoin('product_sub_sub_categories', 'product_sub_sub_categories.id', '=', 'products.pssc_id')
                    ->leftJoin('brands', 'brands.id', '=', 'products.br_id')
                    ->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
                    ->leftJoin('product_location_setups', 'product_location_setups.pst_id', '=', 'product_stocks.id')
                    ->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
                    ->whereNotIn('pl_code', $exc_location)
                    ->where('pls_qty', '>', '0')
                    ->whereNotNull('p_main_image')
                    ->where('p_price_tag', '>', '0')
                    ->where('p_delete', '=', '0')
                    ->whereIn('brands.br_name', $brands)
                    ->whereIn('products.p_name', $articles)
                    ->groupBy('p_name_color')
                    ->get();
                foreach ($all_stock_colors as $sc) {
                    $key = $sc->article . '|||' . $sc->brand;
                    $stock_colors_by_key[$key][] = $sc;
                }
            }

            // Batch 5: ratings
            $ratings_by_article = [];
            if (!empty($articles)) {
                $all_ratings = DB::table("products")
                    ->select('id', 'p_name', 'p_ratting', 'p_user_ratting')
                    ->whereIn("p_name", $articles)
                    ->where("p_delete", "=", "0")
                    ->get();
                foreach ($all_ratings as $rt) {
                    $ratings_by_article[$rt->p_name][] = $rt;
                }
            }

            $arr_customer_cached = null;
            if ($cust_id) {
                $arr_customer_cached = DB::table("customers")->where("id", "=", $cust_id)->first();
            }

            foreach ($stock as $row) {
                $price = null;
                $price_tag = null;
                $store = null;
                $image_url = $this->url_server."api/product/600/";
                $image = ProductImageHelper::parseImages($row->p_main_image, $row->p_image);
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
                $check_discount = isset($discounts_by_pid[$row->pid]) ? $discounts_by_pid[$row->pid] : null;
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
				$arr_promo = isset($promos_by_pst[$row->pid]) ? $promos_by_pst[$row->pid] : null;
				
				$price_tag = $row->p_price_tag; // harga bandrol
				$sell_price = $row->p_sell_price; // harga jual
				$flag_buy1get1 = 0;
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
					} else
					if($arr_promo->pd_type == "b1g1"){ // jika B1G1
						$sell_price = $price_tag; // harga bandrol sebagai harga jual...
						$flag_buy1get1 = 1;
					}
					else {
						$sell_price = $sell_price;
					}
					
					if($price_tag!=$sell_price){
						$persen_potongan = 100 - (($sell_price / $price_tag) * 100);
					} else {
						$persen_potongan = 0;
					}
				}
				
				// tampilkan jumlah warna tersedia
				$key_color = $row->article . '|||' . $row->brand;
				$stock_color = isset($stock_colors_by_key[$key_color]) ? $stock_colors_by_key[$key_color] : [];
				
				$image = array();
				foreach($stock_color as $row_color){
					$img = ProductImageHelper::parseSingleImage($row_color->p_main_image, $row_color->p_image);
					if (!empty($img)) {
						$image[] = $img;
					}
				}
				$image_url = "https://topsystem.id/api/product/600/";
				
				// cek ratting produk [new function]
				$qproducts = isset($ratings_by_article[$row->article]) ? $ratings_by_article[$row->article] : [];
				$tot_ratting = 0;
    			$tot_ratting_user = 0;
    			$jml_data_product = 0;
			    foreach($qproducts as $raw_ratting){
			        if($raw_ratting->p_ratting>0){
        				$tot_ratting += $raw_ratting->p_ratting;
            			$tot_ratting_user += $raw_ratting->p_user_ratting;
            			$jml_data_product++;
			        }
			    }
				if($tot_ratting>0 && $jml_data_product>0){
				    $tot_ratting = round($tot_ratting/$jml_data_product,2);
				}
				
				if($tot_ratting==0){
				    $tot_ratting = 5;
				}
				
				$cashback_coin = (1/100) * $sell_price;
				if($flag_buy1get1==1){$label_promo = 'B1G1';} else {$label_promo = '';}
				
				$flag_wishlist = isset($wishlists_by_pid[$row->pid]) ? 1 : 0;
				
				// cek customers
				$add_persen = 0;
				if($cust_id && !$arr_promo){
					$arr_customer = $arr_customer_cached;
					if($arr_customer){
						// jika sebagai reseller
						if($arr_customer->cust_reseller==1){
							// kusus brand topscore {$row->brand}
							if($row->brand == "TOPSCORE"){
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
				$flag_sold=0;
				if(count($stock_color)>0){
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
						'ratting' => $tot_ratting,
						'total_ulasan' => $tot_ratting_user,
						'wishlist' => $flag_wishlist,
						'jumlah_warna' => count($stock_color),
						'image_url' => $image_url,
						'image' => $image,
						'flag_buy1get1' => $flag_buy1get1,
						'cashback_coin' => round($cashback_coin),
						'label_promo' => $label_promo,
						'flag_sold' => $flag_sold,
						'slug' => $row->p_slug,
					];
				}
            }

        }
        return $results;
		});
    }
    
    // last stock check ***
	public function detailLaststock($cust_id)
    {
		$exc_location = Cache::remember('exception_locations', now()->addHours(24), function () {
            return DB::table('exception_locations')
			->leftJoin('product_locations', 'product_locations.id', '=', 'exception_locations.pl_id')
			->pluck('pl_code')
			->toArray();
        });
		
		// cek promo by no MOM
		$by_mom = DB::table('product_discounts')->select('id')
				  ->where('std_id', '=', '20') // kusus aplikasi
				  ->whereNotIn('pd_type', ['fix_special'])
				  ->whereRaw("CONCAT(ts_product_discounts.pd_start,' ',ts_product_discounts.pd_start_time)<='".date("Y-m-d H:i:s")."'")
		          ->whereRaw("CONCAT(ts_product_discounts.pd_date,' ',ts_product_discounts.pd_end_time)>='".date("Y-m-d H:i:s")."'")
				  //->where('pd_flash_sale', '=', "1") // kusus flash sale
				  ->where('pd_flash_sale',"=","0") // selain flash sale
				  ->where('pd_name', 'LIKE', "%last stock%")
				  //->groupBy('no_mom')
				  ->get();
		if(!$by_mom){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data promo flash sale tidak ditemukan!'
                ]
            ];
            return response()->json($data);
		}

        $in_pdid = array();
        foreach ($by_mom as $mom) {
            $in_pdid[] = $mom->id;
        }
		
		
		if($cust_id){$page_limit=10;} else {$page_limit=20;}
		
		$data_products = DB::table('product_discount_details')
		->selectRaw("ts_product_discount_details.id, ts_product_discount_details.pst_id, pc_name as category, br_name as brand, p_name as article, p_color as color, sz_name as size, ps_price_tag, p_price_tag, ps_sell_price, p_sell_price, p_main_image, p_image, psc_name, pssc_name, sold, SUM(ts_product_stocks.ps_qty) as stock, CONCAT(p_name,' (',br_name,')') as p_name_brand, pd_type, pd_value, p_slug")
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
        ->paginate(10);
		
		$pid = '';
        $results = array();
		$address = array();
        if (count($data_products)>0) {
            $articles = $data_products->pluck('article')->filter()->unique()->toArray();
            $brands = $data_products->pluck('brand')->filter()->unique()->toArray();

            $stock_colors_by_key = [];
            if (!empty($articles) && !empty($brands)) {
                $all_stock_colors = DB::table('product_stocks')
                    ->selectRaw("ts_product_stocks.id as pid, pc_name as category, br_name as brand, p_name as article, p_color as color, sz_name as size, ps_price_tag, p_price_tag, ps_sell_price, p_sell_price, p_main_image, p_image, psc_name, pssc_name, sold, SUM(ts_product_location_setups.pls_qty) as stock, CONCAT(p_name,' (',br_name,')') as p_name_brand, CONCAT(p_name,' (',p_color,')') as p_name_color, p_slug")
                    ->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
                    ->leftJoin('product_categories', 'product_categories.id', '=', 'products.pc_id')
                    ->leftJoin('product_sub_categories', 'product_sub_categories.id', '=', 'products.psc_id')
                    ->leftJoin('product_sub_sub_categories', 'product_sub_sub_categories.id', '=', 'products.pssc_id')
                    ->leftJoin('brands', 'brands.id', '=', 'products.br_id')
                    ->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
                    ->leftJoin('product_location_setups', 'product_location_setups.pst_id', '=', 'product_stocks.id')
                    ->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
                    ->whereNotIn('pl_code', $exc_location)
                    ->where('pls_qty', '>', '0')
                    ->whereNotNull('p_main_image')
                    ->where('p_price_tag', '>', '0')
                    ->where('p_delete', '=', '0')
                    ->whereIn('brands.br_name', $brands)
                    ->whereIn('products.p_name', $articles)
                    ->groupBy('p_name_color')
                    ->get();
                foreach ($all_stock_colors as $sc) {
                    $key = $sc->article . '|||' . $sc->brand;
                    $stock_colors_by_key[$key][] = $sc;
                }
            }

            foreach ($data_products as $row) {
				$price = null;
                $price_tag = null;
                $store = null;
                $image_url = $this->url_server."api/product/600/";
                $image = ProductImageHelper::parseImages($row->p_main_image, $row->p_image);
                
				
				$price_tag = $row->p_price_tag; // harga bandrol
				
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
					$sell_price = $price_tag;
					$flag_buy1get1 = 1;
				}
				else {
					$sell_price = $sell_price;
				}
				
				if($price_tag!=$sell_price){
					$persen_potongan = 100 - (($sell_price / $price_tag) * 100);
				} else {
					$persen_potongan = 0;
				}
				
				// tampilkan jumlah warna tersedia
				$key_color = $row->article . '|||' . $row->brand;
				$stock_color = isset($stock_colors_by_key[$key_color]) ? $stock_colors_by_key[$key_color] : [];
				
				$image = array();
				foreach($stock_color as $row_color){
					$img = ProductImageHelper::parseSingleImage($row_color->p_main_image, $row_color->p_image);
					if (!empty($img)) {
						$image[] = $img;
					}
				}
				$image_url = $this->url_server."api/product/600/";
				
				if($flag_buy1get1==1){$label_promo='B1G1';} else {$label_promo='';}
				if($row->brand == "TOPSCORE"){$flag_sold=0;} else {$flag_sold=1;}
				$flag_sold=0;
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
					'additional_discount' => 0, // kusus promo additional diskon=0
					'sold' => $row->sold,
					'ratting' => 0, //$ratting,
					'total_ulasan' => 0,
					'jumlah_warna' => count($stock_color),
                    'image_url' => $image_url,
                    'image' => $image,
					'flag_buy1get1' => $flag_buy1get1,
					'label_promo' => $label_promo,
					'flag_sold' => $flag_sold,
					'slug' => $row->p_slug,
                ];
            }
			//$data = $results;
        }
        return $results;
    }
}
