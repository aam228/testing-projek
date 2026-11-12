<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
//use App\Models\Artikel;
use App\Midtrans\Config;
use App\Midtrans\Transaction;
use App\Midtrans\ApiRequestor;
use App\Midtrans\SnapApiRequestor;
use App\Midtrans\Notification;
use App\Midtrans\CoreApi;
use App\Midtrans\Snap;
use App\Midtrans\Sanitizer;


class PaymentMidtrans extends Controller
{   
	protected $key_midtrans;

	public function __construct()
	{
		$this->key_midtrans = config('services.midtrans.server_key');
	}
	protected $production_status = false;
	
	public function getPaymentMandiri(Request $request)
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

        Config::$serverKey = $this->key_midtrans;
		Config::$isProduction = $this->production_status;
		//Config::$isProduction = true;
		$no_invoice = date("YmdHis");
		$item_details = $request->item_details;
		//echo $item_details; exit();
		//$item_details = '[{"id": "1", "price": 5000, "quantity": 2, "name": "Jogosala Revolt size 40 Black"}, {"id": "2", "price": 5000, "quantity": 2, "name": "Jogosala Revolt size 40 Black"}]';
		$item_details =  json_decode($item_details);
		//echo count($item_details).' == ';
		$array_item=array();
		foreach ($item_details as $key => $lookup) {
			$row_item = array();
		  	//echo $key." Price: ".$lookup->price." <br>";
			$row_item['id'] = $lookup->id;
			$row_item['price'] = $lookup->price;
			$row_item['quantity'] = $lookup->quantity;
			$row_item['name'] = $lookup->name;
		  	$array_item[] = $row_item;
		}
		$item_details = $array_item;
		//echo json_encode($item_details); 
		//exit();
		$transaction_data = array(
			'payment_type' => 'echannel',
			'echannel' => [
				'bill_info1' => 'Invoice',
				'bill_info2' => $no_invoice,
				//"bill_key" => '085376341110'
			],
			'transaction_details' => [
				'order_id' => rand(100,999).date("YmdHis"),
				'gross_amount' => $request->total_invoice,
			],
			'item_details'        => $item_details,
			/*'customer_details'    => [
				'first_name'    => "Bowo",
				'last_name'     => "Susanto",
				'email'         => "bowo@topscore.id",
				'phone'         => "085376341110",
				'billing_address'  => [
					'first_name'    => "Susanto",
					'last_name'     => "Wibowo",
					'address'       => "Jl. Akses UI Kelapa Dua",
					'city'          => "Depok",
					'postal_code'   => "28282",
					'phone'         => "085376341110",
					'country_code'  => 'IDN'
				],
				'shipping_address' => [
					'first_name'    => "Susanto",
					'last_name'     => "Wibowo",
					'address'       => "Jl. Akses UI Kelapa Dua",
					'city'          => "Depok",
					'postal_code'   => "28282",
					'phone'         => "085376341110",
					'country_code'  => 'IDN'
				],
			]*/
		  );

