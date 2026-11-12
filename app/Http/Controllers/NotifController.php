<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Hash;

class NotifController extends Controller
{
	protected $table = 'apps_notif';
	
	private function checkData($table, $select, $where)
    {
        $affected = DB::table($table)
            ->select($select)
            ->where($where)
            ->get()->first();
        return $affected;
    }
	
	// Cek View data
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
		
        $query_data = DB::table($this->table)
		->selectRaw('ts_apps_notif.id as pid, id_lookup, type_data, an_title, an_description, an_read, created_at')
		/*
		->leftJoin('products', 'products.id', '=', 'favorit.p_id')
		*/
		->where('cust_id', '=', $cust_id)		
		->orderBy('created_at','desc')
        ->get();
        $results = array();
        if (!empty($query_data)) {
            foreach ($query_data as $row) {
                // jika event 
				if($row->type_data == "event"){
					$event_arr = DB::table("event_orders")->where("id","=",$row->id_lookup)->first();
					if($event_arr){$ext_id = $event_arr->external_id;} else {$ext_id = $row->id_lookup;}
				} else {
					$ext_id = $row->id_lookup;
				}
				$results[] = [
					'notif_id' => $row->pid,
					'lookup_id' => $row->id_lookup,
					'external_id' => $ext_id,
					'type_data' => $row->type_data,
					'title' => $row->an_title,
					'description' => $row->an_description,
					'read' => $row->an_read,
					'created_at' => $row->created_at,
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

	// SAVE PAYMENT ORDERS
	public function updateRead(Request $request) 
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
			
			$notif_id = $request->notif_id;
			$cust_id 	= $request->cust_id;
			
			$arr_data = DB::table($this->table)
			->selectRaw('ts_apps_notif.id as pid, id_lookup, type_data, an_title, an_description, an_read')
			->where('cust_id', '=', $cust_id)
			->where('id', '=', $notif_id)
			->orderBy('created_at','desc')
			->get()->first();
			$results = array();
			if (!empty($arr_data)) {
			$update_pos = DB::table($this->table)->where('id', '=', $notif_id)->update(['an_read' => 1]);
			if($update_pos){ $status_absen=1; } else {
				if($arr_data->an_read==1){
					$status_absen=1;
				} else {
					$status_absen=0;
				}
			}
			$results[] = [
				'lookup_id' => $arr_data->id_lookup,
				'type_data' => $arr_data->type_data,
				'title' => $arr_data->an_title,
				'description' => $arr_data->an_description,
				'read' => $status_absen,
			];
	
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
    
    // updateReadall
	public function updateReadall(Request $request) 
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
			
			//$notif_id = $request->notif_id;
			$cust_id  = $request->cust_id;
			
			$arr_data = DB::table($this->table)
			->selectRaw('ts_apps_notif.id as pid, id_lookup, type_data, an_title, an_description, an_read')
			->where('cust_id', '=', $cust_id)
			->orderBy('created_at','desc')
			->get();
			$results = array();
			if (!empty($arr_data)) {
			$update_pos = DB::table($this->table)->where('cust_id', '=', $cust_id)->update(['an_read' => 1]);
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success'
				],
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

}
