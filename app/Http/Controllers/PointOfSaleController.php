<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;


class PointOfSaleController extends Controller
{
	protected $server_domain = 'https://api.xendit.co';
	//protected $secret_api_key = 'xnd_development_E7did9g3635fyKoLPg736TkunasCCKhDeHFWy5LiYRkRzxXMmZrd4PsV9lNjgR'; // SANDBOX
	protected $secret_api_key;

	public function __construct()
	{
		$this->secret_api_key = config('services.xendit.secret_key');
	}
	protected $url_server = 'https://topsystem.id/';

	/*
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
	*/

	private function reloadItemTotal(Request $request)
	{
		$pt_id = $request->_pt_id;
		$check_ptd = DB::table('')->select('pos_td_qty', 'pos_td_total_price')->where([
			'pt_id' => $pt_id,
		])->get();
		if (!empty($check_ptd)) {
			$total_item = 0;
			$total_price = 0;
			foreach ($check_ptd as $row) {
				$total_item += $row->pos_td_qty;
				$total_price += $row->pos_td_total_price;
			}
			$r['total_item'] = $total_item;
			$r['total_price'] = $total_price;
			$r['status'] = '200';
		} else {
			$r['status'] = '400';
		}
		return json_encode($r);
	}

	private function generateExternal_id()
	{
		$invoice = date('YmdHis') . rand(10000, 99999);
		if ($this->InvoiceExists($invoice)) {
			return generateExternal_id();
		}
		return $invoice;
	}

	private function InvoiceExists($number)
	{
		return DB::table('pos_transactions')->where(['external_id' => $number])->exists();
	}

