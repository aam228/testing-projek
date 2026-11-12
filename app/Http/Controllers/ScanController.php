<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Hash;

class ScanController extends Controller
{
	protected $table = 'pos_transactions';
	protected $url_topsystem = 'https://topsystem.id/';
	//protected $url_server = 'https://topsystem.id/';
	//protected $url_server = 'https://devtop.topsystem.id/';
	
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
		$qr_invoice = $request->qr_invoice; 
		$type = $request->type; 
		if(empty($cust_id) || empty($qr_invoice) || empty($type)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id, type dan qr_invoice harus diisi'
                ]
            ];
            return response()->json($data);
		}
		
		$row_cust = DB::table("customers")->where("id","=",$cust_id)->first();
		if(!$row_cust){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data customer tidak ditemukan'
                ]
            ];
            return response()->json($data);
		}
		
		if($type=="redeem.coin"){
			//return $this->index_pos($cust_id, $qr_invoice);		
			$arr_qrvalue = explode(".",$qr_invoice);
			if(count($arr_qrvalue)<=1){
				return $this->index_pos($cust_id, $qr_invoice);
			} else 
			if($arr_qrvalue[0] == "event"){
				return $this->index_coinevent($cust_id, $arr_qrvalue[1]);
			} else 
			if($arr_qrvalue[0] == "store"){ // kusus redeem koin offline store 
				return $this->index_coinstore($cust_id, $arr_qrvalue[1]);
			} else 
			if($arr_qrvalue[0] == "seminar"){ // kusus seminar tanpa order event
				return $this->index_coinseminar($cust_id, $arr_qrvalue[1]);
			}
		} else 
		if($type=="event.komunitas"){
			return $this->index_event_komunitas($cust_id, $qr_invoice);
		} else 
		if($type=="event.topscore"){
			//return $this->index_event_topscore($cust_id, $qr_invoice);		
			return $this->index_event_komunitas($cust_id, $qr_invoice);
		} else 
		if($type=="event.challenge"){
			// {"type": "event.challenge", "value" : "eyJpdiI6ImFnTXBCSUJnVStESldYOEY5MDIzSGc9PSIsInZhbHVlIjoiWnpZTmlpaHFwczJzdGZSUllMUzcwdz09IiwibWFjIjoiYzNmYjE2ZWRiYjkyYjI2MDQ2NDFjYzgzNzg3NDUyMTIyNGUzMDAyYWIyMmQyYTFjMjQyZTBlMzE1ZjVhYjRhYiIsInRhZyI6IiJ9", "latitude": "-6.9392635", "longitude": "107.6299005"}
			return $this->index_event_challenge($cust_id, $qr_invoice);
		}
		else {
			// tampilkan respon data jika type tidak tersedia
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Type data tidak valid',
				],
			];
			return response()->json($data);
		}
		
    }
	
	// scan invoice (pos transaction)
	private function index_pos($cust_id, $qr_invoice)
    {
		$cust_id = $cust_id; 
		$qr_invoice = $qr_invoice; 
		if(empty($cust_id) || empty($qr_invoice)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id dan qr_invoice harus diisi'
                ]
            ];
            return response()->json($data);
		}
		
		$row_cust = DB::table("customers")->where("id","=",$cust_id)->first();
		if(!$row_cust){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data customer tidak ditemukan'
                ]
            ];
            return response()->json($data);
		}
		
		$row_pos = DB::table($this->table)
		->select("pos_transactions.id", "pos_invoice", "pos_real_price", "pos_admin_cost", "pos_another_cost", "pos_transactions.created_at", "pos_scan_invoice", "pos_scan_date", "pos_shipping")
		->where("pos_invoice","=",$qr_invoice)
		//->where("cust_id","=",$cust_id)
		//->where("is_apps","=",0) // kusus aplikasi
		//->where("is_website","=",0)
		//->whereNull('is_website')
		//->where("is_website","=",1)
		//->whereNotIn('pos_status', ['WAITING FOR CONFIRMATION', 'CANCEL', 'UNPAID', 'REFUND', 'EXCHANGE'])
		->whereNotIn('pos_status', ['WAITING FOR CONFIRMATION', 'CANCEL', 'UNPAID', 'REFUND'])
		->whereNotIn('std_id', ['20', '17', '13']) // selain apps, offline, dan website
		->first();
		if(!$row_pos){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data order tidak ditemukan'
                ]
            ];
            return response()->json($data);
		}
		// cek jika sudah pernah d scan
		if($row_pos->pos_scan_invoice==1){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Invoice sudah pernah di redeem pada tanggal '.date("d M Y H:i",strtotime($row_pos->pos_scan_date))
                ]
            ];
            return response()->json($data);			
		}
		
		$nilai_coin=0;
		$nilai_score=0;
		// update proses redeem coin dan score
		if($row_pos->pos_invoice!='INV20210908220322'){ // buat sample
		$updateds = [
			'pos_scan_invoice' => 1,
			'pos_scan_date' => date("Y-m-d H:i:s"),
		];
		DB::table($this->table)->where('id', $row_pos->id)->update($updateds);
		$total_inv = $row_pos->pos_real_price - $row_pos->pos_admin_cost - $row_pos->pos_shipping;
		// coint = 1%, score = 1
		$nilai_coin = (1/100) * $total_inv;
		$nilai_score = 1;
		
		// input coin log
		$data_coin = [
			'cust_id' => $cust_id,
			'ccoin_type' => 'pos',
			'ccoin_lookup_id' => $row_pos->id,
			'ccoin_description' => 'Scan invoice belanja no order '.$row_pos->pos_invoice,
			'ccoin_value' => $nilai_coin,
			'created_at' => date("Y-m-d H:i:s"),
		];
		DB::table("customer_coins")->insertGetId(array_merge($data_coin));
		// input score log
		$data_score = [
			'cust_id' => $cust_id,
			'cscore_type' => 'pos',
			'cscore_lookup_id' => $row_pos->id,
			'cscore_description' => 'Scan invoice belanja no order '.$row_pos->pos_invoice,
			'cscore_value' => $nilai_score,
			'created_at' => date("Y-m-d H:i:s"),
		];
		DB::table("customer_scores")->insertGetId(array_merge($data_score));
		// update nilai coin dan score customer
		$updated_cust = [
			'cust_coin' => ($row_cust->cust_coin + $nilai_coin),
			'cust_score' => ($row_cust->cust_score + $nilai_score),
		];
		DB::table('customers')->where('id', $row_cust->id)->update($updated_cust);
		}
		
		// tampilkan respon data setelah di redeem oleh user
        $data = [
            'status' => [
                'code' => 200,
                //'description' => 'Success',
                'description' => 'Selamat, anda berhasil mendapatkan nominal coin sebesar Rp. '.number_format($nilai_coin,0,',','.').' dan '.$nilai_score.' Score"',
                'coin' => (int)$nilai_coin,
				'score' => (int)$nilai_score,
            ],
        ];
        return response()->json($data);
    }
	
	// gift coin user
	private function index_coinevent($cust_id, $qr_invoice)
    {
		$cust_id = $cust_id; 
		$qr_invoice = $qr_invoice; 
		if(empty($cust_id) || empty($qr_invoice)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id dan qr_invoice harus diisi'
                ]
            ];
            return response()->json($data);
		}
		
		$row_cust = DB::table("customers")->where("id","=",$cust_id)->first();
		if(!$row_cust){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data customer tidak ditemukan'
                ]
            ];
            return response()->json($data);
		}
		
		$row_event = DB::table("event_tops")
		->select("event_tops.id", "et_title", "et_start_date", "et_end_date", "et_category", "et_type", "et_coin")
		->where("id","=",$qr_invoice)
		->where("et_delete","=","0")
		->first();
		if(!$row_event){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data event tidak ditemukan'
                ]
            ];
            return response()->json($data);
		}
		
		$arr_scan = DB::table("customer_coins")
		->where(["cust_id" => $cust_id, "ccoin_lookup_id" => $qr_invoice, "ccoin_type" => "event"])
		->first();
		// cek jika sudah pernah d scan
		if($arr_scan){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data sudah pernah di redeem pada tanggal '.date("d M Y H:i",strtotime($arr_scan->created_at))
                ]
            ];
            return response()->json($data);			
		}
		
		// cek customer sudah order event atau belum
		$arr_orders = DB::table("event_orders")->where(["et_id" => $row_event->id, "u_id" => $cust_id])->first();
		if(!$arr_orders){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Kamu belum terdaftar di acara ini'
                ]
            ];
            return response()->json($data);
		}
		
		//$nilai_coin = 100000;
		$nilai_coin = $row_event->et_coin;
		// update proses redeem coin dan score
		if($row_event->id!='1'){ // buat sample
		// input coin log
		$data_coin = [
			'cust_id' => $cust_id,
			'ccoin_type' => 'event',
			'ccoin_lookup_id' => $row_event->id,
			'ccoin_description' => 'Scan QR event dengan id '.$row_event->id.' dan nama event '.$row_event->et_title,
			'ccoin_value' => (int)$nilai_coin,
			'created_at' => date("Y-m-d H:i:s"),
		];
		DB::table("customer_coins")->insertGetId(array_merge($data_coin));
		
		// update nilai coin dan score customer
		$updated_cust = [
			'cust_coin' => ($row_cust->cust_coin + $nilai_coin),
			//'cust_score' => ($row_cust->cust_score + $nilai_score),
		];
		DB::table('customers')->where('id', $row_cust->id)->update($updated_cust);
		
		}
		
		// tampilkan respon data setelah di redeem oleh user
        $data = [
            'status' => [
                'code' => 200,
                'description' => 'Selamat, anda berhasil mendapatkan nominal coin sebesar Rp. '.number_format($nilai_coin,0,',','.'),
                'coin' => (int)$nilai_coin,
				//'score' => (int)$nilai_score,
            ],
        ];
        return response()->json($data);
    }
	
	// gift coin seminat tanpa order event
	private function index_coinseminar($cust_id, $qr_invoice)
    {
		$cust_id = $cust_id; 
		$qr_invoice = $qr_invoice; 
		if(empty($cust_id) || empty($qr_invoice)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id dan qr_invoice harus diisi'
                ]
            ];
            return response()->json($data);
		}
		
		$row_cust = DB::table("customers")->where("id","=",$cust_id)->first();
		if(!$row_cust){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data customer tidak ditemukan'
                ]
            ];
            return response()->json($data);
		}
		
		$row_event = DB::table("event_tops")
		->select("event_tops.id", "et_title", "et_start_date", "et_end_date", "et_category", "et_type", "et_coin", "et_end_time")
		->where("id","=",$qr_invoice)
		->where("et_end_date",">=",date("Y-m-d"))
		->where("et_end_time",">=",date("H:i:s"))
		->where("et_delete","=","0")
		->first();
		if(!$row_event){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data event tidak ditemukan'
                ]
            ];
            return response()->json($data);
		}
		
		// cek jika event sudah berlalu
		/*
		if($row_event->et_end_date.' '.$row_event->et_end_time > date("Y-m-d H:i:s")){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data event seminar sudah tidak aktif!'
                ]
            ];
            return response()->json($data);
		}
		*/
		
		$arr_scan = DB::table("customer_coins")
		->where(["cust_id" => $cust_id, "ccoin_lookup_id" => $qr_invoice, "ccoin_type" => "seminar"])
		->first();
		// cek jika sudah pernah d scan
		if($arr_scan){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data sudah pernah di redeem pada tanggal '.date("d M Y H:i",strtotime($arr_scan->created_at))
                ]
            ];
            return response()->json($data);			
		}
		
		//$nilai_coin = 50000;
		$nilai_coin = $row_event->et_coin;
		
		// cek nilai koin
		if($nilai_coin<=0){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data tidak valid!'
                ]
            ];
            return response()->json($data);
		}
		
		// input coin log
		$data_coin = [
			'cust_id' => $cust_id,
			'ccoin_type' => 'seminar',
			'ccoin_lookup_id' => $row_event->id,
			//'ccoin_description' => 'Scan QR event seminar dengan id '.$row_event->id,
			'ccoin_description' => 'Scan QR event seminar dengan id '.$row_event->id.' dan nama seminar '.$row_event->et_title,
			'ccoin_value' => (int)$nilai_coin,
			'created_at' => date("Y-m-d H:i:s"),
		];
		DB::table("customer_coins")->insertGetId(array_merge($data_coin));
		
		// update nilai coin dan score customer
		$updated_cust = [
			'cust_coin' => ($row_cust->cust_coin + $nilai_coin),
		];
		DB::table('customers')->where('id', $row_cust->id)->update($updated_cust);
		
		
		// tampilkan respon data setelah di redeem oleh user
        $data = [
            'status' => [
                'code' => 200,
                'description' => 'Selamat, anda berhasil mendapatkan nominal coin sebesar Rp. '.number_format($nilai_coin,0,',','.'),
                'coin' => (int)$nilai_coin,
				//'score' => (int)$nilai_score,
            ],
        ];
        return response()->json($data);
    }
	
	// gift coin scan QR offline store
	private function index_coinstore($cust_id, $store_id)
    {
		//$cust_id = $cust_id; 
		//$store_id = $store_id; 
		if(empty($cust_id) || empty($store_id)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id dan store_id harus diisi'
                ]
            ];
            return response()->json($data);
		}
		
		$row_cust = DB::table("customers")->where("id","=",$cust_id)->first();
		if(!$row_cust){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data customer tidak ditemukan'
                ]
            ];
            return response()->json($data);
		}
		
		$row_store = DB::table("stores")
		->select("stores.id", "st_name")
		->where("id","=",$store_id)
		->where("st_delete","=","0")
		->first();
		if(!$row_store){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data store tidak ditemukan'
                ]
            ];
            return response()->json($data);
		}
		
		$arr_scan = DB::table("customer_coins")
		//->where(["cust_id" => $cust_id, "ccoin_lookup_id" => $store_id, "ccoin_type" => "store"])
		->where(["cust_id" => $cust_id, "ccoin_type" => "store"])
		->first();
		// cek jika sudah pernah d scan
		if($arr_scan){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data sudah pernah di redeem pada tanggal '.date("d M Y H:i",strtotime($arr_scan->created_at))
                ]
            ];
            return response()->json($data);			
		}
		
		//$nilai_coin = 50000;
		$nilai_coin = rand(10000,20000); // random mulai 10.000 sd 20.000
		// input coin log
		$data_coin = [
			'cust_id' => $cust_id,
			'ccoin_type' => 'store',
			'ccoin_lookup_id' => $store_id,
			'ccoin_description' => 'Scan QR coin di store '.$row_store->st_name,
			'ccoin_value' => (int)$nilai_coin,
			'created_at' => date("Y-m-d H:i:s"),
		];
		DB::table("customer_coins")->insertGetId(array_merge($data_coin));
		
		// update nilai coin dan score customer
		$updated_cust = [
			'cust_coin' => ($row_cust->cust_coin + $nilai_coin),
			//'cust_score' => ($row_cust->cust_score + $nilai_score),
		];
		DB::table('customers')->where('id', $row_cust->id)->update($updated_cust);
		
		//}
		
		// tampilkan respon data setelah di redeem oleh user
        $data = [
            'status' => [
                'code' => 200,
                'description' => 'Selamat, anda berhasil mendapatkan nominal coin sebesar Rp. '.number_format($nilai_coin,0,',','.'),
                'coin' => (int)$nilai_coin,
            ],
        ];
        return response()->json($data);
    }
	
	// event komunitas
	private function index_event_komunitas($cust_id, $qr_invoice)
    {
		$cust_id = $cust_id; 
		$qr_invoice = $qr_invoice; 
		if(empty($cust_id) || empty($qr_invoice)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id dan qr_invoice harus diisi'
                ]
            ];
            return response()->json($data);
		}
		
		$row_cust = DB::table("customers")->where("id","=",$cust_id)->first();
		if(!$row_cust){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data customer tidak ditemukan'
                ]
            ];
            return response()->json($data);
		}
		
		$row_event = DB::table("event_tops")->where("id","=",$qr_invoice)->first();
		if(!$row_event){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data event tidak ditemukan'
                ]
            ];
            return response()->json($data);
		}
		
		$row_pos = DB::table("event_orders")
		->select("event_orders.id", "eo_invoice", "eo_name", "eo_phone", "et_id", "u_id as cust_id", "eo_total", "eo_price", "eo_qty", "event_orders.created_at", "eo_scan_date", "eo_scan_count", "eo_scan_updated", "eo_scan_user", "eo_scan_update")
		//->where("eo_invoice","=",$qr_invoice)
		->where("u_id","=",$cust_id)
		->where("et_id","=",$row_event->id)
		//->where("eo_scan_count","=","0")
		->where("payment_status","=","1")
		->orderBy("eo_scan_count","ASC")
		->orderBy("event_orders.id","DESC")
		->first();
		if(!$row_pos){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data order tidak ditemukan'
                ]
            ];
            return response()->json($data);
		}
		// cek jika sudah pernah d scan
		if($row_pos->eo_scan_count>0){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data sudah pernah di scan pada tanggal '.date("d M Y H:i",strtotime($row_pos->eo_scan_date))
                ]
            ];
            return response()->json($data);			
		}
		
		if($qr_invoice!='1'){ // buat sample
		// update proses redeem coin dan score
		$updateds = [
			'eo_scan_count' => $row_pos->eo_scan_count+1,
			'eo_scan_date' => date("Y-m-d H:i:s"),
		];
		DB::table("event_orders")->where('id', $row_pos->id)->update($updateds);
		}
		/*
		$total_inv = $row_pos->pos_real_price - $row_pos->pos_admin_cost - $row_pos->pos_shipping;
		// coint = 1%, score = 1
		$nilai_coin = (1/100) * $total_inv;
		$nilai_score = 1;
		
		// input coin log
		$data_coin = [
			'cust_id' => $cust_id,
			'ccoin_type' => 'pos',
			'ccoin_lookup_id' => $row_pos->id,
			'ccoin_description' => 'Scan invoice belanja no order '.$row_pos->pos_invoice,
			'ccoin_value' => $nilai_coin,
			'created_at' => date("Y-m-d H:i:s"),
		];
		DB::table("customer_coins")->insertGetId(array_merge($data_coin));
		// input score log
		$data_score = [
			'cust_id' => $cust_id,
			'cscore_type' => 'pos',
			'cscore_lookup_id' => $row_pos->id,
			'cscore_description' => 'Scan invoice belanja no order '.$row_pos->pos_invoice,
			'cscore_value' => $nilai_score,
			'created_at' => date("Y-m-d H:i:s"),
		];
		DB::table("customer_scores")->insertGetId(array_merge($data_score));
		// update nilai coin dan score customer
		$updated_cust = [
			'cust_coin' => ($row_cust->cust_coin + $nilai_coin),
			'cust_score' => ($row_cust->cust_score + $nilai_score),
		];
		DB::table('customers')->where('id', $row_cust->id)->update($updated_cust);
		*/
		// tampilkan respon data setelah di redeem oleh user
        $data = [
            'status' => [
                'code' => 200,
                //'description' => 'Data ["'.$row_pos->eo_name.'"] berhasil di scan. Terimakasih sudah mengisi daftar kehadiran',
                'description' => 'Selamat '.$row_pos->eo_name.', data kamu berhasil di scan. Terimakasih sudah mengisi daftar kehadiran pada acara '.$row_event->et_title,
                'coin' => 0,
				'score' => 0,
            ],
        ];
        return response()->json($data);
    }
	
	/*
	public function index_old(Request $request)
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
		$qr_invoice = $request->qr_invoice; 
		if(empty($cust_id) || empty($qr_invoice)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id dan qr_invoice harus diisi'
                ]
            ];
            return response()->json($data);
		}
		
		$row_cust = DB::table("customers")->where("id","=",$cust_id)->first();
		if(!$row_cust){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data customer tidak ditemukan'
                ]
            ];
            return response()->json($data);
		}
		
		$row_pos = DB::table($this->table)
		->select("pos_transactions.id", "pos_invoice", "pos_real_price", "pos_admin_cost", "pos_another_cost", "pos_transactions.created_at", "pos_scan_invoice", "pos_scan_date", "pos_shipping")
		->where("pos_invoice","=",$qr_invoice)
		//->where("cust_id","=",$cust_id)
		//->where("is_apps","=",0) // kusus aplikasi
		//->where("is_website","=",0)
		//->whereNull('is_website')
		->where("is_website","=",1)
		->whereNotIn('pos_status', ['WAITING FOR CONFIRMATION', 'CANCEL', 'UNPAID', 'REFUND', 'EXCHANGE'])
		->first();
		if(!$row_pos){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data order tidak ditemukan'
                ]
            ];
            return response()->json($data);
		}
		// cek jika sudah pernah d scan
		if($row_pos->pos_scan_invoice==1){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Invoice sudah pernah di redeem pada tanggal '.date("d M Y H:i",strtotime($row_pos->pos_scan_date))
                ]
            ];
            return response()->json($data);			
		}
		// update proses redeem coin dan score
		$updateds = [
			'pos_scan_invoice' => 1,
			'pos_scan_date' => date("Y-m-d H:i:s"),
		];
		DB::table($this->table)->where('id', $row_pos->id)->update($updateds);
		$total_inv = $row_pos->pos_real_price - $row_pos->pos_admin_cost - $row_pos->pos_shipping;
		// coint = 1%, score = 1
		$nilai_coin = (1/100) * $total_inv;
		$nilai_score = 1;
		
		// input coin log
		$data_coin = [
			'cust_id' => $cust_id,
			'ccoin_type' => 'pos',
			'ccoin_lookup_id' => $row_pos->id,
			'ccoin_description' => 'Scan invoice belanja no order '.$row_pos->pos_invoice,
			'ccoin_value' => $nilai_coin,
			'created_at' => date("Y-m-d H:i:s"),
		];
		DB::table("customer_coins")->insertGetId(array_merge($data_coin));
		// input score log
		$data_score = [
			'cust_id' => $cust_id,
			'cscore_type' => 'pos',
			'cscore_lookup_id' => $row_pos->id,
			'cscore_description' => 'Scan invoice belanja no order '.$row_pos->pos_invoice,
			'cscore_value' => $nilai_score,
			'created_at' => date("Y-m-d H:i:s"),
		];
		DB::table("customer_scores")->insertGetId(array_merge($data_score));
		// update nilai coin dan score customer
		$updated_cust = [
			'cust_coin' => ($row_cust->cust_coin + $nilai_coin),
			'cust_score' => ($row_cust->cust_score + $nilai_score),
		];
		DB::table('customers')->where('id', $row_cust->id)->update($updated_cust);

		// tampilkan respon data setelah di redeem oleh user
        $data = [
            'status' => [
                'code' => 200,
                'description' => 'Success',
                'coin' => (int)$nilai_coin,
				'score' => (int)$nilai_score,
            ],
        ];
        return response()->json($data);
    }
    */
    
    // event challenge scan
	private function index_event_challenge($cust_id, $qr_invoice)
    {
		$cust_id = $cust_id; 
		$qr_invoice = $qr_invoice; 
		if(empty($cust_id) || empty($qr_invoice)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id dan qr_invoice harus diisi'
                ]
            ];
            return response()->json($data);
		}

		$row_cust = DB::table("customers")->where("id","=",$cust_id)->first();
		if(!$row_cust){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data customer tidak ditemukan'
                ]
            ];
            return response()->json($data);
		}

		//$encoded_id = Crypt::encryptString($qr_invoice);
		$decoded_id = Crypt::decryptString($qr_invoice); // ID detail challenge
		//$decoded_id = $qr_invoice;
		$row_cd = DB::table("game_challenge_details")->where("id","=",$decoded_id)->first();
		if(!$row_cd){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'QR data tidak ditemukan!'
                ]
            ];
            return response()->json($data);
		}
		
		// simpan semua log scan ***
		$data_logscan_all = [
			'cust_id' => $cust_id,
			'gcd_id' => $row_cd->id,
			'created_at' => date("Y-m-d H:i:s"),
		];
		$save_log = DB::table("game_challenge_detail_scan_logs")->insertGetId(array_merge($data_logscan_all));
		// end save log *********
		
		// cek apakah aktif challengenya ...
		$row_challenge = DB::table("game_challenges")
			->where("id","=",$row_cd->gc_id)
			->where("gc_start","<=",date("Y-m-d"))
			->where("gc_end",">=",date("Y-m-d"))
			->first();
		if(!$row_challenge){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Challenge tidak ditemukan!'
                ]
            ];
            return response()->json($data);
		}
		
		// cek apakah user sudah pernah scan di sini?
		$row_log = DB::table("game_challenge_detail_scans")->where(["gcd_id"=>$decoded_id, "cust_id"=>$cust_id])->first();
		if($row_log){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Kamu sudah pernah SCAN QR ini pada tanggal '.date("d M Y H:i",strtotime($row_log->created_at)),
                ]
            ];
            return response()->json($data);
		}
		
		
		// cek apakah kuota masih tersedia? ($row_cd)
		$user_win = DB::table("game_challenge_detail_scans")->where(["gcd_id"=>$decoded_id])->count(); 
		if($row_cd->gcd_maxwin <= (int)$user_win){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Oopps..! scan gagal karena kuota untuk QR ini sudah full!',
                ]
            ];
            return response()->json($data);
		}
		
		
		// jika ada koin
		if($row_cd->gcd_coin>0){$gcds_claim=1;} else {$gcds_claim=0;}
		
		// simpan data log scan ***
		$gcds_invoice = $this->generateInvoice();
		$data_logscan = [
			'cust_id' => $cust_id,
			'gcd_id' => $row_cd->id,
			'gcds_invoice' => $gcds_invoice,
			'gcds_win' => 1,
			'gcds_claim' => $gcds_claim,
			'gcds_coin' => $row_cd->gcd_coin,
			'created_at' => date("Y-m-d H:i:s"),
		];
		$save_log = DB::table("game_challenge_detail_scans")->insertGetId(array_merge($data_logscan));
		
		// jika ada koin
		if($row_cd->gcd_coin>0 && $save_log){
			// simpan koin log
			$data_coin = [
				'cust_id' => $cust_id,
				'ccoin_type' => 'game challenge',
				'ccoin_lookup_id' => $row_cd->id,
				'ccoin_description' => 'Scan QR challenge dengan ID '.$gcds_invoice,
				'ccoin_value' => $row_cd->gcd_coin,
				'created_at' => date("Y-m-d H:i:s"),
			];
			$save_coin = DB::table("customer_coins")->insertGetId(array_merge($data_coin));
			// update koin ke data customer
			$updated_user = [
				'cust_coin' => $row_cust->cust_coin + $row_cd->gcd_coin,
			];
			DB::table("customers")->where('id', $cust_id)->update($updated_user);
		}
		
		if($row_cd->gcd_icon){
			$gcd_icon = $this->url_topsystem.'upload/challenge/'.$row_cd->gcd_icon;
		} else {
			$gcd_icon = '';
		}
		// tampilkan respon data setelah di redeem oleh user
        $data = [
            'status' => [
                'code' => 200,
                'description' => 'Selamat '.$row_cust->cust_name.', kamu telah berhasil melakukan scan dan redeem hadiahnya. Terima kasih sudah berpartisipasi',
				'coin' => $row_cd->gcd_coin,
				'score' => 0,
				'gclog_maxdate' => $row_cd->gcd_date_active,
				'gclog_icon' => $gcd_icon,
				'gclog_text_reward' => $row_cd->gcd_text_reward,
				'gclog_product' => $row_cd->gcd_text_description ?? '',
				'gclog_id' => $save_log,
            ],
        ];
        return response()->json($data);
    }

	private function generateInvoice()
    {
        $invoice = 'GC'.date('YmdHis').rand(1000,9999);
        if ($this->InvoiceExists($invoice)) {
            return generateInvoice();
        }
        return $invoice;
    }
	
	private function InvoiceExists($number) {
        return DB::table('game_challenge_detail_scans')->where(['gcds_invoice' => $number])->exists();
    }	
    
    // Ambil string ID yang di encode ***
	public function generateEncode(Request $request)
	{
		// $encoded_id = Crypt::encryptString($raw->id);
        $row_cd = DB::table("game_challenge_details")
			->select('game_challenge_details.id', 'st_name', 'gcd_name', 'gcd_lat', 'gcd_lang')
			->leftJoin('stores', 'stores.id', '=', 'game_challenge_details.st_id')
			->where("game_challenge_details.id","=",$request->id)
			->first();
		if($row_cd){
			$encoded_id = Crypt::encryptString($row_cd->id);
            $data = [
                'status' => [
                    'code' => 200,
                    'id' => $encoded_id,
                ]
            ];
            return response()->json($data);
		} else {
            $data = [
                'status' => [
                    'code' => 400,
                ]
            ];
            return response()->json($data);
		}
	}
}
