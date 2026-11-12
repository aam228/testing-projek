<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Helpers\ProductImageHelper;

class ArtikelController extends Controller
{
	protected $url_server = 'https://topsystem.id/';

	public function getStock_post(Request $request)
	{
		//Cache::flush();
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

		// save log traffic
		$data_log = [
			'cust_id' => $request->cust_id,
			'ta_page_access' => "Produk",
			'ta_description' => "Mengakses laman Produk aplikasi",
			'created_at' => date("Y-m-d H:i:s"),
			'ip_address' => $request->ip(),
		];
		$save_log = DB::table("traffic_apps")->insertGetId(array_merge($data_log));

		/*
		$exception = DB::table('exception_locations')->select('pl_code')
		->leftJoin('product_locations', 'product_locations.id', '=', 'exception_locations.pl_id')->get();
		$exc_location = array();
		foreach ($exception as $exc) {
			$exc_location[] = $exc->pl_code;
		}
		*/

		$exc_location = Cache::remember('exception_locations', now()->addHours(24), function () {
			return DB::table('exception_locations')
				->leftJoin('product_locations', 'product_locations.id', '=', 'exception_locations.pl_id')
				//->whereRaw("pl_code!=''")
				->pluck('pl_code')
				->toArray();
		});


		// Param pencarian artikel
		$sub_kat = $request->sub_kat;
		$subsub_kat = $request->subsub_kat;
		$brand = $request->brand ?? $request->brand_id;
		$product_name = $request->product_name;
		$sorting_artikel = $request->sorting_artikel; // sorting artikel (baru, terlaris, dilihat)
		$size_name = $request->size_name;
		$cust_id = $request->cust_id;

		// cek jika ada brand yang di klik
		if (!empty($brand)) {
			// save log traffic by brand
			$brand_log = is_array($brand) ? implode(', ', $brand) : (string)$brand;
			$data_log = [
				'cust_id' => $request->cust_id,
				'ta_page_access' => "Brand",
				'ta_description' => "Mengakses laman Produk dengan filter Brand " . $brand_log,
				'created_at' => date("Y-m-d H:i:s"),
				'ip_address' => $request->ip(),
			];
			$save_log = DB::table("traffic_apps")->insertGetId(array_merge($data_log));
		}

		/*
		if(!empty($sorting_artikel)){
			if($sorting_artikel == "baru"){
				//$sort_orders = 'purchase_order_article_details.id';
				$sort_orders = 'products.created_at';
			} else 
			if($sorting_artikel == "terlaris"){
				$sort_orders = 'products.sold';
			} else 
			if($sorting_artikel == "dilihat"){
				$sort_orders = 'products.sold';
			} else {
				$sort_orders = 'products.sold';
			}
		} else {
			//$sort_orders = 'products.sold';
			$sort_orders = 'products.created_at';
		}
		*/
		$sorts = 'DESC';
		if (!empty($sorting_artikel)) {
			if ($sorting_artikel == "baru" || $sorting_artikel == "terbaru") {
				//$sort_orders = 'purchase_order_article_details.id';
				$sort_orders = 'products.created_at';
				$sorts = 'DESC';
			} else
				if ($sorting_artikel == "terlaris") {
					$sort_orders = 'products.sold';
					$sorts = 'DESC';
				} else
					if ($sorting_artikel == "dilihat") {
						$sort_orders = 'products.sold';
						$sorts = 'DESC';
					} else
						if ($sorting_artikel == "tertinggi") {
							$sort_orders = 'products.p_sell_price';
							$sorts = 'DESC';
						} else
							if ($sorting_artikel == "terrendah") {
								$sort_orders = 'products.p_sell_price';
								$sorts = 'ASC';
							} else {
								$sort_orders = 'products.sold';
								$sorts = 'DESC';
							}
		} else {
			//$sort_orders = 'products.sold';
			$sort_orders = 'products.created_at';
			$sorts = 'DESC';
		}

		/*
		$stock = DB::table('product_stocks')->selectRaw("ts_product_stocks.id as pid, pc_name as category, br_name as brand, p_name as article, p_color as color, sz_name as size, ps_price_tag, p_price_tag, ps_sell_price, p_sell_price, p_main_image, p_image, psc_name, pssc_name, sold, SUM(ts_product_location_setups.pls_qty) as stock, CONCAT(p_name,' (',br_name,')') as p_name_brand, SUM(ts_products.p_ratting) as tot_ratting, COUNT(ts_products.id) as tot_ratting_data, SUM(ts_products.p_user_ratting) as tot_ratting_user")
		->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
		->leftJoin('product_categories', 'product_categories.id', '=', 'products.pc_id')
		->leftJoin('product_sub_categories', 'product_sub_categories.id', '=', 'products.psc_id')
		->leftJoin('product_sub_sub_categories', 'product_sub_sub_categories.id', '=', 'products.pssc_id')
		->leftJoin('brands', 'brands.id', '=', 'products.br_id')
		->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
		//->leftJoin('purchase_order_article_details', 'purchase_order_article_details.pst_id', '=', 'product_stocks.id') // data PO
		//  cek stok
		->leftJoin('product_location_setups', 'product_location_setups.pst_id', '=', 'product_stocks.id')
		->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
		->whereNotIn('pl_code', $exc_location)
		//->whereIn('product_locations.st_id', ['1'])
		->where('pls_qty', '>', '0')
		->whereNotNull('p_main_image')
		->where('p_price_tag', '>', '0')
		->where('p_delete', '=', '0')
		->whereNotNull('p_main_image')
		->where(function($w) use($sub_kat, $subsub_kat, $brand, $product_name, $size_name, $request) {
			if (!empty($sub_kat)) {
				$sub_details = explode(",",str_replace(", ",",",$sub_kat));
				$w->whereIn('product_sub_categories.psc_name', $sub_details);
			}
			if (!empty($subsub_kat)) {
				$subsub_details = explode(",",str_replace(", ",",",$subsub_kat));
				if($subsub_kat == "BALL"){
					$subsub_details = ['BOLA SEPAK', 'BOLA FUTSAL', 'BOLA BASKET', 'BOLA VOLI'];
				}
				//$w->whereIn('product_sub_sub_categories.pssc_name', $subsub_details);
				if(in_array($subsub_kat,array('WOMEN','KIDS'))){
					$genders = DB::table('genders')->where("gn_name",'=',$subsub_kat)->first();
					if($genders){
						$w->where('products.gn_id','=', $genders->id);
					}
				} else {
					$w->whereIn('product_sub_sub_categories.pssc_name', $subsub_details);
				}
			}
			if (!empty($product_name)) {
				//$w->where('products.p_name', '=', $product_name);
				//$w->where('products.p_name', 'LIKE', "%$product_name%");
				$arr_product_name = explode(" ",$product_name);
				foreach($arr_product_name as $nama_produk){
					//$w->where('products.p_name', 'LIKE', "%$nama_produk%");
					//$w->where(DB::raw("CONCAT(p_name, ' ', br_name, ' ',psc_name,' ',pssc_name,' ',br_name)"), 'LIKE', "%$nama_produk%");
					$w->where(DB::raw("CONCAT(p_name, ' ', br_name, ' ',psc_name,' ',pssc_name,' ',br_name)"), 'LIKE', "%$nama_produk%");
				}

				//"%$search%"
			}
			if (!empty($brand)) {
				$brand_details = explode(",",str_replace(", ",",",$brand));
				$w->whereIn('brands.br_name', $brand_details);
			}

			if (!empty($size_name)) {
				$size_name_details = explode(",",str_replace(", ",",",$size_name));			
				$w->whereIn('sizes.sz_name', $size_name_details);
			}
			// cek store stock data
			if($request->store_id){
				$w->where('product_locations.st_id','=',$request->store_id);
			}
		})
		->groupBy('p_name_brand')
		->orderBy($sort_orders,$sorts)
		->paginate(10);
		*/

		// cek customer berlangganan atau tidak ??? karena penggunaaan cache
		$cust_reseller = 0;
		if ($request->cust_id) {
			$arr_customer = DB::table("customers")->where("id", "=", $request->cust_id)->first();
			if ($arr_customer) {
				$cust_reseller = $arr_customer->cust_reseller;
			}
		} else {
			$arr_customer = DB::table("customers")->where("id", "=", NULL)->first();
		}

		// bungkus dalam cache selama 30 menit kedepan (md5(json_encode))
		$cacheKey = 'product_stock_v2_' . md5(json_encode([
			'sub_kat' => $sub_kat,
			'subsub_kat' => $subsub_kat,
			'brand' => $brand,
			'product_name' => $product_name,
			'sort_orders' => $sort_orders,
			'sorts' => $sorts,
			'size_name' => $size_name,
			'store_id' => $request->store_id,
			'cust_reseller' => $cust_reseller,
			'page' => request('page', 1) // biar cache per halaman paginate
		]));

		//Cache::forget($cacheKey);

		return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($sub_kat, $subsub_kat, $brand, $product_name, $sorting_artikel, $size_name, $request, $sort_orders, $sorts, $cust_id, $exc_location, $arr_customer) {


			$stock = DB::table('product_stocks')->selectRaw("ts_product_stocks.id as pid, pc_name as category, br_name as brand, p_name as article, p_color as color, sz_name as size, ps_price_tag, p_price_tag, ps_sell_price, p_sell_price, p_main_image, p_image, psc_name, pssc_name, sold, SUM(ts_product_stocks.ps_qty) as stock, CONCAT(p_name,' (',br_name,')') as p_name_brand, SUM(ts_products.p_ratting) as tot_ratting, COUNT(ts_products.id) as tot_ratting_data, SUM(ts_products.p_user_ratting) as tot_ratting_user, p_slug, p_id")
				->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
				->leftJoin('product_categories', 'product_categories.id', '=', 'products.pc_id')
				->leftJoin('product_sub_categories', 'product_sub_categories.id', '=', 'products.psc_id')
				->leftJoin('product_sub_sub_categories', 'product_sub_sub_categories.id', '=', 'products.pssc_id')
				->leftJoin('brands', 'brands.id', '=', 'products.br_id')
				->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
				//  cek stok
				//->leftJoin('product_location_setups', 'product_location_setups.pst_id', '=', 'product_stocks.id')
				//->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
				//->whereNotIn('pl_code', $exc_location)
				->where('product_stocks.ps_qty', '>', '0')
				->where(function ($q) {
					$q->where('product_stocks.ps_delete', '!=', '1')
					  ->orWhereNull('product_stocks.ps_delete');
				})
				->where(function ($q) {
					$q->where('product_stocks.ps_barcode', 'NOT LIKE', '%_OLD_%')
					  ->orWhereNull('product_stocks.ps_barcode');
				})
				->whereNotNull('p_main_image')
				->where('p_price_tag', '>', '0')
				->where('p_delete', '=', '0')
				->whereNotNull('p_main_image')
				->where(function ($w) use ($sub_kat, $subsub_kat, $brand, $product_name, $size_name, $request) {
					if (!empty($sub_kat)) {
						$sub_details = explode(",", str_replace(", ", ",", $sub_kat));
						$w->whereIn('product_sub_categories.psc_name', $sub_details);
					}
					if (!empty($subsub_kat)) {
						$subsub_details = explode(",", str_replace(", ", ",", $subsub_kat));
						if ($subsub_kat == "BALL") {
							$subsub_details = ['BOLA SEPAK', 'BOLA FUTSAL', 'BOLA BASKET', 'BOLA VOLI'];
						}

						if ($subsub_kat == "OTHERS") {
							$subsub_details = ['OTHERS', 'T-SHIRT', 'LONG', 'MIDDLE', 'SHORT', 'ANKLE', 'HAT', 'TRAINING', 'BACKPACK', 'SLING BAG', 'WAIST BAG', 'SADDLE', 'TAPE', 'SHOES BAG', 'SHINGUARD', 'SHORT PANTS', 'LONG PANTS', 'HOODIE', 'BASELAYER', 'JACKET', 'SWEATER', 'WASH BAG', 'TOTEBAG', 'HANGTAG'];
						}

						if (in_array($subsub_kat, array('WOMEN', 'KIDS'))) {
							$genders = DB::table('genders')->where("gn_name", '=', $subsub_kat)->first();
							if ($genders) {
								$w->where('products.gn_id', '=', $genders->id);
							}
						} else {
							$w->whereIn('product_sub_sub_categories.pssc_name', $subsub_details);
						}
					}
					if (!empty($product_name)) {
						$arr_product_name = explode(" ", $product_name);
						foreach ($arr_product_name as $nama_produk) {
							//$w->where(DB::raw("CONCAT(p_name, ' ', br_name, ' ',psc_name,' ',pssc_name,' ',p_description)"), 'LIKE', "%$nama_produk%");
							$w->where(DB::raw("CONCAT(
					    COALESCE(p_name, ''), ' ',
                        COALESCE(br_name, ''), ' ',
                        COALESCE(psc_name, ''), ' ',
                        COALESCE(pssc_name, ''), ' ',
                        COALESCE(p_description, ''), ' ',
                        COALESCE(p_hashtag, '')
					)"), 'LIKE', "%$nama_produk%");
						}
					}
					if (!empty($brand)) {
						$brand_details = is_array($brand) ? $brand : explode(",", str_replace(", ", ",", (string)$brand));
						$w->where(function ($bw) use ($brand_details) {
							$bw->whereIn('brands.br_name', $brand_details)
								->orWhereIn('products.br_id', $brand_details);
						});
					}

					if (!empty($size_name)) {
						$size_name_details = explode(",", str_replace(", ", ",", $size_name));
						$w->whereIn('sizes.sz_name', $size_name_details);
					}
					// cek store stock data
					if ($request->store_id) {
						//$w->where('product_locations.st_id','=',$request->store_id);
					}
				})
				->groupBy('p_name_brand')
				->orderBy($sort_orders, $sorts)
				->paginate(10);

			$image = array();
			$results = array();
			if (!empty($stock)) {
				$pids = $stock->pluck('pid')->filter()->unique()->toArray();
				$articles = $stock->pluck('article')->filter()->unique()->toArray();
				$brands = $stock->pluck('brand')->filter()->unique()->toArray();

				// Batch 1: check_discount
				$discounts_by_pid = [];
				if (!empty($pids)) {
					$now_date = date("Y-m-d");
					$now_time = date("H:i:s");
					$all_discounts = DB::table('product_discounts')
						->select('product_discount_details.pst_id', 'pd_type', 'pd_value')
						->leftJoin('product_discount_details', 'product_discount_details.pd_id', '=', 'product_discounts.id')
						->where('product_discounts.std_id', '=', '9')
						->whereIn('product_discount_details.pst_id', $pids)
						->where('pd_start', '<=', $now_date)
						->where('pd_date', '>=', $now_date)
						->where('pd_start_time', '<=', $now_time)
						->where('pd_end_time', '>=', $now_time)
						->get();
					foreach ($all_discounts as $cd) {
						if (!isset($discounts_by_pid[$cd->pst_id])) {
							$discounts_by_pid[$cd->pst_id] = $cd;
						}
					}
				}

				// Batch 2: wishlists
				$wishlists_by_pid = [];
				if (!empty($pids) && !empty($cust_id)) {
					$all_wishlists = DB::table("wishlists")
						->whereIn('pst_id', $pids)
						->where('cust_id', $cust_id)
						->where('ws_delete', '=', '0')
						->pluck('pst_id')
						->toArray();
					$wishlists_by_pid = array_flip($all_wishlists);
				}

				// Batch 3: promo aktif apps
				$promos_by_pst = [];
				$b1g1_by_pst = [];
				if (!empty($articles)) {
					$now_dt = date("Y-m-d H:i:s");
					$all_promos = DB::table("product_discount_details")
						->select('product_discount_details.id', 'product_discount_details.pst_id', 'pd_value', 'pd_type', 'p_main_image', 'p_image', 'products.p_name')
						->leftJoin('product_discounts', 'product_discounts.id', '=', 'product_discount_details.pd_id')
						->leftJoin('product_stocks', 'product_stocks.id', '=', 'product_discount_details.pst_id')
						->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
						->leftJoin('product_location_setups', 'product_location_setups.pst_id', '=', 'product_stocks.id')
						->where('pls_qty', '>', '0')
						->where('std_id', '=', '20')
						->whereRaw("CONCAT(ts_product_discounts.pd_start,' ',ts_product_discounts.pd_start_time)<='$now_dt'")
						->whereRaw("CONCAT(ts_product_discounts.pd_date,' ',ts_product_discounts.pd_end_time)>='$now_dt'")
						->whereIn('products.p_name', $articles)
						->where('products.p_delete', '=', '0')
						->get();
					foreach ($all_promos as $promo) {
						if ($promo->pd_type == 'b1g1') {
							$b1g1_by_pst[$promo->pst_id] = true;
						} else {
							if (!isset($promos_by_pst[$promo->pst_id])) {
								$promos_by_pst[$promo->pst_id] = $promo;
							}
						}
					}
				}

				// Batch 4: stock colors
				$stock_colors_by_key = [];
				if (!empty($articles) && !empty($brands)) {
					$all_stock_colors = DB::table('product_stocks')
						->selectRaw("ts_product_stocks.id as pid, pc_name as category, br_name as brand, p_name as article, p_color as color, sz_name as size, ps_price_tag, p_price_tag, ps_sell_price, p_sell_price, p_main_image, p_image, psc_name, pssc_name, sold, SUM(ts_product_location_setups.pls_qty) as stock, CONCAT(p_name,' (',br_name,')') as p_name_brand, CONCAT(p_name,' (',p_color,')') as p_name_color")
						->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
						->leftJoin('product_categories', 'product_categories.id', '=', 'products.pc_id')
						->leftJoin('product_sub_categories', 'product_sub_categories.id', '=', 'products.psc_id')
						->leftJoin('product_sub_sub_categories', 'product_sub_sub_categories.id', '=', 'products.pssc_id')
						->leftJoin('brands', 'brands.id', '=', 'products.br_id')
						->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
						->leftJoin('product_location_setups', 'product_location_setups.pst_id', '=', 'product_stocks.id')
						->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
						->whereNotIn('pl_code', $exc_location)
						->where('pls_qty', '>', '0')
						->where(function ($q) {
							$q->where('product_stocks.ps_delete', '!=', '1')
							  ->orWhereNull('product_stocks.ps_delete');
						})
						->where(function ($q) {
							$q->where('product_stocks.ps_barcode', 'NOT LIKE', '%_OLD_%')
							  ->orWhereNull('product_stocks.ps_barcode');
						})
						->whereNotNull('p_main_image')
						->where('p_price_tag', '>', '0')
						->where('p_delete', '=', '0')
						->whereIn('products.p_name', $articles)
						->whereIn('brands.br_name', $brands)
						->groupBy('p_name_color')
						->orderBy('products.created_at', 'DESC')
						->get();
					foreach ($all_stock_colors as $sc) {
						$key = $sc->article . '|||' . $sc->brand;
						$stock_colors_by_key[$key][] = $sc;
					}
				}

				// Batch 5: ratings
				$ratings_by_article = [];
				if (!empty($articles)) {
					$all_ratings = DB::table("products")
						->select('id', 'p_name', 'p_ratting', 'p_user_ratting')
						->whereIn("p_name", $articles)
						->where("p_delete", "=", "0")
						->get();
					foreach ($all_ratings as $rt) {
						$ratings_by_article[$rt->p_name][] = $rt;
					}
				}

				foreach ($stock as $row) {
					$price = null;
					$price_tag = null;
					$image_url = "https://topsystem.id/api/product/600/";
					$store = null;
					$image = ProductImageHelper::parseImages($row->p_main_image, $row->p_image);
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

					$check_discount = isset($discounts_by_pid[$row->pid]) ? $discounts_by_pid[$row->pid] : null;
					if (!empty($check_discount)) {
						$cd = $check_discount;
						$discount = 0;
						if ($cd->pd_type == 'percent') {
							$discount = $price_tag - (($price_tag / 100) * $cd->pd_value);
						} else if ($cd->pd_type == 'amount') {
							$discount = $price_tag - $cd->pd_value;
						} else {
							$discount = $price_tag;
						}
						$price = $discount;
					}

					// persentase potongan harga
					if ($row->p_price_tag != $row->p_sell_price && $row->p_price_tag != '' && $row->p_sell_price != '') {
						$persen_potongan = (($row->p_price_tag - $row->p_sell_price) / $row->p_price_tag) * 100;
					} else {
						$persen_potongan = 0;
					}

					$flag_wishlist = isset($wishlists_by_pid[$row->pid]) ? 1 : 0;

					// promo aktif apps
					$arr_promo = isset($promos_by_pst[$row->pid]) ? $promos_by_pst[$row->pid] : null;

					$price_tag = $row->p_price_tag; // harga bandrol
					$sell_price = $row->p_sell_price; // harga jual
					$flag_buy1get1 = 0;
					// cek diskon
					if ($arr_promo) {
						if ($arr_promo->pd_type == "percent") {
							$nilai_diskon = $price_tag * ($arr_promo->pd_value / 100);
							$sell_price = $price_tag - $nilai_diskon;
						} else
							if ($arr_promo->pd_type == "amount") {
								$sell_price = $price_tag - $arr_promo->pd_value;
							} else
								if ($arr_promo->pd_type == "fix") {
									$sell_price = $arr_promo->pd_value;
								} else
									if ($arr_promo->pd_type == "b1g1") { // jika B1G1
										$sell_price = $price_tag + 0000; // harga bandrol sebagai harga jual...
										$flag_buy1get1 = 1;
										$price_tag = $sell_price;
									} else {
										$sell_price = $sell_price;
									}

						if ($price_tag != $sell_price) {
							$persen_potongan = 100 - (($sell_price / $price_tag) * 100);
						} else {
							$persen_potongan = 0;
						}
					}

					// tampilkan jumlah warna tersedia
					$key_color = $row->article . '|||' . $row->brand;
					$stock_color = isset($stock_colors_by_key[$key_color]) ? $stock_colors_by_key[$key_color] : [];

					$image = array();
					$no_color = 0;
					foreach ($stock_color as $row_color) {
						$no_color++;
						$img = ProductImageHelper::parseSingleImage($row_color->p_main_image, $row_color->p_image);
						if (!empty($img)) {
							$image[] = $img;
						}
					}
					if (empty($image)) {
						$image = ProductImageHelper::parseImages($row->p_main_image, $row->p_image);
					}
					$image_url = (!empty($image[0]) && preg_match('#^https?://#i', $image[0])) ? "" : "https://topsystem.id/api/product/600/";

					// cek ratting produk [new function]
					$qproducts = isset($ratings_by_article[$row->article]) ? $ratings_by_article[$row->article] : [];
					$tot_ratting = 0;
					$tot_ratting_user = 0;
					$jml_data_product = 0;
					foreach ($qproducts as $raw_ratting) {
						if ($raw_ratting->p_ratting > 0) {
							$tot_ratting += $raw_ratting->p_ratting;
							$tot_ratting_user += $raw_ratting->p_user_ratting;
							$jml_data_product++;
						}
					}
					if ($tot_ratting > 0 && $jml_data_product > 0) {
						$tot_ratting = round($tot_ratting / $jml_data_product, 2);
					}

					if ($tot_ratting == 0) {
						$tot_ratting = 5;
					}

					if (isset($b1g1_by_pst[$row->pid])) {
						$flag_buy1get1 = 1;
					}

					$cashback_coin = (1 / 100) * $sell_price;
					if ($flag_buy1get1 == 1) {
						$label_promo = 'B1G1';
					} else {
						$label_promo = '';
					}

					// if($persen_potongan>=50){$add_persen=0;} else {$add_persen=$add_persen;}

					// cek IPB RUN
					$jersey_ipb = 0;
					if (in_array($row->p_id, ['13797', '13798', '13799', '13800', '13814', '13815'])) {
						$jersey_ipb++;
					}

					// cek customers
					$add_persen = 0;
					if ($request->cust_id && $persen_potongan < 30 && $jersey_ipb == 0) {
						//$arr_customer = DB::table("customers")->where("id","=",$request->cust_id)->first();
						$arr_customer = $arr_customer;
						if ($arr_customer && $flag_buy1get1 == 0) {
							// jika sebagai reseller
							if ($arr_customer->cust_reseller == 1) {
								// kusus brand topscore {$row->brand}
								if ($row->brand == "TOPSCORE") {
									//$add_persen = 0;
									//$persen_potongan = 20;
									//$sell_price_persen = ($price_tag * ($persen_potongan/100)); // default reseller 20%
									$add_persen = 20;
									$persen_potongan = 0;
									$sell_price_persen = ($price_tag * ($add_persen / 100)); // default reseller 20%
									$sell_price = $price_tag - $sell_price_persen;
								} else {
									$add_persen = 5;
									$sell_price_persen = ($sell_price * ($add_persen / 100)); // default reseller 5%
									$new_sell_price = $sell_price - $sell_price_persen;
									$sell_price = round($new_sell_price);
								}

							}
						} else {
							$add_persen = 0;
						}
					} else {
						$persen_potongan = $persen_potongan;
						$add_persen = 0;
					}

					//if($persen_potongan>=50){$add_persen=0;} else {$add_persen=$add_persen;}

					if ($row->brand == "TOPSCORE") {
						$flag_sold = 0;
					} else {
						$flag_sold = 1;
					}

					if (count($stock_color) > 0 || !empty($image)) {
						$results[] = [
							'product_id' => $row->pid,
							'category' => $row->category,
							'brand' => $row->brand,
							'sub_cat' => $row->psc_name,
							'subsub_cat' => $row->pssc_name,
							'article' => $row->article,
							//'color' => $row->color,
							//'size' => $row->size,
							'stock' => $row->stock,
							//'price_tag' => $row->p_price_tag,
							//'sell_price' => $row->p_sell_price,
							'price_tag' => $price_tag,
							'sell_price' => $sell_price,
							'discount' => round($persen_potongan),
							'additional_discount' => round($add_persen),
							'sold' => $row->sold,
							//'ratting' => 5, //$ratting,
							//'ratting' => $ratting_produk,
							'ratting' => $tot_ratting,
							//'total_ulasan' => 0,
							'total_ulasan' => $tot_ratting_user,
							//'total_ulasan' => count($query_ratting),
							//'ratting' => $ratting_produk,
							//'total_ulasan' => count($query_ratting),
							//'ratting' => $tot_ratting,
							//'total_ulasan' => $tot_ratting_user,
							'wishlist' => $flag_wishlist,
							'jumlah_warna' => count($stock_color),
							'image_url' => $image_url,
							'image' => $image,
							'flag_buy1get1' => $flag_buy1get1,
							'cashback_coin' => round($cashback_coin),
							'label_promo' => $label_promo,
							'flag_sold' => $flag_sold,
							'slug' => $row->p_slug,
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
			} else {
				$data = [
					'status' => [
						'code' => 200,
						'description' => 'Data not found'
					],
					'results' => $results
				];
			}
			return response()->json($data);

		}); // bungkus dalam cache selama 30 menit kedepan
	}

	public function getStock(Request $request)
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

		$exception = DB::table('exception_locations')->select('pl_code')
			->leftJoin('product_locations', 'product_locations.id', '=', 'exception_locations.pl_id')->get();
		$exc_location = array();
		foreach ($exception as $exc) {
			$exc_location[] = $exc->pl_code;
		}
		$stock = DB::table('product_stocks')->selectRaw('ts_product_stocks.id as pid, pc_name as category, br_name as brand, p_name as article, p_color as color, sz_name as size, ps_price_tag, p_price_tag, ps_sell_price, p_sell_price, p_main_image, p_image')
			->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
			->leftJoin('product_categories', 'product_categories.id', '=', 'products.pc_id')
			->leftJoin('brands', 'brands.id', '=', 'products.br_id')
			->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
			->leftJoin('product_location_setups', 'product_location_setups.pst_id', '=', 'product_stocks.id')
			->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
			->whereNotIn('pl_code', $exc_location)
			//->whereIn('product_locations.st_id', ['1'])
			//->where('pls_qty', '>', '0')
			->whereNotIn('product_locations.st_id', ['5', '10', '11'])
			->where(function ($q) {
				$q->where('product_stocks.ps_delete', '!=', '1')
				  ->orWhereNull('product_stocks.ps_delete');
			})
			->where(function ($q) {
				$q->where('product_stocks.ps_barcode', 'NOT LIKE', '%_OLD_%')
				  ->orWhereNull('product_stocks.ps_barcode');
			})
			->where('p_delete', '=', '0')
			//->groupBy('pid')
			->groupBy('products.p_name')
			->limit('100')
			->get();
		$results = array();
		if (!empty($stock) && count($stock) > 0) {
			$pids = $stock->pluck('pid')->filter()->unique()->toArray();
			$discounts = [];
			if (!empty($pids)) {
				$now_date = date("Y-m-d");
				$now_time = date("H:i:s");
				$discount_records = DB::table('product_discounts')
					->select('product_discount_details.pst_id', 'pd_type', 'pd_value')
					->leftJoin('product_discount_details', 'product_discount_details.pd_id', '=', 'product_discounts.id')
					->where('product_discounts.std_id', '=', '9')
					->whereIn('product_discount_details.pst_id', $pids)
					->where('pd_start', '<=', $now_date)
					->where('pd_date', '>=', $now_date)
					->where('pd_start_time', '<=', $now_time)
					->where('pd_end_time', '>=', $now_time)
					->get();
				foreach ($discount_records as $dr) {
					if (!isset($discounts[$dr->pst_id])) {
						$discounts[$dr->pst_id] = $dr;
					}
				}
			}

			foreach ($stock as $row) {
				$price = null;
				$price_tag = null;
				$image_url = null;
				$store = null;
				$image = array();
				/*
				if ($row->stock <= 0) {
					continue;
				}
				*/
				$image_url = "https://topsystem.id/api/product/600/";
				$image = ProductImageHelper::parseImages($row->p_main_image, $row->p_image);
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
				$check_discount = $discounts[$row->pid] ?? null;
				if (!empty($check_discount)) {
					$cd = $check_discount;
					$discount = 0;
					if ($cd->pd_type == 'percent') {
						$discount = $price_tag - (($price_tag / 100) * $cd->pd_value);
					} else if ($cd->pd_type == 'amount') {
						$discount = $price_tag - $cd->pd_value;
					} else {
						$discount = $price_tag;
					}
					$price = $discount;
				}

				/*
				if ($row->st_id == '4') {
					$store = 'URBAN';
				} else {
					$store = 'WHOLESALE';
				}
				*/

				// persentase potongan harga
				if ($row->p_price_tag != $row->p_sell_price && $row->p_price_tag != '' && $row->p_sell_price != '') {
					$persen_potongan = (($row->p_price_tag - $row->p_sell_price) / $row->p_price_tag) * 100;
				} else {
					$persen_potongan = 0;
				}

				// ratting produk
				/*
				$check_ratting = DB::table('user_ratings')->selectRaw('SUM(ts_user_ratings.ur_value) as total_value, COUNT(*) as jml')
					//->leftJoin('pos_transactions', 'pos_transactions.id', '=', 'user_ratings.pt_id')
					->leftJoin('pos_transaction_details', 'pos_transaction_details.pt_id', '=', 'user_ratings.pt_id')
					->leftJoin('product_stocks', 'product_stocks.id', '=', 'pos_transaction_details.pst_id')
					->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
					->where('products.p_name', '=', $row->article)
					//->groupBy('product_stocks.p_name')
					->get()->first();
				if (!empty($check_ratting)) {
					if($check_ratting->total_value>0){
						$ratting = ($check_ratting->total_value / $check_ratting->jml);
						$ratting = round($ratting,2);
					} else {
						$ratting = 0;
					}
				} else {
					$ratting = 0;
				}
				*/

				if ($row->brand == "TOPSCORE") {
					$flag_sold = 0;
				} else {
					$flag_sold = 1;
				}
				$results[] = [
					'product_id' => (int) $row->pid,
					//'store' => $store,
					'category' => $row->category,
					'brand' => $row->brand,
					'article' => $row->article,
					//'color' => $row->color,
					//'size' => $row->size,
					//'stock' => $row->stock,
					'price_tag' => $row->p_price_tag,
					'sell_price' => $row->p_sell_price,
					'discount' => round($persen_potongan),
					'ratting' => 0, //$ratting,
					'total_ulasan' => 0,
					'image_url' => $image_url,
					'image' => $image,
					'flag_sold' => $flag_sold,
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

	public function getStockByCategory(Request $request)
	{
		$client_id = $request->header('client_id');
		$api_key = $request->header('api_key');

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

		$exception = DB::table('exception_locations')->select('pl_code')
			->leftJoin('product_locations', 'product_locations.id', '=', 'exception_locations.pl_id')->get();
		$exc_location = array();
		foreach ($exception as $exc) {
			$exc_location[] = $exc->pl_code;
		}
		$category_id = $request->input('category_id');
		if (empty($category_id)) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Not found'
				]
			];
			return response()->json($data);
		}
		$stock = DB::table('product_stocks')->selectRaw('ts_product_stocks.id as pid, ts_product_locations.st_id as st_id, pc_name as category, br_name as brand, p_name as article, p_color as color, sz_name as size, sum(ts_product_location_setups.pls_qty) as stock, ps_price_tag, p_price_tag, ps_sell_price, p_sell_price, p_main_image, p_image')
			->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
			->leftJoin('product_categories', 'product_categories.id', '=', 'products.pc_id')
			->leftJoin('brands', 'brands.id', '=', 'products.br_id')
			->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
			->leftJoin('product_location_setups', 'product_location_setups.pst_id', '=', 'product_stocks.id')
			->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
			->whereNotIn('pl_code', $exc_location)
			->where('products.pc_id', '=', $category_id)
			->whereIn('product_locations.st_id', ['4', '5'])
			->groupBy('pid')
			->get();
		$results = array();
		if (!empty($stock->first())) {
			$pids = $stock->pluck('pid')->filter()->unique()->toArray();
			$discounts_by_pid = [];
			if (!empty($pids)) {
				$now_date = date("Y-m-d");
				$now_time = date("H:i:s");
				$all_discounts = DB::table('product_discounts')
					->select('product_discount_details.pst_id', 'pd_type', 'pd_value')
					->leftJoin('product_discount_details', 'product_discount_details.pd_id', '=', 'product_discounts.id')
					->where('product_discounts.std_id', '=', '9')
					->whereIn('product_discount_details.pst_id', $pids)
					->where('pd_start', '<=', $now_date)
					->where('pd_date', '>=', $now_date)
					->where('pd_start_time', '<=', $now_time)
					->where('pd_end_time', '>=', $now_time)
					->get();
				foreach ($all_discounts as $cd) {
					if (!isset($discounts_by_pid[$cd->pst_id])) {
						$discounts_by_pid[$cd->pst_id] = $cd;
					}
				}
			}

			foreach ($stock as $row) {
				$price = null;
				$price_tag = null;
				$image_url = null;
				$image = array();
				if ($row->stock <= 0) {
					continue;
				}
				$image_url = "https://topsystem.id/api/product/600/";
				$image = ProductImageHelper::parseImages($row->p_main_image, $row->p_image);
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
				$check_discount = isset($discounts_by_pid[$row->pid]) ? $discounts_by_pid[$row->pid] : null;
				if (!empty($check_discount)) {
					$cd = $check_discount;
					$discount = 0;
					if ($cd->pd_type == 'percent') {
						$discount = $price_tag - (($price_tag / 100) * $cd->pd_value);
					} else if ($cd->pd_type == 'amount') {
						$discount = $price_tag - $cd->pd_value;
					} else {
						$discount = $price_tag;
					}
					$price = $discount;
				}
				if ($row->st_id == '4') {
					$store = 'URBAN';
				} else {
					$store = 'WHOLESALE';
				}

				if ($row->brand == "TOPSCORE") {
					$flag_sold = 0;
				} else {
					$flag_sold = 1;
				}
				$results[] = [
					'product_id' => $row->pid,
					'store' => $store,
					'category' => $row->category,
					'brand' => $row->brand,
					'article' => $row->article,
					'color' => $row->color,
					'size' => $row->size,
					'stock' => $row->stock,
					'price' => $price,
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
				'results' => $results
			];
		} else {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Not found'
				]
			];
		}
		return response()->json($data);
	}

	public function getStockV2(Request $request)
	{
		$client_id = $request->header('client_id');
		$api_key = $request->header('api_key');

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

		$exception = DB::table('exception_locations')->select('pl_code')
			->leftJoin('product_locations', 'product_locations.id', '=', 'exception_locations.pl_id')->get();
		$exc_location = array();
		foreach ($exception as $exc) {
			$exc_location[] = $exc->pl_code;
		}
		$product = DB::table('products')->selectRaw('ts_products.id as pid, ts_product_stocks.id as pstid, ts_product_locations.st_id as st_id,  sum(ts_product_location_setups.pls_qty) as stock, pc_name as category, br_name as brand, p_name as article, p_color as color, ps_price_tag, p_price_tag, ps_sell_price, p_sell_price, p_main_image, p_image')
			->leftJoin('product_stocks', 'product_stocks.p_id', '=', 'products.id')
			->leftJoin('product_categories', 'product_categories.id', '=', 'products.pc_id')
			->leftJoin('brands', 'brands.id', '=', 'products.br_id')
			->leftJoin('product_location_setups', 'product_location_setups.pst_id', '=', 'product_stocks.id')
			->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
			->whereNotIn('pl_code', $exc_location)
			->whereIn('product_locations.st_id', ['4', '5'])
			->groupBy('pid')
			->get();
		$results = array();
		if (!empty($product)) {
			$results = array();
			foreach ($product as $row) {
				$price = null;
				$price_tag = null;
				$image_url = null;
				$store = null;
				$image = array();
				if ($row->stock <= 0) {
					continue;
				}
				$image_url = "https://topsystem.id/api/product/600/";
				$image = ProductImageHelper::parseImages($row->p_main_image, $row->p_image);
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
				$check_discount = DB::table('product_discounts')->select('pd_type', 'pd_value')
					->leftJoin('product_discount_details', 'product_discount_details.pd_id', '=', 'product_discounts.id')
					->where('product_discounts.std_id', '=', '9')
					->where('product_discount_details.pst_id', '=', $row->pstid)
					//->where('product_discounts.pd_date', '>=', date('Y-m-d'))
					->where('pd_start', '<=', date("Y-m-d"))
					->where('pd_date', '>=', date("Y-m-d"))
					->where('pd_start_time', '<=', date("H:i:s"))
					->where('pd_end_time', '>=', date("H:i:s"))
					->get()->first();
				if (!empty($check_discount)) {
					$cd = $check_discount;
					$discount = 0;
					if ($cd->pd_type == 'percent') {
						$discount = $price_tag - (($price_tag / 100) * $cd->pd_value);
					} else if ($cd->pd_type == 'amount') {
						$discount = $price_tag - $cd->pd_value;
					} else {
						$discount = $price_tag;
					}
					$price = $discount;
				}
				if ($row->st_id == '4') {
					$store = 'URBAN';
				} else {
					$store = 'WHOLESALE';
				}
				$stock = DB::table('product_stocks')->selectRaw('ts_product_stocks.id as pid, ts_product_locations.st_id as st_id, sz_name as size, sum(ts_product_location_setups.pls_qty) as stock')
					->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
					->leftJoin('product_location_setups', 'product_location_setups.pst_id', '=', 'product_stocks.id')
					->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
					->whereNotIn('pl_code', $exc_location)
					->whereIn('product_locations.st_id', ['4', '5'])
					->where('product_stocks.p_id', '=', $row->pid)
					->groupBy('size')
					->groupBy('st_id')
					->groupBy('pid')
					->having('stock', '>', '0')
					->get();
				$details = array();
				foreach ($stock as $st) {
					$details[] = [
						'product_id' => $st->pid,
						'size' => $st->size,
						'stock' => $st->stock,
					];
				}
				$results[] = [
					'store' => $store,
					'category' => $row->category,
					'brand' => $row->brand,
					'article' => $row->article,
					'color' => $row->color,
					'price' => $price,
					'image_url' => $image_url,
					'image' => $image,
					'details' => $details
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

	public function getStockByCategoryV2(Request $request)
	{
		$client_id = $request->header('client_id');
		$api_key = $request->header('api_key');

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

		$exception = DB::table('exception_locations')->select('pl_code')
			->leftJoin('product_locations', 'product_locations.id', '=', 'exception_locations.pl_id')->get();
		$exc_location = array();
		foreach ($exception as $exc) {
			$exc_location[] = $exc->pl_code;
		}
		$category_id = $request->input('category_id');
		if (empty($category_id)) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Not found'
				]
			];
			return response()->json($data);
		}
		$product = DB::table('products')->selectRaw('ts_products.id as pid, ts_product_stocks.id as pstid, ts_product_locations.st_id as st_id,  sum(ts_product_location_setups.pls_qty) as stock, pc_name as category, br_name as brand, p_name as article, p_color as color, ps_price_tag, p_price_tag, ps_sell_price, p_sell_price, p_main_image, p_image')
			->leftJoin('product_stocks', 'product_stocks.p_id', '=', 'products.id')
			->leftJoin('product_categories', 'product_categories.id', '=', 'products.pc_id')
			->leftJoin('brands', 'brands.id', '=', 'products.br_id')
			->leftJoin('product_location_setups', 'product_location_setups.pst_id', '=', 'product_stocks.id')
			->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
			->whereNotIn('pl_code', $exc_location)
			->where('products.pc_id', '=', $category_id)
			->whereIn('product_locations.st_id', ['4', '5'])
			->groupBy('pid')
			->get();
		$results = array();
		if (!empty($product->first())) {
			foreach ($product as $row) {
				$price = null;
				$price_tag = null;
				$image_url = null;
				$image = array();
				if ($row->stock <= 0) {
					continue;
				}
				$image_url = "https://topsystem.id/api/product/600/";
				$image = ProductImageHelper::parseImages($row->p_main_image, $row->p_image);
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
				$check_discount = DB::table('product_discounts')->select('pd_type', 'pd_value')
					->leftJoin('product_discount_details', 'product_discount_details.pd_id', '=', 'product_discounts.id')
					->where('product_discounts.std_id', '=', '9')
					->where('product_discount_details.pst_id', '=', $row->pstid)
					//->where('product_discounts.pd_date', '>=', date('Y-m-d'))
					->where('pd_start', '<=', date("Y-m-d"))
					->where('pd_date', '>=', date("Y-m-d"))
					->where('pd_start_time', '<=', date("H:i:s"))
					->where('pd_end_time', '>=', date("H:i:s"))
					->get()->first();
				if (!empty($check_discount)) {
					$cd = $check_discount;
					$discount = 0;
					if ($cd->pd_type == 'percent') {
						$discount = $price_tag - (($price_tag / 100) * $cd->pd_value);
					} else if ($cd->pd_type == 'amount') {
						$discount = $price_tag - $cd->pd_value;
					} else {
						$discount = $price_tag;
					}
					$price = $discount;
				}
				if ($row->st_id == '4') {
					$store = 'URBAN';
				} else {
					$store = 'WHOLESALE';
				}
				$stock = DB::table('product_stocks')->selectRaw('ts_product_stocks.id as pid, ts_product_locations.st_id as st_id, sz_name as size, sum(ts_product_location_setups.pls_qty) as stock')
					->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
					->leftJoin('product_location_setups', 'product_location_setups.pst_id', '=', 'product_stocks.id')
					->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
					->whereNotIn('pl_code', $exc_location)
					->whereIn('product_locations.st_id', ['4', '5'])
					->where('product_stocks.p_id', '=', $row->pid)
					->groupBy('pid')
					->having('stock', '>', '0')
					->get();
				$details = array();
				foreach ($stock as $st) {
					$details[] = [
						'product_id' => $st->pid,
						'size' => $st->size,
						'stock' => $st->stock,
					];
				}
				$results[] = [
					'store' => $store,
					'category' => $row->category,
					'brand' => $row->brand,
					'article' => $row->article,
					'color' => $row->color,
					'price' => $price,
					'image_url' => $image_url,
					'image' => $image,
					'details' => $details
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
					'description' => 'Not found'
				]
			];
		}
		return response()->json($data);
	}

	public function getStockDetail(Request $request)
	{
		/*
		$client_id = $request->header('client_id');
		$api_key = $request->header('api_key');

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

		$exception = DB::table('exception_locations')->select('pl_code')
			->leftJoin('product_locations', 'product_locations.id', '=', 'exception_locations.pl_id')->get();
		$exc_location = array();
		foreach ($exception as $exc) {
			$exc_location[] = $exc->pl_code;
		}
		// get
		$product_id = $request->get('product_id');
		$product_color = $request->get('product_color');
		//$product_id = $request->input('product_id');
		if (empty($product_id)) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Not found'
				]
			];
			return response()->json($data);
		}

		// save log traffic
		$data_log = [
			'cust_id' => $request->cust_id,
			'ta_page_access' => "Detail Produk",
			//'ta_description' => "Mengakses laman detail Produk dengan warna ".$product_color,
			'ta_description' => "Mengakses laman detail Produk",
			'ta_lookup_id' => $request->product_id,
			'created_at' => date("Y-m-d H:i:s"),
			'ip_address' => $request->ip(),
		];
		$save_log = DB::table("traffic_apps")->insertGetId(array_merge($data_log));

		$cust_id = $request->get('cust_id');

		$scart = '';

		$stock = DB::table('product_stocks')->selectRaw('ts_product_stocks.id as pid, pc_name as category, ts_product_locations.st_id as st_id, br_name as brand, p_name as article, p_color as color, sz_name as size, sum(ts_product_location_setups.pls_qty) as stock, ps_price_tag, p_price_tag, ps_sell_price, p_sell_price, p_main_image, p_image, p_id, p_ratting, p_description, p_size_chart, sold, br_id, pssc_name')
			->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
			->leftJoin('product_categories', 'product_categories.id', '=', 'products.pc_id')
			->leftJoin('product_sub_sub_categories', 'product_sub_sub_categories.id', '=', 'products.pssc_id')
			->leftJoin('brands', 'brands.id', '=', 'products.br_id')
			->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
			->leftJoin('product_location_setups', 'product_location_setups.pst_id', '=', 'product_stocks.id')
			->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
			//->whereNotIn('pl_code', $exc_location) // laman detail aktifkan jika exception tidak dibaca
			//->whereIn('product_locations.st_id', ['2'])
			->whereNotIn('product_locations.st_id', ['5', '10', '11']) // selain store wholesale, Bazar dan DC
			->where('product_stocks.id', '=', $product_id)
			->where(function ($w) use ($request) {
				// cek jika ada store yang di pilih
				if (!empty($request->store_id)) {
					$w->where('product_locations.st_id', '=', $request->store_id);
					$w->where('product_location_setups.pls_qty', '>', '0');
				}
			})
			->groupBy('pid')
			->get()->first();

		if (empty($stock)) {
			// Auto-resolve jika client mengirim ID varian lama/off bekas migrasi
			$activePst = DB::table('product_stocks as old_ps')
				->join('product_stocks as active_ps', 'active_ps.p_id', '=', 'old_ps.p_id')
				->join('product_location_setups', 'product_location_setups.pst_id', '=', 'active_ps.id')
				->join('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
				->where('old_ps.id', '=', $product_id)
				->where(function ($q) {
					$q->where('active_ps.ps_delete', '!=', '1')
					  ->orWhereNull('active_ps.ps_delete');
				})
				->where(function ($q) {
					$q->where('active_ps.ps_barcode', 'NOT LIKE', '%_OLD_%')
					  ->orWhereNull('active_ps.ps_barcode');
				})
				->whereNotIn('product_locations.st_id', ['5', '10', '11'])
				->orderByDesc('product_location_setups.pls_qty')
				->value('active_ps.id');

			if (!empty($activePst)) {
				$product_id = $activePst;
				$stock = DB::table('product_stocks')->selectRaw('ts_product_stocks.id as pid, pc_name as category, ts_product_locations.st_id as st_id, br_name as brand, p_name as article, p_color as color, sz_name as size, sum(ts_product_location_setups.pls_qty) as stock, ps_price_tag, p_price_tag, ps_sell_price, p_sell_price, p_main_image, p_image, p_id, p_ratting, p_description, p_size_chart, sold, br_id, pssc_name')
					->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
					->leftJoin('product_categories', 'product_categories.id', '=', 'products.pc_id')
					->leftJoin('product_sub_sub_categories', 'product_sub_sub_categories.id', '=', 'products.pssc_id')
					->leftJoin('brands', 'brands.id', '=', 'products.br_id')
					->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
					->leftJoin('product_location_setups', 'product_location_setups.pst_id', '=', 'product_stocks.id')
					->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
					->whereNotIn('product_locations.st_id', ['5', '10', '11'])
					->where('product_stocks.id', '=', $product_id)
					->where(function ($w) use ($request) {
						if (!empty($request->store_id)) {
							$w->where('product_locations.st_id', '=', $request->store_id);
							$w->where('product_location_setups.pls_qty', '>', '0');
						}
					})
					->groupBy('pid')
					->get()->first();
			}
		}
		$results = array();
		if (!empty($stock)) {
			$stock = $stock;
			$price = null;
			$price_tag = null;
			$store = null;
			$image_url = "https://topsystem.id/api/product/600/";
			$image = ProductImageHelper::parseImages($stock->p_main_image, $stock->p_image);
			if (!empty($stock->ps_sell_price)) {
				$price = $stock->ps_sell_price;
			} else {
				$price = $stock->p_sell_price;
			}
			if (!empty($stock->ps_price_tag)) {
				$price_tag = $stock->ps_price_tag;
			} else {
				$price_tag = $stock->p_price_tag;
			}
			$check_discount = DB::table('product_discounts')->select('pd_type', 'pd_value')
				->leftJoin('product_discount_details', 'product_discount_details.pd_id', '=', 'product_discounts.id')
				->where('product_discounts.std_id', '=', '9')
				->where('product_discount_details.pst_id', '=', $stock->pid)
				//->where('product_discounts.pd_date', '>=', date('Y-m-d'))
				->where('pd_start', '<=', date("Y-m-d"))
				->where('pd_date', '>=', date("Y-m-d"))
				->where('pd_start_time', '<=', date("H:i:s"))
				->where('pd_end_time', '>=', date("H:i:s"))
				->get()->first();
			if (!empty($check_discount)) {
				$cd = $check_discount;
				$discount = 0;
				if ($cd->pd_type == 'percent') {
					$discount = $price_tag - (($price_tag / 100) * $cd->pd_value);
				} else if ($cd->pd_type == 'amount') {
					$discount = $price_tag - $cd->pd_value;
				} else {
					$discount = $price_tag;
				}
				$price = $discount;
			}
			/*
			if ($stock->st_id == '4') {
				$store = 'URBAN';
			} else {
				$store = 'WHOLESALE';
			}
			*/

			// Warna 
			$check_color = DB::table('product_stocks')
				->select('product_stocks.id', 'p_color', 'p_name', 'p_image', 'p_main_image', 'p_price_tag', 'p_sell_price', 'ps_price_tag', 'ps_sell_price')
				->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
				->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
				->leftJoin('product_location_setups', 'product_location_setups.pst_id', '=', 'product_stocks.id')
				->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
				->where('products.p_name', '=', $stock->article)
				->where('products.br_id', '=', $stock->br_id)
				->where('product_location_setups.pls_qty', '>', '0')
				->whereNotNull('products.p_main_image') // cek color
				->whereNotIn('pl_code', $exc_location)
				->whereNotIn('product_locations.st_id', ['5', '10', '11']) // selain store wholesale, Bazar dan DC
				// pencarian data
				->where(function ($w) {
					if (!empty($product_color)) {
						$w->where('products.p_color', '=', $product_color);
					}
				})
				->groupBy('products.p_color')
				->get();
			$color = array();
			if (!empty($check_color)) {
				foreach ($check_color as $row_color) {
					$color[] = $row_color->p_color;
				}
			}

			// gambar setiap produk di list item
			$color_image = array();
			if (!empty($check_color)) {
				// ponytail: batch promo per-warna sebelum loop (N+1 → 1 query)
				$color_ids = $check_color->pluck('id')->filter()->unique()->toArray();
				$color_promos = [];
				if (!empty($color_ids)) {
					$now_dt = date("Y-m-d H:i:s");
					$promo_records = DB::table("product_discount_details")
						->select('product_discount_details.pst_id', 'pd_value', 'pd_type', 'pd_flash_sale')
						->leftJoin('product_discounts', 'product_discounts.id', '=', 'product_discount_details.pd_id')
						->leftJoin('product_stocks', 'product_stocks.id', '=', 'product_discount_details.pst_id')
						->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
						->where('std_id', '=', '20')
						->whereRaw("CONCAT(ts_product_discounts.pd_start,' ',ts_product_discounts.pd_start_time)<='$now_dt'")
						->whereRaw("CONCAT(ts_product_discounts.pd_date,' ',ts_product_discounts.pd_end_time)>='$now_dt'")
						->whereIn('product_discount_details.pst_id', $color_ids)
						->where('products.p_delete', '=', '0')
						->orderBy('product_discounts.id', 'DESC')
						->get();
					foreach ($promo_records as $pr) {
						if (!isset($color_promos[$pr->pst_id])) {
							$color_promos[$pr->pst_id] = $pr;
						}
					}
				}

				foreach ($check_color as $row_color) {
					//$color_image[] = $row_color->p_color;
					// image list poduk...
					$image_details = ProductImageHelper::parseImages($row_color->p_main_image, $row_color->p_image);

					// cek harga diskon aktif sesuai artikel warna (from batch above)
					$arr_promo = $color_promos[$row_color->id] ?? null;

					$price_tag_color = $row_color->p_price_tag; // harga bandrol
					$sell_price_color = $row_color->p_sell_price; // harga jual
					$flag_buy1get1_color = 0;
					$pd_flash_sale_color = 0;
					//$persen_potongan_color = 0;

					// cek diskon
					if ($row_color->p_price_tag != $row_color->p_sell_price && $row_color->p_price_tag != '' && $row_color->p_sell_price != '') {
						$persen_potongan_color = (($row_color->p_price_tag - $row_color->p_sell_price) / $row_color->p_price_tag) * 100;
					} else {
						$persen_potongan_color = 0;
					}

					// cek diskon
					if ($arr_promo) {
						if ($arr_promo->pd_flash_sale == 1) {
							$pd_flash_sale_color = 1;
						} else {
							$pd_flash_sale_color = 0;
						}

						if ($arr_promo->pd_type == "percent") {
							$nilai_diskon_color = $price_tag_color * ($arr_promo->pd_value / 100);
							$sell_price_color = $price_tag_color - $nilai_diskon_color;
						} else
							if ($arr_promo->pd_type == "amount") {
								$sell_price_color = $price_tag_color - $arr_promo->pd_value;
							} else
								if ($arr_promo->pd_type == "fix") {
									$sell_price_color = $arr_promo->pd_value;
								} else
									if ($arr_promo->pd_type == "b1g1") { // jika B1G1
										$sell_price_color = $price_tag_color + 0000; // harga bandrol sebagai harga jual...
										$flag_buy1get1_color = 1;
										$price_tag_color = $sell_price_color;
									} else {
										$sell_price_color = $sell_price_color;
									}

						if ($price_tag_color != $sell_price_color) {
							$persen_potongan_color = 100 - (($sell_price_color / $price_tag_color) * 100);
						} else {
							$persen_potongan_color = 0;
						}
					}

					// cek customers
					$add_persen_color = 0;
					//if($request->cust_id && !$arr_promo && $persen_potongan_color<30){
					if ($request->cust_id && $persen_potongan_color < 30) {
						$arr_customer = DB::table("customers")->where("id", "=", $request->cust_id)->first();
						if ($arr_customer && $flag_buy1get1_color == 0) {
							// jika sebagai reseller
							if ($arr_customer->cust_reseller == 1 && $pd_flash_sale_color == 0) {
								if ($stock->brand == "TOPSCORE") {
									$add_persen_color = 20;
									$persen_potongan_color = 0;
									$sell_price_persen_color = ($price_tag_color * ($add_persen_color / 100));
									$sell_price_color = $price_tag_color - $sell_price_persen_color;
								} else {
									$add_persen_color = 5;
									$sell_price_persen_color = ($sell_price_color * ($add_persen_color / 100));
									$new_sell_price_color = $sell_price_color - $sell_price_persen_color;
									$sell_price_color = round($new_sell_price_color);
								}

							}
						}
					} else {
						$persen_potongan_color = $persen_potongan_color;
						$add_persen_color = 0;
					}
					// end harga diskon aktif sesuai artikel warna ***

					$color_image[] = [
						'color' => $row_color->p_color,
						'detail_color_image' => $image_details,
						'color_price_tag' => $price_tag_color,
						'color_sell_price' => $sell_price_color,
						'color_discount' => round($persen_potongan_color),
						'color_additional_discount' => $add_persen_color,
						'color_flag_buy1get1' => $flag_buy1get1_color,
						'color_label_promo' => ($flag_buy1get1_color == 1 ? 'B1G1' : ($persen_potongan_color > 0 ? round($persen_potongan_color) . '%' : '')),
					];
				}
			}

			// check size 
			$check_size = DB::table('product_stocks')->select('sz_id', 'sz_name')
				->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
				->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
				//->where('product_stocks.p_id', '=', $stock->p_id)
				// pencarian data
				->where(function ($w) use ($product_color, $stock) {
					if (!empty($product_color)) {
						$w->where('products.p_color', '=', $product_color);
						$w->where('product_stocks.p_id', '=', $stock->p_id);
					} else {
						$w->where('product_stocks.p_id', '=', $stock->p_id);
					}
				})
				//->groupBy('product_stocks.p_name')
				->groupBy('sizes.sz_name')
				->get();
			$size = array();
			if (!empty($check_size)) {
				foreach ($check_size as $row_size) {
					$size[] = $row_size->sz_name;
				}
			}

			// cek diskon
			if ($stock->p_price_tag != $stock->p_sell_price && $stock->p_price_tag != '' && $stock->p_sell_price != '') {
				$persen_potongan = (($stock->p_price_tag - $stock->p_sell_price) / $stock->p_price_tag) * 100;
			} else {
				$persen_potongan = 0;
			}

			// ratting produk
			/*
			$check_ratting = DB::table('user_ratings')->selectRaw('SUM(ts_user_ratings.ur_value) as total_value, COUNT(*) as jml')
				//->leftJoin('pos_transactions', 'pos_transactions.id', '=', 'user_ratings.pt_id')
				->leftJoin('pos_transaction_details', 'pos_transaction_details.pt_id', '=', 'user_ratings.pt_id')
				->leftJoin('product_stocks', 'product_stocks.id', '=', 'pos_transaction_details.pst_id')
				->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
				->where('products.p_name', '=', $stock->article)
				//->groupBy('product_stocks.p_name')
				->get()->first();
			if (!empty($check_ratting)) {
				if($check_ratting->total_value>0){
					$ratting = ($check_ratting->total_value / $check_ratting->jml);
					$ratting = round($ratting,2);
				} else {
					$ratting = 0;
				}
			} else {
				$ratting = 0;
			}
			*/

			if ($product_color == '') {
				$color_default = $stock->color;
			} else {
				$color_default = $product_color;
			}

			$check_store = DB::table('stores')->select('id', 'st_name', 'zip_id')
				//->where('product_stocks.p_id', '=', $stock->p_id)
				->whereNotIn('id', ['3', '4', '11', '10', '5'])
				// pencarian data
				/*
				->where(function($w) use($product_color,$stock) {
					if (!empty($product_color)) {
						$w->where('products.p_color', '=', $product_color);
					} else {
						$w->where('product_stocks.p_id', '=', $stock->p_id);
					}
				})
				//->groupBy('product_stocks.p_name')
				->groupBy('sizes.sz_name')
				*/
				->get();
			$store_data = array();
			if (!empty($check_store)) {
				foreach ($check_store as $row_store) {
					//$store_data['st_id'] = $row_store->st_name;
					$store_data[] = $row_store->st_name;
					//$store_data['zip_id'] = $row_store->zip_id;
				}
			}

			if (!empty($color[0])) {
				$default_c = $color[0];
			} else {
				$default_c = '';
			}
			// size cart 
			// https://topsystem.id/api/product/size_chart
			if ($stock->p_size_chart != '') {
				$schart = 'https://topsystem.id/api/product/size_chart/' . $stock->p_size_chart;
			} else {
				$schart = '';
			}

			// cek whistlist
			$arr_wishlist = DB::table("wishlists")->where('pst_id', $stock->pid)->where('cust_id', $cust_id)->where('ws_delete', '=', '0')->first();
			if ($arr_wishlist) {
				$flag_wishlist = 1;
			} else {
				$flag_wishlist = 0;
			}

			// cek diskon aktif apps
			$arr_promo = DB::table("product_discount_details")
				->select('product_discount_details.id', 'product_discount_details.pst_id', 'pd_value', 'pd_type', 'pd_flash_sale')
				->leftJoin('product_discounts', 'product_discounts.id', '=', 'product_discount_details.pd_id')
				->leftJoin('product_stocks', 'product_stocks.id', '=', 'product_discount_details.pst_id')
				->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
				->where('std_id', '=', '20') // kusus apps
				//->where('pd_date', '>=', date("Y-m-d"))
				/*
				->where('pd_start', '<=', date("Y-m-d"))
				->where('pd_date', '>=', date("Y-m-d"))
				->where('pd_start_time', '<=', date("H:i:s"))
				->where('pd_end_time', '>=', date("H:i:s"))
				*/
				->whereRaw("CONCAT(ts_product_discounts.pd_start,' ',ts_product_discounts.pd_start_time)<='" . date("Y-m-d H:i:s") . "'")
				->whereRaw("CONCAT(ts_product_discounts.pd_date,' ',ts_product_discounts.pd_end_time)>='" . date("Y-m-d H:i:s") . "'")
				->where('product_discount_details.pst_id', '=', $stock->pid)
				->where('products.p_delete', '=', '0')
				->orderBy('product_discounts.id', "DESC")
				->first();

			$price_tag = $stock->p_price_tag; // harga bandrol
			$sell_price = $stock->p_sell_price; // harga jual
			$flag_buy1get1 = 0;
			$pd_flash_sale = 0;
			// cek diskon
			if ($arr_promo) {
				if ($arr_promo->pd_flash_sale == 1) {
					$pd_flash_sale = 1;
				} else {
					$pd_flash_sale = 0;
				}

				if ($arr_promo->pd_type == "percent") {
					$nilai_diskon = $price_tag * ($arr_promo->pd_value / 100);
					$sell_price = $price_tag - $nilai_diskon;
				} else
					if ($arr_promo->pd_type == "amount") {
						$sell_price = $price_tag - $arr_promo->pd_value;
					} else
						if ($arr_promo->pd_type == "fix") {
							$sell_price = $arr_promo->pd_value;
						} else
							if ($arr_promo->pd_type == "b1g1") { // jika B1G1
								$sell_price = $price_tag + 0000; // harga bandrol sebagai harga jual...
								$flag_buy1get1 = 1;
								$price_tag = $sell_price;
							} else {
								$sell_price = $sell_price;
							}

				if ($price_tag != $sell_price) {
					$persen_potongan = 100 - (($sell_price / $price_tag) * 100);
				} else {
					$persen_potongan = 0;
				}
			}

			// cek ratting

			$query_ratting = DB::table("ratting_apps")
				->selectRaw('ts_ratting_apps.id as pid')
				->leftJoin('pos_transactions', 'pos_transactions.id', '=', 'ratting_apps.lookup_id')
				->leftJoin('pos_transaction_details', 'pos_transaction_details.pt_id', '=', 'pos_transactions.id')
				->leftJoin('product_stocks', 'product_stocks.id', '=', 'pos_transaction_details.pst_id')
				->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
				->leftJoin('customers', 'customers.id', '=', 'ratting_apps.cust_id')
				->where('ra_type', '=', 'pos')
				->where('products.p_name', '=', $stock->article)
				->get();

			$ratting_produk = 5;
			// jika ada ulasan
			if (count($query_ratting) > 0) {
				$check_ratting = DB::table('user_ratings')
					->selectRaw('SUM(ts_user_ratings.ur_value) as total_value, COUNT(*) as jml')
					->leftJoin('pos_transaction_details', 'pos_transaction_details.pt_id', '=', 'user_ratings.pt_id')
					->leftJoin('product_stocks', 'product_stocks.id', '=', 'pos_transaction_details.pst_id')
					->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
					->where('products.p_name', '=', $stock->article)
					->get()->first();
				if (!empty($check_ratting)) {
					if ($check_ratting->total_value > 0) {
						$ratting_check = ($check_ratting->total_value / $check_ratting->jml);
						$ratting_produk = round($ratting_check);
					} else {
						$ratting_produk = $ratting_produk;
					}
				} else {
					$ratting_produk = $ratting_produk;
				}
			} else {
				$ratting_produk = $ratting_produk;
			}

			$cashback_coin = (1 / 100) * $sell_price;
			if ($flag_buy1get1 == 1) {
				$label_promo = 'B1G1';
			} else {
				$label_promo = '';
			}

			// cek customers
			$add_persen = 0;
			//if($request->cust_id && !$arr_promo && $persen_potongan<30){
			if ($request->cust_id && $persen_potongan < 30) {
				$arr_customer = DB::table("customers")->where("id", "=", $request->cust_id)->first();
				if ($arr_customer && $flag_buy1get1 == 0) {
					// jika sebagai reseller
					if ($arr_customer->cust_reseller == 1 && $pd_flash_sale == 0) {
						// kusus brand topscore {$row->brand}
						if ($stock->brand == "TOPSCORE") {
							//$add_persen = 0;
							//$persen_potongan = 20;
							//$sell_price_persen = ($price_tag * ($persen_potongan/100)); // default reseller 20%
							$add_persen = 20;
							$persen_potongan = 0;
							$sell_price_persen = ($price_tag * ($add_persen / 100)); // default reseller 20%
							$sell_price = $price_tag - $sell_price_persen;
						} else {
							$add_persen = 5;
							$sell_price_persen = ($sell_price * ($add_persen / 100)); // default reseller 5%
							$new_sell_price = $sell_price - $sell_price_persen;
							$sell_price = round($new_sell_price);
						}

					}
				}
			} else {
				$persen_potongan = $persen_potongan;
				$add_persen = 0;
			}

			if ($stock->brand == "TOPSCORE") {
				$flag_sold = 0;
			} else {
				$flag_sold = 1;
			}
			$results[] = [
				'product_id' => $stock->pid,
				//'store' => $store,
				//'category' => $stock->category,
				'category' => $stock->pssc_name,
				'brand' => $stock->brand,
				'article' => $stock->article,
				//'default_color' => $color_default, //$stock->color,
				'default_color' => $default_c,
				'color' => $color, //$stock->color,
				'color_image' => $color_image,
				'size' => $size,
				//'stock' => $stock->stock,
				//'price' => $price,
				//'price_tag' => $stock->p_price_tag,
				//'sell_price' => $stock->p_sell_price,
				'price_tag' => $price_tag,
				'sell_price' => $sell_price,
				'sold' => $stock->sold,
				'stock' => '',
				'discount' => round($persen_potongan),
				'additional_discount' => round($add_persen),
				//'ratting' => round($stock->p_ratting,2), //$ratting,
				//'total_ulasan' => 0,
				'ratting' => $ratting_produk,
				'total_ulasan' => count($query_ratting),
				'wishlist' => $flag_wishlist,
				'description' => $stock->p_description,
				'size_chart' => $schart,
				'store_data' => $store_data,
				'image_url' => $image_url,
				'image' => $image,
				'flag_buy1get1' => $flag_buy1get1,
				'cashback_coin' => round($cashback_coin),
				'label_promo' => $label_promo,
				'voucher_produk' => $this->voucher_produk($stock->pid, $sell_price, $request->cust_id),
				'flag_sold' => $flag_sold,
				//'flag_sold' => 1,
			];
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
					'description' => 'Not found'
				]
			];
		}
		return response()->json($data);
	}