	// POS
	public function saveTransaction(Request $request)
	{
		//date_default_timezone_set("Asia/Jakarta");
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
		$u_id = '198'; // AUTOMATIC APPS USERS
		//$u_id = '61'; // AUTOMATIC website
		$stt_id = 1; // ts_store_types [ONLINE]
		$cr_id = '5'; //$request->cr_id; // couriers		
		//$store_id = $request->store_id;
		$pg_name = $request->pg_name; // payment gateway == XENDIT, MIDTRANS
		$pm = DB::table('payment_methods')->select('id')->where('pm_name', '=', $pg_name)->first();
		$pm_id = $pm ? $pm->id : 1;
		$std = DB::table('store_type_divisions')->select('id')->where('dv_name', '=', 'APPS')->first();
		$std_id = $std ? $std->id : 1;
		$cust_id = $request->cust_id;
		$bank_code = $request->bank_code;
		$amount = $request->amount;
		//$nama_user = $request->nama_user;
		//$nama_user = str_replace(['(',')'],['',''],$request->nama_user); 
		$nama_user = str_replace(['(', ')', '+'], ['', '', ''], $request->nama_user);
		$phone = $request->phone;

		if (empty($nama_user)) {
			$result_nama = preg_replace("/[^0-9]/", "", $phone);
			$nama_user = $result_nama;
		} else {
			$nama_user = $nama_user;
		}

		$email = $request->email;
		$address = $request->address;
		$address_id = $request->address_id;
		$voucher_id = $request->voucher_id;
		// total ongkir *********** 
		$total_ongkir = $request->total_ongkir;
		$cross = true;
		///$st_id = $store_id;
		$admin_cost = 0; //round($request->admin_cost);
		$another_cost = 0; //$request->another_cost;
		//$shipping_cost = $request->shipping_cost;
		$ref_number = $this->generateExternal_id();
		$external_id = $ref_number;
		$cross_order = "1";
		$coin = $request->coin; // cek nilai coin
		$arr_customers = DB::table("customers")->where("id", "=", $cust_id)->first();
		/*
		if ($cross == true) {
		  $cross_order = '1';
		  $st_id_ref = $st_id;
		} else {
		  $cross_order = '0';
		  $st_id_ref = null;
		}
		if ($st_id == '4') {
		  $st_omset = $st_id;
		} else {
		  $st_omset = $store_id;
		}
		*/
		//$note = $request->note;
		// load data json dari postingan data POS
		// [{"store_id": "1", "total_price": 10000, "shipping_cost": 0, "note": "", }]

		$master_pos = $request->master_pos;
		$item_details = $request->item_details;
		$master_pos = json_decode($master_pos);
		//$array_item=array();
		$invoice_details = array();
		$detail_item_json = array();
		$detail_item_save = array();
		$detail_item_save = array();

		// save log form transaksi
		$save_idlog = DB::table('pos_log')->insertGetId([
			'cust_id' => $cust_id,
			'external_id' => $ref_number,
			//'json_pos' => $master_pos,
			//'json_detail_pos' => $item_details,
			'json_pos' => json_encode($master_pos),
			'json_detail_pos' => ($item_details),
			'created_at' => date("Y-m-d H:i:s"),
		]);

		// Guard B1G1: setiap item price=0 wajib punya promo buy1get1 aktif di pst_id-nya (std_id=20/APPS)
		// ponytail: lookup DB per free-item; blok cross-product exploit (beli A diskon%, dapat B gratis)
		$items_decoded = json_decode($item_details);
		if (!empty($items_decoded) && is_array($items_decoded)) {
			$store_item_stats = [];
			foreach ($items_decoded as $item) {
				$s_id = $item->store_id ?? 0;
				$i_qty = isset($item->qty) ? (int)$item->qty : (isset($item->quantity) ? (int)$item->quantity : 1);
				$i_price = isset($item->price) ? (float)$item->price : 0;
				if (!isset($store_item_stats[$s_id])) {
					$store_item_stats[$s_id] = ['total_qty' => 0, 'free_qty' => 0];
				}
				$store_item_stats[$s_id]['total_qty'] += $i_qty;

				if ($i_price <= 0) {
					$store_item_stats[$s_id]['free_qty'] += $i_qty;

					// Validasi: item gratis harus punya promo B1G1 aktif di pst_id-nya
					$free_pst_id = null;
					if (isset($item->pls_id)) {
						$pls_row = DB::table('product_location_setups')->select('pst_id')->where('id', $item->pls_id)->first();
						if ($pls_row) $free_pst_id = $pls_row->pst_id;
					}
					if (!$free_pst_id && isset($item->product_name) && isset($item->color) && isset($item->size)) {
						$pst_row = DB::table('product_stocks')
							->select('product_stocks.id')
							->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
							->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
							->where('products.p_name', $item->product_name)
							->where('products.p_color', $item->color)
							->where('sizes.sz_name', $item->size)
							->first();
						if ($pst_row) $free_pst_id = $pst_row->id;
					}

					if ($free_pst_id) {
						$has_b1g1 = DB::table('product_discounts')
							->join('product_discount_details', 'product_discount_details.pd_id', '=', 'product_discounts.id')
							->where('product_discounts.std_id', '20') // APPS
							->where('product_discount_details.pst_id', $free_pst_id)
							->where('product_discounts.pd_type', 'buy1get1')
							->where('product_discounts.pd_date', '>=', date('Y-m-d'))
							->exists();

						if (!$has_b1g1) {
							return response()->json([
								'status' => [
									'code' => 400,
									'description' => 'Item gratis tidak memiliki promo Buy 1 Get 1 yang valid. Checkout tidak dapat diproses.'
								]
							]);
						}
					}
				}
			}

			foreach ($store_item_stats as $s_id => $stats) {
				$max_free = (int)floor($stats['total_qty'] / 2);
				if ($stats['free_qty'] > $max_free) {
					return response()->json([
						'status' => [
							'code' => 400,
							'description' => 'Promo B1G1 minimal 2 barang dalam toko yang sama! Tidak dapat checkout hanya barang potongan Rp 0.'
						]
					]);
				}
			}
		}

		// save to data POS
		$cek_total_store = count($master_pos); // total store sesuai artikel yang dipilih customer ***
		$cek_vcproduk = 0;
		$cek_vcongkir = 0;
		// cek jika ada voucher
		if ($voucher_id) {
			$arr_vouchers = DB::table("apps_vouchers")->where("id", "=", $voucher_id)->first();
			// jika voucher ditemukan
			if ($arr_vouchers) {
				// diskon kusus ongkos kirim
				if ($arr_vouchers->avc_category == '2') {
					$cek_vcongkir = 1;
					if ($total_ongkir > 0) {
						//$new_ongkir = round($total_ongkir/$cek_total_store); // tampilkan nilai sisa ongkir per store
						if ($cek_total_store > 1) {
							$new_ongkir = round($total_ongkir / $cek_total_store); // tampilkan nilai sisa ongkir per store
						} else {
							$new_ongkir = $total_ongkir;
						}
					} else {
						$new_ongkir = 0;
					}
				} else
					// diskon voucher produk
					if ($arr_vouchers->avc_category == '1') {
						$cek_vcproduk = 1;
						$total_discount = $request->total_discount;
						if ($total_discount > 0) {
							if ($cek_total_store > 1) {
								$new_diskon = round($total_discount / $cek_total_store);
							} else {
								$new_diskon = $total_discount;
							}
						} else {
							$new_diskon = 0;
						}
					}
			}
		}

		// cek jika ada coin
		if ($coin > 0) {
			$new_coin = round($coin / $cek_total_store);
		} else {
			$new_coin = $coin;
		}

		// cek pos fees
		if ($request->pos_fees > 0) {
			$total_fees = $request->pos_fees;
			$pos_fees = round($total_fees / $cek_total_store);
		} else {
			$pos_fees = 0;
		}

		$no_simpan = 0;
		$coin_use_all = 0;
		foreach ($master_pos as $key => $lookup) {
			$store_id = $lookup->store_id;
			$shipping_cost = $lookup->shipping_cost;
			$another_cost = $lookup->another_cost;
			$admin_cost = $lookup->admin_cost;
			$real_price = round($lookup->total_price);
			$note = $lookup->note;
			$pt_id_complaint = NULL;
			$sub_cust_id = NULL;
			$cp_id = NULL;
			$unique_code = NULL;
			//$expedisi = $lookup->expedisi;
			$arr_expedisi = explode("#service", $lookup->expedisi);
			$expedisi = $arr_expedisi[0];
			if (!empty($arr_expedisi[1])) {
				$expedisi_service = $arr_expedisi[1];
			} else {
				$expedisi_service = '';
			}

			$expedisi_service = strtoupper($expedisi_service);

			$pos_discount = $lookup->pos_discount; // tambahan
			// generate invoice nomor...
			$rand = str_pad(rand(0, pow(10, 3) - 1), 3, '0', STR_PAD_LEFT);
			$prefix = DB::table('web_configs')->select('config_value')->where('config_name', 'pos_prefix')->get()->first()->config_value;

			$store_prefix = DB::table('stores')->select('st_description')->where('id', '=', $store_id)->get()->first()->st_description;
			$div_prefix = DB::table('store_type_divisions')->select('dv_description')->where('id', '=', $std_id)->get()->first()->dv_description;
			$sorting_number = DB::table('pos_transactions')->where([
				'std_id' => $std_id,
				'st_id' => $store_id,
			])->whereDate('created_at', date('Y-m-d'))->count('id');

			$invoice = $prefix . date('YmdHis') . $rand . $store_prefix . $div_prefix . ($sorting_number + 1);

			// cek total nominal jika ada potongan ongkir
			/*
			if($cek_vcongkir==1){
				$shipping_cost_new = $new_ongkir;
				$real_price_new = ($real_price-$shipping_cost) + $new_ongkir;
			} else {
				$shipping_cost_new = $shipping_cost;
				$real_price_new = $real_price;
			}
			*/

			// jika coin melebihi nilai invoice 
			if ($new_coin > $real_price) {
				$total_coin = $real_price;
			} else {
				$total_coin = $new_coin;
			}
			$coin_use_all += $total_coin; // total penggunaan coin

			// cek total nominal jika ada potongan ongkir
			if ($cek_vcongkir == 1) {
				$shipping_cost_new = $new_ongkir;
				$pos_discount_new = $pos_discount;
				//$real_price_new = (($real_price-$shipping_cost) + $new_ongkir) - $new_coin;
				$real_price_new = (($real_price - $shipping_cost) + $new_ongkir) - $total_coin;
			} else
				if ($cek_vcproduk == 1) {
					$shipping_cost_new = $shipping_cost;
					$pos_discount_new = $new_diskon;
					//$real_price_new = ($real_price - $new_diskon) - $new_coin;
					$real_price_new = ($real_price - $new_diskon) - $total_coin;
				} else {
					$shipping_cost_new = $shipping_cost;
					$pos_discount_new = $pos_discount;
					//$real_price_new = $real_price - $new_coin;
					$real_price_new = $real_price - $total_coin;
				}

			// cek reseller
			if ($request->reseller == 1) {
				$pos_reseller = 1;
			} else {
				$pos_reseller = 0;
			}
			//if($request->pos_fees>0){$pos_fees=$request->pos_fees;} else {$pos_fees=0;}

			// save to transactions
			$insert_get_id = DB::table('pos_transactions')->insertGetId([
				'u_id' => $u_id, //
				'st_id' => 13, // Store default TIM ONLINE
				'stt_id' => $stt_id,
				'pm_id' => $pm_id,
				'cp_id' => $cp_id,
				'std_id' => $std_id,
				'cust_id' => $cust_id,
				'pt_id_ref' => $pt_id_complaint,
				'sub_cust_id' => $sub_cust_id,
				'pos_admin_cost' => $admin_cost,
				'pos_another_cost' => $another_cost,
				//'pos_real_price' => $real_price, 
				'pos_real_price' => ($real_price_new + $pos_fees),
				'pos_order_number' => $ref_number,
				'pos_invoice' => $invoice,
				'pos_unique_code' => $unique_code,
				//'pos_shipping' => $shipping_cost, 
				'pos_shipping' => $shipping_cost_new,
				//'pos_discount' => $pos_discount, // diskon tambahan
				'pos_discount' => $pos_discount_new,
				//'pos_ref_number' => $ref_number,
				'cr_id' => $cr_id,
				'pos_note' => $note,
				'created_at' => date('Y-m-d H:i:s'),
				'pos_draft' => '0',
				'pos_refund' => '0',
				'st_id_ref' => $store_id,
				'cross_order' => $cross_order,
				'is_apps' => 1,
				'type_pg' => $pg_name,
				//'json_pg' => $json_pg,
				//'fee_pg' => $fee_pg,
				'external_id' => $ref_number,
				//'va_number' => $va_number,
				'pos_status' => 'UNPAID',
				'expedisi' => $expedisi,
				'pos_expedisi_service' => $expedisi_service, // new field
				'voucher_apps_id' => $voucher_id,
				//'voucher_apps_id' => 1,
				'pos_courier' => $expedisi,
				'pos_web_payment' => $pg_name,
				'pos_cust_address' => $address,
				'pos_address_id' => $address_id,
				//'pos_coin' => (int)$coin,
				//'pos_coin' => (int)$new_coin,
				'pos_coin' => (int) $total_coin,
				'pos_reseller' => $pos_reseller,
				'pos_fees' => $pos_fees,
				//'pos_url' => '',

			]);
			// simpan data berhasil...
			if (!empty($insert_get_id)) {
				$store_name = DB::table('stores')->select('st_name')->where('id', '=', $store_id)->get()->first()->st_name;
				//$detail_item_save[] = $this->saveTransactionDetail($insert_get_id, $u_id, $store_id, $item_details); // save details
				$detail_item_save = $this->saveTransactionDetail($insert_get_id, $u_id, $store_id, $item_details); // save details

				// cek jika ada free item
				if ($request->item_free != '') {
					$save_item_free = $this->saveTransactionBenefit($insert_get_id, $u_id, $store_id, $request->item_free); // save benefit dan GWP
					$save_item_detail_array = array_merge($detail_item_save, $save_item_free);
				} else {
					$save_item_detail_array = $detail_item_save;
				}

				$invoice_details[] = [
					'pos_id' => $insert_get_id,
					'pos_invoice' => $invoice,
					'store_id' => $store_id,
					'store_name' => $store_name,
					'total_price' => $real_price,
					'note' => $note,
					//'item_details' => $detail_item_save,
					'item_details' => $save_item_detail_array,
				];

				// update pos_url
				//$r['trx_id'] = Crypt::encryptstring($insert_get_id);
				$pos_url = url('pesanan') . '/' . Crypt::encryptstring($insert_get_id);
				DB::table('pos_transactions')->where('id', '=', $insert_get_id)->update([
					'pos_url' => $pos_url
				]);

				$no_simpan++;
				/*
				if (!empty($voc_pst_id)) {
					$used_voucher = DB::table('voucher_transactions')->insert([
						'vc_id' => $voc_id,
						'pt_id' => $insert_get_id,
						'u_id' => Auth::user()->id,
						'cust_id' => $cust_id,
						'pst_id' => $voc_pst_id,
						'value' => $voc_value,
						'created_at' => date('Y-m-d'),
						'updated_at' => date('Y-m-d'),
					]);
				}
				*/
			}

		}

		if ($coin > 0) {
			//if($coin>$amount){$use_coin=$amount;} else {$use_coin=$coin;}
			if ($coin > $amount) {
				$use_coin = $coin_use_all;
			} else {
				$use_coin = $coin;
			}
			//$coin_sisa = $arr_customers->cust_coin - $amount;
			$coin_sisa = $arr_customers->cust_coin - $use_coin;
			if ($coin_sisa < 0) {
				$coin_sisa = 0;
			} else {
				$coin_sisa = $coin_sisa;
			}
			$update_cust = DB::table('customers')->where('id', '=', $arr_customers->id)->update(['cust_coin' => $coin_sisa]);
			// log penggunaan coin
			$insert_get_id = DB::table('customer_coin_withdraws')->insertGetId([
				'cust_id' => $cust_id,
				'ccw_type' => 'pos',
				'ccw_lookup_id' => $external_id,
				'ccs_description' => 'Penggunaan coin pada proses order artikel dengan no order ' . $external_id,
				'ccw_value' => $use_coin,
				'created_at' => date('Y-m-d H:i:s'),
			]);
		}

		// Jika COD ***
		if ($pg_name == "COD") {
			//$update_pos = DB::table('pos_transactions')->where('external_id', '=', $external_id)->update(['pos_status' => "UNPAID"]);
			// SAVE LOG NOTIF *********************** // ********************************************
			$an_description = 'Pesanan ' . $external_id . ' telah berhasil di order secara COD. Silahkan lakukan pembayaran ketika barang sudah kamu terima.';
			$save_notif = DB::table('apps_notif')->insertGetId([
				'id_lookup' => $external_id,
				'cust_id' => $cust_id,
				'type_data' => 'pos',
				'an_title' => 'Pesanan berhasil',
				'an_description' => $an_description,
				'created_at' => date('Y-m-d H:i:s'),
			]);
			// notif pesana terbayarkan
			/*
			$an_description = 'Pesanan '.$external_id.' telah berhasil dibayarkan dengan nominal Rp '.$amount;
			$save_notif = DB::table('apps_notif')->insertGetId([
						'id_lookup' => $external_id,
						'cust_id' => $cust_id,
						'type_data' => 'pos',
						'an_title' => 'Pesanan sudah dibayar',
						'an_description' => $an_description,
						'created_at' => date('Y-m-d H:i:s'),
			]);
			*/

			$instruksi_payment = $this->instruksiPayment($bank_code);
			$result_payment = array();
			if (!empty($instruksi_payment)) {
				foreach ($instruksi_payment as $row) {
					$result_payment[] = [
						'metode' => $row->metod_instruction_payget,
						'instruction' => $row->instruction_payget,
					];
				}
			}

			// save payment log...
			$data_pg = [
				'id_lookup' => $external_id,
				'type_data' => 'pos',
				'external_id' => $external_id,
				'account_number' => "",
				'bank_code' => $bank_code,
				'name' => $nama_user,
				'expected_amount' => (int) $amount,
				'status' => "PENDING",
				'created_at' => date("Y-m-d H:i:s"),
			];
			$get_pgmaster = DB::table("payment_xendit")->insertGetId(array_merge($data_pg));

			$img_channel = '';
			$data_payment = [
				'id' => $external_id,
				'owner_id' => "",
				'external_id' => $external_id,
				'qr_string' => "",
				'callback_url' => "",
				'description' => "",
				'type' => "",
				'account_number' => "",
				'bank_code' => $bank_code,
				'merchant_code' => "",
				'name' => $nama_user,
				'is_closed' => true,
				'expected_amount' => (int) $amount,
				'expiration_date' => date("Y-m-d H:i:s"),
				'is_single_use' => false,
				'status' => 'UNPAID',
				'currency' => "IDR",
				'country' => "",
				'img' => $img_channel,
				'actions' => null,
				'pt' => 'PT. Mencetak Banyak Gol (Topscore)',
				'payment_instruction' => $result_payment,
			];
			$r['status'] = '200';
			$r['external_id'] = $external_id;
			$r['payment_gateway'] = $pg_name;
			$r['banner'] = url('public/upload/payment_product.png');
			$r['invoice_details'] = $invoice_details;
			$r['data_payment'] = $data_payment;

			return response()->json($r);
		} else
			// END COD

			// jika payment hanya dari coin OKE ************************************** //
			if (($coin >= $amount) && $amount <= 0) {
				$update_pos = DB::table('pos_transactions')->where('external_id', '=', $external_id)->update(['pos_status' => "PAID"]);
				// SAVE LOG NOTIF *********************** // ********************************************
				$an_description = 'Pesanan ' . $external_id . ' telah berhasil di order. Segera lakukan Pembayaran agar tidak otomatis terbatalkan pesanan anda.';
				$save_notif = DB::table('apps_notif')->insertGetId([
					'id_lookup' => $external_id,
					'cust_id' => $cust_id,
					'type_data' => 'pos',
					'an_title' => 'Pesanan berhasil',
					'an_description' => $an_description,
					'created_at' => date('Y-m-d H:i:s'),
				]);
				// notif pesana terbayarkan
				$an_description = 'Pesanan ' . $external_id . ' telah berhasil dibayarkan dengan nominal Rp ' . $amount;
				$save_notif = DB::table('apps_notif')->insertGetId([
					'id_lookup' => $external_id,
					'cust_id' => $cust_id,
					'type_data' => 'pos',
					'an_title' => 'Pesanan sudah dibayar',
					'an_description' => $an_description,
					'created_at' => date('Y-m-d H:i:s'),
				]);
				$img_channel = '';
				$data_payment = [
					'id' => $external_id,
					'owner_id' => "",
					'external_id' => $external_id,
					'qr_string' => "",
					'callback_url' => "",
					'description' => "",
					'type' => "",
					'account_number' => "",
					'bank_code' => $bank_code,
					'merchant_code' => "",
					'name' => $nama_user,
					'is_closed' => true,
					'expected_amount' => (int) $amount,
					'expiration_date' => date("Y-m-d H:i:s"),
					'is_single_use' => false,
					'status' => 'PAID',
					'currency' => "IDR",
					'country' => "",
					'img' => $img_channel,
					'actions' => null,
					'pt' => 'PT. Mencetak Banyak Gol (Topscore)',
					'payment_instruction' => array(),
				];
				$r['status'] = '200';
				//$r['pt_id'] = $insert_get_id;
				$r['external_id'] = $external_id;
				$r['payment_gateway'] = $pg_name;
				$r['banner'] = url('public/upload/payment_product.png');
				$r['invoice_details'] = $invoice_details;
				$r['data_payment'] = $data_payment;

				return response()->json($r);
			}
		// END jika payment hanya dari coin OKE ************************************** //

		//$amount = $amount - $coin; // nilai pengurangan total jika ada coin dan PG
		$amount = $amount;

		//$item_details = $array_item;
		$detail_item_json[] = $detail_item_save;

		// SAVE LOG NOTIF *********************** // ********************************************
		$an_description = 'Pesanan ' . $external_id . ' telah berhasil di order. Segera lakukan Pembayaran agar tidak otomatis terbatalkan pesanan anda.';
		$save_notif = DB::table('apps_notif')->insertGetId([
			'id_lookup' => $external_id,
			'cust_id' => $cust_id,
			'type_data' => 'pos',
			'an_title' => 'Pesanan berhasil',
			'an_description' => $an_description,
			'created_at' => date('Y-m-d H:i:s'),
		]);

		// Proses PAYMENT ***********************************************************************
		$curl = curl_init();

		$tgl_indo_expired = date('Y-m-d H:i:s', strtotime(date("Y-m-d H:i:s") . ' -4 hours'));

		$headers = array();
		$headers[] = 'Content-Type: application/json';

		$disbursement_options = [];
		if (!empty($disbursement_options['X-IDEMPOTENCY-KEY'])) {
			array_push($headers, 'X-IDEMPOTENCY-KEY: ' . $disbursement_options['X-IDEMPOTENCY-KEY']);
		}

		/*
		if($request->bank_code !='QRIS'){
			$end_point = $this->server_domain.'/callback_virtual_accounts';

			$tgl_indo_expired = date('Y-m-d H:i:s', strtotime(date("Y-m-d H:i:s"). ' -4 hours'));
			//$tgl_expired = date('Y-m-d H:i:s', strtotime($tgl_indo_expired. ' +6 hours'));

			$data['external_id'] = $external_id;
			$data['name'] = $nama_user;
			$data['bank_code'] = $bank_code;
			$data['expected_amount'] = (int)$amount;
			//$data['expiration_date'] = date('Y-m-d', strtotime(date("Y-m-d"). ' + 1 days'))." 23:59:59"; 
			//$data['expiration_date'] = $tgl_expired; 
			$data['expiration_date'] = $tgl_indo_expired; 
			$data['is_closed'] = true;
			/
			if ( is_array($bank_options) ) {
				foreach ( $bank_options as $key => $value ) {
					$data[$key] = $value;
				}
			}
			/
		} 
		// jika Payment mengunakan QRIS
		else {
			$end_point = $this->server_domain.'/qr_codes';

			$data['external_id'] = $external_id;
			$data['amount'] = (int)$amount;
			$data['type'] = 'DYNAMIC';
			$data['callback_url'] = 'https://production.topscore.id';
		}
		*/
		if ($bank_code == 'QRIS') {
			$end_point = $this->server_domain . '/qr_codes';

			$data['external_id'] = $external_id;
			$data['amount'] = (int) $amount;
			$data['type'] = 'DYNAMIC';
			//$data['callback_url'] = 'https://production.topscore.id';
			$data['callback_url'] = 'https://production.topscore.id/api/callback_xd';
		} else
			if (in_array($bank_code, array("BCA", "BNI", "BRI", "MANDIRI", "PERMATA", "BSI"))) {
				$end_point = $this->server_domain . '/callback_virtual_accounts';

				$data['external_id'] = $external_id;
				$data['name'] = $nama_user;
				//$data['name'] = 'imam';
				$data['bank_code'] = $bank_code;
				$data['expected_amount'] = (int) $amount;
				//$data['expiration_date'] = date('Y-m-d', strtotime(date("Y-m-d"). ' + 1 days'))." 23:59:59"; 
				$data['expiration_date'] = $tgl_indo_expired;
				$data['is_closed'] = true;
			}
			// jika Payment mengunakan QRIS
			else
				if (in_array($bank_code, array("ID_OVO", "ID_SHOPEEPAY", "ID_DANA", "ID_LINKAJA"))) {
					$end_point = $this->server_domain . '/ewallets/charges';
					$phone = str_replace('+', '', $phone);
					$check = substr($phone, 0, 1);
					if ($check == '0' || $check == 0) {
						$phone = preg_replace('/^0/', '62', $phone);
					}
					/*
					$data['external_id'] = $external_id;
					$data['amount'] = (int)$amount;
					$data['type'] = 'DYNAMIC';
					$data['callback_url'] = 'https://production.topscore.id/api/callback_xd_ewallet';
					*/
					$data['reference_id'] = $external_id;
					$data['currency'] = "IDR";
					$data['amount'] = (int) $amount;
					$data['checkout_method'] = "ONE_TIME_PAYMENT";
					$data['channel_code'] = $bank_code;
					$data['channel_properties'] = array(
						"success_redirect_url" => 'https://production.topscore.id/api/callback_xd_ewallet',
						"mobile_number" => $phone
					);
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

		/*if($request->bank_code =='QRIS'){
			$nomor_va = $json_parsing->qr_string;
			$bank_code = 'QRIS';
		} else {
			$nomor_va = $json_parsing->account_number;
			$bank_code = $json_parsing->bank_code;
		}*/
		if ($request->bank_code == 'QRIS') {
			$nomor_va = $json_parsing->qr_string;
			$bank_code = 'QRIS';
		} else
			if (in_array($request->bank_code, array("ID_OVO", "ID_SHOPEEPAY", "ID_DANA", "ID_LINKAJA"))) {
				$nomor_va = $external_id;
				$bank_code = $bank_code;
			} else {
				$nomor_va = $json_parsing->account_number;
				$bank_code = $json_parsing->bank_code;
			}


		// OUTPUT JSON SAVE DATA...
		/*
		if($request->bank_code =='QRIS'){
			$cek_channels = DB::table('pg_metod_payget')->select('id', 'bank_code', 'img')
							->where('status', '=', '1')
							->where('bank_code', '=', 'QRIS')->first();
			if(!empty($cek_channels)){
				$img_channel = url('public/pg').'/'.str_replace("svg","png",$cek_channels->img);
			} else { $img_channel = ''; }
			$data = [
				'id' => $json_parsing->id,
				'external_id' => $json_parsing->external_id,
				'amount' => $json_parsing->amount,
				'qr_string' => $json_parsing->qr_string,
				'callback_url' => $json_parsing->callback_url,
				'description' => $json_parsing->description,
				'type' => $json_parsing->type,
				'status' => $json_parsing->status,
				'created' => $json_parsing->created,
				'updated' => $json_parsing->updated,
				'metadata' => $json_parsing->metadata,
				'img' => $img_channel,
				'pt' => 'PT. Mencetak Banyak Gol (Topscore)',
				'payment_instruction' => $results
			];
			// Save to PG Master ******************************************************* //
			$data_pg = [
				'id_lookup' => $external_id,
				'type_data' => 'pos',
				'external_id' => $external_id,
				'account_number' => $nomor_va,
				'bank_code' => $bank_code,
				'name' => $nama_user,
				'expected_amount' => $json_parsing->amount,
				'status' => $json_parsing->status,
				'created_at' => date("Y-m-d H:i:s"),
			];
			$get_pgmaster = DB::table("payment_xendit")->insertGetId(array_merge($data_pg));
		} 
		// Jika Payment menggunakan VA
		else {
			$cek_channels = DB::table('pg_metod_payget')->select('id', 'bank_code', 'img')
							->where('status', '=', '1')
							->where('bank_code', '=', $json_parsing->bank_code)->first();
			if(!empty($cek_channels)){
				$img_channel = url('public/pg').'/'.str_replace("svg","png",$cek_channels->img);
			} else { $img_channel = ''; }
			$data = [
				'id' => $json_parsing->id,
				'owner_id' => $json_parsing->owner_id,
				'external_id' => $json_parsing->external_id,
				'account_number' => $json_parsing->account_number,
				'bank_code' => $json_parsing->bank_code,
				'merchant_code' => $json_parsing->merchant_code,
				'name' => $json_parsing->name,
				'is_closed' => $json_parsing->is_closed,
				'expected_amount' => $json_parsing->expected_amount,
				'expiration_date' => $json_parsing->expiration_date,
				'is_single_use' => $json_parsing->is_single_use,
				'status' => $json_parsing->status,
				'currency' => $json_parsing->currency,
				'country' => $json_parsing->country,
				'img' => $img_channel,
				'pt' => 'PT. Mencetak Banyak Gol (Topscore)',
				'payment_instruction' => $results
			];
			// Save to PG Master ******************************************************* //
			$data_pg = [
				'id_lookup' => $external_id,
				'type_data' => 'pos',
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
		*/

		if ($request->bank_code == 'QRIS') {
			$cek_channels = DB::table('pg_metod_payget')->select('id', 'bank_code', 'img')
				->where('status', '=', '1')
				->where('bank_code', '=', 'QRIS')->first();
			if (!empty($cek_channels)) {
				$img_channel = url('public/pg') . '/' . str_replace("svg", "png", $cek_channels->img);
			} else {
				$img_channel = '';
			}

			// expired date
			$tgl_expired_payment = date('Y-m-d H:i:s', strtotime(date("Y-m-d H:i:s") . ' +3 hours'));

			$data = [
				'id' => $json_parsing->id,
				'owner_id' => "",
				'external_id' => $json_parsing->external_id,
				//'amount' => $json_parsing->amount,
				//'qr_string' => $json_parsing->qr_string,
				//'callback_url' => $json_parsing->callback_url,
				//'description' => $json_parsing->description,
				//'type' => $json_parsing->type,
				//'status' => $json_parsing->status,
				//'created' => $json_parsing->created,
				//'updated' => $json_parsing->updated,
				//'metadata' => $json_parsing->metadata,
				'qr_string' => $json_parsing->qr_string,
				'callback_url' => $json_parsing->callback_url,
				'description' => $json_parsing->description,
				'type' => $json_parsing->type,
				'account_number' => "",
				'bank_code' => $bank_code,
				'merchant_code' => "",
				'name' => $nama_user,
				'is_closed' => true,
				'expected_amount' => $json_parsing->amount,
				//'expiration_date' => $tgl_indo_expired,
				'expiration_date' => $tgl_expired_payment,
				'is_single_use' => false,
				'status' => $json_parsing->status,
				'currency' => "IDR",
				'country' => "",
				'img' => $img_channel,
				'actions' => null,
				'pt' => 'PT. Mencetak Banyak Gol (Topscore)',
				'payment_instruction' => $results
			];
			// Save to PG Master ******************************************************* //
			$data_pg = [
				'id_lookup' => $external_id,
				'type_data' => 'pos',
				'external_id' => $external_id,
				'account_number' => $nomor_va,
				'bank_code' => $bank_code,
				'name' => $nama_user,
				'expected_amount' => $json_parsing->amount,
				'status' => $json_parsing->status,
				'created_at' => date("Y-m-d H:i:s"),
			];
			$get_pgmaster = DB::table("payment_xendit")->insertGetId(array_merge($data_pg));
		} else if (in_array($request->bank_code, array("ID_OVO", "ID_SHOPEEPAY", "ID_DANA", "ID_LINKAJA"))) {
			// kusus E-Wallet ************* CEK
			$cek_channels = DB::table('pg_metod_payget')->select('id', 'bank_code', 'img')
				->where('status', '=', '1')
				->where('bank_code', '=', $request->bank_code)->first();
			if (!empty($cek_channels)) {
				$img_channel = url('public/pg') . '/' . str_replace("svg", "png", $cek_channels->img);
			} else {
				$img_channel = '';
			}

			// expired date
			if ($bank_code == "ID_OVO") {
				$tgl_expired_payment = date('Y-m-d H:i:s', strtotime(date("Y-m-d H:i:s") . ' +1 minutes'));
			} else {
				$tgl_expired_payment = date('Y-m-d H:i:s', strtotime(date("Y-m-d H:i:s") . ' +3 hours'));
			}

			$data = [
				'id' => $json_parsing->id,
				'owner_id' => "",
				'external_id' => $json_parsing->reference_id,
				'qr_string' => "",
				'callback_url' => $json_parsing->callback_url,
				'description' => "",
				'type' => "",
				'account_number' => "",
				'bank_code' => $bank_code,
				'merchant_code' => "",
				'name' => $nama_user,
				'is_closed' => true,
				'expected_amount' => $json_parsing->charge_amount,
				//'expiration_date' => $tgl_indo_expired,
				'expiration_date' => $tgl_expired_payment,
				'is_single_use' => false,
				'status' => $json_parsing->status,
				'currency' => "IDR",
				'country' => "",
				'img' => $img_channel,
				'actions' => $json_parsing->actions,
				'pt' => 'PT. Mencetak Banyak Gol (Topscore)',
				'payment_instruction' => $results
			];
			// Save to PG Master ******************************************************* //
			$data_pg = [
				'id_lookup' => $external_id,
				'type_data' => 'pos',
				'external_id' => $external_id,
				'account_number' => $external_id,
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
			$cek_channels = DB::table('pg_metod_payget')->select('id', 'bank_code', 'img')
				->where('status', '=', '1')
				->where('bank_code', '=', $json_parsing->bank_code)->first();
			if (!empty($cek_channels)) {
				$img_channel = url('public/pg') . '/' . str_replace("svg", "png", $cek_channels->img);
			} else {
				$img_channel = '';
			}
			$data = [
				'id' => $json_parsing->id,
				'owner_id' => $json_parsing->owner_id,
				'external_id' => $json_parsing->external_id,
				'qr_string' => "",
				'callback_url' => "",
				'description' => "",
				'type' => "",
				'account_number' => $json_parsing->account_number,
				'bank_code' => $json_parsing->bank_code,
				'merchant_code' => $json_parsing->merchant_code,
				'name' => $json_parsing->name,
				'is_closed' => $json_parsing->is_closed,
				'expected_amount' => $json_parsing->expected_amount,
				'expiration_date' => $json_parsing->expiration_date,
				'is_single_use' => $json_parsing->is_single_use,
				'status' => $json_parsing->status,
				'currency' => $json_parsing->currency,
				'country' => $json_parsing->country,
				'img' => $img_channel,
				'actions' => null,
				'pt' => 'PT. Mencetak Banyak Gol (Topscore)',
				'payment_instruction' => $results
			];
			// Save to PG Master ******************************************************* //
			$data_pg = [
				'id_lookup' => $external_id,
				'type_data' => 'pos',
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
		// Proses PAYMENT ***********************************************************************


		if ($no_simpan > 0) {
			//$update_pos = DB::table('pos_transactions')->where('external_id', '=', $external_id)->update(['va_number' => $nomor_va]);

			//  cek data jika sudah berhasil melakuan transaksi, pastiin no inv dan artikel berhasil di simpan
			$cek_posdata = DB::table("pos_transactions")->select("id")->where('external_id', '=', $external_id)->get();
			$pos_detail_save = 0;
			foreach ($cek_posdata as $rpos) {
				$cek_posdetail = DB::table("pos_transaction_details")
					->select("id")
					->where('pt_id', '=', $rpos->id)
					->get();
				if ($cek_posdetail) {
					$pos_detail_save++;
				}
			}

			if ($pos_detail_save == 0) {
				$r['status'] = '402'; // error data, artikel tidak tersimpan
				return response()->json($r);
			}

			// jika detail artikel berhasil di simpan
			if ($pos_detail_save > 0 && $amount > 0) {
				$this->waSend($arr_customers->cust_phone, $arr_customers->cust_name, $external_id, $amount);
			}

			$update_pos = DB::table('pos_transactions')->where('external_id', '=', $external_id)->update(['va_number' => $nomor_va, "json_pg" => $response]);

			// save log checkout POS
			$data_log = [
				'cust_id' => $request->cust_id,
				'ta_page_access' => "Checkout",
				'ta_description' => "Mengakses laman checkout dengan ID transaksi " . $external_id,
				//'ta_lookup_id' => $pst_id,
				'created_at' => date("Y-m-d H:i:s"),
			];
			$save_log = DB::table("traffic_apps")->insertGetId(array_merge($data_log));

			$r['status'] = '200';
			//$r['pt_id'] = $insert_get_id;
			$r['external_id'] = $external_id;
			$r['payment_gateway'] = $pg_name;
			$r['banner'] = url('public/upload/payment_product.png');
			$r['invoice_details'] = $invoice_details;
			$r['data_payment'] = $data;
		} else {
			$r['status'] = '400';
		}
		//return json_encode($r);
		return response()->json($r);
	}

	// Proses save orderan details...
	private function saveTransactionDetail($pt_id, $u_id, $st_id, $item_details)
	{
		// BIN exeption / stok yang tidak valid
		$exception = DB::table('exception_locations')->select('pl_code')
			->leftJoin('product_locations', 'product_locations.id', '=', 'exception_locations.pl_id')->get();
		$exc_location = array();
		foreach ($exception as $exc) {
			$exc_location[] = $exc->pl_code;
		}

		$item_details = json_decode($item_details);
		$array_item = array();
		$data_save = 0;
		//$item_savedetails=array();
		$item_savedetails_new = array();
		foreach ($item_details as $key => $lookup) {
			$store_id = $lookup->store_id;
			$product_name = isset($lookup->product_name) ? $lookup->product_name : '';
			$color = isset($lookup->color) ? $lookup->color : '';
			$size = isset($lookup->size) ? $lookup->size : '';
			$item_qty = isset($lookup->qty) ? $lookup->qty : (isset($lookup->quantity) ? $lookup->quantity : 1);
			$price = isset($lookup->price) ? $lookup->price : 0;

			if ($store_id == $st_id) {
				$item_savedetails = array();

				$arr_pst = DB::table('product_stocks')->select('product_stocks.id')
					->leftJoin('product_location_setups', 'product_location_setups.pst_id', '=', 'product_stocks.id')
					->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
					->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
					->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
					->where('products.p_name', '=', $product_name)
					->where('products.p_color', '=', $color)
					->where('sizes.sz_name', '=', $size)
					->where('products.p_delete', '=', '0')
					->where('product_location_setups.pls_qty', '>', '0')
					->whereNotIn('pl_code', $exc_location)
					->groupBy("product_stocks.id")
					->first();

				if (!$arr_pst && isset($lookup->pls_id)) {
					$pls_row = DB::table('product_location_setups')->select('pst_id as id')->where('id', $lookup->pls_id)->first();
					if ($pls_row) {
						$arr_pst = $pls_row;
					}
				}

				if (!$arr_pst && isset($lookup->product_id)) {
					$pst_row = DB::table('product_stocks')->select('id')->where('p_id', $lookup->product_id)->first();
					if ($pst_row) {
						$arr_pst = $pst_row;
					}
				}

				// jika ditemukan item pada database...
				if ($arr_pst) {
					// cek ketersediaan stok
					$arr_stok = DB::table('product_location_setups')
						->select('product_location_setups.id', 'product_location_setups.pls_qty', 'product_location_setups.pl_id', 'product_location_setups.pst_id')
						->leftJoin('product_stocks', 'product_stocks.id', '=', 'product_location_setups.pst_id')
						->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
						->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
						->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
						->where('product_location_setups.pst_id', '=', $arr_pst->id)
						->where('product_location_setups.pls_qty', '>', '0') // cek stok tersedia
						->where('product_locations.st_id', '=', $store_id)
						->where('products.p_delete', '=', '0')
						->whereNotIn('pl_code', $exc_location)
						->orderBy('product_location_setups.pls_qty', 'DESC')
						->get()->first();

					if (!$arr_stok && isset($lookup->pls_id)) {
						$arr_stok = DB::table('product_location_setups')->select('id', 'pls_qty', 'pl_id', 'pst_id')->where('id', $lookup->pls_id)->first();
					}
					if (!$arr_stok && isset($lookup->product_id)) {
						$arr_stok = DB::table('product_location_setups')->select('product_location_setups.id', 'product_location_setups.pls_qty', 'product_location_setups.pl_id', 'product_location_setups.pst_id')->leftJoin('product_stocks', 'product_stocks.id', '=', 'product_location_setups.pst_id')->where('product_stocks.p_id', '=', $lookup->product_id)->first();
					}
					//$pl_id=NULL;
					//$pst_id = NULL;
					//$stok_tersedia = 0;
					/*
					if($arr_stok){
						foreach($arr_stok as $rsub){
							$stok_tersedia += $rsub->pls_qty;
							$pl_id = $rsub->pl_id;
							$pst_id = $rsub->pst_id;
						}
					}
					*/
					// jika stok tersedia...
					if ($arr_stok) {
						$pl_id = $arr_stok->pl_id;
						$pst_id = $arr_stok->pst_id;
					}

					$pos_total_price = $price * $item_qty;
					$create = DB::table('pos_transaction_details')->insertGetId([
						'pt_id' => $pt_id,
						'pst_id' => $pst_id,
						'pl_id' => $pl_id,
						'pos_td_qty' => $item_qty,
						'pos_td_sell_price' => $price,
						'pos_td_discount' => '0',
						'pos_td_discount_price' => $price,
						//'pos_td_marketplace_price' => $price,
						'pos_td_marketplace_price' => $pos_total_price,
						'pos_td_nameset_price' => NULL,
						'pos_td_nameset' => '0',
						'pos_td_description' => NULL,
						'pos_td_total_price' => $pos_total_price,
						'created_at' => date('Y-m-d H:i:s'),
					]);
					if (!empty($create)) {
						// update total terjual...
						$sold = DB::table('products')->select('products.id as p_id', 'sold')
							->leftJoin('product_stocks', 'product_stocks.p_id', '=', 'products.id')
							->where('product_stocks.id', '=', $pst_id)
							->get()->first();
						if (!empty($sold)) {
							$update_sold = DB::table('products')->where('id', '=', $sold->p_id)
								->update([
									'sold' => ($sold->sold + $item_qty)
								]);
						}
						$pls_id = DB::table('product_location_setups')
							->select('id')->where('pst_id', $pst_id)
							->where('pl_id', $pl_id)
							->get()
							->first()
							->id;
						// save to log data transaksi stok
						$activity_stock = DB::table('product_location_setup_transactions')->insert([
							'pls_id' => $pls_id,
							'u_id' => $u_id,
							'pt_id' => $pt_id,
							'plst_qty' => $item_qty,
							'plst_type' => 'OUT',
							'plst_status' => 'WAITING ONLINE',
							'created_at' => date('Y-m-d H:i:s')
						]);
						if ($activity_stock) {
							$product_setup_location_qty = DB::table('product_location_setups')->select('pls_qty')->where('id', $pls_id)->get()->first()->pls_qty;
							$product_setup_location = DB::table('product_location_setups')->where('id', $pls_id)->update([
								'pls_qty' => ($product_setup_location_qty - $item_qty)
							]);
						}

						$item_savedetails = [
							'detail_id' => $create,
							'pos_id' => $pt_id,
							'product_name' => $product_name,
							'product_size' => $size,
							'product_color' => $color,
							'detail_qty' => $item_qty,
							'detail_price' => $price,
							'detail_total' => $pos_total_price,
						];
						$data_save++;

						// update stok di product_stocks //
						$stock_all = DB::table("product_location_setups")->where("pls_qty", ">", "0")->where("pst_id", "=", $pst_id)->sum('pls_qty');
						if ($stock_all > 0) {
							$stock_all = $stock_all;
						} else {
							$stock_all = 0;
						}
						$upstock_pst = DB::table("product_stocks")->where('id', $pst_id)->update(['ps_qty' => $stock_all]);
						// end update product_stocks //

					}
				}
				$item_savedetails_new[] = $item_savedetails;

			} // jika store sama dengan store di invoice
		}

		//$r['item_savedetails'] = '200';
		return $item_savedetails_new;
	}

	// save item free dan GWP
	private function saveTransactionBenefit($pt_id, $u_id, $st_id, $item_details)
	{
		// BIN exeption / stok yang tidak valid
		$exception = DB::table('exception_locations')->select('pl_code')
			->leftJoin('product_locations', 'product_locations.id', '=', 'exception_locations.pl_id')->get();
		$exc_location = array();
		foreach ($exception as $exc) {
			$exc_location[] = $exc->pl_code;
		}

		$item_details = json_decode($item_details);
		$array_item = array();
		$data_save = 0;
		//$item_savedetails=array();
		$item_savedetails_new = array();
		foreach ($item_details as $key => $lookup) {
			$store_id = $lookup->store_id;
			$product_name = $lookup->product_name;
			$color = $lookup->color;
			$size = $lookup->size;
			$item_qty = $lookup->qty;
			$price = $lookup->price;

			if ($store_id == $st_id) {
				$item_savedetails = array();

				$arr_pst = DB::table('product_stocks')->select('product_stocks.id')
					->leftJoin('product_location_setups', 'product_location_setups.pst_id', '=', 'product_stocks.id')
					->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
					->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
					->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
					->where('products.p_name', '=', $product_name)
					->where('products.p_color', '=', $color)
					->where('sizes.sz_name', '=', $size)
					->where('products.p_delete', '=', '0')
					->where('product_location_setups.pls_qty', '>', '0')
					->whereNotIn('pl_code', $exc_location)
					->groupBy("product_stocks.id")
					->first();
				// jika ditemukan item pada database...
				//$item_savedetails=array();
				if ($arr_pst) {
					// cek ketersediaan stok
					$arr_stok = DB::table('product_location_setups')
						->select('product_location_setups.id', 'product_location_setups.pls_qty', 'product_location_setups.pl_id', 'product_location_setups.pst_id')
						->leftJoin('product_stocks', 'product_stocks.id', '=', 'product_location_setups.pst_id')
						->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
						->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
						->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
						->where('product_location_setups.pst_id', '=', $arr_pst->id)
						->where('product_location_setups.pls_qty', '>', '0') // cek stok tersedia
						->where('product_locations.st_id', '=', $store_id)
						->where('products.p_delete', '=', '0')
						->whereNotIn('pl_code', $exc_location)
						->orderBy('product_location_setups.pls_qty', 'DESC')
						->get()->first();

					// jika stok tersedia...
					if ($arr_stok) {
						$pl_id = $arr_stok->pl_id;
						$pst_id = $arr_stok->pst_id;
					}

					$pos_total_price = $price * $item_qty;
					$create = DB::table('pos_transaction_details')->insertGetId([
						'pt_id' => $pt_id,
						'pst_id' => $pst_id,
						'pl_id' => $pl_id,
						'pos_td_qty' => $item_qty,
						'pos_td_sell_price' => $price,
						'pos_td_discount' => '0',
						'pos_td_discount_price' => $price,
						//'pos_td_marketplace_price' => $price,
						'pos_td_marketplace_price' => $pos_total_price,
						'pos_td_nameset_price' => NULL,
						'pos_td_nameset' => '0',
						'pos_td_description' => NULL,
						'pos_td_total_price' => $pos_total_price,
						'created_at' => date('Y-m-d H:i:s'),
					]);
					if (!empty($create)) {
						// update total terjual...
						$sold = DB::table('products')->select('products.id as p_id', 'sold')
							->leftJoin('product_stocks', 'product_stocks.p_id', '=', 'products.id')
							->where('product_stocks.id', '=', $pst_id)
							->get()->first();
						if (!empty($sold)) {
							$update_sold = DB::table('products')->where('id', '=', $sold->p_id)
								->update([
									'sold' => ($sold->sold + $item_qty)
								]);
						}
						$pls_id = DB::table('product_location_setups')
							->select('id')->where('pst_id', $pst_id)
							->where('pl_id', $pl_id)
							->get()
							->first()
							->id;
						// save to log data transaksi stok
						$activity_stock = DB::table('product_location_setup_transactions')->insert([
							'pls_id' => $pls_id,
							'u_id' => $u_id,
							'pt_id' => $pt_id,
							'plst_qty' => $item_qty,
							'plst_type' => 'OUT',
							'plst_status' => 'WAITING ONLINE',
							'created_at' => date('Y-m-d H:i:s')
						]);
						if ($activity_stock) {
							$product_setup_location_qty = DB::table('product_location_setups')->select('pls_qty')->where('id', $pls_id)->get()->first()->pls_qty;
							$product_setup_location = DB::table('product_location_setups')->where('id', $pls_id)->update([
								'pls_qty' => ($product_setup_location_qty - $item_qty)
							]);
						}

						$item_savedetails = [
							'detail_id' => $create,
							'pos_id' => $pt_id,
							'product_name' => $product_name,
							'product_size' => $size,
							'product_color' => $color,
							'detail_qty' => $item_qty,
							'detail_price' => $price,
							'detail_total' => $pos_total_price,
						];
						$data_save++;
					}
				}
				$item_savedetails_new[] = $item_savedetails;

			} // jika store sama dengan store di invoice
		}

		//$r['item_savedetails'] = '200';
		return $item_savedetails_new;
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

	// List pesana saya orderan event
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
		$payment_status = $request->payment_status;
		$pos_id = $request->pos_id;
		$external_id = $request->external_id;
		$pos_status = $request->pos_status; // IN PROGRESS

		$query_data = DB::table("pos_transactions")
			->selectRaw('ts_pos_transactions.id as pid, pos_invoice, st_id, st_id_ref, st_name, pos_real_price, pos_admin_cost, pos_another_cost, pos_member_cost, pos_status, pos_note, ts_pos_transactions.created_at, external_id, va_number, payment_status, pos_status, no_resi_expedisi, expedisi, pos_discount, pos_shipping, pos_cust_address, pt_callback_date, json_pg, pos_reseller, pos_fees, is_apps, pt_id_ref, pos_coin, voucher_apps_id')
			->leftJoin('stores', 'stores.id', '=', 'pos_transactions.st_id_ref')
			->where('cust_id', '=', $cust_id)
			//->where('is_apps', '=', 1)
			//->whereNotNull('json_pg')
			->where(function ($w) use ($payment_status, $pos_id, $external_id, $pos_status) {
				if (!empty($payment_status)) {
					$w->where('payment_status', '=', $payment_status);
				}
				if (!empty($pos_id)) {
					$w->where('pos_transactions.id', '=', $pos_id);
				}
				if (!empty($external_id)) {
					$w->where('pos_transactions.external_id', '=', $external_id);
				}
				if (!empty($pos_status)) {
					if ($pos_status == "diproses") {
						$w->whereIn('pos_transactions.pos_status', ['PAID', 'WAITING FOR CONFIRMATION', 'IN PROGRESS', 'SHIPPING NUMBER', 'NAMESET']);
					} else if ($pos_status == "dikirim") {
						$w->whereIn('pos_transactions.pos_status', ['IN DELIVERY']);
					} else if ($pos_status == "selesai") {
						$w->whereIn('pos_transactions.pos_status', ['DONE']);
					} else if ($pos_status == "belum bayar" || $pos_status == "unpaid") {
						$w->whereIn('pos_transactions.pos_status', ['UNPAID']);
					} else if ($pos_status == "batal" || $pos_status == "cancel") {
						$w->whereIn('pos_transactions.pos_status', ['CANCEL']);
					} else {
						$w->where('pos_transactions.pos_status', '=', $pos_status);
					}
				}
			})
			->orderBy('pos_transactions.created_at', 'DESC')
			->get();

		$no = 0;
		$pos_master = array();
		if (!empty($query_data)) {
			$pids = [];
			$external_ids = [];
			$st_ids_to_fetch = [];
			foreach ($query_data as $row) {
				$pids[] = $row->pid;
				if ($row->external_id) {
					$external_ids[] = $row->external_id;
				}
				if (!$row->st_name && $row->st_id) {
					$st_ids_to_fetch[] = $row->st_id;
				}
			}

			// Batch 1: Transaction Details
			$details_by_pt_id = [];
			if (!empty($pids)) {
				$all_details_raw = DB::table("pos_transaction_details")
					->selectRaw('ts_pos_transaction_details.id as pid, sz_name, p_name, p_color, pos_td_qty, pos_td_sell_price, pos_td_total_price, p_main_image, p_image, pst_id, ts_pos_transaction_details.pt_id')
					->leftJoin('product_stocks', 'product_stocks.id', '=', 'pos_transaction_details.pst_id')
					->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
					->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
					->whereIn('pos_transaction_details.pt_id', $pids)
					->orderBy('pos_transaction_details.created_at', 'DESC')
					->get();

				foreach ($all_details_raw as $raw) {
					$image = \App\Helpers\ProductImageHelper::parseImages($raw->p_main_image, $raw->p_image);
					$image_url = !empty($image) ? "https://topsystem.id/api/product/600/" : "";
					$details_by_pt_id[$raw->pt_id][] = [
						'detail_id' => $raw->pid,
						'pos_id' => $raw->pt_id,
						'product_id' => $raw->pst_id,
						'product_name' => $raw->p_name,
						'product_size' => $raw->sz_name,
						'product_color' => $raw->p_color,
						'detail_qty' => (int) $raw->pos_td_qty,
						'detail_price' => (int) $raw->pos_td_sell_price,
						'detail_total' => (int) $raw->pos_td_total_price,
						'image_url' => $image_url,
						'image' => $image,
					];
				}
			}

			// Batch 2: Payment Xendit
			$payments_by_ext_id = [];
			$bank_codes = [];
			if (!empty($external_ids)) {
				$all_payments = DB::table('payment_xendit')->select('*')
					->whereIn('external_id', array_unique($external_ids))
					->get();
				foreach ($all_payments as $pg) {
					// Use FIRST logic
					if (!isset($payments_by_ext_id[$pg->external_id])) {
						$payments_by_ext_id[$pg->external_id] = $pg;
					}
					if ($pg->bank_code && !in_array($pg->bank_code, $bank_codes)) {
						$bank_codes[] = $pg->bank_code;
					}
				}
			}

			// Batch 3: Payment Instructions & Channels
			$instructions_by_bank = [];
			$channels_by_bank = [];
			if (!empty($bank_codes)) {
				$all_instructions = DB::table('pg_instruction_payget')->selectRaw('ts_pg_instruction_payget.id as pid, metod_instruction_payget, instruction_payget, ts_pg_metod_payget.bank_code')
					->leftJoin('pg_metod_instruction_payget', 'pg_metod_instruction_payget.id', '=', 'pg_instruction_payget.id_metod_instruction_payget')
					->leftJoin('pg_metod_payget', 'pg_metod_payget.id', '=', 'pg_instruction_payget.id_metod_payget')
					->where('pg_instruction_payget.status', '=', '1')
					->where('pg_metod_instruction_payget.status', '=', '1')
					->where('pg_metod_payget.status', '=', '1')
					->whereIn('pg_metod_payget.bank_code', $bank_codes)
					->orderBy('pg_metod_instruction_payget.sort')
					->get();
				foreach ($all_instructions as $rinstuct) {
					$instructions_by_bank[$rinstuct->bank_code][] = [
						'metode' => $rinstuct->metod_instruction_payget,
						'instruction' => $rinstuct->instruction_payget,
					];
				}

				$all_channels = DB::table('pg_metod_payget')->select('id', 'bank_code', 'img')
					->where('status', '=', '1')
					->whereIn('bank_code', $bank_codes)
					->get();
				foreach ($all_channels as $ch) {
					// Use FIRST logic
					if (!isset($channels_by_bank[$ch->bank_code])) {
						$channels_by_bank[$ch->bank_code] = $ch;
					}
				}
			}

			// Batch 4: Ratting & Customers & Stores
			$arr_customers = DB::table("customers")->where("id", "=", $cust_id)->first();
			
			$ratting_by_pid = [];
			if (!empty($pids)) {
				$all_ratting = DB::table("ratting_apps")
					->where("cust_id", "=", $cust_id)
					->whereIn("lookup_id", array_unique($pids))
					->where("ra_type", "=", "pos")
					->get();
				foreach ($all_ratting as $rt) {
					if (!isset($ratting_by_pid[$rt->lookup_id])) {
						$ratting_by_pid[$rt->lookup_id] = $rt;
					}
				}
			}

			$stores_by_id = [];
			if (!empty($st_ids_to_fetch)) {
				$all_stores = DB::table("stores")->whereIn("id", array_unique($st_ids_to_fetch))->get();
				foreach ($all_stores as $st) {
					if (!isset($stores_by_id[$st->id])) {
						$stores_by_id[$st->id] = $st;
					}
				}
			}

			foreach ($query_data as $row) {
				// Arahan Mas Bowo: jika is_apps=1 proses json_pg, jika is_apps=0 abaikan
				$json_pg = ($row->is_apps == 1 && !empty($row->json_pg)) ? json_decode($row->json_pg) : null;

				$no++;
				if ($row->payment_status == "0") {
					$payment_status = 'Unpaid';
				} else if ($row->payment_status == "1") {
					$payment_status = 'Paid';
				} else if ($row->payment_status == "10") {
					$payment_status = 'Expired';
				} else {
					$payment_status = $row->payment_status ?? 'Unpaid';
				}
				// cek details item
				$detail_item_data = isset($details_by_pt_id[$row->pid]) ? $details_by_pt_id[$row->pid] : [];
				$detail_item_save = !empty($detail_item_data) ? $detail_item_data : [];

				// cek payment 
				$arr_pg = isset($payments_by_ext_id[$row->external_id]) ? $payments_by_ext_id[$row->external_id] : null;
				$data_payment = null;
				$payment_instruction = array();
				if (!empty($arr_pg)) {
					if (isset($instructions_by_bank[$arr_pg->bank_code])) {
						$payment_instruction = $instructions_by_bank[$arr_pg->bank_code];
					}

					if (!empty($arr_pg->expiration_date)) {
						$expiration_date = $arr_pg->expiration_date;
					} else {
						$expiration_date = date('Y-m-d H:i:s', strtotime($row->created_at . ' +3 hours'));
					}

					// cek channel bank pembayaran
					$cek_channels = isset($channels_by_bank[$arr_pg->bank_code]) ? $channels_by_bank[$arr_pg->bank_code] : null;
					if (!empty($cek_channels)) {
						$img_channel = url('public/pg') . '/' . str_replace("svg", "png", $cek_channels->img);
					} else {
						$img_channel = '';
					}

					$merchant_code = !empty($arr_pg->merchant_code) ? $arr_pg->merchant_code : '';
					$is_closed = !empty($arr_pg->is_closed) ? $arr_pg->is_closed : '';
					$currency = !empty($arr_pg->currency) ? $arr_pg->currency : 'IDR';

					// kusus COD
					if (in_array($arr_pg->bank_code, array("COD"))) {
						$data_payment['channel_type'] = 3;
						$data_payment['external_id'] = $row->external_id;
						$data_payment['qr_string'] = '';
						$data_payment['callback_url'] = '';
						$data_payment['description'] = 'Cash On Delivery (COD)';
						$data_payment['type'] = "";
						$data_payment['account_number'] = "";
						$data_payment['bank_code'] = "COD";
						$data_payment['merchant_code'] = $merchant_code;
						$data_payment['name'] = $arr_pg->name;
						$data_payment['is_closed'] = true;
						$data_payment['expected_amount'] = (int) $arr_pg->expected_amount;
						$data_payment['expiration_date'] = "";
						$data_payment['is_single_use'] = false;
						$data_payment['status'] = $arr_pg->status;
						$data_payment['currency'] = "IDR";
						$data_payment['country'] = "ID";
						$data_payment['img'] = $img_channel;
						$data_payment['actions'] = null;
						$data_payment['pt'] = 'PT. Mencetak Banyak Gol (Topscore)';
						$data_payment['payment_instruction'] = $payment_instruction;
					} else if (in_array($arr_pg->bank_code, array("ID_OVO", "ID_SHOPEEPAY", "ID_DANA", "ID_LINKAJA"))) {
						$data_payment['channel_type'] = 0;
						$data_payment['external_id'] = $json_pg ? $json_pg->reference_id : $row->external_id;
						$data_payment['qr_string'] = "";
						$data_payment['callback_url'] = $json_pg ? $json_pg->callback_url : "";
						$data_payment['description'] = "";
						$data_payment['type'] = "";
						$data_payment['account_number'] = $arr_pg->account_number;
						$data_payment['bank_code'] = $arr_pg->bank_code;
						$data_payment['merchant_code'] = $merchant_code;
						$data_payment['name'] = $arr_pg->name;
						$data_payment['is_closed'] = true;
						$data_payment['expected_amount'] = (int) $arr_pg->expected_amount;
						$data_payment['expiration_date'] = date("Y-m-d H:i:s", strtotime($expiration_date));
						$data_payment['is_single_use'] = false;
						$data_payment['status'] = $arr_pg->status;
						$data_payment['currency'] = "IDR";
						$data_payment['country'] = "ID";
						$data_payment['img'] = $img_channel;
						$data_payment['actions'] = $json_pg ? $json_pg->actions : null;
						$data_payment['pt'] = 'PT. Mencetak Banyak Gol (Topscore)';
						$data_payment['payment_instruction'] = $payment_instruction;
					} else if (in_array($arr_pg->bank_code, array("QRIS"))) {
						$data_payment['channel_type'] = 2;
						$data_payment['external_id'] = $json_pg ? $json_pg->external_id : $row->external_id;
						$data_payment['qr_string'] = $json_pg ? $json_pg->qr_string : "";
						$data_payment['callback_url'] = $json_pg ? $json_pg->callback_url : "";
						$data_payment['description'] = $json_pg ? $json_pg->description : "";
						$data_payment['type'] = $json_pg ? $json_pg->type : "";
						$data_payment['account_number'] = $json_pg ? $json_pg->external_id : "";
						$data_payment['bank_code'] = $arr_pg->bank_code;
						$data_payment['merchant_code'] = $merchant_code;
						$data_payment['name'] = $arr_pg->name;
						$data_payment['is_closed'] = true;
						$data_payment['expected_amount'] = (int) $arr_pg->expected_amount;
						$data_payment['expiration_date'] = date("Y-m-d H:i:s", strtotime($expiration_date));
						$data_payment['is_single_use'] = false;
						$data_payment['status'] = $arr_pg->status;
						$data_payment['currency'] = "IDR";
						$data_payment['country'] = "ID";
						$data_payment['img'] = $img_channel;
						$data_payment['actions'] = null;
						$data_payment['pt'] = 'PT. Mencetak Banyak Gol (Topscore)';
						$data_payment['payment_instruction'] = $payment_instruction;
					} else {
						if ($json_pg) {
							$expiration_date = isset($json_pg->expiration_date) ? $json_pg->expiration_date : $row->created_at;
							$is_single_use = isset($json_pg->is_single_use) ? $json_pg->is_single_use : false;
						} else {
							$expiration_date = $row->created_at;
							$is_single_use = false;
						}

						$data_payment['channel_type'] = 1;
						$data_payment['external_id'] = $arr_pg->external_id;
						$data_payment['qr_string'] = "";
						$data_payment['callback_url'] = "";
						$data_payment['description'] = "";
						$data_payment['type'] = "";
						$data_payment['account_number'] = $arr_pg->account_number;
						$data_payment['bank_code'] = $arr_pg->bank_code;
						$data_payment['merchant_code'] = $merchant_code;
						$data_payment['name'] = $arr_pg->name;
						$data_payment['is_closed'] = true;
						$data_payment['expected_amount'] = (int) $arr_pg->expected_amount;
						$data_payment['expiration_date'] = $expiration_date;
						$data_payment['is_single_use'] = $is_single_use;
						$data_payment['status'] = $arr_pg->status;
						$data_payment['currency'] = "IDR";
						$data_payment['country'] = "ID";
						$data_payment['img'] = $img_channel;
						$data_payment['actions'] = null;
						$data_payment['pt'] = 'PT. Mencetak Banyak Gol (Topscore)';
						$data_payment['payment_instruction'] = $payment_instruction;
					}
				}

				if ($row->no_resi_expedisi != '') {
					$no_resi = $row->no_resi_expedisi;
				} else {
					$no_resi = '';
				}
				if ($row->expedisi != '') {
					$expedisi = $row->expedisi;
				} else {
					$expedisi = '';
				}
				// cek jika sudah melakukan pembayaran
				if ($row->pt_callback_date == '') {
					$pt_callback_date = '';
				} else {
					$pt_callback_date = $row->pt_callback_date;
				}
				$shipping_date = '';
				if ($row->pos_discount == '') {
					$pdiskon = 0;
				} else {
					$pdiskon = $row->pos_discount;
				}
				// cek user sudah memberikan ulasan
				$cek_ulasan = isset($ratting_by_pid[$row->pid]) ? $ratting_by_pid[$row->pid] : null;
				if ($cek_ulasan) {
					$user_ratting = 1;
				} else {
					$user_ratting = 0;
				}

				$pickup_info = '';
				if ($expedisi == "AMBIL DI TOKO") {
					$pickup_info = '<ol type="1"><li>Silahkan datang ke Store <b>' . ($row->st_name ?? '') . '</b></li><li>Sebutkan nomor invoice pesanan kamu <b>' . $row->pos_invoice . '</b> ke kasir</li><li>Selesai</li></ol>';
				}

				// cek status invoice
				if (in_array($row->pos_status, array("CANCEL", "UNPAID"))) {
					$print_invoice = 0;
					$url_invoice = '';
				} else {
					$print_invoice = 1;
					$url_invoice = 'https://invoice.topscore.id/invoice_apps/' . $row->external_id;
				}

				$pos_tracking_url = 'https://resitracking.my.id/' . $expedisi . '/' . $no_resi;

				// cek store *** new kondisi data.. ***
				if (!$row->st_name) {
					$arr_store_new = isset($stores_by_id[$row->st_id]) ? $stores_by_id[$row->st_id] : null;
					$st_names = $arr_store_new ? $arr_store_new->st_name : '';
					$st_ids = $arr_store_new ? $arr_store_new->id : $row->st_id;
					$expedisi = !empty($expedisi) ? $expedisi : 'AMBIL DI TOKO';
				} else {
					$st_names = $row->st_name;
					$st_ids = $row->st_id_ref;
					$expedisi = $expedisi;
				}

				if ($row->pos_cust_address) {
					$cust_address = $row->pos_cust_address;
				} else {
					if ($arr_customers && $arr_customers->cust_address) {
						$cust_address = $arr_customers->cust_address;
					} else {
						$cust_address = '';
					}
				}

				if (in_array($row->pos_status, array('SHIPPING NUMBER', 'IN DELIVERY', 'DONE'))) {
					$payment_status = 'Paid';
				}

				// jika bukan orderan aplikasi atau data_payment masih kosong (fallback aman untuk mencegah crash deserialization JSON di mobile app)
				if (empty($data_payment) || $row->is_apps == 0) {
					$data_payment = [
						'channel_type' => ($row->is_apps == 0) ? 5 : 1,
						'external_id' => $row->external_id ?? '',
						'qr_string' => "",
						'callback_url' => "",
						'description' => "",
						'type' => "",
						'account_number' => $row->va_number ?? "",
						'bank_code' => ($row->is_apps == 0) ? "CASH" : "TRANSFER",
						'merchant_code' => "",
						'name' => "",
						'is_closed' => true,
						'expected_amount' => (int) $row->pos_real_price,
						'expiration_date' => $row->created_at ?? date('Y-m-d H:i:s'),
						'is_single_use' => false,
						'status' => strtoupper($payment_status),
						'currency' => "IDR",
						'country' => "ID",
						'img' => ($row->is_apps == 0) ? url('public/upload/cash_payment.png') : '',
						'actions' => null,
						'pt' => 'PT. Mencetak Banyak Gol (Topscore)',
						'payment_instruction' => array(),
					];
				}

				if ($row->pos_note) {
					$notes = $row->pos_note;
				} else {
					$notes = '';
				}

				$diskon_ongkir = 0;
				$diskon_produk = 0;
				// cek jenis voucher ***
				if ($row->voucher_apps_id) {
					$vc_ongkir = DB::table("apps_vouchers")->where("id", $row->voucher_apps_id)->first();
					if ($vc_ongkir) {
						if ($vc_ongkir->avc_code == 'DISKONONGKIR') {
							$diskon_ongkir = (int) $row->pos_discount;
						} else {
							$diskon_produk = (int) $row->pos_discount;
						}
					}
				}

				$pos_master[] = [
					'pos_id' => $row->pid,
					'customer_ratting' => $user_ratting,
					'pos_invoice' => $row->pos_invoice,
					'store_id' => $st_ids,
					'store_name' => $st_names,

					'total_price' => (int) $row->pos_real_price,
					'shipping_price' => (int) $row->pos_shipping,
					'pos_discount' => (int) $pdiskon,
					'note' => $notes,
					'pos_date' => $row->created_at,
					'external_id' => $row->external_id,
					'expedisi' => $expedisi,
					'no_resi' => $no_resi,
					'shipping_date' => $shipping_date,
					'cust_address' => $cust_address,
					'flag_ulasan' => count(DB::table("ratting_apps")->where('ra_type', '=', 'pos')->where('lookup_id', '=', $row->pid)
						->where('cust_id', '=', $cust_id)->get()),
					'item_details' => $detail_item_save,
					'data_payment' => $data_payment,
					'payment_date' => $pt_callback_date,
					'payment_status' => strtoupper($payment_status),
					'invoice_status' => $row->pos_status,
					'pickup_info' => $pickup_info,
					'print_invoice' => $print_invoice,
					'url_invoice' => $url_invoice,
					'pos_reseller' => (int) $row->pos_reseller,
					'pos_tracking_url' => $pos_tracking_url,
					'pos_fees' => (int) $row->pos_fees,
					'pos_coin' => (int) $row->pos_coin,
					'pos_discount_ongkir' => (int) $diskon_ongkir,
					'pos_discount_product' => (int) $diskon_produk,
				];
			}

			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success'
				],
				'banner' => url('public/upload/payment_product.png'),
				'invoice_details' => $pos_master
			];

		} else {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data not found'
				],
				'banner' => url('public/upload/payment_product.png'),
			];
		}
		return response()->json($data);
	}

