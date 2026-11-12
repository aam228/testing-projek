<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Hash;

class ResellerController extends Controller
{
	protected $table = 'reseller_settings';
	protected $url_server = 'https://topsystem.id/';
	//protected $url_server = 'https://devtop.topsystem.id/';
	protected $server_domain = 'https://api.xendit.co';
    ///protected $secret_api_key = 'xnd_development_BiwG1eyewnIqZZnY05AV6yaTmcXji00SbXM1JIJy2aM0t1hqduxxCrLCodi'; // SANDBOX
	protected $secret_api_key;

	public function __construct()
	{
		$this->secret_api_key = config('services.xendit.secret_key');
	}

	private function checkData($table, $select, $where)
	{
		$affected = DB::table($table)
			->select($select)
			->where($where)
			->get()->first();
		return $affected;
	}

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
		/*
		if (!$cust_id) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Feld cust_id tidak boleh kosong!'
				]
			];
			return response()->json($data);
		}
		*/

		// save log traffic
		if ($cust_id) {
			$data_log = [
				'cust_id' => $cust_id,
				'ta_page_access' => "Reseller",
				'ta_description' => "Mengakses laman reseller aplikasi",
				'created_at' => date("Y-m-d H:i:s"),
			];
			$save_log = DB::table("traffic_apps")->insertGetId(array_merge($data_log));
		}

		$arr_setting = DB::table($this->table)->first();

		$query_data = DB::table("reseller_helpcenters")
			->select('reseller_helpcenters.id as pid', 'rhc_name', 'rhc_description')
			->where('rhc_delete', '=', '0')
			->orderBy('rhc_sort', "ASC")
			->get();
		$results = array();
		if (!empty($query_data)) {
			foreach ($query_data as $row) {
				$results[] = [
					'help_rs_id' => $row->pid,
					'help_rs_name' => $row->rhc_name,
					'help_rs_detail' => $row->rhc_description,
				];
			}

			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success'
				],
				'res_open' => $arr_setting->rs_open,
				'res_banner' => '',
				'res_price' => (int) $arr_setting->rs_price,
				'res_price_tag' => (int) $arr_setting->rs_price_tag,
				'res_title' => $arr_setting->rs_title,
				'res_short' => $arr_setting->rs_short_description,
				'res_help' => $results,
				'res_product' => $this->getArticle($cust_id, $key_search = ''),
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

	private function getArticle($cust_id, $key_search = '')
	{
		$exception = DB::table('exception_locations')->select('pl_code')
			->leftJoin('product_locations', 'product_locations.id', '=', 'exception_locations.pl_id')->get();
		$exc_location = array();
		foreach ($exception as $exc) {
			$exc_location[] = $exc->pl_code;
		}

		$arr_customer = DB::table("customers")->where("id", "=", $cust_id)->first();

		$sub_kat = "";
		$subsub_kat = "";
		$brand = "";
		$product_name = "";
		$sorting_artikel = "baru";
		if (!empty($sorting_artikel)) {
			if ($sorting_artikel == "baru") {
				//$sort_orders = 'purchase_order_article_details.id';
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

		$stock = DB::table('product_stocks')
			->selectRaw('ts_product_stocks.id as pid, pc_name as category, br_name as brand, p_name as article, p_color as color, sz_name as size, ps_price_tag, p_price_tag, ps_sell_price, p_sell_price, p_main_image, p_image, psc_name, pssc_name, sold, SUM(ts_product_stocks.ps_qty) as stock')
			->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
			->leftJoin('product_categories', 'product_categories.id', '=', 'products.pc_id')
			->leftJoin('product_sub_categories', 'product_sub_categories.id', '=', 'products.psc_id')
			->leftJoin('product_sub_sub_categories', 'product_sub_sub_categories.id', '=', 'products.pssc_id')
			->leftJoin('brands', 'brands.id', '=', 'products.br_id')
			->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
			// cek stok
			->where('product_stocks.ps_qty', '>', '0')
			//->where('brands.id', '=', '4') // kusus brand topscore
			->where('p_delete', '=', '0')
			->whereIn('brands.id', ['4', '8', '10', '2', '29']) // topscore, mills, ortuseight, specs, 910
			->whereNotNull('p_main_image')
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
			->paginate(20);

		$results = array();
		if (!empty($stock)) {
			foreach ($stock as $row) {
				$price = null;
				$price_tag = null;
				$store = null;
				$image_url = $this->url_server . "api/product/600/";
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
				$check_discount = DB::table('product_discounts')->select('pd_type', 'pd_value')
					->leftJoin('product_discount_details', 'product_discount_details.pd_id', '=', 'product_discounts.id')
					->where('product_discounts.std_id', '=', '9')
					->where('product_discount_details.pst_id', '=', $row->pid)
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
					->where('pd_start', '<=', date("Y-m-d"))
					->where('pd_date', '>=', date("Y-m-d"))
					->where('pd_start_time', '<=', date("H:i:s"))
					->where('pd_end_time', '>=', date("H:i:s"))
					->where('products.p_name', '=', $row->article)
					->where('products.p_delete', '=', '0')
					->first();

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
									$sell_price = $price_tag; // harga bandrol sebagai harga jual...
									$flag_buy1get1 = 1;
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
				foreach ($stock_color as $row_color) {
					$img = \App\Helpers\ProductImageHelper::parseSingleImage($row_color->p_main_image, $row_color->p_image);
					if (!empty($img)) {
						$image[] = $img;
					}
				}
				$image_url = "https://topsystem.id/api/product/600/";

				// cek ratting produk [new function]
				$qproducts = DB::table("products")->select('id', 'p_ratting', 'p_user_ratting')->where("p_name", "=", $row->article)->where("p_delete", "=", "0")->get();
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
				if ($tot_ratting > 0) {
					$tot_ratting = round($tot_ratting / $jml_data_product, 2);
				}

				if ($tot_ratting == 0) {
					$tot_ratting = 5;
				}

				$cashback_coin = (1 / 100) * $sell_price;
				if ($flag_buy1get1 == 1) {
					$label_promo = 'B1G1';
				} else {
					$label_promo = '';
				}

				$arr_wishlist = DB::table("wishlists")->where('pst_id', $row->pid)->where('cust_id', $cust_id)->where('ws_delete', '=', '0')->first();
				if ($arr_wishlist) {
					$flag_wishlist = 1;
				} else {
					$flag_wishlist = 0;
				}

				// cek produk topscore
				if ($row->brand == "TOPSCORE") {
					$add_persen = 0;
					$persen_potongan = 20;
					$sell_price_persen = ($price_tag * ($persen_potongan / 100)); // default reseller 20%
					$sell_price = $price_tag - $sell_price_persen;
				} else {
					$add_persen = 5;
					$sell_price_persen = ($sell_price * ($add_persen / 100)); // default reseller 5%
					$new_sell_price = $sell_price - $sell_price_persen;
					$sell_price = round($new_sell_price);
					$persen_potongan = $add_persen;
				}

				//$persen_potongan = $persen_potongan+$add_persen;
				//if($persen_potongan<=0){ $persen_potongan = $persen_potongan+$add_persen; } else {$persen_potongan = $add_persen;}

				if (count($stock_color) > 0) {
					$results[] = [
						'product_id' => $row->pid,
						'category' => $row->category,
						'brand' => $row->brand,
						'sub_cat' => $row->psc_name,
						'subsub_cat' => $row->pssc_name,
						'article' => $row->article,
						'stock' => $row->stock,
						'price_tag' => $price_tag,
						'sell_price' => $sell_price,
						'discount' => round($persen_potongan),
						'additional_discount' => round($add_persen),
						'sold' => $row->sold,
						'ratting' => $tot_ratting,
						'total_ulasan' => $tot_ratting_user,
						'wishlist' => $flag_wishlist,
						'jumlah_warna' => count($stock_color),
						'image_url' => $image_url,
						'image' => $image,
						'flag_buy1get1' => $flag_buy1get1,
						'cashback_coin' => round($cashback_coin),
						'label_promo' => $label_promo,
					];
				}
			}

		}
		return $results;
	}

	// create registrasi Reseller
	private function generateInvoice()
	{
		$invoice = date('YmdHis') . rand(1000, 9999);
		if ($this->InvoiceExists($invoice)) {
			return generateInvoice();
		}
		return $invoice;
	}

	private function InvoiceExists($number)
	{
		return DB::table('reseller_registrations')->where(['rr_external_id' => $number])->exists();
	}

	// SAVE PAYMENT ORDERS
	public function createOrders(Request $request)
	{
		error_reporting(0);
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
		$reseller_store = $request->res_store;
		$reseller_phone = $request->res_phone;
		$amount = $request->amount;
		$bank_code = $request->bank_code;

		if ($amount <= 0) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Biaya pendaftaran tidak sesuai!'
				]
			];
			return response()->json($data);
		}

