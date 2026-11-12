<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
//use App\Models\Artikel;

class StoreController extends Controller
{
    public function getStore(Request $request)
    {
        $client_id = $request->header('client_id');
        $api_key = $request->header('api_key');

        /*
		$validate = DB::table('client_credentials')
        ->where([
            'client_id' => $client_id,
            'client_api' => $api_key
        ])->exists();
        if (!$validate) {
            $data = [
                'status' => [
                    'code' => 401,
                    'description' => 'We could not identify your credential id and api key'
                ]
            ];
            return response()->json($data);
        }
		*/

		$st_name = $request->store_name;
		
        $banner_apps = DB::table('stores')
		->selectRaw('ts_stores.id as pid, st_name, st_phone, st_email, zip_id, created_at, area_id_biteship, st_lat, st_lang, st_address, st_maps')
		->where('st_apps', '=', '1')
		->where('st_delete', '=', '0')
		->where(function($w) use($st_name) {
			if (!empty($st_name)) {
				$w->where('st_name', '=', $st_name);
			}
		})
        ->get();
        $results = array();
        if (!empty($banner_apps)) {
            foreach ($banner_apps as $row) {
                $results[] = [
                    'store_id' => $row->pid,
                    'store_name' => $row->st_name,
                    'store_email' => $row->st_email,
                    'store_phone' => $row->st_phone,
                    'store_address' => $row->st_address,
					'store_lat' => $row->st_lat,
					'store_lang' => $row->st_lang,
                    'store_zip' => $row->zip_id,
					'area_id_biteship' => $row->area_id_biteship,
					'created_at' => $row->created_at,
					'maps_url' => $row->st_maps,
                ];
            }
        }
        $data = [
            'status' => [
                'code' => 200,
                'description' => 'Success'
            ],
            'results' => $results
        ];
        return response()->json($data);
    }
    
	
}
