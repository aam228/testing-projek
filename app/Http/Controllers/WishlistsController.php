<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WishlistsController extends Controller
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


		$cust_id = $request->cust_id;
		//$product_id = $request->cust_id;
		if (!$cust_id) {
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id tidak boleh kosong'
                ]
            ];
            return response()->json($data);
        }
		
		$exception = DB::table('exception_locations')->select('pl_code')
        ->leftJoin('product_locations', 'product_locations.id', '=', 'exception_locations.pl_id')->get();
        $exc_location = array();
        foreach ($exception as $exc) {
            $exc_location[] = $exc->pl_code;
        }
		
        $data_products = DB::table('wishlists')
		//->select('wishlists.id', 'p_name', 'p_description', 'p_color', 'ps_price_tag', 'p_price_tag', 'ps_sell_price', 'p_sell_price', 'p_main_image', 'p_image', 'pst_id')
		->selectRaw("ts_wishlists.id, ts_wishlists.pst_id, pc_name as category, br_name as brand, p_name as article, p_color as color, sz_name as size, ps_price_tag, p_price_tag, ps_sell_price, p_sell_price, p_main_image, p_image, psc_name, pssc_name, sold, SUM(ts_product_location_setups.pls_qty) as stock, CONCAT(p_name,' (',br_name,')') as p_name_brand")
		->leftJoin('product_stocks', 'product_stocks.id', '=', 'wishlists.pst_id')
		->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
		
		->leftJoin('product_categories', 'product_categories.id', '=', 'products.pc_id')
		->leftJoin('product_sub_categories', 'product_sub_categories.id', '=', 'products.psc_id')
		->leftJoin('product_sub_sub_categories', 'product_sub_sub_categories.id', '=', 'products.pssc_id')
        ->leftJoin('brands', 'brands.id', '=', 'products.br_id')
        ->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
        //  cek stok
        ->leftJoin('product_location_setups', 'product_location_setups.pst_id', '=', 'product_stocks.id')
        ->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
        ->whereNotIn('pl_code', $exc_location)
		->where('pls_qty', '>', '0')
		->whereNotNull('p_main_image')
		->where('p_price_tag', '>', '0')
		->where('p_delete', '=', '0')
		->whereNotNull('p_main_image')
		
		->where('wishlists.cust_id', '=', $cust_id)
		->where('products.p_delete', '=', '0')
		->where('ws_delete', '=', '0')
		->groupBy('p_name_brand')
        ->orderBy("wishlists.id","DESC")
		//->limit('100')
        //->get();
        ->paginate(10);
		
		$pid = '';
        $results = array();
		$address = array();
        if (count($data_products)>0) {
            foreach ($data_products as $row) {
				$price = null;
                $price_tag = null;
                $store = null;
                $image_url = "https://topsystem.id/api/product/600/";
                $image = \App\Helpers\ProductImageHelper::parseImages($row->p_main_image, $row->p_image);
                if (!empty($row->ps_sell_price)) {
                    $price = $row->ps_sell_price;
                } else {
                    $price = $row->p_sell_price;
                }
                if (!empty($row->ps_price_tag)) {
                    $price_tag = $row->ps_price_tag;
                } else {
                    $price_tag = $row->p_price_tag;
                }
				
				if($row->p_price_tag!=$row->p_sell_price && $row->p_price_tag!='' && $row->p_sell_price!=''){
					$persen_potongan = (($row->p_price_tag - $row->p_sell_price) / $row->p_price_tag) * 100;
				} else {
					$persen_potongan = 0;
				}
				
				// tampilkan jumlah warna tersedia
				$stock_color = DB::table('product_stocks')
				->selectRaw("ts_product_stocks.id as pid, pc_name as category, br_name as brand, p_name as article, p_color as color, sz_name as size, ps_price_tag, p_price_tag, ps_sell_price, p_sell_price, p_main_image, p_image, psc_name, pssc_name, sold, SUM(ts_product_location_setups.pls_qty) as stock, CONCAT(p_name,' (',br_name,')') as p_name_brand, CONCAT(p_name,' (',p_color,')') as p_name_color")
				->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
				->leftJoin('product_categories', 'product_categories.id', '=', 'products.pc_id')
				->leftJoin('product_sub_categories', 'product_sub_categories.id', '=', 'products.psc_id')
				->leftJoin('product_sub_sub_categories', 'product_sub_sub_categories.id', '=', 'products.pssc_id')
				->leftJoin('brands', 'brands.id', '=', 'products.br_id')
				->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
				//cek stok
				->leftJoin('product_location_setups', 'product_location_setups.pst_id', '=', 'product_stocks.id')
				->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
				->whereNotIn('pl_code', $exc_location)
				//->whereIn('product_locations.st_id', ['2'])
				->where('pls_qty', '>', '0')
				->whereNotNull('p_main_image')
				->where('p_price_tag', '>', '0')
				->where('p_delete', '=', '0')
				->whereNotNull('p_main_image')
				->where('p_name', '=', $row->article)
				->groupBy('p_name_color')
				->get();
				
				$image = array();
				foreach($stock_color as $row_color){
					$img = \App\Helpers\ProductImageHelper::parseSingleImage($row_color->p_main_image, $row_color->p_image);
					if (!empty($img)) {
						$image[] = $img;
					}
				}
				$image_url = "https://topsystem.id/api/product/600/";
				
				// cek customers
				$add_persen = 0;
				if($cust_id){
					$price_tag = $row->p_price_tag;
					$sell_price = $row->p_sell_price;
					$arr_customer = DB::table("customers")->where("id","=",$cust_id)->first();
					if($arr_customer){
						// jika sebagai reseller
						if($arr_customer->cust_reseller==1){
							// kusus brand topscore {$row->brand}
							if($row->brand == "TOPSCORE"){
								//$add_persen = 0;
								//$persen_potongan = 20;
								//$sell_price_persen = ($price_tag * ($persen_potongan/100)); // default reseller 20%
								$add_persen = 20;
								$persen_potongan = 0;
								$sell_price_persen = ($price_tag * ($add_persen/100)); // default reseller 20%
								$sell_price = $price_tag - $sell_price_persen;
							} else {
								$add_persen = 5;
								$sell_price_persen = ($sell_price * ($add_persen/100)); // default reseller 5%
								$new_sell_price = $sell_price - $sell_price_persen;
								$sell_price = round($new_sell_price);
							}
							
						}
					}
				} else {
					$persen_potongan = $persen_potongan;
					$add_persen = 0;
				}
				
				if($row->brand == "TOPSCORE"){$flag_sold=0;} else {$flag_sold=1;}
				$results[] = [
                    'wishlist_id' => $row->id,
					//'product_id' => $row->pst_id,
                    //'product_name' => $row->p_name,
                    //'product_price_tag' => $price_tag,
                    //'product_sell_price' => $price,
                    //'discount' => round($persen_potongan),
                    //'image_url' => $image_url,
                    //'image' => $image,
					// batas awal
                    'product_id' => $row->pst_id,
                    'category' => $row->category,
                    'brand' => $row->brand,
                    'sub_cat' => $row->psc_name,
                    'subsub_cat' => $row->pssc_name,
                    'article' => $row->article,
                    'stock' => $row->stock,
                    'price_tag' => $row->p_price_tag,
					'sell_price' => $row->p_sell_price,
					'discount' => round($persen_potongan),
					'additional_discount' => round($add_persen),
					'sold' => $row->sold,
					'ratting' => 0, //$ratting,
					'total_ulasan' => 0,
					'jumlah_warna' => count($stock_color),
                    'image_url' => $image_url,
                    'image' => $image,
                    'flag_sold' => $flag_sold,
                ];
            }
			
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success'
				],
				'results' => $results,
			];
        } else {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data tidak ditemukan'
				],
				'results' => $results,
			];
		}
        return response()->json($data);
    }
    
	// add alamat user
	public function storeData(Request $request)
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
		
        //$mode = $request->mode;
        $pst_id = $request->product_id;
        $product_name = $request->product_name;
		$cust_id = $request->cust_id;
        if ($pst_id=='' || $cust_id=='' || $product_name=='') {
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id, product_name dan product_id tidak boleh kosong'
                ]
            ];
            return response()->json($data);
        }
		
		$arr_wishlist=DB::table("wishlists")->where('pst_id', $pst_id)->where('cust_id', $cust_id)->where('ws_delete', '=', '0')->first();
		if($arr_wishlist){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data produk sudah ada di wishlist'
                ]
            ];
            return response()->json($data);
		}
		
		//$mode = "add";
        $data = [
			'cust_id' => $cust_id,
			'pst_id' => $pst_id,
			'ws_product_name' => $product_name,
			'created_at' => date("Y-m-d H:i:s"),
		];
		
		//$check = $form_save->checkData('id', ['cust_email' => $request->r_email, 'cust_delete' => '0']);
		$saves = DB::table("wishlists")->insertGetId(array_merge($data));
		if($saves){
			//$r['status'] = "200";
			//$r['message'] = "Berhasil menyimpan data";
            $data = [
                'status' => [
                    'code' => 200,
                    'description' => 'Berhasil menyimpan data'
                ]
            ];
		} else {
			//$r['status'] = "400";
			//$r['message'] = "Gagal menyimpan data";
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Gagal menyimpan data'
                ]
            ];

		}
        return response()->json($data);
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

        //$id = $request->wishlist_id;
		$id = $request->product_id;
		$cust_id = $request->cust_id;
		//$arr_data = DB::table("wishlists")->where('id', $id)->where('cust_id', $cust_id)->where('ws_delete', 0)->first();
		$arr_data = DB::table("wishlists")->where('pst_id', $id)->where('cust_id', $cust_id)->where('ws_delete', 0)->first();
		if(empty($arr_data)){
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
			'ws_delete' => '1',
		];
		DB::table("wishlists")->where('id', $arr_data->id)->update($updateds);
		
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
}