		if (!$cust_id || !$reseller_store || !$reseller_phone || !$bank_code) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Field cust_id, res_store, res_phone dan bank_code tidak boleh kosong!'
				]
			];
			return response()->json($data);
		}

		$check_customer = DB::table('customers')->select('*')
			->where('id', '=', $cust_id)
			->where('cust_delete', '=', '0')
			->first();
		if (!$check_customer) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data customer tidak ditemukan!'
				]
			];
			return response()->json($data);
		}

		$external_id = $this->generateInvoice();
		//$amount = $request->amount;
		//$nama_user = $request->nama_user;
		$nama_user = $check_customer->cust_name;
		//$bank_code = $request->bank_code;

		$bank_options = NULL;

		//$tgl_indo_expired = date('Y-m-d H:i:s', strtotime(date("Y-m-d H:i:s"). ' -4 hours')); // untuk +3 jam
		$tgl_indo_expired = date('Y-m-d H:i:s', strtotime(date("Y-m-d H:i:s") . ' -6 hours'));  // untuk +1 jam
		$curl = curl_init();

		$headers = array();
		$headers[] = 'Content-Type: application/json';

		$disbursement_options = [];
		if (!empty($disbursement_options['X-IDEMPOTENCY-KEY'])) {
			array_push($headers, 'X-IDEMPOTENCY-KEY: ' . $disbursement_options['X-IDEMPOTENCY-KEY']);
		}

		if (in_array($bank_code, array("BCA", "BNI", "BRI", "MANDIRI", "PERMATA", "BSI"))) { //echo 11; exit();
			$end_point = $this->server_domain . '/callback_virtual_accounts';

			$data['external_id'] = $external_id;
			$data['name'] = $nama_user;
			$data['bank_code'] = $bank_code;
			$data['expected_amount'] = (int) $amount;
			//$data['expiration_date'] = date('Y-m-d H:i:s', strtotime(date("Y-m-d H:i:s"). ' -6 hours'));  // untuk +1 jam
			$data['expiration_date'] = $tgl_indo_expired;
			$data['is_closed'] = true;

			if (is_array($bank_options)) {
				foreach ($bank_options as $key => $value) {
					$data[$key] = $value;
				}
			}
		} else
			if (in_array($bank_code, array("ID_OVO", "ID_SHOPEEPAY", "ID_DANA", "ID_LINKAJA"))) {
				$end_point = $this->server_domain . '/ewallets/charges';

				$phone = str_replace('+', '', $reseller_phone);
				$check = substr($phone, 0, 1);
				if ($check == '0' || $check == 0) {
					$phone = preg_replace('/^0/', '62', $phone);
				}
				$data['reference_id'] = $external_id;
				$data['currency'] = "IDR";
				$data['amount'] = (int) $amount;
				$data['checkout_method'] = "ONE_TIME_PAYMENT";
				$data['channel_code'] = $bank_code;
				$data['channel_properties'] =
					array(
						"success_redirect_url" => 'https://production.topscore.id/api/callback_xd_ewallet',
						"mobile_number" => $phone
					);
			}
			// jika Payment mengunakan QRIS
			else {
				$end_point = $this->server_domain . '/qr_codes';

				$data['external_id'] = $external_id;
				$data['amount'] = (int) $amount;
				$data['type'] = 'DYNAMIC';
				$data['callback_url'] = 'https://production.topscore.id/api/callback_xd';
			}

		$payload = json_encode($data);

		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_USERPWD, $this->secret_api_key . ":");
		curl_setopt($curl, CURLOPT_URL, $end_point);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_CAINFO, dirname(__FILE__) . "/data/cacert.pem");

		$response = curl_exec($curl);
		curl_close($curl);

		$responseObject = json_decode($response, true);
		//return $responseObject;

		//return response()->json($responseObject);
		$json_view = json_encode($responseObject);
		$json_parsing = json_decode($json_view);

		$instruksi_payment = $this->instruksiPayment($bank_code);
		$results = array();
		if (!empty($instruksi_payment)) {
			foreach ($instruksi_payment as $row) {
				$results[] = [
					'metode' => $row->metod_instruction_payget,
					'instruction' => $row->instruction_payget,
				];
			}
		}

		//echo $json_view; exit();
		if ($json_parsing->error_code != '') {
			$data = [
				'status' => 400,
				'description' => 'Gagal mendapatkan data pembayaran, harap pastikan kembali param yang anda kirim sudah benar!',
			];
			return response()->json($data);
		}

		if ($request->bank_code == 'QRIS') {
			$nomor_va = $json_parsing->qr_string;
			$bank_code = 'QRIS';
		} else
			if (in_array($request->bank_code, array("ID_OVO", "ID_SHOPEEPAY", "ID_DANA", "ID_LINKAJA"))) {
				$nomor_va = $external_id;
				$bank_code = $request->bank_code;
			} else {
				$nomor_va = $json_parsing->account_number;
				$bank_code = $json_parsing->bank_code;
			}

		// insert order reseller ***
		$data_event = [
			'cust_id' => $cust_id,
			'rr_store_name' => $reseller_store,
			'rr_store_phone' => $reseller_phone,
			'rr_external_id' => $external_id,
			'rr_price' => $amount,
			'rr_json_pg' => $json_view,
			'rr_virtual_account' => $nomor_va,
			'rr_bank_code' => $bank_code,
			'ca_id' => $request->address_id,
			'created_at' => date("Y-m-d H:i:s"),
		];
		$get_idsave = DB::table("reseller_registrations")->insertGetId(array_merge($data_event));


		// SAVE LOG NOTIF *********************** // ********************************************
		$an_description = 'Pendaftaran Reseller dengan no order ' . $external_id . ' telah berhasil di submit. Segera lakukan Pembayaran agar tidak otomatis terbatalkan.';
		$save_notif = DB::table('apps_notif')->insertGetId([
			'id_lookup' => $get_idsave,
			'cust_id' => $request->user_id,
			'type_data' => 'reseller',
			'an_title' => 'Pendaftaran berhasil',
			'an_description' => $an_description,
			'created_at' => date('Y-m-d H:i:s'),
		]);

		// Save to PG Master ******************************************************* //

		$cek_channels = DB::table('pg_metod_payget')->select('id', 'bank_code', 'img')
			->where('status', '=', '1')
			->where('bank_code', '=', $bank_code)
			->first();
		if (!empty($cek_channels)) {
			$img_channel = url('public/pg') . '/' . str_replace("svg", "png", $cek_channels->img);
		} else {
			$img_channel = '';
		}

		// OUTPUT JSON SAVE DATA...
		if ($request->bank_code == 'QRIS') {
			// expired date
			$tgl_expired_payment = date('Y-m-d H:i:s', strtotime(date("Y-m-d H:i:s") . ' +1 hours'));
			$data = [
				'id' => $json_parsing->id,
				'owner_id' => "",
				'external_id' => $json_parsing->external_id,
				'account_number' => $json_parsing->external_id,
				'bank_code' => $request->bank_code,
				'amount' => $json_parsing->amount,
				'qr_string' => $json_parsing->qr_string,
				'callback_url' => $json_parsing->callback_url,
				'description' => $json_parsing->description,
				'type' => $json_parsing->type,
				'merchant_code' => "",
				'name' => $nama_user,
				'is_closed' => true,
				'expected_amount' => $json_parsing->amount,
				'expiration_date' => $tgl_expired_payment,
				'is_single_use' => true,
				'status' => $json_parsing->status,
				'currency' => "IDR",
				'country' => "ID",
				'actions' => null,

				'img' => $img_channel,
				'pt' => 'PT. Mencetak Banyak Gol (Topscore)',
				'banner' => url('public/upload/payment_product.png'),
				/*
				'event' => [
					'event_id' => $row_event->id,
					'event_title' => $row_event->et_title,
					'event_price' => $row_event->et_price,
					'event_banner' => 'https://topsystem.id/upload/event/'.$row_event->et_banner,
				],
				*/
				'payment_instruction' => $results
			];

			// Save to PG Master ******************************************************* //
			$data_pg = [
				'id_lookup' => $get_idsave,
				'type_data' => 'reseller',
				'external_id' => $external_id,
				'account_number' => $nomor_va,
				'bank_code' => $bank_code,
				'name' => $nama_user,
				'expected_amount' => $json_parsing->amount,
				'status' => $json_parsing->status,
				'created_at' => date("Y-m-d H:i:s"),
			];
			$get_pgmaster = DB::table("payment_xendit")->insertGetId(array_merge($data_pg));
		} else
			// menggunakan E-Wallet
			if (in_array($request->bank_code, array("ID_OVO", "ID_SHOPEEPAY", "ID_DANA", "ID_LINKAJA"))) {
				// expired date
				if ($bank_code == "ID_OVO") {
					$tgl_expired_payment = date('Y-m-d H:i:s', strtotime(date("Y-m-d H:i:s") . ' +1 minutes'));
				} else {
					$tgl_expired_payment = date('Y-m-d H:i:s', strtotime(date("Y-m-d H:i:s") . ' +1 hours'));
				}
				$data = [
					'id' => $json_parsing->id,
					'owner_id' => "",
					'external_id' => $json_parsing->reference_id,
					'account_number' => $json_parsing->reference_id,
					'bank_code' => $request->bank_code,
					'amount' => $json_parsing->charge_amount,
					'qr_string' => "",
					'callback_url' => $json_parsing->callback_url,
					'description' => "",
					'type' => "",
					'merchant_code' => "",
					'name' => $nama_user,
					'is_closed' => true,
					'expected_amount' => $json_parsing->charge_amount,
					//'expiration_date' => $tgl_indo_expired,
					'expiration_date' => $tgl_expired_payment,
					'is_single_use' => true,
					'status' => $json_parsing->status,
					'currency' => "IDR",
					'country' => "ID",
					'actions' => $json_parsing->actions,

					'img' => $img_channel,
					'pt' => 'PT. Mencetak Banyak Gol (Topscore)',
					'banner' => url('public/upload/payment_product.png'),
					/*
					'event' => [
						'event_id' => $row_event->id,
						'event_title' => $row_event->et_title,
						'event_price' => $row_event->et_price,
						'event_banner' => 'https://topsystem.id/upload/event/'.$row_event->et_banner,
						//'event_banner' => 'https://devtop.topsystem.id/upload/event/'.$row_event->et_banner,
					],
					*/
					'payment_instruction' => $results
				];

				// Save to PG Master ******************************************************* //
				$data_pg = [
					'id_lookup' => $get_idsave,
					'type_data' => 'reseller',
					'external_id' => $external_id,
					'account_number' => $nomor_va,
					'bank_code' => $bank_code,
					'name' => $nama_user,
					'expected_amount' => $json_parsing->charge_amount,
					'status' => $json_parsing->status,
					'created_at' => date("Y-m-d H:i:s"),
				];
				$get_pgmaster = DB::table("payment_xendit")->insertGetId(array_merge($data_pg));
			}
			// Jika Payment menggunakan VA
			else {
				$data = [
					'id' => $json_parsing->id,
					'owner_id' => $json_parsing->owner_id,
					'external_id' => $json_parsing->external_id,
					'account_number' => $json_parsing->account_number,
					'bank_code' => $request->bank_code,
					'amount' => $json_parsing->expected_amount,
					'qr_string' => "",
					'callback_url' => "",
					'description' => "",
					'type' => "",
					'merchant_code' => $json_parsing->merchant_code,
					'name' => $json_parsing->name,
					'is_closed' => $json_parsing->is_closed,
					'expected_amount' => $json_parsing->expected_amount,
					'expiration_date' => $json_parsing->expiration_date,
					'is_single_use' => $json_parsing->is_single_use,
					'status' => $json_parsing->status,
					'currency' => $json_parsing->currency,
					'country' => $json_parsing->country,
					'actions' => null,

					'img' => $img_channel,
					'pt' => 'PT. Mencetak Banyak Gol (Topscore)',
					'banner' => url('public/upload/payment_product.png'),
					/*
					'event' => [
						'event_id' => $row_event->id,
						'event_title' => $row_event->et_title,
						'event_price' => $row_event->et_price,
						'event_banner' => 'https://topsystem.id/upload/event/'.$row_event->et_banner,
					],
					*/
					'payment_instruction' => $results
				];

				// Save to PG Master ******************************************************* //
				$data_pg = [
					'id_lookup' => $get_idsave,
					'type_data' => 'reseller',
					'external_id' => $external_id,
					'account_number' => $nomor_va,
					'bank_code' => $bank_code,
					'merchant_code' => $json_parsing->merchant_code,
					'name' => $json_parsing->name,
					'is_closed' => $json_parsing->is_closed,
					'expected_amount' => $json_parsing->expected_amount,
					'expiration_date' => $json_parsing->expiration_date,
					'status' => $json_parsing->status,
					'currency' => $json_parsing->currency,
					'created_at' => date("Y-m-d H:i:s"),
				];
				$get_pgmaster = DB::table("payment_xendit")->insertGetId(array_merge($data_pg));
			}

		return response()->json($data);
	}

	private function instruksiPayment($kode_bank)
	{
		$query_apps = DB::table('pg_instruction_payget')->selectRaw('ts_pg_instruction_payget.id as pid, metod_instruction_payget, instruction_payget')
			->leftJoin('pg_metod_instruction_payget', 'pg_metod_instruction_payget.id', '=', 'pg_instruction_payget.id_metod_instruction_payget')
			->leftJoin('pg_metod_payget', 'pg_metod_payget.id', '=', 'pg_instruction_payget.id_metod_payget')
			->where('pg_instruction_payget.status', '=', '1')
			->where('pg_metod_instruction_payget.status', '=', '1')
			->where('pg_metod_payget.status', '=', '1')
			->where('pg_metod_payget.bank_code', '=', $kode_bank)
			->orderBy('pg_metod_instruction_payget.sort')
			->get();
		return $query_apps;
	}

	// cek orderan...
	public function CekOrders(Request $request)
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
		$external_id = $request->external_id;
		if (!$cust_id) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Field cust_id tidak boleh kosong!'
				]
			];
			return response()->json($data);
		}

		$query_data = DB::table("reseller_registrations")
			->select('reseller_registrations.id as pid', 'rr_store_name', 'rr_store_phone', 'rr_external_id', 'rr_price', 'rr_bank_code', 'rr_virtual_account', 'rr_payment_status', 'reseller_registrations.created_at', 'cust_name', 'cust_phone', 'rr_json_pg', 'respacket_id', 'respacket_name', 'respacket_duration', 'cust_reseller_active', 'rr_address')
			->leftJoin('customers', 'customers.id', '=', 'reseller_registrations.cust_id')
			->leftJoin('reseller_packets', 'reseller_packets.id', '=', 'reseller_registrations.respacket_id')
			->where('customers.id', '=', $cust_id) // Customer ID
			->where(function ($w) use ($external_id) {
				if (!empty($external_id)) {
					$w->where('reseller_registrations.rr_external_id', '=', $external_id);
				}

			})
			->orderBy('reseller_registrations.created_at', 'DESC')
			->get();
		$results = array();
		if (!empty($query_data)) {
			foreach ($query_data as $row) {
				$json_parsing = json_decode($row->rr_json_pg);

				if ($row->rr_payment_status == "0") {
					$payment_status = 'Unpaid';
				} else
					if ($row->rr_payment_status == "1") {
						$payment_status = 'Paid';
					} else
						if ($row->rr_payment_status == "10") {
							$payment_status = 'Expired';
						} else
							if ($row->rr_payment_status == "2") {
								$payment_status = 'Cancel';
							}

				// jika menggunakan payment gateway ************** //
				//if($row->rr_json_pg){

				$instruksi_payment = $this->instruksiPayment($row->rr_bank_code);
				$result_pay = array();
				if (!empty($instruksi_payment)) {
					foreach ($instruksi_payment as $row_pay) {
						$result_pay[] = [
							'metode' => $row_pay->metod_instruction_payget,
							'instruction' => $row_pay->instruction_payget,
						];
					}
				}

				// cek channel bank pembayaran
				$cek_channels = DB::table('pg_metod_payget')->select('id', 'bank_code', 'img')
					->where('status', '=', '1')
					->where('bank_code', '=', $row->rr_bank_code)
					->first();
				if (!empty($cek_channels)) {
					$img_channel = url('public/pg') . '/' . str_replace("svg", "png", $cek_channels->img);
				} else {
					$img_channel = '';
				}

				// cek pg payments
				$cek_pgpayment = DB::table('payment_xendit')
					->where('id_lookup', '=', $row->pid)
					->where('type_data', '=', 'reseller')
					->first();
				if (!empty($cek_pgpayment)) {
					$exp_date = date("Y-m-d H:i:s", strtotime($cek_pgpayment->expiration_date));
				} else {
					$exp_date = $row->created_at;
				}

				// tambahkan objek data payment
				$payment_info = "";
				if (in_array($row->rr_bank_code, array("ID_OVO", "ID_SHOPEEPAY", "ID_DANA", "ID_LINKAJA"))) {
					// expired date
					if ($row->rr_bank_code == "ID_OVO") {
						$exp_date_new = date('Y-m-d H:i:s', strtotime($row->created_at . ' +1 minutes'));
					} else {
						$exp_date_new = date('Y-m-d H:i:s', strtotime($row->created_at . ' +3 hours'));
					}
					$payment_info = [
						'channel_type' => 0,
						'external_id' => $json_parsing->reference_id,
						'account_number' => "",
						'bank_code' => $row->rr_bank_code,
						'amount' => (int) $json_parsing->charge_amount,
						'qr_string' => "",
						'callback_url' => "",
						'description' => "",
						'type' => "",
						'merchant_code' => "",
						'name' => $row->rr_store_name,
						'is_closed' => true,
						'expected_amount' => (int) $json_parsing->charge_amount,
						//'expiration_date' => $exp_date,
						'expiration_date' => $exp_date_new,
						'is_single_use' => true,
						'status' => $json_parsing->status,
						'currency' => "IDR",
						'country' => "ID",
						'actions' => $json_parsing->actions,
					];
				} else
					if (in_array($row->rr_bank_code, array("QRIS"))) {
						// expired date
						$exp_date_new = date('Y-m-d H:i:s', strtotime($row->created_at . ' +1 hours'));
						$payment_info = [
							'channel_type' => 2,
							'external_id' => $json_parsing->external_id,
							'account_number' => "",
							'bank_code' => $row->rr_bank_code,
							'amount' => $json_parsing->amount,
							'qr_string' => $json_parsing->qr_string,
							'callback_url' => $json_parsing->callback_url,
							'description' => $json_parsing->description,
							'type' => $json_parsing->type,
							'merchant_code' => "",
							'name' => $row->rr_store_name,
							'is_closed' => true,
							'expected_amount' => $json_parsing->amount,
							'expiration_date' => $exp_date_new,
							'is_single_use' => true,
							'status' => $json_parsing->status,
							'currency' => "IDR",
							'country' => "ID",
							'actions' => null,
						];
					} else {
						$payment_info = [
							'channel_type' => 1,
							'external_id' => $json_parsing->external_id,
							'account_number' => $json_parsing->account_number,
							'bank_code' => $row->rr_bank_code,
							'amount' => $json_parsing->expected_amount,
							'qr_string' => "",
							'callback_url' => "",
							'description' => "",
							'type' => "",
							'merchant_code' => $json_parsing->merchant_code,
							'name' => $json_parsing->name,
							'is_closed' => $json_parsing->is_closed,
							'expected_amount' => $json_parsing->expected_amount,
							'expiration_date' => $exp_date,
							'is_single_use' => $json_parsing->is_single_use,
							'status' => $json_parsing->status,
							'currency' => $json_parsing->currency,
							'country' => $json_parsing->country,
							'actions' => null,
						];
					}

				// expired date
				if ($row->rr_bank_code == "ID_OVO") {
					$exp_date_new = date('Y-m-d H:i:s', strtotime($row->created_at . ' +1 minutes'));
				} else {
					$exp_date_new = date('Y-m-d H:i:s', strtotime($row->created_at . ' +1 hours'));
				}

				// Tampilkan status expired
				if ($exp_date_new < date("Y-m-d H:i:s")) {
					$status_expired = 1;
				} else {
					$status_expired = 0;
				}

				if ($row->respacket_name) {
					$respacket_name = [
						'info1' => 'Topscore Berlangganan',
						'info2' => $row->respacket_duration . ' bulan expired tanpa transaksi',
					];
				} else {
					$respacket_name = [
						'info1' => 'Topscore Berlangganan',
						'info2' => '6 bulan expired tanpa transaksi',
					];
				}

				// cek profile sebagai reseller ***
				//$arr_reseller = DB::table("reseller_registrations")->where(["cust_id" => $cust_id, "rr_payment_status"=>"1"])->first();
				if (1 == 1) {
					$reseller_address = $row->rr_address ?: ($row->cust_address ?? '');
					//if($row->cust_reseller_active==1){
					if ($row->rr_store_name) {
						$res_customer = [
							'res_customer_id' => $row->pid,
							'res_customer_name' => $row->rr_store_name,
							'res_customer_phone' => $row->rr_store_phone,
							'res_customer_address' => $reseller_address,
						];
					} else {
						$res_customer = NULL;
					}
				}


				$results[] = [
					'order_id' => $row->pid,
					'nama_user' => $row->rr_store_name,
					'phone_user' => $row->rr_store_phone,
					'order_invoice' => $row->rr_external_id,
					//'qty' => 1,
					'price' => $row->rr_price,
					//'total_price' => $row->rr_price,
					'external_id' => $row->rr_external_id,
					'account_number' => $row->rr_virtual_account,
					'bank_code' => $row->rr_bank_code,
					'img' => $img_channel,
					'pt' => 'PT. Mencetak Banyak Gol (Topscore)',
					'banner' => url('public/upload/payment_product.png'),
					'payment_status' => $payment_status,
					'created_at' => $row->created_at,
					'expiration_date' => $exp_date_new,
					'payment_info' => $payment_info,
					'payment_instruction' => $result_pay,
					'status_expired' => $status_expired,
					'icon' => $this->url_server . 'upload/event/subscription_apps.svg',
					'info_subscription' => $respacket_name,
					'res_customer' => $res_customer,
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

	// check data reseller...
	public function customerReseller(Request $request)
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
		if (!$cust_id) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Field cust_id tidak boleh kosong!'
				]
			];
			return response()->json($data);
		}

		// settingan reseller ***
		$arr_settingreseller = DB::table("reseller_settings")->first();

		$arr_customer = DB::table("customers")->where(["cust_reseller" => "1", "id" => $cust_id, "cust_delete" => "0"])->first();
		if (!$arr_customer) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data customer tidak ditemukan!'
				]
			];
			return response()->json($data);
		}

		// cek data yang sudah lunas pendaftarannya
		$arr_reseller = DB::table("reseller_registrations")->where(["rr_payment_status" => "1", "cust_id" => $cust_id])->first();
		if (!$arr_reseller) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data pendaftaran reseller tidak ditemukan!'
				]
			];
			return response()->json($data);
		}

		// check alamat
		$list_alamat = DB::table('customer_address')
			->selectRaw('ts_customer_address.id as pid, ca_type, ca_address, ca_default, ts_customer_address.created_at, zc_province, zc_city, zc_district, zc_subdistrict, zc_zip, zc_code, ca_name, ca_phone, area_id_biteship, ca_lat, ca_lang, ca_patokan, zip_id, ca_pinpoint')
			->leftJoin('zip_codes', 'zip_codes.id', '=', 'customer_address.zip_id')
			->where('cust_id', '=', $cust_id)
			->where('zip_id', '>=', '1')
			->where('ca_delete', '=', '0')
			->orderBy("ca_default", "DESC")
			->get();
		$address = array();
		foreach ($list_alamat as $row_sub) {
			if ($row_sub->area_id_biteship != '') {
				$area_id_biteship = $row_sub->area_id_biteship;
			} else {
				$area_id_biteship = '';
			}
			$address[] = [
				'address_id' => $row_sub->pid,
				'nama' => $row_sub->ca_name,
				'label_alamat' => $row_sub->ca_type,
				'phone' => $row_sub->ca_phone,
				'province' => $row_sub->zc_province,
				'city' => $row_sub->zc_city,
				'district' => $row_sub->zc_district,
				'subdistrict' => $row_sub->zc_subdistrict,
				'address' => $row_sub->ca_address,
				'default_address' => $row_sub->ca_default,
				'zip' => $row_sub->zc_zip,
				'zip_code' => $row_sub->zc_code,
				'area_id_biteship' => $area_id_biteship,
				'lat' => $row_sub->ca_lat,
				'lang' => $row_sub->ca_lang,
				'patokan' => $row_sub->ca_patokan,
				'kelurahan_id' => $row_sub->zip_id,
				'status_koordinat' => $row_sub->ca_pinpoint,
			];
		}

		// cek alamat reseller
		//$arr_ca = DB::table("customer_address")->where("id","=",$arr_reseller->ca_id)->first();
		$arr_ca = DB::table('customer_address')
			->selectRaw('ts_customer_address.id as id, ca_type, ca_address, ca_default, ts_customer_address.created_at, zc_province, zc_city, zc_district, zc_subdistrict, zc_zip, zc_code, ca_name, ca_phone, area_id_biteship, ca_lat, ca_lang, ca_patokan, zip_id, ca_pinpoint')
			->leftJoin('zip_codes', 'zip_codes.id', '=', 'customer_address.zip_id')
			->where('customer_address.id', '=', $arr_reseller->ca_id)
			->where('zip_id', '>=', '1')
			->where('ca_delete', '=', '0')
			->orderBy("ca_default", "DESC")
			->first();
		$address_customer = array();
		if ($arr_ca) {
			if ($arr_ca->area_id_biteship != '') {
				$area_id_biteship = $arr_ca->area_id_biteship;
			} else {
				$area_id_biteship = '';
			}
			$address_customer = [
				'address_id' => $arr_ca->id,
				'nama' => $arr_ca->ca_name,
				'label_alamat' => $arr_ca->ca_type,
				'phone' => $arr_ca->ca_phone,
				'province' => $arr_ca->zc_province,
				'city' => $arr_ca->zc_city,
				'district' => $arr_ca->zc_district,
				'subdistrict' => $arr_ca->zc_subdistrict,
				'address' => $arr_ca->ca_address,
				'default_address' => $arr_ca->ca_default,
				'zip' => $arr_ca->zc_zip,
				'zip_code' => $arr_ca->zc_code,
				'area_id_biteship' => $area_id_biteship,
				'lat' => $arr_ca->ca_lat,
				'lang' => $arr_ca->ca_lang,
				'patokan' => $arr_ca->ca_patokan,
				'kelurahan_id' => $arr_ca->zip_id,
				'status_koordinat' => $arr_ca->ca_pinpoint,
			];
		}

		// $arr_reseller
		$date_register = substr($arr_reseller->created_at, 0, 10);
		$expired_user = date('Y-m-d', strtotime($date_register . ' + ' . $arr_settingreseller->rs_duration . ' month'));
		;

		$data = [
			'status' => [
				'code' => 200,
				'description' => 'Success'
			],
			'reseller' => [
				'reseller_id' => $arr_reseller->id,
				'reseller_store' => $arr_reseller->rr_store_name,
				'reseller_phone' => $arr_reseller->rr_store_phone,
				'reseller_address' => $address_customer,
				'reseller_packet' => 'Rp ' . number_format($arr_settingreseller->rs_price, 0, ',', '.') . ' / ' . $arr_settingreseller->rs_duration . ' bulan',
				'reseller_expired' => 'Masa aktif sampai ' . date("d M Y", strtotime($expired_user)),
			],
			'address' => $address,
		];

		return response()->json($data);
	}

	// customerResellerupdate
	public function customerResellerupdate(Request $request)
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
		$reseller_id = $request->reseller_id;
		$res_store = $request->res_store;
		$res_phone = $request->res_phone;
		if (!$cust_id || !$reseller_id || !$res_store || !$res_phone) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Field cust_id, reseller_id, res_store, dan res_phone tidak boleh kosong!'
				]
			];
			return response()->json($data);
		}

		$arr_customer = DB::table("customers")->where(["cust_reseller" => "1", "id" => $cust_id, "cust_delete" => "0"])->first();
		if (!$arr_customer) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data customer tidak ditemukan!'
				]
			];
			return response()->json($data);
		}

		// cek data yang sudah lunas pendaftarannya
		$arr_reseller = DB::table("reseller_registrations")->where(["rr_payment_status" => "1", "cust_id" => $cust_id, "id" => $reseller_id])->first();
		if (!$arr_reseller) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data reseller tidak ditemukan!'
				]
			];
			return response()->json($data);
		}

		// lakukan update data...
		$updated_reseller = [
			'rr_store_name' => $request->res_store,
			'rr_store_phone' => $request->res_phone,
			'ca_id' => $request->address_id,
		];
		DB::table("reseller_registrations")->where('id', $reseller_id)->update($updated_reseller);

		$data = [
			'status' => [
				'code' => 200,
				'description' => 'Success'
			]
		];

		return response()->json($data);
	}


	// submit payment  *** (WEBSITE)
	public function webcreateOrders(Request $request)
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


		//$cust_id = $request->cust_id;
		$reseller_name = $request->res_name;
		$reseller_phone = $request->res_phone;
		$reseller_address = $request->res_address;
		$amount = $request->amount;
		$bank_code = $request->bank_code;

		$cust_id = $request->cust_id; //  opsional
		if ($cust_id) {
			$arr_customer = DB::table("customers")->where("id", "=", $cust_id)->first();
			if (!$arr_customer) {
				$data = [
					'status' => [
						'code' => 400,
						'description' => 'data customer tidak ditemukan!'
					]
				];
				return response()->json($data);
			}
		}

		if ($amount <= 0) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Biaya pendaftaran tidak sesuai!'
				]
			];
			return response()->json($data);
		}

		if (!$reseller_phone || !$bank_code) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Field res_phone dan bank_code tidak boleh kosong!'
				]
			];
			return response()->json($data);
		}

		$check_customer = DB::table('customers')
			->select('*')
			//->where('cust_phone', '=', $reseller_phone)
			->where(function ($w) use ($request, $reseller_phone, $cust_id) {
				// dahulukan jika menggunakan customer ID
				if ($cust_id) {
					$w->where('id', '=', $cust_id);
				} else
					if ($reseller_phone) {
						$w->where('cust_phone', '=', $reseller_phone);
					}
			})
			->where('cust_delete', '=', '0')
			->first();

		// jika belum ada, input data customer ***
		if (!$check_customer) {
			/*
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data customer tidak ditemukan!'
				]
			];
			return response()->json($data);
			*/

			// $cust_id
			// save customer baru ***
			$data_customers = [
				'cust_name' => $reseller_name ?? $reseller_phone,
				'cust_phone' => $reseller_phone,
				'cust_address' => $reseller_address,
				'ct_id' => '16',
				'cust_gender' => 'm',
				'cust_token_active' => '1',
				'cust_delete' => '0',
				'cust_first' => '0',
				'cust_second' => '0',
				'cust_third' => '0',
				'cust_platform' => 'web',
				'created_at' => date("Y-m-d H:i:s"),
			];
			$get_cust_id = DB::table("customers")->insertGetId(array_merge($data_customers));

			$cust_id = $get_cust_id;
			$nama_user = $reseller_name ?? $reseller_phone;
		} else {
			$cust_id = $check_customer->id;
			$nama_user = $check_customer->cust_name;
		}

		$external_id = $this->generateInvoice();
		//$nama_user = $check_customer->cust_name;
		//$cust_id = $check_customer->id;

		$bank_options = NULL;

		//$tgl_indo_expired = date('Y-m-d H:i:s', strtotime(date("Y-m-d H:i:s"). ' -4 hours')); // untuk +3 jam
		$tgl_indo_expired = date('Y-m-d H:i:s', strtotime(date("Y-m-d H:i:s") . ' -6 hours'));  // untuk +1 jam
		$curl = curl_init();

		$headers = array();
		$headers[] = 'Content-Type: application/json';

		$disbursement_options = [];
		if (!empty($disbursement_options['X-IDEMPOTENCY-KEY'])) {
			array_push($headers, 'X-IDEMPOTENCY-KEY: ' . $disbursement_options['X-IDEMPOTENCY-KEY']);
		}

		if (in_array($bank_code, array("BCA", "BNI", "BRI", "MANDIRI", "PERMATA", "BSI"))) { //echo 11; exit();
			$end_point = $this->server_domain . '/callback_virtual_accounts';

			$data['external_id'] = $external_id;
			$data['name'] = $nama_user;
			$data['bank_code'] = $bank_code;
			$data['expected_amount'] = (int) $amount;
			//$data['expiration_date'] = date('Y-m-d H:i:s', strtotime(date("Y-m-d H:i:s"). ' -6 hours'));  // untuk +1 jam
			$data['expiration_date'] = $tgl_indo_expired;
			$data['is_closed'] = true;

			if (is_array($bank_options)) {
				foreach ($bank_options as $key => $value) {
					$data[$key] = $value;
				}
			}
		} else
			if (in_array($bank_code, array("ID_OVO", "ID_SHOPEEPAY", "ID_DANA", "ID_LINKAJA"))) {
				$end_point = $this->server_domain . '/ewallets/charges';

				$phone = str_replace('+', '', $reseller_phone);
				$check = substr($phone, 0, 1);
				if ($check == '0' || $check == 0) {
					$phone = preg_replace('/^0/', '62', $phone);
				}
				$data['reference_id'] = $external_id;
				$data['currency'] = "IDR";
				$data['amount'] = (int) $amount;
				$data['checkout_method'] = "ONE_TIME_PAYMENT";
				$data['channel_code'] = $bank_code;
				$data['channel_properties'] =
					array(
						"success_redirect_url" => 'https://production.topscore.id/api/callback_xd_ewallet',
						"mobile_number" => $phone
					);
			}
			// jika Payment mengunakan QRIS
			else {
				$end_point = $this->server_domain . '/qr_codes';

				$data['external_id'] = $external_id;
				$data['amount'] = (int) $amount;
				$data['type'] = 'DYNAMIC';
				$data['callback_url'] = 'https://production.topscore.id/api/callback_xd';
			}

		$payload = json_encode($data);

		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_USERPWD, $this->secret_api_key . ":");
		curl_setopt($curl, CURLOPT_URL, $end_point);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_CAINFO, dirname(__FILE__) . "/data/cacert.pem");

		$response = curl_exec($curl);
		curl_close($curl);

		$responseObject = json_decode($response, true);
		//return $responseObject;

		//return response()->json($responseObject);
		$json_view = json_encode($responseObject);
		$json_parsing = json_decode($json_view);

		$instruksi_payment = $this->instruksiPayment($bank_code);
		$results = array();
		if (!empty($instruksi_payment)) {
			foreach ($instruksi_payment as $row) {
				$results[] = [
					'metode' => $row->metod_instruction_payget,
					'instruction' => $row->instruction_payget,
				];
			}
		}

		//echo $json_view; exit();
		if (!$json_parsing->external_id) {
			$data = [
				'status' => 400,
				'description' => 'Gagal mendapatkan data pembayaran, harap pastikan kembali variabel yang anda kirim sudah benar!',
			];
			return response()->json($data);
		}

		if ($request->bank_code == 'QRIS') {
			$nomor_va = $json_parsing->qr_string;
			$bank_code = 'QRIS';
		} else
			if (in_array($request->bank_code, array("ID_OVO", "ID_SHOPEEPAY", "ID_DANA", "ID_LINKAJA"))) {
				$nomor_va = $external_id;
				$bank_code = $request->bank_code;
			} else {
				$nomor_va = $json_parsing->account_number;
				$bank_code = $json_parsing->bank_code;
			}

		// insert order reseller ***
		$data_event = [
			'cust_id' => $cust_id,
			'rr_store_name' => $reseller_name ?? $reseller_phone,
			'rr_store_phone' => $reseller_phone,
			'rr_address' => $reseller_address,
			'rr_external_id' => $external_id,
			'rr_price' => $amount,
			'rr_json_pg' => $json_view,
			'rr_virtual_account' => $nomor_va,
			'rr_bank_code' => $bank_code,
			'ca_id' => $request->address_id,
			'respacket_id' => $request->respacket_id,
			'created_at' => date("Y-m-d H:i:s"),
		];
		$get_idsave = DB::table("reseller_registrations")->insertGetId(array_merge($data_event));

		// SAVE LOG NOTIF *********************** // ********************************************
		$an_description = 'Pendaftaran Reseller dengan no order ' . $external_id . ' telah berhasil di submit. Segera lakukan Pembayaran agar tidak otomatis terbatalkan.';
		$save_notif = DB::table('apps_notif')->insertGetId([
			'id_lookup' => $get_idsave,
			'cust_id' => $cust_id,
			'type_data' => 'reseller',
			'an_title' => 'Pendaftaran berhasil',
			'an_description' => $an_description,
			'created_at' => date('Y-m-d H:i:s'),
		]);

		// Save to PG Master ******************************************************* //

		$cek_channels = DB::table('pg_metod_payget')->select('id', 'bank_code', 'img')
			->where('status', '=', '1')
			->where('bank_code', '=', $bank_code)
			->first();
		if (!empty($cek_channels)) {
			$img_channel = url('public/pg') . '/' . str_replace("svg", "png", $cek_channels->img);
		} else {
			$img_channel = '';
		}

		// OUTPUT JSON SAVE DATA...
		if ($request->bank_code == 'QRIS') {
			// expired date
			$tgl_expired_payment = date('Y-m-d H:i:s', strtotime(date("Y-m-d H:i:s") . ' +1 hours'));
			$data = [
				'id' => $json_parsing->id,
				'owner_id' => "",
				'external_id' => $json_parsing->external_id,
				'account_number' => $json_parsing->external_id,
				'bank_code' => $bank_code,
				'amount' => $json_parsing->amount,
				'qr_string' => $json_parsing->qr_string,
				'callback_url' => $json_parsing->callback_url,
				'description' => $json_parsing->description,
				'type' => $json_parsing->type,
				'merchant_code' => "",
				'name' => $nama_user,
				'is_closed' => true,
				'expected_amount' => $json_parsing->amount,
				'expiration_date' => $tgl_expired_payment,
				'is_single_use' => true,
				'status' => $json_parsing->status,
				'currency' => "IDR",
				'country' => "ID",
				'actions' => null,
				'img' => $img_channel,
				'pt' => 'PT. Mencetak Banyak Gol (Topscore)',
				'banner' => url('public/upload/payment_product.png'),
				'payment_instruction' => $results
			];

			// Save to PG Master ******************************************************* //
			$data_pg = [
				'id_lookup' => $get_idsave,
				'type_data' => 'reseller',
				'external_id' => $external_id,
				'account_number' => $nomor_va,
				'bank_code' => $bank_code,
				'name' => $nama_user,
				'expected_amount' => $json_parsing->amount,
				'status' => $json_parsing->status,
				'created_at' => date("Y-m-d H:i:s"),
			];
			$get_pgmaster = DB::table("payment_xendit")->insertGetId(array_merge($data_pg));
		} else
			// menggunakan E-Wallet
			if (in_array($request->bank_code, array("ID_OVO", "ID_SHOPEEPAY", "ID_DANA", "ID_LINKAJA"))) {
				// expired date
				if ($bank_code == "ID_OVO") {
					$tgl_expired_payment = date('Y-m-d H:i:s', strtotime(date("Y-m-d H:i:s") . ' +1 minutes'));
				} else {
					$tgl_expired_payment = date('Y-m-d H:i:s', strtotime(date("Y-m-d H:i:s") . ' +1 hours'));
				}
				$data = [
					'id' => $json_parsing->id,
					'owner_id' => "",
					'external_id' => $json_parsing->reference_id,
					'account_number' => $json_parsing->reference_id,
					'bank_code' => $bank_code,
					'amount' => $json_parsing->charge_amount,
					'qr_string' => "",
					'callback_url' => $json_parsing->callback_url,
					'description' => "",
					'type' => "",
					'merchant_code' => "",
					'name' => $nama_user,
					'is_closed' => true,
					'expected_amount' => $json_parsing->charge_amount,
					//'expiration_date' => $tgl_indo_expired,
					'expiration_date' => $tgl_expired_payment,
					'is_single_use' => true,
					'status' => $json_parsing->status,
					'currency' => "IDR",
					'country' => "ID",
					'actions' => $json_parsing->actions,

					'img' => $img_channel,
					'pt' => 'PT. Mencetak Banyak Gol (Topscore)',
					'banner' => url('public/upload/payment_product.png'),
					'payment_instruction' => $results
				];

				// Save to PG Master ******************************************************* //
				$data_pg = [
					'id_lookup' => $get_idsave,
					'type_data' => 'reseller',
					'external_id' => $external_id,
					'account_number' => $nomor_va,
					'bank_code' => $bank_code,
					'name' => $nama_user,
					'expected_amount' => $json_parsing->charge_amount,
					'status' => $json_parsing->status,
					'created_at' => date("Y-m-d H:i:s"),
				];
				$get_pgmaster = DB::table("payment_xendit")->insertGetId(array_merge($data_pg));
			}
			// Jika Payment menggunakan VA
			else {
				$data = [
					'id' => $json_parsing->id,
					'owner_id' => $json_parsing->owner_id,
					'external_id' => $json_parsing->external_id,
					'account_number' => $json_parsing->account_number,
					'bank_code' => $bank_code,
					'amount' => $json_parsing->expected_amount,
					'qr_string' => "",
					'callback_url' => "",
					'description' => "",
					'type' => "",
					'merchant_code' => $json_parsing->merchant_code,
					'name' => $json_parsing->name,
					'is_closed' => $json_parsing->is_closed,
					'expected_amount' => $json_parsing->expected_amount,
					'expiration_date' => $json_parsing->expiration_date,
					'is_single_use' => $json_parsing->is_single_use,
					'status' => $json_parsing->status,
					'currency' => $json_parsing->currency,
					'country' => $json_parsing->country,
					'actions' => null,
					'img' => $img_channel,
					'pt' => 'PT. Mencetak Banyak Gol (Topscore)',
					'banner' => url('public/upload/payment_product.png'),
					'payment_instruction' => $results
				];

				// Save to PG Master ******************************************************* //
				$data_pg = [
					'id_lookup' => $get_idsave,
					'type_data' => 'reseller',
					'external_id' => $external_id,
					'account_number' => $nomor_va,
					'bank_code' => $bank_code,
					'merchant_code' => $json_parsing->merchant_code,
					'name' => $json_parsing->name,
					'is_closed' => $json_parsing->is_closed,
					'expected_amount' => $json_parsing->expected_amount,
					'expiration_date' => $json_parsing->expiration_date,
					'status' => $json_parsing->status,
					'currency' => $json_parsing->currency,
					'created_at' => date("Y-m-d H:i:s"),
				];
				$get_pgmaster = DB::table("payment_xendit")->insertGetId(array_merge($data_pg));
			}

		return response()->json($data);
	}

	// web check order
	public function webcekOrders(Request $request)
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

		$external_id = $request->external_id;
		if (!$external_id) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Field external_id tidak boleh kosong!'
				]
			];
			return response()->json($data);
		}

		$row = DB::table("reseller_registrations")
			->select('reseller_registrations.id as pid', 'rr_store_name', 'rr_store_phone', 'rr_external_id', 'rr_price', 'rr_bank_code', 'rr_virtual_account', 'rr_payment_status', 'reseller_registrations.created_at', 'cust_name', 'cust_phone', 'rr_json_pg', 'respacket_name', 'respacket_duration')
			->leftJoin('customers', 'customers.id', '=', 'reseller_registrations.cust_id')
			->leftJoin('reseller_packets', 'reseller_packets.id', '=', 'reseller_registrations.respacket_id')
			->where('rr_external_id', '=', $external_id)
			/*
			->where(function($w) use($external_id) {
				if (!empty($external_id)) {
					$w->where('reseller_registrations.rr_external_id', '=', $external_id);
				}

			})
			*/
			->orderBy('reseller_registrations.created_at', 'DESC')
			//->get();
			->first();
		//$results = array();
		if ($row) {
			if (1 == 1) {
				$json_parsing = json_decode($row->rr_json_pg);

				if ($row->rr_payment_status == "0") {
					$payment_status = 'Unpaid';
				} else
					if ($row->rr_payment_status == "1") {
						$payment_status = 'Paid';
					} else
						if ($row->rr_payment_status == "10") {
							$payment_status = 'Expired';
						}

				// jika menggunakan payment gateway ************** //
				//if($row->rr_json_pg){

				$instruksi_payment = $this->instruksiPayment($row->rr_bank_code);
				$result_pay = array();
				if (!empty($instruksi_payment)) {
					foreach ($instruksi_payment as $row_pay) {
						$result_pay[] = [
							'metode' => $row_pay->metod_instruction_payget,
							'instruction' => $row_pay->instruction_payget,
						];
					}
				}

				// cek channel bank pembayaran
				$cek_channels = DB::table('pg_metod_payget')->select('id', 'bank_code', 'img')
					->where('status', '=', '1')
					->where('bank_code', '=', $row->rr_bank_code)
					->first();
				if (!empty($cek_channels)) {
					$img_channel = url('public/pg') . '/' . str_replace("svg", "png", $cek_channels->img);
				} else {
					$img_channel = '';
				}

				// cek pg payments
				$cek_pgpayment = DB::table('payment_xendit')
					->where('id_lookup', '=', $row->pid)
					->where('type_data', '=', 'reseller')
					->first();
				if (!empty($cek_pgpayment)) {
					$exp_date = date("Y-m-d H:i:s", strtotime($cek_pgpayment->expiration_date));
				} else {
					$exp_date = $row->created_at;
				}

				// tambahkan objek data payment
				$payment_info = "";
				if (in_array($row->rr_bank_code, array("ID_OVO", "ID_SHOPEEPAY", "ID_DANA", "ID_LINKAJA"))) {
					// expired date
					if ($row->rr_bank_code == "ID_OVO") {
						$exp_date_new = date('Y-m-d H:i:s', strtotime($row->created_at . ' +1 minutes'));
					} else {
						$exp_date_new = date('Y-m-d H:i:s', strtotime($row->created_at . ' +3 hours'));
					}
					$payment_info = [
						'channel_type' => 0,
						'external_id' => $json_parsing->reference_id,
						'account_number' => "",
						'bank_code' => $row->rr_bank_code,
						'amount' => (int) $json_parsing->charge_amount,
						'qr_string' => "",
						'callback_url' => "",
						'description' => "",
						'type' => "",
						'merchant_code' => "",
						'name' => $row->rr_store_name,
						'is_closed' => true,
						'expected_amount' => (int) $json_parsing->charge_amount,
						//'expiration_date' => $exp_date,
						'expiration_date' => $exp_date_new,
						'is_single_use' => true,
						'status' => $json_parsing->status,
						'currency' => "IDR",
						'country' => "ID",
						'actions' => $json_parsing->actions,
					];
				} else
					if (in_array($row->rr_bank_code, array("QRIS"))) {
						// expired date
						$exp_date_new = date('Y-m-d H:i:s', strtotime($row->created_at . ' +1 hours'));
						$payment_info = [
							'channel_type' => 2,
							'external_id' => $json_parsing->external_id,
							'account_number' => "",
							'bank_code' => $row->rr_bank_code,
							'amount' => $json_parsing->amount,
							'qr_string' => $json_parsing->qr_string,
							'callback_url' => $json_parsing->callback_url,
							'description' => $json_parsing->description,
							'type' => $json_parsing->type,
							'merchant_code' => "",
							'name' => $row->rr_store_name,
							'is_closed' => true,
							'expected_amount' => $json_parsing->amount,
							'expiration_date' => $exp_date_new,
							'is_single_use' => true,
							'status' => $json_parsing->status,
							'currency' => "IDR",
							'country' => "ID",
							'actions' => null,
						];
					} else {
						$payment_info = [
							'channel_type' => 1,
							'external_id' => $json_parsing->external_id,
							'account_number' => $json_parsing->account_number,
							'bank_code' => $row->rr_bank_code,
							'amount' => $json_parsing->expected_amount,
							'qr_string' => "",
							'callback_url' => "",
							'description' => "",
							'type' => "",
							'merchant_code' => $json_parsing->merchant_code,
							'name' => $json_parsing->name,
							'is_closed' => $json_parsing->is_closed,
							'expected_amount' => $json_parsing->expected_amount,
							'expiration_date' => $exp_date,
							'is_single_use' => $json_parsing->is_single_use,
							'status' => $json_parsing->status,
							'currency' => $json_parsing->currency,
							'country' => $json_parsing->country,
							'actions' => null,
						];
					}

				// expired date
				if ($row->rr_bank_code == "ID_OVO") {
					$exp_date_new = date('Y-m-d H:i:s', strtotime($row->created_at . ' +1 minutes'));
				} else {
					$exp_date_new = date('Y-m-d H:i:s', strtotime($row->created_at . ' +1 hours'));
				}

				// Tampilkan status expired
				if ($exp_date_new < date("Y-m-d H:i:s")) {
					$status_expired = 1;
				} else {
					$status_expired = 0;
				}

				$results = [
					'order_id' => $row->pid,
					'nama_user' => $row->rr_store_name,
					'phone_user' => $row->rr_store_phone,
					'order_invoice' => $row->rr_external_id,
					//'qty' => 1,
					'price' => $row->rr_price,
					//'total_price' => $row->rr_price,
					'external_id' => $row->rr_external_id,
					'account_number' => $row->rr_virtual_account,
					'bank_code' => $row->rr_bank_code,
					'img' => $img_channel,
					'pt' => 'PT. Mencetak Banyak Gol (Topscore)',
					'banner' => url('public/upload/payment_product.png'),
					'payment_status' => $payment_status,
					'created_at' => $row->created_at,
					'expiration_date' => $exp_date_new,
					'payment_info' => $payment_info,
					'payment_instruction' => $result_pay,
					'status_expired' => $status_expired,
					'respacket_name' => $row->respacket_name ?? '',
					'respacket_duration' => $row->respacket_duration ?? '',
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

	// list paket aktif ***
	public function listPacket(Request $request)
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
		// save log traffic
		$data_log = [
			'cust_id' => $cust_id,
			'ta_page_access' => "Reseller",
			'ta_description' => "Mengakses laman reseller aplikasi",
			'created_at' => date("Y-m-d H:i:s"),
		];
		$save_log = DB::table("traffic_apps")->insertGetId(array_merge($data_log));
		*/

		//$arr_setting = DB::table($this->table)->first();

		$arr_setting = DB::table('reseller_settings')->first();

		// FAQ berlangganan
		$query_langganan = DB::table("reseller_faqs")
			->select('*')
			->where('rsf_delete', '=', '0')
			//->orderBy("id","ASC")
			->get();
		$result_faq = array();
		foreach ($query_langganan as $raw_data) {
			$result_faq[] = [
				'resfaq_id' => $raw_data->id,
				'resfaq_name' => $raw_data->rsf_name,
				'resfaq_description' => $raw_data->rsf_description,
			];
		}


		$best_seller = 0;
		$cek_best_seller = DB::table('reseller_registrations')
			->select('respacket_id', DB::raw('COUNT(*) as jml'))
			->where('rr_payment_status', 1)
			->whereNotNull('respacket_id')
			->groupBy('respacket_id')
			->orderByDesc('jml')
			->first();

		$query_data = DB::table("reseller_packets")
			->select('*')
			->where('respacket_delete', '=', '0')
			->orderBy("respacket_duration", "ASC")
			->get();
		$label = '';
		$results = array();
		if (!empty($query_data)) {
			foreach ($query_data as $row) {
				if ($row->respacket_sell_price != $row->respacket_price_tag) {
					$respacket_persen = 100 - (($row->respacket_sell_price / $row->respacket_price_tag) * 100);
					$label = 'Hemat ' . $respacket_persen . '%';
				} else {
					$respacket_persen = 0;
				}

				// best_seller
				if ($cek_best_seller) {
					if ($row->id == $cek_best_seller->respacket_id) {
						$best_seller = 1;
						$label = 'Paling Laku';
					} else {
						$best_seller = 0;
					}
				}

				if ($row->id == 1) {
					$best_seller = 1;
					$label = 'Paling Laku';
				} else {
					$best_seller = 0;
				}

				$results[] = [
					'respacket_id' => $row->id,
					'respacket_name' => $row->respacket_name,
					'respacket_slug' => $row->respacket_slug,
					'respacket_duration' => $row->respacket_duration,
					'respacket_price_tag' => $row->respacket_price_tag,
					'respacket_sell_price' => $row->respacket_sell_price,
					'respacket_discount' => $respacket_persen,
					'respacket_best_seller' => $best_seller,
					'respacket_label' => $label,
				];
			}

			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success'
				],
				//'res_review_banner' => $this->url_server."upload/reseller/review_banner.png",
				'res_review_banner' => $this->url_server . "upload/reseller/foto-berlangganan.webp",
				'respacket' => $results,
				'resfaq' => $result_faq,
				'res_article' => [
					[
						'res_article_title' => 'Potongan Harga 20%',
						'res_article_description' => 'Untuk semua produk topscore',
						'res_article_snk' => $arr_setting->rs_long_description,
						'res_article_detail' => $this->getArticleReseller($cust_id = '', $key_search = '', $topscore = true),
					],
					[
						'res_article_title' => 'Tambahan Diskon 5%',
						'res_article_description' => 'Untuk semua produk non-topscore',
						'res_article_snk' => $arr_setting->rs_terms,
						'res_article_detail' => $this->getArticleReseller($cust_id = '', $key_search = '', $topscore = false),
					],
				],
				'res_info' => '<p style="padding:0 20px;">Masa berlaku mengikuti paket berlangganan (6 atau 12 bulan) dan akan otomatis bertambah setiap kali kamu bertransaksi. Misalnya, jika berlangganan 6 bulan mulai 7 Januari maka masa aktif berakhir 7 Juli. Namun, ketika kamu melakukan pembelian pada 7 Mei, masa aktif langsung diperpanjang 6 bulan dari tanggal tersebut, sehingga berlaku hingga 7 November.</p>',
				'res_review' => [
					[
						'res_review_name' => 'Luthfi ',
						'res_review_title' => 'User Berlangganan',
						'res_review_description' => 'Sebagai reseller, fitur tersebut bermanfaat sekali membantu meningkatkan pendapatan. Bisa jualan barang tanpa repot harus punya stok ',
					],
					[
						'res_review_name' => 'Dejan A',
						'res_review_title' => 'User Berlangganan',
						'res_review_description' => 'Menurut saya berlangganan di aplikasi topscore sangat menguntungkan , karena setiap saya beli sepatu, selalu mendapatkan diskon tambahan mulai dari 5% sampai 20%.',
					],
					[
						'res_review_name' => 'Pagit Tarigan',
						'res_review_title' => 'User Berlangganan',
						'res_review_description' => 'Tanggapan untuk fitur langganan di aplikasi topscore lebih mempermudah untuk belanja ,dan berasa sat set aja.',
					],
					[
						'res_review_name' => 'Goldenboots',
						'res_review_title' => 'User Berlangganan',
						'res_review_description' => 'Buat yang sering beli sepatu ini sangat bermanfaat banget ya, dapet potongan harga soalnya 😁 ',
					]
				],
				'res_info_data' => '<ol type="1">
<li>Program Topscore Berlangganan tidak hanya membuatmu lebih hemat, tetapi juga membuka peluang untuk menjadi reseller Topscore dan menjual produk dengan identitas tokomu sendiri. </li>
<li>Saat mendaftar sebagai reseller, Topscore meminta tiga data: nama reseller (dapat diisi dengan nama pribadi, nama toko, atau nama brand buatan sendiri), alamat reseller, dan nomor telepon reseller.</li>
<li>Ketiga data digunakan sebagai identitas pengirim ketika Topscore mengirimkan produk ke customer kamu.</li>
<li>Walaupun pengiriman dilakukan oleh tim Topscore, paket akan tercantum atas nama kamu sehingga terlihat seolah dikirim langsung dari tokomu sendiri.</li>
<li>Sistem ini memungkinkan kamu fokus berjualan, menentukan harga jual sendiri, dan membangun identitas tokomu.</li>
<li>Seluruh proses logistik ditangani oleh Topscore.</li>
</ol>',
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

	// cek produk topscore dan non topscore
	private function getArticleReseller($cust_id, $key_search = '', $topscore = true)
	{
		$exception = DB::table('exception_locations')->select('pl_code')
			->leftJoin('product_locations', 'product_locations.id', '=', 'exception_locations.pl_id')->get();
		$exc_location = array();
		foreach ($exception as $exc) {
			$exc_location[] = $exc->pl_code;
		}

		$arr_customer = DB::table("customers")->where("id", "=", $cust_id)->first();

		$sub_kat = "";
		$subsub_kat = "";
		$brand = "";
		$product_name = "";
		$sorting_artikel = "baru";
		if (!empty($sorting_artikel)) {
			if ($sorting_artikel == "baru") {
				//$sort_orders = 'purchase_order_article_details.id';
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

		$stock = DB::table('product_stocks')
			->selectRaw('ts_product_stocks.id as pid, pc_name as category, br_name as brand, p_name as article, p_color as color, sz_name as size, ps_price_tag, p_price_tag, ps_sell_price, p_sell_price, p_main_image, p_image, psc_name, pssc_name, sold, SUM(ts_product_stocks.ps_qty) as stock')
			->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
			->leftJoin('product_categories', 'product_categories.id', '=', 'products.pc_id')
			->leftJoin('product_sub_categories', 'product_sub_categories.id', '=', 'products.psc_id')
			->leftJoin('product_sub_sub_categories', 'product_sub_sub_categories.id', '=', 'products.pssc_id')
			->leftJoin('brands', 'brands.id', '=', 'products.br_id')
			->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
			// cek stok
			->where('product_stocks.ps_qty', '>', '0')
			//->where('brands.id', '=', '4') // kusus brand topscore
			->where('p_delete', '=', '0')
			->whereIn('brands.id', ['4', '8', '10', '2', '29']) // topscore, mills, ortuseight, specs, 910
			->whereNotNull('p_main_image')
			->where(function ($w) use ($sub_kat, $subsub_kat, $brand, $product_name, $topscore) {
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
				// $topscore
				if ($topscore == true) {
					$w->where('brands.br_name', '=', 'topscore');
				} else {
					$w->where('brands.br_name', '!=', 'topscore');
					$w->whereRaw('ts_products.p_sell_price != ts_products.p_price_tag');
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
				$store = null;
				$image_url = $this->url_server . "api/product/600/";
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
				$check_discount = DB::table('product_discounts')->select('pd_type', 'pd_value')
					->leftJoin('product_discount_details', 'product_discount_details.pd_id', '=', 'product_discounts.id')
					->where('product_discounts.std_id', '=', '9')
					->where('product_discount_details.pst_id', '=', $row->pid)
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
					->where('pd_start', '<=', date("Y-m-d"))
					->where('pd_date', '>=', date("Y-m-d"))
					->where('pd_start_time', '<=', date("H:i:s"))
					->where('pd_end_time', '>=', date("H:i:s"))
					->where('products.p_name', '=', $row->article)
					->where('products.p_delete', '=', '0')
					->first();

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
									$sell_price = $price_tag; // harga bandrol sebagai harga jual...
									$flag_buy1get1 = 1;
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
				foreach ($stock_color as $row_color) {
					$img = \App\Helpers\ProductImageHelper::parseSingleImage($row_color->p_main_image, $row_color->p_image);
					if (!empty($img)) {
						$image[] = $img;
					}
				}
				$image_url = "https://topsystem.id/api/product/600/";

				// cek ratting produk [new function]
				$qproducts = DB::table("products")->select('id', 'p_ratting', 'p_user_ratting')->where("p_name", "=", $row->article)->where("p_delete", "=", "0")->get();
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
				if ($tot_ratting > 0) {
					$tot_ratting = round($tot_ratting / $jml_data_product, 2);
				}

				if ($tot_ratting == 0) {
					$tot_ratting = 5;
				}

				$cashback_coin = (1 / 100) * $sell_price;
				if ($flag_buy1get1 == 1) {
					$label_promo = 'B1G1';
				} else {
					$label_promo = '';
				}

				$arr_wishlist = DB::table("wishlists")->where('pst_id', $row->pid)->where('cust_id', $cust_id)->where('ws_delete', '=', '0')->first();
				if ($arr_wishlist) {
					$flag_wishlist = 1;
				} else {
					$flag_wishlist = 0;
				}

				// cek produk topscore
				if ($row->brand == "TOPSCORE") {
					$add_persen = 0;
					$persen_potongan = 20;
					$sell_price_persen = ($price_tag * ($persen_potongan / 100)); // default reseller 20%
					$sell_price = $price_tag - $sell_price_persen;
				} else {
					$add_persen = 5;
					$sell_price_persen = ($sell_price * ($add_persen / 100)); // default reseller 5%
					$new_sell_price = $sell_price - $sell_price_persen;
					$sell_price = round($new_sell_price);
					$persen_potongan = $persen_potongan;
				}

				//$persen_potongan = $persen_potongan+$add_persen;
				//if($persen_potongan<=0){ $persen_potongan = $persen_potongan+$add_persen; } else {$persen_potongan = $add_persen;}

				if (count($stock_color) > 0) {
					$results[] = [
						'product_id' => $row->pid,
						'category' => $row->category,
						'brand' => $row->brand,
						'sub_cat' => $row->psc_name,
						'subsub_cat' => $row->pssc_name,
						'article' => $row->article,
						'stock' => $row->stock,
						'price_tag' => $price_tag,
						'sell_price' => $sell_price,
						'discount' => round($persen_potongan),
						'additional_discount' => round($add_persen),
						'sold' => $row->sold,
						'ratting' => $tot_ratting,
						'total_ulasan' => $tot_ratting_user,
						'wishlist' => $flag_wishlist,
						'jumlah_warna' => count($stock_color),
						'image_url' => $image_url,
						'image' => $image,
						'flag_buy1get1' => $flag_buy1get1,
						'cashback_coin' => round($cashback_coin),
						'label_promo' => $label_promo,
					];
				}
			}

		}
		return $results;
	}

	// Profile berlangganan *** 
	public function profileReseller(Request $request)
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
		if (!$cust_id) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'field cust_id tidak boleh kosong!'
				]
			];
			return response()->json($data);
		}

		$arr_customer = DB::table("customers")->where("id", "=", $cust_id)->first();
		if (!$arr_customer) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'data customer tidak ditemukan!'
				]
			];
			return response()->json($data);
		}

		$arr_reseller = DB::table("reseller_registrations")->where(["cust_id" => $cust_id, "rr_payment_status" => "1"])->first();
		if (!$arr_reseller) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'data berlangganan tidak ditemukan!'
				]
			];
			return response()->json($data);
		}

		/*
		if($arr_reseller->rr_address){
			$reseller_address = $arr_reseller->rr_address;
		} else {
			$reseller_address = $arr_customer->cust_address ?? '';
		}
		*/
		$reseller_address = $arr_reseller->rr_address ?: ($arr_customer->cust_address ?? '');
		if ($arr_customer->cust_reseller_active == 1) {
			$res_customer = [
				'res_customer_id' => $arr_reseller->id,
				'res_customer_name' => $arr_reseller->rr_store_name,
				'res_customer_phone' => $arr_reseller->rr_store_phone,
				'res_customer_address' => $reseller_address,
			];
		} else {
			$res_customer = NULL;
		}

		$arr_setting = DB::table('reseller_settings')->first();

		// FAQ berlangganan
		$query_langganan = DB::table("reseller_faqs")
			->select('*')
			->where('rsf_delete', '=', '0')
			//->orderBy("id","ASC")
			->get();
		$result_faq = array();
		foreach ($query_langganan as $raw_data) {
			$result_faq[] = [
				'resfaq_id' => $raw_data->id,
				'resfaq_name' => $raw_data->rsf_name,
				'resfaq_description' => $raw_data->rsf_description,
			];
		}

		// cek pos terbaru
		//$pos_user = DB::table("pos_transactions")->whereNotIn("pos_status",['UNPAID','CANCEL'])->orderBy("id","DESC")->first();
		$pos_user = DB::table('pos_transactions')
			->whereNotIn('pos_status', ['UNPAID', 'CANCEL'])
			->where("cust_id", "=", $cust_id)
			->latest('id')
			->first();
		/*
		if($pos_user){
			// masa aktif 1 tahun kedepan ***
			$date_pos = $pos_user->created_at;
			$expired_reseller = date('Y-m-d H:i:s', strtotime("+1 year", strtotime($date_pos)));
			$cust_reseller_expired = date("d/m/Y H:i:s",strtotime($expired_reseller));
		} else { $cust_reseller_expired=''; }
		*/

		if (!empty($pos_user->created_at)) {
			$created_at = $pos_user->created_at;
		} else {
			$created_at = $arr_reseller->created_at;
		}
		/*
		$cust_reseller_expired = $pos_user ? \Carbon\Carbon::parse($created_at)
			->addYear()
			->format('Y-m-d H:i:s') : '';
		*/
		$cust_reseller_expired = \Carbon\Carbon::parse($created_at)
			->addYear()
			->format('Y-m-d H:i:s');

		// cek badge level
		/*
		if($arr_customer->cust_membership_type=='ROOKIE PLAYER'){$icon_level = url('public/upload/rookie.png');} else
		if($arr_customer->cust_membership_type=='PRO PLAYER'){$icon_level = url('public/upload/pro.png');} else
		if($arr_customer->cust_membership_type=='ELITE PLAYER'){$icon_level = url('public/upload/top.png');}
		*/
		if ($arr_customer->cust_membership_type == 'ROOKIE PLAYER') {
			$icon_level = url('public/upload/badge-rookie.webp');
		} else
			if ($arr_customer->cust_membership_type == 'PRO PLAYER') {
				$icon_level = url('public/upload/badge-pro.webp');
			} else
				if ($arr_customer->cust_membership_type == 'ELITE PLAYER') {
					$icon_level = url('public/upload/badge-elite.webp');
				}

		if ($arr_customer->cust_gender == 'm') {
			$avatar_profile = "https://topsystem.id/upload/avatar/AV1.png";
		} else {
			$avatar_profile = "https://topsystem.id/upload/avatar/AV51.png";
		}

		$cust_profile = [
			'cust_id' => $arr_customer->id,
			'cust_name' => $arr_customer->cust_name,
			'cust_phone' => $arr_customer->cust_phone,
			'cust_email' => $arr_customer->cust_email ?? '',
			'cust_level' => $arr_customer->cust_membership_type,
			'cust_member_expired' => $cust_reseller_expired,
			'cust_icon_level' => $icon_level,
			'cust_avatar' => $arr_customer->cust_avatar ?? $avatar_profile,
		];

		$results = array();
		if (1 == 1) {
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success'
				],
				'cust_profile' => $cust_profile,
				'res_customer' => $res_customer,
				'resfaq' => $result_faq,
				'res_article' => [
					[
						'res_article_title' => 'Potongan Harga 20%',
						'res_article_description' => 'Untuk semua produk topscore',
						'res_article_snk' => $arr_setting->rs_long_description,
						//'res_article_detail' => $this->getArticleReseller($cust_id='', $key_search='', $topscore=true),
					],
					[
						'res_article_title' => 'Tambahan Diskon 5%',
						'res_article_description' => 'Untuk semua produk non-topscore',
						'res_article_snk' => $arr_setting->rs_terms,
						//'res_article_detail' => $this->getArticleReseller($cust_id='', $key_search='', $topscore=false),
					],
				],
				//'res_info' => '<p style="padding:0 20px;">Masa berlaku mengikuti paket berlangganan (6 atau 12 bulan) dan akan otomatis bertambah setiap kali kamu bertransaksi. Misalnya, jika berlangganan 6 bulan mulai 7 Januari maka masa aktif berakhir 7 Juli. Namun, ketika kamu melakukan pembelian pada 7 Mei, masa aktif langsung diperpanjang 6 bulan dari tanggal tersebut, sehingga berlaku hingga 7 November.</p>',
				'res_info' => '<ol type="1">
