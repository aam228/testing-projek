<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GamesController extends Controller
{
    protected $url_server = 'https://topsystem.id/';
    
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
		
		// save log traffic
		$data_log = [
			'cust_id' => $request->cust_id,
			'ta_page_access' => "Games",
			'ta_description' => "Mengakses laman games aplikasi",
			'created_at' => date("Y-m-d H:i:s"),
			'ip_address' => $request->ip(),
		];
		$save_log = DB::table("traffic_apps")->insertGetId(array_merge($data_log));
		
		$setting = DB::table('setting_apps')->first();
		
		// kategori
		$games_search = $request->games_search; // keyword pencarian
		$games_id = $request->games_id;
		
		$cust_user = DB::table("customers")->where("id","=",$request->cust_id)->first();
		// peringkat users
		$q_ranking_all = DB::table('customers')
		->select('customers.id', 'cust_name', 'cust_score', 'cust_coin')
		//->leftJoin('users', 'users.id', '=', 'games.user_input')
		->where('cust_phone_verify', '=', '1')
		//->where('cust_score', '>=', $cust_user->cust_score)
		->where('cust_score', '>', $cust_user->cust_score)
		->where('id', '!=', $cust_user->id)
		->where('cust_delete', '=', "0")
        ->orderBy("cust_score",'DESC')
		->orderBy("cust_email_verify",'DESC')
		->orderBy("customers.id",'ASC')
		->get();
		//$ranking_cust = (int)(count($q_ranking_all)+1);
		if(count($q_ranking_all)>0){
			$ranking_cust = (int)(count($q_ranking_all)+1);
		} else {
			$ranking_cust = 1;
		}
		
		$q_ranking_10 = DB::table('customers')
		->select('customers.id', 'cust_name', 'cust_score', 'cust_coin', 'cust_membership_type', 'cust_avatar', 'cust_gender', 'cust_reseller')
		//->leftJoin('users', 'users.id', '=', 'games.user_input')
		->where('cust_phone_verify', '=', '1')
		//->where('cust_score', '>=', $cust_user->cust_score)
		//->where('id', '!=', $cust_user->id)
		->where('cust_delete', '=', "0")
        ->orderBy("cust_score",'DESC')
		->orderBy("cust_email_verify",'DESC')
		->orderBy("customers.id",'ASC')
		->limit(10)
		->get();
		// END ***
		$no_rank = 0;
		$arr_ranking = array();
		foreach($q_ranking_10 as $rsub){
			$no_rank++;
			if(empty($rsub->cust_avatar)){
				if($rsub->cust_gender == 'm'){
					$avatar_profile = "https://avatar.iran.liara.run/public/boy?username=scott";
				} else {
					$avatar_profile = "https://avatar.iran.liara.run/public/girl?username=maria";
				}
			} else {
				$avatar_profile = $rsub->cust_avatar;
			}
			$arr_ranking[] = [
				'rangking_id' => $rsub->id,
				'rangking_no' => $no_rank,
				'rangking_name' => $rsub->cust_name,
				'rangking_score' => $rsub->cust_score,
				'rangking_level' => $rsub->cust_membership_type,
				'cust_avatar' => $avatar_profile,
				'cust_reseller' => $rsub->cust_reseller,
			];
		}
		
        $query = DB::table('games')
		->select('games.id', 'gm_name', 'gm_slug', 'gm_description', 'gm_image', 'gm_view', 'gm_time', 'gm_active', 'games.created_at', 'u_name', 'gm_score')
		->leftJoin('users', 'users.id', '=', 'games.user_input')
		->where('games.gm_active', '=', "1")
		->where('games.gm_delete', '=', "0")
		->where(DB::raw("CONCAT(gm_startdate,' ',gm_starttime)"), '<=', date("Y-m-d H:i:s"))
		->where(DB::raw("CONCAT(gm_enddate,' ',gm_endtime)"), '>=', date("Y-m-d H:i:s"))
		->where(function($w) use($games_search, $games_id) {
			if (!empty($games_search)) {
				$w->where(DB::raw("CONCAT(gm_name, ' ', gm_description)"), 'LIKE', "%$games_search%");
			}
			if(!empty($games_id)){
				$w->where('games.id', '=', $games_id);
			}
		})
        ->orderBy("games.created_at",'DESC')
		->paginate(5);
		
		// tampilkan game header
		$games_home = DB::table("game_homes")->first();
		
        $results = array();
		$result_details = array();
        if (!empty($query)) {
            foreach ($query as $row) {
				// cek user yang main game
				$cek_usergame = DB::table("game_users")->where("gm_id","=",$row->id)->where("cust_id","=",$request->cust_id)->first();
				if(empty($cek_usergame)){
					
                if (!empty($row->gm_image)) {
                    $image_url = "https://topsystem.id/upload/game/".$row->gm_image;
                } else {
					$image_url = '';
				}

				// update jika melihat detail data
				//if(!empty($games_id)){
				if(1==1){
					$updated_date = [
						'gm_view' => $row->gm_view+1,
					];
					DB::table("games")->where('id', $row->id)->update($updated_date);
					
					// tampilkan detail games *****
					$query_det = DB::table('game_details')
					->select('game_details.id', 'gmd_description', 'gmd_image', 'gmd_option_1', 'gmd_option_2', 'gmd_option_3', 'gmd_option_4', 'gmd_option_5', 'gmd_true', 'game_details.created_at')
					->leftJoin('users', 'users.id', '=', 'game_details.user_input')
					->where('gm_id', '=', $row->id)
					->where('gmd_delete', '=', "0")
					->orderBy("game_details.created_at",'ASC')
					->get();
					$result_details = array();
					foreach($query_det as $row_detail){
						if (!empty($row_detail->gmd_image)) {
							$image_url_det = "https://topsystem.id/upload/game/".$row_detail->gmd_image;
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

				}
                
				if(count($result_details)>0){
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
			} // end foreach
		
		$level_apps = DB::table("apps_levels")->select('id', 'al_level', 'al_banner', 'al_min', 'al_max')->get();
		$level_array=array();
		foreach($level_apps as $lrow){
			$level_array[] = [
					'level_name' => $lrow->al_level,
					'level_min' => $lrow->al_min,
					'level_max' => $lrow->al_max,
			];
		}
		
		
		if(empty($cust_user->cust_avatar)){
			if($cust_user->cust_gender == 'm'){
				$avatar_profile = "https://avatar.iran.liara.run/public/boy?username=scott";
			} else {
				$avatar_profile = "https://avatar.iran.liara.run/public/girl?username=maria";
			}
		} else {
			$avatar_profile = $cust_user->cust_avatar;
		}
		
        $data = [
            'status' => [
                'code' => 200,
                'description' => 'Success',
            ],
			'info' => [
				//'image' => 'https://topsystem.id/public/upload/bg_games.png',
				//'description' => $setting->info_games,
				'title' => $games_home->gh_title,
				'image' => 'https://topsystem.id/public/upload/'.$games_home->gh_image,
				'description' => $games_home->gh_description,
				'game_info' => $setting->info_game_default,
			],
			/*
			'master_level' => [
				[
					'level_name' => 'ROOKIE PLAYER',
					'level_min' => 1,
					'level_max' => 25,
				],
				[
					'level_name' => 'PRO PLAYER',
					'level_min' => 26,
					'level_max' => 50,
				],
				[
					'level_name' => 'TOP PLAYER',
					'level_min' => 51,
					'level_max' => 1000000000,
				],
			],
			*/
			'master_level' => $level_array,
			'rangking' => [
				'rank_cust_id' => $cust_user->id,
				'rank_cust_name' => $cust_user->cust_name,
				'rank_cust_score' => $cust_user->cust_score,
				'rank_cust_level' => $cust_user->cust_membership_type,
				'rank_cust_number' => $ranking_cust,
				'cust_avatar' => $avatar_profile,
				'cust_reseller' => $cust_user->cust_reseller,
				'rank_cust_list' => $arr_ranking,
			],
            'results' => $results,
        ];
        } else {
            
        if(empty($cust_user->cust_avatar)){
			if($cust_user->cust_gender == 'm'){
				$avatar_profile = "https://avatar.iran.liara.run/public/boy?username=scott";
			} else {
				$avatar_profile = "https://avatar.iran.liara.run/public/girl?username=maria";
			}
		} else {
			$avatar_profile = $cust_user->cust_avatar;
		}
            
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data not found'
				],
				'info' => [
					//'image' => 'https://topsystem.id/public/upload/bg_games.png',
					//'description' => $setting->info_games,
					'title' => $games_home->gh_title,
					'image' => 'https://topsystem.id/public/upload/'.$games_home->gh_image,
					'description' => $games_home->gh_description,
				],
				/*
				'master_level' => [
    				[
    					'level_name' => 'ROOKIE PLAYER',
    					'level_min' => 1,
    					'level_max' => 25,
    				],
    				[
    					'level_name' => 'PRO PLAYER',
    					'level_min' => 26,
    					'level_max' => 50,
    				],
    				[
    					'level_name' => 'TOP PLAYER',
    					'level_min' => 51,
    					'level_max' => 1000000000,
    				],
    			],
    			*/
    			'master_level' => $level_array,
    			'rangking' => [
    				'rank_cust_id' => $cust_user->id,
    				'rank_cust_name' => $cust_user->cust_name,
    				'rank_cust_score' => $cust_user->cust_score,
    				'rank_cust_level' => $cust_user->cust_membership_type,
    				'rank_cust_number' => $ranking_cust,
    				'cust_avatar' => $avatar_profile,
    				'cust_reseller' => $cust_user->cust_reseller,
    				'rank_cust_list' => $arr_ranking,
    			],
				'results' => $results
			];
		}
        return response()->json($data);
    }
	
	
	public function gameUser(Request $request)
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
		
		$setting = DB::table('setting_apps')->first();
		$games_id = $request->games_id;
		$cust_id = $request->cust_id;
		if (empty($games_id) || empty($cust_id)) {
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field games_id dan cust_id tidak boleh kosong'
                ]
            ];
            return response()->json($data);
        }
		
		$games = DB::table('games')->where("id","=",$games_id)->where("gm_delete","=",0)->where("gm_active","=",1)->first();
		if (!$games) {
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Game tidak ditemukan'
                ]
            ];
            return response()->json($data);
        }
		
		// cek sudah pernah ikut game atau belum
		$game_user = DB::table('game_users')->where("gm_id","=",$games_id)->where("cust_id","=",$cust_id)->first();
		// jika belum pernah ikut game
		if(!$game_user){
			// simpan data mengikuti game
			$data = [
				'gm_id' => $games_id,
				'cust_id' => $cust_id,
				'created_at' => date("Y-m-d H:i:s"),
			];
			$gmu_id = DB::table("game_users")->insertGetId(array_merge($data));
			$game_user = DB::table('game_users')->where("id","=",$gmu_id)->first();
		} else {
			$updated_date = [
				'updated_at' => date("Y-m-d H:i:s"),
			];
			DB::table("game_users")->where('id', $game_user->id)->update($updated_date);
			// sudah selesai memainkan game
			if($game_user->gmu_finish==1){
				$data = [
					'status' => [
						'code' => 400,
						'description' => 'Anda sudah selesai memainkan game ini.'
					]
				];
				return response()->json($data);

			}
		}
		// tampilkan list game
		$game_detail = DB::table('game_details')
				       ->select('game_details.id', 'gmd_name', 'gmd_slug', 'gmd_description', 'gmd_image', 'gmd_option_1', 'gmd_option_2', 'gmd_option_3', 'gmd_option_4', 'gmd_option_5', 'gmd_true')
					   ->where("gm_id","=",$games_id)
					   ->where('gmd_delete','=','0')
					   //->orderBy('gmd_name','ASC')
					   ->orderBy('game_details.id','ASC')
					   ->get();
		$task = array();
		if(count($game_detail)>0){
			foreach($game_detail as $raw){
				// cek jawaban
				$game_answer = DB::table('game_user_answers')->where("gmd_id","=",$raw->id)->where('gmu_id','=',$game_user->id)->first();
				if($game_answer){
					$gfun_answer = $game_answer->gmu_answer;
					$gfun_answer_status = $game_answer->gmu_true;
				} else {
					$gfun_answer = '';
					$gfun_answer_status = '';
				}
				
				if (!empty($raw->gmd_image)) {
                    $image_url = "https://topsystem.id/upload/game/".$raw->gmd_image;
                } else {
					$image_url = '';
				}
				
				if(empty($raw->gmd_description)){
					$gmd_description = $raw->gmd_name;
				} else {
					$gmd_description = $raw->gmd_description;
				}
				
				$task[]= [
					'gfun_id' => $raw->id,
					'gfun_userid' => $game_user->id,
					'gfun_name' => $raw->gmd_name,
					'gfun_slug' => $raw->gmd_slug,
					//'gfun_description' => $raw->gmd_description,
					'gfun_description' => $gmd_description,
					'gfun_banner' => $image_url,
					'gfun_option_1' => $raw->gmd_option_1,
					'gfun_option_2' => $raw->gmd_option_2,
					'gfun_option_3' => $raw->gmd_option_3,
					'gfun_option_4' => $raw->gmd_option_4,
					'gfun_option_5' => $raw->gmd_option_5,
					'gfun_true' => (int)$raw->gmd_true,
					'gfun_answer' => (int)$gfun_answer,
					'gfun_answer_status' => (int)$gfun_answer_status,
				];
			}
			
            $data_out = [
                'status' => [
                    'code' => 200,
                    'description' => 'Detail game tersedia'
                ],
				'results' => $task,
            ];
            return response()->json($data_out);
		}
		// tidak ada games ditemukan
		else {
            $data_out = [
                'status' => [
                    'code' => 400,
                    'description' => 'Detail game tidak ditemukan'
                ],
				'results' => $task,
            ];
            return response()->json($data_out);
		}
	}
	
	// simpan jawaban users
	public function gameAnswer(Request $request)
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
		$game_answer = $request->game_answer;
		if (empty($cust_id) || empty($game_answer)) {
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id dan game_answer tidak boleh kosong'
                ]
            ];
            return response()->json($data);
        }
		
		// save jawaban user
		//$game_answer = $request->game_answer;
		$game_answer =  json_decode($game_answer);
		// save answer form
		$no_simpan=0;
		$count_true=0;
		$count_false=0;
		$gmu_id = '';
		foreach ($game_answer as $key => $lookup) {
			$check_answer = DB::table('game_details')->where("id","=",$lookup->gfun_id)->first();
			$check_answer_user = DB::table('game_user_answers')
				->where("gmd_id","=",$check_answer->id)
				->where("gmu_id","=",$lookup->gfun_userid)
				->first();
			if($check_answer->gmd_true == $lookup->answer){
				$gmu_true = 1;
				$count_true++;
			} else {
				$gmu_true = 0;
				$count_false++;
			}
			if(!$check_answer_user){
				$data_save = [
					'gmd_id' => $lookup->gfun_id,
					'gmu_id' => $lookup->gfun_userid,
					'gmu_answer' => $lookup->answer,
					//'gmu_true' => $lookup->status,
					'gmu_true' => $gmu_true,
					'created_at' => date("Y-m-d H:i:s"),
				];
				$save_forms = DB::table("game_user_answers")->insertGetId(array_merge($data_save));
			} 
			// update jawaban user
			else {
				$data_save = [
					'gmu_answer' => $lookup->answer,
					//'gmu_true' => $lookup->status,
					'gmu_true' => $gmu_true,
					'updated_at' => date("Y-m-d H:i:s"),
				];
				$save_forms = DB::table("game_user_answers")->where('id', $check_answer_user->id)->update($data_save);
			}
			$gmu_id = $lookup->gfun_userid;
			$no_simpan++;
		}
		
        // update status pengerjaan game user
		$data_save = [
			'gmu_true' => $count_true,
			'gmu_false' => $count_false,
			'gmu_finish' => 1,
			'updated_at' => date("Y-m-d H:i:s"),
		];
		DB::table("game_users")->where('id', $gmu_id)->update($data_save);
		
		//if($count_false==0){
	    if($count_false==0 && $no_simpan>0){
	        // save score jika game benar semua
			if(!empty($cust_id)){
				$arr_game_user = DB::table("game_users")->where("id","=",$gmu_id)->first();
				$arr_games = DB::table("games")->where("id","=",$arr_game_user->gm_id)->first();
				$arr_cust = DB::table("customers")->where("id","=",$cust_id)->first();
				if($arr_games->gm_score>0){ $score_game = $arr_games->gm_score; } else { $score_game = 1; }
				// cek sudah pernah belum baca detail
				$arr_scores = DB::table("customer_scores")
							  ->where("cust_id","=",$arr_cust->id)
							  ->where("cscore_lookup_id","=",$arr_games->id)
							  ->where("cscore_type","=","game")
							  ->first();
				if($arr_cust && empty($arr_scores)){
					$data_score = [
						'cust_id' => $arr_cust->id,
						'cscore_type' => 'game',
						'cscore_lookup_id' => $arr_games->id,
						'cscore_description' => 'Berhasil memainkan game : '.$arr_games->gm_name,
						//'cscore_value' => 1,
						'cscore_value' => $score_game,
						'created_at' => date("Y-m-d H:i:s"),
					];
					DB::table("customer_scores")->insertGetId($data_score);
					
					// update nilai score customers...
					DB::table("customers")->where('id', $cust_id)->update([
						'cust_score' => ($arr_cust->cust_score + $score_game), 
					]);
				}
			}
			
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Game berhasil diselesaikan',
					//'score' => (int)$count_true,
					'score' => 1,
				]
			];
		} else {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Oops...!, tidak semua game anda selesaikan degan benar',
					'score' => 0,
				]
			];
		}
        return response()->json($data);
		/*
		if($no_simpan>0){
			
		} else {
			
		}
		*/
	}
	
	// game tebak skor ***
	public function tebakScore(Request $request)
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
		/*
		// save log traffic
		$data_log = [
			'cust_id' => $request->cust_id,
			'ta_page_access' => "Games",
			'ta_description' => "Mengakses laman games aplikasi",
			'created_at' => date("Y-m-d H:i:s"),
		];
		$save_log = DB::table("traffic_apps")->insertGetId(array_merge($data_log));
		*/
		
		$cust_id = $request->cust_id;
		if(empty($cust_id)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id tidak boleh kosong'
                ]
            ];
            return response()->json($data);
		}

		$arr_customer = DB::table("customers")->where("id","=",$cust_id)->first();

        $query_data = DB::table("game_scores")
		->select('game_scores.id as pid', 'club_id_1', 'club_id_2', 'gms_score_1', 'gms_score_2', 'gms_name', 'gms_description', 'gms_view', 'gms_score', 'gms_coin', 'gms_startdate', 'gms_starttime', 'gms_enddate', 'gms_endtime', 'gms_active', 'game_scores.created_at')
		->whereRaw("CONCAT(ts_game_scores.gms_startdate,' ',ts_game_scores.gms_starttime) <= '".date('Y-m-d H:i:s')."'")
		->whereRaw("CONCAT(ts_game_scores.gms_enddate,' ',ts_game_scores.gms_endtime) > '".date('Y-m-d H:i:s')."'")
		->where('gms_delete', '=', '0')
		->where('gms_active', '=', '1')
		->orderBy('gms_enddate', 'ASC')
        ->get();
        $results = array();
        if ($query_data) {
            foreach ($query_data as $row) {
				$club1 = DB::table("clubs")->where("id",'=',$row->club_id_1)->first();
				$club2 = DB::table("clubs")->where("id",'=',$row->club_id_2)->first();
                $user_answer = DB::table("game_score_answers")
							   ->where("gms_id","=",$row->pid)
							   ->where("cust_id","=",$cust_id)
							   ->where("gsa_delete","=","0")
							   ->get();
				if(count($user_answer)>0){
					$sudah_jawab = 1;
				} else {
					$sudah_jawab = 0;
				}
				
				if($row->gms_coin>0){
					$game_score_benefit='Koin';
					$game_score_value = $row->gms_coin;
				} else {
					$game_score_benefit='Skor';
					$game_score_value = $row->gms_score;
				}
				
				// cek logo klub
				$logo_klub_1 = $this->url_server.'upload/club/'.$club1->club_image;
				$logo_klub_2 = $this->url_server.'upload/club/'.$club2->club_image;
				
				// cek total user yang menjawab tebak score
				$cust_answer = DB::table("game_score_answers")->where(['gms_id' => $row->pid, "gsa_delete" => '0'])->get();
				
				$customer_join = DB::table("forum_detail_joins")
					->select('forum_detail_joins.id', 'fodet_name', 'fodet_cities', 'fodet_id')
					->leftJoin('forum_details', 'forum_details.id', '=', 'forum_detail_joins.fodet_id')
					->where("forum_detail_joins.cust_id","=",$cust_id)
					->where("foc_id","=",'2') // kusus forum komunitas 
					->where("fodj_delete","=",'0')
					->first();
				//if($customer_join){ $flag_join=1; } else {$flag_join=0;}
				if($customer_join){ 
					$flag_join=1;
					$fodet_id = $customer_join->fodet_id;
					$fodet_name = $customer_join->fodet_name;
				} else {
					$flag_join=0;
					$fodet_id = NULL;
					$fodet_name = NULL;
				}
				
				// $customer_join
				$customer_joins = DB::table('forum_detail_joins')
				->select('forum_detail_joins.id', 'cust_id', 'cust_name', 'cust_email', 'cust_address', 'cust_phone', 'cust_score', 'cust_coin', 'cust_gender', 'cust_avatar', 'cust_reseller')
				->leftJoin('forum_details', 'forum_details.id', '=', 'forum_detail_joins.fodet_id')
				->leftJoin('customers', 'customers.id', '=', 'forum_detail_joins.cust_id')
				->where("foc_id","=",'2') // kusus forum komunitas 
				->where('fodj_delete', '=', '0')
				->get();
				$customer_join_count = count($customer_joins);
				$customer_join_id = '';
				if($customer_joins){
					foreach($customer_joins as $raw_cust_join){
						$customer_join_id .= $raw_cust_join->cust_id.',';
					}
					$customer_join_id = rtrim($customer_join_id,",");
				}
				
				// game_active
				$tgl_end = date('Y-m-d', strtotime($row->gms_enddate . " -2 days"));
				if($tgl_end<=date("Y-m-d")){$game_active=1;} else {$game_active=0;}
				
				if(1==1){
					$results[]= [
						'game_score_id' => $row->pid,
						'game_score_name' => $row->gms_name,
						'game_score_description' => $row->gms_description,
						//'game_score_view' => (int)$row->gms_view,
						'game_score_view' => count($cust_answer),
						'game_score_benefit' => $game_score_benefit,
						'game_score_value' => (int)$game_score_value,
						'game_score_club_1' => $club1->club_name,
						'game_score_club_1_image' => $logo_klub_1,
						'game_score_club_2' => $club2->club_name,
						'game_score_club_2_image' => $logo_klub_2,
						'game_score_end_date' => $row->gms_enddate.' '.$row->gms_endtime,
						'game_score_user_answer' => $sudah_jawab,
						'forum_cust_join' => $flag_join,
						'forum_detail_id' => $fodet_id,
						'forum_join_customer_id' => $customer_join_id,
						'forum_detail_name' => $fodet_name, 
						'game_active' => $game_active,
					];
				}
            }// end foreach

        } // end if

		$data = [
			'status' => [
				'code' => 200,
				'description' => 'Success'
			],
			'results' => $results,
		];

        return $data;
    }
	
	// riwayat tebak score
	public function historyScore(Request $request)
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
		
		/*
		// save log traffic
		$data_log = [
			'cust_id' => $request->cust_id,
			'ta_page_access' => "Games",
			'ta_description' => "Mengakses laman games aplikasi",
			'created_at' => date("Y-m-d H:i:s"),
		];
		$save_log = DB::table("traffic_apps")->insertGetId(array_merge($data_log));
		*/
		
		$cust_id = $request->cust_id;
		if(empty($cust_id)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id tidak boleh kosong'
                ]
            ];
            return response()->json($data);
		}

		$arr_customer = DB::table("customers")->where("id","=",$cust_id)->first();

        $query_data = DB::table("game_scores")
		->select('game_scores.id as pid', 'club_id_1', 'club_id_2', 'gms_score_1', 'gms_score_2', 'gms_name', 'gms_description', 'gms_view', 'gms_score', 'gms_coin', 'gms_startdate', 'gms_starttime', 'gms_enddate', 'gms_endtime', 'gms_active', 'game_scores.created_at')
		//->whereRaw("CONCAT(ts_game_scores.gms_startdate,' ',ts_game_scores.gms_starttime) <= '".date('Y-m-d H:i:s')."'")
		->whereRaw("CONCAT(ts_game_scores.gms_enddate,' ',ts_game_scores.gms_endtime) < '".date('Y-m-d H:i:s')."'") // aktifkan jka hanya data yg sudah berlalu
		->where('gms_delete', '=', '0')
		->where('gms_active', '=', '1')
		->orderBy('gms_enddate', 'DESC')
        ->get();
        $results = array();
        if ($query_data) {
            foreach ($query_data as $row) {
				$club1 = DB::table("clubs")->where("id",'=',$row->club_id_1)->first();
				$club2 = DB::table("clubs")->where("id",'=',$row->club_id_2)->first();
                $user_answer = DB::table("game_score_answers")
							   ->where("gms_id","=",$row->pid)
							   ->where("cust_id","=",$cust_id)
							   ->where("gsa_delete","=","0")
							   ->get();
				if(count($user_answer)>0){
					$sudah_jawab = 1;
				} else {
					$sudah_jawab = 0;
				}
				
				if($row->gms_coin>0){
					$game_score_benefit='Koin';
					$game_score_value = $row->gms_coin;
				} else {
					$game_score_benefit='Skor';
					$game_score_value = $row->gms_score;
				}
				
				// cek logo klub
				$logo_klub_1 = $this->url_server.'upload/club/'.$club1->club_image;
				$logo_klub_2 = $this->url_server.'upload/club/'.$club2->club_image;
				
				// cek total user yang menjawab tebak score
				$cust_answer = DB::table("game_score_answers")->where(['gms_id' => $row->pid, "gsa_delete" => '0'])->get();
				
				if(1==1){
					$results[]= [
						'game_score_id' => $row->pid,
						'game_score_name' => $row->gms_name,
						'game_score_description' => $row->gms_description,
						//'game_score_view' => (int)$row->gms_view,
						'game_score_view' => count($cust_answer),
						'game_score_benefit' => $game_score_benefit,
						'game_score_value' => (int)$game_score_value,
						'game_score_club_1' => $club1->club_name,
						'game_score_club_1_image' => $logo_klub_1,
						'game_score_club_2' => $club2->club_name,
						'game_score_club_2_image' => $logo_klub_2,
						'game_score_end_date' => $row->gms_enddate.' '.$row->gms_endtime,
						'game_score_user_answer' => $sudah_jawab,
					];
				}
            }// end foreach

        } // end if

		$data = [
			'status' => [
				'code' => 200,
				'description' => 'Success'
			],
			'results' => $results,
		];

        return $data;
    }
	
	// leaderboard pemenang tebak score
	public function winuserScore(Request $request)
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
		
		/*
		// save log traffic
		$data_log = [
			'cust_id' => $request->cust_id,
			'ta_page_access' => "Games",
			'ta_description' => "Mengakses laman games aplikasi",
			'created_at' => date("Y-m-d H:i:s"),
		];
		$save_log = DB::table("traffic_apps")->insertGetId(array_merge($data_log));
		*/
		
		$cust_id = $request->cust_id;
		$game_score_id = $request->game_score_id;
		if(empty($cust_id) || empty($game_score_id)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id dan game_score_id tidak boleh kosong!'
                ]
            ];
            return response()->json($data);
		}

		$arr_customer = DB::table("customers")->where("id","=",$cust_id)->first();
		
        $row = DB::table("game_scores")
		->select('game_scores.id as pid', 'club_id_1', 'club_id_2', 'gms_score_1', 'gms_score_2', 'gms_name', 'gms_description', 'gms_view', 'gms_score', 'gms_coin', 'gms_startdate', 'gms_starttime', 'gms_enddate', 'gms_endtime', 'gms_active', 'game_scores.created_at', 'gsm_win')
		//->whereRaw("CONCAT(ts_game_scores.gms_startdate,' ',ts_game_scores.gms_starttime) <= '".date('Y-m-d H:i:s')."'")
		//->whereRaw("CONCAT(ts_game_scores.gms_enddate,' ',ts_game_scores.gms_endtime) < '".date('Y-m-d H:i:s')."'")
		->where('game_scores.id', '=', $game_score_id)
		->where('gms_delete', '=', '0')
		->where('gms_active', '=', '1')
		->orderBy('gms_enddate', 'DESC')
        ->first();
        $results = '';
        if ($row) {
            //foreach ($query_data as $row) {
			if(1==1){
				$club1 = DB::table("clubs")->where("id",'=',$row->club_id_1)->first();
				$club2 = DB::table("clubs")->where("id",'=',$row->club_id_2)->first();
                $user_answer = DB::table("game_score_answers")
							   ->where("gms_id","=",$row->pid)
							   ->where("cust_id","=",$cust_id)
							   ->where("gsa_delete","=","0")
							   ->get();
				if(count($user_answer)>0){
					$sudah_jawab = 1;
				} else {
					$sudah_jawab = 0;
				}
				
				if($row->gms_coin>0){
					$game_score_benefit='Koin';
					$game_score_value = $row->gms_coin;
				} else {
					$game_score_benefit='Skor';
					$game_score_value = $row->gms_score;
				}
				
				// cek logo klub
				$logo_klub_1 = $this->url_server.'upload/club/'.$club1->club_image;
				$logo_klub_2 = $this->url_server.'upload/club/'.$club2->club_image;
				
				// cek total user yang menjawab tebak score
				$cust_answer = DB::table("game_score_answers")->where(['gms_id' => $row->pid, "gsa_delete" => '0'])->get();
				
				if($row->gms_score_1!='' || $row->gms_score_1=='0'){$gms_score_1=$row->gms_score_1;} else {$gms_score_1=NULL;}
				if($row->gms_score_2!='' || $row->gms_score_2=='0'){$gms_score_2=$row->gms_score_2;} else {$gms_score_2=NULL;}
				
				if(1==1){
					$results= [
						'game_score_id' => $row->pid,
						'game_score_name' => $row->gms_name,
						'game_score_description' => $row->gms_description,
						'game_score_view' => count($cust_answer),
						'game_score_benefit' => $game_score_benefit,
						'game_score_value' => (int)$game_score_value,
						'game_score_club_1' => $club1->club_name,
						'game_score_club_1_image' => $logo_klub_1,
						'game_score_club_2' => $club2->club_name,
						'game_score_club_2_image' => $logo_klub_2,
						'game_score_end_date' => $row->gms_enddate.' '.$row->gms_endtime,
						'game_score_user_answer' => $sudah_jawab,
						//'game_score_club_1_value' => ($row->gms_score_1? (int)$row->gms_score_1 : ''),
						//'game_score_club_2_value' => ($row->gms_score_2? (int)$row->gms_score_2 : ''),
						'game_score_club_1_value' => $gms_score_1,
						'game_score_club_2_value' => $gms_score_2,
						'game_score_leaderboard' => $this->leaderBoardscore($row, $cust_id),
					];
				}
            }// end foreach

        } // end if

		$data = [
			'status' => [
				'code' => 200,
				'description' => 'Success'
			],
			'results' => $results,
		];

        return $data;
    }
	
	private function leaderBoardscore($row_games, $cust_id)
	{
		// gsm_win
		$result = array();
		if($row_games->gms_score_1!='' || $row_games->gms_score_2!=''){
			if($row_games->gsm_win>0){$limit=$row_games->gsm_win;} else {$limit=1;}
			$data_leaderboard = DB::table("game_score_answers")
				->select('game_score_answers.id', 'cust_name', 'cust_phone', 'game_score_answers.created_at', 'cust_gender', 'cust_id', 'cust_avatar')
				->leftJoin('customers', 'customers.id', '=', 'game_score_answers.cust_id')
				->where("gms_id","=",$row_games->pid)
				->where("gsa_score_1","=",$row_games->gms_score_1)
				->where("gsa_score_2","=",$row_games->gms_score_2)
				->where("gsa_win","=","1") // hanya pemenang yang di tampilkan
				->orderBy("game_score_answers.id","ASC")
				//->paginate($limit);
				->get();
			if($data_leaderboard){
				foreach($data_leaderboard as $row){

					if(empty($row->cust_avatar)){
						if($row->cust_gender == 'm'){
							$avatar_profile = "https://avatar.iran.liara.run/public/boy?username=scott";
						} else {
							$avatar_profile = "https://avatar.iran.liara.run/public/girl?username=maria";
						}
					} else {
						$avatar_profile = $row->cust_avatar;
					}

					$result[]= [
						'cust_id' => $row->cust_id,
						'cust_name' => $row->cust_name,
						'cust_phone' => ($row->cust_phone? $row->cust_phone : ''),
						'cust_image' => $avatar_profile,
						'cust_reward_coin' => (int)$row_games->gms_coin,
						'cust_date' => $row->created_at,
					];
				}
			}
		} 
		//else {$result = 2;}
		
		return $result;
	}
}
