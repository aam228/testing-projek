<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
//use App\Models\User;
use App\Jobs\ProcessXenditWebhookJob;
use App\Mail\TicketEvent;
use Mail;
use Hash;

class CallbackController extends Controller
{
	//protected $table = 'event_tops';
	//protected $server_domain = 'https://api.xendit.co';
	//protected $secret_api_key = 'xnd_development_E7did9g3635fyKoLPg736TkunasCCKhDeHFWy5LiYRkRzxXMmZrd4PsV9lNjgR'; // SANDBOX
	//protected $secret_api_key = 'xnd_development_E7did9g3635fyKoLPg736TkunasCCKhDeHFWy5LiYRkRzxXMmZrd4PsV9lNjgR'; // LIVE

	//protected $mycoinscore = new Mycoinscore;

	// Cek View data (callback QR Code)
	public function index(Request $request)
	{
		$json = file_get_contents('php://input');
		$data_json_posting = json_decode(mb_convert_encoding($json, 'UTF-8', 'ISO-8859-1'));
		$json_parsing = json_encode($data_json_posting);
		$data_jsoncallback = [
			'callback' => $json_parsing,
			'created_at' => date("Y-m-d H:i:s"),
		];
		$get_idsave = DB::table("callback_xendit")->insertGetId(array_merge($data_jsoncallback));

		// Proses update payment (coin, status pesanan, notif, WA, email) dijalankan
		// di background job supaya response ke Xendit tetap cepat & tidak timeout.
		ProcessXenditWebhookJob::dispatch('qr', $json_parsing);

		echo $json_parsing;
	}

	// Callback Virtual Account
	public function CallbackVirtualAccount(Request $request)
	{
		$json = file_get_contents('php://input');
		$data_json_posting = json_decode(mb_convert_encoding($json, 'UTF-8', 'ISO-8859-1'));
		$json_parsing = json_encode($data_json_posting);
		$data_jsoncallback = [
			'callback' => $json_parsing,
			'created_at' => date("Y-m-d H:i:s"),
		];
		$get_idsave = DB::table("callback_xendit")->insertGetId(array_merge($data_jsoncallback));

		ProcessXenditWebhookJob::dispatch('va', $json_parsing);

		echo $json_parsing;
	}

	// Callback E-Wallet
	public function CallbackEwallet(Request $request)
	{
		$json = file_get_contents('php://input');
		$data_json_posting = json_decode(mb_convert_encoding($json, 'UTF-8', 'ISO-8859-1'));
		$json_parsing = json_encode($data_json_posting);
		$data_jsoncallback = [
			'callback' => $json_parsing,
			'created_at' => date("Y-m-d H:i:s"),
		];
		$get_idsave = DB::table("callback_xendit")->insertGetId(array_merge($data_jsoncallback));

		ProcessXenditWebhookJob::dispatch('ewallet', $json_parsing);

		echo $json_parsing;
	}
	// End E-Wallet

	// kirim WA donasi sukses order
	private function waSend_donasi($phone, $name, $invoice, $total)
	{
		$phone = str_replace('+', '', $phone);
		$check = substr($phone, 0, 1);
		if ($check == '0' || $check == 0) {
			$phone = preg_replace('/^0/', '62', $phone);
		}
		$message = "Halo *" . $name . "*!

Terima kasih telah berdonasi di TOPSCORE.
Kami telah menerima pembayaran donasi anda dengan Invoice *#" . $invoice . "*.

Total yang sudah dibayar adalah:
Rp " . number_format($total, 0, ',', '.') . "

Semoga dilancarkan rezekinya dan donasi yang disumbangkan membawa berkah buat yang menerima, Aamiin...
------------------------
Balas pesan ini jika butuh bantuan :)";

		$curl = curl_init();
		/*
        $token = config('services.whatsapp.wablas_token');
		$data = [
			'phone' => $phone,
			'message' => $message,
		];
		curl_setopt($curl, CURLOPT_HTTPHEADER,
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
		if ($getdata->status=='pending') {
			return 'Success';
		} else {
			return 'Fail';
		}
		*/
		$dataSending = [
		  "api_key" => config('services.whatsapp.watzap_api_key'),
			"number_key" => config('services.whatsapp.watzap_number_key'),
			"phone_no" => $phone,
			"message" => $message,
			"wait_until_send" => "1",
		];