<li>Program Topscore Berlangganan tidak hanya membuatmu lebih hemat, tetapi juga membuka peluang untuk menjadi reseller Topscore dan menjual produk dengan identitas tokomu sendiri. </li>
<li>Saat mendaftar sebagai reseller, Topscore meminta tiga data: nama reseller (dapat diisi dengan nama pribadi, nama toko, atau nama brand buatan sendiri), alamat reseller, dan nomor telepon reseller.</li>
<li>Ketiga data digunakan sebagai identitas pengirim ketika Topscore mengirimkan produk ke customer kamu.</li>
<li>Walaupun pengiriman dilakukan oleh tim Topscore, paket akan tercantum atas nama kamu sehingga terlihat seolah dikirim langsung dari tokomu sendiri.</li>
<li>Sistem ini memungkinkan kamu fokus berjualan, menentukan harga jual sendiri, dan membangun identitas tokomu.</li>
<li>Seluruh proses logistik ditangani oleh Topscore.</li>
</ol>',
				/*
				'res_review' => [
					[
						'res_review_name' => 'Siti Maryam Ilyas',
						'res_review_title' => 'Ibu Rumah Tangga',
						'res_review_description' => 'berlangganan topscore belanja sepatu jadi lebih hemat untuk ibu rumah tangga seperti saya yang perlu beli sepatu buat suami dan anak-anak',
					],
					[
						'res_review_name' => 'Kristoni Hidayat',
						'res_review_title' => 'Pengusaha Sepatu',
						'res_review_description' => 'sudah lama berlangganan topscore ini, saya jadi bisa mulai usaha saya jadi reseller sepatu. sudah punya toko sendiri masih kecil-kecilan tapi nyambung terus',
					],
					[
						'res_review_name' => 'Brandon Alexander',
						'res_review_title' => 'Karyawan Swasta',
						'res_review_description' => 'di kantor suka pada rutin beli sepatu olahraga karena eventnya banyak banget, dengan program topscore berlangganan ini saya jadi bisa jadi kaya raya',
					]
				],
				*/
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

	// update profile pelanggan new ***
	public function customerResellerupdate_new(Request $request)
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
		//$reseller_id = $request->reseller_id;
		$res_store = $request->res_store;
		$res_phone = $request->res_phone;
		if (!$cust_id || !$res_store || !$res_phone) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Field cust_id, res_store, dan res_phone tidak boleh kosong!'
				]
			];
			return response()->json($data);
		}

		$arr_customer = DB::table("customers")->where(["cust_reseller" => "1", "id" => $cust_id, "cust_delete" => "0"])->first();
		if (!$arr_customer) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data customer tidak ditemukan!'
				]
			];
			return response()->json($data);
		}

		// cek data yang sudah lunas pendaftarannya
		//$arr_reseller = DB::table("reseller_registrations")->where(["rr_payment_status"=>"1", "cust_id"=>$cust_id, "id"=>$reseller_id])->first();
		$arr_reseller = DB::table("reseller_registrations")->where(["rr_payment_status" => "1", "cust_id" => $cust_id])->orderBy("id", "DESC")->first();
		if (!$arr_reseller) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data reseller tidak ditemukan!'
				]
			];
			return response()->json($data);
		}

		// lakukan update data...
		$updated_reseller = [
			'rr_store_name' => $request->res_store,
			'rr_store_phone' => $request->res_phone,
			'rr_address' => $request->res_address,
		];
		DB::table("reseller_registrations")->where('id', $arr_reseller->id)->update($updated_reseller);

		// update menjadi 1 (cust_reseller_active)
		DB::table("customers")->where('id', $cust_id)->update(['cust_reseller_active' => '1']);

		$data = [
			'status' => [
				'code' => 200,
				'description' => 'Success'
			]
		];

		return response()->json($data);
	}

	// non aktif reseller
	public function offuserReseller(Request $request)
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
		$reseller_id = $request->reseller_id;
		if (!$cust_id || !$reseller_id) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Field cust_id dan reseller_id tidak boleh kosong!'
				]
			];
			return response()->json($data);
		}

		$arr_customer = DB::table("customers")->where(["cust_reseller" => "1", "id" => $cust_id, "cust_delete" => "0"])->first();
		if (!$arr_customer) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data customer tidak ditemukan!'
				]
			];
			return response()->json($data);
		}

		// cek data yang sudah lunas pendaftarannya
		$arr_reseller = DB::table("reseller_registrations")->where(["rr_payment_status" => "1", "cust_id" => $cust_id, "id" => $reseller_id])->first();
		if (!$arr_reseller) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data reseller tidak ditemukan!'
				]
			];
			return response()->json($data);
		}

		$updated_reseller = [
			'cust_reseller_active' => "0",
		];
		DB::table("customers")->where('id', $cust_id)->update($updated_reseller);


		$data = [
			'status' => [
				'code' => 200,
				'description' => 'Success'
			]
		];

		return response()->json($data);
	}

	// non aktifkan berlagganan
	public function offReseller(Request $request)
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
		$reseller_id = $request->reseller_id;
		if (!$cust_id || !$reseller_id) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Field cust_id dan reseller_id tidak boleh kosong!'
				]
			];
			return response()->json($data);
		}

		$arr_customer = DB::table("customers")->where(["cust_reseller" => "1", "id" => $cust_id, "cust_delete" => "0"])->first();
		if (!$arr_customer) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data customer tidak ditemukan!'
				]
			];
			return response()->json($data);
		}

		// cek data yang sudah lunas pendaftarannya
		$arr_reseller = DB::table("reseller_registrations")->where(["rr_payment_status" => "1", "cust_id" => $cust_id, "id" => $reseller_id])->first();
		if (!$arr_reseller) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data reseller tidak ditemukan!'
				]
			];
			return response()->json($data);
		}

		// lakukan update data...
		/*
		$updated_reseller = [
			'rr_store_name' => $request->res_store,
			'rr_store_phone' => $request->res_phone,
			'rr_address' => $request->res_address,
		];
		DB::table("reseller_registrations")->where('id', $reseller_id)->update($updated_reseller);
		*/

		$updated_reseller = [
			'cust_reseller' => "0",
		];
		DB::table("customers")->where('id', $cust_id)->update($updated_reseller);


		$data = [
			'status' => [
				'code' => 200,
				'description' => 'Success'
			]
		];

		return response()->json($data);
	}

	// cancel order reseller ***
	public function cancelOrders(Request $request)
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
		$reseller_id = $request->reseller_id;
		if (!$cust_id || !$reseller_id) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Field cust_id dan reseller_id tidak boleh kosong!'
				]
			];
			return response()->json($data);
		}

		$arr_customer = DB::table("customers")->where(["id" => $cust_id, "cust_delete" => "0"])->first();
		if (!$arr_customer) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data customer tidak ditemukan!'
				]
			];
			return response()->json($data);
		}

		// cek data yang sudah lunas pendaftarannya
		$arr_reseller = DB::table("reseller_registrations")->where(["rr_payment_status" => "0", "cust_id" => $cust_id, "id" => $reseller_id])->first();
		if (!$arr_reseller) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data reseller tidak ditemukan!'
				]
			];
			return response()->json($data);
		}

		$updated_reseller = [
			'rr_payment_status' => "2",
		];
		DB::table("reseller_registrations")->where('id', $arr_reseller->id)->update($updated_reseller);

		$data = [
			'status' => [
				'code' => 200,
				'description' => 'Success'
			]
		];

		return response()->json($data);
	}
}
