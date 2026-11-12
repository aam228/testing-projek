<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
//use App\Models\Artikel;

class RattingAppsController extends Controller
{
	//protected $url_server = 'https://topsystem.id/';
	protected $table = 'rattings';
	
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
        if (!$cust_id) {
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id tidak boleh kosong'
                ]
            ];
            return response()->json($data);
        }
		$arr_customer = DB::table("customers")->where("id","=",$cust_id)->first();
        if (!$arr_customer) {
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data customer dengan ID '.$cust_id.' tidak ditemukan...!'
                ]
            ];
            return response()->json($data);
        }

        $arr_data = DB::table($this->table)->where("cust_id","=",$cust_id)->first();
		if($arr_data){
			$data = [
				'status' => [
					'code' => 200,
					'ratting_apps' => 1,
					'description' => 'Sudah memberikan ratting aplikasi'
				],
			];
		} else {
			$data = [
				'status' => [
					'code' => 200,
					'ratting_apps' => 0,
					'description' => 'Belum memberikan ratting aplikasi'
				]
			];
		}
        return response()->json($data);
    }
    
	// add alamat user
	public function storeData(Request $request)
    {
        $cust_id = $request->cust_id;
		$rat_value = $request->rat_value;
		$rat_description = $request->rat_description;
		
		$arr_ratting = DB::table($this->table)->where("cust_id","=",$cust_id)->first();
		if($arr_ratting){
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Sudah pernah memberikan ratting'
				]
			];
			return response()->json($data);
		}
		
        $data_save = [
			'cust_id' => $cust_id,
			'rat_value' => $rat_value,
			'rat_description' => $rat_description,
			'created_at' => date("Y-m-d H:i:s"),
		];
		$saves = DB::table($this->table)->insertGetId(array_merge($data_save));
		// cek customer
		$arr_customer = DB::table("customers")->where("id","=",$cust_id)->first();
		if($saves){
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Ratting berhasil di simpan'
				]
			];
		} else {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Gagal menyimpan ratting'
				]
			];
		}
        return response()->json($data);
    }
	
}
