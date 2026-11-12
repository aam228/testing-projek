<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Hash;

class HelpcenterController extends Controller
{
	protected $table = 'helpcenters';
	
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

		$category 	= $request->category;
		
        $query_data = DB::table($this->table)
		->select('*')
		->where('hc_delete', '=', '0')		
		->orderBy('hc_category','ASC')
		->orderBy('hc_title','ASC')
        ->get();
        $results = array();
        if (!empty($query_data)) {
            foreach ($query_data as $row) {
				$results[] = [
					'faq_id' => $row->id,
					'faq_category' => $row->hc_category,
					'faq_title' => $row->hc_title,
					'faq_description' => $row->hc_description,
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

}
