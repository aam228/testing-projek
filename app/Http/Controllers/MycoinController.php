<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Hash;

class MycoinController extends Controller
{
	protected $table = 'customer_coins';
	
	private function checkData($table, $select, $where)
    {
        $affected = DB::table($table)
            ->select($select)
            ->where($where)
            ->get()->first();
        return $affected;
    }
	
	// Cek View data
	public function CekCoin(Request $request)
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
		if(empty($cust_id)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id harus diisi'
                ]
            ];
            return response()->json($data);
		}
		
		$row_cust = DB::table("customers")->where("id","=",$cust_id)->first();
		if(!$row_cust){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data customer tidak ditemukan'
                ]
            ];
            return response()->json($data);
		}
		
        $query_data = DB::table($this->table)
		->select('id as pid', 'ccoin_type', 'ccoin_lookup_id', 'ccoin_description', 'ccoin_value', 'created_at')
		//->where('yt_active', '=', '1')
		//->where('yt_delete', '=', '0')
		->where("cust_id","=",$cust_id)
		->orderBy('id','DESC')
        ->get();
        $results = array();
        if (!empty($query_data)) {
            foreach ($query_data as $row) {
				$results[] = [
					'logcoin_id' => $row->pid,
					'logcoin_description' => $row->ccoin_description,
					'logcoin_value' => $row->ccoin_value,
					'logcoin_date' => $row->created_at,
                ];
            }

        $data = [
            'status' => [
                'code' => 200,
                'description' => 'Success'
            ],
            'results' => $results
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
	
	// cek score
	public function CekScore(Request $request)
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
		if(empty($cust_id)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id harus diisi'
                ]
            ];
            return response()->json($data);
		}
		
		$row_cust = DB::table("customers")->where("id","=",$cust_id)->first();
		if(!$row_cust){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data customer tidak ditemukan'
                ]
            ];
            return response()->json($data);
		}
		
        $query_data = DB::table("customer_scores")
		->select('id as pid', 'cscore_type', 'cscore_lookup_id', 'cscore_description', 'cscore_value', 'created_at')
		//->where('yt_active', '=', '1')
		//->where('yt_delete', '=', '0')		
		->where("cust_id","=",$cust_id)
		->orderBy('id','DESC')
        ->get();
        $results = array();
        if (!empty($query_data)) {
            foreach ($query_data as $row) {
				$results[] = [
					'logscore_id' => $row->pid,
					'logscore_description' => $row->cscore_description,
					'logscore_value' => $row->cscore_value,
					'logscore_date' => $row->created_at,
                ];
            }

        $data = [
            'status' => [
                'code' => 200,
                'description' => 'Success'
            ],
            'results' => $results
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
	
	
	// CekCoinscore	
	public function CekCoinscore(Request $request)
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
		if(empty($cust_id)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id harus diisi'
                ]
            ];
            return response()->json($data);
		}
		
		$row_cust = DB::table("customers")->where("id","=",$cust_id)->first();
		if(!$row_cust){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data customer tidak ditemukan'
                ]
            ];
            return response()->json($data);
		}
		
        /*
        $query_coin = DB::table($this->table)
		->select('id as pid', 'ccoin_type', 'ccoin_lookup_id', 'ccoin_description', 'ccoin_value', 'created_at')
		->where("cust_id","=",$cust_id)
		->orderBy('id','DESC')
        ->get();
		*/
		
		$query_score = DB::table("customer_scores")
		->select('id as pid', 'cscore_type', 'cscore_lookup_id', 'cscore_description', 'cscore_value', 'created_at')
		->where("cust_id","=",$cust_id)
		->orderBy('id','DESC')
        ->get();
		
		$query_coin = DB::table($this->table)
			->select(
				'id as pid',
				DB::raw("'in' as logcoin_type"),
				'ccoin_description as logcoin_description',
				'ccoin_value as logcoin_value',
				'created_at as logcoin_date',
				'ccoin_expired as logcoin_expired_date',
				'ccoin_type as logcoin_title'
			)
			->where("cust_id", "=", $cust_id)
			->orderBy('id', 'DESC')
			->get();
		
		$query_coin_belanja = DB::table("customer_coin_withdraws")
			->select(
				'id as pid',
				DB::raw("'out' as logcoin_type"),
				'ccs_description as logcoin_description',
				'ccw_value as logcoin_value',
				'created_at as logcoin_date',
				DB::raw("'' as logcoin_expired_date"),
				DB::raw("'' as logcoin_title")
			)
			->where("cust_id", "=", $cust_id)
			->orderBy('id', 'DESC')
			->get();
		
		// gabungkan kedua hasil query
		$merged = $query_coin->concat($query_coin_belanja);		
		// urutkan berdasarkan tanggal terbaru
		$sorted = $merged->sortByDesc('logcoin_date')->values();
		// Filter dulu sesuai request (biar gak return null)
		if ($request->filter) {
			$sorted = $sorted->filter(function ($item) use ($request) {
				return $item->logcoin_type === $request->filter;
			});
		}		
		// ubah nama field agar sesuai format output yang kamu mau
		$log_coin = array();
		$log_coin = $sorted->map(function ($item) use ($request) {
			/*
			if($request->filter){
				if($request->filter == 'in' && $item->logcoin_type=='in'){ $show_lc = 1; } else
				if($request->filter == 'out' && $item->logcoin_type=='out'){ $show_lc = 1; } else {$show_lc=0;}
			} else { $show_lc=1; }
			*/
			//if($show_lc==1){
			    $logcoin_expired_flag=0;
				if($item->logcoin_expired_date!=''){
					if($item->logcoin_expired_date < date("Y-m-d")){ 
						$logcoin_expired_flag = 1; 
					} else {
						$logcoin_expired_flag=0;
					}
					$logcoin_expired_date = $item->logcoin_expired_date;
				} else { $logcoin_expired_date=''; }
				if($item->logcoin_title == "expired coin"){$logcoin_type='out';} else {$logcoin_type=$item->logcoin_type;}
				return [
					'logcoin_id'          => $item->pid,
					'logcoin_description' => $item->logcoin_description,
					'logcoin_value'       => $item->logcoin_value,
					'logcoin_date'        => $item->logcoin_date,
					//'logcoin_type'        => $item->logcoin_type,
					'logcoin_type'        => $logcoin_type,
					'logcoin_expired_date'=> $logcoin_expired_date,
					'logcoin_expired_flag'=> $logcoin_expired_flag,
				];
			//}
		})->values();
		
        //$log_coin = array();
		$log_score = array();
        if (!empty($query_coin) || !empty($query_score)) {
            /*
            foreach ($query_coin as $row) {
				$log_coin[] = [
					'logcoin_id' => $row->pid,
					'logcoin_description' => $row->ccoin_description,
					'logcoin_value' => $row->ccoin_value,
					'logcoin_date' => $row->created_at,
                ];
            }
            */
			foreach ($query_score as $row_sc) {
				$log_score[] = [
					'logscore_id' => $row_sc->pid,
					'logscore_description' => $row_sc->cscore_description,
					'logscore_value' => $row_sc->cscore_value,
					'logscore_date' => $row_sc->created_at,
                ];
            }

        $data = [
            'status' => [
                'code' => 200,
                'description' => 'Success'
            ],
            'iconcoin' => url("public/upload/coin.png"),
			'logcoin' => $log_coin,
			'iconscore' => url("public/upload/score.png"),
			'logscore' => $log_score
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
	
	// cek informasi coin
	public function infoCoinScore(Request $request)
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
		
		$setting_apps = DB::table("setting_apps")->where("id","=",1)->first();
		$cust_id 	= $request->cust_id;
		$row_cust = DB::table("customers")->where("id","=",$cust_id)->first();
		if(!$row_cust){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data customer tidak ditemukan'
                ]
            ];
            return response()->json($data);
		}
		
		$score_mission = array();
		// cek kelengkapan data profile
		if(empty($row_cust->cust_bday) || empty($row_cust->cust_phone) || empty($row_cust->cust_email) || $row_cust->cust_phone_verify!=1 || $row_cust->cust_email_verify!='1' || empty($row_cust->cust_name)){
			$score_mission[] = [
				'mission_type' => 0,
				'mission_title' => 'Lengkapi Data Diri',
				'mission_description' => 'Isi seluruh data mu secara lengkap serta alamat, dan selesaikan misi lainnya!',
				'mission_icon' => url("public/upload/icon_users.png"),
			];
		}
		// cek game
		$query = DB::table('games')
		->select('games.id', 'gm_name', 'gm_slug', 'gm_description', 'gm_image', 'gm_view', 'gm_time', 'gm_active', 'games.created_at', 'u_name', 'gm_score')
		->leftJoin('users', 'users.id', '=', 'games.user_input')
		->where('games.gm_active', '=', "1")
		->where('games.gm_delete', '=', "0")
		->orderBy("games.created_at",'DESC')
		->get();
		$ngame=0;
		foreach ($query as $row) {
			$cek_usergame = DB::table("game_users")->where("gm_id","=",$row->id)->where("cust_id","=",$cust_id)->first();
				if(empty($cek_usergame)){
					$ngame++;
				}
		}
		if($ngame>0){
			$score_mission[] = [
				'mission_type' => 1,
				'mission_title' => 'Mainkan game',
				'mission_description' => 'Mainkan game untuk menambah score mu, dan selesaikan misi lainnya!',
				'mission_icon' => url("public/upload/icon_games.png"),
			];
		}	
		// cek forum
		$query_data = DB::table('forums')
		->select('forums.id', 'fo_name', 'fo_slug', 'fo_description', 'fo_image', 'fo_view', 'fo_active', 'forums.created_at', 'u_name')
		->leftJoin('users', 'users.id', '=', 'forums.user_input')
		->where('forums.fo_active', '=', "1")
		->where('forums.fo_delete', '=', "0")
        ->orderBy("forums.created_at",'DESC')
		->get();
		$nforum=0;
		foreach ($query_data as $row) {
			$join_cek = DB::table("forum_joins")->where("cust_id","=",$cust_id)->where("fo_id","=",$row->id)->first();
			if(!$join_cek){$nforum++;}
		}
		if($nforum>0){
			$score_mission[] = [
				'mission_type' => 2,
				'mission_title' => 'Join forum',
				'mission_description' => 'Segera join forum untuk menambah score mu, dan selesaikan misi lainnya!',
				'mission_icon' => url("public/upload/icon_forums.png"),
			];
		}
		
		//$expired_coin = date("Y")."-12-31";
		
		// cek order terbaru
		$arr_orders = DB::table("pos_transactions")
			->where("cust_id","=",$cust_id)
			->whereNotIn('pos_status', ['WAITING FOR CONFIRMATION','CANCEL', 'UNPAID'])
			->orderBy("id","DESC")
			->first();
		if($arr_orders){
			//$expired_coin = substr($arr_orders->created_at,0,10);
			$date_order = substr($arr_orders->created_at,0,10);
			//$new_date = strtotime('+ 1 year', $date_order);
			$expired_coin = date('Y-m-d', strtotime($date_order. ' +1 year'));
		} else {
			$expired_coin = date("Y")."-12-31";
		}
		
        $data = [
            'status' => [
                'code' => 200,
                'description' => 'Success'
            ],
			'coin' => [
				'coin_icon' => url("public/upload/coin.png"),
				'coin_value' => (int)$row_cust->cust_coin,
				'coin_expired' => $expired_coin,
				'coin_description' => $setting_apps->coin_info,
				'coin_minimal' => 20000,
			],
			'score' => [
				'score_icon' => url("public/upload/score.png"),
				'score_value' => (int)$row_cust->cust_score,
				'score_description' => $setting_apps->score_info,
				'score_mission' => $score_mission,
			],
        ];		
		return response()->json($data);
	}
}
