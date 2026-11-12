<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
//use App\Models\Artikel;

class CategoryController extends Controller
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

		//$kategori = $request->kategori;
		
        $banner_apps = DB::table('product_sub_categories')->selectRaw('ts_product_sub_categories.id as pid, psc_name, psc_description, psc_slug, psc_banner, created_at')
        //->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
        //->whereNotIn('pl_code', $exc_location)
        //->whereIn('product_locations.st_id', ['1'])
		//->where('pls_qty', '>', '0')
		->where('id', '!=', '5')
		->where('psc_delete', '=', '0')
		/*->where(function($w) use($kategori) {
			if (!empty($kategori)) {
				$w->where('ba_kategori', '=', $kategori);
			}
		})*/
        ->get();
        $results = array();
        if (!empty($banner_apps)) {
            foreach ($banner_apps as $row) {
                /*if (!empty($row->p_main_image)) {
                    $image_url = "https://topsystem.id/api/product/600/";
                    $image[] = $row->p_main_image;
                }*/
				$check_subkat = DB::table('product_sub_sub_categories')->select('pssc_name')
                ->where('product_sub_sub_categories.psc_id', '=', $row->pid)
				->get();
				$detail_cat = array();
				if (!empty($check_subkat)) {
					foreach ($check_subkat as $row_sub) {
						$detail_cat[] = $row_sub->pssc_name;
					}
				}
				$url_banner = "https://topsystem.id/public/upload/category/".$row->psc_banner;

                $results[] = [
                    'category_id' => $row->pid,
                    'title' => $row->psc_name,
                    'slug' => $row->psc_slug,
                    'url_banner' => $url_banner, //$row->psc_banner,
					'description' => $row->psc_description,
					'category_detail' => $detail_cat,
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
    
    // sub sub kategogi
	public function getSubcat(Request $request)
    {
        $client_id = $request->header('client_id');
        $api_key = $request->header('api_key');

        $banner_apps = DB::table('product_sub_sub_categories')->selectRaw('ts_product_sub_sub_categories.id as pid, pssc_name, pssc_description, pssc_logo, created_at')
		->where('pssc_view_apps', '=', '1')
		->where('pssc_delete', '=', '0')
		/*->where(function($w) use($kategori) {
			if (!empty($kategori)) {
				$w->where('ba_kategori', '=', $kategori);
			}
		})*/
		//->orderBy('flag_default','Desc')
		->orderBy('pssc_sort','ASC')
        ->get();
        $nom=0;
        $results = array();
        if (!empty($banner_apps)) {
            foreach ($banner_apps as $row) {
                $nom++;
                /*if (!empty($row->p_main_image)) {
                    $image_url = "https://topsystem.id/api/product/600/";
                    $image[] = $row->p_main_image;
                }*/
				if($row->pssc_logo!=''){
				$url_banner = "https://topsystem.id/public/upload/category/".$row->pssc_logo;
				} else {
				$url_banner = "";
				}
				
				/*
				if($nom==1){
					$results[] = [
								'subcategory_id' => 1001,
								'title' => "WOMEN",
								'url_banner' => "",
								'description' => "WOMEN",
							];
					$results[] = [
								'subcategory_id' => 1002,
								'title' => "KIDS",
								'url_banner' => "",
								'description' => "KIDS",
							];
				}
				*/
				
                $results[] = [
                    'subcategory_id' => $row->pid,
                    'title' => $row->pssc_name,
                    'url_banner' => $url_banner,
					'description' => $row->pssc_description,
                ];
            }
        }

					$results[] = [
								'subcategory_id' => 1001,
								'title' => "WOMEN",
								'url_banner' => "",
								'description' => "WOMEN",
							];
					$results[] = [
								'subcategory_id' => 1002,
								'title' => "KIDS",
								'url_banner' => "",
								'description' => "KIDS",
							];

        // others
		$results[] = [
                    'subcategory_id' => 1000,
                    'title' => "OTHERS",
                    'url_banner' => "",
					'description' => "LAINNYA",
                ];
        
        $data = [
            'status' => [
                'code' => 200,
                'description' => 'Success'
            ],
            'results' => $results
        ];
        return response()->json($data);
    }
    
    public function getBrand(Request $request)
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

		$pssc_name = $request->subkat;
		
        $banner_apps = DB::table('products')->selectRaw('ts_products.id as pid, br_name, ts_brands.id as brand_id, br_image, br_slug, br_description, br_banner')
        ->leftJoin('product_sub_sub_categories', 'product_sub_sub_categories.id', '=', 'products.pssc_id')
        ->leftJoin('brands', 'brands.id', '=', 'products.br_id')
		//->where('pls_qty', '>', '0')
		->where('br_delete', '=', '0')
		->where('br_show_apps', '=', '1')
		->where(function($w) use($pssc_name) {
			if (!empty($pssc_name)) {
				$w->where('pssc_name', '=', $pssc_name);
			}
		})
		->groupBy('brands.id')
        ->get();
        $results = array();
        if (!empty($banner_apps)) {
            foreach ($banner_apps as $row) {
				//$url_banner = "https://topsystem.id/public/upload/brand/".$row->br_banner;
				if($row->br_banner!=''){
					$url_banner = "https://topsystem.id/api/brand/thumbs/".$row->br_banner;
				} else {
					if($row->br_image!=''){
						$url_banner = "https://topsystem.id/api/brand/thumbs/".$row->br_image;
					} else {
						$url_banner = "";
					}
				}
				
					if($row->br_image!=''){
						$url_banner = "https://topsystem.id/api/brand/thumbs/".$row->br_image;
					} else {
						$url_banner = "";
					}

                $results[] = [
                    'brand_id' => $row->brand_id,
                    'title' => $row->br_name,
                    'slug' => $row->br_slug,
                    'url_banner' => $url_banner,
					'description' => $row->br_description,
                ];
            }

        $data = [
            'status' => [
                'code' => 200,
                'description' => 'Success'
            ],
            'results' => $results
        ];
		
        }		
		else {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Not found'
				]
			];			
		}
		
        return response()->json($data);
    }
	
	
	// show brand
	public function allBrand(Request $request)
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

		//$kategori = $request->kategori;
		
        $banner_apps = DB::table('brands')->selectRaw('ts_brands.id as pid, br_name, br_slug, br_description, br_image, br_banner, created_at')
		->where('br_show_apps', '=', '1')
		->whereNotNull('br_image')
		->where('br_delete', '=', '0')
		/*->where(function($w) use($kategori) {
			if (!empty($kategori)) {
				$w->where('ba_kategori', '=', $kategori);
			}
		})*/
		->orderBy("br_sort","ASC")
        ->get();
        $results = array();
        if (!empty($banner_apps)) {
            foreach ($banner_apps as $row) {
				if($row->br_banner!=''){
					$url_banner = "https://topsystem.id/api/brand/thumbs/".$row->br_banner;
				} else {
					if($row->br_image!=''){
						$url_banner = "https://topsystem.id/api/brand/thumbs/".$row->br_image;
					} else {
						$url_banner = "";
					}
				}
					if($row->br_image!=''){
						$url_banner = "https://topsystem.id/api/brand/thumbs/".$row->br_image;
					} else {
						$url_banner = "";
					}

                $results[] = [
                    'brand_id' => $row->pid,
                    'title' => $row->br_name,
                    'slug' => $row->br_slug,
                    'url_banner' => $url_banner,
					'description' => $row->br_description,
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
