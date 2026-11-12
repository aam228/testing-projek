<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
//use App\Models\Artikel;

class SettingController extends Controller
{
    public function getSetting(Request $request)
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

		//$st_name = $request->store_name;
		
        $setting_apps = DB::table('setting_apps')->first();
        $results = array();
        if (!empty($setting_apps)) {
            //foreach ($setting_apps as $row) {
                $results[] = [
                    'setting_id' => $setting_apps->id,
                    'maintenance' => $setting_apps->flag_maintenance,
					'privacy_policy' => $setting_apps->privacy_policy,
					'terms' => $setting_apps->terms,
					'about_us' => $setting_apps->about_us,
					'video' => url('public/upload/jingle/'.$setting_apps->about_video),
					'banner_map' => url('public/upload/'.$setting_apps->about_banner_maps),
                    'media_sosial' => [
						['media_sosial_name' => 'INSTAGRAM',
						 'media_sosial_icon' => url('public/upload/medsos/logo_ig.png'),
						 'media_sosial_url' => 'https://www.instagram.com/topscore',
						],
						['media_sosial_name' => 'YOUTUBE',
						 'media_sosial_icon' => url('public/upload/medsos/logo_yt.png'),
						 'media_sosial_url' => 'https://www.youtube.com/@topscoretv',
						],
					],
					'phone' => [
						['phone_number' => $setting_apps->about_phone,
						 'phone_icon' => url('public/upload/medsos/logo_phone.png'),
						]
					]
                ];
            //}
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

