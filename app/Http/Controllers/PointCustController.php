<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Hash;

class PointCustController extends Controller
{
	protected $table = 'apps_points';
	
	private function checkData($table, $select, $where)
    {
        $affected = DB::table($table)
            ->select($select)
            ->where($where)
            ->get()->first();
        return $affected;
    }
	
	// Cek View data
	public function CekPoint(Request $request)
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
		
        $row_point = DB::table($this->table)
		->selectRaw('ts_apps_points.id as pid, ap_point, cust_name, cust_phone')
		->leftJoin('customers', 'customers.id', '=', 'apps_points.cust_id')
		->where('cust_id', '=', $cust_id)
		->where('ap_delete', '=', '0')
		->orderBy('apps_points.id')
        ->get()->first();
        $results = array();
		if(!empty($row_point)){
        $results[] = [
			'point_id' => $row_point->pid,
			'point' => $row_point->ap_point,
			'cust_name' => $row_point->cust_name,
			'cust_phone' => $row_point->cust_phone,
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
	
	private function generateInvoice()
    {
        $invoice = date('YmdHis').rand(1000,9999);
        if ($this->InvoiceExists($invoice)) {
            return generateInvoice();
        }
        return $invoice;
    }
	
	private function InvoiceExists($number) {
        return DB::table('event_orders')->where(['external_id' => $number])->exists();
    }

	// SAVE PAYMENT ORDERS
	public function savePoint(Request $request) 
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
			
			$id_point = $request->point_id;
			$cust_id = $request->cust_id;
			$point = $request->point;
			$category = $request->category; // in, out
			
			// cek pint master sesuai data  customer
			//$arr_point=$this->checkData('apps_points',['id','ap_point'], ['id' => $id_point]);
			$arr_point = DB::table($this->table)
			->selectRaw('ts_apps_points.id as id, ap_point, cust_name, cust_phone')
			->leftJoin('customers', 'customers.id', '=', 'apps_points.cust_id')
			->where('apps_points.id', '=', $id_point)
			->where('ap_delete', '=', '0')
			->orderBy('apps_points.id')
			->get()->first();
			$results = array();
			if($arr_point){
				// update point master
				if($category == 'in'){
					$point_update = $arr_point->ap_point + $point;
				} else {
					$point_update = $arr_point->ap_point - $point;
				}
				$data_update = [
					'ap_point' => $point_update,
					'updated_at' => date("Y-m-d H:i:s"),
				];
				$get_update = DB::table("apps_points")->where('id', $arr_point->id)->update(array_merge($data_update));
				
				// Save LOG
				$data_event = [
					'id_point' => $id_point,
					'apl_point' => $point,
					'cust_id' => $cust_id,
					'apl_category' => $category,
					'created_at' => date("Y-m-d H:i:s"),
				];
				$get_idsave = DB::table("apps_point_logs")->insertGetId(array_merge($data_event));
				if($get_idsave){
					$results[] = [
						'point_id' => $arr_point->id,
						'point' => $point_update,
						'cust_name' => $arr_point->cust_name,
						'cust_phone' => $arr_point->cust_phone,
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
							'description' => 'Gagal update data'
						]
					];
				}			
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
