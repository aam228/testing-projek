<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Hash;

class CronController extends Controller
{
	protected $table = 'customers';
	protected $url_server = 'https://topsystem.id/';
	
	private function checkData($table, $select, $where)
    {
        $affected = DB::table($table)
            ->select($select)
            ->where($where)
            ->get()->first();
        return $affected;
    }
	
	public function resetCoinnewmember(Request $request)
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
        
		// Setting per 7 hari dari tanggal daftar, koin akan di reset...
		$date_register = date('Y-m-d', strtotime("-7 day", strtotime(date("Y-m-d")))); // tanggal pendaftaran 7 hari lalu...
		
        $query_data = DB::table($this->table)
		->select('customers.id', 'cust_name', 'cust_email', 'cust_phone', 'cust_coin')
		->leftJoin('customer_coins', 'customer_coins.cust_id', '=', 'customers.id')
		->where('cust_delete', '=', '0')
		//->where('cust_coin', '>=', '20000') // minimal mempunya 20rb koin dari new member
		->whereRaw("LEFT(ts_customer_coins.created_at,10)='".$date_register."'")
		->where('ccoin_type', '=', 'new user') // hanya user baru saja yang di cek koin nya.
		->get();
		
		$no=0;
        $results = array();
        if (!empty($query_data)) {
            foreach ($query_data as $row) {
				if($row->cust_coin>0){
					$no++;
					$nilai_coin_expired = 20000; // secara default dikurangi 20 rb...
					$cust_coin_update = $row->cust_coin - $nilai_coin_expired;
					if($cust_coin_update<0){
						$cust_coin_update=0;
						$nilai_coin_expired = $row->cust_coin;
					} else {
						$cust_coin_update=$cust_coin_update;
						$nilai_coin_expired = $nilai_coin_expired;
					}
					$results[] = [
						'cust_id' => $row->id,
						'cust_name' => $row->cust_name,
						'cust_phone' => $row->cust_phone,
						'cust_coin' => $row->cust_coin,
						'cust_update' => $cust_coin_update,
						'cust_reset' => $nilai_coin_expired,
						'no_urut' => $no,
					];
					
					// cek apakah sudah pernah di reset atau belum?
					$arr_check = DB::table("customer_coins")->where(['cust_id' => $row->id, 'ccoin_type' => 'expired coin'])->first();					
					if(!$arr_check){
						// lakukan update data...
						$updated_coin = [
							'cust_coin' => $cust_coin_update,
						];
						DB::table($this->table)->where('id', $row->id)->update($updated_coin);
						
						// insert log koin 
						$data_coin = [
							'cust_id' => $row->id,
							'ccoin_type' => 'expired coin',
							'ccoin_lookup_id' => $row->id,
							'ccoin_description' => 'Koin new user sudah kadaluarsa',
							'ccoin_value' => $nilai_coin_expired,
							'created_at' => date("Y-m-d H:i:s"),
						];
						DB::table("customer_coins")->insertGetId(array_merge($data_coin));
					}
					
				}
            }

			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success'
				],
				'customer' => $results,
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
	
	
	// save user ID orderan apps jika masih default... ***
	public function updateUseridapps(Request $request)
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
        
		//$pos_id = $request->pos_id; // id transaksi POS
		
        $query_pos = DB::table("pos_transactions")
		->select('pos_transactions.id', 'u_id', 'pos_status', 'pos_invoice', 'pos_transactions.created_at')
		->leftJoin('customers', 'customers.id', '=', 'pos_transactions.cust_id')
		->whereIn('pos_transactions.pos_status', ['DONE', 'SHIPPING NUMBER', 'IN DELIVERY'])
		->where('is_apps', '=', '1')
		->where('u_id', '=', '198') // user automatic apps
		->whereRaw("LEFT(ts_pos_transactions.created_at,4)='2025'") // mulai tahun 2025 dan seterusnya...
		->get();
		
		$no=0;
        $results = array();
        if ($query_pos) {
            foreach ($query_pos as $row) {
				$no++;
				if($row->cust_coin>0){
					$no++;
					$log_user = DB::table("product_location_setup_transactions")->where(['pt_id' => $row->id])->first();					
					$user_data = DB::table("users")->where(['id' => $log_user->u_id])->first();
					
					$results[] = [
						'pos_id' => $row->id,
						'pos_invoice' => $row->pos_invoice,
						'pos_status' => $row->pos_status,
						'user' => $user_data->u_name,
						'date' => $row->created_at,
					];
					
					if($log_user){
						// lakukan update data USER ID...
						/*
						$updated_user = [
							'u_id' => $log_user->u_id,
						];
						DB::table('pos_transactions')->where('id', $row->id)->update($updated_user);
						*/
					}
				}
            }

			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success'
				],
				'pos' => $results,
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
    
    // kirim whatsapp kusus user masukan ke keranjang belanja dan kirim notifikasi
	public function reminderCart(Request $request)
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
        
		//$pos_id = $request->pos_id; // id transaksi POS
		$tanggal_cart = date('Y-m-d', strtotime('-1 days'));
        $query_cart = DB::table("apps_carts")
		->select('apps_carts.id', 'cust_id', 'product_name', 'color', 'size', 'cust_name', 'cust_phone', 'apps_carts.created_at')
		->leftJoin('customers', 'customers.id', '=', 'apps_carts.cust_id')
		//->where('apps_carts.created_at', '<=', $tanggal_cart.' 00:00:01')
		->whereRaw("LEFT(ts_apps_carts.created_at,10)='".$tanggal_cart."'")
		//->whereRaw("LEFT(ts_pos_transactions.created_at,4)='2025'") // mulai tahun 2025 dan seterusnya...
		->groupBy("apps_carts.cust_id")
		->orderBy("apps_carts.created_at", "DESC")
		//->limit(20)
		->get();
		
