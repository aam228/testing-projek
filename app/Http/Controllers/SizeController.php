<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SizeController extends Controller
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
		
		//$setting = DB::table('setting_apps')->first();
		
		// kategori
		$size_name = $request->size_name;
		
        $query = DB::table('sizes')
		->select('sizes.id', 'sz_name')
		//->leftJoin('users', 'users.id', '=', 'games.user_input')
		->where('sz_delete', '=', "0")
		->where(function($w) use($size_name) {
			/*
			if (!empty($games_search)) {
				$w->where(DB::raw("CONCAT(gm_name, ' ', gm_description)"), 'LIKE', "%$games_search%");
			}
			if(!empty($games_id)){
				$w->where('games.id', '=', $games_id);
			}
			*/
			if (!empty($size_name)) {
				$w->where('sizes.sz_name', '=', $size_name);
			}
		})
		->groupBy("sz_name")
        ->orderBy("sz_type",'ASC')
		->orderBy("sz_sort",'ASC')
		->get();
		//->paginate(5);
		
		$results = array();
        if (!empty($query)) {
            foreach ($query as $row) {
                $results[] = [
                    'size_id' => $row->id,
					'size_name' => $row->sz_name,
                ];
			}
		
        $data = [
            'status' => [
                'code' => 200,
                'description' => 'Success',
            ],
            'results' => $results,
        ];
        } else {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data not found'
				],
				'results' => $results
			];
		}
        return response()->json($data);
    }
}
