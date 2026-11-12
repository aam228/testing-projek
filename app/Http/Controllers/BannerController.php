<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
//use App\Models\Artikel;

class BannerController extends Controller
{
    public function getBanner(Request $request)
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

		$kategori = $request->kategori;
		$has_lookup_ids = false;
		try {
			$has_lookup_ids = \Illuminate\Support\Facades\Schema::hasColumn('banner_apps', 'ba_lookup_ids');
		} catch (\Throwable $e) {}

		$select_fields = 'ts_banner_apps.id as pid, ba_kategori, ba_title, ba_link, ba_file, ba_description, created_at, ba_type, ba_lookup_id' . ($has_lookup_ids ? ', ba_lookup_ids' : '');
        $banner_apps = DB::table('banner_apps')->selectRaw($select_fields)
        //->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
        //->whereNotIn('pl_code', $exc_location)
        //->whereIn('product_locations.st_id', ['1'])
		//->where('pls_qty', '>', '0')
		->where('ba_delete', '=', '0')
		->where('ba_start', '<=', date("Y-m-d"))
		->where('ba_end', '>=', date("Y-m-d"))
		->where(function($w) use($kategori) {
			if (!empty($kategori)) {
				$w->where('ba_kategori', '=', $kategori);
			}
		})
		//->orderBy('created_at', 'desc')
		->orderBy('created_at', 'ASC')
        ->get();
        $results = array();
        if (!empty($banner_apps)) {
            foreach ($banner_apps as $row) {
                if (!empty($row->ba_file)) {
                    //$image_url = 'https://devtop.topsystem.id/upload/event/'.$row->ba_file;
                    $image_url = 'https://topsystem.id/upload/event/'.$row->ba_file;
                } else {
                    $image_url = "";
                }
                if(empty($row->ba_type)){$ba_type='';} else {$ba_type=$row->ba_type;}
                $detail_id=NULL;
                $results[] = [
                    'banner_id' => $row->pid,
                    'kategori' => $row->ba_kategori,
                    'title' => $row->ba_title,
                    'link' => $row->ba_link,
                    'url_banner' => $image_url, //'https://devtop.topsystem.id/upload/event/'.$row->ba_file,
                    'brand' => $this->cek_brands($row->pid),
					'subsubcat' => $this->cek_subsubcat($row->pid),
					'description' => $row->ba_description,
					'type' => $ba_type, // new field
					'detail_id' => $row->ba_lookup_id,
					'detail_ids' => ($has_lookup_ids && !empty($row->ba_lookup_ids)) ? $row->ba_lookup_ids : $row->ba_lookup_id,
                ];
            }
        }
        $data = [
            'status' => [
                'code' => 200,
                'description' => 'Success',
                'button_google' => 0,
            ],
            'results' => $results
        ];
        return response()->json($data);
    }
    
	
	private function cek_brands($banner_id)
	{
		$cek = DB::table('banner_app_brands')
			   ->select("br_id","br_name")
			   ->leftJoin('brands', 'brands.id', '=', 'banner_app_brands.br_id')
			   ->where("bn_id","=",$banner_id)->get();
		$no=0; $brand='';
		foreach($cek as $raw){
			$brand.=$raw->br_name.', ';
		}
		return rtrim($brand,', ');
	}
	
	private function cek_subsubcat($banner_id)
	{
		$cek = DB::table('banner_app_brand_details')
			   ->select("pssc_id","pssc_name")
			   ->leftJoin('banner_app_brands', 'banner_app_brands.id', '=', 'banner_app_brand_details.bnb_id')
			   ->leftJoin('product_sub_sub_categories', 'product_sub_sub_categories.id', '=', 'banner_app_brand_details.pssc_id')
			   ->where("bn_id","=",$banner_id)->groupBy('pssc_id')->get();
		$no=0; $subsubcat='';
		foreach($cek as $raw){
			$subsubcat.=$raw->pssc_name.', ';
		}
		return rtrim($subsubcat,', ');
	}
	
}
