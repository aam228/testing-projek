<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class StravaController extends Controller
{
	public function __construct()
    {
        $this->strava_client_id = config('services.strava.strava_client_id');
        $this->strava_client_secret = config('services.strava.strava_client_secret');
		$this->strava_verify_token = config('services.strava.strava_verify_token');
		//$this->url_strava = 'https://onesignal.com/api/v1/notifications';
		$this->url_strava = 'https://www.strava.com/api/v3/push_subscriptions';
    }
	
	// save konek ke strava -> per user ***
	public function userConnect(Request $request)
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

		$token_id = $request->token_id;
		$token_refresh = $request->token_refresh;
		$owner_id = $request->owner_id;
		//$object_id = $request->object_id;
		$cust_id = $request->cust_id;
		$su_token_expired = $request->token_expired;
		
		if(!$cust_id || !$token_id || !$token_refresh || !$owner_id){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id, token_id, token_refresh, dan owner_id tidak boleh kosong!'
                ]
            ];
            return response()->json($data);
		}
		
		$customer = DB::table("customers")->where("id","=",$cust_id)->first();
		if(!$customer){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data user tidak ditemukan!'
                ]
            ];
            return response()->json($data);
		}
		
		// cek sudah ada disave data koneksi strava user?
		$strava_user = DB::table("strava_users")->where("cust_id","=",$cust_id)->first();
		// save data
		if(!$strava_user){
			$data_save = [
				'cust_id' => $cust_id,
				'su_token_id' => $token_id,
				'su_token_refresh' => $token_refresh,
				'su_owner_id' => $owner_id,
				//'su_object_id' => $object_id,
				'su_token_expired' => $su_token_expired,
				'created_at' => date("Y-m-d H:i:s"),
				'updated_at' => date("Y-m-d H:i:s"),
			];
			DB::table("strava_users")->insertGetId(array_merge($data_save));
		}
		// sudah ada data -> update
		else {
			$data_save = [
				'cust_id' => $cust_id,
				'su_token_id' => $token_id,
				'su_token_refresh' => $token_refresh,
				'su_owner_id' => $owner_id,
				//'su_object_id' => $object_id,
				'su_token_expired' => $su_token_expired,
				'updated_at' => date("Y-m-d H:i:s"),
			];
			DB::table("strava_users")->where('id', $strava_user->id)->update($data_save);
		}
		// DB::table("customers")->where('id', $user_login->id)->update(['cust_coin'=>$cust_coin_update]);
		
		// save data profile strava *** ...
		$this->profileStravasave($cust_id);
		
		$data = [
			'status' => [
				'code' => 200,
				'message' => 'Data user strava berhasil disimpan',
			]
        ];
		
        return response()->json($data);
	}
	
	public function verify(Request $request)
    {
        if ($request->hub_mode === 'subscribe' && $request->hub_verify_token === $this->strava_verify_token) 
		{
            return response()->json([
                'hub.challenge' => $request->hub_challenge
            ]);
        }

        return response()->json([], 403);
    }

    public function receive(Request $request)
    {
        // Simpan log atau proses event dari Strava
        Log::info('Strava Webhook Event:', $request->all());

        // Kamu bisa tambahkan handler khusus berdasarkan event
        // Misalnya: jika ada aktivitas baru, ambil detailnya via API

        return response()->json(['status' => 'received'], 200);
    }
	
	public function connectwebhookStrava(Request $request)
	{
		$response = Http::asForm()->post($this->url_strava, [
			'client_id' => $this->strava_client_id,
			'client_secret' => $this->strava_client_secret,
			'callback_url' => 'https://topscore.id/api/webhook/strava',
			'verify_token' => $this->strava_verify_token,
		]);
		return($response->json());
	}
	
	/*
	public function getlogActivity(Request $request)
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
		$activityId = $request->object_id;
		$owner_id = $request->owner_id;
		$user_access_token = $request->token_user;
		
		if(!$cust_id || !$activityId || !$owner_id || !$user_access_token){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id, object_id, owner_id dan token_user tidak boleh kosong!'
                ]
            ];
            return response()->json($data);
		}
		
		// Token user strava: 21167c68c80955656fbe04d9d347f53e1383cdfa
		// Refresh Token user strava: d3df8069a5d8e65599a6f05f0d0cd4fc10ed0212
		$response = '';
		if ($activityId) {	
			// Dapatkan access token user. Di sini kamu harus sesuaikan berdasarkan sistem loginmu.
			/*
			$user = User::where('strava_id', $request->owner_id)->first();
	
			if (!$user || !$user->strava_access_token) {
				Log::warning("User or token not found for Strava ID {$request->owner_id}");
				return response()->json(['error' => 'Unauthorized'], 403);
			}
			*/
	/*
			// Ambil detail aktivitas dari Strava API
			$response = Http::withToken($user_access_token)
				->get("https://www.strava.com/api/v3/activities/{$activityId}");
	
			if ($response->successful()) {
				$data = $response->json();
				
				/*
				// Simpan ke database
				Activity::updateOrCreate(
					['strava_activity_id' => $activityId],
					[
						'user_id' => $user->id,
						'name' => $data['name'],
						'distance' => $data['distance'],
						'moving_time' => $data['moving_time'],
						'start_date' => $data['start_date'],
						'raw_data' => json_encode($data),
					]
				);
				*//*
			}
		}
	
		//return response()->json(['status' => 'received'], 200);
		//return $response;
		return json_decode($response);
	}
	*/
	
	public function getlogActivity(Request $request)
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

		//$cust_id = $request->cust_id;
		//$activityId = $request->object_id;
		//$user_access_token = $request->token_user;
		$owner_id = $request->owner_id;
		if(!$owner_id){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field owner_id tidak boleh kosong!'
                ]
            ];
            return response()->json($data);
		}
		
		/*
		$cust_id = $request->cust_id;
		$activityId = $request->object_id;
		$owner_id = $request->owner_id;
		$user_access_token = $request->token_user;
		*/
		$activityId = $request->object_id;
		//$customer_strava = DB::table("strava_users")->where("su_owner_id","=",$owner_id)->first();
		$customer_strava = DB::table("strava_users")->where("su_owner_id","=",$owner_id)->orderBy("updated_at","DESC")->first();
		if(!$customer_strava){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data owner ID tidak ditemukan!'
                ]
            ];
            return response()->json($data);
		}
		$customer = DB::table("customers")->where("id","=",$customer_strava->cust_id)->first();

		//$cust_id = $customer->id;
		$user_access_token = $customer_strava->su_token_id;
        
        // cek apakah sudah expired tokennya atau belum???
		$tanggal_expired = date("Y-m-d H:i:s", $customer_strava->su_token_expired);
		if (date("Y-m-d H:i:s") > $tanggal_expired) {
            $response = Http::asForm()->post('https://www.strava.com/oauth/token', [
                'client_id' => $this->strava_client_id,
                'client_secret' => $this->strava_client_secret,
                'grant_type' => 'refresh_token',
                'refresh_token' => $customer_strava->su_token_refresh,
            ]);
			
            if($response->successful()){
                $data = $response->json();
				$access_token = $data['access_token'];
                $refresh_token = $data['refresh_token'];
                //$expires_at = Carbon::createFromTimestamp($data['expires_at']);
				$expires_at = $data['expires_at'];
				// ************************************************ //
				$user_access_token = $data['access_token'];
				
				// update log strava connect ***
				$data_save = [
					'su_token_id' => $data['access_token'],
					'su_token_refresh' => $data['refresh_token'],
					'su_token_expired' => $data['expires_at'],
					'updated_at' => date("Y-m-d H:i:s"),
				];
				DB::table("strava_users")->where('id', $customer_strava->id)->update($data_save);
            }
        }
        
		$response = '';
		if ($activityId) {
			// Ambil detail aktivitas dari Strava API
			$response = Http::withToken($user_access_token)
				->get("https://www.strava.com/api/v3/activities/{$activityId}");
	
			if ($response->successful()) {
				$data = $response->json();
				
				/*
				// Simpan ke database
				Activity::updateOrCreate(
					['strava_activity_id' => $activityId],
					[
						'user_id' => $user->id,
						'name' => $data['name'],
						'distance' => $data['distance'],
						'moving_time' => $data['moving_time'],
						'start_date' => $data['start_date'],
						'raw_data' => json_encode($data),
					]
				);
				*/
			}
		}	
		return json_decode($response);
	}
	
	
	// get list all activities
	public function listActivities(Request $request)
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
		$ym = $request->ym; // pencarian tahun-bulan
		if(!$cust_id){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id tidak boleh kosong!'
                ]
            ];
            return response()->json($data);
		}
		// cek customer data
        $user = DB::table("customers")->where("id","=",$cust_id)->first();
		if(!$user){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data user tidak ditemukan!'
                ]
            ];
            return response()->json($data);
		}
		// cek user konek strava ...
		$user_strava = DB::table("strava_users")->where("cust_id","=",$cust_id)->first();
		if(!$user_strava){
            $data = [
                'status' => [
                    'code' => 403,
                    'description' => 'User belum connect ke Strava!'
                ]
            ];
            return response()->json($data);
		}
		
		// jika ambil dari database (offkan fungsi ini jika mau ambil langsung ke strava...) ***********
		if($request->flag_db == 1 || (!$request->flag_db && $request->flag_db!='0') ){
			$log_aktifitas = $this->userlistActivities($cust_id, $ym);
			return $log_aktifitas;
		}
		// end baca data dari log aktifitas runner (dari database) *************************************
		
        /*
		if (!$user->strava_access_token) {
            //return response()->json(['error' => 'User belum connect ke Strava'], 403);
            $data = [
                'status' => [
                    'code' => 403,
                    'description' => 'User belum connect ke Strava!'
                ]
            ];
            return response()->json($data);
        }
		*/
		
		$tanggal_expired = date("Y-m-d H:i:s", $user_strava->su_token_expired);
		//echo $tanggal_expired; exit();
        // Refresh token jika kadaluarsa
        //if (Carbon::now()->gte($user_strava->su_token_expired)) {
		if (date("Y-m-d H:i:s") > $tanggal_expired) {
            $response = Http::asForm()->post('https://www.strava.com/oauth/token', [
                'client_id' => $this->strava_client_id,
                'client_secret' => $this->strava_client_secret,
                'grant_type' => 'refresh_token',
                'refresh_token' => $user_strava->su_token_refresh,
            ]);
			
            if($response->successful()){
                $data = $response->json();
                $user_strava->su_token_id = $data['access_token'];
                $user_strava->su_token_refresh = $data['refresh_token'];
                //$user_strava->su_token_expired = Carbon::createFromTimestamp($data['expires_at']);
				$user_strava->su_token_expired = $data['expires_at'];
                //$user_strava->save();
				// update log strava connect
				$data_save = [
					'su_token_id' => $data['access_token'],
					'su_token_refresh' => $data['refresh_token'],
					'su_token_expired' => $data['expires_at'],
					'updated_at' => date("Y-m-d H:i:s"),
				];
				DB::table("strava_users")->where('id', $user_strava->id)->update($data_save);
            }
        }

        // Ambil aktivitas (limit default: 30)
        $response = Http::withToken($user_strava->su_token_id)
            ->get('https://www.strava.com/api/v3/athlete/activities', [
                'per_page' => 50,
                'page' => 1,
            ]);

        if (!$response->successful()) {
            //return response()->json(['error' => 'Gagal ambil data aktivitas dari Strava'], 500);
            $data = [
				'status' => [
					'code' => 400,
					'description' => 'Gagal ambil data aktivitas dari Strava',
				],
			];
			return response()->json($data);
        }

        $activities = $response->json();
		$data_json = array();
		foreach($activities as $respon_data){
			if($respon_data['type']=="Run"){
				// hitung pase
				$distanceKm = $respon_data['distance'] / 1000; // meter ke km
				$timeInSeconds = $respon_data['moving_time'];
				
				if ($distanceKm > 0) {
					$paceSecondsPerKm = $timeInSeconds / $distanceKm;
				
					$paceMinutes = floor($paceSecondsPerKm / 60);
					$paceSeconds = round($paceSecondsPerKm % 60);
				
					$pace = sprintf('%d:%02d/km', $paceMinutes, $paceSeconds);
				} else {
					$pace = '-';
				}
				
				
				//echo $respon_data['name'].' == '.$respon_data['type'].'<br>';
				//$data_json[] = [
				//	"distance" => 5000, // meter
				//	"moving_time" => 1500, // detik (25 menit)
				//	"elapsed_time" => 1550, // detik
				//	"type" => "Run",
				//	"start_date" =>  "2024-06-24T06:00:00Z",
				//	"name" => "Morning Run",
				//];
				
				// hitung detik aktifitas
				$hours = floor($respon_data['moving_time'] / 3600);
				$minutes = floor(($respon_data['moving_time'] % 3600) / 60);
				$seconds = $respon_data['moving_time'] % 60;
				$time = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
				$data_json[] = [
					"name" => $respon_data['name'],
					"distance" => round($distanceKm,2), // KM
					"pace" => $pace,
					"type" => $respon_data['type'],
					"start_date" =>  date("Y-m-d H:i:s",strtotime($respon_data['start_date'])),
					"time" => $time, // Jam
					
				];
			}
		}
		//return response()->json($data_json);
		//exit();
		
        // Kalau mau simpan ke database, kamu bisa lakukan di sini
        //return response()->json($activities);
        $data = [
			'status' => [
				'code' => 200,
				'description' => 'Get data berhasil',
				'log_activities' => $data_json,
			],
		];
		return response()->json($data);
    }
    
    // ambil dari database log aktifitas *** 
	private function userlistActivities($cust_id, $ym='')
    {
		/*
        $activities = DB::table("running_join_activities")
			->select('running_join_activities.id', 'rja_name', 'rja_distance', 'rja_moving_time', 'rja_start_date', 'rja_object_id', 'rja_subscription_id', 'running_join_activities.created_at')
			->leftJoin('running_joins', 'running_joins.id', '=', 'running_join_activities.runjoin_id')
			->leftJoin('runnings', 'runnings.id', '=', 'running_joins.run_id')
			->where("cust_id","=",$cust_id)
			->where("run_id","=",$run_id)
			//->where("run_start","<=",date("Y-m-d"))
			//->where("run_end",">=",date("Y-m-d"))
			->where("run_active","=","1")
			->where("run_delete","=","0")
			->orderBy("running_join_activities.id","DESC")
			->get();
		*/
		$activities = DB::table("running_join_activities")
			->select('running_join_activities.id', 'rja_name', 'rja_distance', 'rja_moving_time', 'rja_start_date', 'rja_object_id', 'rja_subscription_id', 'running_join_activities.created_at', 'rja_flagged', 'rja_uniqid')
			->where("cust_id","=",$cust_id)
			//->where(["rja_distance"=>"0", "rja_moving_time"=>"0"])
			->where("rja_distance", ">", "0")
			->where("rja_moving_time", ">", "0")
			->where(function($w) use($ym) {
				if ($ym) {
					$w->whereRaw("LEFT(ts_running_join_activities.rja_start_date,7)='".$ym."'");
				}
					
			})
			->groupBy("rja_uniqid")
			//->orderBy("running_join_activities.id","DESC")
			->orderBy("running_join_activities.rja_start_date","DESC")
			->get();
		$data_json = array();
		foreach($activities as $raw){
			if(1==1){
				// hitung pase
				$distanceKm = $raw->rja_distance / 1000; // meter ke km
				$timeInSeconds = $raw->rja_moving_time;
				
				if ($distanceKm > 0) {
					$paceSecondsPerKm = $timeInSeconds / $distanceKm;
				
					$paceMinutes = floor($paceSecondsPerKm / 60);
					$paceSeconds = round($paceSecondsPerKm % 60);
				
					$pace = sprintf('%d:%02d/km', $paceMinutes, $paceSeconds);
				} else {
					$pace = '-';
				}
				// hitung detik aktifitas
				$hours = floor($raw->rja_moving_time / 3600);
				$minutes = floor(($raw->rja_moving_time % 3600) / 60);
				$seconds = $raw->rja_moving_time % 60;
				$time = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
				
				// tampilkan list slit metric -> detail log pace *** 
				$log_metric = DB::table("running_join_activity_details")
				->select('id', 'rjad_distance', 'rjad_elapsed_time', 'rjad_elevation_difference', 'rjad_moving_time', 'rjad_split', 'rjad_average_speed', 'rjad_average_grade_adjusted_speed', 'rjad_pace_zone', 'running_join_activity_details.created_at', 'rjad_average_heartrate')
				->where(["cust_id" => $cust_id, "rja_uniqid" => $raw->rja_uniqid])
				->orderBy("id","ASC")
				->get();
				$split_metric = array();
				if(count($log_metric)>0){
					foreach($log_metric as $metric){
					    $distanceKm2 = $metric->rjad_distance / 1000; // meter ke km
						$timeInSeconds2 = $metric->rjad_moving_time;
						
						if ($distanceKm2 > 0) {
							$paceSecondsPerKm2 = $timeInSeconds2 / $distanceKm2;
						
							$paceMinutes2 = floor($paceSecondsPerKm2 / 60);
							$paceSeconds2 = round($paceSecondsPerKm2 % 60);
						
							//$pace_detail = sprintf('%d:%02d/km', $paceMinutes, $paceSeconds);
							$pace_detail = sprintf('%d:%02d', $paceMinutes2, $paceSeconds2);
						} else {
							$pace_detail = '-';
						}
					    /*
					    $distanceKm = $metric->rjad_distance / 1000; // meter ke km
						$timeInSeconds = $metric->rjad_moving_time;
						
						if ($distanceKm > 0) {
							$paceSecondsPerKm = $timeInSeconds / $distanceKm;
						
							$paceMinutes = floor($paceSecondsPerKm / 60);
							$paceSeconds = round($paceSecondsPerKm % 60);
						
							//$pace_detail = sprintf('%d:%02d/km', $paceMinutes, $paceSeconds);
							$pace_detail = sprintf('%d:%02d', $paceMinutes, $paceSeconds);
						} else {
							$pace_detail = '-';
						}
						// hitung detik aktifitas
						$hours = floor($metric->rjad_moving_time / 3600);
						$minutes = floor(($metric->rjad_moving_time % 3600) / 60);
						$seconds = $metric->rjad_moving_time % 60;
						$time_detail = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
					    */
					    
					    if ($distanceKm2 > 0) {
						$split_metric[]= [
							"distance" => (float)$metric->rjad_distance,
							"elapsed_time" => (float)$metric->rjad_elapsed_time,
							"elevation_difference" => (float)$metric->rjad_elevation_difference,
							"moving_time" => (float)$metric->rjad_moving_time,
							"split" => (float)$metric->rjad_split,
							"average_speed" => (float)$metric->rjad_average_speed,
							"average_grade_adjusted_speed" => (float)$metric->rjad_average_grade_adjusted_speed,
							"average_heartrate" => (float)$metric->rjad_average_heartrate,
							"pace_zone" => (float)$metric->rjad_pace_zone,
							"pace" => $pace_detail,
							//"time" => $time_detail, // Jam
						];
					    }
					}
				}
				
				$data_json[] = [
				    "cust_id" => (int)$cust_id,
					"uniqid" => $raw->rja_uniqid,
					"name" => $raw->rja_name,
					"distance" => round($distanceKm,2), // KM
					"pace" => $pace,
					"type" => 'Run',
					"start_date" =>  date("Y-m-d H:i:s",strtotime($raw->rja_start_date)),
					"time" => $time, // Jam
					"flagged" => $raw->rja_flagged, // jika 1 maka tidak valid, jika 0=valid
					"splits_metric" => $split_metric,
				];
			}
		}

        $data = [
			'status' => [
				'code' => 200,
				'description' => 'Get data berhasil',
				'log_activities' => $data_json,
			],
		];
		return response()->json($data);		
    }
    
    // list master challenge
	public function listChallenge(Request $request)
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
		if(!$cust_id){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id tidak boleh kosong!'
                ]
            ];
            return response()->json($data);
		}
		// cek customer data
        $user = DB::table("customers")->where("id","=",$cust_id)->first();
		if(!$user){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data user tidak ditemukan!'
                ]
            ];
            return response()->json($data);
		}
		
		$challenge = DB::table("runnings")
			->where("run_start","<=",date("Y-m-d"))
			->where("run_end",">=",date("Y-m-d"))
			->where(["run_active" => "1", "run_delete" => "0"])
			->orderBy('run_winner_sponsorhip',"DESC")
			->orderBy("run_type","DESC")
			->orderBy("run_distance","ASC")
			->get();
		$data_challenge = array();
		foreach($challenge as $raw){
			// cek join customer...
			//$user_join = DB::table("running_joins")->where(["cust_id"=>$cust_id, "run_id"=>$raw->id, "runjoin_active"=>"1"])->get();
			//if(count($user_join)>0){$status_join=1;} else {$status_join=0;}
			$user_join = DB::table("running_joins")->where(["cust_id"=>$cust_id, "run_id"=>$raw->id, "runjoin_active"=>"1"])->first();
			if($user_join){
				$status_join=1;
				$run_achieve = $user_join->run_achieve;
				$run_claim = $user_join->run_claim;
			} else {
				$status_join=0;
				$run_achieve = 0;
				$run_claim = 0;
			}
			
			$challenge_sponsor = DB::table("running_sponsorships")->where(["run_id"=>$raw->id, "sponsor_delete"=>"0"])->get();
			$array_sponsor = null;
			foreach($challenge_sponsor as $raw_sponsor){
				$array_sponsor['sponsor_id'] = $raw_sponsor->id;
				$array_sponsor['sponsor_name'] = $raw_sponsor->sponsor_name;
				$array_sponsor['sponsor_icon'] = 'https://topsystem.id/upload/event/'.$raw_sponsor->sponsor_icon;
				$array_sponsor['sponsor_banner'] = 'https://topsystem.id/upload/event/'.$raw_sponsor->sponsor_banner;
				$array_sponsor['sponsor_description'] = $raw_sponsor->sponsor_description;
			}
			
			// cek jika ada di event, tidak di tampilkan di list
			$cek_challenge_event = DB::table("running_events")->where(["run_id"=>$raw->id, "runevent_delete"=>"0"])->first();
			if(!$cek_challenge_event){
    			$data_challenge[]=[
    			    'run_id' => $raw->id,
    				'run_type' => $raw->run_type,
    				'run_name' => $raw->run_name,
    				'run_image' => 'https://topsystem.id/upload/'.$raw->run_image,
    				'run_description' => $raw->run_description,
    				'run_start' => $raw->run_start,
    				'run_end' => $raw->run_end,
    				'run_distance' => $raw->run_distance,
    				'run_coin' => $raw->run_coin,
    				'run_score' => $raw->run_score,
    				'run_customer_join' => $status_join, // 0 dan 1
    				'run_achieve' => $run_achieve,
    				'run_claim' => $run_claim,
    				'sponsorhip' => $array_sponsor,
    			];
			}
		}
		
		$challenge_winner = NULL;
		
		//$challenge_winner = 'https://topsystem.id/upload/event/banner_challenge_1.png';
		//$challenge_winner = "https://topsystem.id/upload/challenge/win_challenge_okt_2025.webp";
		//$challenge_winner = "";
		$challenge_winner = "https://topsystem.id/upload/challenge/win_challenge_jan_2026.webp";
		
        $data = [
			'status' => [
				'code' => 200,
				'description' => 'Success',
				'challenge_winner' => $challenge_winner,
				'ongoing_challenge' => $this->ongoingChallenge($cust_id),
				'event_challenge' => $this->eventChallenge($cust_id),
				'challenge' => $data_challenge,
			],
		];
		return response()->json($data);		
    }
    
    // ongoing challenge ...
	private function ongoingChallenge($cust_id)
    {
		// cek customer data
        $user = DB::table("customers")->where("id","=",$cust_id)->first();
		
		$challenge = DB::table("running_joins")
			->select('running_joins.id', 'run_id', 'run_name', 'run_image', 'run_start', 'run_end', 'run_type', 'run_description', 'run_distance', 'run_coin', 'run_score', 'run_achieve', 'run_claim')
			->leftJoin('runnings', 'runnings.id', '=', 'running_joins.run_id')
			->where("run_start","<=",date("Y-m-d"))
			->where("run_end",">=",date("Y-m-d"))
			->where(["run_active" => "1", "run_delete" => "0", "cust_id" => $cust_id, 'run_claim'=>"0"])
			//->orderBy("run_type","DESC")
			//->orderBy("run_distance","ASC")
			->orderBy("run_achieve","DESC")
			->orderBy("run_claim","ASC")
			->get();
		$data_challenge = array();
		foreach($challenge as $raw){
			// cek log aktifitas running
			$log_activity = DB::table("running_join_activities")
					->select('running_join_activities.id', 'rja_distance', 'rja_moving_time', 'rja_start_date', 'rja_object_id', 'rja_subscription_id', 'running_join_activities.created_at')
					->leftJoin('running_joins', 'running_joins.id', '=', 'running_join_activities.runjoin_id')
					->where(["running_join_activities.cust_id"=>$cust_id, "run_id"=>$raw->run_id, 'rja_flagged'=>'0'])
					->get();
			$run_progress=0;
			$distanceKm = 0;
			if($log_activity){
					foreach($log_activity as $log){
						//$run_progress += $log->rja_distance;
						$distanceKm += $log->rja_distance;
					}
			}
				//$run_distance_activity = round(($distanceKm / 1000),2);
				$run_distance_activity = round(number_format(($distanceKm / 1000),2, '.', ''),2);
			if($distanceKm>0){
					$master_km = $raw->run_distance;
					if($run_distance_activity < $master_km){
						//$run_progress = round(($run_distance_activity / $master_km))*100;
						$run_progress = round(($run_distance_activity / $master_km)*100);
					} else { $run_progress = 100; }
			}
				
			$run_progress = $run_progress; // persen
			$run_distance_activity = $run_distance_activity; // KM
            
            if($run_distance_activity > $raw->run_distance){$run_distance_activity=$raw->run_distance;} else {$run_distance_activity=$run_distance_activity;}
            
			$data_challenge[]=[
				'run_id' => $raw->run_id,
				'run_type' => $raw->run_type,
				'run_name' => $raw->run_name,
				'run_image' => 'https://topsystem.id/upload/'.$raw->run_image,
				'run_description' => $raw->run_description,
				'run_start' => $raw->run_start,
				'run_end' => $raw->run_end,
				'run_distance' => $raw->run_distance,
				'run_coin' => $raw->run_coin,
				'run_score' => $raw->run_score,
				'run_progress' => $run_progress, // persen
				'run_distance_activity' => $run_distance_activity, // KM
				'run_achieve' => $raw->run_achieve,
				'run_claim' => $raw->run_claim,
			];
		}
		return $data_challenge;
    }
	
	// event + challenge
	private function eventChallenge($cust_id)
    {
		// cek customer data
        $user = DB::table("customers")->where("id","=",$cust_id)->first();
		
		$challenge_event = DB::table("running_events")
			->select('running_events.id', 'et_id', 'run_id', 'run_name', 'run_image', 'run_start', 'run_end', 'run_type', 'run_description', 'run_distance', 'run_coin', 'run_score', 'et_title', 'et_start_date', 'et_start_time', 'et_end_date', 'et_end_time', 'et_address', 'et_banner', 'et_price', 'et_terms', 'et_description', 'et_quota', 'et_lat', 'et_lang', 'et_type', 'et_link_online', 'et_score', 'et_maps_url', 'et_category', 'et_tournament', 'et_city')
			->leftJoin('runnings', 'runnings.id', '=', 'running_events.run_id')
			->leftJoin('event_tops', 'event_tops.id', '=', 'running_events.et_id')
			->where("run_start","<=",date("Y-m-d"))
			->where("run_end",">=",date("Y-m-d"))
			->where(["run_active" => "1", "run_delete" => "0"])
			->whereRaw("CONCAT(ts_event_tops.et_start_date,' ',ts_event_tops.et_start_time) >= '".date('Y-m-d H:i:s')."'")
			->orderBy("run_type","DESC")
			->orderBy("run_distance","ASC")
			->get();
		$data_event = array();
		foreach($challenge_event as $raw){
			// cek sudah daftar event atau belum
			$order_event = DB::table("event_orders")->where(["payment_status"=>"1", "u_id"=>$cust_id, "et_id"=>$raw->et_id])->first();
			if($order_event){
				$join_event = 1;
			} else {
				$join_event = 0;
			}
			
			// jika mencari salah 1 data event
            $event_users = DB::table("event_orders")
							   ->where("et_id","=",$raw->et_id)
							   ->where("payment_status","=","1")
							   ->sum('eo_qty');
			if($event_users>0){
				$jumlah_user = (int)$event_users;
			} else {
				$jumlah_user = 0;
			}

			if (1==1) {
				    $jumlah_user = (int)$event_users;
				    $quota = $raw->et_quota;
				    
				    // cek jika sudah full, jadikan nol
					if($quota<=$jumlah_user){$quota=0; $jumlah_user=0;}
				    
				    //$quota = $quota;
				    if($raw->et_start_date.' '.$raw->et_start_time <= date("Y-m-d H:i:s")){
						$quota = 0;
					}
			} else {
					// cek jika sudah full, jadikan nol
					if($raw->et_quota<=(int)$event_users){$quota=0; $jumlah_user=0;}
			}
			
			//$user_join = DB::table("running_joins")->where(["cust_id"=>$cust_id, "run_id"=>$raw->run_id])->first();
			//if($user_join){ $status_join=1; } else { $status_join=0; }
			$user_join = DB::table("running_joins")->where(["cust_id"=>$cust_id, "run_id"=>$raw->run_id])->first();
			if($user_join){
				$status_join=1;
				$run_achieve = $user_join->run_achieve;
				$run_claim = $user_join->run_claim;
			} else {
				$status_join=0;
				$run_achieve = 0;
				$run_claim = 0;
			}
			
			if($raw->et_banner){
				$run_name = $raw->et_title;
				$run_image = 'https://topsystem.id/upload/event/'.$raw->et_banner;
			} else {
				$run_name = $raw->run_name;
				$run_image = 'https://topsystem.id/upload/'.$raw->run_image;
			}

			$data_event[]=[
				'run_id' => $raw->run_id,
				'run_type' => $raw->run_type,
				//'run_name' => $raw->run_name,
				//'run_image' => 'https://topsystem.id/upload/'.$raw->run_image,
				'run_name' => $run_name,
				'run_image' => $run_image,
				'run_description' => $raw->run_description,
				'run_start' => $raw->run_start,
				'run_end' => $raw->run_end,
				'run_distance' => $raw->run_distance,
				'run_coin' => $raw->run_coin,
				'run_score' => $raw->run_score,
				'run_customer_join' => $status_join,
				'run_achieve' => $run_achieve,
				'run_claim' => $run_claim,
				
				'event_id' => $raw->et_id,
				'event_title' => $raw->et_title,
				'event_start_date' => $raw->et_start_date,
				'event_start_time' => $raw->et_start_time,
				'event_end_date' => $raw->et_end_date,
				'event_end_time' => $raw->et_end_time,
				'event_city' => $raw->et_city,
				'event_address' => $raw->et_address,
				'event_quota' => $quota, // tambahan
				'event_user_order' => $jumlah_user, // tambahan
				'event_banner' => 'https://topsystem.id/upload/event/'.$raw->et_banner,
				'event_join' => $join_event,
			];
		}
		return $data_event;
    }
    
    // join running ***
	public function joinChallenge(Request $request)
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
		$run_id = $request->run_id;
		if(!$cust_id || !$run_id){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id dan run_id tidak boleh kosong!'
                ]
            ];
            return response()->json($data);
		}
		
		// cek customer data
        $user = DB::table("customers")->where("id","=",$cust_id)->first();
		if(!$user){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data user tidak ditemukan!'
                ]
            ];
            return response()->json($data);
		}
		
		$master_challenge = DB::table("runnings")->where("id","=",$run_id)->first();
		if(!$master_challenge){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data challenge tidak ditemukan!'
                ]
            ];
            return response()->json($data);
		}
		
		// jika challenge sudah tidak aktif
		if($master_challenge->run_end<date("Y-m-d")){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data challenge sudah tidak aktif!'
                ]
            ];
            return response()->json($data);
		}
		
		$user_join = DB::table("running_joins")->where(["cust_id"=>$cust_id, "run_id"=>$run_id])->first();
		if($user_join){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Kamu sudah join pada challenge ini!'
                ]
            ];
            return response()->json($data);
		}
		
		//save to join challenge
		$data_save = [
				'cust_id' => $cust_id,
				'run_id' => $run_id,
				'created_at' => date("Y-m-d H:i:s"),
		];
		$save_join = DB::table("running_joins")->insertGetId(array_merge($data_save));
		if($save_join){

			// save log join, default nol
			$data_log = [
				'runjoin_id' => $save_join,
				'cust_id' => $cust_id,
				'rja_name' => 'Default',
				'rja_distance' => 0,
				'rja_moving_time' => 0,
				'rja_start_date' => date("Y-m-d H:i:s"),
				'rja_object_id' => NULL,
				'rja_subscription_id' =>NULL,
				'rja_uniqid' => mt_rand(1000000000,9999999999),
				'rja_flagged' => 0,
				'created_at' => date("Y-m-d H:i:s"),
			];
			DB::table("running_join_activities")->insertGetId(array_merge($data_log));

			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success',
				]
			];
		} else {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Gagal join challenge',
				]
			];
		}
		return response()->json($data);		
    }
    
    // detail challenge
	public function detailChallenge(Request $request)
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
		$run_id = $request->run_id;
		if(!$cust_id || !$run_id){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id dan run_id tidak boleh kosong!'
                ]
            ];
            return response()->json($data);
		}
		// cek customer data
        $user = DB::table("customers")->where("id","=",$cust_id)->first();
		if(!$user){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data user tidak ditemukan!'
                ]
            ];
            return response()->json($data);
		}
		
		$master_challenge = DB::table("runnings")->where("id","=",$run_id)->first();
		if(!$master_challenge){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data challenge tidak ditemukan!'
                ]
            ];
            return response()->json($data);
		}
		
		// jika challenge sudah tidak aktif
		/*
		if($master_challenge->run_end<date("Y-m-d")){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data challenge sudah tidak aktif!'
                ]
            ];
            return response()->json($data);
		}
		*/
		
		$user_join = DB::table("running_joins")->where(["cust_id"=>$cust_id, "run_id"=>$run_id])->first();
		$arr_event = DB::table("running_events")->where(["run_id"=>$run_id, "runevent_delete"=>'0'])->first();
		//if($arr_event){ $event_id=$arr_event->et_id; } else { $event_id=null; }
		if($arr_event){ 
			// cek sudah daftar event atau belum
			$order_event = DB::table("event_orders")->where(["payment_status"=>"1", "u_id"=>$cust_id, "et_id"=>$arr_event->et_id])->first();
			if($order_event){
				$join_event = 1;
			} else {
				$join_event = 0;
			}
			$event_id=$arr_event->et_id; 
		} else { $event_id=null; $join_event = 0; }
		
		$run_achieve = 0;
		$flag_win_sponsor=0;
		if($user_join){
			// cek log aktifitas running
			$log_activity = DB::table("running_join_activities")
				->select('running_join_activities.id', 'rja_distance', 'rja_moving_time', 'rja_start_date', 'rja_object_id', 'rja_subscription_id', 'running_join_activities.created_at')
				->leftJoin('running_joins', 'running_joins.id', '=', 'running_join_activities.runjoin_id')
				->where(["running_join_activities.cust_id"=>$cust_id, "run_id"=>$run_id, 'rja_flagged' => "0"])
				->get();
			$run_progress=0;
			$distanceKm = 0;
			if($log_activity){
				foreach($log_activity as $log){
					//$run_progress += $log->rja_distance;
					$distanceKm += $log->rja_distance;
				}
			}
			//$run_distance_activity = round(($distanceKm / 1000),2);
			$run_distance_activity = round(number_format(($distanceKm / 1000),2, '.', ''),2);
			if($distanceKm>0){
				$master_km = $master_challenge->run_distance;
				if($run_distance_activity < $master_km){
					//$run_progress = round(($run_distance_activity / $master_km))*100;
					$run_progress = round(($run_distance_activity / $master_km)*100);
				} else { $run_progress = 100; }
			}
			
			$status_join=1;
			$run_progress = $run_progress; // persen
			$run_distance_activity = $run_distance_activity; // KM
			$run_achieve = $user_join->run_achieve;
			$run_claim = $user_join->run_claim;
			
			// cek achive apa tidak
			if($user_join->run_achieve==1){
				$run_achive_at = $user_join->achieve_updated;
				$run_achieve = 1;
			} else {
				$run_achive_at = '';
			}
			//$run_position = 5;
			
			// tentukan urutan peserta running *** ???
			$log_activity = DB::table("running_join_activities")
					->selectRaw('SUM(ts_running_join_activities.rja_distance) distance, ts_running_join_activities.cust_id')
					->leftJoin('running_joins', 'running_joins.id', '=', 'running_join_activities.runjoin_id')
					->leftJoin('customers', 'customers.id', '=', 'running_join_activities.cust_id')
					->where("runjoin_active","=","1")
					->where("rja_flagged","=","0")
					->where("running_joins.run_id","=",$master_challenge->id)
					->groupBy('running_join_activities.cust_id')
					->orderBy("distance","DESC")
					//->limit('20')
					->get();
			if(count($log_activity)>0){
				$cust_id_run = array();
				foreach($log_activity as $log_run){
					$cust_id_run[]= $log_run->cust_id;
				}
				$index_search = array_search($cust_id, $cust_id_run);
				
				if ($index_search !== false) {
					$urutan = $index_search + 1; // +1 karena array dimulai dari 0
					//$total = count($nama);
					//echo "$cariNama berada di urutan ke-$urutan dari total $total data.";
					$run_position = $urutan; 
				} else { $run_position = 0;  }
				//$run_position = 5; 
			} else {
				$run_position = 0; 
			}
			
			// cek apakah sebagai pemenang hadiah sponsor brand
			if($user_join->runjoin_win==1){ $flag_win_sponsor=1; }
			$runjoin_wipe = $user_join->runjoin_wipe;
		} else {
			$status_join=0;
			$run_progress = 0; // persen
			$run_distance_activity = 0; // KM
			$run_achieve = 0;
			$run_claim = 0;
			$run_achive_at = '';
			$run_position = 0;
			$runjoin_wipe = 0;
		}
		
		$join_all = DB::table("running_joins")->where(["run_id"=>$run_id])->get();
		$challenge_sponsor = DB::table("running_sponsorships")->where(["run_id"=>$run_id, "sponsor_delete"=>"0"])->get();
		if(count($challenge_sponsor)>0){$sponsorship=1;} else {$sponsorship=0;}
		
		$array_sponsor = null;
		foreach($challenge_sponsor as $raw_sponsor){
			$array_sponsor['sponsor_id'] = $raw_sponsor->id;
			$array_sponsor['sponsor_name'] = $raw_sponsor->sponsor_name;
			$array_sponsor['sponsor_icon'] = 'https://topsystem.id/upload/event/'.$raw_sponsor->sponsor_icon;
			$array_sponsor['sponsor_banner'] = 'https://topsystem.id/upload/event/'.$raw_sponsor->sponsor_banner;
			$array_sponsor['sponsor_description'] = $raw_sponsor->sponsor_description;
		}
		// total pemenang challenge undian hadiah
		$all_win = DB::table("running_joins")->where(["run_id"=>$run_id, "runjoin_win"=>"1"])->get();
		
		if($run_distance_activity > $master_challenge->run_distance){$run_distance_activity=$master_challenge->run_distance;} else {$run_distance_activity=$run_distance_activity;}
		
		// cek apakah challenge link ke data event?
		$cek_join_event = DB::table("running_events")
		->select('running_events.id', 'et_start_date', 'et_start_time', 'et_end_date', 'et_end_time', 'et_banner', 'et_title')
		->leftJoin('event_tops', 'event_tops.id', '=', 'running_events.et_id')
		->where("run_id","=",$master_challenge->id) // cek by challenge ***
		->where("runevent_delete","=","0")
		->where("et_delete","=","0")
		->first();
		if($cek_join_event){
			$run_name = $cek_join_event->et_title;
			$run_image = 'https://topsystem.id/upload/event/'.$cek_join_event->et_banner;
		} else {
			$run_name = $master_challenge->run_name;
			$run_image = 'https://topsystem.id/upload/'.$master_challenge->run_image;
		}
		
		if($run_achieve==1){
		    $all_run = count($all_win);
		} else {
		    $all_run = 0;
		}
		
		$data_challenge =[
				'run_id' => $master_challenge->id,
				'run_type' => $master_challenge->run_type,
				//'run_name' => $master_challenge->run_name,
				//'run_image' => 'https://topsystem.id/upload/'.$master_challenge->run_image,
				'run_name' => $run_name,
				'run_image' => $run_image,
				'run_description' => $master_challenge->run_description,
				'run_start' => $master_challenge->run_start,
				'run_end' => $master_challenge->run_end,
				'run_distance' => $master_challenge->run_distance,
				'run_coin' => $master_challenge->run_coin,
				'run_score' => $master_challenge->run_score,
				'run_notes' => $master_challenge->run_notes,
				'run_customer_join' => $status_join,
				'run_customer_all' => count($join_all),
				'run_progress' => $run_progress, // persen
				'run_distance_activity' => $run_distance_activity, // KM
				'run_achieve' => $run_achieve,
				'run_claim' => $run_claim,
				'run_achive' => $run_achieve, // 1 sudah tercapai, 0=belum tercapai
				'run_achive_at' => $run_achive_at,
				'run_position' => $run_position,
				'runjoin_sponsorhip' => $sponsorship,
				'runjoin_reward_sponsor' => $flag_win_sponsor,
				'sponsorhip' => $array_sponsor,
				//'runjoin_allwinner' => (int)count($all_win),
				'runjoin_allwinner' => (int)$master_challenge->run_winner_sponsorhip,
				'runflag_wipe' => $runjoin_wipe,
				//'runflag_winner' => (int)count($all_win),
				'runflag_winner' => (int)$all_run,
				'event_id' => $event_id,
				'event_join' => $join_event,
		];

        $data = [
			'status' => [
				'code' => 200,
				'description' => 'Success',
				'challenge' => $data_challenge,
			],
		];
		return response()->json($data);		
    }
    
    // leaderboards all...
	public function leaderboardAll(Request $request)
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
		if(!$cust_id){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id tidak boleh kosong!'
                ]
            ];
            return response()->json($data);
		}
		
		// cek customer data
        $user = DB::table("customers")->where("id","=",$cust_id)->first();
		if(!$user){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data user tidak ditemukan!'
                ]
            ];
            return response()->json($data);
		}
		

		/*
		$log_activity = DB::table("running_join_activities")
				->selectRaw('ts_running_join_activities.id, cust_name, cust_phone, SUM(ts_running_join_activities.rja_distance) distance, cust_gender, cust_avatar, cust_id')
				->leftJoin('customers', 'customers.id', '=', 'running_join_activities.cust_id')
				->groupBy('cust_id')
				->orderBy("distance","DESC")
				->limit('20')
				->get();
        */
        
        // baca banner dan keterangan
		$banner_lb = DB::table("running_leaderboards")
			->select('id', 'runlead_name', 'runlead_description', 'runlead_star', 'runlead_end', 'runlead_banner')
			->where("runlead_star","<=",date("Y-m-d"))
			->where("runlead_end",">=",date("Y-m-d"))
			->where("runlead_delete","=","0")
			->first();
		$leaderboard = NULL;
		$tahun_bulan = date("Y-m");
		// $run_image = 'https://topsystem.id/upload/'.$master_challenge->run_image;
		$leaderboard_banner = null;
		if(!$banner_lb){
			$banner_lb = DB::table("running_leaderboards")
				->select('id', 'runlead_name', 'runlead_description', 'runlead_star', 'runlead_end', 'runlead_banner')
				->where("runlead_delete","=","0")
				->orderBy("runlead_star","DESC")
				->first();
		}
		if($banner_lb){
			$leaderboard_banner = [
				'lb_name' => $banner_lb->runlead_name,
				'lb_description' => $banner_lb->runlead_description,
				'lb_banner' => 'https://topsystem.id/upload/event/'.$banner_lb->runlead_banner,
			];
			$tahun_bulan = substr($banner_lb->runlead_star,0,7);
		}
        
        $yd = $request->yd;
        if($yd){ $tahun_bulan = $yd; } else { $tahun_bulan = $tahun_bulan; }
        
        $log_activity = DB::table("view_leaderboard_runnings")
				->select('rja_uniqid', 'cust_name', 'cust_phone', 'cust_gender', 'cust_avatar', 'cust_id', 'distance_real as distance')
				->where("distance_real",">","0")
				->whereRaw("LEFT(ts_view_leaderboard_runnings.created_at,7)='".$tahun_bulan."'")
				->whereNotIn("cust_phone", ['085641061673','082168329023','085342790746', // september
				                            '082244414186', '083856698871', '087835666399', // oktober
				                            '083838947770','08386096468','082390898231' // november
				                            ]
				)
				//->orderBy("distance","DESC")
				->limit('100')
				->get();
		//var_dump($log_activity);
		//exit();
		$data_leaderboard = array();
		$no=0;
		$cutomer_id = array();
		foreach($log_activity as $row){
			$no++;
			$cutomer_id[]=$row->cust_id;
			
			if(empty($row->cust_avatar)){
				if($row->cust_gender == 'm'){
					//$avatar_profile = "https://avatar.iran.liara.run/public/boy?username=scott";
					$avatar_profile = "https://www.topsystem.id/upload/avatar/AV1.png";
				} else {
					//$avatar_profile = "https://avatar.iran.liara.run/public/girl?username=maria";
					$avatar_profile = "https://www.topsystem.id/upload/avatar/AV51.png";
				}
			} else {
				$avatar_profile = $row->cust_avatar;
			}
			
			// cek strava profil
			$user_strava = DB::table("strava_users")->where("cust_id","=",$row->cust_id)->orderBy("updated_at","DESC")->first();
			if($user_strava->su_profile_medium){ 
			    if($user_strava->su_profile_medium == "avatar/athlete/medium.png"){ $avatar_profile=$avatar_profile; } else {
					$avatar_profile = $user_strava->su_profile_medium; 
				}
			    //$avatar_profile = $user_strava->su_profile; 
			} else {$avatar_profile=$avatar_profile;}
            
			$data_leaderboard[]= [
				'cust_id' => $row->cust_id,
				'cust_name' => $row->cust_name,
				'cust_phone' => $row->cust_phone,
				'cust_image' => $avatar_profile,
				'distance' => round(($row->distance/1000),2),
			];
		}
		
		usort($data_leaderboard, function($a, $b) {
			return $b['distance'] <=> $a['distance'];
		});
		
		// cek jika tidak ada list dalam 20 user teratas? **********
		$data_leaderboard_user = NULL;
		if(!in_array($cust_id,$cutomer_id)){
			/*
			$arr_activity_user = DB::table("running_join_activities")
				->selectRaw('ts_running_join_activities.id, cust_name, cust_phone, SUM(ts_running_join_activities.rja_distance) distance, cust_gender, cust_avatar, cust_id')
				->leftJoin('customers', 'customers.id', '=', 'running_join_activities.cust_id')
				->where("cust_id","=",$cust_id)
				->groupBy('cust_id')
				->orderBy("distance","DESC")
				->first();
			if($arr_activity_user){
				if(empty($row->cust_avatar)){
					if($arr_activity_user->cust_gender == 'm'){
						//$avatar_profile = "https://avatar.iran.liara.run/public/boy?username=scott";
						$avatar_profile = "https://www.topsystem.id/upload/avatar/AV1.png";
					} else {
						//$avatar_profile = "https://avatar.iran.liara.run/public/girl?username=maria";
						$avatar_profile = "https://www.topsystem.id/upload/avatar/AV51.png";
					}
				} else {
					$avatar_profile = $arr_activity_user->cust_avatar;
				}
	
				$data_leaderboard_user = [
					'cust_name' => $arr_activity_user->cust_name,
					'cust_phone' => $arr_activity_user->cust_phone,
					'cust_image' => $avatar_profile,
					//'distance' => $arr_activity_user->distance,
					'distance' => round(($arr_activity_user->distance/1000),2),
				];
			}
			*/
			$q_activity_user = DB::table("running_join_activities")
				->selectRaw('ts_running_join_activities.id, cust_name, cust_phone, rja_distance as distance, cust_gender, cust_avatar, cust_id')
				->leftJoin('customers', 'customers.id', '=', 'running_join_activities.cust_id')
				->where("cust_id","=",$cust_id)
				->where("rja_flagged","=",'0')
				->whereRaw("LEFT(ts_running_join_activities.created_at,7)='".$tahun_bulan."'")
				->groupBy('cust_id')
				->groupBy('rja_uniqid') // ID Unik user***
				->orderBy("distance","DESC")
				->get();
			$distance_personal = 0;
			foreach($q_activity_user as $rsub){
				$distance_personal += $rsub->distance;
			}
			
			if(count($q_activity_user)>0){
				if(empty($row->cust_avatar)){
					if($user->cust_gender == 'm'){
						$avatar_profile = "https://www.topsystem.id/upload/avatar/AV1.png";
					} else {
						$avatar_profile = "https://www.topsystem.id/upload/avatar/AV51.png";
					}
				} else {
					$avatar_profile = $user->cust_avatar;
				}
	            
	            // cek strava profil
				$user_strava = DB::table("strava_users")->where("cust_id","=",$cust_id)->orderBy("updated_at","DESC")->first();
				if($user_strava->su_profile_medium){ 
				    //$avatar_profile = $user_strava->su_profile; 
				    if($user_strava->su_profile_medium == "avatar/athlete/medium.png"){ $avatar_profile=$avatar_profile; } else {
						$avatar_profile = $user_strava->su_profile_medium; 
					}
				} else {$avatar_profile=$avatar_profile;}
	            
				$data_leaderboard_user = [
				    'cust_id' => (int)$cust_id,
					'cust_name' => $user->cust_name,
					'cust_phone' => $user->cust_phone,
					'cust_image' => $avatar_profile,
					//'distance' => $arr_activity_user->distance,
					'distance' => round(($distance_personal/1000),2),
				];
			}

		}
		
		//return response()->json($data_leaderboard);
		//return response()->json($log_activity);
		
		// baca banner dan keterangan
		/*
		$banner_lb = DB::table("running_leaderboards")
			->select('id', 'runlead_name', 'runlead_description', 'runlead_star', 'runlead_end', 'runlead_banner')
			->where("runlead_star","<=",date("Y-m-d"))
			->where("runlead_end",">=",date("Y-m-d"))
			->where("runlead_delete","=","0")
			->first();
		$leaderboard = NULL;
		// $run_image = 'https://topsystem.id/upload/'.$master_challenge->run_image;
		if($banner_lb){
			$leaderboard_banner = [
				'lb_name' => $banner_lb->runlead_name,
				'lb_description' => $banner_lb->runlead_description,
				'lb_banner' => 'https://topsystem.id/upload/event/'.$banner_lb->runlead_banner,
			];
		}
		*/
		
		$data = [
			'status' => [
				'code' => 200,
				'description' => 'Success',
				'leaderboard' => $data_leaderboard,
				'leaderboard_user' => $data_leaderboard_user,
				'leaderboard_banner' => $leaderboard_banner,
			],
		];
		return response()->json($data);		
    }
	
	// leaderboard Challenge
	public function leaderboardChallenge(Request $request)
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
		$run_id = $request->run_id;
		if(!$cust_id || !$run_id){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id dan run_id tidak boleh kosong!'
                ]
            ];
            return response()->json($data);
		}
		
		// cek customer data
        $user = DB::table("customers")->where("id","=",$cust_id)->first();
		if(!$user){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data user tidak ditemukan!'
                ]
            ];
            return response()->json($data);
		}
		
		$master_challenge = DB::table("runnings")->where("id","=",$run_id)->first();
		if(!$master_challenge){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data challenge tidak ditemukan!'
                ]
            ];
            return response()->json($data);
		}		

		$log_activity = DB::table("running_join_activities")
				->selectRaw('ts_running_join_activities.id, cust_name, cust_phone, SUM(ts_running_join_activities.rja_distance) distance, cust_gender, cust_avatar, ts_running_join_activities.cust_id, run_achieve')
				->leftJoin('running_joins', 'running_joins.id', '=', 'running_join_activities.runjoin_id')
				//->leftJoin('runnings', 'runnings.id', '=', 'running_joins.run_id')
				->leftJoin('customers', 'customers.id', '=', 'running_join_activities.cust_id')
				->where("runjoin_active","=","1")
				->where("running_joins.run_id","=",$master_challenge->id)
				->where("rja_flagged","=","0")
				->groupBy('running_join_activities.cust_id')
				->orderBy("distance","DESC")
				->limit('20')
				->get();
		//var_dump($log_activity);
		//exit();
		$data_leaderboard = array();
		$no=0;
		$cutomer_id = array();
		foreach($log_activity as $row){
			$no++;
			$cutomer_id[]=$row->cust_id;
			
			if(empty($row->cust_avatar)){
				if($row->cust_gender == 'm'){
					//$avatar_profile = "https://avatar.iran.liara.run/public/boy?username=scott";
					$avatar_profile = "https://www.topsystem.id/upload/avatar/AV1.png";
				} else {
					//$avatar_profile = "https://avatar.iran.liara.run/public/girl?username=maria";
					$avatar_profile = "https://www.topsystem.id/upload/avatar/AV51.png";
				}
			} else {
				$avatar_profile = $row->cust_avatar;
			}
            
            
            // cek strava profil
			$user_strava = DB::table("strava_users")->where("cust_id","=",$row->cust_id)->orderBy("updated_at","DESC")->first();
			if($user_strava->su_profile_medium){ 
			    if($user_strava->su_profile_medium == "avatar/athlete/medium.png"){ $avatar_profile=$avatar_profile; } else {
					$avatar_profile = $user_strava->su_profile_medium; 
				}
			    //$avatar_profile = $user_strava->su_profile; 
			} else {$avatar_profile=$avatar_profile;}
            
            // round(($row->distance/1000),2)
			$run_distance_activity = round(($row->distance/1000),2); 
			if($run_distance_activity > $master_challenge->run_distance){$run_distance_activity=$master_challenge->run_distance;} else {$run_distance_activity=$run_distance_activity;}
            
			$data_leaderboard[]= [
				'cust_id' => $row->cust_id,
				'cust_name' => $row->cust_name,
				'cust_phone' => $row->cust_phone,
				'cust_image' => $avatar_profile,
				//'distance' => $row->distance,
				//'distance' => round(($row->distance/1000),2),
				'distance' => $run_distance_activity,
				'run_achive' => $row->run_achieve,
			];
		}
		
		usort($data_leaderboard, function($a, $b) {
			return $b['distance'] <=> $a['distance'];
		});
		
		// cek jika tidak ada list dalam 20 user teratas? **********
		$data_leaderboard_user = NULL;
		if(!in_array($cust_id,$cutomer_id)){
			$arr_activity_user = DB::table("running_join_activities")
				->selectRaw('ts_running_join_activities.id, cust_name, cust_phone, SUM(ts_running_join_activities.rja_distance) distance, cust_gender, cust_avatar, ts_running_join_activities.cust_id')
				->leftJoin('running_joins', 'running_joins.id', '=', 'running_join_activities.runjoin_id')
				->leftJoin('customers', 'customers.id', '=', 'running_join_activities.cust_id')
				->where("runjoin_active","=","1")
				->where("running_joins.run_id","=",$master_challenge->id)
				->where("running_join_activities.cust_id","=",$cust_id)
				->where("rja_flagged","=","0")
				->groupBy('running_join_activities.cust_id')
				->orderBy("distance","DESC")
				->first();
			if($arr_activity_user){
				if(empty($row->cust_avatar)){
					if($arr_activity_user->cust_gender == 'm'){
						//$avatar_profile = "https://avatar.iran.liara.run/public/boy?username=scott";
						$avatar_profile = "https://www.topsystem.id/upload/avatar/AV1.png";
					} else {
						//$avatar_profile = "https://avatar.iran.liara.run/public/girl?username=maria";
						$avatar_profile = "https://www.topsystem.id/upload/avatar/AV51.png";
					}
				} else {
					$avatar_profile = $arr_activity_user->cust_avatar;
				}
	            
	            // cek strava profil
				$user_strava = DB::table("strava_users")->where("cust_id","=",$cust_id)->orderBy("updated_at","DESC")->first();
				if($user_strava->su_profile_medium){ 
					if($user_strava->su_profile_medium == "avatar/athlete/medium.png"){ $avatar_profile=$avatar_profile; } else {
						$avatar_profile = $user_strava->su_profile_medium; 
					}
					//$avatar_profile = $user_strava->su_profile; 
				} else {$avatar_profile=$avatar_profile;}
	            
				$data_leaderboard_user = [
					'cust_id' => (int)$cust_id,
					'cust_name' => $arr_activity_user->cust_name,
					'cust_phone' => $arr_activity_user->cust_phone,
					'cust_image' => $avatar_profile,
					//'distance' => $arr_activity_user->distance,
					'distance' => round(($arr_activity_user->distance/1000),2),
				];
			}
		}
		
		$data = [
			'status' => [
				'code' => 200,
				'description' => 'Success',
				'leaderboard' => $data_leaderboard,
				'leaderboard_user' => $data_leaderboard_user,
			],
		];
		return response()->json($data);		
    }
    
    // claim benefit reward jika sudah achieve
	public function claimChallenge(Request $request)
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
		$run_id = $request->run_id;
		if(!$cust_id || !$run_id){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id dan run_id tidak boleh kosong!'
                ]
            ];
            return response()->json($data);
		}
		
		// cek customer data
        $user = DB::table("customers")->where("id","=",$cust_id)->first();
		if(!$user){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data user tidak ditemukan!'
                ]
            ];
            return response()->json($data);
		}
		
		$master_challenge = DB::table("runnings")->where("id","=",$run_id)->first();
		if(!$master_challenge){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data challenge tidak ditemukan!',
                ]
            ];
            return response()->json($data);
		}
		
		// cek apakah user sudah join atau belum ???
		$arr_join = DB::table("running_joins")->where(["run_id" => $run_id, "cust_id" => $cust_id, "runjoin_active" => "1"])->first();
		if(!$arr_join){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Kamu belum join ke challenge '.$master_challenge->run_name.'!',
                ]
            ];
            return response()->json($data);
		}
		
		// jika belum achieve
		if($arr_join->run_achieve!="1"){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Kamu belum achieve target untuk challenge '.$master_challenge->run_name.'!'
                ]
            ];
            return response()->json($data);
		}
		
		// jika sudah pernah di klaim
		if($arr_join->run_achieve=="1" && $arr_join->run_claim=="1"){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Kamu sudah pernah melakukan klaim reward untuk challenge '.$master_challenge->run_name.'!'
                ]
            ];
            return response()->json($data);
		}
		
		// jika sudah achieve dan belum di klaim ***
		if($arr_join->run_achieve=="1" && $arr_join->run_claim=="0"){
			$data_update = [
				'run_claim' => "1",
				'claim_updated' => date("Y-m-d H:i:s"),
			];
			DB::table("running_joins")->where('id', $arr_join->id)->update($data_update);
			
			// cek koin reward
			if($master_challenge->run_coin>0){
				$nilai_coin = $master_challenge->run_coin;
			} else {
				$nilai_coin = 0;
			}
			
			// cek score
			if($master_challenge->run_score>0){
				$nilai_score = $master_challenge->run_score;
			} else {
				$nilai_score = 0;
			}
			
			// masukan log koin dan score jika ada kemudian update ke master data customer untuk update koin dan score
			$cust_score_update = $user->cust_score + $nilai_score;
			$cust_coin_update = $user->cust_coin + $nilai_coin;
			DB::table("customers")->where('id', $user->id)->update(['cust_coin'=>$cust_coin_update, 'cust_score'=>$cust_score_update]);
			// input coin log
			if($nilai_coin>0){
				$data_coin = [
							'cust_id' => $cust_id,
							'ccoin_type' => 'running challenge',
							'ccoin_lookup_id' => $arr_join->id, // join id challenge
							'ccoin_description' => 'Running challenge '.$master_challenge->run_name.' ('.$master_challenge->run_type.')',
							'ccoin_value' => $nilai_coin,
							'created_at' => date("Y-m-d H:i:s"),
				];
				DB::table("customer_coins")->insertGetId(array_merge($data_coin));							
			}
			
			// input score log
			if($nilai_score>0){
				$data_score = [
							'cust_id' => $cust_id,
							'cscore_type' => 'running challenge',
							'cscore_lookup_id' => $arr_join->id,
							'cscore_description' => 'Running challenge '.$master_challenge->run_name.' ('.$master_challenge->run_type.')',
							'cscore_value' => $nilai_score,
							'created_at' => date("Y-m-d H:i:s"),
				];
				DB::table("customer_scores")->insertGetId(array_merge($data_score));			
			}
			// ************************************** //

			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Reward challenge berhasil di klaim',
				],
			];
			return response()->json($data);
		} 
		// jika reward challenge tidak berhasil diklaim
		else {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Reward challenge gagal di klaim!',
				],
			];
			return response()->json($data);
		}
    }    
    
    // jika diputuskan koneksi strava -> reset semua  log sesuai customer ***
	public function resetchallenge(Request $request)
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
		if(!$cust_id){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id dan run_id tidak boleh kosong!'
                ]
            ];
            return response()->json($data);
		}
		
		// cek customer data
        $user = DB::table("customers")->where("id","=",$cust_id)->first();
		if(!$user){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data user tidak ditemukan!'
                ]
            ];
            return response()->json($data);
		}
		
		// cek apakah user sudah join atau belum ???
		$query_join = DB::table("running_joins")->where(["cust_id" => $cust_id, "runjoin_active" => "1"])->get();
		if(count($query_join)>0){
			foreach($query_join as $raw){
				// reset data yang sudah konek sebelumnya...
				DB::table("running_joins")->where('id', $raw->id)->update(['cust_id'=>NULL, 'cust_id_delete'=>$cust_id, 'runjoin_active'=>'0', 'updated_at'=>date("Y-m-d H:i:s")]);
				DB::table("running_join_activities")->where('cust_id', $cust_id)->update(['cust_id'=>NULL, 'cust_id_delete'=>$cust_id, 'updated_at'=>date("Y-m-d H:i:s")]);
			}
		}
		
		$data = [
			'status' => [
				'code' => 200,
				'description' => 'Data challenge berhasil di reset',
			],
		];
		return response()->json($data);
    }
    
    // ongoing list challenge
	public function listongoingChallenge(Request $request)
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
		if(!$cust_id){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id tidak boleh kosong!'
                ]
            ];
            return response()->json($data);
		}
		// cek customer data
        $user = DB::table("customers")->where("id","=",$cust_id)->first();
		if(!$user){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data user tidak ditemukan!'
                ]
            ];
            return response()->json($data);
		}
		
		$challenge = DB::table("running_joins")
			->select('running_joins.id', 'run_id', 'run_name', 'run_image', 'run_start', 'run_end', 'run_type', 'run_description', 'run_distance', 'run_coin', 'run_score', 'run_achieve', 'run_claim')
			->leftJoin('runnings', 'runnings.id', '=', 'running_joins.run_id')
			->where("run_start","<=",date("Y-m-d"))
			->where("run_end",">=",date("Y-m-d"))
			->where(["run_active" => "1", "run_delete" => "0", "cust_id" => $cust_id, 'run_claim'=>'0'])
			//->orderBy("run_type","DESC")
			//->orderBy("run_distance","ASC")
			->orderBy("run_achieve","DESC")
			->orderBy("run_claim","ASC")
			//->get();
			->paginate(10);
		$data_challenge = array();
		foreach($challenge as $raw){
			// cek log aktifitas running
			$log_activity = DB::table("running_join_activities")
					->select('running_join_activities.id', 'rja_distance', 'rja_moving_time', 'rja_start_date', 'rja_object_id', 'rja_subscription_id', 'running_join_activities.created_at')
					->leftJoin('running_joins', 'running_joins.id', '=', 'running_join_activities.runjoin_id')
					->where(["running_join_activities.cust_id"=>$cust_id, "run_id"=>$raw->run_id, 'rja_flagged'=>'0'])
					->get();
			$run_progress=0;
			$distanceKm = 0;
			if($log_activity){
					foreach($log_activity as $log){
						$distanceKm += $log->rja_distance;
					}
			}
			//$run_distance_activity = round(($distanceKm / 1000),2);
			$run_distance_activity = round(number_format(($distanceKm / 1000),2, '.', ''),2);
			if($distanceKm>0){
					$master_km = $raw->run_distance;
					if($run_distance_activity < $master_km){
						//$run_progress = round(($run_distance_activity / $master_km))*100;
						$run_progress = round(($run_distance_activity / $master_km)*100);
					} else { $run_progress = 100; }
			}
				
			$run_progress = $run_progress; // persen
			$run_distance_activity = $run_distance_activity; // KM
            if($run_distance_activity>$raw->run_distance){$run_distance_activity=$raw->run_distance;} else {$run_distance_activity=$run_distance_activity;}
            
			$data_challenge[]=[
				'run_id' => $raw->run_id,
				'run_type' => $raw->run_type,
				'run_name' => $raw->run_name,
				'run_image' => 'https://topsystem.id/upload/'.$raw->run_image,
				'run_description' => $raw->run_description,
				'run_start' => $raw->run_start,
				'run_end' => $raw->run_end,
				'run_distance' => $raw->run_distance,
				'run_coin' => $raw->run_coin,
				'run_score' => $raw->run_score,
				'run_progress' => $run_progress, // persen
				'run_distance_activity' => $run_distance_activity, // KM
				'run_achieve' => $raw->run_achieve,
				'run_claim' => $raw->run_claim,
			];
		}
		//return $data_challenge;
		
        $data = [
			'status' => [
				'code' => 200,
				'description' => 'Success',
				'ongoing_challenge' => $data_challenge,
			],
		];
		return response()->json($data);		
    }
    
    // riwayat challenge user
	public function logChalengeuser(Request $request)
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
		if(!$cust_id){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id tidak boleh kosong!'
                ]
            ];
            return response()->json($data);
		}
		
		// cek customer data
        $user = DB::table("customers")->where("id","=",$cust_id)->first();
		if(!$user){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data user tidak ditemukan!'
                ]
            ];
            return response()->json($data);
		}
		

		$log_challenge = DB::table("running_joins")
				->selectRaw('ts_runnings.id, cust_name, cust_phone, cust_gender, cust_avatar, cust_id, achieve_updated, run_name, run_type, run_image, run_description, run_start, run_end, run_distance, run_coin, run_score, run_achieve, run_claim')
				->leftJoin('customers', 'customers.id', '=', 'running_joins.cust_id')
				->leftJoin('runnings', 'runnings.id', '=', 'running_joins.run_id')
				->where('cust_id', '=', $cust_id)
				->where('runjoin_active', '=', "1")
				->where('run_active', '=', "1")
				->where('run_delete', '=', "0")
				->where('run_end', '<', date("Y-m-d"))
				->where(function($w) use($request) {
					$run_status = $request->run_status;
					if ($run_status || $run_status=="0") {
						//if($run_status==10){$run_status=0;} else {$run_status=$run_status;}
						//$w->where('run_achieve','=', $run_status);
						if($run_status==1){
							$w->where('run_achieve','=', $run_status);
						} else {
							$w->where('run_achieve','=', '0');
							$w->where('run_end','<', date("Y-m-d"));
						}
					}
					
				})
				->groupBy("runnings.id")
				->orderBy("run_end","DESC")
				//->limit('20')
				->get();

		$data_challenge = array();
		$no=0;
		$cutomer_id = array();
		foreach($log_challenge as $row){
			$no++;
			$cutomer_id[]=$row->cust_id;
			
			if(empty($row->cust_avatar)){
				if($row->cust_gender == 'm'){
					//$avatar_profile = "https://avatar.iran.liara.run/public/boy?username=scott";
					$avatar_profile = "https://www.topsystem.id/upload/avatar/AV1.png";
				} else {
					//$avatar_profile = "https://avatar.iran.liara.run/public/girl?username=maria";
					$avatar_profile = "https://www.topsystem.id/upload/avatar/AV51.png";
				}
			} else {
				$avatar_profile = $row->cust_avatar;
			}
			
			//$status_challenge = '';
			if($row->run_achieve==1){ $status_challenge = 1; } else {
				// cek apakah sudah expired?
				//if($row->run_end<date("Y-m-d")){$status_challenge = 10;}
				$status_challenge = 0;
			}

			$data_challenge[]=[
				'run_id' => $row->id,
				'run_type' => $row->run_type,
				'run_name' => $row->run_name,
				'run_image' => 'https://topsystem.id/upload/'.$row->run_image,
				'run_description' => $row->run_description,
				'run_start' => $row->run_start,
				'run_end' => $row->run_end,
				'run_distance' => (int)$row->run_distance,
				'run_coin' => $row->run_coin,
				'run_score' => $row->run_score,
				'run_status' => $status_challenge, // (1) seleseai, (10) expired
			];
		}
		

		if($no>0){
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success',
					'challenge' => $data_challenge,
				],
			];
		} else {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data tidak ditemukan!',
				],
			];
		}
		return response()->json($data);		
    }
    
    // posting save usap pemenang
	public function wipeSave(Request $request)
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
		$run_id = $request->run_id;
		if(!$cust_id || !$run_id){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id dan run_id tidak boleh kosong!'
                ]
            ];
            return response()->json($data);
		}
		
		// cek customer data
        $user = DB::table("customers")->where("id","=",$cust_id)->first();
		if(!$user){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data user tidak ditemukan!'
                ]
            ];
            return response()->json($data);
		}
		
		$master_challenge = DB::table("runnings")->where("id","=",$run_id)->first();
		if(!$master_challenge){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data challenge tidak ditemukan!',
                ]
            ];
            return response()->json($data);
		}
		
		// cek apakah user sudah join atau belum ???
		$arr_join = DB::table("running_joins")->where(["run_id" => $run_id, "cust_id" => $cust_id, "runjoin_active" => "1"])->first();
		if(!$arr_join){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Kamu belum join ke challenge '.$master_challenge->run_name.'!',
                ]
            ];
            return response()->json($data);
		}
		
		// jika belum achieve
		if($arr_join->runjoin_wipe=="1"){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Kamu sudah melakukan usap pemenang untuk challenge '.$master_challenge->run_name.'!'
                ]
            ];
            return response()->json($data);
		}
		
		// jika belum melakukan usap pemenang hadiah sponsor ***
		if($arr_join->runjoin_wipe=="0"){
			$data_update = [
				'runjoin_wipe' => "1",
				'wipe_updated' => date("Y-m-d H:i:s"),
			];
			DB::table("running_joins")->where('id', $arr_join->id)->update($data_update);
						
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Pemenang challenge berhasil di lihat',
				],
			];
			return response()->json($data);
		} 
		// jika reward challenge tidak berhasil diklaim
		else {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Pemenang challenge gagal di lihat!',
				],
			];
			return response()->json($data);
		}
    }
    
    public function getlogActivitysave(Request $request)
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
		
		$logstrava_pending = DB::table("callback_strava")
			->where(["flag_insert"=>"0", 'aspect_type'=>"create"])
			->where("created_at",">=","2025-08-05 00:00:01")
			->orderBy('id',"ASC")
			->limit(1)
			->get();
		
		foreach($logstrava_pending as $row_callback){
			$owner_id = $row_callback->owner_id;
			$activityId = $row_callback->object_id;
			//return $owner_id.'  =  '.$activityId;
			
			$customer_strava = DB::table("strava_users")->where("su_owner_id","=",$owner_id)->first();
			if($customer_strava){
	
			//$cust_id = $customer->id;
			$user_access_token = $customer_strava->su_token_id;
			//return $user_access_token;
	
			$tanggal_expired = date("Y-m-d H:i:s", $customer_strava->su_token_expired);
			if (date("Y-m-d H:i:s") > $tanggal_expired) {
				$response = Http::asForm()->post('https://www.strava.com/oauth/token', [
					'client_id' => $this->strava_client_id,
					'client_secret' => $this->strava_client_secret,
					'grant_type' => 'refresh_token',
					'refresh_token' => $customer_strava->su_token_refresh,
				]);
				
				if($response->successful()){
					$data = $response->json();
					$access_token = $data['access_token'];
					$refresh_token = $data['refresh_token'];
					//$expires_at = Carbon::createFromTimestamp($data['expires_at']);
					$expires_at = $data['expires_at'];
					// ************************************************ //
					$user_access_token = $data['access_token'];
					
					// update log strava connect ***
					$data_save = [
						'su_token_id' => $data['access_token'],
						'su_token_refresh' => $data['refresh_token'],
						'su_token_expired' => $data['expires_at'],
						'updated_at' => date("Y-m-d H:i:s"),
					];
					DB::table("strava_users")->where('id', $customer_strava->id)->update($data_save);
				}
			}

	
			$response = '';
			if ($activityId) {
				// Ambil detail aktivitas dari Strava API
				$response = Http::withToken($user_access_token)
					->get("https://www.strava.com/api/v3/activities/{$activityId}");
				
				if ($response->successful()) {
					$data = $response->json();
					
					if($data['flagged']==true){ $flagged = 1; } else { $flagged = 0; }
					$rja_uniqid = mt_rand(1000000000,9999999999);
					// save log aktifitas ***
					if($data['type']=='Run'){
						$run_join = DB::table("running_joins")
							->select('running_joins.id', 'run_id', 'run_name', 'run_image', 'run_start', 'run_end', 'run_type', 'run_description', 'run_distance', 'run_coin', 'run_score')
							->leftJoin('runnings', 'runnings.id', '=', 'running_joins.run_id')
							->where("run_start","<=",date("Y-m-d"))
							->where("run_end",">=",date("Y-m-d"))
							->where(["run_active" => "1", "run_delete" => "0", "cust_id" => $customer_strava->cust_id])
							->where("run_achieve","=","0") // hanya yang belum achieve 
							->orderBy("run_type","DESC")
							->orderBy("run_distance","ASC")
							->get();
						$nomor=0;
						foreach($run_join as $raw_join){
							$nomor++;
							$start_date = date("Y-m-d H:i:s",strtotime($data['start_date']));
	
							// cek apakah join ke event ???
							$cek_join_event = DB::table("running_events")
								->select('running_events.id', 'et_start_date', 'et_start_time', 'et_end_date', 'et_end_time')
								->leftJoin('event_tops', 'event_tops.id', '=', 'running_events.et_id')
								->where("run_id","=",$raw_join->run_id) // cek by challenge ***
								->where("runevent_delete","=","0")
								->where("et_delete","=","0")
								->first();
							if($cek_join_event){
								if(($cek_join_event->et_start_date.' '.$cek_join_event->et_start_time)<=date("Y-m-d H:i:s") && ($cek_join_event->et_end_date.' 23:59:00')>=date("Y-m-d H:i:s") ){
									$log_event_valid = 1;
								} else {
									$log_event_valid = 0;
								}
							} else {
								$log_event_valid = 1;
							}
							// end cek apakah join ke event ???
							
							if($log_event_valid==1){
								$data_log = [
									'runjoin_id' => $raw_join->id,
									'cust_id' => $customer_strava->cust_id,
									'rja_name' => $data['name'],
									'rja_distance' => $data['distance'],
									'rja_moving_time' => $data['moving_time'],
									'rja_start_date' => $start_date,
									'rja_object_id' => $row_callback->object_id,
									'rja_subscription_id' => $row_callback->subscription_id,
									'rja_uniqid' => $rja_uniqid,
									'rja_flagged' => $flagged,
									'created_at' => date("Y-m-d H:i:s"),
								];
								DB::table("running_join_activities")->insertGetId(array_merge($data_log));
							}
							
							$log_total_distance = DB::table("running_join_activities")
								->where("runjoin_id","=",$raw_join->id)
								->sum('running_join_activities.rja_distance');
							// ubah ke KM jaraknya...
							if($log_total_distance>0){ $log_total_distance_end = round(($log_total_distance / 1000),2); } else { $log_total_distance_end=0; }
							// jarak sudah terpenuhi *** //
							if($raw_join->run_distance<=$log_total_distance_end){
								if($flagged==0){
									$data_update = [
										'run_achieve' => "1",
										'achieve_updated' => date("Y-m-d H:i:s"),
									];
									DB::table("running_joins")->where('id', $raw_join->id)->update($data_update);
									/*
									// kirim notifikasi 
									$title_notif = 'Challenge selesai, klaim hadiahnya!';
									$message_notif = 'Selamat! ŸŽ‰ Kamu telah berhasil menyelesaikan challenge. Klaim hadiah-mu sekarang Tops! ð¤©';
									$this->pushNotifchallenge($user_strava->cust_id,$title_notif, $message_notif);
									*/
								}
							}
						}
						
						// jika log belum tersimpan
						if($nomor==0){
							//$start_date = date("Y-m-d H:i:s",strtotime($getdata->start_date));
							$start_date = date("Y-m-d H:i:s",strtotime($data['start_date']));
							$data_log = [
								'cust_id' => $customer_strava->cust_id,
								'rja_name' => $data['name'],
								'rja_distance' => $data['distance'],
								'rja_moving_time' => $data['moving_time'],
								'rja_start_date' => $start_date,
								'rja_object_id' => $row_callback->object_id,
								'rja_subscription_id' => $row_callback->subscription_id,
								'rja_uniqid' => $rja_uniqid,
								'rja_flagged' => $flagged,
								'created_at' => date("Y-m-d H:i:s"),
							];
							DB::table("running_join_activities")->insertGetId(array_merge($data_log));						
						}
					
					// update callback strava ***
					$data_update = [
						'flag_insert' => "1",
						'updated_at' => date("Y-m-d H:i:s"),
					];
					DB::table("callback_strava")->where('id', $row_callback->id)->update($data_update);
						
					}
					
					
					// selain run
					else {
						$data_update = [
							'flag_insert' => "3", // selain runnin
							'updated_at' => date("Y-m-d H:i:s"),
						];
						DB::table("callback_strava")->where('id', $row_callback->id)->update($data_update);
					}
					
					/*	
					// jika aktifitas mencurigakan kirim notifikasi *** $flagged
					if($flagged==1){
						//kirim notifikasi 
						$title_notif = 'Aktivitas Lari Mencurigakan';
						$message_notif = 'Kami mendeteksi aktivitas lari yang tidak valid. Aktivitas ini tidak dihitung ke progres challenge.';
						$this->pushNotifchallenge($user_strava->cust_id,$title_notif, $message_notif);
					}
					*/
					/// ******************** //
					
				}
			}
			
			}
		}
		
		if(count($logstrava_pending)>0){
		    return json_decode($response);
		} else {
		    $response = [
		        'status' => 400,
		        'description' => 'Data log pending tidak ditemukan!',
		    ];
		    //return json_encode($response);
		    return response()->json($response);
		}
	}
	
	// get profile user strava ***
	public function profileStrava(Request $request)
    {
		$cust_id = $request->cust_id;
		$customer_strava = DB::table("strava_users")->where('cust_id', $cust_id)->orderBy("updated_at","DESC")->first();
		if(!$customer_strava){
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'User belum konek strava!',
				],
			];
			return response()->json($data);
		}

        // cek log 
		$gallery_log = DB::table("running_join_activities")
		->select("rja_photos")
		->where('cust_id',"=",$cust_id)
		->whereNotNull('rja_photos')
		->groupBy("rja_uniqid")
		->orderBy("id","DESC")
		->limit(5) // hanya 5 terbaru 
		->get();
		$rallery_array = array();
		if(count($gallery_log)>0){
			foreach($gallery_log as $gallery){
				$rallery_array[]=$gallery->rja_photos;
			}
		}
        
		// jika data sudah pernah di simpan,tampilkan tanpa load ke API strava ***
		//if($customer_strava->su_stravaid){
		if($customer_strava->su_stravaid && $customer_strava->su_stravaupdated==date("Y-m-d")){
			$respon_data = [
				'status' => 200,
				'id' => $customer_strava->su_stravaid,
				'username' => $customer_strava->su_username,
				'firstname' => $customer_strava->su_firstname,
				'lastname' => $customer_strava->su_lastname,
				'profile_medium' => $customer_strava->su_profile_medium,
				'profile' => $customer_strava->su_profile,
				'follower_count' => $customer_strava->su_followers,
				'friend_count' => $customer_strava->su_following,
				'logo_stava' => 'https://topsystem.id/upload/strava_icon_new.svg',
				'gallery' => $rallery_array,
			];
			return response()->json($respon_data);
		}

		//$customers = DB::table("customers")->where('id', "3")->first();
		//$customer_strava = DB::table("strava_users")->where('id', "3")->first();
		// $user->strava_access_token
        /*
		$token = Session::get('strava_access_token');

        if (!$token) {
            return redirect()->route('strava.redirect');
        }
		*/
		$token = $customer_strava->su_token_id;
		// cek apakah sudah expired tokennya atau belum???
		$tanggal_expired = date("Y-m-d H:i:s", $customer_strava->su_token_expired);
		if (date("Y-m-d H:i:s") > $tanggal_expired) {
            $response = Http::asForm()->post('https://www.strava.com/oauth/token', [
                'client_id' => $this->strava_client_id,
                'client_secret' => $this->strava_client_secret,
                'grant_type' => 'refresh_token',
                'refresh_token' => $customer_strava->su_token_refresh,
            ]);
			
            if($response->successful()){
                $data = $response->json();
				$access_token = $data['access_token'];
                $refresh_token = $data['refresh_token'];
				$expires_at = $data['expires_at'];
				// ************************************************ //
				$user_access_token = $data['access_token'];
				
				// update log strava connect ***
				$data_save = [
					'su_token_id' => $data['access_token'],
					'su_token_refresh' => $data['refresh_token'],
					'su_token_expired' => $data['expires_at'],
					'su_stravaupdated' => date('Y-m-d'),
					'updated_at' => date("Y-m-d H:i:s"),
				];
				DB::table("strava_users")->where('id', $customer_strava->id)->update($data_save);
				$token = $access_token;
            }
        }
		
		
		$response = ['status' => "400"];
		//$response =  response()->json($response);
		
		if ($token) {
			// Ambil detail aktivitas dari Strava API
			/*
			$response = Http::withToken($user_access_token)
				->get("https://www.strava.com/api/v3/activities/{$activityId}");
	
			if ($response->successful()) {
				$data = $response->json();
			}
			*/
			$response = Http::withToken($token)
				->get('https://www.strava.com/api/v3/athlete')
				->json();
				
			// simpan ke database *** customer_strava
			//if(!$customer_strava->su_stravaid){
			if(1==1){
				$data_update = [
					'su_stravaid' => $response['id'],
					'su_username' => $response['username'],
					'su_firstname' => $response['firstname'],
					'su_lastname' => $response['lastname'],
					'su_profile_medium' => $response['profile_medium'],
					'su_profile' => $response['profile'],
					//'su_following' => $response['friend_count'],
					'su_following' => $response['friend_count'] ?? 0,
					//'su_followers' => $response['follower_count'],
					'su_followers' => $response['follower_count'] ?? 0,
					'su_stravaupdated' => date('Y-m-d'),
				];
				DB::table("strava_users")->where('id', $customer_strava->id)->update($data_update);
			}

		}
		
		//return $response;
		if(!empty($response['id'])){
			/*
			$respon_data = [
				'status' => 200,
				'id' => $response['id'],
				'username' => $response['username'],
				'resource_state' => $response['resource_state'],
				'firstname' => $response['firstname'],
				'lastname' => $response['lastname'],
				'bio' => $response['bio'],
				'city' => $response['city'],
				'state' => $response['state'],
				'country' => $response['country'],
				'sex' => $response['sex'],
				'premium' => $response['premium'],
				'summit' => $response['summit'],
				'created_at' => $response['created_at'],
				'updated_at' => $response['updated_at'],
				'badge_type_id' => $response['badge_type_id'],
				'weight' => $response['weight'],
				'profile_medium' => $response['profile_medium'],
				'profile' => $response['profile'],
				'friend' => $response['friend'],
				'follower' => $response['follower'],
				'blocked' => $response['blocked'],
				'can_follow' => $response['can_follow'],
				'follower_count' => $response['follower_count'],
				'friend_count' => $response['friend_count'],
				'mutual_friend_count' => $response['mutual_friend_count'],
				'athlete_type' => $response['athlete_type'],
				'date_preference' => $response['date_preference'],
				'measurement_preference' => $response['measurement_preference'],
			];
			*/
			$respon_data = [
				'status' => 200,
				'id' => $response['id'],
				'username' => $response['username'],
				'firstname' => $response['firstname'],
				'lastname' => $response['lastname'],
				'profile_medium' => $response['profile_medium'],
				'profile' => $response['profile'],
				'follower_count' => $response['follower_count'],
				'friend_count' => $response['friend_count'],
				'logo_stava' => 'https://topsystem.id/upload/strava_icon_new.svg',
				'gallery' => $rallery_array,
			];
			$response = $respon_data;
		}
		
		return response()->json($response);
		
        /*
		$response = Http::withToken($token)
            ->get('https://www.strava.com/api/v3/athlete')
            ->json();

        return view('strava.profile', compact('athlete'));
		*/
    }
    
    // save user prodfile strava user ***
	private function profileStravasave($cust_id)
    {
		$customer_strava = DB::table("strava_users")->where('cust_id', $cust_id)->orderBy("updated_at","DESC")->first();
		if(!$customer_strava){
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'User belum konek strava!',
				],
			];
			return response()->json($data);
		}

		$token = $customer_strava->su_token_id;
		// cek apakah sudah expired tokennya atau belum???
		$tanggal_expired = date("Y-m-d H:i:s", $customer_strava->su_token_expired);
		if (date("Y-m-d H:i:s") > $tanggal_expired) {
            $response = Http::asForm()->post('https://www.strava.com/oauth/token', [
                'client_id' => $this->strava_client_id,
                'client_secret' => $this->strava_client_secret,
                'grant_type' => 'refresh_token',
                'refresh_token' => $customer_strava->su_token_refresh,
            ]);
			
            if($response->successful()){
                $data = $response->json();
				$access_token = $data['access_token'];
                $refresh_token = $data['refresh_token'];
				$expires_at = $data['expires_at'];
				// ************************************************ //
				$user_access_token = $data['access_token'];
				
				// update log strava connect ***
				$data_save = [
					'su_token_id' => $data['access_token'],
					'su_token_refresh' => $data['refresh_token'],
					'su_token_expired' => $data['expires_at'],
					'updated_at' => date("Y-m-d H:i:s"),
				];
				DB::table("strava_users")->where('id', $customer_strava->id)->update($data_save);
				$token = $access_token;
            }
        }
		
		
		$response = ['status' => 400];
		//$response =  response()->json($response,400);
		
		if ($token) {
			$response = Http::withToken($token)
				->get('https://www.strava.com/api/v3/athlete')
				->json();
				
			// simpan ke database *** customer_strava
			if(!$customer_strava->su_stravaid){
				$data_update = [
					'su_stravaid' => $response['id'],
					'su_username' => $response['username'],
					'su_firstname' => $response['firstname'],
					'su_lastname' => $response['lastname'],
					'su_profile_medium' => $response['profile_medium'],
					'su_profile' => $response['profile'],
					//'su_following' => $response['friend_count'],
					'su_following' => $response['friend_count'] ?? 0,
					//'su_followers' => $response['follower_count'],
					'su_followers' => $response['follower_count'] ?? 0,
					'su_stravaupdated' => date('Y-m-d'),
				];
				DB::table("strava_users")->where('id', $customer_strava->id)->update($data_update);
			}

		}
		
		//return $response;
		if(!empty($response['id'])){
			$respon_data = [
				'status' => 200,
			];
			$response = $respon_data;
		}
		return response()->json($response);		
    }
    
    // laporkan ***
	public function reportStrava(Request $request)
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
		$uniqid = $request->uniqid;
		
		if(empty($cust_id) || empty($uniqid)){
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Field cust_id dan uniqid tidak boleh kosong!',
				],
			];
			return response()->json($data);
		}
		
		$customer_strava = DB::table("strava_users")->where('cust_id', $cust_id)->orderBy("updated_at","DESC")->first();
		if(!$customer_strava){
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'User belum konek strava!',
				],
			];
			return response()->json($data);
		}
		
		// cek log aktifitas
		$customer_logstrava = DB::table("running_join_activities")
			->select('id', 'rja_uniqid')
			//->where(['cust_id' => $cust_id, 'rja_uniqid'=>$uniqid])
			->where(['rja_uniqid'=>$uniqid])
			->first();
		if(!$customer_logstrava){
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Log aktititas tidak ditemukan!',
				],
			];
			return response()->json($data);
		}
		
		// cek apakah sudah pernah melaporkan aktifitas ***
		$check_report = DB::table("running_join_activity_reports")->where(["rja_uniqid"=>$uniqid, 'cust_id'=>$cust_id])->first();
		if($check_report){
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Kamu sudah pernah melaporkan aktifitas ini!',
				],
			];
			return response()->json($data);
		}
		
		// insert log yang di laporkan user ***
		$data_save = [
				'cust_id' => $cust_id,
				'rja_uniqid' => $uniqid,
				//'rjar_description' => $request->rjar_description,
				'created_at' => date("Y-m-d H:i:s"),
				'user_input' => $cust_id,
				//'updated_at' => date("Y-m-d H:i:s"),
		];
		$save  = DB::table("running_join_activity_reports")->insertGetId(array_merge($data_save));
		
		//return $response;
		if($save){
			$respon_data = [
				'status' => [
					'code' => 200,
					'description' => 'Aktifitas lari berhasil dilaporkan',
				],
			];
		}
		
		return response()->json($respon_data);
    }
    
    public function checklogDevicedouble(Request $request)
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

		$log_activity = DB::table("running_join_activities")
			->select('rja_uniqid', 'rja_start_date', 'rja_end_date', 'cust_id')
			->where("rja_flagged","=","0")
			->groupBy("rja_uniqid")
			->orderBy("cust_id","ASC")
			->orderBy("rja_start_date","ASC")
			->get();
		$data_array = [];
		$tanggal_akhir = [];
		$cust_id_array = [];
		$no=0;
		foreach($log_activity as $log){
			$no++;
			$tanggal_akhir[$no]= $log->rja_end_date;
			$cust_id_array[$no]= $log->cust_id;
			
			if($no>1){
				if($log->rja_start_date < $tanggal_akhir[($no-1)] && $log->cust_id==$cust_id_array[($no-1)]) { 
					//$rja_flagged=9; 
					$data_save = [
						'rja_flagged' => 9,
					];
					DB::table("running_join_activities")->where('rja_uniqid', $log->rja_uniqid)->update($data_save);
				} 
				//else {$rja_flagged=0;}
			}
			$data_array[] = [
				'rja_uniqid' => $log->rja_uniqid,
				'rja_start_date' => $log->rja_start_date,
				'rja_end_date' => $log->rja_end_date,
			];
		}
			$respon_data = [
				'status' => 200,
				'description' => 'Aktifitas lari berhasil validasi',
				'log' => $data_array,
			];

		return response()->json($respon_data);
	}
}
