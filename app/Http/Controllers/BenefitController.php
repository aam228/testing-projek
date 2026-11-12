<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BenefitController extends Controller
{
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
		
		$cust_id = $request->cust_id;
        if (!$cust_id) {
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id tidak boleh kosong'
                ]
            ];
            return response()->json($data);
        }
		
		$cust = DB::table("customers")->where("id","=",$cust_id)->first();
		
		if(empty($cust->cust_avatar)){
			if($cust->cust_gender == 'm'){
				$avatar_profile = "https://avatar.iran.liara.run/public/boy?username=scott";
			} else {
				$avatar_profile = "https://avatar.iran.liara.run/public/girl?username=maria";
			}
		} else {
			$avatar_profile = $cust->cust_avatar;
		}
		
        $query_benefit = DB::table('customer_benefits')
		->select('id', 'cb_level', 'cb_benefit', 'created_at', 'cb_banner')
		//->leftJoin('users', 'users.id', '=', 'forums.user_input')
		->groupBy("cb_level")
		->orderBy("id",'ASC')
		->get();
		$data_level = array();
		foreach($query_benefit as $row){
			$query_sub = DB::table('customer_benefits')
			->select('id', 'cb_level', 'cb_benefit', 'cb_description', 'created_at', 'cb_banner')
			->where("cb_level","=",$row->cb_level)
			->orderBy("id",'ASC')
			->get();
			$level_benefit = array();
			foreach($query_sub as $sub){
				$level_benefit[] = [
					'benefit_banner' => "https://topsystem.id/public/upload/event/".$sub->cb_banner,
					'benefit_description' => $sub->cb_benefit,
					'benefit_subdescription' => $sub->cb_description,
				];
			}
			// cek level
			if($row->cb_level == "ROOKIE PLAYER"){
				$level_min=1; $level_max=50;
				$level_description = 'Cara sekarang menuju level Pro Player, Mantap! 26 Score akan membuat naik level.';
				$logo_level = url('public/upload/rookie.png');
				$logo_banner = url('public/upload/bg_rookie.jpg');
			} else 
			if($row->cb_level == "PRO PLAYER"){
				$level_min=51; $level_max=100;
				$level_description = 'Cara sekarang menuju level Elite Player, Mantap! 51 Score akan membuat naik level.';
				$logo_level = url('public/upload/pro.png');
				$logo_banner = url('public/upload/bg_pro.jpg');
			} else 
			if($row->cb_level == "ELITE PLAYER"){
				$level_min=101; $level_max=1000000000;
				$level_description = 'Naikan Score dan Coin mu untuk menjadi peringkat pertama di leaderboard!';
				$logo_level = url('public/upload/top.png');
				$logo_banner = url('public/upload/bg_elite.jpg');
			}
			$data_level[] = [
				'level_name' => $row->cb_level,
				'level_min' => $level_min,
				'level_max' => $level_max,
				'level_description' => $level_description,
				'level_icon' => $logo_level,
				'level_banner' => $logo_banner,
				'level_benefit' => $level_benefit,
			];
		}
		
		// ceklevel
		if($cust->cust_membership_type == "ROOKIE PLAYER"){
			$logo_level = url('public/upload/rookie.png');
		} else 
		if($cust->cust_membership_type == "PRO PLAYER"){
			$logo_level = url('public/upload/pro.png');
		} else 
		if($cust->cust_membership_type == "ELITE PLAYER"){
			$logo_level = url('public/upload/top.png');
		}
		
        $data = [
            'status' => [
                'code' => 200,
                'description' => 'Success'
            ],
            'banner' => url('public/upload/bg_profile_white2.jpg'),
			'customer' => [
				'cust_name' => $cust->cust_name,
				'cust_level' => $cust->cust_membership_type,
				'cust_score' => $cust->cust_score,
				'cust_coin' => $cust->cust_coin,
				'cust_level_icon' => $logo_level,
				'cust_avatar' => $avatar_profile,
			],
            'results' => $data_level,
        ];
        return response()->json($data);
    }
	
}