	// cek voucher produk
	private function voucher_produk($pst_id, $sell_price, $cust_id)
	{
		$now_dt = date("Y-m-d H:i:s");
		$query_data = DB::table("apps_vouchers")
			->selectRaw('ts_apps_vouchers.id as pid, avc_code, avc_discount, avc_min_order, avc_type, avc_status, avc_cashback, avc_due_date, created_at, avc_title, avc_title, avc_banner, avc_terms, avc_category, avc_to')
			->whereRaw("CONCAT(ts_apps_vouchers.avc_start,' ',ts_apps_vouchers.acv_start_time)<='$now_dt'")
			->whereRaw("CONCAT(ts_apps_vouchers.avc_due_date,' ',ts_apps_vouchers.avc_end_time)>='$now_dt'")
			->where('avc_status', '=', '1')
			->where('avc_delete', '=', '0')
			->orderBy('avc_code')
			->get();
		$results = array();
		if (empty($query_data) || $query_data->isEmpty()) {
			return $results;
		}

		// ponytail: batch semua cek eligibility sebelum loop (N+1 → 2 queries)
		$all_voucher_ids = $query_data->pluck('pid')->toArray();
		// batch: voucher customer yang dimiliki user ini
		$cust_voucher_ids = [];
		$used_voucher_ids = [];
		if ($cust_id) {
			$cust_voucher_ids = DB::table("apps_voucher_customers")
				->where("cust_id", "=", $cust_id)
				->whereIn("avc_id", $all_voucher_ids)
				->where("avcust_status", "=", "1")
				->where("avcust_delete", "=", "0")
				->pluck('avc_id')->toArray();
			// batch: voucher yang sudah pernah dipakai user ini
			$used_voucher_ids = DB::table("pos_transactions")
				->where("cust_id", "=", $cust_id)
				->whereIn("voucher_apps_id", $all_voucher_ids)
				->pluck('voucher_apps_id')->toArray();
		}
		// batch: voucher khusus produk ini
		$product_voucher_ids = DB::table("apps_voucher_products")
			->leftJoin('product_stocks', 'product_stocks.p_id', '=', 'apps_voucher_products.p_id')
			->where('product_stocks.id', '=', $pst_id)
			->where("avp_delete", "=", "0")
			->whereIn("avc_id", $all_voucher_ids)
			->pluck('avc_id')->toArray();
		// batch: info customer (untuk cek ultah) — hanya 1 query
		$arr_customer = $cust_id ? DB::table("customers")->where("id", "=", $cust_id)->first() : null;
		// batch: voucher ultah yang sudah dipakai tahun ini
		$used_ultah_ids = [];
		if ($arr_customer) {
			$used_ultah_ids = DB::table("pos_transactions")
				->where("cust_id", "=", $cust_id)
				->whereIn("voucher_apps_id", $all_voucher_ids)
				->whereRaw("LEFT(ts_pos_transactions.created_at,4)='" . date("Y") . "'")
				->pluck('voucher_apps_id')->toArray();
		}

		foreach ($query_data as $row) {
			// cek customer kusus customer
			if ($row->avc_to == "customer") {
				if ($cust_id) {
					if (in_array($row->pid, $cust_voucher_ids)) {
						$show = in_array($row->pid, $used_voucher_ids) ? 0 : 1;
					} else {
						$show = 0;
					}
				} else {
					$show = 0;
				}
			} else
				if ($row->avc_to == "produk") {
					$show = in_array($row->pid, $product_voucher_ids) ? 1 : 0;
				} else
					// kusus ulang tahun
					if ($row->avc_to == "ultah") {
						if ($arr_customer) {
							if ($arr_customer->cust_bday && !in_array($row->pid, $used_ultah_ids)) {
								$show = (substr($arr_customer->cust_bday, -5) == date("m-d")) ? 1 : 0;
							} else {
								$show = 0;
							}
						} else {
							$show = 0;
						}
					} else {
						$show = 1;
					}

			// check product stock
			if (!empty($row->avc_banner)) {
				$banner = $this->url_server . 'upload/event/' . $row->avc_banner;
			} else {
				$banner = '';
			}

			if ($show == 1 && $row->avc_min_order <= $sell_price) {
				//if($show==1){
				$results[] = [
					'voucher_id' => $row->pid,
					'category' => $row->avc_category,
					'discount_title' => $row->avc_title,
					'discount' => $row->avc_discount,
					'min_order' => $row->avc_min_order,
					'discount_type' => $row->avc_type,
					'cashback' => (int) $row->avc_cashback,
					'status_discount' => $row->avc_status,
					'banner' => $banner,
					'terms' => $row->avc_terms,
					'due_date' => $row->avc_due_date,
				];
			}
		}

		//return response()->json($results);
		return $results;
	}

