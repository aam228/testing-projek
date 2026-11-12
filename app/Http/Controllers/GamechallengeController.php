<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Crypt;

class GamechallengeController extends Controller
{
	public function __construct()
    {
        //$this->url_topsystem = config('services.strava.strava_client_id');
		$this->url_topsystem = "https://topsystem.id/";
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
		if(!$cust_id){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id tidak boleh kosong!'
                ]
            ];
            return response()->json($data);
		}
		// cek customer data
        $user = DB::table("customers")->where("id","=",$cust_id)->first();
		if(!$user){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data user tidak ditemukan!'
                ]
            ];
            return response()->json($data);
		}
		
		
		// cek user konek strava ...
		$gamechallenge = DB::table("game_challenges")
			->select('id', 'gc_name', 'gc_description', 'gc_start', 'gc_end', 'gc_popup', 'gc_banner_top', 'gc_banner_middle', 'gc_gift_description', 'gc_active')
			->where("gc_start","<=",date("Y-m-d"))
			->where("gc_end",">=",date("Y-m-d"))
			->where("gc_active","=",'1')
			->where("gc_delete","=",'0')
			->orderBy("id","DESC")
			//->inRandomOrder()
			//->orderByRaw('RAND()')
			->first();
		if(!$gamechallenge){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data tidak ditemukan!'
                ]
            ];
            return response()->json($data);
		}
		
		// berakhir jam 21:00
		/*
		if(date("Y-m-d H:i:s") > "2025-10-15 21:00:00"){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data tidak ditemukan!'
                ]
            ];
            return response()->json($data);
		}
		*/
		
		
		$gc_description = explode("\n",$gamechallenge->gc_description);
		
		if($gamechallenge->gc_popup){
			$gc_popup = $this->url_topsystem.'upload/challenge/'.$gamechallenge->gc_popup;
		} else { $gc_popup=''; }
		if($gamechallenge->gc_banner_top){
			$gc_banner_top = $this->url_topsystem.'upload/challenge/'.$gamechallenge->gc_banner_top;
		} else { $gc_banner_top=''; }
		if($gamechallenge->gc_banner_middle){
			$gc_banner_middle = $this->url_topsystem.'upload/challenge/'.$gamechallenge->gc_banner_middle;
		} else { $gc_banner_middle=''; }
		
		$data_gamechallenge = [
				"gamechallenge_id" => $gamechallenge->id,
				"gamechallenge_name" => $gamechallenge->gc_name,
				"gamechallenge_description" => $gc_description,
				"gamechallenge_start" => $gamechallenge->gc_start,
				"gamechallenge_end" => $gamechallenge->gc_end,
				"gamechallenge_popup" => $gc_popup,
				"gamechallenge_banner_top" => $gc_banner_top,
				"gamechallenge_banner_middle" => $gc_banner_middle,
				"gamechallenge_terms" => $gamechallenge->gc_gift_description,
		];		
        
		$data = [
			'status' => [
				'code' => 200,
				'description' => 'Success',
				'gamechallenge' => $data_gamechallenge,
			],
		];
		return response()->json($data);		
    }
	
	// terms challenge
	public function challengeTerms(Request $request)
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
		$gamechallenge_id = $request->gamechallenge_id;
		$st_id = $request->st_id; // optional
		$gcdetail_id = $request->gcdetail_id; // optional
		if(!$cust_id || !$gamechallenge_id){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id dan gamechallenge_id tidak boleh kosong!'
                ]
            ];
            return response()->json($data);
		}
		// cek customer data
        $user = DB::table("customers")->where("id","=",$cust_id)->first();
		if(!$user){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data user tidak ditemukan!'
                ]
            ];
            return response()->json($data);
		}
				
		//$gamechallenge = DB::table("game_challenges")->where("id","=",$gamechallenge_id)->first();
		$gamechallenge = DB::table("game_challenges")
			->select('id', 'gc_name', 'gc_description', 'gc_start', 'gc_end', 'gc_popup', 'gc_banner_top', 'gc_banner_middle', 'gc_gift_description', 'gc_active')
			->where("gc_start","<=",date("Y-m-d"))
			->where("gc_end",">=",date("Y-m-d"))
			->where("gc_active","=",'1')
			->where("gc_delete","=",'0')
			->where("id","=",$gamechallenge_id)
			->first();

		if(!$gamechallenge){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data challenge tidak ditemukan!'
                ]
            ];
            return response()->json($data);
		}
		
		$gc_detail = DB::table("game_challenge_details")
			->select('game_challenge_details.id', 'gc_id', 'st_id', 'gcd_typereward', 'gcd_name', 'gcd_icon', 'gcd_description', 'gcd_gift_description', 'gcd_maxwin', 'gcd_coin', 'gcd_lat', 'gcd_lang', 'gcd_active', 'st_name', 'gcd_text_reward', 'gcd_date_active', 'gcd_image')
			->leftJoin('stores', 'stores.id', '=', 'game_challenge_details.st_id')
			->where("gcd_active","=",'1')
			->where("gcd_delete","=",'0')
			->where("gc_id","=",$gamechallenge_id)
			->where(function($w) use($request, $st_id, $gcdetail_id) {
				if($st_id){
					$w->where('st_id', '=', $st_id);
				}
				if($gcdetail_id){
					$w->where('game_challenge_details.id', '=', $gcdetail_id);
				}
			})
			//->orderBy("st_id")
			->orderByRaw('RAND()')
			->get();
		$array_detail = array();
		foreach($gc_detail as $raw){
			// cek apakah tersedia atau tidak?
			$cek_pemenang = DB::table("game_challenge_detail_scans")->where(["gcd_id"=>$raw->id, "gcds_win"=>"1"])->count();
			if($raw->gcd_maxwin<=$cek_pemenang){ $status_ketersediaan = 0; } else { $status_ketersediaan = 1; }
			
			if($raw->gcd_icon){
				$gcd_icon = $this->url_topsystem.'upload/challenge/'.$raw->gcd_icon;
			} else {
				$gcd_icon = '';
			}
			// $gcd_image
			if($raw->gcd_image){
				$gcd_image = $this->url_topsystem.'upload/challenge/'.$raw->gcd_image;
			} else {
				$gcd_image = '';
			}
			$array_detail[]=[
				'gcdetail_id' => $raw->id,
				'gcdetail_store' => $raw->st_name,
				'gcdetail_type_reward' => $raw->gcd_typereward,
				'gcdetail_text_reward' => $raw->gcd_text_reward,
				'gcdetail_name' => $raw->gcd_name,
				'gcdetail_icon' => $gcd_icon,
				'gcdetail_description' => $raw->gcd_description,
				'gcdetail_terms' => $raw->gcd_gift_description,
				'gcdetail_maxwin' => (int)$raw->gcd_maxwin,
				'gcdetail_coin' => (int)$raw->gcd_coin,
				'gcdetail_lat' => $raw->gcd_lat,
				'gcdetail_long' => $raw->gcd_lang,
				'gcdetail_long' => $raw->gcd_lang,
				'gcdetail_date_reward' => $raw->gcd_date_active,
				'gcdetail_available' => (int)$status_ketersediaan,
				'gcdetail_image' => $gcd_image,
			];
		}
		
        $data = [
			'status' => [
				'code' => 200,
				'description' => 'Success',
				'gcdetail' => $array_detail,
			],
		];
		return response()->json($data);		
    }
	
	// challengeLogsan
	public function challengeLogsan(Request $request)
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
		$st_id = $request->st_id; // opsional
		$gclog_id = $request->gclog_id; // opsional
		if(!$cust_id){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id tidak boleh kosong!'
                ]
            ];
            return response()->json($data);
		}
		// cek customer data
        $user = DB::table("customers")->where("id","=",$cust_id)->first();
		if(!$user){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data user tidak ditemukan!'
                ]
            ];
            return response()->json($data);
		}
				
		$gc_log = DB::table("game_challenge_detail_scans")
			->select('game_challenge_detail_scans.id', 'game_challenge_detail_scans.created_at', 'gcd_name', 'gcd_typereward', 'gcd_icon', 'gcd_coin', 'gcds_coin', 'gcd_text_reward', 'gcd_text_description', 'st_name', 'gcds_invoice', 'gcd_gift_description', 'gcd_date_active', 'gc_notes')
			->leftJoin('game_challenge_details', 'game_challenge_details.id', '=', 'game_challenge_detail_scans.gcd_id')
			->leftJoin('game_challenges', 'game_challenges.id', '=', 'game_challenge_details.gc_id')
			->leftJoin('stores', 'stores.id', '=', 'game_challenge_details.st_id')
			->where("gcd_active","=",'1')
			->where("gcd_delete","=",'0')
			->where("cust_id","=",$cust_id)
			->where(function($w) use($request, $st_id, $gclog_id) {
				if($st_id){
					$w->where('st_id', '=', $st_id);
				}
				if($gclog_id){
					$w->where('game_challenge_detail_scans.id', '=', $gclog_id);
				}
			})
			//->orderBy("st_id")
			->orderBy("game_challenge_detail_scans.id","DESC")
			->get();
        
        $gc_notes = 'Hadiah event 10.10 akan hangus tanggal 31 Okt 2025 jika tidak digunakan/ditukarkan.';
		$array_log = array();
		foreach($gc_log as $raw){
			if($raw->gcd_icon){
				$gcd_icon = $this->url_topsystem.'upload/challenge/'.$raw->gcd_icon;
			} else {
				$gcd_icon = '';
			}
			
			$encoded_id = Crypt::encryptString($raw->id);
			$decoded_id = Crypt::decryptString($encoded_id);
			
			$array_log[]=[
				'gclog_id' => $raw->id,
				'gclog_type' => $raw->gcd_typereward,
				'gclog_name' => $raw->gcd_name,
				'gclog_store' => $raw->st_name,
				'gclog_date' => $raw->created_at,
				'gclog_coin' => (int)$raw->gcds_coin,
				'gclog_icon' => $gcd_icon,
				'gclog_invoice' => $raw->gcds_invoice,
				'gclog_product' => $raw->gcd_text_description ?? '',
				'gclog_terms' => $raw->gcd_gift_description,
				'gclog_maxdate' => $raw->gcd_date_active,
				//'gclog_e' => $encoded_id,
				//'gclog_d' => $decoded_id,
			];
			$gc_notes = $raw->gc_notes;
		}
		
        $data = [
			'status' => [
				'code' => 200,
				'description' => 'Success',
				'gcnotes' => $gc_notes,
				'gclog' => $array_log,
			],
		];
		return response()->json($data);		
    }
}
