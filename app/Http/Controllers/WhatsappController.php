<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WhatsappController extends Controller
{
    public function index(Request $request)
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
		
		$text_join = 'Silahkan join ke group whatsapp dengan klik link ini : https://chat.whatsapp.com/Eff7hh3SWG082MmYw7YzcP';
		$phone = $request->phone;
		$phone = str_replace('+', '', $phone);
        $check = substr($phone, 0, 1);
        if ($check == '0' || $check == 0) {
            $phone = preg_replace('/^0/', '62', $phone);
        }
        $message = "Halo *Bowo Susanto*!

Terima kasih telah melakukan order event di TOPSCORE.
Kami telah menerima pembayaran anda dengan Invoice *#INV0001*.

Total yang sudah dibayar adalah:
Rp ".number_format(20000,0,',','.')."

".$text_join."
------------------------
Balas pesan ini jika butuh bantuan :)";

       $curl = curl_init();
        $token = "0cm5DVadWhLBZDvXU7PiGB1ZHhQ917bmq5yH49d2Own4JPfwStHc3Qa0G866hIQZ";
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
        /*
		if ($getdata->status=='pending') {
            return 'Success';
        } else {
            return 'Fail';
        }
        */
        $data = [
            'status' => [
                'code' => 200,
                'description' => 'Success'
            ],
        ];
        return response()->json($data);
    }
	
	
	// maxchat whatsapp
	public function sendMessages(Request $request)
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
		if (empty($phone)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Phone tidak boleh kosong'
                ]
            ];
            return response()->json($data);
        }
        
        $phone = str_replace('+', '', $phone);
        $check = substr($phone, 0, 1);
        if ($check == '0' || $check == 0) {
            $phone = preg_replace('/^0/', '62', $phone);
        }
        
        // save log
		$data_log = [
			'lwa_type' => 'api_maxchat',
			'lwa_phone' => $phone,
			//'lwa_description' => "",
			'created_at' => date("Y-m-d H:i:s"),
		];
		$save_log = DB::table("log_whatsapp_api")->insertGetId(array_merge($data_log));
        
		//$phone = $request->phone;
		//if(!$phone){$phone='6285376341110';}
		//$message = 'Halo *Bowo Susanto* ini adalah tes pesan menggunakan API maxchat!';
		
		/*
		$response = Http::withHeaders([
			'Content-Type' => 'application/json',
			'Accept' => 'application/json',
			'Authorization' => 'bearer aff0f3znamns1h4wgu5x'
		])->post('https://demo.maxchat.id/demo1/api/messages', [
			//"to" => "6285376341110",
			"to" => $to,
			"text" => "Halo *Bowo Susanto* ini adalah tes pesan menggunakan API maxchat!"
		]);
		echo $response->status();
		*/
		
		// template ID = 268bdb57-74b8-4c97-bac2-a7eb2c091641
		
		\App\Jobs\SendWhatsappJob::dispatch($phone, "Halo Bowo Susanto ini adalah tes pesan menggunakan API Watzap via Worker!");
		
		return response()->json([
		    'status' => 'Success',
		    'message' => 'Test pesan WhatsApp telah dimasukkan ke antrean.'
		]);
		
		$getdata = json_decode($result);
        /*
		if ($getdata->status=='pending') {
            return 'Success';
        } else {
            return 'Fail';
        }
        */
        $data = [
            'status' => [
                'code' => 200,
                'description' => 'Success'
            ],
        ];
        return response()->json($data);

	}
	
	// cek antrian pengiriman ultah user
	public function bdayCustomer(Request $request)
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
		
		// cek customer ulang tahun
		$query_customer = DB::table("customers")
			->select("customers.id", "cust_name", "cust_phone", "cust_bday")
			->whereRaw("RIGHT(ts_customers.cust_bday,5)='".date("m-d")."'")
			->where("cust_delete","=","0")
			->whereNotNull("cust_token_id")
			->whereNotNull("cust_phone")
			->where("ct_id","=","16") // kusus aplikasi
			//->whereIn('customers.id', ['174865','186104'])
			//->whereIn('customers.id', ['26661'])
			->get();
		
		$respon_data = array();
		if($query_customer){
			foreach($query_customer as $raw){
				// kirim chat wa...
				/*
				$send_wa = $this->sendBday($raw->cust_phone, ucwords(strtolower($raw->cust_name))); // fungsi kirim pesan
				$respon_data[] = [
					'cust_id' => $raw->id,
					'cust_name' => $raw->cust_name,
					'cust_phone' => $raw->cust_phone,
					'cust_bday' => $raw->cust_bday,
					'info_sending' => $send_wa,
				];
				*/
				$phone = str_replace('+', '', $raw->cust_phone);
				$check = substr($phone, 0, 1);
				if ($check == '0' || $check == 0) {
					$phone = preg_replace('/^0/', '62', $phone);
				}
				// kirim chat wa...
				$send_wa = $this->sendBday($phone, ucwords(strtolower($raw->cust_name))); // fungsi kirim pesan
				// save log
				$data_log = [
					'lwa_type' => 'api_maxchat',
					'lwa_phone' => $phone,
					'lwa_description' => $send_wa,
					'created_at' => date("Y-m-d H:i:s"),
				];
				$save_log = DB::table("log_whatsapp_api")->insertGetId(array_merge($data_log));
				
				// show data JSON
				$respon_data[] = [
					'cust_id' => $raw->id,
					'cust_name' => $raw->cust_name,
					'cust_phone' => $phone,
					'cust_bday' => $raw->cust_bday,
					'info_sending' => $send_wa,
				];
			}
		}

        $data = [
            'status' => [
                'code' => 200,
                'description' => 'Success',
				'customer' => $respon_data,
            ],
        ];
		return response()->json($data);
	}
	
	// kirim chat ulang tahun customer
	private function sendBday($phone, $nama_customer)
	{
		$phone = str_replace('+', '', $phone);
        $check = substr($phone, 0, 1);
        if ($check == '0' || $check == 0) {
            $phone = preg_replace('/^0/', '62', $phone);
        }
		
		// save log
		/*
		$data_log = [
			'lwa_type' => 'api_maxchat',
			'lwa_phone' => $phone,
			//'lwa_description' => "",
			'created_at' => date("Y-m-d H:i:s"),
		];
		$save_log = DB::table("log_whatsapp_api")->insertGetId(array_merge($data_log));
		*/
		
		$curl = curl_init();
        $token = "aff0f3znamns1h4wgu5x";
		$body_dinamis = array();
		$body_dinamis[] = ["index" => 1, "type" => "text", "text" => $nama_customer];
        $data = [
			"to" => $phone, 
			"msgType" => "text", 
			"templateId" => "0d54dc11-ea37-4b5b-85c7-7b274b9d6757", 
			"values" => array( 
				"body" => $body_dinamis,
			),
        ];		
		//return response()->json($data);
		
		curl_setopt($curl,CURLOPT_HTTPHEADER,array("Authorization:Bearer ".$token, 'Content-Type:application/json'));
		curl_setopt($curl,CURLOPT_CUSTOMREQUEST,"POST");
		curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($data));
		curl_setopt($curl, CURLOPT_URL, "https://app.maxchat.id/api/messages/push");
		curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,0);
		curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,0);

        $result = curl_exec($curl);
        curl_close($curl);
        return $result; // Tampilkan respon JSON kirim chat...
		/*
		$getdata = json_decode($result);
        $data = [
            'status' => [
                'code' => 200,
                'description' => 'Success'
            ],
        ];
        return response()->json($data);
		*/
	}
	
	// kiirm OTP menggunakan wa official TOPSCORE
	public function otpSend(Request $request)
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
		$phone = str_replace('+', '', $phone);
        $check = substr($phone, 0, 1);
        if ($check == '0' || $check == 0) {
            $phone = preg_replace('/^0/', '62', $phone);
        }
		
		$kode_otp = rand(1000,9999);
		$wa_message = $kode_otp.' adalah kode verifikasi Anda. Demi keamanan, jangan bagikan kode ini.'; // manual 
		
		\App\Jobs\SendWhatsappJob::dispatch($phone, $wa_message);
		
        $data = [
            'status' => [
                'code' => 200,
                'description' => 'Success'
            ],
        ];
        return response()->json($data);
	}
}
