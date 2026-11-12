<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
//use App\Models\Artikel;

class PaymentXendit extends Controller
{
	protected $server_domain = 'https://api.xendit.co';
		//protected $secret_api_key = 'xnd_development_BiwG1eyewnIqZZnY05AV6yaTmcXji00SbXM1JIJy2aM0t1hqduxxCrLCodi'; // SANDBOX
		protected $secret_api_key;

	public function __construct()
	{
		$this->secret_api_key = config('services.xendit.secret_key');
	}


	// $posting_payment = $xendit_pg->createvirtualAccount($external_id, $total_amount, $nama_va_users, $bank_type);
	public function createvirtualAccount(Request $request)
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
		$amount = $request->amount;
		$nama_user = $request->nama_user;
		$bank_code = $request->bank_code;
		$bank_options = NULL;

		$curl = curl_init();

		$headers = array();
		$headers[] = 'Content-Type: application/json';

		$disbursement_options = [];
		if (!empty($disbursement_options['X-IDEMPOTENCY-KEY'])) {
			array_push($headers, 'X-IDEMPOTENCY-KEY: ' . $disbursement_options['X-IDEMPOTENCY-KEY']);
		}

		$end_point = $this->server_domain . '/callback_virtual_accounts';

		$data['external_id'] = $external_id;
		$data['name'] = $nama_user;
		$data['bank_code'] = $bank_code;
		$data['expected_amount'] = (int) $amount;
		$data['expiration_date'] = date('Y-m-d', strtotime(date("Y-m-d") . ' + 1 days')) . " 23:59:59";
		$data['is_closed'] = true;