	private function cekTransactionDetail($pt_id)
	{
		$query_data = DB::table("pos_transaction_details")
			->selectRaw('ts_pos_transaction_details.id as pid, sz_name, p_name, p_color, pos_td_qty, pos_td_sell_price, pos_td_total_price, p_main_image, p_image, pst_id')
			->leftJoin('product_stocks', 'product_stocks.id', '=', 'pos_transaction_details.pst_id')
			->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
			->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
			->where('pos_transaction_details.pt_id', '=', $pt_id)
			->orderBy('pos_transaction_details.created_at', 'DESC')
			->get();
		// jika data ditemukan...
		$item_savedetails = array();
		if (!empty($query_data)) {
			foreach ($query_data as $raw) {
				$image = \App\Helpers\ProductImageHelper::parseImages($raw->p_main_image, $raw->p_image);
				$image_url = !empty($image) ? "https://topsystem.id/api/product/600/" : "";

				$item_savedetails[] = [
					'detail_id' => $raw->pid,
					'pos_id' => $pt_id,
					'product_id' => $raw->pst_id,
					'product_name' => $raw->p_name,
					'product_size' => $raw->sz_name,
					'product_color' => $raw->p_color,
					'detail_qty' => (int) $raw->pos_td_qty,
					'detail_price' => (int) $raw->pos_td_sell_price,
					'detail_total' => (int) $raw->pos_td_total_price,
					'image_url' => $image_url,
					'image' => $image,
				];
			}
		}
		return $item_savedetails;
	}

