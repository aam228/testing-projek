<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BiteshipController extends Controller
{
	public function __construct()
    {
		$this->token = env('BITESHIP_TOKEN');
		$this->token_live = env('BITESHIP_LIVE_TOKEN');
    }
	
	private function auth($client_id,$api_key)
	{
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

		$kode_pos = $request->kode_pos;
		$curl	= curl_init();
		$url_api = "https://api.biteship.com/v1/maps/areas?countries=ID&input=".$kode_pos."&type=single";
		$data = [];
		/*
		$data	= ['username'	=> $this->username,
				   'api_key' 	=> $this->api_key,
				   'from'=> $from_location,
				   'thru'=> $to_location,
				   'weight'=> $weight,
				   ];
		*/
		curl_setopt($curl,CURLOPT_HTTPHEADER,array("Authorization: ".$this->token_live,));
		curl_setopt($curl,CURLOPT_CUSTOMREQUEST,"GET");
		curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($data));
		curl_setopt($curl,CURLOPT_URL,$url_api);
		curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,0);
		curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,0);
		$result=curl_exec($curl);
		curl_close($curl);
		$getdata=json_decode($result);
		return $getdata;
		
	}
	
	// cek ongkir biteship
	/*
	public function cekOngkir(Request $request)
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

		$origin_area_id = $request->origin_area_id;
		$destination_area_id = $request->destination_area_id;
		$couriers = $request->couriers;
		$item_details = $request->items;
		$item_details =  json_decode($item_details);
		$array_item=array();
		foreach ($item_details as $key => $lookup) {
			$row_item = array();
			$row_item['name'] = $lookup->name;
			$row_item['description'] = $lookup->description;
			$row_item['value'] = $lookup->value;
			$row_item['length'] = $lookup->length;
			$row_item['width'] = $lookup->width;
			$row_item['height'] = $lookup->height;
			$row_item['weight'] = $lookup->weight;
			$row_item['quantity'] = $lookup->quantity;
		  	$array_item[] = $row_item;
		}
		$item_details = $array_item;
		
		$curl	= curl_init();
		$url_api = "https://api.biteship.com/v1/rates/couriers";
		$data	= ["origin_area_id" => $origin_area_id,
				   "destination_area_id" => $destination_area_id,
				   "couriers" => $couriers,
				   "items" => $item_details,
				   //"items" =>  [[
						"name" => "Shoes",
						"description" => "Black colored size 45",
						"value" => "199000",
						"length" => "30",
						"width" => "15",
						"height" => "20",
						"weight" => "200",
						"quantity" => "2"
				   ]]//
				   ];
		
		curl_setopt($curl,CURLOPT_HTTPHEADER,array("Authorization: ".$this->token, 'Content-Type:application/json'));
		curl_setopt($curl,CURLOPT_CUSTOMREQUEST,"POST");
		curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($data));
		curl_setopt($curl,CURLOPT_URL,$url_api);
		curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,0);
		curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,0);
		$result=curl_exec($curl);
		curl_close($curl);
		$getdata=json_decode($result);
		//return json_encode($data);
		return $getdata;
		
	}
	*/
	
	// cek ongkir biteship
	public function cekOngkir(Request $request)
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

		$origin_area_id = $request->origin_area_id;
		$destination_area_id = $request->destination_area_id;
		$couriers = $request->couriers;
		$item_details = $request->items;
		$item_details =  json_decode($item_details);
		$array_item=array();
		foreach ($item_details as $key => $lookup) {
			$row_item = array();
			$row_item['name'] = $lookup->name;
			$row_item['description'] = $lookup->description;
			$row_item['value'] = $lookup->value;
			/*
			$row_item['length'] = $lookup->length;
			$row_item['width'] = $lookup->width;
			$row_item['height'] = $lookup->height;
			$row_item['weight'] = $lookup->weight;
			*/
			$row_item['length'] = 25;
			$row_item['width'] = 10;
			$row_item['height'] = 20;
			$row_item['weight'] = 800;
			$row_item['quantity'] = $lookup->quantity;
		  	$array_item[] = $row_item;
		}
		$item_details = $array_item;
		
		$curl	= curl_init();
		$url_api = "https://api.biteship.com/v1/rates/couriers";
		$data	= ["origin_area_id" => $origin_area_id,
				   "destination_area_id" => $destination_area_id,
				   "couriers" => $couriers,
				   "items" => $item_details,
				   ];
		
        $cache_key = 'biteship_rates_' . md5(json_encode($data));
        $result = \Illuminate\Support\Facades\Cache::get($cache_key);
        if (!$result) {
    		$curl	= curl_init();
    		curl_setopt($curl,CURLOPT_HTTPHEADER,array("Authorization: ".$this->token_live, 'Content-Type:application/json'));
    		curl_setopt($curl,CURLOPT_CUSTOMREQUEST,"POST");
    		curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
    		curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($data));
    		curl_setopt($curl,CURLOPT_URL,$url_api);
    		curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,0);
    		curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,0);
    		$result = curl_exec($curl);
    		curl_close($curl);
            
            $check = json_decode($result);
            if ($check && !empty($check->success)) {
                \Illuminate\Support\Facades\Cache::put($cache_key, $result, 300);
            }
        }
		$getdata=json_decode($result);
		//return json_encode($data);
		//return $getdata;
		$arr_courier = DB::table("couriers")->where("cr_store","=","1")->first();
		if($getdata->success == true){
			//echo $getdata->success;
			$pricing = array();
			$nom=0;
			foreach($getdata->pricing as $data_price){
				$nom++;
				if($nom == 1){
				// pilihan ambil sendiri di toko
				$pricing[] = [
					"available_collection_method" => ['pickup'],
					"available_for_cash_on_delivery" => $data_price->available_for_cash_on_delivery,
					"available_for_proof_of_delivery" => $data_price->available_for_proof_of_delivery,
					"available_for_instant_waybill_id" => $data_price->available_for_instant_waybill_id,
					"available_for_insurance" => $data_price->available_for_insurance,
					"company" => "Topscore",
					//"courier_name" => "AMBIL DI TOKO",
					//"courier_code" => "ambil di toko",
					"courier_name" => $arr_courier->cr_name,
					"courier_code" => strtolower($arr_courier->cr_name),
					"courier_service_name" => "",
					"courier_service_code" => "adt",
					"description" => "Layanan ambil sendiri di toko topscore",
					"duration" => "1 days",
					"shipment_duration_range" => "1",
					"shipment_duration_unit" => "days",
					"service_type" => "standar",
					"shipping_type" => "parcel",
					"price" => 0,
					"type" => "customer",
				];	
				}
				$pricing[] = [
					"available_collection_method" => $data_price->available_collection_method,
					"available_for_cash_on_delivery" => $data_price->available_for_cash_on_delivery,
					"available_for_proof_of_delivery" => $data_price->available_for_proof_of_delivery,
					"available_for_instant_waybill_id" => $data_price->available_for_instant_waybill_id,
					"available_for_insurance" => $data_price->available_for_insurance,
					"company" => $data_price->company,
					"courier_name" => $data_price->courier_name,
					"courier_code" => $data_price->courier_code,
					"courier_service_name" => $data_price->courier_service_name,
					"courier_service_code" => $data_price->courier_service_code,
					"description" => $data_price->description,
					"duration" => $data_price->duration,
					"shipment_duration_range" => $data_price->shipment_duration_range,
					"shipment_duration_unit" => $data_price->shipment_duration_unit,
					"service_type" => $data_price->service_type,
					"shipping_type" => $data_price->shipping_type,
					"price" => $data_price->price,
					"type" => $data_price->type,
				];
			}
			
			$data_json = [
				"success" => $getdata->success,
				"object" => $getdata->object,
				"message" => $getdata->message,
				"code" => $getdata->code,
				"origin" => $getdata->origin,
				"stops" => $getdata->stops,
				"destination" => $getdata->destination,
				"pricing" => $pricing,
			];
			return response()->json($data_json);
		} else {
			return $getdata;
		}
	}
	
	// new tracking kusus JNE
	private function shipmentracking_new($shipping_number, $courier, $pt_id)
	{
	    $cust_phone = '12345';
	    $pos = DB::table("pos_transactions")->select('id','cust_id', 'pos_address_id', 'pos_status')->where('id',$pt_id)->first();
	    if($pos){
	        $cust = DB::table("customer_address")->select('id','ca_phone')->where('id',$pos->pos_address_id)->first();
	        if($cust->ca_phone){
	            $cust_phone = substr($cust->ca_phone,-5);
	        }
	    }
	    
        $api = DB::table('web_configs')->select('config_value')->where('config_name', 'ro_api')->get()->first()->config_value;        
        $last5 = $cust_phone;
		
        if (!empty($courier)) {
            $curl = curl_init();
            curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.binderbyte.com/v1/track?api_key=$api&courier=$courier&awb=$shipping_number&number={$last5}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET"));
            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);
            if ($err) {
                return "cURL Error #:" . $err;
            } else {
                //return json_decode($response, true);
                //$getdata = json_decode($response, true);
                //return $getdata['status'];
                
                $getdata = json_decode($response, true);
                //return json_decode($response, true);
				// jika respon == 200
				
				$json_respon = [];
				// tampilkan history data
				if($getdata['status'] == '200'){
					$json_history = array();
					foreach($getdata['data']['history'] as $history){
						$json_history[] = [
							"note"=> $history['desc'],
							"updated_at"=> $history['date'],
							"status"=> $history['location'],
						];
					}
					$json_respon = [
						"success"=> true,
						"messsage"=> $getdata['message'],
						"object"=> 'tracking',
						"id"=> $getdata['data']['summary']['awb'],
						"waybill_id"=> $getdata['data']['summary']['awb'],
						"courier"=> [
							"company"=> $getdata['data']['summary']['courier'],
							"name"=> null,
							"phone"=> null,
							"driver_name"=> null,
							"driver_phone"=> null,
							"driver_plate_number"=> null,
							"driver_photo_url"=> null,
						],
						"origin"=> [
							"contact_name"=> $getdata['data']['detail']['shipper'],
							"address"=> $getdata['data']['detail']['origin'],
						],
						"destination"=> [
							"contact_name"=> $getdata['data']['detail']['receiver'],
							"address"=> $getdata['data']['detail']['destination'],
						],
						"history" => $json_history,
						"link"=> null,
						"order_id"=> null,
						"status"=> strtolower($getdata['data']['summary']['status']),
						"invoice_status"=> $pos->pos_status,
					];
				} else {
					$json_respon = [
						"success"=> false,
						"messsage"=> 'data tidak ditemukan!',
					];
				}
				
				return response()->json($json_respon);
                // END
                
            }
        }		
	}
	
	// tracking orderan 
	public function TrackingOrder(Request $request)
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

		$no_resi = $request->no_resi;
		//$couriers = $request->couriers;
		$couriers = strtolower($request->couriers);
		if(strtolower($couriers) == 'j&t'){$couriers = "JNT";} else {$couriers=$couriers;}
		
		$arr_pos = DB::table('pos_transactions')->where("pos_shipping_number","=",$no_resi)->first();
		if($arr_pos){
    		$arr_pos_couriers = DB::table('pos_courier_orders')->where("pt_id","=",$arr_pos->id)->where("pco_active","=","1")->orderBy("id","DESC")->first();
    		if($arr_pos_couriers){
    		//if(1==1){
    			$pco_order_id = $arr_pos_couriers->pco_order_id;
    			//$pco_order_id = '66fb7e8d4bdd8b0013dc2568';
    			$cek_order_biteship = $this->checkOrder_biteship($pco_order_id);
    			return $cek_order_biteship;
    		}
		}
		
		
		// kusus JNE 
		if(strtoupper($couriers) == "JNE"){
			$log_ekspedisi = $this->shipmentracking_new($no_resi, $couriers, $arr_pos->id);
			return $log_ekspedisi;
		}
		
		$curl	= curl_init();
		$url_api = "https://api.biteship.com/v1/trackings/".$no_resi."/couriers/".$couriers;
		$data	= [];
		
		curl_setopt($curl,CURLOPT_HTTPHEADER,array("Authorization: ".$this->token_live, 'Content-Type:application/json'));
		curl_setopt($curl,CURLOPT_CUSTOMREQUEST,"GET");
		curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($data));
		curl_setopt($curl,CURLOPT_URL,$url_api);
		curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,0);
		curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,0);
		$result=curl_exec($curl);
		curl_close($curl);
		$getdata=json_decode($result);
		//return json_encode($data);
		//return $getdata; // ok tampilkan data json
		//return $url_api;
		if($getdata->success==1){
			// cek invoice 
			//$arr_pos = DB::table('pos_transactions')->where("no_resi_expedisi","=",$no_resi)->first();
			$arr_pos = DB::table('pos_transactions')->where("pos_shipping_number","=",$no_resi)->first();
			if($arr_pos){
				$status_invoice = $arr_pos->pos_status;
			} else {
				$status_invoice = "";
			}
			// tampilkan history data
			$json_history = array();
			foreach($getdata->history as $history){
				$json_history[] = [
					"note"=> $history->note,
					"updated_at"=> $history->updated_at,
					"status"=> $history->status,
				];
			}
			$json_respon = [
			"success"=> true,
			"messsage"=> $getdata->messsage,
			"object"=> $getdata->object,
			"id"=> $getdata->id,
			"waybill_id"=> $getdata->waybill_id,
			"courier"=> [
				"company"=> $getdata->courier->company,
				"name"=> $getdata->courier->name,
				"phone"=> $getdata->courier->phone,
				"driver_name"=> $getdata->courier->driver_name,
				"driver_phone"=> $getdata->courier->driver_phone,
				"driver_plate_number"=> $getdata->courier->driver_plate_number,
				"driver_photo_url"=> $getdata->courier->driver_photo_url,
			],
			"origin"=> [
				"contact_name"=> $getdata->origin->contact_name,
				"address"=> $getdata->origin->address,
			],
			"destination"=> [
				"contact_name"=> $getdata->destination->contact_name,
				"address"=> $getdata->destination->address,
			],
			/*
			"history"=> [
				array(
					"note"=> "Item has been picked and ready to be shipped.",
					"updated_at"=> "2024-07-20T00:46:00+07:00",
					"status"=> "picked"
				),
				array(
					"note"=> "RECEIVED AT SORTING CENTER [DEPOK]",
					"updated_at"=> "2024-07-20T01:45:00+07:00",
					"status"=> "dropping_off"
				),
			],
			*/
			"history" => $json_history,
			//"link"=> $getdata->link,
			"link"=> optional($getdata)->link ?? null,
			"order_id"=> $getdata->order_id,
			"status"=> $getdata->status,
			"invoice_status"=> $status_invoice,
			];
			return response()->json($json_respon);
		} else {
			return $getdata;
		}
	}
    
    // ongkir instant
	public function cekOngkirInstant(Request $request)
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

		$origin = str_replace(" ","",$request->origin);
		$destination = str_replace(" ","",$request->destination);
		
		$origin_array = explode(",",$origin);
		$destination_array = explode(",",$destination);
		
		//$couriers = $request->couriers;
		//$couriers = "gojek,jne,jnt,grab,pos,sicepat";
		//$couriers = "gojek,jne,jnt,grab,lalamove";
		//$couriers = "Lalamove";
		//$couriers = "gojek,jne,jnt,grab";
		$couriers = "gojek,jne,jnt,grab";
		$item_details = $request->items;
		$item_details =  json_decode($item_details);
		
		// cek kusus produk jersey running IPB *** tambahkan pengambilan di lokasi acara *** - RPC DAY (H-2) - RACE DAY (H)
		$jersey_ipb=0; $all_produk = 0;
		
		$array_item=array();
		foreach ($item_details as $key => $lookup) {
		    $all_produk++;
		    // cek produk sesuai nama artikel
			$arr_product = DB::table("products")->where(['p_name' => $lookup->name, 'p_delete' => '0'])->first();
			if($arr_product){ 
			    $p_weight=$arr_product->p_weight; 
			    //if(in_array($arr_product->id,['13797','13798','13799','13800', '13814', '13815'])){ $jersey_ipb++; }
			} else {$p_weight='800';}
			
			$row_item = array();
			$row_item['name'] = $lookup->name;
			$row_item['description'] = $lookup->description;
			$row_item['value'] = $lookup->value;
			/*
			$row_item['length'] = $lookup->length;
			$row_item['width'] = $lookup->width;
			$row_item['height'] = $lookup->height;
			$row_item['weight'] = $lookup->weight;
			*/
			$row_item['length'] = 25;
			$row_item['width'] = 10;
			$row_item['height'] = 20;
			$row_item['weight'] = $p_weight;
			$row_item['quantity'] = $lookup->quantity;
		  	$array_item[] = $row_item;
		}
		$item_details = $array_item;
		
		$curl	= curl_init();
		$url_api = "https://api.biteship.com/v1/rates/couriers";
		$data	= [
				   "origin_latitude" => $origin_array[0],
				   "origin_longitude" => $origin_array[1], 
				   "destination_latitude" => $destination_array[0],
				   "destination_longitude" => $destination_array[1],
				   "couriers" => $couriers,
				   "items" => $item_details,
				   ];
        $cache_key = 'biteship_rates_instant_' . md5(json_encode($data));
        $result = \Illuminate\Support\Facades\Cache::get($cache_key);
        if (!$result) {
    		$curl	= curl_init();
    		curl_setopt($curl,CURLOPT_HTTPHEADER,array("Authorization: ".$this->token_live, 'Content-Type:application/json'));
    		curl_setopt($curl,CURLOPT_CUSTOMREQUEST,"POST");
    		curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
    		curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($data));
    		curl_setopt($curl,CURLOPT_URL,$url_api);
    		curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,0);
    		curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,0);
    		$result = curl_exec($curl);
    		curl_close($curl);

            $check = json_decode($result);
            if ($check && !empty($check->success)) {
                \Illuminate\Support\Facades\Cache::put($cache_key, $result, 300);
            }
        }
		$getdata=json_decode($result);
		//return json_encode($data);
		//return $getdata; // tampilkan data json dari API biteship
		
		$arr_courier = DB::table("couriers")->where("cr_store","=","1")->first();
		//if($getdata->success == true){
		if (optional($getdata)->success) {
			$pricing = array();
			$nom=0;
			foreach($getdata->pricing as $data_price){
				$nom++;
				if($nom == 1){
				// pilihan ambil sendiri di toko
				$pricing[] = [
					"available_collection_method" => ['pickup'],
					"available_for_cash_on_delivery" => $data_price->available_for_cash_on_delivery,
					"available_for_proof_of_delivery" => $data_price->available_for_proof_of_delivery,
					"available_for_instant_waybill_id" => $data_price->available_for_instant_waybill_id,
					"available_for_insurance" => $data_price->available_for_insurance,
					"company" => "Topscore",
					"courier_name" => $arr_courier->cr_name, //"AMBIL DI TOKO",
					"courier_code" => strtolower($arr_courier->cr_name), //"ambil di toko",
					"courier_service_name" => "",
					"courier_service_code" => "",
					"description" => "Layanan ambil sendiri di toko topscore",
					"duration" => "1 days",
					"shipment_duration_range" => "1",
					"shipment_duration_unit" => "days",
					"service_type" => "standar",
					"shipping_type" => "parcel",
					"price" => 0,
					"type" => "customer",
				];
				
				if($jersey_ipb>0 && ($all_produk == $jersey_ipb)){
				//if(1==1){
					$pricing[] = [
						"available_collection_method" => ['pickup'],
						"available_for_cash_on_delivery" => $data_price->available_for_cash_on_delivery,
						"available_for_proof_of_delivery" => $data_price->available_for_proof_of_delivery,
						"available_for_instant_waybill_id" => $data_price->available_for_instant_waybill_id,
						"available_for_insurance" => $data_price->available_for_insurance,
						"company" => "Topscore",
						"courier_name" => "RPC DAY", //"AMBIL DI TOKO",
						"courier_code" => strtolower("RPC DAY"), //"ambil di toko",
						"courier_service_name" => "",
						"courier_service_code" => "",
						"description" => "Layanan ambil sendiri di acara event",
						"duration" => "1 days",
						"shipment_duration_range" => "1",
						"shipment_duration_unit" => "days",
						"service_type" => "standar",
						"shipping_type" => "parcel",
						"price" => 0,
						"type" => "customer",
					];
					$pricing[] = [
						"available_collection_method" => ['pickup'],
						"available_for_cash_on_delivery" => $data_price->available_for_cash_on_delivery,
						"available_for_proof_of_delivery" => $data_price->available_for_proof_of_delivery,
						"available_for_instant_waybill_id" => $data_price->available_for_instant_waybill_id,
						"available_for_insurance" => $data_price->available_for_insurance,
						"company" => "Topscore",
						"courier_name" => "RACE DAY",
						"courier_code" => strtolower("RACE DAY"),
						"courier_service_name" => "",
						"courier_service_code" => "",
						"description" => "Layanan ambil sendiri di acara event",
						"duration" => "1 days",
						"shipment_duration_range" => "1",
						"shipment_duration_unit" => "days",
						"service_type" => "standar",
						"shipping_type" => "parcel",
						"price" => 0,
						"type" => "customer",
					];
				}
				
				}
				
				// jika sameday hanya dari jam 08:00 sd 15:00
				if($data_price->courier_service_code == "same_day" && date("H")<='14'){$show_pricing='1';} else
				if($data_price->courier_service_code == "same_day" && date("H")>'14'){$show_pricing='0';} else {$show_pricing='1';}
				
				// cek kurir lalamove hanya motor dan grab selain car
				if($data_price->courier_code =='grab' && $data_price->courier_service_code =='instant_car'){$show_pricing='0';} else
				if($data_price->courier_code =='lalamove' && $data_price->courier_service_code =='mpv'){$show_pricing='0';} else
				{$show_pricing=$show_pricing;}
				
				// if($jersey_ipb>0 && ($all_produk == $jersey_ipb)){
				if($show_pricing==1 && ($jersey_ipb==0) ){
				$pricing[] = [
					"available_collection_method" => $data_price->available_collection_method,
					"available_for_cash_on_delivery" => $data_price->available_for_cash_on_delivery,
					"available_for_proof_of_delivery" => $data_price->available_for_proof_of_delivery,
					"available_for_instant_waybill_id" => $data_price->available_for_instant_waybill_id,
					"available_for_insurance" => $data_price->available_for_insurance,
					"company" => $data_price->company,
					"courier_name" => $data_price->courier_name,
					"courier_code" => $data_price->courier_code,
					"courier_service_name" => $data_price->courier_service_name,
					"courier_service_code" => $data_price->courier_service_code,
					"description" => $data_price->description,
					"duration" => $data_price->duration,
					"shipment_duration_range" => $data_price->shipment_duration_range,
					"shipment_duration_unit" => $data_price->shipment_duration_unit,
					"service_type" => $data_price->service_type,
					"shipping_type" => $data_price->shipping_type,
					"price" => (int)round($data_price->price),
					"type" => $data_price->type,
				];
				}
			}
			
			// getdata->destination
			
			$getdestination = [
				"location_id" =>  $getdata->destination->location_id,
				"latitude" =>  $getdata->destination->latitude,
				"longitude" => $getdata->destination->longitude,
				"postal_code" => $getdata->destination->postal_code,
				"country_name" => $getdata->destination->country_name,
				"country_code" => $getdata->destination->country_code,
				"administrative_division_level_1_name" => $getdata->destination->administrative_division_level_1_name,
				"administrative_division_level_1_type" => $getdata->destination->administrative_division_level_1_type,
				"administrative_division_level_2_name" => $getdata->destination->administrative_division_level_2_name,
				"administrative_division_level_2_type" => $getdata->destination->administrative_division_level_2_type,
				"administrative_division_level_3_name" => $getdata->destination->administrative_division_level_3_name,
				"administrative_division_level_3_type" => $getdata->destination->administrative_division_level_3_type,
				"administrative_division_level_4_name" => $getdata->destination->administrative_division_level_4_name,
				"administrative_division_level_4_type" => $getdata->destination->administrative_division_level_4_type,
				"address" => null,
			];
			
			
			$data_json = [
				"success" => $getdata->success,
				"object" => $getdata->object,
				"message" => $getdata->message,
				"code" => $getdata->code,
				"origin" => $getdata->origin,
				"stops" => $getdata->stops,
			    //"destination" => $getdata->destination,
				"destination" => $getdestination,
				//'a' => $getdata->destination->latitude,
				"pricing" => $pricing,
				
			];
			return response()->json($data_json);
		} else {
			return $getdata;
		}
	}
    
    public function submitOrder_default(Request $request)
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
		
		$data_order = $request->data_order;
		$pos_id = $request->pos_id;
		if(empty($data_order) || empty($pos_id)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field data_order dan pos_id tidak boleh kosong'
                ]
            ];
            return response()->json($data);
		}
				
		$master_order =  json_decode($data_order);
		
		$data = [];
		foreach ($master_order as $key => $lookup) {
			$detail_items = [];
			foreach ($lookup->items as $key2 => $lookup2) {
				$detail_items = [
					[
					"name" => $lookup2->name,
					"description" => $lookup2->description,
					"category" => $lookup2->category,
					"value" => $lookup2->value,
					"quantity" => $lookup2->quantity,
					"height" => $lookup2->height,
					"length" => $lookup2->length,
					"weight" => $lookup2->weight,
					"width" => $lookup2->width,
					],
				];
			}
			$data	= 
			[
				"shipper_contact_name" => $lookup->shipper_contact_name,
				"shipper_contact_phone" => $lookup->shipper_contact_phone,
				"shipper_contact_email" => $lookup->shipper_contact_email,
				"shipper_organization" => $lookup->shipper_organization,
				"origin_contact_name" => $lookup->origin_contact_name,
				"origin_contact_phone" => $lookup->origin_contact_phone,
				"origin_address" => $lookup->origin_address,
				"origin_note" => $lookup->origin_note,
				"origin_postal_code" => $lookup->origin_postal_code,
				"destination_contact_name" => $lookup->destination_contact_name,
				"destination_contact_phone" => $lookup->destination_contact_phone,
				"destination_contact_email" => $lookup->destination_contact_email,
				"destination_address" => $lookup->destination_address,
				"destination_note" => $lookup->destination_note,
				"destination_postal_code" => $lookup->destination_postal_code,
				"courier_company" => $lookup->courier_company,
				"courier_type" => $lookup->courier_type,
				"courier_insurance" => $lookup->courier_insurance,
				"delivery_type" => $lookup->delivery_type,
				"order_note" => $lookup->order_note,
				"metadata" => [],
				"items" => $detail_items,
			];
		}

		$curl	= curl_init();
		$url_api = "https://api.biteship.com/v1/orders";
		curl_setopt($curl,CURLOPT_HTTPHEADER,array("Authorization: ".$this->token_live, 'Content-Type:application/json'));
		curl_setopt($curl,CURLOPT_CUSTOMREQUEST,"POST");
		curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($data));
		curl_setopt($curl,CURLOPT_URL,$url_api);
		curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,0);
		curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,0);
		$result=curl_exec($curl);
		curl_close($curl);
		$getdata=json_decode($result);
		//return $getdata;
		
		$respon_data = array();
		if($getdata->success == true){
			// save log
			$data_save = [
				'pt_id' => $pos_id,
				'pco_order_id' => $getdata->id,
				'pco_type' => 'standard',
				'pco_courier' => $getdata->courier->company,
				'pco_service' => $getdata->courier->type,
				'pco_price' => $getdata->price,
				'json_order' => $result,
				'pco_status' => $getdata->status,
				'pco_resi' => $getdata->courier->waybill_id,
				
				'origin_contact_name' => $data['origin_contact_name'],
				'origin_contact_phone' => $data['origin_contact_phone'],
				'origin_address' => $data['origin_address'],
				'origin_note' => $data['origin_note'],
				'origin_postal_code' => $data['origin_postal_code'],
				'destination_contact_name' => $data['destination_contact_name'],
				'destination_contact_phone' => $data['destination_contact_phone'],
				'destination_contact_email' => $data['destination_contact_email'],
				'destination_address' => $data['destination_address'],
				'destination_note' => $data['destination_note'],
				'destination_postal_code' => $data['destination_postal_code'],
				'json_post' => $request->data_order,
				
				'created_at' => date("Y-m-d H:i:s"),
			];
			$saves = DB::table("pos_courier_orders")->insertGetId(array_merge($data_save));
		}
		//return response()->json($respon_data);
		return $getdata;
	}
	
	
	
	// create instan GRAB/GOJEK
	/*
	public function submitOrder_instant(Request $request)
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

		$data_order = $request->data_order;
		$pos_id = $request->pos_id;
		if(empty($data_order) || empty($pos_id)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field data_order dan pos_id tidak boleh kosong'
                ]
            ];
            return response()->json($data);
		}
				
		$master_order =  json_decode($data_order);
		
		$data = [];
		foreach ($master_order as $key => $lookup) {
			$detail_items = [];
			foreach ($lookup->items as $key2 => $lookup2) {
				$detail_items = [
					[
					"name" => $lookup2->name,
					"description" => $lookup2->description,
					"category" => $lookup2->category,
					"value" => $lookup2->value,
					"quantity" => $lookup2->quantity,
					"height" => $lookup2->height,
					"length" => $lookup2->length,
					"weight" => $lookup2->weight,
					"width" => $lookup2->width,
					],
				];
			}
			$data	= 
			[
				"shipper_contact_name" => $lookup->shipper_contact_name,
				"shipper_contact_phone" => $lookup->shipper_contact_phone,
				"shipper_contact_email" => $lookup->shipper_contact_email,
				"shipper_organization" => $lookup->shipper_organization,
				"origin_contact_name" => $lookup->origin_contact_name,
				"origin_contact_phone" => $lookup->origin_contact_phone,
				"origin_address" => $lookup->origin_address,
				"origin_note" => $lookup->origin_note,
				"origin_coordinate" => [
					"latitude" => $lookup->origin_coordinate->latitude,
					"longitude" => $lookup->origin_coordinate->longitude
				],
				//"origin_postal_code" => $lookup->origin_postal_code,
				"destination_contact_name" => $lookup->destination_contact_name,
				"destination_contact_phone" => $lookup->destination_contact_phone,
				"destination_contact_email" => $lookup->destination_contact_email,
				"destination_address" => $lookup->destination_address,
				"destination_note" => $lookup->destination_note,
				"destination_coordinate" => [
					"latitude" => $lookup->destination_coordinate->latitude,
					"longitude" => $lookup->destination_coordinate->longitude,
				],
				//"destination_postal_code" => $lookup->destination_postal_code,
				"courier_company" => $lookup->courier_company,
				"courier_type" => $lookup->courier_type,
				"courier_insurance" => $lookup->courier_insurance,
				"delivery_type" => $lookup->delivery_type,
				"order_note" => $lookup->order_note,
				"metadata" => [],
				"items" => $detail_items,
			];
		}
		
		$curl	= curl_init();
		$url_api = "https://api.biteship.com/v1/orders";
		//curl_setopt($curl,CURLOPT_HTTPHEADER,array("Authorization: ".$this->token_live, 'Content-Type:application/json'));
		curl_setopt($curl,CURLOPT_HTTPHEADER,array("Authorization: ".$this->token, 'Content-Type:application/json'));
		curl_setopt($curl,CURLOPT_CUSTOMREQUEST,"POST");
		curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($data));
		curl_setopt($curl,CURLOPT_URL,$url_api);
		curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,0);
		curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,0);
		$result=curl_exec($curl);
		curl_close($curl);
		$getdata=json_decode($result);

		$respon_data = array();
		if($getdata->success == true){
			// save log
			$data_save = [
				'pt_id' => $pos_id,
				'pco_order_id' => $getdata->id,
				'pco_type' => 'instant',
				'pco_courier' => $getdata->courier->company,
				'pco_service' => $getdata->courier->type,
				'pco_price' => $getdata->price,
				'json_order' => $result,
				'pco_status' => $getdata->status,
				'pco_resi' => $getdata->courier->waybill_id,
				
				'origin_contact_name' => $data['origin_contact_name'],
				'origin_contact_phone' => $data['origin_contact_phone'],
				'origin_address' => $data['origin_address'],
				'origin_note' => $data['origin_note'],
				//'origin_postal_code' => $data['origin_postal_code'],
				"origin_coordinate" => [
					"latitude" => $lookup->origin_coordinate->latitude,
					"longitude" => $lookup->origin_coordinate->longitude
				],
				'destination_contact_name' => $data['destination_contact_name'],
				'destination_contact_phone' => $data['destination_contact_phone'],
				'destination_contact_email' => $data['destination_contact_email'],
				'destination_address' => $data['destination_address'],
				'destination_note' => $data['destination_note'],
				//'destination_postal_code' => $data['destination_postal_code'],
				"destination_coordinate" => [
					"latitude" => $lookup->destination_coordinate->latitude,
					"longitude" => $lookup->destination_coordinate->longitude,
				],
				'json_post' => $request->data_order,
				
				'created_at' => date("Y-m-d H:i:s"),
			];
			$saves = DB::table("pos_courier_orders")->insertGetId(array_merge($data_save));
		}
		//return response()->json($respon_data);
		return $getdata;	
	}
	*/
	
	public function submitOrder_instant(Request $request)
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

		$data_order = $request->data_order;
		$pos_id = $request->pos_id;
		if(empty($data_order) || empty($pos_id)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field data_order dan pos_id tidak boleh kosong'
                ]
            ];
            return response()->json($data);
		}
				
		$master_order =  json_decode($data_order);
		
		$data = [];
		foreach ($master_order as $key => $lookup) {
			$detail_items = [];
			foreach ($lookup->items as $key2 => $lookup2) {
				$detail_items = [
					[
					"name" => $lookup2->name,
					"description" => $lookup2->description,
					"category" => $lookup2->category,
					"value" => $lookup2->value,
					"quantity" => $lookup2->quantity,
					"height" => $lookup2->height,
					"length" => $lookup2->length,
					"weight" => $lookup2->weight,
					"width" => $lookup2->width,
					],
				];
			}
			$data	= 
			[
				"shipper_contact_name" => $lookup->shipper_contact_name,
				"shipper_contact_phone" => $lookup->shipper_contact_phone,
				"shipper_contact_email" => $lookup->shipper_contact_email,
				"shipper_organization" => $lookup->shipper_organization,
				"origin_contact_name" => $lookup->origin_contact_name,
				"origin_contact_phone" => $lookup->origin_contact_phone,
				"origin_address" => $lookup->origin_address,
				"origin_note" => $lookup->origin_note,
				"origin_coordinate" => [
					"latitude" => $lookup->origin_coordinate->latitude,
					"longitude" => $lookup->origin_coordinate->longitude
				],
				//"origin_postal_code" => $lookup->origin_postal_code,
				//"origin_postal_code" => $lookup->origin_coordinate->latitude.', '.$lookup->origin_coordinate->longitude,
				"destination_contact_name" => $lookup->destination_contact_name,
				"destination_contact_phone" => $lookup->destination_contact_phone,
				"destination_contact_email" => $lookup->destination_contact_email,
				"destination_address" => $lookup->destination_address,
				"destination_note" => $lookup->destination_note,
				"destination_coordinate" => [
					"latitude" => $lookup->destination_coordinate->latitude,
					"longitude" => $lookup->destination_coordinate->longitude,
				],
				//"destination_postal_code" => $lookup->destination_postal_code,
				//"destination_postal_code" => $lookup->destination_coordinate->latitude.', '.$lookup->destination_coordinate->longitude,
				"courier_company" => $lookup->courier_company,
				"courier_type" => $lookup->courier_type,
				"courier_insurance" => $lookup->courier_insurance,
				"delivery_type" => $lookup->delivery_type,
				"order_note" => $lookup->order_note,
				"metadata" => [],
				"items" => $detail_items,
			];
		}
		
		$curl	= curl_init();
		$url_api = "https://api.biteship.com/v1/orders";
		curl_setopt($curl,CURLOPT_HTTPHEADER,array("Authorization: ".$this->token_live, 'Content-Type:application/json'));
		//curl_setopt($curl,CURLOPT_HTTPHEADER,array("Authorization: ".$this->token, 'Content-Type:application/json'));
		curl_setopt($curl,CURLOPT_CUSTOMREQUEST,"POST");
		curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($data));
		curl_setopt($curl,CURLOPT_URL,$url_api);
		curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,0);
		curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,0);
		$result=curl_exec($curl);
		curl_close($curl);
		$getdata=json_decode($result);

		$respon_data = array();
		if($getdata->success == true){
			// save log
			$data_save = [
				'pt_id' => $pos_id,
				'pco_order_id' => $getdata->id,
				'pco_type' => 'instant',
				'pco_courier' => $getdata->courier->company,
				'pco_service' => $getdata->courier->type,
				'pco_price' => $getdata->price,
				'json_order' => $result,
				'pco_status' => $getdata->status,
				'pco_resi' => $getdata->courier->waybill_id,
				
				'origin_contact_name' => $data['origin_contact_name'],
				'origin_contact_phone' => $data['origin_contact_phone'],
				'origin_address' => $data['origin_address'],
				'origin_note' => $data['origin_note'],
				'origin_postal_code' => $data['origin_coordinate']['latitude'].', '.$data['origin_coordinate']['longitude'],
				'destination_contact_name' => $data['destination_contact_name'],
				'destination_contact_phone' => $data['destination_contact_phone'],
				'destination_contact_email' => $data['destination_contact_email'],
				'destination_address' => $data['destination_address'],
				'destination_note' => $data['destination_note'],
				'destination_postal_code' => $data['destination_coordinate']['latitude'].', '.$data['destination_coordinate']['longitude'],
				'json_post' => $request->data_order,
				
				'created_at' => date("Y-m-d H:i:s"),
			];
			$saves = DB::table("pos_courier_orders")->insertGetId(array_merge($data_save));
		}
		//return response()->json($respon_data);
		return $getdata;	
	}
	
	// COD
	public function submitOrder_cod(Request $request)
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
		
		$data_order = $request->data_order;
		$pos_id = $request->pos_id;
		if(empty($data_order) || empty($pos_id)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field data_order dan pos_id tidak boleh kosong'
                ]
            ];
            return response()->json($data);
		}
				
		$master_order =  json_decode($data_order);
		
		$data = [];
		foreach ($master_order as $key => $lookup) {
			$detail_items = [];
			foreach ($lookup->items as $key2 => $lookup2) {
				$detail_items = [
					[
					"name" => $lookup2->name,
					"description" => $lookup2->description,
					"category" => $lookup2->category,
					"value" => $lookup2->value,
					"quantity" => $lookup2->quantity,
					"height" => $lookup2->height,
					"length" => $lookup2->length,
					"weight" => $lookup2->weight,
					"width" => $lookup2->width,
					],
				];
			}
			$data	= 
			[
				"shipper_contact_name" => $lookup->shipper_contact_name,
				"shipper_contact_phone" => $lookup->shipper_contact_phone,
				"shipper_contact_email" => $lookup->shipper_contact_email,
				"shipper_organization" => $lookup->shipper_organization,
				"origin_contact_name" => $lookup->origin_contact_name,
				"origin_contact_phone" => $lookup->origin_contact_phone,
				"origin_address" => $lookup->origin_address,
				"origin_note" => $lookup->origin_note,
				"origin_postal_code" => $lookup->origin_postal_code,
				"destination_contact_name" => $lookup->destination_contact_name,
				"destination_contact_phone" => $lookup->destination_contact_phone,
				"destination_contact_email" => $lookup->destination_contact_email,
				"destination_address" => $lookup->destination_address,
				"destination_note" => $lookup->destination_note,
				"destination_postal_code" => $lookup->destination_postal_code,
				"destination_cash_on_delivery" => $lookup->destination_cash_on_delivery,
				"destination_cash_on_delivery_type" => $lookup->destination_cash_on_delivery_type,
				"courier_company" => $lookup->courier_company,
				"courier_type" => $lookup->courier_type,
				"courier_insurance" => $lookup->courier_insurance,
				"delivery_type" => $lookup->delivery_type,
				"order_note" => $lookup->order_note,
				"metadata" => [],
				"items" => $detail_items,
			];
		}
		
		$curl	= curl_init();
		$url_api = "https://api.biteship.com/v1/orders";
		curl_setopt($curl,CURLOPT_HTTPHEADER,array("Authorization: ".$this->token_live, 'Content-Type:application/json'));
		curl_setopt($curl,CURLOPT_CUSTOMREQUEST,"POST");
		curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($data));
		curl_setopt($curl,CURLOPT_URL,$url_api);
		curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,0);
		curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,0);
		$result=curl_exec($curl);
		curl_close($curl);
		$getdata=json_decode($result);

		$respon_data = array();
		if($getdata->success == true){
			// save log
			/*
			$data_save = [
				'pt_id' => $pos_id,
				'pco_order_id' => $getdata->id,
				'pco_type' => 'cod',
				'pco_courier' => $getdata->courier->company,
				'pco_service' => $getdata->courier->type,
				'pco_price' => $getdata->price,
				'json_order' => $result,
				'pco_status' => $getdata->status,
				'created_at' => date("Y-m-d H:i:s"),
			];
			$saves = DB::table("pos_courier_orders")->insertGetId(array_merge($data_save));
			*/
			// save log
			$data_save = [
				'pt_id' => $pos_id,
				'pco_order_id' => $getdata->id,
				'pco_type' => 'cod',
				'pco_courier' => $getdata->courier->company,
				'pco_service' => $getdata->courier->type,
				'pco_price' => $getdata->price,
				'json_order' => $result,
				'pco_status' => $getdata->status,
				'pco_resi' => $getdata->courier->waybill_id,
				
				'origin_contact_name' => $data['origin_contact_name'],
				'origin_contact_phone' => $data['origin_contact_phone'],
				'origin_address' => $data['origin_address'],
				'origin_note' => $data['origin_note'],
				'origin_postal_code' => $data['origin_postal_code'],
				'destination_contact_name' => $data['destination_contact_name'],
				'destination_contact_phone' => $data['destination_contact_phone'],
				'destination_contact_email' => $data['destination_contact_email'],
				'destination_address' => $data['destination_address'],
				'destination_note' => $data['destination_note'],
				'destination_postal_code' => $data['destination_postal_code'],
				'json_post' => $request->data_order,
				
				'created_at' => date("Y-m-d H:i:s"),
			];
			$saves = DB::table("pos_courier_orders")->insertGetId(array_merge($data_save));
		}
		//return response()->json($respon_data);
		return $getdata;	
	}
	
	// cek hostory order
	public function checkOrder(Request $request)
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

		$id_order = $request->order_id;
		$curl	= curl_init();
		$url_api = "https://api.biteship.com/v1/orders/".$id_order;
		$data	= [''];
		
		curl_setopt($curl,CURLOPT_HTTPHEADER,array("Authorization: ".$this->token_live, 'Content-Type:application/json'));
		curl_setopt($curl,CURLOPT_CUSTOMREQUEST,"GET");
		curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($data));
		curl_setopt($curl,CURLOPT_URL,$url_api);
		curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,0);
		curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,0);
		$result=curl_exec($curl);
		curl_close($curl);
		$getdata=json_decode($result);
		return $getdata;	
	}
    
    public function checkOrder_biteship($id_order)
    {
		$id_order = $id_order;
		$arr_pos_couriers = DB::table('pos_courier_orders')->where("pco_order_id","=",$id_order)->first();
		if($arr_pos_couriers){
			$curl	= curl_init();
			$url_api = "https://api.biteship.com/v1/orders/".$id_order;
			$data	= [''];
			
			curl_setopt($curl,CURLOPT_HTTPHEADER,array("Authorization: ".$this->token_live, 'Content-Type:application/json'));
			curl_setopt($curl,CURLOPT_CUSTOMREQUEST,"GET");
			curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
			curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($data));
			curl_setopt($curl,CURLOPT_URL,$url_api);
			curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,0);
			curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,0);
			$result=curl_exec($curl);
			curl_close($curl);
			$getdata=json_decode($result);
			//return $getdata;	
			if($getdata->success==1){
				$arr_pos = DB::table('pos_transactions')->where("id","=",$arr_pos_couriers->pt_id)->first();
				if($arr_pos){
					$status_invoice = $arr_pos->pos_status;
				} else {
					$status_invoice = "";
				}
				// tampilkan history data
				$json_history = array();
				foreach($getdata->courier->history as $history){
					$json_history[] = [
						"note"=> $history->note,
						"updated_at"=> $history->updated_at,
						"status"=> $history->status,
					];
				}
				$json_respon = [
					"success"=> true,
					"messsage"=> $getdata->message,
					"object"=> $getdata->object,
					"id"=> $getdata->id,
					"waybill_id"=> $getdata->courier->waybill_id,
					"courier"=> [
						"company"=> $getdata->courier->company,
						"name"=> $getdata->courier->name,
						"phone"=> $getdata->courier->phone,
						"driver_name"=> $getdata->courier->driver_name,
						"driver_phone"=> $getdata->courier->driver_phone,
						"driver_plate_number"=> $getdata->courier->driver_plate_number,
						"driver_photo_url"=> $getdata->courier->driver_photo_url,
					],
					"origin"=> [
						"contact_name"=> $getdata->origin->contact_name,
						"address"=> $getdata->origin->address,
					],
					"destination"=> [
						"contact_name"=> $getdata->destination->contact_name,
						"address"=> $getdata->destination->address,
					],
					"history" => $json_history,
					"link"=> $getdata->courier->link,
					//"order_id"=> $getdata->id,
					"order_id"=> null,
					"status"=> $getdata->status,
					"invoice_status"=> $status_invoice,
				];
				
				return response()->json($json_respon);
			}
			else {
				$json_respon = [
					"success"=> false,
					"messsage"=> 'Data tidak ditemukan a',
				];
				return response()->json($json_respon);
			}
		
		} else {
			$json_respon = [
				"success"=> false,
				"messsage"=> 'Data tidak ditemukan b',
			];
			return response()->json($json_respon);
		}
	}
    
    public function callbackOrder(Request $request)
    {
        $json = file_get_contents('php://input');
		$data_json_posting = json_decode(mb_convert_encoding($json, 'UTF-8', 'ISO-8859-1'));
		$json_parsing = json_encode($data_json_posting);
		/*
		$data_jsoncallback = [
			'callback' => $json_parsing,
			'created_at' => date("Y-m-d H:i:s"),
		];
		$get_idsave = DB::table("callback_xendit")->insertGetId(array_merge($data_jsoncallback));
		*/
		// save log
		$data_save = [
				'pcc_json_callback' => $json_parsing,
				'pcc_order_id' => $data_json_posting->order_id,
				'pcc_resi' => $data_json_posting->courier_waybill_id,
				'pcc_courier_company' => $data_json_posting->courier_company,
				'pcc_courier_type' => $data_json_posting->courier_type,
				'pcc_courier_driver_name' => $data_json_posting->courier_driver_name,
				'pcc_courier_driver_phone' => $data_json_posting->courier_driver_phone,
				'pcc_courier_driver_plate_number' => $data_json_posting->courier_driver_plate_number,
				'pcc_courier_tracking_id' => $data_json_posting->courier_tracking_id,
				'pcc_order_price' => $data_json_posting->order_price,
				'pcc_status' => $data_json_posting->status,
				'created_at' => date("Y-m-d H:i:s"),
		];
		$saves = DB::table("pos_courier_callbacks")->insertGetId(array_merge($data_save));
		
		// jika sudah terkirim = delivered
		if($data_json_posting->status == "delivered"){
				$arr_poscourier = DB::table("pos_courier_orders")->where("pco_order_id","=",$data_json_posting->order_id)->orderBy("id","DESC")->first();
				if($arr_poscourier){
    				// update status invoice
    				$update = DB::table("pos_transactions")->where('id', '=', $arr_poscourier->pt_id)->update([
    					'pos_status' => "DONE",
    					'updated_at' => date('Y-m-d H:i:s')
    				]);
				}
		}
		
        echo 200;
    }
    
    // batalkan pesanan 
	public function cancelBiteship(Request $request)
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
				//"success"=> false,
				//"messsage"=> 'Credential id dan api key tidak valid',
                'status' => [
                    'code' => 401,
                    'description' => 'Credential id dan api key tidak valid'
                ]
            ];
            return response()->json($data);
        }

		$id_order = $request->order_id;
		$note = $request->note;
		if(empty($id_order) || empty($note)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'ID Order dan Note tidak boleh kosong!'
                ]
            ];
            return response()->json($data);
		}

		$arr_pos_couriers = DB::table('pos_courier_orders')->where("pco_order_id","=",$id_order)->first();
		if($arr_pos_couriers){
			$curl	= curl_init();
			$url_api = "https://api.biteship.com/v1/orders/".$id_order;
			$data	= ['cancellation_reason' => $note];
			
			curl_setopt($curl,CURLOPT_HTTPHEADER,array("Authorization: ".$this->token_live, 'Content-Type:application/json'));
			//curl_setopt($curl,CURLOPT_HTTPHEADER,array("Authorization: ".$this->token, 'Content-Type:application/json'));
			curl_setopt($curl,CURLOPT_CUSTOMREQUEST,"DELETE");
			curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
			curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($data));
			curl_setopt($curl,CURLOPT_URL,$url_api);
			curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,0);
			curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,0);
			$result=curl_exec($curl);
			curl_close($curl);
			$getdata=json_decode($result);
			//return $getdata;
			if($getdata->success==1){
				$update = DB::table("pos_courier_orders")->where('id', '=', $arr_pos_couriers->id)->update([
					'pco_active' => "0",
					'updated_at' => date('Y-m-d H:i:s')
				]);
				
				// update pos transactions
				$update_pos = DB::table("pos_transactions")->where('id', '=', $arr_pos_couriers->pt_id)->update([
					'pos_shipping_number' => NULL,
					'no_resi_expedisi' => NULL,
					//'updated_at' => date('Y-m-d H:i:s')
				]);
				
				/*
				$json_respon = [
					"success"=> true,
					"messsage"=> $getdata->message,
					"object"=> $getdata->object,
					"id"=> $getdata->id,
					"waybill_id"=> $getdata->courier->waybill_id,
					"courier"=> [
						"company"=> $getdata->courier->company,
						"name"=> $getdata->courier->name,
						"phone"=> $getdata->courier->phone,
						"driver_name"=> $getdata->courier->driver_name,
						"driver_phone"=> $getdata->courier->driver_phone,
						"driver_plate_number"=> $getdata->courier->driver_plate_number,
						"driver_photo_url"=> $getdata->courier->driver_photo_url,
					],
					"origin"=> [
						"contact_name"=> $getdata->origin->contact_name,
						"address"=> $getdata->origin->address,
					],
					"destination"=> [
						"contact_name"=> $getdata->destination->contact_name,
						"address"=> $getdata->destination->address,
					],
					"history" => $json_history,
					"link"=> $getdata->courier->link,
					"order_id"=> $getdata->id,
					"status"=> $getdata->status,
					"invoice_status"=> $status_invoice,
				];
				return response()->json($json_respon);
				*/
				return $getdata;
			}
			else {
				$json_respon = [
					"success"=> false,
					"messsage"=> 'Data tidak ditemukan',
				];
				return response()->json($json_respon);
			}
		
		} else {
			$json_respon = [
				"success"=> false,
				"messsage"=> 'Data tidak ditemukann',
			];
			return response()->json($json_respon);
		}
	}
    	
}