		if (is_array($bank_options)) {
			foreach ($bank_options as $key => $value) {
				$data[$key] = $value;
			}
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
			'payment_instruction' => $results
		];
		return response()->json($data);
	}

	public function getInvoice(Request $request)
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

		$invoice_id = $request->invoice_id;

		$curl = curl_init();

		$headers = array();
		$headers[] = 'Content-Type: application/json';

		$end_point = $this->server_domain . '/v2/invoices/' . $invoice_id;

		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_USERPWD, $this->secret_api_key . ":");
		curl_setopt($curl, CURLOPT_URL, $end_point);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_CAINFO, dirname(__FILE__) . "/data/cacert.pem");

		$response = curl_exec($curl);
		curl_close($curl);

		$responseObject = json_decode($response, true);
		return $responseObject;
	}

	public function getCek(Request $request)
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

		$invoice_id = $request->invoice_id;

		$curl = curl_init();

		$headers = array();
		$headers[] = 'Content-Type: application/json';
		$end_point = $this->server_domain . '/callback_virtual_accounts/' . $invoice_id;

		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_USERPWD, $this->secret_api_key . ":");
		curl_setopt($curl, CURLOPT_URL, $end_point);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_CAINFO, dirname(__FILE__) . "/data/cacert.pem");

		$response = curl_exec($curl);
		curl_close($curl);

		$responseObject = json_decode($response, true);
		return $responseObject;
	}

	public function getcek_qrcode(Request $request)
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

		$curl = curl_init();

		$headers = array();
		$headers[] = 'Content-Type: application/json';
		$end_point = $this->server_domain . '/qr_codes/payments?external_id=' . $external_id;
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_USERPWD, $this->secret_api_key . ":");
		curl_setopt($curl, CURLOPT_URL, $end_point);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_CAINFO, dirname(__FILE__) . "/data/cacert.pem");

		$response = curl_exec($curl);
		curl_close($curl);

		$responseObject = json_decode($response, true);
		return $responseObject;
	}

	public function getCallback(Request $request)
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
		$bank_code = $request->bank_code;
		$name = $request->name;
		$amount = $request->amount;
		$virtual_account_number = NULL;

		$curl = curl_init();
		$headers = array();
		$headers[] = 'Content-Type: application/json';

		$end_point = $this->server_domain . '/callback_virtual_accounts';

		$data['external_id'] = $external_id;
		$data['bank_code'] = $bank_code;
		$data['name'] = $name;
		$data['is_closed'] = true;
		$data['expected_amount'] = (int) $amount;
		//$data['expected_amount'] = 16500;

		if (!empty($virtual_account_number)) {
			$data['virtual_account_number'] = $virtual_account_number;
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
		return $responseObject;
	}

	// QRIS
	public function createQris(Request $request)
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
		$amount = $request->amount;
		$type = 'DYNAMIC'; //$request->type_qris;
		$callback_url = 'https://production.topscore.id/api/callback_xd'; //$request->callback_url; 
		//$callback_url = 'https://topscore.id/api/callback_xd_va';
		$qris_options = NULL;

		$curl = curl_init();

		$headers = array();
		$headers[] = 'Content-Type: application/json';

		if (!empty($qris_options['X-IDEMPOTENCY-KEY'])) {
			array_push($headers, 'X-IDEMPOTENCY-KEY: ' . $qris_options['X-IDEMPOTENCY-KEY']);
		}

		//$end_point = $this->server_domain.'/callback_virtual_accounts';
		$end_point = $this->server_domain . '/qr_codes';

		$data['external_id'] = $external_id;
		$data['amount'] = (int) $amount;
		$data['type'] = $type;
		$data['callback_url'] = $callback_url;

		/*if ( is_array($ewallet_options) ) {
			foreach ( $ewallet_options as $key => $value ) {
				$data[$key] = $value;
			}
		}*/


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
		//exit();
		$json_view = json_encode($responseObject);
		$json_parsing = json_decode($json_view);

		$instruksi_payment = $this->instruksiPayment('QRIS');
		$results = array();
		if (!empty($instruksi_payment)) {
			foreach ($instruksi_payment as $row) {
				$results[] = [
					'metode' => $row->metod_instruction_payget,
					'instruction' => $row->instruction_payget,
				];
			}
		}
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
			'payment_instruction' => $results
		];
		return response()->json($data);
	}


	// Payment E-Wallet
	public function createewallet(Request $request)
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
		$amount = $request->amount;
		$nama_user = $request->nama_user;
		$bank_code = $request->bank_code;
		$phone = $request->phone;
		$phone = str_replace('+', '', $phone);
		$check = substr($phone, 0, 1);
		if ($check == '0' || $check == 0) {
			$phone = preg_replace('/^0/', '62', $phone);
		}

		$curl = curl_init();

		$headers = array();
		$headers[] = 'Content-Type: application/json';

		$disbursement_options = [];
		if (!empty($disbursement_options['X-IDEMPOTENCY-KEY'])) {
			array_push($headers, 'X-IDEMPOTENCY-KEY: ' . $disbursement_options['X-IDEMPOTENCY-KEY']);
		}

		$end_point = $this->server_domain . '/ewallets/charges';
		/*
		$data['external_id'] = $external_id;
		$data['name'] = $nama_user;
		$data['bank_code'] = $bank_code;
		$data['expected_amount'] = (int)$amount;
		$data['expiration_date'] = date('Y-m-d', strtotime(date("Y-m-d"). ' + 1 days'))." 23:59:59"; 
		$data['is_closed'] = true;
		*/
		$data['reference_id'] = $external_id;
		$data['currency'] = "IDR";
		$data['amount'] = (int) $amount;
		$data['checkout_method'] = "ONE_TIME_PAYMENT";
		$data['channel_code'] = $bank_code;
		$data['channel_properties'] = array("mobile_number" => $phone);

		if ($bank_code == "LINKAJA") {
			$data['items'] = array();
			$row_data['id'] = "linkaja" . rand(100, 999);
			$row_data['name'] = $nama_user;
			$row_data['price'] = (int) $amount;
			$row_data['quantity'] = 1;
			array_push($data['items'], $row_data);
			$data['callback_url'] = 'https://topscore.id/api/callback_ewallet';
			$data['redirect_url'] = 'https://topscore.id/api/callback_ewallet';
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
		//return response()->json($responseObject); exit();

		$json_view = json_encode($responseObject);
		$json_parsing = json_decode($json_view);
		//$json_subdata = $json_parsing->data;

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
		$data = [
			'id' => $json_parsing->id,
			'owner_id' => $json_parsing->business_id,
			'external_id' => $json_parsing->reference_id,
			'account_number' => $json_parsing->reference_id,
			'bank_code' => $json_parsing->channel_code,
			'merchant_code' => $json_parsing->business_id,
			'name' => $nama_user,
			'is_closed' => true,
			'expected_amount' => $json_parsing->charge_amount,
			'expiration_date' => '60',
			'is_single_use' => true,
			'status' => $json_parsing->status,
			'currency' => $json_parsing->currency,
			'country' => 'IDN',
			'payment_instruction' => $results
		];
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
			//->where('pg_metod_payget.bank_code', '=', '0')
			/*->where(function($w) use($kategori) {
				if (!empty($kategori)) {
					$w->where('ba_kategori', '=', $kategori);
				}
			})*/
			->orderBy('pg_metod_instruction_payget.sort')
			->get();
		/*
		$results = array();
		if (!empty($query_apps)) {
			foreach ($query_apps as $row) {
				$results[] = [
					'methofe' => $row->pid,
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
		*/
		return $query_apps;
	}

}
