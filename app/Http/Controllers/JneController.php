<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
//use App\Models\Artikel;

class JneController extends Controller
{
	public function __construct()
    {
		$this->username = 'MBGOL';
		$this->api_key = '29ae97e82bd2bf333049df4d854a3074';
    }
	
    public function CekOngkir(Request $request)
    {
		/*
		$from_location = 'CGK10000';
		$to_location = 'PKU10000';
		$weight = 1;
		*/
		$from_location = $request->from;
		$to_location = $request->thru;
		$weight = $request->weight;
		
		$curl	= curl_init();
		$url_api = "http://apiv2.jne.co.id:10101/tracing/api/pricedev";
		$data	= ['username'	=> $this->username,
				   'api_key' 	=> $this->api_key,
				   'from'=> $from_location,
				   'thru'=> $to_location,
				   'weight'=> $weight,
				   ];
		
		curl_setopt($curl,CURLOPT_HTTPHEADER,array("Authorization: ".$this->api_key));
		curl_setopt($curl,CURLOPT_CUSTOMREQUEST,"POST");
		curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($data));
		curl_setopt($curl,CURLOPT_URL,$url_api);
		curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,0);
		curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,0);
		$result=curl_exec($curl);
		curl_close($curl);
		$getdata=json_decode($result);
		return $getdata;
		//echo $result;
		//echo '1';
	}
	
}