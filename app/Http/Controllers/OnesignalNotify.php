<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class OnesignalNotify extends Controller
{
    public function __construct()
    {
        $this->appId = config('services.onesignal.app_id');
        $this->apiKey = config('services.onesignal.api_key');
		$this->url_onesignal = 'https://onesignal.com/api/v1/notifications';
    }
    
	/*
	private function sendToMultipleUsers(array $externalUserIds)
	{
		$rest_api_key = '';
		$app_id = 'com.topscore.app1';
		$response = Http::withHeaders([
			//'Authorization' => 'Basic ' . config('services.onesignal.rest_api_key'),
			'Authorization' => 'Basic '.$rest_api_key,
			'Content-Type' => 'application/json',
		])->post('https://onesignal.com/api/v1/notifications', [
			'app_id' => config('com.topscore.app1'),
			//'app_id' => $app_id,
			'include_external_user_ids' => $externalUserIds,
			'headings' => ['en' => 'Mabar futsal!'],
			'contents' => ['en' => 'Halo tops!'],
		]);
	
		return $response->json();
		//return ($response);
	}
	*/
	
	public function sendPushNotification(array $externalUserIds, string $title, string $message, $imageUrl='', $slug_url='')
	{
		//$imageUrl = 'https://www.topsystem.id/upload/event/1749027692.jpg';		
		// jika ada gambar
		if($imageUrl){
			$response = Http::withHeaders([
				'Authorization' => 'Basic ' . config('services.onesignal.api_key'),
				'Content-Type'  => 'application/json',
			])->post('https://onesignal.com/api/v1/notifications', [
				'app_id' => config('services.onesignal.app_id'),
				'include_external_user_ids' => $externalUserIds,
				'android_channel_id' => '9a73d143-5220-44a4-b585-cc5fd949edfd',
			    'ios_interruption_level' => 'critical',
				'small_icon' => "@mipmap/icon_tops",
				'headings' => [
					'en' => $title
				],
				'contents' => [
					'en' => $message
				],
				'big_picture' => $imageUrl, // ✅ Untuk Android
				'ios_attachments' => [
					'id' => $imageUrl //  Untuk iOS
				],
				// Optional: tambahan data
				'data' => [
					'custom_url' => $slug_url,
					//'order_id' => 1234
				]
			]);
		} 
		// jika tidak ada gambar 
		else {
			$response = Http::withHeaders([
				'Authorization' => 'Basic ' . config('services.onesignal.api_key'),
				'Content-Type'  => 'application/json',
			])->post('https://onesignal.com/api/v1/notifications', [
				'app_id' => config('services.onesignal.app_id'),
				'include_external_user_ids' => $externalUserIds,
				'android_channel_id' => '9a73d143-5220-44a4-b585-cc5fd949edfd',
			    'ios_interruption_level' => 'critical',
				'small_icon' => "@mipmap/icon_tops",
				'headings' => [
					'en' => $title
				],
				'contents' => [
					'en' => $message
				],
				// Optional: tambahan data
				'data' => [
					'custom_url' => $slug_url,
					//'order_id' => 1234
				]
			]);
		}
		
		/*
		return $response->successful()
			? 'Notifikasi berhasil dikirim.'
			: 'Gagal kirim notifikasi: ' . $response->body();
		*/
		
		if($response->successful() == true){
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Notifikasi berhasil di kirim',
				]
			];
		} else {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Notifikasi gagal di kirim',
				]
			];
		}
		return response()->json($data);
	}	
	
	public function sendNotif(Request $request)
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
		
		//$externalUserIds = ['227666'];
		//return $this->sendToMultipleUsers($externalUserIds);
		
		//$users = ['227666', '186104', '188205'];
		//$imageUrl = 'https://www.topsystem.id/upload/event/1749027692.jpg';
		/*
		$imageUrl = '';
		$users = ['227666'];
		$title = 'Halo tops!';
		$message = 'Halo tops';
		*/
		
		$users = $request->cust_id;
		$title = $request->title;
		$message = $request->message;
		$imageUrl = $request->image_url;
		$slug_url = $request->slug_url;
		
		if(empty($users) || empty($title) || empty($message)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id, title dan message tidak boleh kosong!'
                ]
            ];
            return response()->json($data);
		}
		
		$users = explode(",",str_replace(" ","",$users));
		
		return $this->sendPushNotification($users, $title, $message, $imageUrl, $slug_url);
		
		/*
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
		*/
	}	
	
	// send all and spesifik user
	public function sendPushnotify(array $externalUserIds, string $title, string $message, $imageUrl='', $slug_url='')
    {
		$posting_data = [
			'app_id' => $this->appId,
			//'include_external_user_ids' => $externalUserIds,
			'android_channel_id' => '9a73d143-5220-44a4-b585-cc5fd949edfd',
			'ios_interruption_level' => 'critical',
			'small_icon' => "@mipmap/icon_tops",
			'headings' => [
				'en' => $title
			],
			'contents' => [
				'en' => $message
			],
			// Optional: tambahan data
			'data' => [
				'custom_url' => $slug_url,
				//'order_id' => 1234
			]
		];


		// jika ada gambar yang di upload ***
		if($imageUrl){
			$posting_image = [
				'big_picture' => $imageUrl, // ✅ Untuk Android
				'ios_attachments' => [
					'id' => $imageUrl // ✅ Untuk iOS
				],
			];
			$posting_data = array_merge($posting_data,$posting_image); // gabungkan array data...
		} 
		
		// jika kirim kesemua user...
		if (!empty($externalUserIds)) {
			//array_merge($posting_data,['include_subscription_ids'] => $externalUserIds); // gabungkan array data...
			$posting_data = array_merge($posting_data,['include_external_user_ids' => $externalUserIds]); // gabungkan array data...
        } else {
			$posting_data = array_merge($posting_data,['included_segments' => array('All')]); // gabungkan array data...
        }
		//return json_encode($posting_data);
		
		// sending data to onesignal ***
		$response = Http::withHeaders([
			'Authorization' => 'Basic ' . $this->apiKey,
			'Content-Type'  => 'application/json',
		])->post($this->url_onesignal, $posting_data);
		
		/*
		return $response->successful()
			? 'Notifikasi berhasil dikirim.'
			: 'Gagal kirim notifikasi: ' . $response->body();
		*/
		
		if($response->successful() == true){
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Notifikasi berhasil di kirim',
				]
			];
		} else {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Notifikasi gagal di kirim',
				]
			];
		}
		return response()->json($data);        
    }
	
	// send notif new all user and spesifik user
	public function sendNotifall(Request $request)
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
		
		$users = $request->cust_id;
		$title = $request->title;
		$message = $request->message;
		$imageUrl = $request->image_url;
		$slug_url = $request->slug_url;
		
		if(empty($users) || empty($title) || empty($message)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id, title dan message tidak boleh kosong!'
                ]
            ];
            return response()->json($data);
		}
		
		if(strtolower($users)=='all'){
			$users = [];
		} else {
			$users = explode(",",str_replace(" ","",$users));
		}
		
		return $this->sendPushnotify($users, $title, $message, $imageUrl, $slug_url);
	}
}
