<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SurveyController extends Controller
{
	/*
	public function __construct()
    {
        $this->strava_client_id = config('services.strava.strava_client_id');
        $this->strava_client_secret = config('services.strava.strava_client_secret');
		$this->strava_verify_token = config('services.strava.strava_verify_token');
		$this->url_strava = 'https://www.strava.com/api/v3/push_subscriptions';
    }
	*/
	
	public function surveyActive(Request $request)
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
		
		
		// cek user konek strava ...
		$qsurvey = DB::table("quick_surveys")
			->select('id', 'qs_type', 'qs_name', 'qs_description', 'qs_option', 'qs_start', 'qs_end', 'qs_coin')
			->where("qs_start","<=",date("Y-m-d"))
			->where("qs_end",">=",date("Y-m-d"))
			->where("qs_active","=",'1')
			->where("qs_delete","=",'0')
			->get();
		if(count($qsurvey)==0){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data tidak ditemukan!'
                ]
            ];
            return response()->json($data);
		}
		
		$data_survey = null;
		foreach($qsurvey as $raw){
			// cek jika sudah isi survey
			$arr_answer = DB::table("quick_survey_users")->where(["cust_id"=>$cust_id, "qs_id"=>$raw->id])->first();
			if($arr_answer){$flag_answer=1;} else {$flag_answer=0;}
			
			/*
			$qs_option = str_replace([", "],[","],$raw->qs_option);
			$qs_option = explode(",",$qs_option);
			*/
			$survey_option=DB::table("quick_survey_options")->where(["qs_id"=>$raw->id, "qso_delete"=>"0"])->orderBy("qso_name","ASC")->get();
			$qs_option = [];
			foreach($survey_option as $sub){
				$qs_option[] = [
					'so_name' => $sub->qso_name,
					'so_image' => 'https://topsystem.id/upload/survey/'.$sub->qso_image,
				];
			}
			$data_survey = [
					"survey_id" => $raw->id,
					"survey_type" => $raw->qs_type,
					"survey_name" => $raw->qs_name,
					"survey_description" => $raw->qs_description,
					"survey_option" => $qs_option,
					"survey_start" => $raw->qs_start,
					"survey_end" => $raw->qs_end,
					"survey_coin" => (int)$raw->qs_coin,
					"survey_answer" => (int)$flag_answer,
			];
		}
		
        $data = [
			'status' => [
				'code' => 200,
				'description' => 'Success',
				'survey' => $data_survey,
			],
		];
		return response()->json($data);		
    }
	
	// survey save
	public function surveySave(Request $request)
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
		$survey_id = $request->survey_id;
		$answer = $request->survey_answer;
		if(!$cust_id || !$survey_id || !$answer){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id, survey_id dan survey_answer tidak boleh kosong!'
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
		
		$survey_array = DB::table("quick_surveys")->where("id","=",$survey_id)->first();
		if(!$survey_array){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data survey tidak ditemukan!'
                ]
            ];
            return response()->json($data);
		}
		
		// cek sudah pernah isi survey?
		$arr_answer = DB::table("quick_survey_users")->where(["cust_id"=>$cust_id, "qs_id"=>$survey_id])->first();
		if($arr_answer){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Oopps, kamu sudah pernah mengisi survey ini!'
                ]
            ];
            return response()->json($data);
		}
		
		// proses simpan data ***
		$data_save = [
				'cust_id' => $cust_id,
				'qs_id' => $survey_id,
				'qsu_answer' => $answer,
				'created_at' => date("Y-m-d H:i:s"),
				'updated_at' => date("Y-m-d H:i:s"),
		];
		DB::table("quick_survey_users")->insertGetId(array_merge($data_save));
		
		// cek jika ada koin 
		$nilai_coin = $survey_array->qs_coin;
		$cust_coin_update = $user->cust_coin + $nilai_coin;
		DB::table("customers")->where('id', $user->id)->update(['cust_coin'=>$cust_coin_update]);
		// input coin log
		if($nilai_coin>0){
			$data_coin = [
				'cust_id' => $cust_id,
				'ccoin_type' => 'survey',
				'ccoin_lookup_id' => $survey_array->id, // survey
				'ccoin_description' => 'Mengisi survey',
				'ccoin_value' => $nilai_coin,
				'created_at' => date("Y-m-d H:i:s"),
			];
			DB::table("customer_coins")->insertGetId(array_merge($data_coin));							
		}
		
        $data = [
			'status' => [
				'code' => 200,
				'description' => 'Success',
			],
		];
		return response()->json($data);		
    }
	
}