	public function AddCart(Request $request)
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

		$product_id = $request->product_id;
		$cust_id = $request->cust_id;
		$product_qty = $request->product_qty;
		$store = $request->store_id; // new
		$color = $request->color;
		$size = $request->size;
		$product_name = $request->product_name;
		// cek stok
		$arr_pst = DB::table('product_stocks')->select('product_stocks.id')
			->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
			->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
			->where('products.p_name', '=', $product_name)
			->where('products.p_color', '=', $color)
			->where('sizes.sz_name', '=', $size)
			->where('products.p_delete', '=', '0')
			->get()->first();
		if ($arr_pst) {
			$pst_id = $arr_pst->id;
		} else {
			//$pst_id = $product_id;
			$pst_id = NULL;
		}

		// jika artikel tidak tersedia stok
		if ($pst_id == NULL) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Stock tidak tersedia'
				],
				//'results' => $results
			];
			return response()->json($data);
		}

		$arr_store = DB::table('stores')->select('id')
			->where('st_name', '=', $store)
			->get()->first();

		if (empty($product_id)) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Not found'
				]
			];
			return response()->json($data);
		}

		if (empty($cust_id)) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Not found'
				]
			];
			return response()->json($data);
		}

		$check_cart = DB::table('apps_carts')->select('id')
			->where('cust_id', '=', $cust_id)
			->where('pst_id', '=', $pst_id)
			//->where('st_id', '=', $arr_store->id)
			->where('st_id', $arr_store->id ?? null)
			->get()->first();
		if (!empty($check_cart)) {
			// edit data
			$id = $check_cart->id;
			$mode = 'edit';
			//$product_qty_new = $check_cart->qty + $product_qty;
			$product_qty_new = $product_qty;
		} else {
			// data baru 
			$id = '';
			$mode = 'add';
			$product_qty_new = $product_qty;
		}

		// save chart
		$data = [
			'cust_id' => $cust_id,
			'pst_first' => $product_id,
			'pst_id' => $pst_id,
			'qty' => $product_qty_new,
			//'st_id' => $store,
			'st_id' => $arr_store->id ?? null,
			'color' => $color,
			'size' => $size,
			'product_name' => $product_name,
		];

		$results = array();
		if ($this->SaveChart($mode, $id, $data)) {

			// save log add cart
			$data_log = [
				'cust_id' => $request->cust_id,
				'ta_page_access' => "Tambah Keranjang",
				'ta_description' => "Mengakses laman Produk dan memasukan kedalam keranjang",
				'ta_lookup_id' => $pst_id,
				'created_at' => date("Y-m-d H:i:s"),
				'ip_address' => $request->ip(),
			];
			$save_log = DB::table("traffic_apps")->insertGetId(array_merge($data_log));

			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success'
				],
				//'results' => $results
			];
		} else {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Not found'
				]
			];
		}

		return response()->json($data);
	}

	private function SaveChart($mode, $id, $data)
	{
		$created = [
			'created_at' => date('Y-m-d H:i:s')
		];
		$updated = [
			'updated_at' => date('Y-m-d H:i:s')
		];
		if ($mode == 'add') {
			$save = DB::table("apps_carts")->insert(array_merge($data, $created));
			return $save;
		} else if ($mode == 'edit') {
			try {
				$save = DB::table('apps_carts')->where('id', $id)->update(array_merge($data, $updated));
				return $save;
			} catch (\Illuminate\Database\QueryException $ex) {
				if ($ex->getCode() === '23000') {
					return false;
				}
			}
		} else {
			return false;
		}
	}

	// check cart table
	public function getCarts(Request $request)
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

		$setting_apps = DB::table("setting_apps")->first();

		$pst_id = $request->product_id;
		$cust_id = $request->cust_id;
		//$product_qty = $request->product_qty;

		$stock = DB::table('apps_carts')->selectRaw('ts_apps_carts.id as pid, pc_name as category, br_name as brand, p_name as article, p_color as color, sz_name as size, ps_price_tag, p_price_tag, ps_sell_price, p_sell_price, p_main_image, p_image, qty, pst_id, st_id, st_name, zip_id, area_id_biteship, st_lat, st_lang, p_id')
			->leftJoin('product_stocks', 'product_stocks.id', '=', 'apps_carts.pst_id')
			->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
			->leftJoin('product_categories', 'product_categories.id', '=', 'products.pc_id')
			->leftJoin('brands', 'brands.id', '=', 'products.br_id')
			->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
			->leftJoin('stores', 'stores.id', '=', 'apps_carts.st_id')
			->where('p_delete', '=', '0')
			->where('apps_carts.cust_id', '=', $cust_id)
			//->where('apps_carts.pst_id', '=', $pst_id)
			//->groupBy('products.p_name')
			//->limit('100')
			->orderBy('apps_carts.created_at', 'DESC')
			->get();
		$results = array();
		if (!empty($stock)) {
			$pst_ids = $stock->pluck('pst_id')->filter()->unique()->toArray();
			$st_ids = $stock->pluck('st_id')->filter()->unique()->toArray();

			$discounts_by_pst = [];
			$promos_by_pst = [];
			if (!empty($pst_ids)) {
				$now_dt = date("Y-m-d H:i:s");
				$all_discounts = DB::table('product_discounts')->select('pd_type', 'pd_value', 'product_discount_details.pst_id')
					->leftJoin('product_discount_details', 'product_discount_details.pd_id', '=', 'product_discounts.id')
					->where('product_discounts.std_id', '=', '20')
					->whereIn('product_discount_details.pst_id', $pst_ids)
					->whereRaw("CONCAT(ts_product_discounts.pd_start,' ',ts_product_discounts.pd_start_time)<='$now_dt'")
					->whereRaw("CONCAT(ts_product_discounts.pd_date,' ',ts_product_discounts.pd_end_time)>='$now_dt'")
					->get();
				foreach ($all_discounts as $cd) {
					if (!isset($discounts_by_pst[$cd->pst_id])) {
						$discounts_by_pst[$cd->pst_id] = $cd;
					}
				}

				$all_promos = DB::table("product_discount_details")
					->select('product_discount_details.id', 'product_discount_details.pst_id', 'pd_value', 'pd_type')
					->leftJoin('product_discounts', 'product_discounts.id', '=', 'product_discount_details.pd_id')
					->leftJoin('product_stocks', 'product_stocks.id', '=', 'product_discount_details.pst_id')
					->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
					->where('std_id', '=', '20')
					->whereRaw("CONCAT(ts_product_discounts.pd_start,' ',ts_product_discounts.pd_start_time)<='$now_dt'")
					->whereRaw("CONCAT(ts_product_discounts.pd_date,' ',ts_product_discounts.pd_end_time)>='$now_dt'")
					->whereIn('product_stocks.id', $pst_ids)
					->where('products.p_delete', '=', '0')
					->get();
				foreach ($all_promos as $promo) {
					if (!isset($promos_by_pst[$promo->pst_id])) {
						$promos_by_pst[$promo->pst_id] = $promo;
					}
				}
			}

			$stock_by_pst_st = [];
			if (!empty($pst_ids) && !empty($st_ids)) {
				$all_stok = DB::table('product_location_setups')
					->select('product_location_setups.pls_qty', 'product_location_setups.pst_id', 'product_locations.st_id')
					->leftJoin('product_stocks', 'product_stocks.id', '=', 'product_location_setups.pst_id')
					->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
					->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
					->whereIn('product_location_setups.pst_id', $pst_ids)
					->where('product_location_setups.pls_qty', '>', '0')
					->whereIn('product_locations.st_id', $st_ids)
					->where('products.p_delete', '=', '0')
					->get();
				foreach ($all_stok as $stk) {
					$key = $stk->pst_id . '|||' . $stk->st_id;
					if (!isset($stock_by_pst_st[$key])) {
						$stock_by_pst_st[$key] = 0;
					}
					$stock_by_pst_st[$key] += $stk->pls_qty;
				}
			}

			foreach ($stock as $row) {
				$price = null;
				$price_tag = null;
				$image_url = null;
				$store = null;
				$image = array();

				$image_url = "https://topsystem.id/api/product/600/";
				$image = ProductImageHelper::parseImages($row->p_main_image, $row->p_image);
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
				$check_discount = isset($discounts_by_pst[$row->pst_id]) ? $discounts_by_pst[$row->pst_id] : (isset($discounts_by_pst[$row->pid]) ? $discounts_by_pst[$row->pid] : null);
				if (!empty($check_discount)) {
					$cd = $check_discount;
					$discount = 0;
					if ($cd->pd_type == 'percent') {
						$discount = $price_tag - (($price_tag / 100) * $cd->pd_value);
					} else if ($cd->pd_type == 'amount') {
						$discount = $price_tag - $cd->pd_value;
					} else {
						$discount = $price_tag;
					}
					$price = $discount;
				}

				// persentase potongan harga
				if ($row->p_price_tag != $row->p_sell_price && $row->p_price_tag != '' && $row->p_sell_price != '') {
					$persen_potongan = (($row->p_price_tag - $row->p_sell_price) / $row->p_price_tag) * 100;
				} else {
					$persen_potongan = 0;
				}

				// CEK STOK TERSEDIA
				$stk_key = $row->pst_id . '|||' . $row->st_id;
				$stok_tersedia = isset($stock_by_pst_st[$stk_key]) ? $stock_by_pst_st[$stk_key] : 0;

				// cek diskon aktif apps
				$arr_promo = isset($promos_by_pst[$row->pst_id]) ? $promos_by_pst[$row->pst_id] : null;

				$price_tag = $row->p_price_tag; // harga bandrol
				$sell_price = $row->p_sell_price; // harga jual
				$flag_buy1get1 = 0;
				// cek diskon
				if ($arr_promo) {
					if ($arr_promo->pd_type == "percent") {
						$nilai_diskon = $price_tag * ($arr_promo->pd_value / 100);
						$sell_price = $price_tag - $nilai_diskon;
					} else
						if ($arr_promo->pd_type == "amount") {
							$sell_price = $price_tag - $arr_promo->pd_value;
						} else
							if ($arr_promo->pd_type == "fix") {
								$sell_price = $arr_promo->pd_value;
							} else
								if ($arr_promo->pd_type == "b1g1") { // jika B1G1
									$sell_price = $price_tag + 0000; // harga bandrol sebagai harga jual...
									$flag_buy1get1 = 1;
									$price_tag = $sell_price;
								} else {
									$sell_price = $sell_price;
								}

					if ($price_tag != $sell_price) {
						$persen_potongan = 100 - (($sell_price / $price_tag) * 100);
					} else {
						$persen_potongan = 0;
					}
				}

				if ($stok_tersedia >= $row->qty) {
					if ($flag_buy1get1 == 1) {
						$label_promo = 'B1G1';
					} else {
						$label_promo = '';
					}

					// cek IPB RUN
					$jersey_ipb = 0;
					if (in_array($row->p_id, ['13797', '13798', '13799', '13800', '13814', '13815'])) {
						$jersey_ipb++;
					}

					// cek customers
					$add_persen = 0;
					if ($cust_id && $persen_potongan < 30 && $jersey_ipb == 0) {
						$arr_customer = DB::table("customers")->where("id", "=", $cust_id)->first();
						if ($arr_customer && $flag_buy1get1 == 0) {
							// jika sebagai reseller
							if ($arr_customer->cust_reseller == 1) {
								// kusus brand topscore {$row->brand}
								if ($row->brand == "TOPSCORE") {
									//$add_persen = 0;
									//$persen_potongan = 20;
									//$sell_price_persen = ($price_tag * ($persen_potongan/100)); // default reseller 20%
									$add_persen = 20;
									$persen_potongan = 0;
									$sell_price_persen = ($price_tag * ($add_persen / 100)); // default reseller 20%
									$sell_price = $price_tag - $sell_price_persen;
								} else {
									$add_persen = 5;
									$sell_price_persen = ($sell_price * ($add_persen / 100)); // default reseller 5%
									$new_sell_price = $sell_price - $sell_price_persen;
									$sell_price = round($new_sell_price);
								}

							}
						}
					} else {
						$persen_potongan = $persen_potongan;
						$add_persen = 0;
					}

					if ($row->brand == "TOPSCORE") {
						$flag_sold = 0;
					} else {
						$flag_sold = 1;
					}
					//if(1==1){
					$results[] = [
						'cart_id' => $row->pid, // tambahan ID
						'product_id' => $row->pst_id,
						'store_id' => $row->st_id,
						'store_name' => $row->st_name,
						'store_lat' => $row->st_lat,
						'store_lang' => $row->st_lang,
						'zip_id' => $row->zip_id,
						'area_id_biteship' => $row->area_id_biteship,
						'category' => $row->category,
						'brand' => $row->brand,
						'article' => $row->article,
						'color' => $row->color,
						'size' => $row->size,
						'qty' => $row->qty,
						'stock' => $stok_tersedia,
						//'price_tag' => $row->p_price_tag,
						//'sell_price' => $row->p_sell_price,
						'price_tag' => $price_tag,
						'sell_price' => $sell_price,
						'discount' => round($persen_potongan),
						'additional_discount' => round($add_persen),
						'ratting' => 5, //$ratting,
						'total_ulasan' => 0,
						'image_url' => $image_url,
						'image' => $image,
						'flag_buy1get1' => $flag_buy1get1,
						'label_promo' => $label_promo,
						'flag_sold' => $flag_sold,
					];
				}
			}
		}

		// ponytail: B1G1 pair calculation — grouped per store; for every 2 B1G1 items in same store, the cheaper one is free
		$b1g1_by_store = [];
		foreach ($results as $idx => $item) {
			if (!empty($item['flag_buy1get1']) && $item['flag_buy1get1'] == 1 && $item['qty'] > 0) {
				$sid = $item['store_id'] ?? 0;
				for ($u = 0; $u < $item['qty']; $u++) {
					$b1g1_by_store[$sid][] = [
						'result_idx' => $idx,
						'price' => (float)$item['price_tag'],
					];
				}
			}
		}

		foreach ($b1g1_by_store as $sid => $b1g1_units) {
			$total_b1g1 = count($b1g1_units);
			if ($total_b1g1 >= 2) {
				usort($b1g1_units, function ($a, $b) {
					return $b['price'] <=> $a['price'];
				});

				$free_count = (int)floor($total_b1g1 / 2);
				for ($i = $total_b1g1 - $free_count; $i < $total_b1g1; $i++) {
					$b1g1_units[$i]['price'] = 0;
				}

				$new_totals = [];
				foreach ($b1g1_units as $unit) {
					$r_idx = $unit['result_idx'];
					$new_totals[$r_idx] = ($new_totals[$r_idx] ?? 0) + $unit['price'];
				}

				foreach ($new_totals as $r_idx => $tot) {
					$qty = $results[$r_idx]['qty'];
					// B1G1: show full price_tag as sell_price, total saving reflected in discount
					// Example: qty=2, price=999900 → total paid=999900 (1 free), avg per unit=499950
					// We keep sell_price = price_tag (full) so display shows "buy 1 get 1 free"
					// but pass the actual total so checkout calculates correctly
					$pt = $results[$r_idx]['price_tag'];
					$results[$r_idx]['sell_price'] = $pt; // tetap harga penuh per unit
					$results[$r_idx]['b1g1_total'] = $tot; // total yang harus dibayar (1 unit gratis)
					$results[$r_idx]['b1g1_free_count'] = $free_count;
					if ($pt > 0 && $qty > 0) {
						// diskon efektif = berapa % hemat dari total normal
						$normal_total = $pt * $qty;
						$results[$r_idx]['discount'] = round((($normal_total - $tot) / $normal_total) * 100);
					}
				}
			}
		}

		$data = [
			'status' => [
				'code' => 200,
				'description' => 'Success'
			],
			'benefit_info' => $setting_apps->benefit_info,
			'results' => $results
		];
		return response()->json($data);
	}

	// update QTY
	public function UpdateQtyCart(Request $request)
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

		$cart_id = $request->cart_id;
		$cart_qty = $request->cart_qty;
		$cust_id = $request->cust_id;

		if (empty($cart_id)) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Not found'
				]
			];
			return response()->json($data);
		}

		if (empty($cust_id)) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Not found'
				]
			];
			return response()->json($data);
		}

		$check_cart = DB::table('apps_carts')->select('id', 'qty')
			->where('id', '=', $cart_id)
			->first();
		if (!empty($check_cart)) {
			if ($cart_qty > 0) {
				$update_data = DB::table('apps_carts')->where('id', $cart_id)->where('cust_id', $cust_id)->update(['qty' => $cart_qty]);
			} else {
				$update_data = DB::table('apps_carts')->where('id', $cart_id)->delete();
			}
		} else {
			$delete = '';
			$update_data = '';
		}

		$results = array();
		if ($update_data) {
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success'
				],
			];
		} else {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Not found'
				]
			];
		}

		return response()->json($data);
	}

	//delete cart
	public function DeleteCart(Request $request)
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

		$product_id = $request->product_id;
		$cust_id = $request->cust_id;
		//$store = $request->store_id;
		/*
		if (empty($product_id)) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Not found'
				]
			];
			return response()->json($data);
		}
		*/

		if (empty($cust_id)) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Not found'
				]
			];
			return response()->json($data);
		}

		/*
		$check_cart = DB::table('apps_carts')->select('id','qty')
					->where('cust_id', '=', $cust_id)
					->where('pst_id', '=', $product_id)
					->get()->first();
		   if (!empty($check_cart)) {
			$delete = DB::table('apps_carts')->where('id', $check_cart->id)->delete();
		} else {
			$delete = '';
		}
		*/

		$delete = '';
		if ($product_id) {
			$check_cart = DB::table('apps_carts')->select('id', 'qty')
				->where('cust_id', '=', $cust_id)
				->where('pst_id', '=', $product_id)
				->first();
			if (!empty($check_cart)) {
				$delete = DB::table('apps_carts')->where('id', $check_cart->id)->delete();
			}
		} else {
			$delete = DB::table('apps_carts')->where('cust_id', $cust_id)->delete();
		}

		//$delete = DB::table('apps_carts')->where('id', $check_cart->id)->delete();

		$results = array();
		if ($delete) {
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success'
				],
			];
		} else {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Not found'
				]
			];
		}

		return response()->json($data);
	}

	// Terakkhir dilihat
	public function storeArticle(Request $request)
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

		$product_id = $request->product_id;
		$product_name = $request->product_name;
		// 		$color = $request->color;
		$cust_id = $request->cust_id;
		if ($product_id != '' && $product_name != '' && $cust_id != '') {
			$arr_product = DB::table('products')->select('products.id', 'p_name')
				//->leftJoin('product_stocks', 'product_stocks.id', '=', 'product_location_setups.pst_id')
				->where('products.p_name', '=', $product_name)
				// 			->where('products.p_color', '=', $color)
				->where('products.p_delete', '=', '0')
				->get()->first();
			$data = [
				'pst_id' => $product_id,
				'p_id' => $arr_product->id,
				'pva_name' => $product_name,
				// 'pva_color' => $color,
				'cust_id' => $cust_id,
				'created_at' => date("Y-m-d H:i:s"),
			];

			$saves = DB::table("product_view_apps")->insertGetId(array_merge($data));

			if ($saves) {
				$r['status'] = "200";
				$r['message'] = "Berhasil menyimpan data";
			} else {
				$r['status'] = "401";
				$r['message'] = "Gagal menyimpan data";
			}
		} else {
			$r['status'] = "400";
			$r['message'] = "Data form isian tidak boleh kosong";
		}

		return response()->json($r);
	}

	// Cek size artikel yang stok nya tersedia di store ********************** //
	public function getStocksize(Request $request)
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

		$exception = DB::table('exception_locations')->select('pl_code')
			->leftJoin('product_locations', 'product_locations.id', '=', 'exception_locations.pl_id')->get();
		$exc_location = array();
		foreach ($exception as $exc) {
			$exc_location[] = $exc->pl_code;
		}
		// get ...
		$product_id = $request->get('product_id');
		$product_color = $request->get('product_color');
		if (empty($product_id) || empty($product_color)) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Field product_id and product_color is required'
				]
			];
			return response()->json($data);
		}

		// cek produk stok
		$arr_pst = DB::table('product_stocks')->select('product_stocks.id', 'p_id', 'sz_id', 'p_name', 'p_color')
			->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
			->where('product_stocks.id', '=', $product_id)
			//->where('products.p_color', '=', $product_color)
			->where('products.p_delete', '=', '0')
			->groupBy(['products.p_name', 'products.p_color'])
			->get()->first();
		if (empty($arr_pst)) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data artikel not found!',
				]
			];
			return response()->json($data);
		}

		$stock = DB::table('product_stocks')->selectRaw('ts_product_stocks.id as pid, pc_name as category, ts_product_locations.st_id as st_id, br_name as brand, p_name as article, p_color as color, sz_name as size, sum(ts_product_location_setups.pls_qty) as stock, ps_price_tag, p_price_tag, ps_sell_price, p_sell_price, p_main_image, p_image, p_id, p_ratting, p_description')
			->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
			->leftJoin('product_categories', 'product_categories.id', '=', 'products.pc_id')
			->leftJoin('brands', 'brands.id', '=', 'products.br_id')
			->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
			->leftJoin('product_location_setups', 'product_location_setups.pst_id', '=', 'product_stocks.id')
			->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
			->whereNotIn('pl_code', $exc_location)
			->whereNotIn('product_locations.st_id', ['4', '5', '10', '11']) // selain store wholesale, Bazar dan DC
			//->where('product_stocks.id', '=', $product_id)
			//->where('products.id', '=', $arr_pst->p_id) //
			->where('products.p_name', '=', $arr_pst->p_name) //
			->where('products.p_color', '=', $product_color)
			->where('product_location_setups.pls_qty', '>', '0')
			->where(function ($w) use ($request) {
				// cek jika ada store yang di pilih
				if (!empty($request->store_id)) {
					$w->where('product_locations.st_id', '=', $request->store_id);
				}
			})
			->groupBy('sizes.sz_name')
			->get();
		$results = array();
		//$store = array();
		if (!empty($stock)) {
			foreach ($stock as $raw) {
				// cek spesial produk 
				$spesial_produk = DB::table("product_special_details")
					->select('product_special_details.id', 'pds_id', 'pds_qty', 'pds_repeat')
					->leftJoin('product_specials', 'product_specials.id', '=', 'product_special_details.pds_id')
					->leftJoin('products', 'products.id', '=', 'product_special_details.p_id')
					->where("product_special_details.p_id", "=", $raw->p_id)
					->where("product_specials.pds_status", "=", "1")
					->where("p_delete", "=", "0")
					->where("pds_delete", "=", "0")
					->where("pdsd_delete", "=", "0")
					->first();

				// bandingkan data jika sudah pernah order produk spesial, batasi qty nya
				if ($spesial_produk) {
					if ($spesial_produk->pds_qty <= $raw->stock) {
						$maks_stock = $spesial_produk->pds_qty;
					} else {
						$maks_stock = $raw->stock;
					}
				} else {
					$maks_stock = $raw->stock;
				}

				// cek orderan terbaru ditanggal berapa?
				if ($request->cust_id) {
					$user_order = DB::table("pos_transaction_details")
						->select('pos_transaction_details.id', 'pos_transaction_details.created_at')
						->leftJoin('pos_transactions', 'pos_transactions.id', '=', 'pos_transaction_details.pt_id')
						->leftJoin('product_stocks', 'product_stocks.id', '=', 'pos_transaction_details.pst_id')
						->where("product_stocks.p_id", "=", $raw->p_id)
						->where("pos_transactions.cust_id", "=", $request->cust_id)
						->whereNotIn('pos_transactions.pos_status', ['UNPAID', 'CANCEL'])
						->orderBy("pos_transaction_details.created_at", "DESC")
						->first();
					// cek user order terakhir 
					if ($user_order && $spesial_produk) {
						$tgl_orderan = substr($user_order->created_at, 0, 10);
						$repeat_day = $spesial_produk->pds_repeat; // berapa hari baru boleh repeat order kembali...
						$tanggal_stok = date('Y-m-d', strtotime($tgl_orderan . "+" . $repeat_day . " days"));
						if ($tanggal_stok < date("Y-m-d")) {
							$maks_stock = $maks_stock;
						} else {
							$maks_stock = 0;
						}
					} else {
						$maks_stock = $maks_stock;
						//$tanggal_stok = '';
					}
				}


				// cek store tersedia artikel
				$arr_locations = DB::table('product_locations')
					->selectRaw('ts_product_locations.id, st_name, st_id, sum(ts_product_location_setups.pls_qty) as stock, area_id_biteship, zip_id, st_address, st_lat, st_lang, st_maps')
					->leftJoin('product_location_setups', 'product_location_setups.pl_id', '=', 'product_locations.id')
					->leftJoin('stores', 'stores.id', '=', 'product_locations.st_id')
					->leftJoin('product_stocks', 'product_stocks.id', '=', 'product_location_setups.pst_id')
					->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
					->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
					->where('product_location_setups.pls_qty', '>', '0')
					->where('product_location_setups.pst_id', '=', $raw->pid)
					->whereNotIn('pl_code', $exc_location)
					->whereNotIn('product_locations.st_id', ['3', '4', '5', '10', '11', '16'])
					//->where('products.p_name', '=', $arr_pst->p_name) //
					//->where('products.p_color', '=', $product_color)
					//->where('sizes.sz_name', '=', $raw->size)
					->where(function ($w) use ($request) {
						// cek jika ada store yang di pilih
						if (!empty($request->store_id)) {
							$w->where('product_locations.st_id', '=', $request->store_id);
						}
					})
					->groupBy('st_id')
					->orderBy('st_id', 'ASC')
					->get();
				if (!empty($arr_locations)) {
					$stores = array();
					foreach ($arr_locations as $store_raw) {
						if ($spesial_produk) {
							if ($spesial_produk->pds_qty <= $store_raw->stock) {
								$stock_store_data = $spesial_produk->pds_qty;
							} else {
								$stock_store_data = $store_raw->stock;
							}
						} else {
							$stock_store_data = $store_raw->stock;
						}

						// cek user terakhir order
						if ($request->cust_id) {
							if ($user_order && $spesial_produk) {
								if ($tanggal_stok < date("Y-m-d")) {
									$stock_store_data = $stock_store_data;
								} else {
									$stock_store_data = 0;
								}
							} else {
								$stock_store_data = $stock_store_data;
							}
						}

						$stock_store['store_id'] = $store_raw->st_id;
						$stock_store['store_name'] = $store_raw->st_name;
						$stock_store['location_id1'] = $store_raw->area_id_biteship;
						$stock_store['location_id2'] = $store_raw->zip_id;
						//$stock_store['stock_store'] = $store_raw->stock;
						$stock_store['stock_store'] = "$stock_store_data";
						$stock_store['store_address'] = $store_raw->st_address;
						$stock_store['store_lat'] = $store_raw->st_lat;
						$stock_store['store_lang'] = $store_raw->st_lang;
						$stock_store['maps_url'] = $store_raw->st_maps;
						$stores[] = $stock_store;
					}

				}

				$image = ProductImageHelper::parseImages($raw->p_main_image, $raw->p_image);
				$image_url = !empty($image) ? "https://topsystem.id/api/product/600/" : '';

				$results[] = [
					'product_id' => (int) $raw->pid,
					'category' => $raw->category,
					'brand' => $raw->brand,
					'article' => $raw->article,
					'color' => $raw->color,
					//'stock' => $raw->stock,
					'stock' => $maks_stock,
					//'stock_maks' => $maks_stock,
					'size' => $raw->size,
					'image_url' => $image_url,
					'image' => $image,
					'store' => $stores,
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
					'description' => 'Stok artikel ' . $arr_pst->p_name . ' warna ' . $product_color . ' tidak tersedia'
				]
			];
		}
		return response()->json($data);
	}

	// cek semua artikel ***
	public function getaArtikelall(Request $request)
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

		/*
		$exception = DB::table('exception_locations')->select('pl_code')
		->leftJoin('product_locations', 'product_locations.id', '=', 'exception_locations.pl_id')->get();
		$exc_location = array();
		foreach ($exception as $exc) {
			$exc_location[] = $exc->pl_code;
		}
		*/

		$type_data = $request->type_data;

		$stock = DB::table('products')
			->select("products.id as pid", "p_name", "br_name", "psc_name", "pssc_name", "pc_name")
			->leftJoin('product_categories', 'product_categories.id', '=', 'products.pc_id')
			->leftJoin('product_sub_categories', 'product_sub_categories.id', '=', 'products.psc_id')
			->leftJoin('product_sub_sub_categories', 'product_sub_sub_categories.id', '=', 'products.pssc_id')
			->leftJoin('brands', 'brands.id', '=', 'products.br_id')
			//->whereNotIn('pl_code', $exc_location)
			//->whereIn('product_locations.st_id', ['2'])
			->whereNotNull('p_main_image')
			->where('p_price_tag', '>', '0')
			->where('p_delete', '=', '0')
			->whereNotNull('p_main_image')
			/*
			->where(function($w) use($sub_kat, $subsub_kat, $brand, $product_name, $size_name) {
				if (!empty($sub_kat)) {
					$sub_details = explode(",",str_replace(", ",",",$sub_kat));
					$w->whereIn('product_sub_categories.psc_name', $sub_details);
				}
			})
			*/
			->groupBy('p_name')
			->orderBy("p_name", "ASC")
			//->paginate(10);
			->get();

		if ($type_data == "summary") {
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success',
					'sum_article' => count($stock),
				]
			];
			return response()->json($data);
		}

		$results = array();
		if (!empty($stock)) {
			foreach ($stock as $row) {
				$results[] = [
					'category' => $row->pc_name,
					'brand' => $row->br_name,
					'sub_cat' => $row->psc_name,
					'subsub_cat' => $row->pssc_name,
					'article' => $row->p_name,
				];
			}

			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success',
					'sum_article' => count($stock),
				],
				'results' => $results
			];
		} else {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data not found',
					'sum_article' => 0,
				],
				'results' => $results
			];
		}
		return response()->json($data);
	}

	// list artikel rekomendasi keranjang
	public function recomendationArticle(Request $request)
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

		$product_id = $request->product_id; // tidak wajib di isi
		$cust_id = $request->cust_id; // tidak wajib di isi

		$exception = DB::table('exception_locations')->select('pl_code')
			->leftJoin('product_locations', 'product_locations.id', '=', 'exception_locations.pl_id')->get();
		$exc_location = array();
		foreach ($exception as $exc) {
			$exc_location[] = $exc->pl_code;
		}

		$sub_kat = "";
		$subsub_kat = "";
		$brand = "";
		$product_name = "";
		$sorting_artikel = "baru";
		if (!empty($sorting_artikel)) {
			if ($sorting_artikel == "baru") {
				$sort_orders = 'products.created_at';
			} else
				if ($sorting_artikel == "terlaris") {
					$sort_orders = 'products.sold';
				} else
					if ($sorting_artikel == "dilihat") {
						$sort_orders = 'products.sold';
					} else {
						$sort_orders = 'products.sold';
					}
		} else {
			$sort_orders = 'products.sold';
		}

		$stock = DB::table('product_recomendations')
			->selectRaw('ts_product_stocks.id as pid, pc_name as category, br_name as brand, p_name as article, p_color as color, sz_name as size, ps_price_tag, p_price_tag, ps_sell_price, p_sell_price, p_main_image, p_image, psc_name, pssc_name, sold, SUM(ts_product_stocks.ps_qty) as stock')
			->leftJoin('product_recomendation_details', 'product_recomendation_details.pr_id', '=', 'product_recomendations.id')
			->leftJoin('products', 'products.id', '=', 'product_recomendation_details.p_id')
			->leftJoin('product_stocks', 'product_stocks.p_id', '=', 'products.id')
			//->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
			->leftJoin('product_categories', 'product_categories.id', '=', 'products.pc_id')
			->leftJoin('product_sub_categories', 'product_sub_categories.id', '=', 'products.psc_id')
			->leftJoin('product_sub_sub_categories', 'product_sub_sub_categories.id', '=', 'products.pssc_id')
			->leftJoin('brands', 'brands.id', '=', 'products.br_id')
			->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
			->where('product_stocks.ps_qty', '>', '0')
			->where('p_delete', '=', '0')
			->whereNotNull('p_main_image')
			->where('pr_delete', '=', '0')
			->where('prd_delete', '=', '0')
			->where(function ($w) use ($sub_kat, $subsub_kat, $brand, $product_name) {
				if (!empty($sub_kat)) {
					$sub_details = explode(",", str_replace(", ", ",", $sub_kat));
					$w->whereIn('product_sub_categories.psc_name', $sub_details);
				}
				if (!empty($subsub_kat)) {
					$subsub_details = explode(",", str_replace(", ", ",", $subsub_kat));
					$w->whereIn('product_sub_sub_categories.pssc_name', $subsub_details);
				}
				if (!empty($product_name)) {
					$arr_product_name = explode(" ", $product_name);
					foreach ($arr_product_name as $nama_produk) {
						$w->where(DB::raw("CONCAT(p_name, ' ', br_name, ' ',psc_name,' ',pssc_name,' ',br_name)"), 'LIKE', "%$nama_produk%");
					}
				}
				if (!empty($brand)) {
					$brand_details = explode(",", str_replace(", ", ",", $brand));
					$w->whereIn('brands.br_name', $brand_details);
				}
			})
			->groupBy('products.p_name')
			->orderBy($sort_orders, 'DESC')
			->paginate(10);

		$results = array();
		if (!empty($stock)) {
			foreach ($stock as $row) {
				$price = null;
				$price_tag = null;
				$image_url = null;
				$store = null;
				$image = array();

				$image_url = "https://topsystem.id/api/product/600/";
				$image = ProductImageHelper::parseImages($row->p_main_image, $row->p_image);
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
				$check_discount = DB::table('product_discounts')->select('pd_type', 'pd_value')
					->leftJoin('product_discount_details', 'product_discount_details.pd_id', '=', 'product_discounts.id')
					->where('product_discounts.std_id', '=', '9')
					->where('product_discount_details.pst_id', '=', $row->pid)
					->where('pd_start', '<=', date("Y-m-d"))
					->where('pd_date', '>=', date("Y-m-d"))
					->where('pd_start_time', '<=', date("H:i:s"))
					->where('pd_end_time', '>=', date("H:i:s"))
					->get()->first();
				if (!empty($check_discount)) {
					$cd = $check_discount;
					$discount = 0;
					if ($cd->pd_type == 'percent') {
						$discount = $price_tag - (($price_tag / 100) * $cd->pd_value);
					} else if ($cd->pd_type == 'amount') {
						$discount = $price_tag - $cd->pd_value;
					} else {
						$discount = $price_tag;
					}
					$price = $discount;
				}

				// persentase potongan harga
				if ($row->p_price_tag != $row->p_sell_price && $row->p_price_tag != '' && $row->p_sell_price != '') {
					$persen_potongan = (($row->p_price_tag - $row->p_sell_price) / $row->p_price_tag) * 100;
				} else {
					$persen_potongan = 0;
				}

				// cek diskon aktif apps
				$arr_promo = DB::table("product_discount_details")
					->select('product_discount_details.id', 'product_discount_details.pst_id', 'pd_value', 'pd_type')
					->leftJoin('product_discounts', 'product_discounts.id', '=', 'product_discount_details.pd_id')
					->leftJoin('product_stocks', 'product_stocks.id', '=', 'product_discount_details.pst_id')
					->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
					->where('std_id', '=', '20') // kusus apps
					//->where('pd_date', '>=', date("Y-m-d"))
					/*
					->where('pd_start', '<=', date("Y-m-d"))
					->where('pd_date', '>=', date("Y-m-d"))
					->where('pd_start_time', '<=', date("H:i:s"))
					->where('pd_end_time', '>=', date("H:i:s"))
					*/
					->whereRaw("CONCAT(ts_product_discounts.pd_start,' ',ts_product_discounts.pd_start_time)<='" . date("Y-m-d H:i:s") . "'")
					->whereRaw("CONCAT(ts_product_discounts.pd_date,' ',ts_product_discounts.pd_end_time)>='" . date("Y-m-d H:i:s") . "'")
					->where('products.p_name', '=', $row->article)
					->where('products.p_delete', '=', '0')
					->first();

				$price_tag = $row->p_price_tag; // harga bandrol
				$sell_price = $row->p_sell_price; // harga jual
				// cek diskon
				if ($arr_promo) {
					if ($arr_promo->pd_type == "percent") {
						$nilai_diskon = $price_tag * ($arr_promo->pd_value / 100);
						$sell_price = $price_tag - $nilai_diskon;
					} else
						if ($arr_promo->pd_type == "amount") {
							$sell_price = $price_tag - $arr_promo->pd_value;
						} else
							if ($arr_promo->pd_type == "fix") {
								$sell_price = $arr_promo->pd_value;
							} else {
								$sell_price = $sell_price;
							}

					if ($price_tag != $sell_price) {
						$persen_potongan = 100 - (($sell_price / $price_tag) * 100);
					} else {
						$persen_potongan = 0;
					}
				}

				// cek customers
				$add_persen = 0;
				if ($request->cust_id && $persen_potongan < 30) {
					$arr_customer = DB::table("customers")->where("id", "=", $request->cust_id)->first();
					if ($arr_customer) {
						// jika sebagai reseller
						if ($arr_customer->cust_reseller == 1) {
							// kusus brand topscore {$row->brand}
							if ($row->brand == "TOPSCORE") {
								//$add_persen = 0;
								//$persen_potongan = 20;
								//$sell_price_persen = ($price_tag * ($persen_potongan/100)); // default reseller 20%
								$add_persen = 20;
								$persen_potongan = 0;
								$sell_price_persen = ($price_tag * ($add_persen / 100)); // default reseller 20%
								$sell_price = $price_tag - $sell_price_persen;
							} else {
								$add_persen = 5;
								$sell_price_persen = ($sell_price * ($add_persen / 100)); // default reseller 5%
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
					'product_id' => $row->pid,
					'product_category' => $row->category,
					'product_brand' => $row->brand,
					'product_sub_cat' => $row->psc_name,
					'product_subsub_cat' => $row->pssc_name,
					'product_article' => $row->article,
					'product_stock' => $row->stock,
					'product_price_tag' => $price_tag,
					'product_sell_price' => $sell_price,
					'product_discount' => round($persen_potongan, 2),
					'additional_discount' => round($add_persen),
					'product_ratting' => 0, //$ratting,
					'product_image_url' => $image_url,
					'product_image' => $image,
				];
			}

			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success',
					'count_product' => count($stock),
				],
				'product' => $results
			];

		} else {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Success',
					'count_product' => count($stock),
				],
			];
		}
		return $data;
	}

	// cek fee service produk...
	public function feeServices(Request $request)
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

		$item_details = $request->item_details; // item produk 
		if (!$item_details) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Field item_details tidak boleh kosong!'
				]
			];
			return response()->json($data);
		}

		// [{"store_id": "2", "product_name": "KAOS KAKI PENDEK TOPSCORE ", "color": "HITAM", "size": "NS", "qty": 1, "price":382000}, {"store_id": "2", "product_name": "KAOS KAKI PANJANG TOPSCORE", "color": "BLACK", "size": "NS", "qty": 1, "price":365000}]

		$item_details = json_decode($item_details);
		$no = 0;
		$pst_id_array = array();

		$query_conditions = [];
		foreach ($item_details as $key => $lookup) {
			$query_conditions[] = "(products.p_name = '" . addslashes($lookup->product_name) . "' AND products.p_color = '" . addslashes($lookup->color) . "' AND sizes.sz_name = '" . addslashes($lookup->size) . "')";
		}

		if (!empty($query_conditions)) {
			$all_pst = DB::table("product_stocks")
				->select('product_stocks.id', 'products.p_name', 'products.p_color', 'sizes.sz_name')
				->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
				->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
				->whereRaw("(" . implode(" OR ", $query_conditions) . ")")
				->get();

			// Buat index pencarian cepat (Grouping)
			$pst_map = [];
			foreach ($all_pst as $p) {
				$key_map = $p->p_name . '_' . $p->p_color . '_' . $p->sz_name;
				$pst_map[$key_map] = $p->id;
			}

			// Mapping ulang ke pst_id_array sesuai urutan item_details
			foreach ($item_details as $lookup) {
				$key_map = $lookup->product_name . '_' . $lookup->color . '_' . $lookup->size;
				if (isset($pst_map[$key_map])) {
					$pst_id_array[] = $pst_map[$key_map];
				}
			}
		}

		foreach ($item_details as $key => $lookup) {
			$store_id = $lookup->store_id;
			$product_name = $lookup->product_name;
			$color = $lookup->color;
			$size = $lookup->size;
			$item_qty = $lookup->qty;
			$price = $lookup->price;
		}

		$fee_services = 0;
		if (count($pst_id_array) > 0) {
			// cek diskon produk yang ad biaya layanannya...
			$data_diskon = DB::table('product_discount_details')
				->selectRaw("ts_product_discounts.id, pd_fees")
				->leftJoin('product_discounts', 'product_discounts.id', '=', 'product_discount_details.pd_id') // cek product_discounts
				->where('product_discounts.std_id', '=', '20') // kusus hanya aplikasi
				->whereRaw("CONCAT(ts_product_discounts.pd_start,' ',ts_product_discounts.pd_start_time)<='" . date("Y-m-d H:i:s") . "'")
				->whereRaw("CONCAT(ts_product_discounts.pd_date,' ',ts_product_discounts.pd_end_time)>='" . date("Y-m-d H:i:s") . "'")
				->whereIn('product_discount_details.pst_id', $pst_id_array)
				->where('product_discounts.pd_fees', '>', '0')
				->first();
			if ($data_diskon) {
				$fee_services = $data_diskon->pd_fees;
			}
		}

		$data = [
			'status' => [
				'code' => 200,
				'description' => 'Success',
				'fee_services' => $fee_services,
				'product_id' => $pst_id_array,
			],
		];
		return $data;

	}

	// cek harga aktif berdasarkan artikel warna dan ukuran
	public function getpriceDetail(Request $request)
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
		/*
		$exception = DB::table('exception_locations')->select('pl_code')
		->leftJoin('product_locations', 'product_locations.id', '=', 'exception_locations.pl_id')->get();
		$exc_location = array();
		foreach ($exception as $exc) {
			$exc_location[] = $exc->pl_code;
		}
		*/

		// get request data...
		$product_name = $request->product_name;
		$product_color = $request->product_color;
		$product_size = $request->product_size;
		$cust_id = $request->cust_id;

		if (!$product_name || !$product_color || !$product_size) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Field product_name, product_color, dan product_size tidak boleh kosong!'
				]
			];
			return response()->json($data);
		}

		/*
		// save log traffic
		$data_log = [
			'cust_id' => $request->cust_id,
			'ta_page_access' => "Detail Produk",
			'ta_description' => "Mengakses laman detail Produk",
			'ta_lookup_id' => $request->product_id,
			'created_at' => date("Y-m-d H:i:s"),
		];
		$save_log = DB::table("traffic_apps")->insertGetId(array_merge($data_log));
		*/



		$stock = DB::table('product_stocks')
			->selectRaw('ts_product_stocks.id as pid, br_name, p_name, p_color, sz_name, p_price_tag, p_sell_price, ps_price_tag,  ps_sell_price')
			->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
			->leftJoin('brands', 'brands.id', '=', 'products.br_id')
			->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
			->where('products.p_name', '=', $product_name)
			->where('products.p_color', '=', $product_color)
			->where('sizes.sz_name', '=', $product_size)
			->where('products.p_delete', '=', '0')
			->first();

		$results = array();
		if (!empty($stock)) {
			if (!empty($stock->ps_sell_price)) {
				$price = $stock->ps_sell_price;
			} else {
				$price = $stock->p_sell_price;
			}
			if (!empty($stock->ps_price_tag)) {
				$price_tag = $stock->ps_price_tag;
			} else {
				$price_tag = $stock->p_price_tag;
			}


			// cek diskon
			if ($stock->p_price_tag != $stock->p_sell_price && $stock->p_price_tag != '' && $stock->p_sell_price != '') {
				$persen_potongan = (($stock->p_price_tag - $stock->p_sell_price) / $stock->p_price_tag) * 100;
			} else {
				$persen_potongan = 0;
			}

			// cek diskon aktif apps
			$arr_promo = DB::table("product_discount_details")
				->select('product_discount_details.id', 'product_discount_details.pst_id', 'pd_value', 'pd_type', 'pd_flash_sale')
				->leftJoin('product_discounts', 'product_discounts.id', '=', 'product_discount_details.pd_id')
				->leftJoin('product_stocks', 'product_stocks.id', '=', 'product_discount_details.pst_id')
				->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
				->where('std_id', '=', '20') // kusus apps
				->whereRaw("CONCAT(ts_product_discounts.pd_start,' ',ts_product_discounts.pd_start_time)<='" . date("Y-m-d H:i:s") . "'")
				->whereRaw("CONCAT(ts_product_discounts.pd_date,' ',ts_product_discounts.pd_end_time)>='" . date("Y-m-d H:i:s") . "'")
				//->where('products.p_name', '=', $stock->article)
				->where('product_discount_details.pst_id', '=', $stock->pid)
				->where('products.p_delete', '=', '0')
				->first();

			$price_tag = $stock->p_price_tag; // harga bandrol
			$sell_price = $stock->p_sell_price; // harga jual
			$flag_buy1get1 = 0;
			$pd_flash_sale = 0;
			// cek diskon
			if ($arr_promo) {
				if ($arr_promo->pd_flash_sale == 1) {
					$pd_flash_sale = 1;
				} else {
					$pd_flash_sale = 0;
				}

				if ($arr_promo->pd_type == "percent") {
					$nilai_diskon = $price_tag * ($arr_promo->pd_value / 100);
					$sell_price = $price_tag - $nilai_diskon;
				} else
					if ($arr_promo->pd_type == "amount") {
						$sell_price = $price_tag - $arr_promo->pd_value;
					} else
						if ($arr_promo->pd_type == "fix") {
							$sell_price = $arr_promo->pd_value;
						} else
							if ($arr_promo->pd_type == "b1g1") { // jika B1G1
								$sell_price = $price_tag + 0000; // harga bandrol sebagai harga jual...
								$flag_buy1get1 = 1;
								$price_tag = $sell_price;
							} else {
								$sell_price = $sell_price;
							}

				if ($price_tag != $sell_price) {
					$persen_potongan = 100 - (($sell_price / $price_tag) * 100);
				} else {
					$persen_potongan = 0;
				}
			}

			// cek customers
			$add_persen = 0;
			if ($request->cust_id && $persen_potongan < 30) {
				$arr_customer = DB::table("customers")->where("id", "=", $request->cust_id)->first();
				if ($arr_customer && $flag_buy1get1 == 0) {
					// jika sebagai reseller
					if ($arr_customer->cust_reseller == 1 && $pd_flash_sale == 0) {
						// kusus brand topscore {$row->brand}
						if ($stock->br_name == "TOPSCORE") {
							$add_persen = 20;
							$persen_potongan = 0;
							$sell_price_persen = ($price_tag * ($add_persen / 100)); // default reseller 20%
							$sell_price = $price_tag - $sell_price_persen;
						} else {
							$add_persen = 5;
							$sell_price_persen = ($sell_price * ($add_persen / 100)); // default reseller 5%
							$new_sell_price = $sell_price - $sell_price_persen;
							$sell_price = round($new_sell_price);
						}

					}
				}
			} else {
				$persen_potongan = $persen_potongan;
				$add_persen = 0;
			}

			if ($stock->br_name == "TOPSCORE") {
				$flag_sold = 0;
			} else {
				$flag_sold = 1;
			}
			$results[] = [
				'product_id' => $stock->pid,
				'brand' => $stock->br_name,
				'article' => $stock->p_name,
				'color' => $stock->p_color,
				'size' => $stock->sz_name,
				'price_tag' => $price_tag,
				'sell_price' => $sell_price,
				'discount' => round($persen_potongan),
				'additional_discount' => round($add_persen),
				'flag_buy1get1' => $flag_buy1get1,
			];
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
					'description' => 'Not found'
				]
			];
		}
		return response()->json($data);
	}
}