		$no=0;
        $results = array();
		$cust_id_array='';
        if ($query_cart) {
            foreach ($query_cart as $row) {
				$no++;
				if(1==1){
					$no++;
					//$log_user  = DB::table("product_location_setup_transactions")->where(['pt_id' => $row->id])->first();					
					//$user_data = DB::table("users")->where(['id' => $log_user->u_id])->first();
					$cust_id_array .= $row->cust_id.',';
					
					$results[] = [
						'cart_id' => $row->id,
						'cust_name' => $row->cust_name,
						'cust_phone' => $row->cust_phone,
						'product_name' => $row->product_name,
						'product_color' => $row->color,
						'product_size' => $row->size,
						'date' => $row->created_at,
					];
					
					if(1==1){
						/*
						$updated_user = [
							'u_id' => $log_user->u_id,
						];
						DB::table('pos_transactions')->where('id', $row->id)->update($updated_user);
						*/
						
						$message = 'Hai '.$row->cust_name.', Barang incaranmu udah masuk keranjang, tapi belum kamu bawa pulang nih! Jangan kendor di akhir pertandingan. 

Ayo, lanjutkan sampai garis finish dengan checkout sekarang. 
🛒 [https://topscore.id/?type=keranjang] 

Di dunia olahraga, yang cepat yang menang!';

						
						if($no<=1 || $row->cust_phone=="08176009898" || $row->cust_phone=="085376341110"){ // hanya 5 orang yang dikirim whatsapp notifikasi, sisanya di kirim notifikasi ***
						// kirim whatsapp
						$wa_respon = $this->waSend($row->cust_phone, $message);
						//$wa_respon = $this->waSend('085376341110', $message);
						
						// simpan log pengiriman whatsapp
						$data_log = [
							'cust_id' => $row->cust_id,
							'wa_receiver' => $row->cust_phone,
							'wa_phone' => $row->cust_phone,
							'wa_message' => $message,
							'wa_status' => $wa_respon,
							'created_at' => date("Y-m-d H:i:s"),
						];
						DB::table("whatsapps")->insertGetId(array_merge($data_log));
						}
						
					}
				}
            }
			
			// kirim notifikasi ke user
			$curl = curl_init();			
			$data_posting = [
				'client_id' => '8025087a9e2e8d0e95e2da2152e8be2f55276616',
				'api_key' => config('services.whatsapp.watzap_api_key'),
				'cust_id' => str_replace([",,",",,,"],[",",","],rtrim($cust_id_array,',')),
				//'cust_id' => '227666',
				'title' => 'Jangan berhenti di tengah trek!', 
				'message' => 'Satu langkah lagi ke garis finish. Checkout Pesananmu sekarang sebelum kehabisan!',
				'slug_url' => 'https://topscore.id/?type=keranjang',
				//'image_url' => '',
			];
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data_posting));
			curl_setopt($curl, CURLOPT_URL, url('api/pushone_user'));
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
	
			$result = curl_exec($curl);
			curl_close($curl);
			$getdata = json_decode($result);
			//return $result;
			// end kirim notifikasi ***
			
			
			
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success'
				],
				'cust_id_all' => str_replace([",,",",,,"],[",",","],rtrim($cust_id_array,',')),
				'respon_notif' => $result,
				'cart' => $results,
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
	
	private function waSend($phone, $message)
    {
        $phone = str_replace(['+',' '], ['',''], $phone);
        $check = substr($phone, 0, 1);
        if ($check == '0' || $check == 0) {
            $phone = preg_replace('/^0/', '62', $phone);
        }
        $message = $message;

        $dataSending = [
			"api_key" => config('services.whatsapp.watzap_api_key'),
			"number_key" => "rS7eNiU9U4DXPRY6",
			"phone_no" => $phone,
			"message" => $message,
			"wait_until_send" => "1",
		];
		
		$curl = curl_init();
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
    
    // kirim whatsapp untuk info user belanja website
	public function waSendwebsite(Request $request)
    {
		$arr_user = DB::table("whatsapps")->where("wa_status","=","Waiting")->orderBy("id")->first();
		if($arr_user){
			$phone = str_replace(['+',' '], ['',''], $arr_user->wa_phone);
			$check = substr($phone, 0, 1);
			if ($check == '0' || $check == 0) {
				$phone = preg_replace('/^0/', '62', $phone);
			}
			//$message = $message;
	
			$dataSending = [
				"api_key" => config('services.whatsapp.watzap_api_key'),
				"number_key" => config('services.whatsapp.watzap_number_key'), // 
				"phone_no" => $phone,
				"message" => $arr_user->wa_message,
				"wait_until_send" => "1",
			];
			
			$curl = curl_init();
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
				// lakukan update data...
				$updated_status = [
					'wa_status' => "Success",
				];
				DB::table("whatsapps")->where('id', $arr_user->id)->update($updated_status);
				return 'Success';
			} else {
				// lakukan update data...
				$updated_status = [
					'wa_status' => "Failed",
				];
				DB::table("whatsapps")->where('id', $arr_user->id)->update($updated_status);
				return 'Fail';
			}
		} else { return '404'; }
    } 
}
