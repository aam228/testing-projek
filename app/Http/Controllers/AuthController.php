<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Services\CustomerAccessToken;
use Illuminate\Support\Str;

use Hash;

class AuthController extends Controller
{
	private function checkData($select, $where)
    {
        $affected = DB::table("users")
            ->select($select)
            ->where($where)
            ->get()->first();
        return $affected;
    }
    
    public function checkEmail(Request $request)
    {
        $email = $request->input('email');

        $user = User::where('cust_email', $email)->first();

        if ($user) {
            return response()->json(['status' => '200', 'message' => 'Email sudah terdaftar']);
        } else {
            return response()->json(['status' => '404', 'message' => 'Email belum terdaftar']);
        }
    }
    
    public function loginPhone(Request $request)
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

        $phone = str_replace(" ","",$request->cust_phone);
		$cust_token = $request->cust_token;
        if (empty($phone) || empty($cust_token)) {
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Nomor handphone dan token customer tidak boleh kosong'
                ]
            ];
            return response()->json($data);
        }
		
		if(substr($phone,0,2) == "62"){
			$phone = "0".substr($phone,2);
		} else
		if(substr($phone,0,3) == "+62"){
			$phone = "0".substr($phone,3);
		} else {
			$phone = $phone;
		}
        $user = User::where('cust_phone', $phone)->where('cust_delete','=','0')->first();
		if (!empty($user)) {
		    if(empty($user->cust_avatar)){
				if($user->cust_gender == 'm'){
					$avatar_profile = "https://www.topsystem.id/upload/avatar/AV1.png";
				} else {
					$avatar_profile = "https://www.topsystem.id/upload/avatar/AV51.png";
				}
			} else {
				$avatar_profile = $user->cust_avatar;
			}
			$data = [
                'status' => [
                    'code' => 200,
                    'message' => 'Login Sukses',
                    'cust_id' => $user->id,
                    'email' => $user->cust_email,
                    'nama' => $user->cust_name,
                    'phone' => $user->cust_phone,
                    'alamat' => $user->cust_address,
                    'created_at' => date("Y-m-d H:i:s",strtotime($user->created_at)),
					'verify' => 1,
					'verify_email' => $user->cust_email_verify,
					'cust_avatar' => $avatar_profile,
					'cust_role' => $user->cust_role,
					'cust_reseller' => $user->cust_reseller,
					//'created_at' => $user->created_at,
                ]
            ];
			/*
            $r['status'] = "200";
			$r['message'] = "Login Sukses";
			$r['cust_id'] = $user->id;
			$r['email'] = $user->cust_email;
			$r['nama'] = $user->cust_name;
			$r['phone'] = $user->cust_phone;
			$r['alamat'] = $user->cust_address;
			$r['created_at'] = $user->created_at;
			*/
			// update token id
			$data_token = [
				'cust_token_id' => $cust_token,
				'cust_phone_verify' => 1,
				'cust_platform' => $request->cust_platform,
				'ct_id' => '16',
			];
			$save_update = DB::table("customers")->where('id', $user->id)->update($data_token);
        } else {
            //Auth::logout();
			$data = [
                'status' => [
                    'code' => 400,
                    'message' => 'Data tidak ditemukan',
                    'cust_id' => null,
                    'email' => '',
                    'nama' => '',
                    'phone' => '',
                    'alamat' => '',
                    'created_at' => '',
					'verify' => 0,
					'verify_email' => 0,
					'cust_avatar' => '',
					'cust_role' => '',
					'cust_reseller' => '',
                ]
            ];
			/*
			$r['status'] = "400";
			$r['message'] = "Data tidak ditemukan";
			$r['cust_id'] = '';
			$r['email'] = '';
			$r['nama'] = '';
			$r['phone'] = '';
			$r['alamat'] = '';
			$r['created_at'] = '';
			*/
        }
        return response()->json($data);
    }
    
	// lengkapi data akun baru user (pendaftaran konsep baru 29 mei 2024)
	public function RegisterNew(Request $request)
    {
        $form_save = new User;
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

		$phone = str_replace(" ","",$request->cust_phone);
		if(substr($phone,0,2) == "62"){
			$phone = "0".substr($phone,2);
		} else
		if(substr($phone,0,3) == "+62"){
			$phone = "0".substr($phone,3);
		} else {
			$phone = $phone;
		}
        $user = User::where('cust_phone','=',$phone)->where('cust_delete','=','0')->first();
		// tampilkan error jika data sudah ada
		if (!empty($user)) {
			$data = [
                'status' => [
                    'code' => 400,
                    'message' => 'Nomor handphone sudah digunakan',
                ]
            ];
			return response()->json($data);
		}

		// validasi data tidak boleh kosong
		//if(empty($request->cust_bday) || empty($request->cust_gender) || empty($phone) || empty($request->cust_token)){
		//if(empty($request->cust_bday) || empty($phone) || empty($request->cust_token)){
		//if(empty($request->cust_bday) || empty($phone)){
		if(empty($phone)){
			$data = [
                'status' => [
                    'code' => 400,
                    //'message' => 'Tanggal lahir, jenis kelamin dan nomor whatsapp tidak boleh kosong!',
                    'message' => 'nomor whatsapp tidak boleh kosong!',
                ]
            ];
			return response()->json($data);
		}
		
		// cek jika ada kode referal yang d isi
		$kode_referal = $request->cust_referal;
		$arr_referal = DB::table("customers")->where("cust_referal_code","=",$kode_referal)->where("cust_flag_referal","=",1)->first();
		if($kode_referal){
			if(!$arr_referal){
				$data = [
					'status' => [
						'code' => 400,
						'message' => 'Kode referal tidak valid',
					]
				];
				return response()->json($data);
			}
		}
		
		if(empty($request->cust_gender)){$cust_gender = 'w';} else {$cust_gender = $request->cust_gender;}
		
        $platform = $request->cust_platform;
		if(empty($platform)){
			$platform = 'android';
		} else { $platform=$platform; }
		
		if($request->cust_bday){
		$cust_bday = $request->cust_bday;
		$new_date = explode("/",$cust_bday);
		$new_date_cust = $new_date[2].'-'.$new_date[1].'-'.$new_date[0];
		} else { $new_date_cust=NULL; }
		
		$mode = "add";
        $data = [
			'stt_id' => '1',
			'ct_id' => '16',
			//'cust_name' => $request->cust_name,
			//'cust_name' => $phone,
			'cust_phone' => $phone,
			'cust_bday' => $new_date_cust,
			//'cust_email' => $request->cust_email,
			//'cust_gender' => $request->cust_gender,
			'cust_gender' => $cust_gender,
			//'password' => Hash::make($request->cust_password),
			'created_at' => date("Y-m-d H:i:s"),
			'cust_platform' => $platform,
			'cust_token_id' => $request->cust_token,
			'cust_phone_verify' => 1,
			'cust_score' => 1,
			//'cust_name' => $request->cust_name,
			'cust_name' => strip_tags($request->cust_name),
			'cust_select_category' => $request->cust_select_category,
		];
		
		if ($form_save->storeData($mode, $data) == "success_add") {
			$user_login = User::where('cust_phone','=',$phone)->where('cust_delete','=','0')->first();
			// update nama customer default
			$nama_customers = 'Topscore user '.$user_login->id;
			if(empty($request->cust_name)){
    			$custupdate = [
    				'cust_name' => $nama_customers,
    			];
    			$save_update = DB::table("customers")->where('id', $user_login->id)->update($custupdate);
			}
			
			// save kode referal save log...
			if($kode_referal){
				$data_ref = [
					'cust_id' => $arr_referal->id,
					'cust_id_ref' => $user_login->id,
					'cf_code' => $kode_referal,
					'created_at' => date("Y-m-d H:i:s"),
				];
				DB::table("customer_referals")->insertGetId(array_merge($data_ref));
			}
			
			if(empty($user_login->cust_avatar)){
				if($user_login->cust_gender == 'm'){
					$avatar_profile = "https://www.topsystem.id/upload/avatar/AV1.png";
				} else {
					$avatar_profile = "https://www.topsystem.id/upload/avatar/AV51.png";
				}
			} else {
				$avatar_profile = $user_login->cust_avatar;
			}
			
			// input log coin
			//$nilai_coin = 10000; (mulai 2026 koin user baru 10000 dan langsung bisa di gunakan)
			if(date("Y-m-d")>'2026-01-01'){ $nilai_coin = 10000; } else {$nilai_coin = 0;}
			
			// expired coin
			$date_reg = date("Y-m-d H:i:s");
			//$expired_coin = date('Y-m-d H:i:s', strtotime("+365 day", strtotime($date_reg))); // 1 tahun expired
			$expired_coin = date('Y-m-d H:i:s', strtotime("+7 day", strtotime($date_reg))); // 1 tahun expired
			$expired_coin_text = date("d/m/Y H:i:s",strtotime($expired_coin));
			$data_coin = [
				'cust_id' => $user_login->id,
				'ccoin_type' => 'new user',
				'ccoin_lookup_id' => $user_login->id,
				//'ccoin_description' => 'New user aplikasi topscore [akan hangus pada tanggal '.$expired_coin_text.']',
				'ccoin_description' => 'New user aplikasi topscore',
				'ccoin_value' => $nilai_coin,
				'created_at' => date("Y-m-d H:i:s"),
				'ccoin_expired' => substr($expired_coin,0,10), // tanggal kadaliarsa koin
			];
			DB::table("customer_coins")->insertGetId(array_merge($data_coin));							
			// tambahkan nilai koin untuk customer
			$cust_coin_update = $user_login->cust_coin + $nilai_coin;
			DB::table("customers")->where('id', $user_login->id)->update(['cust_coin'=>$cust_coin_update]);
			
			$data = [
                'status' => [
                    'code' => 200,
                    'message' => 'Berhasil melakukan pendaftaran akun',
                    'cust_id' => $user_login->id,
                    'email' => $user_login->cust_email,
                    'nama' => $nama_customers, //$user_login->cust_name,
                    'phone' => $user_login->cust_phone,
                    'alamat' => $user_login->cust_address,
                    'created_at' => date("Y-m-d H:i:s",strtotime($user_login->created_at)),
					//'created_at' => $user_login->created_at,
					'verify' => 1,
					'verify_email' => $user_login->cust_email_verify,
					'cust_avatar' => $avatar_profile,
					'cust_role' => $user_login->cust_role,
					'type_benefit' => 'coin', // coin, voucher
					'cust_coin' => $nilai_coin,
					'cust_coin_info' => 'koin '.number_format($nilai_coin,0,',','.').' akan hangus pada tanggal '.$expired_coin_text,
                ]
            ];
            
            // input score log
			$data_score = [
				'cust_id' => $user_login->id,
				'cscore_type' => 'profile',
				'cscore_lookup_id' => $user_login->id,
				'cscore_description' => 'Pendaftaran akun baru',
				'cscore_value' => 1,
				'created_at' => date("Y-m-d H:i:s"),
			];
			DB::table("customer_scores")->insertGetId($data_score);
		} else {
			$data = [
                'status' => [
                    'code' => 400,
                    'message' => 'Gagal melakukan pendaftaran akun',
                ]
            ];
		}
		
        return response()->json($data);
    }
    
    /*
    public function loginGoogle(Request $request)
    {
        $email = $request->input('email');

        $user = User::where('cust_email', $email)->first();
    
            if (!empty($user)) {
                $r['status'] = "200";
				$r['message'] = "Login Sukses";
				$r['cust_id'] = $user->id;
				$r['email'] = $user->cust_email;
				$r['nama'] = $user->cust_name;
				$r['phone'] = $user->cust_phone;
				$r['alamat'] = $user->cust_address;
				$r['created_at'] = $user->created_at;
            } else {
                Auth::logout();
                $r['status'] = "400";
				$r['message'] = "Data tidak ditemukan";
				$r['cust_id'] = '';
				$r['email'] = '';
				$r['nama'] = '';
				$r['phone'] = '';
				$r['alamat'] = '';
				$r['created_at'] = '';
            }
        return response()->json($r);
    }
    */
    
    public function loginGoogle(Request $request)
    {
		$form_save = new User;
        $email = $request->input('email');
		if(!empty($email)){
        $user = User::where('cust_email', $email)->where("cust_delete","=","0")->first();
    	/*
		"status": {
			"code": 200,
			"message": "Login Sukses",
			"cust_id": 227666,
			"email": "bowo.topscore@gmail.com",
			"nama": "Bowo Susanto",
			"phone": "085376341110",
			"alamat": "jl. diwangsa 3 no 9\ncluster diwangsa, komplek nusa indah\ntanah baru bogor utara, bogor city, west java, 16154",
			"created_at": "2025-02-12 09:43:25",
			"verify": 1,
			"verify_email": 1,
			"cust_avatar": "https://topsystem.id/upload/avatar/AV47.png",
			"cust_role": "admin",
			"cust_reseller": 1
    	}
		*/
			
			$respon_data = array();
            if (!empty($user)) {

				if(empty($user->cust_avatar)){
					if($user->cust_gender == 'm'){
						$avatar_profile = "https://topsystem.id/upload/avatar/AV1.png";
					} else {
						$avatar_profile = "https://topsystem.id/upload/avatar/AV51.png";
					}
				} else {
					$avatar_profile = $user->cust_avatar;
				}

				$r['code'] = "200";
				$r['message'] = "Login Sukses";
				$r['cust_id'] = $user->id;
				$r['email'] = $user->cust_email;
				$r['nama'] = $user->cust_name;
				$r['phone'] = $user->cust_phone;
				$r['alamat'] = $user->cust_address;
				$r['created_at'] = $user->created_at;
				$r["verify"] = $user->cust_phone_verify ?? 0;
				$r["verify_email"] = $user->cust_email_verify ?? 0;
				$r["cust_avatar"] = $avatar_profile;
				$r["cust_role"] = $user->cust_role;
				$r["cust_reseller"] = $user->cust_reseller;

				/*
				$r['bday'] = $user->cust_bday;
				$r['created_at'] = $user->created_at;
				$r['verify'] = $user->cust_phone_verify;
				$r['verify_email'] = $user->cust_email_verify;
				*/
				// update token id
				$data_token = [
					'cust_token_id' => $request->cust_token,
				];
				$save_update = DB::table("customers")->where('id', $user->id)->update($data_token);
            } else {
				$mode = "add";
				$nama_users = explode("@",$email);
				$data = [
					'stt_id' => '1', // defaul apps
					'ct_id' => '16', // defaul apps
					'cust_name' => str_replace("."," ",$nama_users[0]),
					'cust_email' => $email,
					'password' => Hash::make('123456789'), // pass default
					'created_at' => date("Y-m-d H:i:s"),
					'cust_platform' => 'android',
					'cust_token_id' => $request->cust_token,
				];
				$form_save->storeData($mode, $data);
				
				$user = User::where('cust_email', $email)->first();
				// tampilkan respon data...
				if(empty($user->cust_avatar)){
					if($user->cust_gender == 'm'){
						$avatar_profile = "https://topsystem.id/upload/avatar/AV1.png";
					} else {
						$avatar_profile = "https://topsystem.id/upload/avatar/AV51.png";
					}
				} else {
					$avatar_profile = $user->cust_avatar;
				}

				$r['code'] = "200";
				$r['message'] = "Login Sukses";
				$r['cust_id'] = $user->id;
				$r['email'] = $user->cust_email;
				$r['nama'] = $user->cust_name;
				$r['phone'] = $user->cust_phone;
				$r['alamat'] = $user->cust_address;
				$r['created_at'] = $user->created_at;
				$r["verify"] = $user->cust_phone_verify ?? 0;
				$r["verify_email"] = $user->cust_email_verify ?? 0;
				$r["cust_avatar"] = $avatar_profile;
				$r["cust_role"] = $user->cust_role;
				$r["cust_reseller"] = $user->cust_reseller;
            }
		} else {
                //Auth::logout();
				$r['code'] = "400";
				$r['message'] = "Data tidak ditemukan";
				$r['cust_id'] = "";
				$r['email'] = "";
				$r['nama'] = "";
				$r['phone'] = "";
				$r['alamat'] = "";
				$r['created_at'] = "";
				$r["verify"] = 0;
				$r["verify_email"] = 0;
				$r["cust_avatar"] = "";
				$r["cust_role"] = "";
				$r["cust_reseller"] = 0;
		}
		
		$respon_data = ['status' => $r];
		
        return response()->json($respon_data);
    }
    
    public function userView(Request $request)
    {
        $user = new User;
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
        $data = [
            'cust_email' => $request->l_username_email,
            'password' => $request->l_password,
        ];
        $check = $user->checkData(['id','cust_email','cust_name','cust_phone','created_at','cust_address', 'cust_bday', 'cust_phone_verify', 'cust_email_verify'], ['id' => $request->cust_id, 'cust_delete' => '0']);
            if (!empty($check)) {
                $r['status'] = "200";
				$r['message'] = "Data ditemukan";
				$r['cust_id'] = $check->id;
				$r['email'] = $check->cust_email;
				$r['nama'] = $check->cust_name;
				$r['phone'] = $check->cust_phone;
				$r['bday'] = $check->cust_bday;
				$r['alamat'] = $check->cust_address;
				$r['created_at'] = $check->created_at;
				$r['verify'] = $check->cust_phone_verify;
				$r['verify_email'] = $check->cust_email_verify;
            } else {
                Auth::logout();
                $r['status'] = "400";
				$r['message'] = "Data tidak ditemukan";
				$r['cust_id'] = '';
				$r['email'] = '';
				$r['nama'] = '';
				$r['phone'] = '';
				$r['bday'] = '';
				$r['alamat'] = '';
				$r['created_at'] = '';
				$r['verify'] = 0;
				$r['verify_email'] = 0;
            }
        return response()->json($r);
    }
    
