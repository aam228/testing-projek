<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
//use App\Models\Artikel;

class CustController extends Controller
{
    protected $url_server = 'https://topsystem.id/';
    public function getCust(Request $request)
    {
        $client_id = $request->header('client_id');
        $api_key = $request->header('api_key');

        /*
		$validate = DB::table('client_credentials')
        ->where([
            'client_id' => $client_id,
            'client_api' => $api_key
        ])->exists();
        if (!$validate) {
            $data = [
                'status' => [
                    'code' => 401,
                    'description' => 'We could not identify your credential id and api key'
                ]
            ];
            return response()->json($data);
        }
		*/

		//$id = $request->user_id;
		//$email = $request->email;
		$cust_id = $request->cust_id;
		
		// save log traffic
		$data_log = [
			'cust_id' => $request->cust_id,
			'ta_page_access' => "Akun",
			'ta_description' => "Mengakses laman akun profile",
			//'ta_lookup_id' => $request->product_id,
			'created_at' => date("Y-m-d H:i:s"),
			'ip_address' => $request->ip(),
		];
		$save_log = DB::table("traffic_apps")->insertGetId(array_merge($data_log));
		
        $banner_apps = DB::table('customers')->selectRaw('ts_customers.id as pid, cust_name, cust_phone, cust_email, cust_bday, cust_username, created_at, cust_address, flag_member, id_member, cust_membership_type, cust_phone_verify, cust_email_verify, cust_bday, cust_gender, cust_referal_code, cust_flag_referal, cust_avatar, cust_select_category, cust_reseller, cust_reseller_uf')
		//->where('cust_email', '=', $email)
		->where('id', '=', $cust_id)
		->where('cust_delete', '=', '0')
		/*->where(function($w) use($st_name) {
			if (!empty($st_name)) {
				$w->where('st_name', '=', $st_name);
			}
		})*/
        ->get();
		
		$pid = '';
        $results = array();
		$address = array();
        if (count($banner_apps)>0) {
            foreach ($banner_apps as $row) {
                $join_log=array();
                // cek referal code 
				//if($row->cust_referal_code && $row->cust_flag_referal==1){$refcode=$row->cust_referal_code;} else {$refcode='';}
				if($row->cust_referal_code && $row->cust_flag_referal==1){
					$refcode=$row->cust_referal_code;
					// cek jumlah cuser yang menggunakan referal
					//$jumlah_referal = DB::table("customer_referals")->where("cust_id","=",$row->pid)->count();
					$sql_referal = DB::table("customer_referals")->where("cust_id","=",$row->pid)->get();
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
					$jumlah_referal = 0;
				}
				
				$member_referal = DB::table("customer_referals")->where("cust_id_ref","=",$row->pid)->first();
				if($member_referal){$refcode_member=$member_referal->cf_code;} else {$refcode_member='';}
				//$avatar_profile = $this->url_server.'upload/avatar/avatar.png';
				
				if(empty($row->cust_avatar)){
					//$avatar_profile = $this->url_server.'upload/avatar/avatar.png';
					if($row->cust_gender == 'm'){
						$avatar_profile = "https://www.topsystem.id/upload/avatar/AV1.png";
					} else {
						$avatar_profile = "https://www.topsystem.id/upload/avatar/AV51.png";
					}
				} else {
					$avatar_profile = $row->cust_avatar;
				}
				
				//$banner_profile = $this->url_server.'upload/bg_profile.png';
				$banner_profile = $this->url_server.'upload/bg_profile_black2.jpg';
				// cek kamu banget sesuai kategori
				if($row->cust_select_category){
					$cat_product = explode(", ",$row->cust_select_category);
				} else {
					//$cat_product = ['FG','IN','RUNNING'];
					$cat_product = array();
				}
				
				$arr_reseller = DB::table("reseller_settings")->first();
				$arr_profile_res = DB::table("reseller_registrations")->where(["rr_payment_status"=>"1", "cust_id"=>$row->pid])->first();
				if($arr_profile_res && $row->cust_reseller==1){
					$res_store = $arr_profile_res->rr_store_name;
					$res_phone = $arr_profile_res->rr_store_phone;
				} else {
					$res_store = '';
					$res_phone = '';
				}
				
				// cek jika customer reseller
				if($row->cust_reseller==1){
					if($row->cust_reseller_uf==0){
						$updated_uf = ['cust_reseller_uf' => 1];
						DB::table("customers")->where('id', $cust_id)->update($updated_uf);
					}
					$cust_reseller_uf = $row->cust_reseller_uf;
				} else {
					$cust_reseller_uf = 1;
				}
				
				// cek apakah sudah pernah belanja atau belum
				$check_pos = DB::table("pos_transactions")
				//->where(['cust_id'=>$cust_id, 'is_apps'=>'1'])
				->where(['cust_id'=>$cust_id])
				->whereNotIn('pos_status', ['CANCEL','UNPAID','REFUND'])
				->get();
				if(count($check_pos)>0){$pos_order=1;} else {$pos_order=0;}
				
				$results[] = [
                    'user_id' => $row->pid,
                    'nama' => $row->cust_name,
                    'email' => $row->cust_email,
                    'phone' => $row->cust_phone,
                    'status_member' => $row->flag_member,
					'member_id' => $row->id_member,
					'member_type' => $row->cust_membership_type,
                    'alamat' => $row->cust_address,
					'created_at' => $row->created_at,
					'verify' => $row->cust_phone_verify,
					'verify_email' => $row->cust_email_verify,
					//'verify_email' => 1,
					'tanggal_lahir' => $row->cust_bday,
					'jenis_kelamin' => $row->cust_gender,
					'cust_refcode' => $refcode,
					'cust_refcode_join' => (int)$jumlah_referal,
					'cust_refcode_join_log' => $join_log,
					'cust_refcode_member' => $refcode_member,
					'cust_avatar' => $avatar_profile,
					'cust_banner' => $banner_profile,
					'subsub_kat' => $cat_product,
				    'cust_reseller' => $row->cust_reseller,
					//'cust_reseller' => 0,
					'res_open' => $arr_reseller->rs_open,
					'res_store' => $res_store,
					'res_phone' => $res_phone,
					'reseller_firebase' => $cust_reseller_uf,
					'pos_order' => $pos_order,
                ];
				$pid = $row->pid;
            }
			
			// tampilkan alamat customer
			$list_alamat = DB::table('customer_address')->selectRaw('ts_customer_address.id as pid, ca_type, ca_address, ca_default, ts_customer_address.created_at, zc_province, zc_city, zc_district, zc_subdistrict, zc_zip, zc_code, ca_name, ca_phone, area_id_biteship, ca_lat, ca_lang, ca_patokan, zip_id, ca_pinpoint')
			//->leftJoin('zip_codes', 'zip_codes.zc_zip', '=', 'customer_address.zip_id')
			->leftJoin('zip_codes', 'zip_codes.id', '=', 'customer_address.zip_id')
			->where('cust_id', '=', $pid)
			->where('zip_id', '>=', '1')
			->whereNotNull('area_id_biteship')
			->where('ca_delete', '=', '0')
			//->groupBy('customer_address.id')
			->get();
			foreach ($list_alamat as $row_sub) {
			    if($row_sub->area_id_biteship!=''){$area_id_biteship=$row_sub->area_id_biteship;} else {$area_id_biteship='';}
				$address[] = [
                    'address_id' => $row_sub->pid,
					'nama' => $row_sub->ca_name,
					'label_alamat' => $row_sub->ca_type,
					'phone' => $row_sub->ca_phone,
                    'province' => $row_sub->zc_province,
                    'city' => $row_sub->zc_city,
                    'district' => $row_sub->zc_district,
                    'subdistrict' => $row_sub->zc_subdistrict,
                    'address' => $row_sub->ca_address,
                    'default_address' => $row_sub->ca_default,
					'zip' => $row_sub->zc_zip,
					'zip_code' => $row_sub->zc_code,
					//'area_id_biteship' => $row_sub->area_id_biteship,
					'area_id_biteship' => $area_id_biteship,
					'lat' => $row_sub->ca_lat,
					'lang' => $row_sub->ca_lang,
					'patokan' => $row_sub->ca_patokan,
					'kelurahan_id' => $row_sub->zip_id,
					'status_koordinat' => $row_sub->ca_pinpoint,
                ];
			}
			
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success'
				],
				'results' => $results,
				'address' => $address
			];
        } else {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data tidak ditemukan'
				],
			];
		}
        return response()->json($data);
    }
    
	// add alamat user
	public function storeAddress(Request $request)
    {
        $mode = $request->mode;
        $id = $request->id;
		$cust_id = $request->cust_id;
		$zip_id = $request->zip_code;
		$ca_type = $request->jenis_alamat;
		$ca_address = $request->alamat;
		$ca_default = $request->alamat_default;
		//$ca_name = $request->nama;
		$ca_name = strip_tags($request->nama);
		$ca_phone = $request->phone;
		$area_id_biteship = $request->area_id_biteship;
		$ca_lat = $request->lat;
		$ca_lang = $request->lang;
		$ca_patokan = $request->patokan;
		$ca_pinpoint = $request->status_koordinat;
		if($ca_pinpoint==''){$ca_pinpoint=1;} else {$ca_pinpoint=$ca_pinpoint;}
		
		//$mode = "add";
        $data = [
			'cust_id' => $cust_id,
			'zip_id' => $zip_id,
			'ca_type' => $ca_type,
			'ca_address' => $ca_address,
			'ca_default' => $ca_default,
			'ca_name' => $ca_name,
			'ca_phone' => $ca_phone,
			'area_id_biteship' => $area_id_biteship,
			'ca_lat' => $ca_lat,
			'ca_lang' => $ca_lang,
			'ca_patokan' => $ca_patokan,
			'ca_pinpoint' => $ca_pinpoint,
			'created_at' => date("Y-m-d H:i:s"),
		];
		
		// update master address
		$updated_cust = [
			'cust_address' => $ca_address,
		];
		DB::table("customers")->where('id', $cust_id)->update($updated_cust);
		
		// update alamat default = 0;
		if($ca_default == 1){
			$updateds = [
				'ca_default' => '0',
			];
			DB::table("customer_address")->where('cust_id', $cust_id)->update($updateds);
		}
		
		//$check = $form_save->checkData('id', ['cust_email' => $request->r_email, 'cust_delete' => '0']);

        if ($mode == 'edit') {
			$updated = [
				'updated_at' => date('Y-m-d H:i:s')
			];
            $saves = DB::table("customer_address")->where('id', $id)->update(array_merge($data,$updated));
			if($saves){
			//$notif = 'success_edit';
			$r['status'] = "200";
			$r['message'] = "Berhasil menyimpan data";
			} else {
			$r['status'] = "401";
			$r['message'] = "Gagal melakukan update data";
			}
        } else 
		if ($mode == 'add') {
            $saves = DB::table("customer_address")->insertGetId(array_merge($data));
			//$notif = 'success_add';
			if($saves){
			$r['status'] = "200";
			$r['message'] = "Berhasil menyimpan data";
			} else {
			$r['status'] = "401";
			$r['message'] = "Gagal menyimpan data";
			}
        } else 
		{
			$r['status'] = "400";
			$r['message'] = "Data tidak ditemukan";
		}

		/*
		if (!empty($check)) {
			$r['status'] = "400";
			$r['message'] = "Email sudah digunakan";
		} else {
			if ($form_save->storeData($mode, $data) == "success_add") {
				$r['status'] = "200";
				$r['message'] = "Berhasil menyimpan data";
			} else {
				$r['status'] = "400";
				$r['message'] = "Gagal menyimpan data";
			}
		}
		*/
        return response()->json($r);
    }
    
    // delete alamat
	public function deleteAddress(Request $request)
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

        $id = $request->address_id; // id_alamat
		$cust_id = $request->cust_id;
		$arr_address = DB::table("customer_address")->where('id', $id)->where('cust_id', $cust_id)->first();
		if(empty($arr_address)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data alamat tidak ditemukan'
                ]
            ];
            return response()->json($data);
		}
		
		// lakukan update data...	
		$updateds = [
			'ca_delete' => '1',
		];
		DB::table("customer_address")->where('id', $id)->update($updateds);
		
		if($updateds){
            $data = [
                'status' => [
                    'code' => 200,
                    'description' => 'Data alamat berhasil di hapus'
                ]
            ];
            return response()->json($data);
		} else {
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data alamat gagal di hapus'
                ]
            ];
            return response()->json($data);
		}
    }
    
    // update avatar 
	public function updateAvatar(Request $request)
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

        $cust_avatar = $request->cust_avatar;
		$cust_id = $request->cust_id;
		if(empty($cust_avatar)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id, cust_avatar tidak boleh kosong'
                ]
            ];
            return response()->json($data);
		}
		
		$arr_customer = DB::table("customers")->where('id', $cust_id)->first();
		if(empty($arr_customer)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data tidak ditemukan'
                ]
            ];
            return response()->json($data);
		}
		
		// lakukan update data...	
		$updateds = [
			'cust_avatar' => $cust_avatar,
		];
		DB::table("customers")->where('id', $cust_id)->update($updateds);
		
		if($updateds){
            $data = [
                'status' => [
                    'code' => 200,
                    'description' => 'Data avatar berhasil di update'
                ]
            ];
            return response()->json($data);
		} else {
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data avatar gagal di update'
                ]
            ];
            return response()->json($data);
		}
    }
    
    // withdrawRequest
	public function withdrawRequest(Request $request)
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
		$nominal = $request->nominal;
		if(empty($cust_id) || $nominal<=0){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id dan nominal tidak boleh kosong',
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
		
		// cek sisa coin
		if($arr_customer->cust_coin<$nominal){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Nominal yang di redeem melebihi koin yang kamu miliki',
                ]
            ];
            return response()->json($data);
		}
		
		// cek log
		$arr_log = DB::table("customer_request_withdraws")->where('cust_id', $cust_id)->get();
		if($arr_log){
			// update jika ada data
			$updated_data = [
				'crw_active' => "0",
				'updated_at' => date("Y-m-d H:i:s"),
			];
			DB::table("customer_request_withdraws")->where('cust_id', $cust_id)->update($updated_data);
		}
		// save new log request withdraw
		$crw_code = rand(100000,999999);
        $data = [
			'cust_id' => $cust_id,
			'crw_code' => $crw_code,
			'crw_description' => $arr_customer->cust_name.' melakukan request redeem koin dengan kode '.$crw_code.' dan nominal '.number_format($nominal,0,',','.').' pada tanggal '.date("d/m/Y H:i:s"),
			'crw_value' => $nominal,
			'created_at' => date("Y-m-d H:i:s"),
		];
		$saves = DB::table("customer_request_withdraws")->insertGetId(array_merge($data));
		
		// tapilkan data json
		$data = [
			'status' => [
				'code' => 200,
				'description' => 'Kode request redeem koin berhasil di generate',
				'results' => [
					'cust_coin' => $arr_customer->cust_coin,
					'code' => $crw_code,
					'coin_redeem' => (int)$nominal,
				],
				
            ]
        ];
		return response()->json($data);
	}
	
	// tampilkan free sesuai level customer
	public function benefitLevel(Request $request)
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
		
		// cek membership customer
		if(!in_array($arr_customer->cust_membership_type,array("PRO PLAYER","ELITE PLAYER"))){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Level kamu belum memenuhi syarat mendapatkan benefit tambahan, upgrade segera level kamu menjadi PRO PLAYER dan ELITE PLAYER',
                ]
            ];
            return response()->json($data);
		}
		
		$in_product_id = explode(",",$product_id);
		$in_store_id = explode(",",$store_id);
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
				
				$data = [
					'status' => [
						'code' => 200,
						'description' => 'Benefit kamu berhasil di generate, claim sekarang!',
						'results' => [
							'product_id' => $stock->pid,
							'article' => $stock->article,
							'product_name' => $stock->article,
							'color' => $stock->color,
							'size' => $stock->size,
							'image_url' => 'https://topsystem.id/api/product/600/',
							'image' => $image,
							'store_id' => $stock->store_id,
							'store_name' => $stock->st_name,
						],
						
					]
				];
				return response()->json($data);
			}
			// stok tidak tersedia
			else {
				// tapilkan data json
				$data = [
					'status' => [
						'code' => 400,
						'description' => 'Stok sudah habis',						
					]
				];
				return response()->json($data);
			}
			
			
		}
		else {		
			// tapilkan data json untuk produk selain sepatu
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Benefit free kaos kaki tidak dapat di claim, pastikan produk yang kamu order adalah sepatu.',
				]
			];
			return response()->json($data);
		}
	}
	
	// update pilihan category produk customer
	public function updateCategory(Request $request)
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
		$category = $request->category;
		// 'cust_select_category' => $request->cust_select_category,
		if (!$cust_id || !$category) {
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id & category tidak boleh kosong!'
                ]
            ];
            return response()->json($data);
        }

		// cek customer
		$arr_customer = DB::table("customers")->where("id","=",$cust_id)->first();
		if($arr_customer){
			$updated_cust = [
				'cust_select_category' => $category,
			];
			$save = DB::table("customers")->where('id', $cust_id)->update($updated_cust);
			
			if ($category) {
				$r['status'] = "200";
				$r['message'] = "Berhasil menyimpan data";
			} else {
				$r['status'] = "400";
				$r['message'] = "Gagal melakukan update data!";
			}
		} else {
			$r['status'] = "400";
			$r['message'] = "Data customer tidak ditemukan!";
		}

        return response()->json($r);
    }
    
    // customer update topic
	public function updateTopic(Request $request)
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

        $cust_topic = $request->cust_topic;
		$cust_id = $request->cust_id;
		$cust_device_id = $request->cust_device_id;
		if(!$cust_topic || !$cust_id || !$cust_device_id){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id, cust_topic dan cust_device_id tidak boleh kosong'
                ]
            ];
            return response()->json($data);
		}
		
		$arr_customer = DB::table("customers")->where('id', $cust_id)->first();
		if(empty($arr_customer)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data tidak ditemukan'
                ]
            ];
            return response()->json($data);
		}
		
		// lakukan update data...	
		$updateds = [
			'cust_topic' => $cust_topic,
			'cust_device_id' => $cust_device_id,
		];
		DB::table("customers")->where('id', $cust_id)->update($updateds);
		
		if($updateds){
            $data = [
                'status' => [
                    'code' => 200,
                    'description' => 'Data subscribe topic berhasil di update'
                ]
            ];
            return response()->json($data);
		} else {
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data subscribe topic gagal di update'
                ]
            ];
            return response()->json($data);
		}
    }
}
