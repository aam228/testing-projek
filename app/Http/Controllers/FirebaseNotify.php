<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class FirebaseNotify extends Controller
{
    // ini_set('max_execution_time', '300');    
    function __construct()
    {
        ini_set('memory_limit', '-1');
        //set_time_limit(0);
        ini_set('max_execution_time', '600'); // 10 Menit
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
		
		$cust_id 	= $request->cust_id;
		$cust_data = DB::table("customers")->where("id","=",$cust_id)->first();
		if (!$cust_data) {
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data user tidak ditemukan'
                ]
            ];
            return response()->json($data);
        }
		
		$access_token = $this->get_access_token(public_path("fgoogle/gtoken/auth_adminsdk_v3.json"));
		//echo $access_token;
		//exit();
        
		if(!empty($cust_data->cust_token_id)){
		    //$token = 'dFvOQDHDTHuiK8d62urJC8:APA91bHEOXzBoYV8x5lOAVW9v5Pu2a8yenZVmBgV_JvaemKsD6ntrQbDUJVUSJv45rY890lkGQ4odfW7MJXNyCqSq4WFCGy2zDmeHPyaPMrY4SX51oFP7jpjO7JEIw7j6oM3jLf1xxZ3';
		    $token = $cust_data->cust_token_id;
		} else {
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Token user tidak tersimpan'
                ]
            ];
            return response()->json($data);
		}
		
		$url = "https://fcm.googleapis.com/v1/projects/auth-tops/messages:send";
		$data = [
			'message' => [
				"data"=> [
					//"title" => "Pesanan dibatalkan",
					//"body" => "Pesanan 0000111110000 berhasil dibatalkan",
					//"icon" => "https://www.clipscutter.com/image/brand/brand-256.png",
					//"image" => "https://images.unsplash.com/photo-1514473776127-61e2dc1dded3?w=871&q=80",
					//"click_action" => "https://topscore.id"
					"score" => "Pesanan dibatalkan",
					"time" => "Pesanan 0000111110000 berhasil dibatalkan",
					"type" => "m.pesanan.status",
				],
				'token' => $token
			]
		];
		$options = array(
			CURLOPT_URL => $url,
			CURLOPT_POST => true,
			CURLOPT_HTTPHEADER => array(
				"Authorization: Bearer " . $access_token,
				"Content-Type: application/json",
			),
			CURLOPT_POSTFIELDS => json_encode($data),
		);
		$curl = curl_init();
		curl_setopt_array($curl, $options);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($curl);
		curl_close($curl);
		echo $response;
    }
	
	// get token 
	private function get_access_token($JSON_file_path)
	{
		$client = new \Google_Client();
		try {
			$client->setAuthConfig($JSON_file_path);
			$client->addScope(\Google_Service_FirebaseCloudMessaging::CLOUD_PLATFORM);
	
			$savedTokenJson = $this->readSavedToken();
	
			if ($savedTokenJson) {
				// the token exists, set it to the client and check if it's still valid
				$client->setAccessToken($savedTokenJson);
				$accessToken = $savedTokenJson;
				if ($client->isAccessTokenExpired()) {
					// the token is expired, generate a new token and set it to the client
					$accessToken = $this->generateToken($client);
					$client->setAccessToken($accessToken);
				}
			} else {
				// the token doesn't exist, generate a new token and set it to the client
				$accessToken = $this->generateToken($client);
				$client->setAccessToken($accessToken);
			}
			
	
			$oauthToken = $accessToken["access_token"];
	
			return $oauthToken;
	
			
		} catch (Google_Exception $e) {}
	   return false;
	}
	
	//Using a simple file to cache and read the toke, can store it in a databse also
	private function readSavedToken() {
	  $tk = @file_get_contents(public_path('fgoogle/gtoken/token.cache'));
	  //echo json_decode($tk); exit();
	  if ($tk) return json_decode($tk, true); else return false;
	}
	
	private function writeToken($tk) {
	 	@file_put_contents(public_path("fgoogle/gtoken/token.cache"),$tk);
	}
	
	private function generateToken($client)
	{
	    //echo (var_dump($client)); exit();
		$client->fetchAccessTokenWithAssertion();
		$accessToken = $client->getAccessToken();
	
		$tokenJson = json_encode($accessToken);
		$this->writeToken($tokenJson);
	
		return $accessToken;
	}
    
    // send manual notif via API
	public function SendNotify(Request $request)
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
		
		$cust_id 	= $request->cust_id; // 174865
		$notif_type	= $request->notif_type;
		$notif_title = $request->notif_title;
		$notif_description = $request->notif_description;
		
		$cust_data = DB::table("customers")->where("id","=",$cust_id)->first();
		if (!$cust_data) {
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data user tidak ditemukan'
                ]
            ];
            return response()->json($data);
        }
		
		$access_token = $this->get_access_token(public_path("fgoogle/gtoken/auth_adminsdk_v3.json"));
		//echo $access_token;
		//exit();
        
		if(!empty($cust_data->cust_token_id)){
		    //$token = 'cUn5RqwfRt21VFvoZNothO:APA91bH6Fxbdwd_4l4lvQXYP-1FmZJV4jYTp5h1drRkl-V0DNT0F3rkJJJg9D9uRqjsbwCt1s_WkGydX3VXrZ_4azFhHcjFescZHkNGHjKgldos4AOi70LsBc86waFWPTPgsdWjbtfoO';
		    $token = $cust_data->cust_token_id;
		} else {
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Token user tidak tersimpan'
                ]
            ];
            return response()->json($data);
		}
		
		$url = "https://fcm.googleapis.com/v1/projects/auth-tops/messages:send";
		$data = [
			'message' => [
				"data"=> [
					//"title" => "Pesanan dibatalkan",
					//"description" => "Pesanan 0000111110000 berhasil dibatalkan",
					//"type" => "m.pesanan.status",
					/*
					"title" => $notif_title,
					"description" => $notif_description,
					"type" => $notif_type,
					*/
					/*
					"score" => $notif_title,
					"time" => $notif_description,
					"type" => $notif_type,
					*/
					"title" => $notif_title,
					"description" => $notif_description,
					"type" => $notif_type,
					"notif_data" => $request->notif_data,
				],
				'token' => $token
			]
		];
		$options = array(
			CURLOPT_URL => $url,
			CURLOPT_POST => true,
			CURLOPT_HTTPHEADER => array(
				"Authorization: Bearer " . $access_token,
				"Content-Type: application/json",
			),
			CURLOPT_POSTFIELDS => json_encode($data),
		);
		$curl = curl_init();
		curl_setopt_array($curl, $options);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($curl);
		curl_close($curl);
		//echo $response;
		$json_respon = json_decode($response);
		//$json_respon->name;
		if(!empty($json_respon->name)){
		//if($json_respon){
            $data = [
                'status' => [
                    'code' => 200,
                    'description' => 'Notifikasi berhasil di kirim'
                ]
            ];
            return response()->json($data);
		} else {
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Notifikasi gagal di kirim'
                ]
            ];
            return response()->json($data);
		}
    }
    
    
    // send multi user notify
	/*
	public function SendMulti(Request $request)
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
		
		//$cust_id 	= $request->cust_id;
		$notif_type	= $request->notif_type;
		$notif_title = $request->notif_title;
		$notif_description = $request->notif_description;
		
		$access_token = $this->get_access_token(public_path("fgoogle/gtoken/auth_adminsdk_v3.json"));
		
		$q_cust = DB::table("customers")
				  ->select('id', 'cust_name', 'cust_phone', 'cust_email', 'cust_coin', 'cust_score', 'cust_token_id')
				  //->where('cust_phone_verify','=','1')
				  ->whereNotNull('cust_token_id')
				  //->whereIn('id',['186104','174865', '26661', '185564'])
				  ->get();
        //$notif_description = "Topscore adalah toko sepatu olahraga terlengkap dengan harga sangat terjangkau Segera beli sepatu di topscore \n";
		$no=0;
		foreach($q_cust as $row){
			if(!empty($row->cust_token_id)){
				$token = $row->cust_token_id;
			} else {
				$token = '';
			}
			
			$url = "https://fcm.googleapis.com/v1/projects/auth-tops/messages:send";
			$data = [
			'message' => [
				"data"=> [
					"title" => $notif_title,
					//"description" => $notif_description,
					"description" => $notif_description,
					"type" => $notif_type,
					"notif_data" => $request->notif_data,
				],
				'token' => $token
			]
		];
			$options = array(
				CURLOPT_URL => $url,
				CURLOPT_POST => true,
				CURLOPT_HTTPHEADER => array(
					"Authorization: Bearer " . $access_token,
					"Content-Type: application/json",
				),
				CURLOPT_POSTFIELDS => json_encode($data),
			);
			$curl = curl_init();
			curl_setopt_array($curl, $options);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			$response = curl_exec($curl);
			curl_close($curl);
			//echo $response;
			//$json_respon = json_encode($response);
			$json_respon = json_decode($response);
			//$json_respon->name;
			//if($json_respon->name){
			if(!empty($json_respon->name)){
				$no++;
			}
		}
		
		// tampilkan respon data...
		if($no>0){
			$data = [
				'status' => [
					'code' => 200,
					'description' => $no.' notifikasi berhasil di kirim',
				]
			];
			return response()->json($data);
		} else {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Notifikasi gagal di kirim',
				]
			];
			return response()->json($data);
		}
    }
    */
    
    public function SendMulti(Request $request)
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
		
		$notif_type	= $request->notif_type;
		$notif_title = $request->notif_title;
		$notif_description = $request->notif_description;
		
		$access_token = $this->get_access_token(public_path("fgoogle/gtoken/auth_adminsdk_v3.json"));
		
        $firebaseToken = array("cR6MkEBFR_6ZHoo5zPQceW:APA91bEbBFqRiEpiiSIOQRO9xw5W4z93855qoWq2SVayQE_mZlCge2zTGqz5SGFRhpZQqI16mKUh9rEkhE4hQWsP6wP7q7YiM4_HCiCtiA_OsQQCZbtLHaxl6RgmhuERKOH8L6hsErZA", 
                          "diivQ--uQA-PVAQExjUO10:APA91bHE4JGBsd22FGYpsCA5AlBRJavL6oWxgL4uYpMsXqkp_Xix-vhHZrsjdAYlWNnWBQhm1NK3xR2PGcUj6Ge72gSrkSsKasm8y-jqbudLKKCSurMSMBpHeh1w44qB1IDwhHw2qrUW"
                         );
        
        //$firebaseToken = "cR6MkEBFR_6ZHoo5zPQceW:APA91bEbBFqRiEpiiSIOQRO9xw5W4z93855qoWq2SVayQE_mZlCge2zTGqz5SGFRhpZQqI16mKUh9rEkhE4hQWsP6wP7q7YiM4_HCiCtiA_OsQQCZbtLHaxl6RgmhuERKOH8L6hsErZA";
        /*
        $data = [
			'message' => [
				"data"=> [
					"title" => $notif_title,
					"description" => $notif_description,
					"type" => $notif_type,
					"notif_data" => $request->notif_data,
				],
				'token' => $firebaseToken,
		    ]
		];
		*/
        //$dataString = json_encode($data);
        
        $data = [
            "message" => [
                //"topic" => 'Topscore_logout',
                //"topic" => 'Topscore_login',
                "topic" => $request->notif_topic,
                "data" => [
                    "title" => $notif_title,
					"description" => $notif_description,
					"type" => $notif_type,
					"notif_data" => $request->notif_data,
                ],
            ]
        ];

        $headers = [
            "Authorization: Bearer " . $access_token,
			"Content-Type: application/json",
        ];
		
		$url = "https://fcm.googleapis.com/v1/projects/auth-tops/messages:send";
        $options = array(
				CURLOPT_URL => $url,
				CURLOPT_POST => true,
				CURLOPT_HTTPHEADER => array(
					"Authorization: Bearer " . $access_token,
					"Content-Type: application/json",
				),
				CURLOPT_POSTFIELDS => json_encode($data),
		);
		$curl = curl_init();
		curl_setopt_array($curl, $options);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($curl);
		curl_close($curl);
		//echo $response;
		// ***
		$json_respon = json_decode($response);
		if(!empty($json_respon->name)){
            $data = [
                'status' => [
                    'code' => 200,
                    'description' => 'Notifikasi berhasil di kirim'
                ]
            ];
            return response()->json($data);
		} else {
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Notifikasi gagal di kirim'
                ]
            ];
            return response()->json($data);
		}

    }
    
}