//     public function loginGoogle(Request $request)
//     {
//         $user = new User;
//         $client_id = $request->client_id;
//         $api_key = $request->api_key;

// 		$validate = DB::table('api_credentials')
//         ->where([
//             'client_id' => $client_id,
//             'client_api' => $api_key
//         ])->exists();
//         if (!$validate) {
//             $data = [
//                 'status' => [
//                     'code' => 401,
//                     'description' => 'Credential id dan api key tidak valid'
//                 ]
//             ];
//             return response()->json($data);
//         }
//         $data = [
//             'cust_email' => $request->l_username_email,
//         ];
//         Auth::attempt($data);
//         if (Auth::user()) {
//             $user = Auth::user();
//             $check = $user->checkData(['id','cust_email','cust_name','cust_phone'], ['cust_email' => $request->l_username_email, 'cust_delete' => '0']);
//             if (!empty($check)) {
//                 $r['status'] = "200";
// 				$r['message'] = "Login successful.";
// 				$r['cust_id'] = $check->id;
// 				$r['email'] = $check->cust_email;
// 				$r['nama'] = $check->cust_name;
// 				$r['phone'] = $check->cust_phone;
//             } else {
//                 Auth::logout();
//                 $r['status'] = "400";
// 				$r['message'] = "Invalid Email.";
// 				$r['cust_id'] = '';
// 				$r['email'] = '';
// 				$r['nama'] = '';
// 				$r['phone'] = '';
//             }
//         } else {
//             //Auth::logout();
//             $r['status'] = "400";
// 			$r['message'] = "Invalid Email.";
// 			$r['email'] = '';
// 			$r['nama'] = '';
// 			$r['phone'] = '';
//         }
//         return response()->json($r);
//     }

    
    public function login(Request $request, CustomerAccessToken $tokens)
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
        
        $email = $request->input('l_username_email');
        $password = $request->input('l_password');
        $customer = is_string($email) && $email !== '' && is_string($password) && $password !== ''
            ? User::where('cust_email', $email)->where('cust_delete', '0')->first()
            : null;

        if (!$customer || !Hash::check($password, $customer->getAuthPassword())) {
            return response()->json([
                'status' => '400',
                'message' => 'Invalid username or password.',
                'email' => '',
                'nama' => '',
                'phone' => '',
            ]);
        }

        $accessToken = $tokens->issue((int) $customer->id);
        if ($request->has('cust_token')) {
            DB::table('customers')->where('id', $customer->id)
                ->update(['cust_token_id' => $request->cust_token]);
        }

        return response()->json([
            'status' => '200',
            'message' => 'Login successful.',
            'cust_id' => $customer->id,
            'email' => $customer->cust_email,
            'nama' => $customer->cust_name,
            'phone' => $customer->cust_phone,
            'bday' => $customer->cust_bday,
            'verify' => $customer->cust_phone_verify,
            'verify_email' => $customer->cust_email_verify,
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => CustomerAccessToken::LIFETIME,
        ])->header('Cache-Control', 'no-store');
    }

	public function Registers(Request $request)
    {
        $form_save = new User;
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
        //$mode = $request->r_mode;
        //$id = $request->r_id;
        $platform = $request->platform;
		if(empty($platform)){
			$platform = 'android';
		} else { $platform=$platform; }
		$mode = "add";
        $data = [
			'stt_id' => '1',
			'ct_id' => '16',
			'cust_name' => $request->r_username,
			'cust_phone' => $request->r_phone,
			'cust_email' => $request->r_email,
			'password' => Hash::make($request->r_password),
			'created_at' => date("Y-m-d H:i:s"),
			'cust_platform' => $platform,
			//'cust_platform' => 'apps',
			//'user_input' => Auth::user()->id,
			//'user_update' => Auth::user()->id,
		];
		
		$check = $form_save->checkData('id', ['cust_email' => $request->r_email, 'cust_delete' => '0']);
		if (!empty($check)) {
			$r['status'] = "400";
			$r['message'] = "Email sudah digunakan";
		} else {
			if ($form_save->storeData($mode, $data) == "success_add") {
				$r['status'] = "200";
				$r['message'] = "Berhasil melakukan pendaftaran akun";
			} else {
				$r['status'] = "400";
				$r['message'] = "Gagal melakukan pendaftaran akun";
			}
		}
        return response()->json($r);
    }
	
    /*
    public function changePassword(Request $request)
    {
        $user = new User;
        $password = $request->r_password;
        $data = [
            'password' => Hash::make($password),
        ];
        if ($user->storePassword(Auth::user()->id, $data)) {
            $r['status'] = '200';
        } else {
            $r['status'] = '400';
        }
        return response()->json($r);
    }
    */
    
    public function changePassword(Request $request)
    {
        $user = new User;

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
		$password = $request->password;
		if($cust_id=='' || $password==''){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id dan password tidak boleh kosong'
                ]
            ];
            return response()->json($data);
		}
		
        $data = [
            'password' => Hash::make($password),
        ];
        if ($user->storePassword($cust_id, $data)) {
            $r['status'] = '200';
        } else {
            $r['status'] = '400';
        }
        return response()->json($r);
    }
    
    public function updateUser(Request $request)
	{
		$user = new User;

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
		//$cust_name = $request->cust_name;
		$cust_name = strip_tags($request->cust_name);
		$cust_phone = $request->cust_phone;
		$cust_email = $request->cust_email;
		$cust_bday = $request->cust_bday;
		
		// tambahan data
		$cust_gender = $request->cust_gender;
		$cust_address = $request->cust_address;
		
		//if($cust_id=='' || $cust_name=='' || $cust_phone=='' || $cust_bday==''){
		if($cust_id=='' || $cust_name=='' || $cust_bday==''){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id, cust_name, cust_phone, dan cust_bday tidak boleh kosong'
                ]
            ];
            return response()->json($data);
		}
		
		$cust_otp = $request->cust_otp;
		$new_date = explode("/",$cust_bday);
		$new_date_cust = $new_date[2].'-'.$new_date[1].'-'.$new_date[0];
		if(!empty($cust_otp)){
			$data = [
				'cust_name' => strip_tags($request->cust_name),
				'cust_phone' => $request->cust_phone,
				'cust_email' => $request->cust_email,
				//'cust_bday' => date("Y-m-d",strtotime($cust_bday)),
				//'cust_bday' => $cust_bday,
				'cust_bday' => $new_date_cust,
				'cust_otp' => $cust_otp,
				'cust_phone_verify' => '1',
			];
		} else {
            $data = [
                'cust_name' => strip_tags($request->cust_name),
    			//'cust_phone' => $request->cust_phone,
    			//'cust_email' => $request->cust_email,
    			//'cust_bday' => $cust_bday,
    			//'cust_bday' => date("Y-m-d",strtotime($cust_bday)),
    			'cust_bday' => $new_date_cust,
    			'cust_gender' => $cust_gender,
				'cust_address' => $cust_address,
				'cust_bday' => $new_date_cust,
            ];
		}
		$save_update = DB::table("customers")->where('id', $cust_id)->update($data);
        if ($save_update) {
            //   cek user
			$arr_cust = DB::table("customers")->where("id","=",$cust_id)->first();
			// cek kelengkapan data
			if($arr_cust->cust_fulldata == 0){
				if($arr_cust->cust_name!='' && $arr_cust->cust_phone!='' && $arr_cust->cust_email!='' && $arr_cust->cust_bday!='' && $arr_cust->cust_gender!='' && $arr_cust->cust_address!=''){
					// beri score 10 jika tersisi semau data
					DB::table("customers")->where('id', $cust_id)->update([
						'cust_score' => ($arr_cust->cust_score+10), 
						'cust_fulldata' => "1"
					]);
					// input score log
					$data_score = [
						'cust_id' => $arr_cust->id,
						'cscore_type' => 'profile',
						'cscore_lookup_id' => $arr_cust->id,
						'cscore_description' => 'Melengkapi data diri',
						'cscore_value' => 10,
						'created_at' => date("Y-m-d H:i:s"),
					];
					DB::table("customer_scores")->insertGetId($data_score);
				}
			}
			
            $r['status'] = '200';
            $r['description'] = 'Data berhasil di update';
        } else {
            //$r['status'] = '400';
            //$r['description'] = 'Data gagal di update';
            $r['status'] = '200';
            $r['description'] = 'Data berhasil di update';
        }
        return response()->json($r);
	}
	
	public function DeleteAccount(Request $request)
    {
        $user = new User;

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
		if($cust_id==''){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id tidak boleh kosong'
                ]
            ];
            return response()->json($data);
		}		
		
        $data = [
            'cust_delete' => "1",
        ];
        if ($user->storePassword($cust_id, $data)) {
            $r['status'] = '200';
        } else {
            $r['status'] = '400';
        }
        return response()->json($r);
    }
    
    // reset password with otp
    public function changePassword_otp(Request $request)
    {
        $user = new User;

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

        $cust_otp = $request->otp;
		$password = $request->password;
		$type_data = $request->type_data;
		if($type_data == "otp"){
    	    if($cust_otp==''){
                $data = [
                    'status' => [
                        'code' => 400,
                        'description' => 'Field OTP dan Password tidak boleh kosong'
                    ]
                ];
                return response()->json($data);
    		}
		}
		if($type_data == "password"){
    	    if($cust_otp=='' || $password==''){
                $data = [
                    'status' => [
                        'code' => 400,
                        'description' => 'Field OTP dan Password tidak boleh kosong'
                    ]
                ];
                return response()->json($data);
    		}
		}
		/*
		if($cust_otp=='' || $password==''){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field OTP dan Password tidak boleh kosong'
                ]
            ];
            return response()->json($data);
		}
		*/
		
		// jika OTP SALAH
		$arr_user = DB::table('customers')->where("cust_otp","=",$cust_otp)->get()->first();
		if(empty($arr_user)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Kode OTP salah'
                ]
            ];
            return response()->json($data);
		}
		
		if($type_data == "otp"){
            $data = [
                'status' => [
                    'code' => 200,
                    'description' => 'Kode OTP valid'
                ]
            ];
            return response()->json($data);
		}
		
		if($type_data == "password"){
            $data = [
                'password' => Hash::make($password),
                'cust_otp' => NULL,
            ];
            if ($user->storePassword($arr_user->id, $data)) {
                $data = [
                    'status' => [
                        'code' => 200,
                        'description' => 'Password berhasil di reset'
                    ]
                ];
            } else {
                $data = [
                    'status' => [
                        'code' => 400,
                        'description' => 'Gagal melakukan reset password'
                    ]
                ];
            }
            return response()->json($data);
		}
    }
    
    public function phone_verification(Request $request)
	{
        $user = new User;

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
		
        $phone = $request->phone;
		//$arr_user = DB::table('customers')->where("cust_otp","=",$cust_otp)->get()->first();
		$kode_otp = rand(1000,9999);
		//$message = "Kode OTP anda *".$kode_otp."* [Topscore.ID]";
		
		//$message = "Halo! Kode untuk verifikasi akunmu adalah *".$kode_otp."*. Rahasiakan ya.";
		/*
		$message = "Halo! 👋  
Kode verifikasi Topscore kamu adalah *".$kode_otp."*.  

Kode ini berlaku selama 100 detik.  
Jangan bagikan kode ini kepada siapa pun ya 😊";
        */
        
        //$message = "Halo tops, ini kode masuk kamu: *".$kode_otp."*. Rahasiakan ya"; // yang ORI
        /*
        $template_messages = [
            "Halo tops, ini kode masuk kamu: *".$kode_otp."*. Rahasiakan ya",
            "Ini Kode Kamu : $kode_otp. Jangan bagikan ke siapa pun ya.",
            "Berikut kode verifikasi Kamu  $kode_otp. Berlaku 2 menit.",
            "Ini Kode yang Kamu minta: $kode_otp. Gunakan segera.",
            "Silakan gunakan kode berikut untuk verifikasi: $kode_otp.",
            "Kode Kamu adalah $kode_otp. Tetap rahasiakan ya, dan segera digunakan.",
            "Berikut kode keamanan Kamu : $kode_otp. Jangan diberikan ke siapa pun.",
            "Gunakan kode ini untuk melanjutkan proses : $kode_otp.",
            "Kode verifikasi Kamu  $kode_otp. Berlaku singkat, segera gunakan.",
            "Ini kode untuk memastikan identitas Kamu : $kode_otp.",
            "Masukkan kode OTP berikut: $kode_otp untuk melanjutkan.",
        ];
        $message = $template_messages[array_rand($template_messages)];
        */
        
        $expire_otp = 2;
        $message_templates = [
        // 110
        "Kode OTP kamu: {$kode_otp}. Berlaku {$expire_otp} menit.",
        "Gunakan OTP berikut untuk verifikasi: {$kode_otp}. Masa berlaku {$expire_otp} menit.",
        "Kode rahasia (OTP): {$kode_otp}. Jangan bagikan ke siapa pun.",
        "OTP kamu adalah {$kode_otp}. Berlaku selama {$expire_otp} menit saja.",
        "Berikut kode OTP kamu: {$kode_otp}. Simpan baik-baik.",
        "Untuk melanjutkan, masukkan OTP ini: {$kode_otp}. Expired {$expire_otp} menit.",
        "Ini kode OTP yang kamu minta: {$kode_otp}.",
        "Kode keamanan kamu: {$kode_otp}. Jangan sebar ke orang lain.",
        "Masukkan kode OTP {$kode_otp} untuk verifikasi akun.",
        "Kode verifikasi kamu adalah: {$kode_otp}. Berlaku sampai {$expire_otp} menit.",

        // 1120
        "Hei! Ini OTP kamu: {$kode_otp}. Batas waktu: {$expire_otp} menit.",
        "Silakan pakai OTP {$kode_otp} untuk menyelesaikan proses.",
        "Kode akses sementara: {$kode_otp}. Gunakan sebelum {$expire_otp} menit.",
        "Permintaan OTP kamu berhasil. Kodenya: {$kode_otp}.",
        "Harap masukkan kode OTP berikut: {$kode_otp}.",
        "Pakai kode {$kode_otp} untuk login ke akun kamu.",
        "Kode rahasia untuk kamu: {$kode_otp}.",
        "OTP aktif: {$kode_otp}. Masa aktif hanya {$expire_otp} menit.",
        "Kode ini diperlukan untuk lanjut: {$kode_otp}.",
        "OTP verifikasi sistem: {$kode_otp}.",

        // 21–30
        "Mohon verifikasi dengan kode: {$kode_otp}.",
        "Verifikasi akun kamu pakai OTP: {$kode_otp}.",
        "Kode login cepat: {$kode_otp}.",
        "OTP: {$kode_otp}. Ingat, jangan bagikan ke siapa pun.",
        "Kode autentikasi: {$kode_otp}.",
        "Masukkan angka berikut sebagai OTP: {$kode_otp}.",
        "Kode satu kali (OTP): {$kode_otp}.",
        "Kami mengirimkan OTP kamu: {$kode_otp}.",
        "Gunakan kode ini untuk melanjutkan proses: {$kode_otp}.",
        "Berikut kode akses: {$kode_otp}.",

        // 31–40
        "Hai, ini kode keamananmu: {$kode_otp}.",
        "Kode verifikasi sementara: {$kode_otp}.",
        "Untuk alasan keamanan, kami kirimkan OTP: {$kode_otp}.",
        "Gunakan OTP {$kode_otp} untuk menyelesaikan permintaanmu.",
        "Kode verifikasi rahasia: {$kode_otp}.",
        "Kami terima permintaanmu. Ini OTP: {$kode_otp}.",
        "Silakan input kode berikut: {$kode_otp}.",
        "Kode untuk akses cepat: {$kode_otp}.",
        "Ini kode verifikasi terbaru: {$kode_otp}.",
        "OTP kamu untuk proses ini: {$kode_otp}.",

        // 41–50
        "Kode validasi akun kamu: {$kode_otp}.",
        "Masukkan kode berikut untuk lanjut: {$kode_otp}.",
        "Ini kode konfirmasi: {$kode_otp}. Berlaku {$expire_otp} menit.",
        "Untuk verifikasi, pakai kode: {$kode_otp}.",
        "Kode berikut harus dimasukkan sekarang: {$kode_otp}.",
        "Silakan verifikasi nomor kamu dengan kode: {$kode_otp}.",
        "Kode ini dikirim atas permintaanmu: {$kode_otp}.",
        "Keamanan akun kamu penting. OTP: {$kode_otp}.",
        "Kode konfirmasi keamanan: {$kode_otp}.",
        "Gunakan angka berikut untuk verifikasi identitas: {$kode_otp}."
    	];
    	$message = $message_templates[array_rand($message_templates)];
        //$send_wa = $this->waSend($phone, $message); // wa un official
        
        
        // cek 1 no hanya 1x 
        //$cek_sent = DB::table("whatsapps")->whereRaw(["wa_phone"=>$phone])->first();
        
        $message = $kode_otp.' adalah kode verifikasi Anda. Demi keamanan, jangan bagikan kode ini.'; // manual 
        $cek_sent = DB::table('whatsapps')->where('wa_phone', 'like', "%{$phone}%")->whereDate('created_at', now()->toDateString())->exists();
        if(!$cek_sent){
        //$send_wa = $this->otpSend($phone, $message, $kode_otp); // wa official
        }
		
		
		// kusus buat kirim sms api
		//$message = $kode_otp." gunakan untuk masuk aplikasi Topscore";
		//$send_wa = $this->send_sms($phone, $message);
		//echo $send_wa; exit();
		//if($send_wa == "Success"){ // untuk wa un official
		//if(1==1){ // untuk wa official
		if(!$cek_sent){
		    // save ke database *** log Request OTP
            $data_log = [
                    //'cust_id' => $cust_id,
                    'wa_receiver' => $phone,
                    'wa_phone' => $phone,
                    'wa_message' => $message,
                    'wa_status' => 'Success',
                    'created_at' => date("Y-m-d H:i:s"),
                    'ip_address' => $request->ip(),
            ];
        	DB::table("whatsapps")->insertGetId(array_merge($data_log));
		    
            $data = [
                'status' => [
                    'code' => 200,
					'otp' => $kode_otp,
                    'description' => 'Kode OTP berhasil dikirim'
                ]
            ];
		} else {
		    $data_log = [
                    //'cust_id' => $cust_id,
                    'wa_receiver' => $phone,
                    'wa_phone' => $phone,
                    'wa_message' => $message,
                    'wa_status' => 'Pending',
                    'created_at' => date("Y-m-d H:i:s"),
            ];
        	DB::table("whatsapps")->insertGetId(array_merge($data_log));
        	
		    $kode_otp = rand(1000,9999);
            $data = [
                'status' => [
                    'code' => 200,
                    //'otp' => '',
                    'otp' => $kode_otp,
					'description' => 'Kode OTP gagal dikirim'
                ]
            ];
		}
		return response()->json($data);
	}
    
    // send SMS
	private function send_sms($phone, $message)
	{
		$url_api = 'https://api.zuwinda.com/v1.2/message/sms/send-text';
		$token = '6948b9e76ae57450968272218e751add';
		
		$phone = str_replace(['+',' '], ['',''], $phone);
        /*
		$check = substr($phone, 0, 2);
        if ($check == '62' || $check == 62) {
            $phone = preg_replace('/^0/', '62', $phone);
        }
		*/
		$to = $phone;
		$msg = $message;
		$curl	= curl_init();
		$data = [
			'type' => 'CHEAP',
			//'type' => 'PREMIUM',
			'content' => $msg,
			'to' => $to,
		];
		
		curl_setopt($curl,CURLOPT_HTTPHEADER,array("x-access-key: ".$token,));
		curl_setopt($curl,CURLOPT_CUSTOMREQUEST,"POST");
		curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($data));
		curl_setopt($curl,CURLOPT_URL,$url_api);
		curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,0);
		curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,0);
		$result=curl_exec($curl);
		curl_close($curl);
		$getdata=json_decode($result);
		//echo ($result);
		if($getdata->success == true){
			return 'Success';
		} else {
			return 'Fail';
		}
	}
    
    // send WA
    private function waSend($phone, $message)
    {
        $phone = str_replace(['+',' '], ['',''], $phone);
        $check = substr($phone, 0, 1);
        if ($check == '0' || $check == 0) {
            $phone = preg_replace('/^0/', '62', $phone);
        }
        //$message = "Kode OTP anda *".$kode_otp."* [Topscore.ID]";
		$message = $message;

       $curl = curl_init();
        //$token = "0cm5DVadWhLBZDvXU7PiGB1ZHhQ917bmq5yH49d2Own4JPfwStHc3Qa0G866hIQZ"; // awalnya
        //$token = "io8CnOK0dNq4zFubn6lKRkeCqMNehSi9TWavFtrmodKc8oP0rqAG6VA5LVVc0qx4";
        
        //$token = 'b896cgKJEFtp29BSXM1KusERqgqyicxQF2SgkAla40o75EgnXUUBQgq'; // 6283857170970
        //$token = 'hLFV80YIhFmOJSyIgSeaigvRe3jFOuq3tWpjBgm6vw8FOchMchmXili'; // 6282130841328
        //$token = config('services.whatsapp.wablas_token'); // default
        
        $token_active = [
						 config('services.whatsapp.wablas_token'),  // default
                  		 "7OVKxck66dlL7SHvyydzIbPBoHHz7kJjTE8kLyOrNclTjzNx0QEyTpC", // Topscore No 2
                  	     "hLFV80YIhFmOJSyIgSeaigvRe3jFOuq3tWpjBgm6vw8FOchMchmXili", // Topscore No 1
                  	     "WBbcEq9HiAUvTbYaBr6Ngh5LcmaHny9zKr9FT7SVlvVk3O3mVN5mb7v", // topscore struk 3
                  	     ];
        // Wablas token is provided via environment configuration.
		$token = $token_active[rand(0,2)];
        $token = "RBz7aT9pqGAHyYUvPHzZ7BcEiWWUUxOIPKjriihDtRQWlAzEzHBUjYd"; // nomor ujungnya 2652
        /*
        $data = [
            'phone' => $phone,
            'message' => $message,
        ];
        curl_setopt($curl, CURLOPT_HTTPHEADER,
            array(
                "Authorization: $token",
            )
        );
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($curl, CURLOPT_URL, "https://pati.wablas.com/api/send-message");
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

        $result = curl_exec($curl);
        curl_close($curl);
        $getdata = json_decode($result);
        //echo $result; 
        if ($getdata->status=='pending') {
            return 'Success';
        } else {
            return 'Fail';
        }
        */
        // END Pati wa blast
        
        $dataSending = [
			"api_key" => config('services.whatsapp.watzap_api_key'),
			"number_key" => config('services.whatsapp.watzap_number_key'),
			"phone_no" => $phone,
			"message" => $message,
			"wait_until_send" => "1",
		];
		
		//$curl = curl_init();
		curl_setopt_array($curl, array(
		  CURLOPT_URL => 'https://api.watzap.id/v1/send_message',
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => '',
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 0,
		  CURLOPT_FOLLOWLOCATION => true,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => 'POST',
		  CURLOPT_POSTFIELDS => json_encode($dataSending),
		  CURLOPT_HTTPHEADER => array(
			'Content-Type: application/json'
		  ),
		));
		$response = curl_exec($curl);
		curl_close($curl);
		$getdata = json_decode($response);
		//echo $response;
		if($getdata->status=='200') {
           	return 'Success';
		} else {
			return 'Fail';
		}
    }

    // cek email sudah terdaftar
	public function CekRegisterEmail(Request $request)
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

		$email 	= $request->email;
		if(empty($email)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Email tidak boleh kosong'
                ]
            ];
            return response()->json($data);
		}
		
        $arr_cust = DB::table("customers")
		->select('*')
		->where('cust_email', '=', $email)
		->where('cust_delete', '=', '0')
        ->get()->first();
        if(empty($arr_cust)){
            $r['status'] = 200;
    		$r['description'] = 'Email masih tersedia';
        } else {
            $r['status'] = 400;
            $r['description'] = 'Email sudah terdaftar';
        }
		return response()->json($r);
	}
    
	// update email verify
	public function EmailVerify(Request $request)
	{
		$user = new User;

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
		$cust_email = $request->cust_email;
		if($cust_id=='' || $cust_email==''){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id, dan cust_email tidak boleh kosong'
                ]
            ];
            return response()->json($data);
		}
		
		// cek email sudah di gunakan
		$user_email = User::where('cust_email', $cust_email)->where('cust_delete', '=', '0')->first();
		if($user_email){
			if($cust_id != $user_email->id){
				$data = [
					'status' => [
						'code' => 400,
						'description' => 'Email '.$cust_email.' sudah digunakan',
					]
				];
				return response()->json($data);
			}
		}
		
		$user = User::where('id', $cust_id)->first();
		if(!$user){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data customer belum terdaftar di sistem'
                ]
            ];
            return response()->json($data);
		}
		
		if($user->cust_email_verify==1){
            $data = [
                'status' => [
                    'code' => 200,
                    'description' => 'Email sudah terverifikasi'
                ]
            ];
            return response()->json($data);
		}
		
		// update data...
		$data_update = [
			'cust_email' => $cust_email,
			'cust_email_verify' => '1',
		];
		
		$save_update = DB::table("customers")->where('id', $cust_id)->update($data_update);
        if ($save_update) {
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Email berhasil di verifikasi'
				]
			];
        } else {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Email gagal di verifikasi'
				]
			];
        }
        return response()->json($data);
	}
    
    // update kode referal user ************
	public function ReferalUpdate(Request $request)
	{
		$user = new User;

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
		$cust_refcode = $request->cust_refcode;
		if($cust_id=='' || $cust_refcode==''){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id, dan cust_refcode tidak boleh kosong'
                ]
            ];
            return response()->json($data);
		}
		
		// cek email sudah di gunakan
		$arr_user = User::where('id', $cust_id)->where('cust_delete', '=', '0')->first();
		if(!$arr_user){
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data customer belum terdaftar di sistem',
				]
			];
				return response()->json($data);
		}
		
		$arr_referal = User::where('cust_referal_code', $cust_refcode)->where('cust_delete', '=', '0')->first();
		if(!$arr_referal){
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Kode referal tidak valid',
				]
			];
			return response()->json($data);
		}
		
		// ts_customer_referals
		$join_referal = DB::table("customer_referals")->where('cust_id_ref', $cust_id)->first();
		if($join_referal){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Anda sudah pernah menggunakan kode referal'
                ]
            ];
            return response()->json($data);
		}
		
		// save log penggunaan kode referal customer
		$data_ref = [
			'cust_id' => $arr_referal->id,
			'cust_id_ref' => $arr_user->id,
			'cf_code' => $cust_refcode,
			'created_at' => date("Y-m-d H:i:s"),
		];
		$save_data = DB::table("customer_referals")->insertGetId(array_merge($data_ref));

        if ($save_data) {
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Kode referal berhasil di simpan'
				]
			];
        } else {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Kode referal gagal di simpan'
				]
			];
        }
        return response()->json($data);
	}
    	
    // kirirm OTP menggunakan wa official TOPSCORE
	private function otpSend($phone, $wa_message, $kode_otp)
	{
		$phone = str_replace('+', '', $phone);
        $check = substr($phone, 0, 1);
        if ($check == '0' || $check == 0) {
            $phone = preg_replace('/^0/', '62', $phone);
        }
		
		\App\Jobs\SendWhatsappJob::dispatch($phone, $wa_message);
		
        return json_encode([
            'status' => 'pending',
            'message' => 'OTP dispatched to Watzap worker'
        ]);
	}
        
    // check valid OTP atau tidak?
	public function otp_verification(Request $request)
	{
        $user = new User;
		
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
		
        $phone = $request->phone;
		$otp = $request->otp;
		if (!$phone || !$otp) {
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Phone dan OTP tidak boleh kosong!'
                ]
            ];
            return response()->json($data);
        }

        $phone = str_replace('+', '', $phone);
        $check = substr($phone, 0, 1);
        if ($check == '0') {
            $phone = preg_replace('/^0/', '62', $phone);
        }
		
		// cek otp di database sesuai nomor phone dan masih aktif OTPnya
		$check_otp = DB::table("customer_otps")->where(["csotp_phone"=>$phone, "csotp_is_used"=>"0"])->orderBy("id","DESC")->first();
		if(!$check_otp){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Kode OTP tidak valid!'
                ]
            ];
            return response()->json($data);
		}
		
		if($check_otp->csotp_code != $otp){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Kode OTP tidak valid!'
                ]
            ];
            return response()->json($data);
		}

		// update token id
		$data_update = [
				'csotp_is_used' => "1",
				'updated_at' => date("Y-m-d H:i:s"),
		];
		$save_update = DB::table("customer_otps")->where('id', $check_otp->id)->update($data_update);

		$data = [
            'status' => [
            	'code' => 200,
				'description' => 'Kode OTP valid'
			]
		];
		return response()->json($data);
	}
	
	// register V3 - Desember 2025
	public function RegisterNew3(Request $request)
    {
        $form_save = new User;
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

		$phone = str_replace(" ","",$request->cust_phone);
		if(substr($phone,0,2) == "62"){
			$phone = "0".substr($phone,2);
		} else
		if(substr($phone,0,3) == "+62"){
			$phone = "0".substr($phone,3);
		} else {
			$phone = $phone;
		}
        $user = User::where('cust_phone','=',$phone)->where('cust_delete','=','0')->first();
		// tampilkan error jika data sudah ada
		if (!empty($user)) {
			$data = [
                'status' => [
                    'code' => 400,
                    'message' => 'Nomor handphone sudah digunakan',
                ]
            ];
			return response()->json($data);
		}

		if(empty($request->cust_name) || empty($request->cust_bday) || empty($phone) || empty($request->cust_token)){
			$data = [
                'status' => [
                    'code' => 400,
                    'message' => 'Field cust_name, cust_bday, cust_gender, cust_phone dan cust_token tidak boleh kosong',
                ]
            ];
			return response()->json($data);
		}
		
		$cust_email = $request->cust_email;
		$cust_avatar_upload = $request->cust_avatar_upload;
		$cust_avatar = $request->cust_avatar;
		
		// cek jika ada kode referal yang d isi
		$kode_referal = $request->cust_referal;
		$arr_referal = DB::table("customers")->where("cust_referal_code","=",$kode_referal)->where("cust_flag_referal","=",1)->first();
		if($kode_referal){
			if(!$arr_referal){
				$data = [
					'status' => [
						'code' => 400,
						'message' => 'Kode referal tidak valid',
					]
				];
				return response()->json($data);
			}
		}
		
		if(empty($request->cust_gender)){$cust_gender = 'w';} else {$cust_gender = $request->cust_gender;}
		
        $platform = $request->platform;
		if(empty($platform)){
			$platform = 'android';
		} else { $platform=$platform; }
		
		$cust_bday = $request->cust_bday;
		$new_date = explode("/",$cust_bday);
		$new_date_cust = $new_date[2].'-'.$new_date[1].'-'.$new_date[0];
		
		// jika ada email maka validasi emailnya ***
		if($request->cust_email){$cust_email_verify=1;} else {$cust_email_verify=0;}
        $data = [
			'stt_id' => '1',
			'ct_id' => '16',
			'cust_name' => strip_tags($request->cust_name),
			'cust_phone' => $phone,
			'cust_bday' => $new_date_cust,
			'cust_gender' => $cust_gender,
			'cust_email' => $request->cust_email,
			'cust_platform' => $platform,
			'cust_token_id' => $request->cust_token,
			'cust_phone_verify' => 1,
			'cust_email_verify' => $cust_email_verify,
			'cust_platform' => $request->cust_platform,
			'cust_select_category' => $request->cust_select_category,
			'cust_score' => 1,
			'cust_infotops' => $request->cust_infotops,
			'created_at' => date("Y-m-d H:i:s"),
		];
		
		// jika upload file (https://www.topsystem.id/upload/avatar/AV2.png)
		$data_upload = array();
		if ($request->hasFile('cust_avatar_upload')) {
			$request->validate([
				'cust_avatar_upload' => 'required|file|mimes:jpg,jpeg,png,gif',
			]);
			$image = $request->file('cust_avatar_upload');
			$input['fileName'] = 'cust_avatar_'.rand(10000,99999).time().'.'.$image->extension();
			$destinationPath = public_path('/avatar');
			$image->move($destinationPath, $input['fileName']);
			$data_upload = ['cust_avatar' => url('public/avatar').'/'.$input['fileName']];
		}
		// jika ambil gambar dari path ***
		if($request->cust_avatar){
			//$data_upload = ['cust_avatar' => "https://www.topsystem.id/upload/avatar/".$request->cust_avatar;
			$data_upload = ['cust_avatar' => $request->cust_avatar];
		}
		// save database...
		$saves = DB::table("customers")->insertGetId(array_merge($data, $data_upload));

		
		//if ($form_save->storeData($mode, $data) == "success_add") {
		if($saves){
			$user_login = User::where('cust_phone','=',$phone)->where('cust_delete','=','0')->first();
			// update nama customer default
			/*
			$nama_customers = 'Topscore user '.$user_login->id;
			$custupdate = [
				'cust_name' => $nama_customers,
			];
			$save_update = DB::table("customers")->where('id', $user_login->id)->update($custupdate);
			*/
			
			// save kode referal save log...
			if($kode_referal){
				$data_ref = [
					'cust_id' => $arr_referal->id,
					'cust_id_ref' => $user_login->id,
					'cf_code' => $kode_referal,
					'created_at' => date("Y-m-d H:i:s"),
				];
				DB::table("customer_referals")->insertGetId(array_merge($data_ref));
			}
			
			if(empty($user_login->cust_avatar)){
				if($user_login->cust_gender == 'm'){
					$avatar_profile = "https://www.topsystem.id/upload/avatar/AV1.png";
				} else {
					$avatar_profile = "https://www.topsystem.id/upload/avatar/AV51.png";
				}
			} else {
				$avatar_profile = $user_login->cust_avatar;
			}
			
			// input log coin (mulai 2026 jadi 10000)
			if(date("Y-m-d")>'2026-01-01'){ $nilai_coin = 10000; } else {$nilai_coin = 0;}
			// expired coin
			$date_reg = date("Y-m-d H:i:s");
			//$expired_coin = date('Y-m-d H:i:s', strtotime("+365 day", strtotime($date_reg))); // 1 tahun expired
			$expired_coin = date('Y-m-d H:i:s', strtotime("+7 day", strtotime($date_reg))); // 1 tahun expired
			$expired_coin_text = date("d/m/Y H:i:s",strtotime($expired_coin));
			$data_coin = [
				'cust_id' => $user_login->id,
				'ccoin_type' => 'new user',
				'ccoin_lookup_id' => $user_login->id,
				'ccoin_description' => 'New user aplikasi topscore',
				'ccoin_value' => $nilai_coin,
				'created_at' => date("Y-m-d H:i:s"),
				'ccoin_expired' => substr($expired_coin,0,10), // tanggal kadaliarsa koin
			];
			DB::table("customer_coins")->insertGetId(array_merge($data_coin));							
			// tambahkan nilai koin untuk customer
			$cust_coin_update = $user_login->cust_coin + $nilai_coin;
			DB::table("customers")->where('id', $user_login->id)->update(['cust_coin'=>$cust_coin_update]);
			
			
			if($nilai_coin>0){
				$info_coin_hangus = 'koin '.number_format($nilai_coin,0,',','.').' akan hangus pada tanggal '.$expired_coin_text;
			} else {
				$info_coin_hangus = '';
			}
			
			$data = [
                'status' => [
                    'code' => 200,
                    'message' => 'Berhasil melakukan pendaftaran akun',
                    'cust_id' => $user_login->id,
                    'email' => $user_login->cust_email ?? '',
                    'nama' => $user_login->cust_name ?? '',
                    'phone' => $user_login->cust_phone,
                    'alamat' => $user_login->cust_address ?? '',
                    'created_at' => date("Y-m-d H:i:s",strtotime($user_login->created_at)),
					//'created_at' => $user_login->created_at,
					'verify' => 1,
					'verify_email' => $user_login->cust_email_verify,
					'cust_avatar' => $avatar_profile,
					'cust_role' => $user_login->cust_role,
					'type_benefit' => 'coin', // coin, voucher
					'cust_coin' => $nilai_coin,
					'cust_coin_info' => $info_coin_hangus,
                ]
            ];

            // input score log
			$data_score = [
				'cust_id' => $user_login->id,
				'cscore_type' => 'profile',
				'cscore_lookup_id' => $user_login->id,
				'cscore_description' => 'Pendaftaran akun baru',
				'cscore_value' => 1,
				'created_at' => date("Y-m-d H:i:s"),
			];
			DB::table("customer_scores")->insertGetId($data_score);
			
		} else {
			$data = [
                'status' => [
                    'code' => 400,
                    'message' => 'Gagal melakukan pendaftaran akun',
                ]
            ];
		}
		
        return response()->json($data);
    }
    
    // kirim manual token di chat wa user ***
	public function sendwaUser(Request $request)
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
		
        $phone = $request->phone;
        if (!$phone) {
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'phone tidak boleh kosong!'
                ]
            ];
            return response()->json($data);
        }

        $phone = str_replace(['+',' '], ['',''], $phone);
        $check = substr($phone, 0, 1);
        if ($check == '0' || $check == 0) {
            $phone = preg_replace('/^0/', '62', $phone);
        }
		