		try {
			$response = CoreApi::charge($transaction_data);
		} catch (Exception $e) {
			echo $e->getMessage();
			die();
		}
		
		
        /*
		$response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            return "cURL Error #:" . $err;
        } else {
            return json_decode($response, true);
        }
		*/
		//return json_decode($response, true);
		return response()->json($response);
    }
	
	// Bank BNI
	public function getPaymentBni(Request $request)
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

        Config::$serverKey = $this->key_midtrans;
		Config::$isProduction = $this->production_status;
		$no_invoice = date("YmdHis");
		$item_details = $request->item_details;
		$item_details =  json_decode($item_details);
		$array_item=array();
		foreach ($item_details as $key => $lookup) {
			$row_item = array();
			$row_item['id'] = $lookup->id;
			$row_item['price'] = $lookup->price;
			$row_item['quantity'] = $lookup->quantity;
			$row_item['name'] = $lookup->name;
		  	$array_item[] = $row_item;
		}
		$item_details = $array_item;
		$customer_details = json_decode($request->customer_details);
		$row_customer = array();
		$row_customer['email'] = $customer_details->email;
		$row_customer['first_name'] = $customer_details->first_name;
		$row_customer['last_name'] = $customer_details->last_name;
		$row_customer['phone'] = $customer_details->phone;
		//echo $customer_details->phone; exit();
		
		$transaction_data = array(
			'payment_type' => 'bank_transfer',
			'transaction_details' => [
				'order_id' => rand(100,999).date("YmdHis"),
				'gross_amount' => $request->total_invoice,
			],
			'customer_details' => $row_customer,
			/*'customer_details' => [
				'email' => 'susanto.wibowoo@gmail.com',
				'first_name' => 'susanto',
				"last_name" => 'wibowo',
				"phone" => '085376341110',
			],*/
			'item_details'        => $item_details,
			'bank_transfer' => [
				'bank' => 'bni',
				'va_number' => '',
			],
			
		  );

		try {
			$response = CoreApi::charge($transaction_data);
		} catch (Exception $e) {
			echo $e->getMessage();
			die();
		}
		
		return response()->json($response);
    }
	
	// Bank BRI
	public function getPaymentBri(Request $request)
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

        Config::$serverKey = $this->key_midtrans;
		Config::$isProduction = $this->production_status;
		$no_invoice = date("YmdHis");
		$item_details = $request->item_details;
		$item_details =  json_decode($item_details);
		$array_item=array();
		foreach ($item_details as $key => $lookup) {
			$row_item = array();
			$row_item['id'] = $lookup->id;
			$row_item['price'] = $lookup->price;
			$row_item['quantity'] = $lookup->quantity;
			$row_item['name'] = $lookup->name;
		  	$array_item[] = $row_item;
		}
		$item_details = $array_item;
		$customer_details = json_decode($request->customer_details);
		$row_customer = array();
		$row_customer['email'] = $customer_details->email;
		$row_customer['first_name'] = $customer_details->first_name;
		$row_customer['last_name'] = $customer_details->last_name;
		$row_customer['phone'] = $customer_details->phone;
		//echo $customer_details->phone; exit();
		
		$transaction_data = array(
			'payment_type' => 'bank_transfer',
			'transaction_details' => [
				'order_id' => rand(100,999).date("YmdHis"),
				'gross_amount' => $request->total_invoice,
			],
			'customer_details' => $row_customer,
			'item_details'        => $item_details,
			'bank_transfer' => [
				'bank' => 'bri',
				'va_number' => '',
			],
			
		  );

		try {
			$response = CoreApi::charge($transaction_data);
		} catch (Exception $e) {
			echo $e->getMessage();
			die();
		}
		
		return response()->json($response);
    }
	
	// Bank Permata
	public function getPaymentPermata(Request $request)
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

        Config::$serverKey = $this->key_midtrans;
		Config::$isProduction = $this->production_status;
		$no_invoice = date("YmdHis");
		$item_details = $request->item_details;
		$item_details =  json_decode($item_details);
		$array_item=array();
		foreach ($item_details as $key => $lookup) {
			$row_item = array();
			$row_item['id'] = $lookup->id;
			$row_item['price'] = $lookup->price;
			$row_item['quantity'] = $lookup->quantity;
			$row_item['name'] = $lookup->name;
		  	$array_item[] = $row_item;
		}
		$item_details = $array_item;
		$customer_details = json_decode($request->customer_details);
		$row_customer = array();
		$row_customer['email'] = $customer_details->email;
		$row_customer['first_name'] = $customer_details->first_name;
		$row_customer['last_name'] = $customer_details->last_name;
		$row_customer['phone'] = $customer_details->phone;
		//echo $customer_details->phone; exit();
		if($customer_details->last_name!=''){
			$nama_customer = $customer_details->first_name.' '.$customer_details->last_name;
		} else {
			$nama_customer = $customer_details->first_name;
		}
		
		$transaction_data = array(
			'payment_type' => 'bank_transfer',
			'bank_transfer' => [
				'bank' => 'permata',
				'permata' => [
					'recipient_name' => $nama_customer,
				],
			],
			'transaction_details' => [
				'order_id' => rand(100,999).date("YmdHis"),
				'gross_amount' => $request->total_invoice,
			],
			//'customer_details' => $row_customer,
			//'item_details'        => $item_details,
			
		  );

		try {
			$response = CoreApi::charge($transaction_data);
		} catch (Exception $e) {
			echo $e->getMessage();
			die();
		}
		
		return response()->json($response);
    }
	
	// BCA
	public function getPaymentBca(Request $request)
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

        Config::$serverKey = $this->key_midtrans;
		Config::$isProduction = $this->production_status;
		$no_invoice = date("YmdHis");
		$item_details = $request->item_details;
		$item_details =  json_decode($item_details);
		$array_item=array();
		foreach ($item_details as $key => $lookup) {
			$row_item = array();
			$row_item['id'] = $lookup->id;
			$row_item['price'] = $lookup->price;
			$row_item['quantity'] = $lookup->quantity;
			$row_item['name'] = $lookup->name;
		  	$array_item[] = $row_item;
		}
		$item_details = $array_item;
		$customer_details = json_decode($request->customer_details);
		$row_customer = array();
		$row_customer['email'] = $customer_details->email;
		$row_customer['first_name'] = $customer_details->first_name;
		$row_customer['last_name'] = $customer_details->last_name;
		$row_customer['phone'] = $customer_details->phone;
		//echo $customer_details->phone; exit();
		
		$transaction_data = array(
			'payment_type' => 'bank_transfer',
			'transaction_details' => [
				'order_id' => rand(100,999).date("YmdHis"),
				'gross_amount' => $request->total_invoice,
			],
			'customer_details' => $row_customer,
			'item_details'        => $item_details,
			'bank_transfer' => [
				'bank' => 'bca',
				'va_number' => '',
				'free_text' => [
					'inquiry' => [
						'id' => 'Invoice no '.$no_invoice,
						'en' => 'Invoice no '.$no_invoice,
					],
					'payment' => [
						'id' => $request->total_invoice,
						'en' => $request->total_invoice,
					]
				],
			],
			'bca' => [
				'sub_company_code' => 'G810178141',
			]
			
		  );

		try {
			$response = CoreApi::charge($transaction_data);
		} catch (Exception $e) {
			echo $e->getMessage();
			die();
		}
		
		return response()->json($response);
    }
}

