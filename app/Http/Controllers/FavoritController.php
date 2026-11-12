<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Hash;

class FavoritController extends Controller
{
	protected $table = 'favorit';
	
	private function checkData($table, $select, $where)
    {
        $affected = DB::table($table)
            ->select($select)
            ->where($where)
            ->get()->first();
        return $affected;
    }
	
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

        $mode 	= $request->mode;
		$id 	= $request->product_id;
		$cust_id 	= $request->cust_id;
		$select 	= $request->select;
		if($select==1){
			$flag_delete = '0';
		} else {
			$flag_delete = '1';
		}
		
        $arr_pst = $this->checkData('product_stocks','p_id', ['id' => $id]); // cek produk stok
		$data = [
			'p_id' => $arr_pst->p_id,
			'cust_id' => $cust_id,
			'f_delete' => $flag_delete,
		];
		
		$check = $this->checkData($this->table,'id', ['cust_id' => $cust_id, 'p_id' => $arr_pst->p_id]);
		if (!empty($check)) {
			// update favorit data
			$updated = [
				'updated_at' => date("Y-m-d H:i:s"),
			];
			$save = DB::table($this->table)->where('id', $check->id)->update(array_merge($data, $updated));
			$r['status'] = "200";
			$r['message'] = "Berhasil menambahkan favorit";
		} else {
			$updated = [
				'created_at' => date("Y-m-d H:i:s"),
			];
			$save = DB::table($this->table)->insertGetId(array_merge($data,$updated));
			$r['status'] = "200";
			$r['message'] = "Berhasil menambahkan favorit";
		}
        return response()->json($r);
    }
	
	// list favorit user
	public function getFavorit(Request $request)
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
		
        $query_data = DB::table($this->table)
		->selectRaw('ts_favorit.id as pid, pc_name as category, br_name as brand, p_name as article, p_color as color, p_price_tag, p_sell_price, p_main_image, p_image, ts_products.id as product_id')
		->leftJoin('products', 'products.id', '=', 'favorit.p_id')
		->leftJoin('product_categories', 'product_categories.id', '=', 'products.pc_id')
        ->leftJoin('brands', 'brands.id', '=', 'products.br_id')
		->where('cust_id', '=', $cust_id)
		->where('p_delete', '=', '0')
		->where('f_delete', '=', '0')
		/*->where(function($w) use($kategori) {
			if (!empty($kategori)) {
				$w->where('ba_kategori', '=', $kategori);
			}
		})*/
		->orderBy('p_name')
        ->get();
        $results = array();
        if (!empty($query_data)) {
            foreach ($query_data as $row) {
				
				$price = null;
                $price_tag = null;
				$image_url = "https://topsystem.id/api/product/600/";
				$image = \App\Helpers\ProductImageHelper::parseImages($row->p_main_image, $row->p_image);
				
                $price = $row->p_sell_price;
				$price_tag = $row->p_price_tag;

                $check_discount = DB::table('product_discounts')->select('pd_type', 'pd_value')
					->leftJoin('product_discount_details', 'product_discount_details.pd_id', '=', 'product_discounts.id')
                    ->leftJoin('product_stocks', 'product_stocks.id', '=', 'product_discount_details.pst_id')
                    //->where('product_discounts.std_id', '=', '9')
                    ->where('product_stocks.p_id', '=', $row->pid)
                    ->where('product_discounts.pd_date', '>=', date('Y-m-d'))
                    ->get()->first();
                if (!empty($check_discount)) {
                  $cd = $check_discount;
                  $discount = 0;
                  if ($cd->pd_type == 'percent') {
                    $discount = $price_tag - (($price_tag/100) * $cd->pd_value);
                  } else if ($cd->pd_type == 'amount') {
                    $discount = $price_tag - $cd->pd_value;
                  } else {
                    $discount = $price_tag;
                  }
                  $price = $discount;
                }

				// persentase potongan harga
				if($row->p_price_tag!=$row->p_sell_price && $row->p_price_tag!='' && $row->p_sell_price!=''){
					$persen_potongan = (($row->p_price_tag - $row->p_sell_price) / $row->p_price_tag) * 100;
				} else {
					$persen_potongan = 0;
				}
				
				// check product stock
				$arr_pst = $this->checkData('product_stocks','id', ['p_id' => $row->product_id]);
				
				// cek customers
				$add_persen = 0;
				$price_tag = $row->p_price_tag;
				$sell_price = $row->p_sell_price;
				if($cust_id){
					$arr_customer = DB::table("customers")->where("id","=",$cust_id)->first();
					if($arr_customer){
						// jika sebagai reseller
						if($arr_customer->cust_reseller==1){
							// kusus brand topscore {$row->brand}
							if($row->brand == "TOPSCORE"){
								$add_persen = 0;
								$persen_potongan = 20;
								$sell_price_persen = ($price_tag * ($persen_potongan/100)); // default reseller 20%
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
				
                $results[] = [
                    //'product_id' => $row->pid,
					'product_id' => $arr_pst->id,
					'category' => $row->category,
                    'brand' => $row->brand,
                    'article' => $row->article,
                    'color' => $row->color,
					//'price_tag' => $row->p_price_tag,
					//'sell_price' => $row->p_sell_price,
					//'discount' => round($persen_potongan,2),
					'price_tag' => $price_tag,
					'sell_price' => $sell_price,
					'discount' => round($persen_potongan,2),
					'additional_discount' => round($add_persen),
					'ratting' => 0, //$ratting,
                    'image_url' => $image_url,
                    'image' => $image,
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