	// CANCEL INVOICE ************
	public function cancelInvoice(Request $request)
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

		$external_id = $request->input('external_id');
		if (empty($external_id)) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'external_id tidak boleh kosong'
				]
			];
			return response()->json($data);
		}

		$cek_data = DB::table("pos_transactions")
			->select('pos_transactions.id', 'pos_invoice', 'st_id', 'cust_id', 'external_id', 'pos_status', 'pos_coin')
			->where("external_id", "=", $external_id)
			->where('pos_status', '=', 'UNPAID')
			->get();
		//if(empty($cek_data)){
		if (count($cek_data) == 0) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'external_id tidak ditemukan'
				]
			];
			return response()->json($data);
		}

		// jika sudah di cancel
		/*
		if($cek_data->pos_status == 'CANCEL'){
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'data sudah pernah di CANCEL'
				]
			];
			return response()->json($data);
		}
		*/

		$data = [
			'pos_status' => strtolower($request->input('pos_status'))
		];

		//if ($request->input('pos_status') == 'PAID') {
		$cust_id = '';
		if (1 == 1) {
			$nomor = 0;
			$nilai_coint = 0;
			foreach ($cek_data as $row_invoice) {
				$nomor++;
				// jika ada coint
				if ($row_invoice->pos_coin > 0) {
					$nilai_coint += $row_invoice->pos_coin;
				}

				// tolak balik stok
				$pt_id = $row_invoice->id;
				$pos_td = DB::table('pos_transaction_details')
					->select('pl_id', 'pst_id', 'pos_td_qty')
					->where('pt_id', '=', $pt_id)
					->get();
				if (!empty($pos_td)) {
					foreach ($pos_td as $row) {
						$pls_id = DB::table('product_location_setups')->select('id', 'pls_qty')->where([
							'pl_id' => $row->pl_id,
							'pst_id' => $row->pst_id
						])->get()->first();
						$pls_qty = $pls_id->pls_qty;
						if ($pls_qty < 0) {
							$pls_qty = 0;
						}
						$update_pls = DB::table('product_location_setups')->where('id', '=', $pls_id->id)->update([
							'pls_qty' => ($pls_qty + $row->pos_td_qty)
						]);

						// update stok di product_stocks //
						$pst_id = $row->pst_id;
						$stock_all = DB::table("product_location_setups")->where("pls_qty", ">", "0")->where("pst_id", "=", $pst_id)->sum('pls_qty');
						if ($stock_all > 0) {
							$stock_all = $stock_all;
						} else {
							$stock_all = 0;
						}
						$upstock_pst = DB::table("product_stocks")->where('id', $pst_id)->update(['ps_qty' => $stock_all]);
						// end update product_stocks //

					}
					$update = DB::table('pos_transactions')->where('id', '=', $pt_id)->update([
						'pos_status' => 'CANCEL'
					]);
					$plst = DB::table('product_location_setup_transactions')->where('pt_id', '=', $pt_id)->update([
						'plst_status' => 'INSTOCK'
					]);
					//$del_carts = DB::table('carts')->where('pt_id', '=', $pt_id)->delete();
				}

				$cust_id = $row_invoice->cust_id;
			} // end foreach
		}

		if ($nomor > 0) {
			// SAVE LOG NOTIF *********************** // ********************************************
			$an_description = 'Pesanan ' . $external_id . ' telah berhasil di batalkan.';
			$save_notif = DB::table('apps_notif')->insertGetId([
				'id_lookup' => $external_id,
				'cust_id' => $cust_id,
				'type_data' => 'pos',
				'an_title' => 'Pesanan dibatalkan',
				'an_description' => $an_description,
				'created_at' => date('Y-m-d H:i:s'),
			]);

			$data = [
				'status' => [
					'code' => 200,
					'external_id' => $external_id,
					'description' => 'Orderan berhasil di batalkan '
				]
			];
			//return response()->json($data);

			// cek coin jika ada transaksi yang menggunakan coin
			if ($nilai_coint > 0) {
				$arr_customer = DB::table("customers")->where("id", "=", $cust_id)->first();
				if ($arr_customer) {
					$up_customercoin = DB::table('customers')->where('id', '=', $cust_id)->update([
						'cust_coin' => $arr_customer->cust_coin + $nilai_coint,
					]);

					$data_coin = [
						'cust_id' => $cust_id,
						'ccoin_type' => 'pos',
						'ccoin_lookup_id' => $external_id,
						'ccoin_description' => 'Refund coin dengan nomor order ' . $external_id,
						'ccoin_value' => ($nilai_coint),
						'created_at' => date("Y-m-d H:i:s"),
					];
					DB::table("customer_coins")->insertGetId(array_merge($data_coin));
				}
			}

		} else {
			$data = [
				'status' => [
					'code' => 400,
					'external_id' => '',
					'description' => 'Gagal melakukan update data'
				]
			];
		}
		return response()->json($data);
	}

	// API PESAN LAGI ***
	public function orderRepeatcheck(Request $request)
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
		if (empty($external_id) || empty($cust_id)) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'external_id dan cust_id tidak boleh kosong'
				]
			];
			return response()->json($data);
		}

		$cek_data = DB::table("pos_transaction_details")
			->select('pos_transaction_details.id', 'pos_invoice', 'st_id_ref as st_id', 'cust_id', 'external_id', 'pos_status', 'pos_transaction_details.pst_id', 'pl_id', 'p_name', 'p_color', 'sz_name', 'ps_price_tag', 'p_price_tag', 'ps_sell_price', 'p_sell_price', 'p_main_image', 'p_image', 'sold')
			->leftJoin('pos_transactions', 'pos_transactions.id', '=', 'pos_transaction_details.pt_id')
			->leftJoin('product_stocks', 'product_stocks.id', '=', 'pos_transaction_details.pst_id')
			->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
			->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
			//->leftJoin('product_locations', 'product_locations.id', '=', 'pos_transaction_details.pl_id')
			//->leftJoin('stores', 'stores.id', '=', 'product_locations.st_id')
			->where("external_id", "=", $external_id)
			->where("cust_id", "=", $cust_id)
			->whereNotNull('p_main_image')
			->where('p_delete', '=', '0')
			->get();
		if (!$cek_data) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'external_id tidak ditemukan'
				]
			];
			return response()->json($data);
		}

		// BIN exeption / stok yang tidak valid
		$exception = DB::table('exception_locations')->select('pl_code')
			->leftJoin('product_locations', 'product_locations.id', '=', 'exception_locations.pl_id')->get();
		$exc_location = array();
		foreach ($exception as $exc) {
			$exc_location[] = $exc->pl_code;
		}

		$data_product = array();
		// tampilkan data
		foreach ($cek_data as $row) {
			// cek lokasi store
			$arr_loc = DB::table("product_locations")->where('id', '=', $row->pl_id)->first();
			$arr_store = DB::table("stores")->where('id', '=', $arr_loc->st_id)->first();

			// cek stock
			$arr_stock = DB::table('product_stocks')
				->selectRaw("SUM(ts_product_location_setups.pls_qty) as stock")
				->leftJoin('product_location_setups', 'product_location_setups.pst_id', '=', 'product_stocks.id')
				->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
				->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
				->whereNotIn('pl_code', $exc_location)
				->where('pls_qty', '>', '0')
				->whereNotNull('p_main_image')
				->where('p_price_tag', '>', '0')
				->where('p_delete', '=', '0')
				->whereNotNull('p_main_image')
				->where('product_stocks.id', '=', $row->pst_id)
				->where('product_locations.st_id', '=', $row->st_id)
				->first();
			if ($arr_stock) {
				$stock = $arr_stock->stock;
			} else {
				$stock = 0;
			}

			$image = \App\Helpers\ProductImageHelper::parseImages($row->p_main_image, $row->p_image);
			$image_url = !empty($image) ? $this->url_server . "api/product/600/" : "";
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

			/*
			$check_discount = DB::table('product_discounts')->select('pd_type', 'pd_value')
					->leftJoin('product_discount_details', 'product_discount_details.pd_id', '=', 'product_discounts.id')
					->where('product_discounts.std_id', '=', '9')
					->where('product_discount_details.pst_id', '=', $row->pst_id)
					->where('product_discounts.pd_date', '>=', date('Y-m-d'))
					->first();
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
			*/
			$persen_potongan = 0;
			// cek diskon aktif apps
			$arr_promo = DB::table("product_discount_details")
				->select('product_discount_details.id', 'product_discount_details.pst_id', 'pd_value', 'pd_type')
				->leftJoin('product_discounts', 'product_discounts.id', '=', 'product_discount_details.pd_id')
				->leftJoin('product_stocks', 'product_stocks.id', '=', 'product_discount_details.pst_id')
				->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
				->where('std_id', '=', '20') // kusus apps
				->where('pd_date', '>=', date("Y-m-d"))
				->where('products.p_name', '=', $row->p_name)
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

			$arr_store = DB::table("stores")->where('id', '=', $arr_loc->st_id)->first();
			$data_product[] = [
				'product_id' => $row->pst_id,
				'product_name' => $row->p_name,
				'product_color' => $row->p_color,
				'product_size' => $row->sz_name,
				'product_price_tag' => $price_tag,
				'product_sell_price' => $sell_price,
				'product_discount' => round($persen_potongan),
				'product_sold' => (int) $row->sold,
				'product_stock' => (int) $stock,
				'product_image_url' => $image_url,
				'product_image' => $image,
				'store_id' => $arr_store->id,
				'store_name' => $arr_store->st_name,
			];
		}

		$data = [
			'status' => [
				'code' => 200,
				'description' => 'Data order di temukan'
			],
			'detail_product' => $data_product,
		];
		return response()->json($data);
	}

	// tambahkan ke keranjang dari proses pesan lagi
	public function repeatOrderCart(Request $request)
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
		$detail_product = $request->detail_product;
		if (empty($cust_id) || empty($detail_product)) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Field cust_id dan detail_product tidak boleh kosong'
				]
			];
			return response()->json($data);
		}

		// cek data json sesuai produk yang akan di masukan ke keranjang belanja
		// {product_id:1, st_id:1, product_name:jogosala, product_color:color, product_size:31}
		$data_produk = json_decode($detail_product);
		// save answer form
		$no_save = '';
		foreach ($data_produk as $key => $lookup) {
			$check_cart = DB::table('apps_carts')->select('id')
				->where('cust_id', '=', $cust_id)
				->where('pst_id', '=', $lookup->product_id)
				->where('st_id', '=', $lookup->st_id)
				->get()->first();
			if (!empty($check_cart)) {
				// edit data
				$id = $check_cart->id;
				$mode = 'edit';
				$product_qty_new = 1;
			} else {
				// data baru 
				$id = '';
				$mode = 'add';
				$product_qty_new = 1;
			}

			// save chart
			$data = [
				'cust_id' => $cust_id,
				'pst_first' => $lookup->product_id,
				'pst_id' => $lookup->product_id,
				'qty' => $product_qty_new,
				'st_id' => $lookup->st_id,
				'color' => $lookup->product_color,
				'size' => $lookup->product_size,
				'product_name' => $lookup->product_name,
			];

			$results = array();
			if ($this->SaveChart($mode, $id, $data)) {
				$no_save++;
			}
		}
		// end submit cart produk

		if ($no_save > 0) {
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

	// selesaikan pesanan
	public function doneInvoice(Request $request)
	{
		// $save = DB::table('apps_carts')->where('id', $id)->update(array_merge($data, $updated));
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
		$pos_invoice = $request->pos_invoice;
		if (!$cust_id || !$pos_invoice) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Feld cust_id dan pos_invoice tidak boleh kosong!'
				]
			];
			return response()->json($data);
		}
		$arr_customer = DB::table("customers")->where(['id' => $cust_id, 'cust_delete' => '0'])->first();
		$arr_pos = DB::table("pos_transactions")->where(['pos_invoice' => $pos_invoice])->first();

		if (!$arr_customer) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data customer tidak ditemukan!'
				]
			];
			return response()->json($data);
		}

		if (!$arr_pos) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data pesanan tidak ditemukan!'
				]
			];
			return response()->json($data);
		}

		// cek status invoice dalam proses pengiriman...
		if (in_array($arr_pos->pos_status, array('IN DELIVERY', 'SHIPPING NUMBER'))) {
			DB::table('pos_transactions')->where('id', $arr_pos->id)->update(['pos_status' => 'DONE']);
		}

		$data = [
			'status' => [
				'code' => 200,
				'description' => 'Success'
			]
		];
		return response()->json($data);
	}

	// kirim sms order produk
	private function waSend($phone, $name, $external_id, $total)
	{
		$phone = str_replace('+', '', $phone);
		$check = substr($phone, 0, 1);
		if ($check == '0' || $check == 0) {
			$phone = preg_replace('/^0/', '62', $phone);
		}

		$message = "Halo *" . $name . "*!

Terima kasih telah berbelanja di APLIKASI TOPSCORE.
Kami telah menerima order kamu dengan kode transaksi *#" . $external_id . "*.

Total yang harus dibayar adalah:
Rp " . number_format($total, 0, ',', '.') . "

Silahkan menyelesaikan pembayaran sesuai metode pembayaran yang kamu pilih di aplikasi

Cashback dan Score otomatis masuk ke akun kamu

------------------------
Balas pesan ini jika butuh bantuan :)";

		// simpan ke table dlu, jalankan dari cron job.
		if ($phone) {
			$data_log = [
				// 'cust_id' => $cust_id,
				'wa_receiver' => $phone,
				'wa_phone' => $phone,
				'wa_message' => $message,
				'wa_status' => 'Waiting',
				'created_at' => date("Y-m-d H:i:s"),
			];
			DB::table("whatsapps")->insertGetId(array_merge($data_log));
		}
		return true;
		// simpan data ke table *** untuk dikirm whatsapp


		$curl = curl_init();
		$token = config('services.whatsapp.wablas_token');
		$data = [
			'phone' => $phone,
			'message' => $message,
		];
		curl_setopt(
			$curl,
			CURLOPT_HTTPHEADER,
			array(
				"Authorization: $token",
			)
		);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
		curl_setopt($curl, CURLOPT_URL, "https://pati.wablas.com/api/send-message");
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

		$result = curl_exec($curl);
		curl_close($curl);
		$getdata = json_decode($result);
		if ($getdata->status == 'pending') {
			return 'Success';
		} else {
			return 'Fail';
		}
	}

}
