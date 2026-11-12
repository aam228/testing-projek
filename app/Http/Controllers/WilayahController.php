<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
//use App\Models\Artikel;

class WilayahController extends Controller
{
    public function getProvince(Request $request)
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

		$province = $request->province;
		
        $banner_apps = DB::table('zip_codes')->selectRaw('zc_country, zc_province')
		->where('lj_delete', '=', '0')
		->where(function($w) use($province) {
			if (!empty($province)) {
				$w->where('zc_province', '=', $province);
			}
		})
		->groupBy('zc_province')
		->orderBy('zc_province')
        ->get();
        $results = array();
        if (!empty($banner_apps)) {
            foreach ($banner_apps as $row) {
                $results[] = [
                    'province' => $row->zc_province,
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
	
	// Kota / Kabupaten
	public function getCity(Request $request)
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

		$province = $request->province;
		$city = $request->city;
		
        $banner_apps = DB::table('zip_codes')->selectRaw('zc_province, zc_city')
		->where('zc_province', '=', $province)
		->where('lj_delete', '=', '0')
		->where(function($w) use($city) {
			if (!empty($city)) {
				$w->where('zc_city', '=', $city);
			}
		})
		->groupBy('zc_city')
		->orderBy('zc_city')
        ->get();
        $results = array();
        if (!empty($banner_apps)) {
            foreach ($banner_apps as $row) {
                $results[] = [
                    'province' => $row->zc_province,
					'city' => $row->zc_city,
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
	
	// Kecamatan
	public function getDistrict(Request $request)
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

		$province = $request->province; // required
		$city = $request->city; // required
		$district = $request->district;
		
        $banner_apps = DB::table('zip_codes')->selectRaw('zc_province, zc_city, zc_district')
		->where('zc_province', '=', $province)
		->where('zc_city', '=', $city)
		->where('lj_delete', '=', '0')
		->where(function($w) use($district) {
			if (!empty($district)) {
				$w->where('zc_district', '=', $district);
			}
		})
		->groupBy('zc_district')
		->orderBy('zc_district')
        ->get();
        $results = array();
        if (!empty($banner_apps)) {
            foreach ($banner_apps as $row) {
                $results[] = [
                    'province' => $row->zc_province,
					'city' => $row->zc_city,
					'district' => $row->zc_district,
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
	
	// Kecamatan
	public function getSubdistrict(Request $request)
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

		$province = $request->province; // required
		$city = $request->city; // required
		$district = $request->district; // required
		$subdistrict = $request->subdistrict;
		
        $banner_apps = DB::table('zip_codes')->selectRaw('id, zc_province, zc_city, zc_district, zc_subdistrict, zc_zip, zc_code')
		->where('zc_province', '=', $province)
		->where('zc_city', '=', $city)
		->where('zc_district', '=', $district)
		->where('lj_delete', '=', '0')
		->where(function($w) use($subdistrict) {
			if (!empty($subdistrict)) {
				$w->where('zc_subdistrict', '=', $subdistrict);
			}
		})
		//->groupBy('zc_subdistrict')
		->orderBy('zc_subdistrict')
        ->get();
        $results = array();
        if (!empty($banner_apps)) {
            foreach ($banner_apps as $row) {
                $results[] = [
                    'id' => $row->id,
					'province' => $row->zc_province,
					'city' => $row->zc_city,
					'district' => $row->zc_district,
					'subdistrict' => $row->zc_subdistrict,
					'zip' => $row->zc_zip,
					'zip_code' => $row->zc_code,
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
    
    // cek wilayah...
	public function getLocations(Request $request)
    {
		$client_id = $request->client_id;
        $api_key = $request->api_key;
		/*
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
		*/

		$province = $request->province; // required
		$city = $request->city; // required
		$district = $request->district; // required
		$subdistrict = $request->subdistrict;
		$type_data = $request->type_data;
		$kode_pos = $request->kode_pos;
		
		if($type_data=='kelurahan'){
			$banner_apps = DB::table('zip_codes')->selectRaw('id, zc_province, zc_city, zc_district, zc_subdistrict, zc_zip, zc_code')
			->where('zc_province', '=', $province)
			->where('zc_city', '=', $city)
			->where('zc_district', '=', $district)
			->where('lj_delete', '=', '0')
			->where(function($w) use($subdistrict) {
				if (!empty($subdistrict)) {
					$w->where('zc_subdistrict', '=', $subdistrict);
				}
			})
			//->groupBy('zc_subdistrict')
			->orderBy('zc_subdistrict')
			->get();
		}
		// kecamatan
		if($type_data=='kecamatan'){
			$banner_apps = DB::table('zip_codes')->selectRaw('id, zc_province, zc_city, zc_district, zc_subdistrict, zc_zip, zc_code')
			->where('zc_province', '=', $province)
			->where('zc_city', '=', $city)
			//->where('zc_district', '=', $district)
			->where('lj_delete', '=', '0')
			->where(function($w) use($district) {
				if (!empty($district)) {
					$w->where('zc_district', '=', $district);
				}
			})
			->groupBy('zc_district')
			->orderBy('zc_district')
			->get();
		}
		// kabutapen/kota
		if($type_data=='kota'){
			$banner_apps = DB::table('zip_codes')->selectRaw('id, zc_province, zc_city, zc_district, zc_subdistrict, zc_zip, zc_code')
			->where('zc_province', '=', $province)
			//->where('zc_city', '=', $city)
			//->where('zc_district', '=', $district)
			->where('lj_delete', '=', '0')
			->where(function($w) use($city) {
				if (!empty($city)) {
					$w->where('zc_city', '=', $city);
				}
			})
			->groupBy('zc_city')
			->orderBy('zc_city')
			->get();
		}
		// propinsi
		if($type_data=='propinsi'){
			$banner_apps = DB::table('zip_codes')->selectRaw('id, zc_province, zc_city, zc_district, zc_subdistrict, zc_zip, zc_code')
			->where('lj_delete', '=', '0')
			->where(function($w) use($province) {
			if (!empty($province)) {
					$w->where('zc_province', '=', $province);
				}
			})
			->groupBy('zc_province')
			->orderBy('zc_province')
			->get();
		}
		
		// cek by kode pos data
		if($type_data=='kode_pos'){
			$banner_apps = DB::table('zip_codes')->selectRaw('id, zc_province, zc_city, zc_district, zc_subdistrict, zc_zip, zc_code')
			->where('lj_delete', '=', '0')
			->where(function($w) use($kode_pos) {
			if (!empty($kode_pos)) {
					$w->where('zc_zip', '=', $kode_pos);
				}
			})
			->groupBy('zc_zip')
			->orderBy('zc_zip')
			->get();
		}
		
        $results = array();
        if (!empty($banner_apps)) {
            foreach ($banner_apps as $row) {
                $results[] = [
                    'id' => $row->id,
					'type_data' => $type_data,
					'province' => $row->zc_province,
					'city' => $row->zc_city,
					'district' => $row->zc_district,
					'subdistrict' => $row->zc_subdistrict,
					'zip' => $row->zc_zip,
					'zip_code' => $row->zc_code,
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
    
    // get kode pos by nama kecamatan
	public function getsearchZip(Request $request)
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

		$city = $request->district; // required
		if(empty($city)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field city tidak boleh kosong'
                ]
            ];
            return response()->json($data);
		}
		
        $q_city = DB::table('zip_codes')->selectRaw('id, zc_province, zc_city, zc_district, zc_subdistrict, zc_zip')
		//->where('zc_city', '=', $city)
		->where('lj_delete', '=', '0')
		->where(function($w) use($city) {
			if (!empty($city)) {
				//$w->where(DB::raw("CONCAT(zc_city, ' ', zc_district)"), 'LIKE', "%$city%");
				$w->where(DB::raw("CONCAT(zc_district)"), 'LIKE', "%$city%");
			}
		})
		//->groupBy('zc_district')
		->orderBy('zc_province')
		->orderBy('zc_city')
		->orderBy('zc_district')
		->orderBy('zc_subdistrict')
        ->get();
        $results = array();
        if (!empty($q_city)) {
            foreach ($q_city as $row) {
                $results[] = [
                    'id' => $row->id,
					'province' => $row->zc_province,
					'city' => $row->zc_city,
					'district' => $row->zc_district,
					'subdistrict' => $row->zc_subdistrict,
					'zip' => $row->zc_zip,
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
    
    // group by nama kecamatan
	public function getallDistrict(Request $request)
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
		
        $q_city = DB::table('zip_codes')->selectRaw('id, zc_province, zc_city, zc_district')
		->where('lj_delete', '=', '0')
		->groupBy('zc_district')
		->orderBy('zc_province')
		->orderBy('zc_city')
		->orderBy('zc_district')
		->orderBy('zc_subdistrict')
        ->get();
        $results = array();
        if (!empty($q_city)) {
            foreach ($q_city as $row) {
                $results[] = [
					'province' => $row->zc_province,
					'city' => $row->zc_city,
					'district' => $row->zc_district,
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
    
    // load all kode pos
	public function getallZip(Request $request)
    {
        //return '';
        return response()->json([
                'status'  => 'error',
                'message' => 'Bad Request: data tidak valid',
            ], 400);
        
        
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
		
        $q_city = DB::table('zip_codes')->selectRaw('id, zc_province, zc_city, zc_district, zc_subdistrict, zc_zip')
		->where('lj_delete', '=', '0')
		->orderBy('zc_province')
		->orderBy('zc_city')
		->orderBy('zc_district')
		->orderBy('zc_subdistrict')
        ->get();
        $results = array();
        if (!empty($q_city)) {
            foreach ($q_city as $row) {
                $results[] = [
					'id' => $row->id,
					'province' => $row->zc_province,
					'city' => $row->zc_city,
					'district' => $row->zc_district,
					'subdistrict' => $row->zc_subdistrict,
					'zip' => $row->zc_zip,
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
    
    // cek kode pos by koordinat menggunakan google API
	public function googlePoint(Request $request)
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

		$latitude = $request->latitude;
		$longitude = $request->longitude;
		$key_google = config('services.google.maps_key');

		$curl	= curl_init();
		$url_api = "https://maps.googleapis.com/maps/api/geocode/json?latlng=".$latitude.",".$longitude."&key=".$key_google;
		// Google Maps key is provided through GOOGLE_MAPS_KEY.
		
		$data = [];
		
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
		curl_setopt($curl, CURLOPT_URL, $url_api);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
	
		$result = curl_exec($curl);
		curl_close($curl);
		$getdata = json_decode($result);
		//return $getdata;
		
		
		$result_details = array();
		if($getdata->status == "OK"){
			$no=0;
			foreach($getdata->results as $result_data){
				$no++;
				if(1==1){
					//echo $result_data->address_components.' == ';
					foreach($result_data->address_components as $component){
						//echo $component->types['0'].' == ';
						if($component->types['0'] == "country"){
							$result_details['country'] = $component->long_name;
						}
						if($component->types['0'] == "administrative_area_level_1"){
							$result_details['province'] = $component->long_name;
						}
						if($component->types['0'] == "administrative_area_level_2"){
							$result_details['city'] = $component->long_name;
						}
						if($component->types['0'] == "administrative_area_level_3"){
							$result_details['district'] = $component->long_name;
						}
						if($component->types['0'] == "administrative_area_level_4"){
							$result_details['subdistrict'] = $component->long_name;
						}
						if($component->types['0'] == "postal_code"){
							$result_details['zip'] = $component->long_name;
						}
					}
				}
			}
		}
		
        $data_json = [
            'status' => [
                'code' => 200,
                'description' => 'Success'
            ],
            'results' => $result_details
        ];
        return response()->json($data_json);
	}
	
	// get koordinat dari wilayah (propinsi, kota, kecamatan, kelurahan, kode pos)
	public function googleZip(Request $request)
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

		$address = urlencode($request->address);
		$key_google = config('services.google.maps_key');

		$curl	= curl_init();
		$url_api = "https://maps.googleapis.com/maps/api/geocode/json?address=".$address."&key=".$key_google;
		
		$data = [];
		
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
		curl_setopt($curl, CURLOPT_URL, $url_api);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
	
		$result = curl_exec($curl);
		curl_close($curl);
		$getdata = json_decode($result);
		//return $getdata;
		
		
		$result_details = array();
		if($getdata->status == "OK"){
			$no=0;
			foreach($getdata->results as $result_data){
				$no++;
				if(1==1){
					//echo $result_data->address_components.' == ';
					foreach($result_data->address_components as $component){
						//echo $component->types['0'].' == ';
						if($component->types['0'] == "country"){
							$result_details['country'] = $component->long_name;
						}
						/*
						if($component->types['0'] == "administrative_area_level_1"){
							$result_details['province'] = $component->long_name;
						}
						if($component->types['0'] == "administrative_area_level_2"){
							$result_details['city'] = $component->long_name;
						}
						if($component->types['0'] == "administrative_area_level_3"){
							$result_details['district'] = $component->long_name;
						}
						if($component->types['0'] == "administrative_area_level_4"){
							$result_details['subdistrict'] = $component->long_name;
						}
						*/
						if($component->types['0'] == "postal_code"){
							$result_details['zip'] = $component->long_name;
						}
					}
				}
				$result_details['lat'] = $result_data->geometry->location->lat;
				$result_details['lang'] = $result_data->geometry->location->lng;
			}
		}
		
        $data_json = [
            'status' => [
                'code' => 200,
                'description' => 'Success'
            ],
            'results' => $result_details
        ];
        return response()->json($data_json);
	}
	
	// get wilayah by nama tempat
	public function googlePlace(Request $request)
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

		$address = urlencode($request->address);
		$key_google = config('services.google.maps_key');

		$curl	= curl_init();
		//$url_api = "https://maps.googleapis.com/maps/api/geocode/json?address=".$address."&key=".$key_google;
		$url_api = "https://maps.googleapis.com/maps/api/place/textsearch/json?query=".$address."&key=".$key_google;
		
		$data = [];
		
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
		curl_setopt($curl, CURLOPT_URL, $url_api);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
	
		$result = curl_exec($curl);
		curl_close($curl);
		$getdata = json_decode($result);
		//return $getdata;
		
		
		$result_details = array();
		if($getdata->status == "OK"){
			$no=0;
			foreach($getdata->results as $result_data){
				$no++;
				$result_details[] = [
					'place_id'	=> $result_data->place_id,
					'name' 		=> $result_data->name,
					'address' 	=> $result_data->formatted_address,
					'lat' 		=> $result_data->geometry->location->lat,
					'lang' 		=> $result_data->geometry->location->lng,
				];
			}
		}
		
        $data_json = [
            'status' => [
                'code' => 200,
                'description' => 'Success'
            ],
            'results' => $result_details
        ];
        return response()->json($data_json);
	}
}