/*
Halo Admin,

Permintaan login aplikasi.
Mohon jangan ubah pesan ini.

LOGIN-9f8a23bd
myapp://login?token=LOGIN-9f8a23bd
*/
		
		//$token = 'LOGIN-' . Str::lower(Str::random(8));		
		$token = 'TOPS-' . Str::lower(Str::random(8));
		// cek token aktif
		//$arr_token = DB::table('customer_tokens')->where("ctoken_phone",$phone)->where("ctoken_expired_at",">",now())->first();
		$arr_token = DB::table('customer_tokens')
			->where("ctoken_phone",$phone)
			->where("ctoken_expired_at",">",now())
			->where("ctoken_is_match","=",'0')
			->first();
		if(!$arr_token){
			//$ctoken_expired_at = date('Y-m-d H:i:s', strtotime('+600 seconds')); // expired dalam 10 menit
			//$ctoken_expired_at = date('Y-m-d H:i:s', strtotime('+30 seconds')); // expired dalam 10 menit
			///$ctoken_expired_at = date('Y-m-d H:i:s', strtotime('+7800 seconds')); // expired dalam 2 10 menit jam
			//$ctoken_expired_at = date('Y-m-d H:i:s', strtotime('+86400 seconds')); // expired dalam 1 hari (24jam)
			$ctoken_expired_at = date('Y-m-d H:i:s', strtotime('+31 day')); // 31 hari
			$data_token = [
				'ctoken_phone' => $phone,
				'ctoken_id' => $token,
				'ctoken_expired_at' => $ctoken_expired_at,
				'created_at' => date("Y-m-d H:i:s"),
			];
			DB::table("customer_tokens")->insertGetId(array_merge($data_token));
		} else {
			$token = $arr_token->ctoken_id;
			$ctoken_expired_at = $arr_token->ctoken_expired_at;
		}
