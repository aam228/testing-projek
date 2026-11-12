<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Hash;

class YoutubeController extends Controller
{
	protected $table = 'youtube';
	
	private function checkData($table, $select, $where)
    {
        $affected = DB::table($table)
            ->select($select)
            ->where($where)
            ->get()->first();
        return $affected;
    }
	
	// Cek View data
	public function CekVideo(Request $request)
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
			'ta_page_access' => "Youtube",
			'ta_description' => "Mengakses laman youtube",
			//'ta_lookup_id' => $request->product_id,
			'created_at' => date("Y-m-d H:i:s"),
			'ip_address' => $request->ip(),
		];
		$save_log = DB::table("traffic_apps")->insertGetId(array_merge($data_log));
        
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
		->selectRaw('ts_youtube.id as pid, yt_title, yt_url, yt_description, created_at, yt_view')
		->where('yt_active', '=', '1')
		->where('yt_delete', '=', '0')		
		->orderBy('id','DESC')
        ->get();
        $results = array();
        if (!empty($query_data)) {
            foreach ($query_data as $row) {
				// check product stock
				//$arr_pst = $this->checkData('product_stocks','id', ['p_id' => $row->product_id]);
				$row_views = DB::table("youtube_views")->where("yt_id","=",$row->pid)->where("cust_id","=",$cust_id)->first();
				if($row_views){
					$flag_view = 1;
				} else {
					$flag_view = 0;
				}
				
				$youtube_url_id = explode("=",$row->yt_url);
				$youtube_image = "https://img.youtube.com/vi/".$youtube_url_id[1]."/sddefault.jpg";
                $results[] = [
					'youtube_id' => $row->pid,
					'youtube_title' => $row->yt_title,
					'youtube_url' => $row->yt_url,
					'youtube_url_code' => $youtube_url_id[1],
                    'youtube_description' => $row->yt_description,
					'youtube_viewers' => $row->yt_view,
					'youtube_view' => $flag_view,
					'youtube_image' => $youtube_image,
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
	
	// update viewers
	public function updateViewers(Request $request)
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

		$cust_id 		= $request->cust_id;
		$youtube_id 	= $request->youtube_id;
		if(empty($cust_id) || empty($youtube_id)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id dan youtube_id harus diisi'
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
		
		$row_yt=DB::table($this->table)->where("id","=",$youtube_id)->where('yt_active', '=', '1')->where('yt_delete', '=', '0')->first();
		if(!$row_yt){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data video tidak ditemukan'
                ]
            ];
            return response()->json($data);
		} else {
			// update count view video
			$updated_video = [
				'yt_view' => ($row_yt->yt_view+1),
			];
			DB::table($this->table)->where('id', $row_yt->id)->update($updated_video);
		}
		
        $row_views = DB::table("youtube_views")->where("yt_id","=",$row_yt->id)->where("cust_id","=",$cust_id)->first();
		// jika belum pernah melihat video maka berikan nilai score pada customers *****
        if (!$row_views) {
			$data = [
				'yt_id' => $row_yt->id,
				'cust_id' => $cust_id,
				'yv_view' => 1,
				'created_at' => date("Y-m-d H:i:s"),
			];
			DB::table("youtube_views")->insertGetId(array_merge($data));
			
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success'
				]
			];
        } else {
			$updated_cust = [
				'yv_view' => ($row_views->yv_view+1),
			];
			DB::table("youtube_views")->where('id', $row_views->id)->update($updated_cust);
			
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success'
				]
			];
			/*
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data not found'
				]
			];
			*/
		}
        return response()->json($data);
    }

}
