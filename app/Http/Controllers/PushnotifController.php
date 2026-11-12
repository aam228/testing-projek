<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PushnotifController extends Controller
{
	//protected $url_server = 'https://topsystem.id/';
	protected $table = "push_notifications";
	
	// add data
	public function storeData(Request $request)
    {
		/*
        $data = [
            'pn_type' => $request->pn_type,
            'pn_lookup_id' => $request->pn_lookup_id,
            'pn_name' => $request->pn_name,
            'pn_description' => $request->pn_description,
            'pn_date' => $request->pn_date,
            'pn_time' => $request->pn_time,
			'u_id' => Auth::user()->id,
			//'created_at' => date("Y-m-d H:i:s"),
        ];
		*/
        $mode = $request->mode;
        $data_id = $request->data_id;
		$data_order = json_decode($request->data_order);
		//echo $data_order->pn_type;
		//exit();
		
        $data = [
            'pn_type' => $data_order->pn_type,
            'pn_lookup_id' => $data_order->pn_lookup_id,
            'pn_name' => $data_order->pn_name,
            'pn_description' => $data_order->pn_description,
            'pn_date' => $data_order->pn_date,
            'pn_time' => $data_order->pn_time,
			'u_id' => $data_order->u_id,
        ];
		
		//$arr_customer = DB::table("customers")->where("id","=",$cust_id)->first();
        if ($mode == 'edit') {
			$arr_pushnotif = DB::table($this->table)->where("id","=",$data_id)->first();
			if($arr_pushnotif){
				$updated = [
					'updated_at' => date('Y-m-d H:i:s')
				];
				$saves = DB::table($this->table)->where('id', $data_id)->update(array_merge($data,$updated));
				if($saves){
				//$notif = 'success_edit';
					$r['status'] = "200";
					$r['message'] = "Berhasil menyimpan data";
				} else {
					$r['status'] = "401";
					$r['message'] = "Gagal melakukan update data";
				}
			} else {
					$r['status'] = "402";
					$r['message'] = "Data tidak ditemukan!";
			}
        } else 
		if ($mode == 'add') {
			$created = [
				'created_at' => date('Y-m-d H:i:s')
			];
            $saves = DB::table($this->table)->insertGetId(array_merge($data,$created));
			//$notif = 'success_add';
			if($saves){
			$r['status'] = "200";
			$r['message'] = "Berhasil menyimpan data";
			} else {
			$r['status'] = "401";
			$r['message'] = "Gagal menyimpan data";
			}
        } else 
		{
			$r['status'] = "400";
			$r['message'] = "Data tidak ditemukan";
		}
        return response()->json($r);
    }
	
	// delete alamat
	public function deleteData(Request $request)
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

        $data_id = $request->data_id;
		$arr_pushnotif = DB::table($this->table)->where('id', $data_id)->first();
		if(empty($arr_pushnotif)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data tidak ditemukan'
                ]
            ];
            return response()->json($data);
		}
		
		// lakukan update data...	
		$updateds = [
			'pn_delete' => '1',
			'updated_at' => date('Y-m-d H:i:s'),
		];
		DB::table($this->table)->where('id', $data_id)->update($updateds);
		
		if($updateds){
            $data = [
                'status' => [
                    'code' => 200,
                    'description' => 'Data berhasil di hapus'
                ]
            ];
            return response()->json($data);
		} else {
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data gagal di hapus'
                ]
            ];
            return response()->json($data);
		}
    }
	
	// update status cron 
	public function statusData(Request $request)
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

        $data_id = $request->data_id;
		$arr_pushnotif = DB::table($this->table)->where('id', $data_id)->first();
		if(empty($arr_pushnotif)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data tidak ditemukan'
                ]
            ];
            return response()->json($data);
		}
		
		// lakukan update status cron
		$updateds = [
			'pn_status' => '1',
			'updated_at' => date('Y-m-d H:i:s'),
		];
		DB::table($this->table)->where('id', $data_id)->update($updateds);
		
		if($updateds){
            $data = [
                'status' => [
                    'code' => 200,
                    'description' => 'Status cron berhasil di update'
                ]
            ];
            return response()->json($data);
		} else {
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Status cron gagal di update'
                ]
            ];
            return response()->json($data);
		}
    }
    
    // status data onesignal 
	public function onesignalstatusData(Request $request)
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

        $data_id = $request->data_id;
		$arr_pushnotif = DB::table("push_notification_onesignals")->where('id', $data_id)->first();
		if(empty($arr_pushnotif)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data tidak ditemukan'
                ]
            ];
            return response()->json($data);
		}
		
		// lakukan update status cron
		$updateds = [
			'pno_status' => '1',
			'updated_at' => date('Y-m-d H:i:s'),
		];
		DB::table("push_notification_onesignals")->where('id', $data_id)->update($updateds);
		
		if($updateds){
            $data = [
                'status' => [
                    'code' => 200,
                    'description' => 'Status cron berhasil di update'
                ]
            ];
            return response()->json($data);
		} else {
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Status cron gagal di update'
                ]
            ];
            return response()->json($data);
		}
    }
	
}