/*
$message = 'Halo MinTops,

Permintaan login aplikasi.

Mohon jangan ubah pesan ini.

'.$token;
*/
$message = '*Halo Tops!*
Untuk masuk ke aplikasi Topscore, cukup kirim pesan ini *tanpa perubahan*.
Setelah pesan terkirim, kamu bisa kembali ke aplikasi *Topscore* atau dengan menekan link https://topscore.id/openapp

'.$token;

$message = '*Halo Tops!*
Untuk masuk ke aplikasi Topscore, cukup kirim pesan ini *tanpa perubahan*.
Setelah pesan terkirim, kamu bisa kembali ke aplikasi *Topscore*.

'.$token;

		$data = [
            'status' => [
            	'code' => 200,
				//'phone_topscore' => "6287875964034", /// nomor wa verified topscore ***
				//'phone_topscore' => "6281371672652", // nomor wa un-official***
				//'phone_topscore' => "6285176990076", // nomor wa un-official***
				//'phone_topscore' => "6285173452483", // nomor wa un-official***
				'phone_topscore' => "6285199683817", // nomor wa official baru agustus 2026
				'token' => $token,
				'message' => $message,
				'expired' => $ctoken_expired_at,
			]
		];
		return response()->json($data);
	}
            
    // cek validasi berdasarkan nomot hp yang di input sebelumnya ***
	public function loginwithToken(Request $request)
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
		
        $phone = $request->phone;
        if (!$phone) {
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'phone tidak boleh kosong!'
                ]
            ];
            return response()->json($data);
        }

        $phone = str_replace(['+',' '], ['',''], $phone);
        $check = substr($phone, 0, 1);
        if ($check == '0' || $check == 0) {
            $phone = preg_replace('/^0/', '62', $phone);
        }
		
		$arr_token = DB::table('customer_tokens')
			->where("ctoken_phone",$phone)
			->where("ctoken_expired_at",">",now())
			//->where("ctoken_is_match",1)
			->orderBy("id","DESC")
			->first();
		if($arr_token){
			/*
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Nomor whatsapp dan token valid',
				]
			];
			*/
			if($arr_token->ctoken_is_match==1){ // nomor match
				$data = [
					'status' => [
						'code' => 200,
						'description' => 'Nomor whatsapp dan token valid',
					]
				];
			} else 
			if($arr_token->ctoken_is_match==2){ // nomor tidak match
				$data = [
					'status' => [
						'code' => 401,
						'description' => 'Nomor whatsapp dan token tidak valid!',
					]
				];
			} else 
			if($arr_token->ctoken_is_match==0){ /// belum ada action / kirim pesan dari nomor wa user
				$data = [
					'status' => [
						'code' => 400,
						'description' => 'Nomor whatsapp dan token belum divalidasi!',
					]
				];
			}
		} else {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Tidak ditemukan nomor whatsapp dan token untuk divalidasi!',
				]
			];
		}

        $data_json = response()->json($data);
		// simpan log json request ***
		$data_log = [
			'ctrespon_phone' => $phone,
			'ctrespon_json' => json_encode($data),
			'created_at' => date("Y-m-d H:i:s"),
		];
		DB::table("customer_token_responses")->insertGetId(array_merge($data_log));
		
		return $data_json;
		//return response()->json($data);
	}
    
    // edit profile dan update avatar ***
	public function updateProfile(Request $request)
    {
        $form_save = new User;
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
		$cust_name = strip_tags($request->cust_name);
		$cust_bday = $request->cust_bday;
		$cust_gender = $request->cust_gender ?? 'm';
		$cust_email = $request->cust_email;
		$cust_avatar_upload = $request->cust_avatar_upload;
		$cust_avatar = $request->cust_avatar;
		$account_google = $request->account_google;
		$phone = str_replace(" ","",$request->cust_phone);
		if(substr($phone,0,2) == "62"){
			$phone = "0".substr($phone,2);
		} else
		if(substr($phone,0,3) == "+62"){
			$phone = "0".substr($phone,3);
		} else {
			$phone = $phone;
		}

		if(empty($cust_id) || empty($cust_name) || empty($cust_bday) || empty($phone) || empty($cust_gender)){
			$data = [
                'status' => [
                    'code' => 400,
                    'message' => 'Field cust_name, cust_bday, cust_gender, cust_phone dan cust_token tidak boleh kosong',
                ]
            ];
			return response()->json($data);
		}

        $user = User::where('id','=',$cust_id)->where('cust_delete','=','0')->first();
		// tampilkan error jika data sudah ada
		if (!$user){
			$data = [
                'status' => [
                    'code' => 400,
                    'message' => 'Data user tidak ditemukan!',
                ]
            ];
			return response()->json($data);
		}
		
		if(empty($request->cust_gender)){$cust_gender = 'w';} else {$cust_gender = $request->cust_gender;}
		
		$new_date = explode("/",$cust_bday);
		$new_date_cust = $new_date[2].'-'.$new_date[1].'-'.$new_date[0];
		
		$data = [
			'cust_name' => strip_tags($request->cust_name),
			//'cust_phone' => $phone,
			'cust_bday' => $new_date_cust,
			'cust_gender' => $cust_gender,
			'cust_email' => $request->cust_email,
			//'cust_phone_verify' => 1,
			//'cust_email_verify' => $cust_email_verify,
			'cust_google' => $account_google,
			'updated_at' => date("Y-m-d H:i:s"),
		];
		
		// jika upload file (https://www.topsystem.id/upload/avatar/AV2.png)
		$data_upload = array();
		if ($request->hasFile('cust_avatar_upload')) {
			$request->validate([
				'cust_avatar_upload' => 'required|file|mimes:jpg,jpeg,png,gif',
			]);
			$image = $request->file('cust_avatar_upload');
			$input['fileName'] = 'cust_avatar_'.rand(10000,99999).time().'.'.$image->extension();
			$destinationPath = public_path('/avatar');
			$image->move($destinationPath, $input['fileName']);
			$data_upload = ['cust_avatar' => url('public/avatar').'/'.$input['fileName']];
		}
		// jika ambil gambar dari path ***
		if($request->cust_avatar){
			$data_upload = ['cust_avatar' => $request->cust_avatar];
		}
		// updat database...
		$saves = DB::table("customers")->where('id', $user->id)->update(array_merge($data, $data_upload));

		if($saves){
			$user_login = DB::table("customers")->where("id",$user->id)->first();
			if(empty($user_login->cust_avatar)){
				if($user_login->cust_gender == 'm'){
					$avatar_profile = "https://topsystem.id/upload/avatar/AV1.png";
				} else {
					$avatar_profile = "https://topsystem.id/upload/avatar/AV51.png";
				}
			} else {
				$avatar_profile = $user_login->cust_avatar;
			}
			
			// cek apakah sudah full data nya?
			if($user_login->cust_phone_verify==1 && $user_login->cust_email_verify==1 && $user_login->cust_name && $user_login->cust_bday){
				DB::table("customers")->where('id', $cust_id)->update([
					'cust_fulldata' => "1"
				]);
			}
			
			$data = [
                'status' => [
                    'code' => 200,
                    'message' => 'Data berhasil diupdate',
                    'cust_id' => $user_login->id,
                    'email' => $user_login->cust_email ?? '',
                    'nama' => $user_login->cust_name ?? '',
                    'phone' => $user_login->cust_phone,
                    'alamat' => $user_login->cust_address ?? '',
					'verify' => $user_login->cust_phone_verify, // verifikasi whatsapp
					'verify_email' => $user_login->cust_email_verify, // verifikasi email
					'cust_avatar' => $avatar_profile,
					'cust_role' => $user_login->cust_role,
					'cust_google' => (int)$user_login->cust_google, // 1 sudah tertaut, 0=belum tertaut
                    'created_at' => date("Y-m-d H:i:s",strtotime($user_login->created_at)),
                ]
            ];
		} else {
			$data = [
                'status' => [
                    'code' => 400,
                    'message' => 'Gagal melakukan update data',
                ]
            ];
		}
		
        return response()->json($data);
    }
    
    public function infoSosmed(Request $request)
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
		
		$sosmed  = ['Tiktok', 'Shopee', 'Tokopedia', 'Lazada', 'Whatsapp', 'Youtube', 'Facebook', 'Website', 'Lainnya'];
		//return response()->json($sosmed);
		$data = [
                'status' => [
                    'code' => 200,
                    'sosmed' => $sosmed
                ]
        ];
		return response()->json($data);
	}
            
    public function logout()
    {
        Auth::logout();
		$r['status'] = '200';
        return response()->json($r);
    }
}