		//$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => 'https://api.watzap.id/v1/send_message',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS => json_encode($dataSending),
			CURLOPT_HTTPHEADER => array(
				'Content-Type: application/json'
			),
		));
		$response = curl_exec($curl);
		curl_close($curl);
		$getdata = json_decode($response);
		//echo $response;
		if ($getdata->status == '200') {
			return 'Success';
		} else {
			return 'Fail';
		}
	}


	// kirim notifikasi ke firebase ********************
	// get token 
	private function get_access_token($JSON_file_path)
	{
		$client = new \Google_Client();
		try {
			$client->setAuthConfig($JSON_file_path);
			$client->addScope(\Google_Service_FirebaseCloudMessaging::CLOUD_PLATFORM);
			//$client->addScope($JSON_file_path);

			$savedTokenJson = $this->readSavedToken();

			if ($savedTokenJson) {
				// the token exists, set it to the client and check if it's still valid
				$client->setAccessToken($savedTokenJson);
				$accessToken = $savedTokenJson;
				if ($client->isAccessTokenExpired()) {
					// the token is expired, generate a new token and set it to the client
					$accessToken = $this->generateToken($client);
					$client->setAccessToken($accessToken);
				}
			} else {
				// the token doesn't exist, generate a new token and set it to the client
				$accessToken = $this->generateToken($client);
				$client->setAccessToken($accessToken);
			}


			$oauthToken = $accessToken["access_token"];

			return $oauthToken;


		} catch (Google_Exception $e) {
		}
		return false;
	}

	//Using a simple file to cache and read the toke, can store it in a databse also
	private function readSavedToken()
	{
		$tk = @file_get_contents('token.cache');
		if ($tk)
			return json_decode($tk, true);
		else
			return false;
	}

	private function writeToken($tk)
	{
		file_put_contents("token.cache", $tk);
	}

	private function generateToken($client)
	{
		$client->fetchAccessTokenWithAssertion();
		$accessToken = $client->getAccessToken();

		$tokenJson = json_encode($accessToken);
		$this->writeToken($tokenJson);

		return $accessToken;
	}

	private function SendNotify($cust_id)
	{
		//$cust_id 	= $request->cust_id;
		$cust_data = DB::table("customers")->where("id", "=", $cust_id)->first();
		if (!$cust_data) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data user tidak ditemukan'
				]
			];
			return response()->json($data);
		}

		$access_token = $this->get_access_token(public_path("fgoogle/gtoken/auth_adminsdk_v3.json"));
		//echo $access_token;
		//exit();

		if (!empty($cust_data->cust_token_id)) {
			$token = $cust_data->cust_token_id;
		} else {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Token user tidak tersimpan'
				]
			];
			return response()->json($data);
		}

		$url = "https://fcm.googleapis.com/v1/projects/auth-tops/messages:send";
		$data = [
			'message' => [
				"data" => [
					"title" => 'Pembayaran berhasil',
					//"description" => 'Berhasil melakukan pembayaran',
					"description" => 'Kamu mendapatkan cashback',
					"type" => 'm.pesanan.payment',
				],
				'token' => $token
			]
		];
		$options = array(
			CURLOPT_URL => $url,
			CURLOPT_POST => true,
			CURLOPT_HTTPHEADER => array(
				"Authorization: Bearer " . $access_token,
				"Content-Type: application/json",
			),
			CURLOPT_POSTFIELDS => json_encode($data),
		);
		$curl = curl_init();
		curl_setopt_array($curl, $options);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($curl);
		curl_close($curl);
		//echo $response;
		//$json_respon = json_encode($response);
		$json_respon = json_decode($response);
		if (!empty($json_respon->name)) {
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Notifikasi berhasil di kirim'
				]
			];
			return response()->json($data);
		} else {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Notifikasi gagal di kirim'
				]
			];
			return response()->json($data);
		}
	}

	public function callback_watsapp(Request $request)
	{
		echo '';
		exit();
		$json = file_get_contents('php://input');
		$data_json_posting = json_decode(mb_convert_encoding($json, 'UTF-8', 'ISO-8859-1'));
		$json_parsing = json_encode($data_json_posting);
		$data_jsoncallback = [
			'cb_repon' => $json_parsing,
			'created_at' => date("Y-m-d H:i:s"),
		];
		$get_idsave = DB::table("callback_whatsapp")->insertGetId(array_merge($data_jsoncallback));
	}

}
