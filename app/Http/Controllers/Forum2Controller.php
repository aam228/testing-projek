<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Forum2Controller extends Controller
{
	protected $url_server = 'https://topsystem.id/';
	
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
		
		// save log traffic
		/*
		$data_log = [
			'cust_id' => $request->cust_id,
			'ta_page_access' => "Forum",
			'ta_description' => "Mengakses laman forum aplikasi",
			'created_at' => date("Y-m-d H:i:s"),
		];
		$save_log = DB::table("traffic_apps")->insertGetId(array_merge($data_log));
		*/
		
		// cek banner yang terhubung ke challenge running
        $query_challenge = DB::table('running_sponsorships')
		->select('running_sponsorships.id', 'run_id', 'sponsor_name', 'sponsor_icon', 'sponsor_banner')
		->leftJoin('runnings', 'runnings.id', '=', 'running_sponsorships.run_id')
		->where('run_start', '<=', date("Y-m-d"))
		->where('run_end', '>=', date("Y-m-d"))
		->where('run_delete', '=', "0")
		->where('run_active', '=', "1")
		->where('sponsor_delete', '=', "0")
        ->orderBy("run_distance",'ASC')
		->get();
		// data default...
		$data_challenge[]= [
			'run_id' => null,
			'run_image' => 'https://topsystem.id/upload/event/banner_default_challenge.jpg',
			//'run_start' => "",
			//'run_end' => "",
		];
		$nom=0;
		foreach($query_challenge as $raw){
			$nom++;
			$data_challenge[]= [
				'run_id' => $raw->run_id,
				'run_image' => 'https://topsystem.id/upload/event/'.$raw->sponsor_banner,
				//'run_start' => $raw->run_start,
				//'run_end' => $raw->run_end,
			];
		}
		
		// kategori
		$forum_search = $request->forum_search; // keyword pencarian blog
		$forum_cat_id = $request->forum_cat_id;
		
        $query = DB::table('forum_categories')
		->select('forum_categories.id', 'foc_name', 'foc_short_name', 'foc_slug', 'foc_description', 'foc_image', 'foc_sort', 'foc_active', 'forum_categories.created_at', 'u_name', 'foc_icon')
		->leftJoin('users', 'users.id', '=', 'forum_categories.user_input')
		->where('forum_categories.foc_active', '=', "1")
		->where('forum_categories.foc_delete', '=', "0")
		->where(function($w) use($forum_search, $forum_cat_id) {
			if (!empty($forum_search)) {
				$w->where(DB::raw("CONCAT(foc_name, ' ', foc_description)"), 'LIKE', "%$forum_search%");
			}
			if(!empty($forum_cat_id)){
				$w->where('forum_categories.id', '=', $forum_cat_id);
			}
		})
        ->orderBy("forum_categories.foc_sort",'ASC')
		->paginate(5);
		
        $results = array();
        if (!empty($query)) {
            foreach ($query as $row) {				
				// cek jumlah forum sesuai kategori
				$check_forum = DB::table("forum_details")->where("foc_id","=",$row->id)->where("fodet_delete","=",'0')->get();
				
				if (!empty($row->foc_image)) {
                	$image_url = $this->url_server."upload/forum/".$row->foc_image;
                } else {
					$image_url = '';
				}
                
				$forum_detail = DB::table("forum_details")->where("foc_id","=",$row->id)->where("fodet_delete","=","0")->orderBy("fodet_name","ASC")->get();
				$detail_forum = array();
				foreach($forum_detail as $raw_detail){
					if (!empty($raw_detail->fodet_image)) {
						$fodet_image = $this->url_server."upload/forum/".$raw_detail->fodet_image;
					} else {
						$fodet_image = '';
					}
					$forum_join_user=DB::table("forum_detail_joins")->where(["fodet_id"=>$raw_detail->id, "fodj_delete"=>'0'])->get();
					
					// cek semua user yang sudah join forum
					$customer_join_all = DB::table('forum_detail_joins')
					->select('forum_detail_joins.id', 'cust_id', 'cust_name', 'cust_email', 'cust_address', 'cust_phone', 'cust_score', 'cust_coin', 'cust_gender', 'cust_avatar', 'cust_reseller')
					->leftJoin('customers', 'customers.id', '=', 'forum_detail_joins.cust_id')
					->where('forum_detail_joins.fodet_id', '=', $raw_detail->id)
					->where('fodj_delete', '=', '0')
					->get();
					//$customer_join_count = count($customer_join_all);
					$customer_join_id = '';
					$no=0;
					if($customer_join_all){
						foreach($customer_join_all as $raw_cust_join){
							$no++;
							$customer_join_id .= $raw_cust_join->cust_id.',';
							/*
							if($no<=10){
								//$forum_join_customer_name.= $raw_cust_join->cust_name.',';
								//$forum_join_customer_name.= str_replace([",","_"],[""," "],$raw_cust_join->cust_name).',';
							}
							*/
						}
						$customer_join_id = rtrim($customer_join_id,",");
						//$forum_join_customer_name = rtrim($forum_join_customer_name,",");
						//$forum_join_customer_name = str_replace(" ,",",",rtrim($forum_join_customer_name,","));
					}
					
					$detail_forum[] = [
						'forum_detail_id' => $raw_detail->id,
						'forum_detail_name' => $raw_detail->fodet_name,
						'forum_detail_description' => $raw_detail->fudet_description,
						'forum_detail_image' => $fodet_image,
						'forum_join' => count($forum_join_user),
						'forum_join_customer_id' => $customer_join_id,
						//'forum_detail_like' => $raw_detail->fodet_like,
						//'forum_detail_comment' => $raw_detail->fodet_commment,
						//'forum_detail_share' => $raw_detail->fodet_share,
						//'forum_detail_view' => $raw_detail->fodet_view,
					];
				}
				
				if (!empty($row->foc_icon)) {
                	$image_icon = $this->url_server."upload/forum/".$row->foc_icon;
                } else {
					$image_icon = '';
				}
				
				// tampilkan hanya di event running !!!
				if($row->id==5){
					$data_challenge_running = $data_challenge;
				} else { $data_challenge_running=NULL; }
				
                $results[] = [
                    'forum_cat_id' => $row->id,
					'forum_cat_name' => $row->foc_name,
					'forum_cat_short_name' => $row->foc_short_name,
					'forum_cat_slug' => $row->foc_slug,
					'forum_cat_description' => $row->foc_description,
					//'forum_view' => (int)$row->fo_view,
                    'forum_cat_image' => $image_url,
                    'forum_cat_icon' => $image_icon,
					'forum_admin' => $row->u_name,
					'forum_count' => (int)count($check_forum),
					'forum_detail' => $detail_forum,
					'forum_challenge' => $data_challenge_running,
					//'forum_join' => $flag_join,
					//'forum_cat_date' => $row->created_at,
					//'admin_avatar' => "https://avatar.iran.liara.run/public/boy?username=scott",
                ];
				// update jika melihat detail data
				/*
				if(!empty($forum_id)){
					$updated_date = [
						'fo_view' => $row->fo_view+1,
					];
					DB::table("forums")->where('id', $row->id)->update($updated_date);
				}
				*/
			}
		
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success'
				],
				/*
				'info' => [
					'title' => $forum_home->fh_title,
					'image' => 'https://topsystem.id/public/upload/'.$forum_home->fh_image,
					'description' => $forum_home->fh_description,
				],
				*/
				'results' => $results,
				//'challenge' => $data_challenge,
			];
        } else {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data not found'
				],
				/*
				'info' => [
					'title' => $forum_home->fh_title,
					'image' => 'https://topsystem.id/public/upload/'.$forum_home->fh_image,
					'description' => $forum_home->fh_description,
				],
				*/
				'results' => $results,
			];
		}
        return response()->json($data);
    }
	
	// join Forum
	public function joinForum(Request $request)
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
		
		$forum_detail_id = $request->forum_detail_id;
		$cust_id = $request->cust_id;
		$customer = DB::table("customers")->where("id","=",$cust_id)->first();
		
		if(empty($forum_detail_id) || empty($cust_id)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field forum_detail_id dan cust_id tidak boleh kosong'
                ]
            ];
            return response()->json($data);
		}
		
		$row_forum = DB::table("forum_details")->where("id","=",$forum_detail_id)->first();
		// jika forum tidak ditemukan
		if(!$row_forum){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data tidak ditemukan'
                ]
            ];
            return response()->json($data);
		}
		
		$row_forum_join = DB::table("forum_detail_joins")->where("fodet_id","=",$forum_detail_id)->where("cust_id","=",$cust_id)->where("fodj_delete","=",'0')->first();
		if(!$row_forum_join){
		    
		    if($row_forum->foc_id == 2){
				//cek join forum community, jika sudah pernah maka di unsubscribe forum tersebut...
				$customer_join_community = DB::table('forum_detail_joins')
				->select('forum_detail_joins.id', 'cust_id', 'cust_name', 'cust_email', 'cust_address', 'cust_phone', 'cust_score', 'cust_coin', 'cust_gender', 'cust_avatar', 'cust_reseller')
				->leftJoin('forum_details', 'forum_details.id', '=', 'forum_detail_joins.fodet_id')
				->leftJoin('customers', 'customers.id', '=', 'forum_detail_joins.cust_id')
				->where("foc_id","=",'2') // kusus forum komunitas 
				->where("forum_detail_joins.cust_id","=",$cust_id)
				->where('fodj_delete', '=', '0')
				->get();
				if($customer_join_community && $customer->cust_role!='admin'){
					foreach($customer_join_community as $raw_cust_join){
						// lakukan update un-subscribe forum community...
						$data_update = [
							'fodj_delete' => 1,
							'updated_at' => date("Y-m-d H:i:s"),
						];
						$save_update = DB::table("forum_detail_joins")->where('id', $raw_cust_join->id)->update($data_update);
					}
				}
			}
		    
			$data_save = [
				'fodet_id' => $forum_detail_id,
				'cust_id' => $cust_id,
				'created_at' => date("Y-m-d H:i:s"),
			];
			$saves = DB::table("forum_detail_joins")->insertGetId(array_merge($data_save));
			
			// save log score
			if(!empty($cust_id)){
				$arr_cust = DB::table("customers")->where("id","=",$cust_id)->first();
				/*
				// cek sudah pernah belum baca detail
				$arr_scores = DB::table("customer_scores")
							  ->where("cust_id","=",$arr_cust->id)
							  ->where("cscore_lookup_id","=",$forum_detail_id)
							  ->where("cscore_type","=","forum topscore")
							  ->first();
				if($arr_cust && empty($arr_scores)){
					$data_score = [
						'cust_id' => $arr_cust->id,
						'cscore_type' => 'forum topscore',
						'cscore_lookup_id' => $forum_detail_id,
						'cscore_description' => 'Join forum topscore : '.$row_forum->fodet_name,
						'cscore_value' => 1,
						'created_at' => date("Y-m-d H:i:s"),
					];
					DB::table("customer_scores")->insertGetId($data_score);
					
					// update nilai score customers...
					DB::table("customers")->where('id', $cust_id)->update([
						'cust_score' => ($arr_cust->cust_score+1), 
					]);
				}
				*/
			}
			
			
		} else {
			$updated_date = [
				'updated_at' => date("Y-m-d H:i:s"),
			];
			DB::table("forum_detail_joins")->where('id', $row_forum_join->id)->update($updated_date);
		}
		
        // tampilkan respon data		
        $data = [
            'status' => [
                'code' => 200,
                'description' => 'Success'
            ],
        ];
        return response()->json($data);
    }
	
	// list detail forum berdasarkan forum category
	public function DetailForum(Request $request)
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
		
		$forum_detail_id = $request->forum_detail_id;
		$cust_id = $request->cust_id;
		if (!$forum_detail_id || !$cust_id) {
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field forum_detail_id dan cust_id tidak boleh kosong'
                ]
            ];
            return response()->json($data);
        }
		$forum = DB::table("forum_details")->where("id","=",$forum_detail_id)->where("fodet_delete","=","0")->first();
		if(!$forum){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Forum dengan ID "'.$forum_detail_id.'" tidak ditemukan',
                ]
            ];
            return response()->json($data);
		}
		/*
		// save log traffic
		$data_log = [
			'cust_id' => $request->cust_id,
			'ta_page_access' => "Forum Detail Topscore",
			'ta_description' => "Mengakses laman Forum Detail Topscore",
			'ta_lookup_id' => $forum_detail_id,
			'created_at' => date("Y-m-d H:i:s"),
		];
		$save_log = DB::table("traffic_apps")->insertGetId(array_merge($data_log));
		*/
		
		// data gambar forum
		if (!empty($forum->fodet_image)) {
        	$image_url_forum = $this->url_server."upload/forum/".$forum->fodet_image;
        } else {
			$image_url_forum = '';
		}
		
		// cek customer
		$customer = DB::table("customers")->where("id","=",$cust_id)->where("cust_delete","=","0")->first();
		if(!$customer){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Customer dengan ID "'.$cust_id.'" tidak ditemukan',
                ]
            ];
            return response()->json($data);
		}
		
		// cek join forum user
		$customer_join = DB::table("forum_detail_joins")->where("cust_id","=",$cust_id)->where("fodet_id","=",$forum_detail_id)->where("fodj_delete","=",'0')->first();
		/*
		if(!$customer_join){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Customr dengan ID "'.$cust_id.'" belum bergabung ke forum topscore dengan ID "'.$forum_detail_id.'"',
                ]
            ];
            return response()->json($data);
		}
		*/
		if($customer_join){ $flag_join=1; } else {$flag_join=0;}
		
		// $customer_join
		$customer_joins = DB::table('forum_detail_joins')
		->select('forum_detail_joins.id', 'cust_id', 'cust_name', 'cust_email', 'cust_address', 'cust_phone', 'cust_score', 'cust_coin', 'cust_gender', 'cust_avatar', 'cust_reseller')
        ->leftJoin('customers', 'customers.id', '=', 'forum_detail_joins.cust_id')
		->where('forum_detail_joins.fodet_id', '=', $forum->id)
		->where('fodj_delete', '=', '0')
		->get();
		$customer_join_count = count($customer_joins);
		$customer_join_id = '';
		$forum_join_customer_name = '';
		$no=0;
		if($customer_joins){
			foreach($customer_joins as $raw_cust_join){
			    $no++;
				$customer_join_id .= $raw_cust_join->cust_id.',';
				if($no<=10){
					//$forum_join_customer_name.= $raw_cust_join->cust_name.',';
					$forum_join_customer_name.= str_replace([",","_"],[""," "],$raw_cust_join->cust_name).',';
				}
			}
			$customer_join_id = rtrim($customer_join_id,",");
			//$forum_join_customer_name = rtrim($forum_join_customer_name,",");
			$forum_join_customer_name = str_replace(" ,",",",rtrim($forum_join_customer_name,","));
		}
		
		$customer_join_data = DB::table('forum_detail_joins')
		->select('forum_detail_joins.id', 'cust_id', 'cust_name', 'cust_email', 'cust_address', 'cust_phone', 'cust_score', 'cust_coin', 'cust_gender', 'cust_avatar', 'cust_reseller')
        ->leftJoin('customers', 'customers.id', '=', 'forum_detail_joins.cust_id')
		->where('forum_detail_joins.fodet_id', '=', $forum->id)
		->where('fodj_delete', '=', '0')
		->paginate(50);
		//->get();
		$customer_join=array();
		foreach($customer_join_data as $raw){
			/*
			if(empty($raw->cust_avatar)){
				$avatar_profile = $this->url_server.'upload/avatar/avatar.png';
			} else {
				if($raw->cust_gender == 'm'){
						$avatar_profile = "https://avatar.iran.liara.run/public/boy";
				} else {
					$avatar_profile = "https://avatar.iran.liara.run/public/girl";
				}
			}
			*/
			if(empty($raw->cust_avatar)){
				if($raw->cust_gender == 'm'){
					$avatar_profile = "https://avatar.iran.liara.run/public/boy?username=scott";
				} else {
					$avatar_profile = "https://avatar.iran.liara.run/public/girl?username=maria";
				}
			} else {
				$avatar_profile = $raw->cust_avatar;
			}
			
			$customer_join[] = [
				'cust_id' => $raw->cust_id,
				'cust_name' => $raw->cust_name,
				'cust_email' => $raw->cust_email,
				'cust_phone' => $raw->cust_phone,
				'cust_address' => $raw->cust_address,
				'cust_coin' => $raw->cust_coin,
				'cust_score' => $raw->cust_score,
				'cust_avatar_join' => $avatar_profile,
				'cust_reseller' => $raw->cust_reseller,
			];
		}

        //$forum_home = DB::table("forum_homes")->first();
		
        $results = array();
		$comment = array();
        if (!empty($forum)) {
        
        // cek user sudah join atau belum? (forum_details)
		$customer_join_personal = DB::table('forum_detail_joins')
		->select('forum_detail_joins.id', 'fodet_id')
        ->leftJoin('forum_details', 'forum_details.id', '=', 'forum_detail_joins.fodet_id')
		//->where('forum_detail_joins.fodet_id', '=', $forum->id)
		->where('forum_details.foc_id', '=', '2') // kusus komunitas ***
		->where('forum_detail_joins.cust_id', '=', $cust_id)
		->where('fodj_delete', '=', '0')
		->first();
		if($customer_join_personal){$forumid_join=$customer_join_personal->fodet_id;} else {$forumid_join=NULL;}
		// jika bukan forum komunitas
		//if($forum->foc_id!='2'){$forumid_join=NULL;} else {$forumid_join=$forumid_join;}
		//if($forum->foc_id!='2'){$forumid_join=NULL; $forum_detail_community=0;} else {$forumid_join=$forumid_join; $forum_detail_community=1;}
		if($forum->foc_id!='2'){
			/*
			$forumid_join=NULL; 
			$forum_detail_community=0;
			$event_list = NULL;
			$event_active = NULL;
			$voucher_active = NULL;
			$game_score = NULL;
			*/
			// forum kusus running!
			if($forum->foc_id=='5'){
				$forumid_join=NULL; 
				$forum_detail_community=1;
				$event_list = $this->CekEvent($cust_id,$forum->fodet_cities,$running=true);
    			$event_active = $this->CekEventActive($cust_id, $forum->fodet_cities,$running=true);
    			$voucher_active = $this->CekVoucher($cust_id);
    			$game_score = NULL;
			} else {
				$forumid_join=NULL; 
				$forum_detail_community=0;
				$event_list = NULL;
				$event_active = NULL;
				$voucher_active = NULL;
				$game_score = NULL;
			}
		} else {
		    if($customer->cust_role=='admin'){
				$forumid_join=NULL; 
				$forum_detail_community=1;
				$event_list = $this->CekEvent($cust_id,$forum->fodet_cities);
    			$event_active = $this->CekEventActive($cust_id, $forum->fodet_cities);
    			$voucher_active = $this->CekVoucher($cust_id);
    			$game_score = $this->tebakScore($cust_id);
			} else {
    			$forumid_join=$forumid_join; 
    			$forum_detail_community=1;
    			$event_list = $this->CekEvent($cust_id,$forum->fodet_cities);
    			$event_active = $this->CekEventActive($cust_id, $forum->fodet_cities);
    			$voucher_active = $this->CekVoucher($cust_id);
    			$game_score = $this->tebakScore($cust_id);
			}
		}
        
        $data = [
            'status' => [
                'code' => 200,
                'description' => 'Success'
            ],
			/*
			'info' => [
				'title' => $forum_home->fh_title,
				'image' => 'https://topsystem.id/public/upload/'.$forum_home->fh_image,
				'description' => $forum_home->fh_description,
			],
			*/
			'forum_detail' => [
				'forum_detail_id' => $forum->id,
				'forum_detail_name' => $forum->fodet_name,
				'forum_detail_description' => $forum->fudet_description,
				'forum_detail_image' => $image_url_forum,
				'forum_cust_join' => $flag_join,
				'forum_join' => $customer_join_count,
				'forum_join_customer_id' => $customer_join_id,
				'forum_join_customer_name' => $forum_join_customer_name,
				'forum_detail_join' => $forumid_join,
				'forum_detail_community' => $forum_detail_community,
			],
			'customer_join' => $customer_join,
			/*
			'event' => $this->CekEvent($cust_id,$forum->fodet_cities),
			'event_active' => $this->CekEventActive($cust_id, $forum->fodet_cities),
			'voucher' => $this->CekVoucher($cust_id),
			'game_score' => $this->tebakScore($cust_id),
			*/
			'event' => $event_list,
			'event_active' => $event_active,
			'voucher' => $voucher_active,
			'game_score' => $game_score,
        ];
        } else {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data not found'
				],
				/*
				'info' => [
					'title' => $forum_home->fh_title,
					'image' => 'https://topsystem.id/public/upload/'.$forum_home->fh_image,
					'description' => $forum_home->fh_description,
				],
				*/
				'forum_detail' => [],
				'customer_join' => $customer_join,
			];
		}
        return response()->json($data);
    }
	
	
	
	
	
	// Posting komentar
	public function postComment(Request $request)
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
		
		$fblog_id = $request->fblog_id;
		$cust_id = $request->cust_id;
		$fobc_comment = $request->comment;
		
		if(empty($fblog_id) || empty($cust_id) || empty($fobc_comment)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field fblog_id, fobc_comment dan cust_id tidak boleh kosong'
                ]
            ];
            return response()->json($data);
		}
		
		$row_blogforum = DB::table("forum_blogs")->where("id","=",$fblog_id)->first();
		// jika forum tidak ditemukan
		if(!$row_blogforum){
			// $saves = DB::table("customer_address")->insertGetId(array_merge($data));
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data blog forum dengan ID "'.$fblog_id.'" tidak ditemukan'
                ]
            ];
            return response()->json($data);
		}
		
		$row_blogcomment = DB::table("forum_blog_comments")->where("fobl_id","=",$fblog_id)->where("cust_id","=",$cust_id)->first();
		if(!$row_blogcomment){
			$data_save = [
				'fobl_id' => $fblog_id,
				'cust_id' => $cust_id,
				'fobc_comment' => $fobc_comment,
				'created_at' => date("Y-m-d H:i:s"),
			];
			$saves = DB::table("forum_blog_comments")->insertGetId(array_merge($data_save));
		} else {
			/*
			$updated_date = [
				'updated_at' => date("Y-m-d H:i:s"),
			];
			DB::table("forum_blog_comments")->where('id', $row_blogcomment->id)->update($updated_date);
			*/
			$data_save = [
				'fobl_id' => $fblog_id,
				'cust_id' => $cust_id,
				'fobc_comment' => $fobc_comment,
				'created_at' => date("Y-m-d H:i:s"),
			];
			$saves = DB::table("forum_blog_comments")->insertGetId(array_merge($data_save));
		}
		
        // tampilkan respon data		
        $data = [
            'status' => [
                'code' => 200,
                'description' => 'Success'
            ],
        ];
        return response()->json($data);
    }
	
	// like komentar blog
	public function likeComment(Request $request)
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
		
		$fblog_id = $request->fblog_id;
		$cust_id = $request->cust_id;
		$fbcomment_id = $request->fbcomment_id;
		$fbcomment_like = $request->fbcomment_like;
		
		if(empty($fblog_id) || empty($cust_id) || empty($fbcomment_id)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field fblog_id, fbcomment_id dan cust_id tidak boleh kosong'
                ]
            ];
            return response()->json($data);
		}
		
		$row_blogforum = DB::table("forum_blogs")->where("id","=",$fblog_id)->first();
		// jika forum tidak ditemukan
		if(!$row_blogforum){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data blog forum dengan ID "'.$fblog_id.'" tidak ditemukan'
                ]
            ];
            return response()->json($data);
		}
		
		$row_commentblog = DB::table("forum_blog_comments")->where("id","=",$fbcomment_id)->first();
		if(!$row_commentblog){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data komentar blog forum dengan ID "'.$fblog_id.'" tidak ditemukan'
                ]
            ];
            return response()->json($data);
		}
		
		// cek jika sudah pernah d like
		$row_likecoment = DB::table("forum_blog_comment_likes")
				->where("fobc_id","=",$fbcomment_id)
				->where("cust_id","=",$cust_id)
				->first();
		if(!$row_likecoment){
			$data_save = [
					'fobc_id' => $fbcomment_id,
					'cust_id' => $cust_id,
					'fobclk_active' => $fbcomment_like,
					'created_at' => date("Y-m-d H:i:s"),
					//'updated_at' => date("Y-m-d H:i:s"),
			];
			$saves = DB::table("forum_blog_comment_likes")->insertGetId(array_merge($data_save));
			// update count like...
			$updated_date = [
				'updated_at' => date("Y-m-d H:i:s"),
				'fobc_like' => ($row_commentblog->fobc_like+1),
			];
			DB::table("forum_blog_comments")->where('id', $row_commentblog->id)->update($updated_date);
		} else {
			$updated_date = [
				'updated_at' => date("Y-m-d H:i:s"),
				'fobclk_active' => $fbcomment_like,
			];
			DB::table("forum_blog_comment_likes")->where('id', $row_likecoment->id)->update($updated_date);
			if($fbcomment_like==0 && $row_likecoment->fobclk_active==1){
				// update count like...
				$updated_date = [
					'updated_at' => date("Y-m-d H:i:s"),
					'fobc_like' => ($row_commentblog->fobc_like-1),
				];
				DB::table("forum_blog_comments")->where('id', $row_commentblog->id)->update($updated_date);
			} 
			if($fbcomment_like==1 && $row_likecoment->fobclk_active==0){
				// update count like...
				$updated_date = [
					'updated_at' => date("Y-m-d H:i:s"),
					'fobc_like' => ($row_commentblog->fobc_like+1),
				];
				DB::table("forum_blog_comments")->where('id', $row_commentblog->id)->update($updated_date);
			}
		}
        // tampilkan respon data		
        $data = [
            'status' => [
                'code' => 200,
                'description' => 'Success'
            ],
        ];
        return response()->json($data);
    }
	
	
	// leave forum
	public function leaveForum(Request $request)
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
		
		$forum_detail_id = $request->forum_detail_id;
		$cust_id = $request->cust_id;
		
		if(empty($forum_detail_id) || empty($cust_id)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field forum_detail_id dan cust_id tidak boleh kosong'
                ]
            ];
            return response()->json($data);
		}
		
		$row_forum = DB::table("forum_details")->where("id","=",$forum_detail_id)->first();
		// jika forum tidak ditemukan
		if(!$row_forum){
			// $saves = DB::table("customer_address")->insertGetId(array_merge($data));
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data forum dengan ID "'.$forum_detail_id.'" tidak ditemukan'
                ]
            ];
            return response()->json($data);
		}
		
		$arr_cust = DB::table("customers")->where("id","=",$cust_id)->first();
		if(!$arr_cust){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data customer dengan ID "'.$cust_id.'" tidak ditemukan'
                ]
            ];
            return response()->json($data);
		}
		
		// update leave forum
		/*
		$updated_leave = [
			'foj_delete' => 1,
			'updated_at' => date("Y-m-d H:i:s"),
		];
		DB::table("forums")->where('id', $row_forum->id)->update($updated_leave);
		*/
		
		// cek sudah pernah belum baca detail
		/*
		$arr_scores = DB::table("customer_scores")
					  ->where("cust_id","=",$arr_cust->id)
					  ->where("cscore_lookup_id","=",$forum_id)
					  ->where("cscore_type","=","forum")
					  ->orderBy("id","DESC")
					  ->first();
		*/
		//if($arr_scores){
		if(1==1){
			$row_join = DB::table("forum_detail_joins")
				  ->where("cust_id","=",$cust_id)
				  ->where("fodet_id","=",$forum_detail_id)
				  ->where("fodj_delete","=",'0')
				  ->first();
			if($row_join){
				// update leave forum
				$updated_leave = [
					'fodj_delete' => 1,
					'updated_at' => date("Y-m-d H:i:s"),
				];
				DB::table("forum_detail_joins")->where('id', $row_join->id)->update($updated_leave);
			}			
		}
		
        // tampilkan respon data		
        $data = [
            'status' => [
                'code' => 200,
                'description' => 'Success'
            ],
        ];
        return response()->json($data);
    }
    
    // Posting Feed ** Forum ***
	public function saveFeeds(Request $request)
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
		$feed_description = $request->feed_description;
		$file_upload = $request->hasFile('file_upload');
		if($cust_id=='' || !$file_upload){
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Field cust_id dan file_upload harus di isi!'
				],
			];
		} else {
			// proses save
			$data = [
				'cust_id' => $cust_id,
				'feed_description' => $feed_description,
				'foc_id' => '1',
				'created_at' => date("Y-m-d H:i:s"),
			];
						
			//$check = $this->checkData("forum_feeds",'id', ['cust_id' => $cust_id, 'lookup_id' => $lookup_id, 'ra_type' => $ra_type]);
			//if(empty($check)){
			$data_upload = array();
			if ($request->hasFile('file_upload')) {				
				$request->validate([
						'file_upload' => 'required|file|mimes:jpg,jpeg,png,gif',
				]);
				$image = $request->file('file_upload');
				$input['fileName'] = 'feeds'.rand(10000,99999).time().'.'.$image->extension();
			
				$destinationPath = public_path('/forum');
				$image->move($destinationPath, $input['fileName']);
				
				$data_upload = ['feed_image' => $input['fileName']];
			}
			
			// save database...
			$saves = DB::table("forum_feeds")->insertGetId(array_merge($data, $data_upload));
			if($saves){
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
						'description' => 'Data gagal disimpan!'
					],
				];
			}
			
		}
        return response()->json($data);

	}
	
	// delete feeds
	public function deleteFeeds(Request $request)
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
		
		$feed_id = $request->feed_id;
		$cust_id = $request->cust_id;
		
		if(empty($feed_id) || empty($cust_id)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field feed_id dan cust_id tidak boleh kosong!'
                ]
            ];
            return response()->json($data);
		}
		
		$row_feed = DB::table("forum_feeds")->where(["id"=>$feed_id, "cust_id"=>$cust_id])->first();
		// jika forum tidak ditemukan
		if(!$row_feed){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data feed tidak ditemukan!'
                ]
            ];
            return response()->json($data);
		}
		
		$arr_cust = DB::table("customers")->where("id","=",$cust_id)->first();
		if(!$arr_cust){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data customer tidak ditemukan!'
                ]
            ];
            return response()->json($data);
		}
		
		$updated_feed = [
				'feed_delete' => 1,
				'updated_at' => date("Y-m-d H:i:s"),
			];
		$save_update = DB::table("forum_feeds")->where('id', $row_feed->id)->update($updated_feed);

		
        if($save_update){
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
					'description' => 'Data feed gagal dihapus!'
				],
			];
		}
        return response()->json($data);
    }
    
    // list feeds
	public function listFeeds(Request $request)
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
		
		// save log traffic
		/*
		$data_log = [
			'cust_id' => $request->cust_id,
			'ta_page_access' => "Forum",
			'ta_description' => "Mengakses laman forum aplikasi",
			'created_at' => date("Y-m-d H:i:s"),
		];
		$save_log = DB::table("traffic_apps")->insertGetId(array_merge($data_log));
		*/
		
		$cust_id = $request->cust_id;
		if(empty($cust_id)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id tidak boleh kosong!'
                ]
            ];
            return response()->json($data);
		}
		
		$arr_cust = DB::table("customers")->where("id","=",$cust_id)->first();
		if(!$arr_cust){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data customer tidak ditemukan!'
                ]
            ];
            return response()->json($data);
		}
		
        $query = DB::table('forum_feeds')
		->select('forum_feeds.id', 'feed_description', 'feed_image', 'feed_like', 'feed_commment', 'feed_share', 'feed_view', 'forum_feeds.created_at', 'cust_name', 'cust_phone', 'forum_feeds.cust_id')
		->leftJoin('customers', 'customers.id', '=', 'forum_feeds.cust_id')
		->where('feed_delete', '=', "0")
		/*
		->where(function($w) use($forum_search, $forum_cat_id) {
			if (!empty($forum_search)) {
				$w->where(DB::raw("CONCAT(foc_name, ' ', foc_description)"), 'LIKE', "%$forum_search%");
			}
			if(!empty($forum_cat_id)){
				$w->where('forum_categories.id', '=', $forum_cat_id);
			}
		})
		*/
        ->orderBy("forum_feeds.id",'DESC')
		->paginate(10);
		
        $results = array();
        if (!empty($query)) {
            foreach ($query as $row) {
				if (!empty($row->feed_image)) {
                	$feed_image = url('public/forum/'.$row->feed_image);
                } else {
					$feed_image = '';
				}
                
                // cek postingan sendri atau user lain
				if($row->cust_id==$cust_id){$feed_user=1;} else {$feed_user=0;}
				// cek like user (sudah like atau belum)
				$cek_like_user = DB::table("forum_feed_likes")->where(["feed_id"=>$row->id, "cust_id"=>$cust_id, "feedlike_active"=>"1"])->first();
				if($cek_like_user){$feed_like_user=1;} else {$feed_like_user=0;}
                
                $results[] = [
                    'feed_id' => $row->id,
					'feed_image' => $feed_image,
					'feed_description' => $row->feed_description,
					'feed_like' => (int)$row->feed_like,
					'feed_commment' => (int)$row->feed_commment,
					'feed_share' => (int)$row->feed_share,
					'feed_cust_name' => $row->cust_name,
					//'feed_cust_phone' => $row->cust_phone,
					'feed_cust_phone' => ($row->cust_phone? $row->cust_phone : ''),
					'feed_date' => $row->created_at,
					'feed_user' => $feed_user,
					'feed_like_user' => $feed_like_user,
                ];
			}
		
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success'
				],
				'results' => $results,
			];
        } else {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data tidak ditemukan!'
				],
				'results' => $results
			];
		}
        return response()->json($data);
    }
    
    // cek event selesai
	private function CekEvent($cust_id,$kota,$running=false)
    {
		$arr_customer = DB::table("customers")->where("id","=",$cust_id)->first();
		
        $query_data = DB::table("event_tops")
		->selectRaw('ts_event_tops.id as pid, et_title, et_start_date, et_start_time, et_end_date, et_end_time, et_address, et_banner, et_price, et_terms, et_description, et_quota, et_lat, et_lang, et_type, et_link_online, et_score, et_maps_url, et_category, evt_category, evt_name, et_city, et_tournament, et_drive')
		->leftJoin('event_types', 'event_types.id', '=', 'event_tops.evt_id')
		//->where('et_city', '=', $kota)
		->where('et_delete', '=', '0')
		->whereRaw("CONCAT(ts_event_tops.et_start_date,' ',ts_event_tops.et_start_time) <= '".date('Y-m-d H:i:s')."'")
		->where(function($w) use($running, $kota) {
			if($running==true){
				$w->where('evt_id', '=', '4'); // kusus running
			} else {
				$w->where('evt_id', '!=', '4'); // selain running
				$w->where('et_city', '=', $kota);
			}
		})
		->orderBy('et_end_date', 'DESC')
		->paginate(50);
        //->get();
        $results = array();
        if (!empty($query_data)) {
            foreach ($query_data as $row) {
				// https://www.google.com/maps/search/?api=1&query=28.6139,77.2090
				if($row->et_maps_url){
				    $et_maps_url = $row->et_maps_url;
				} else {
				    $et_maps_url = "https://www.google.com/maps/search/?api=1&query=".$row->et_lat.','.$row->et_lang;
				}
				
				if($row->et_drive){$et_drive=$row->et_drive;} else{$et_drive='';}
				
				//if($row->et_category!="eksekutif"){
				if(1==1){
                $results[] = [
					'event_id' => $row->pid,
					'event_category' => $row->evt_name,
					'event_type' => $row->et_type,
					'event_title' => $row->et_title,
					'event_start_date' => $row->et_start_date,
					'event_start_time' => $row->et_start_time,
					'event_end_date' => $row->et_end_date,
					'event_end_time' => $row->et_end_time,
					'event_address' => $row->et_address,
					'event_banner' => 'https://topsystem.id/upload/event/'.$row->et_banner,
					'event_terms' => $row->et_terms,
					'event_description' => $row->et_description,
					'event_lat' => $row->et_lat,
					'event_lang' => $row->et_lang,
					'event_score' => $row->et_score,
					'maps_url' => $et_maps_url,
					'event_city' => $row->et_city,
					'event_drive' => $et_drive,
                ];
				}
            }

        }
		
        return $results;
    }
    
    // cek voucher ***
	private function CekVoucher($cust_id)
    {
        $query_data = DB::table("apps_vouchers")
		->selectRaw('ts_apps_vouchers.id as pid, avc_code, avc_discount, avc_min_order, avc_type, avc_status, avc_cashback, avc_due_date, created_at, avc_title, avc_banner, avc_terms, avc_category, avc_to')
		->whereRaw("CONCAT(ts_apps_vouchers.avc_start,' ',ts_apps_vouchers.acv_start_time)<='".date("Y-m-d H:i:s")."'")
		->whereRaw("CONCAT(ts_apps_vouchers.avc_due_date,' ',ts_apps_vouchers.avc_end_time)>='".date("Y-m-d H:i:s")."'")
		->where('avc_delete', '=', '0')
		->where('avc_status', '=', '1')
		->orderBy('avc_code')
        ->get();
        $results = NULL;
        if (!empty($query_data)) {
            foreach ($query_data as $row) {
				// cek customer kusus customer
				if($row->avc_to == "customer"){
					$arr_cust_voucher = DB::table("apps_voucher_customers")
						->select("id")
						->where("cust_id","=",$cust_id)
						->where("avc_id","=",$row->pid)
						->where("avcust_status","=","1")
						->where("avcust_delete","=","0")
						->first();
					if($arr_cust_voucher){$show=1;} else {$show=0;}
				} else
				if($row->avc_to == "ultah"){
					// cek cust ultah
					$arr_customer = DB::table("customers")->where("id","=",$cust_id)->first();
					if($arr_customer){
						// cek sudah pernah digunakan dalam tahun sekarang?
						$cek_transaksiultah = DB::table("pos_transactions")
						->select('pos_transactions.id')
						->where("cust_id","=",$cust_id)
						->where("voucher_apps_id","=",$row->pid)
						->whereRaw("LEFT(ts_pos_transactions.created_at,4)='".date("Y")."'")
						->get();
						if($arr_customer->cust_bday && count($cek_transaksiultah) == 0){
							if(substr($arr_customer->cust_bday,-5) == date("m-d")){$show=1;} else {$show=0;}
						} else {$show=0;}
					} else {$show=0;}
				} else
				if($row->avc_to == "produk"){
					$show=0;
				}
				else { $show=1; }
				
				// check product stock
				if(!empty($row->avc_banner)){
					$banner = $this->url_server.'upload/event/'.$row->avc_banner;
				} else {
					$banner = '';
				}
				
				if($show==1){
					$results = [
						'voucher_id' => $row->pid,
						'discount_title' => $row->avc_title,
						'discount' => $row->avc_discount,
						'min_order' => $row->avc_min_order,
						'discount_type' => $row->avc_type,
						'cashback' => (int)$row->avc_cashback,
						'status_discount' => $row->avc_status,
						'banner' => $banner,
						'terms' => $row->avc_terms,
						'due_date' => $row->avc_due_date,
						'category' => $row->avc_category,
					];
				}
            }
		}
        return $results;
    }
	
	// event active ***
	private function CekEventActive($cust_id, $kota, $running=false)
    {
		$arr_customer = DB::table("customers")->where("id","=",$cust_id)->first();
		$type_event = '2';
        $row = DB::table("event_tops")
		->selectRaw('ts_event_tops.id as pid, et_title, et_start_date, et_start_time, et_end_date, et_end_time, et_address, et_banner, et_price, et_terms, et_description, et_quota, et_lat, et_lang, et_type, et_link_online, et_score, et_maps_url, et_category, evt_category, evt_name, et_city, et_tournament')
		->leftJoin('event_types', 'event_types.id', '=', 'event_tops.evt_id')
		//->where('et_city', '=', $kota)
		->where('et_delete', '=', '0')
		->whereRaw("CONCAT(ts_event_tops.et_start_date,' ',ts_event_tops.et_start_time) >= '".date('Y-m-d H:i:s')."'")
		->where(function($w) use($running, $kota) {
			if($running==true){
				$w->where('evt_id', '=', '4'); // kusus running
			} else {
				$w->where('evt_id', '!=', '4'); // selain running
				$w->where('et_city', '=', $kota);
			}
		})
		->orderBy('et_end_date', 'ASC')
		->first();
        //->get();
        $results = NULL;
        if ($row) {
            //foreach ($query_data as $row) {
            if(1==1){
                $event_users = DB::table("event_orders")
							   ->where("et_id","=",$row->pid)
							   ->where("payment_status","=","1")
							   ->sum('eo_qty');
				if($event_users>0){
					$jumlah_user = (int)$event_users;
				} else {
					$jumlah_user = 0;
				}

				if(empty($row->et_quota)){$quota=0;} else {$quota=$row->et_quota;}
				
				if($type_event == "2"){ // upcoming
				    $jumlah_user = $jumlah_user;
				    $quota = $quota;
				} else
				if($type_event == "1"){ // berlangsung
				    $jumlah_user = 0;
				    $quota = 0;
				} else { // selesai
				    $jumlah_user = 0;
				    $quota = 0;
				}
				
				// jika mencari salah 1 data event
				if (!empty($id_event)) {
				    $jumlah_user = (int)$event_users;
				    $quota = $row->et_quota;
				    
				    // cek jika sudah full, jadikan nol
					if($quota<=$jumlah_user){$quota=0; $jumlah_user=0;}
				    
				    //$quota = $quota;
				    if($row->et_start_date.' '.$row->et_start_time <= date("Y-m-d H:i:s")){
						$quota = 0;
					}
				} else {
					// cek jika sudah full, jadikan nol
					if($row->et_quota<=(int)$event_users){$quota=0; $jumlah_user=0;}
				}
				
				if($row->et_type == "online"){
					$link_webinar = $row->et_link_online;
				} else {
					$link_webinar = "";
				}
				
				$cust_terdaftar=DB::table("event_orders")
					->where("u_id",'=',$cust_id)
					->where("payment_status",'=','1')
					->where("et_id",'=',$row->pid)
					->first();
				if($cust_terdaftar){ $cust_terdaftar=1; } else { $cust_terdaftar=0; }
								
				// https://www.google.com/maps/search/?api=1&query=28.6139,77.2090
				if($row->et_maps_url){
				    $et_maps_url = $row->et_maps_url;
				} else {
				    $et_maps_url = "https://www.google.com/maps/search/?api=1&query=".$row->et_lat.','.$row->et_lang;
				}
				
				
				
				// cek jika event coming soon
				$event_coming = '0';
				$start_date_check = $row->et_start_date.' '.$row->et_start_time;
				$end_date_check = date("Y-m-d H:i:s", strtotime('-14 days', strtotime($start_date_check)));
				if($end_date_check<=date("Y-m-d H:i:s")){$event_coming=0;} else {$event_coming=1;}
				
				if($row->et_category!="eksekutif"){
					$results = [
						'event_id' => $row->pid,
						'event_category' => $row->evt_name,
						'event_type' => $row->et_type,
						'event_title' => $row->et_title,
						'event_start_date' => $row->et_start_date,
						'event_start_time' => $row->et_start_time,
						'event_end_date' => $row->et_end_date,
						'event_end_time' => $row->et_end_time,
						'event_address' => $row->et_address,
						'event_banner' => $this->url_server.'upload/event/'.$row->et_banner,
						'event_price' => $row->et_price,
						'event_quota' => $quota, // tambahan
						'event_user_order' => $jumlah_user, // tambahan
						'event_terms' => $row->et_terms,
						'event_description' => $row->et_description,
						'event_lat' => $row->et_lat,
						'event_lang' => $row->et_lang,
						'event_score' => $row->et_score,
						'maps_url' => $et_maps_url,
						'event_city' => $row->et_city,
						'event_coming_soon' => $event_coming,
						'event_open_reg' => $end_date_check,
						'event_tournament' => $row->et_tournament,
					];
				}
            }// end foreach

        } // end if
		
        return $results;
    }
    
    // game tebak skor
	private function tebakScore($cust_id)
    {
		$arr_customer = DB::table("customers")->where("id","=",$cust_id)->first();

        $row = DB::table("game_scores")
		->select('game_scores.id as pid', 'club_id_1', 'club_id_2', 'gms_score_1', 'gms_score_2', 'gms_name', 'gms_description', 'gms_view', 'gms_score', 'gms_coin', 'gms_startdate', 'gms_starttime', 'gms_enddate', 'gms_endtime', 'gms_active', 'game_scores.created_at')
		//->leftJoin('event_types', 'event_types.id', '=', 'event_tops.evt_id')
		->whereRaw("CONCAT(ts_game_scores.gms_startdate,' ',ts_game_scores.gms_starttime) <= '".date('Y-m-d H:i:s')."'")
		->whereRaw("CONCAT(ts_game_scores.gms_enddate,' ',ts_game_scores.gms_endtime) > '".date('Y-m-d H:i:s')."'")
		->where('gms_delete', '=', '0')
		->where('gms_active', '=', '1')
		->orderBy('gms_enddate', 'ASC')
		->first();
        //->get();
        $results = NULL;
        if ($row) {
            //foreach ($query_data as $row) {
            if(1==1){
				$club1 = DB::table("clubs")->where("id",'=',$row->club_id_1)->first();
				$club2 = DB::table("clubs")->where("id",'=',$row->club_id_2)->first();
                $user_answer = DB::table("game_score_answers")
							   ->where("gms_id","=",$row->pid)
							   ->where("cust_id","=",$cust_id)
							   ->where("gsa_delete","=","0")
							   ->get();
				if(count($user_answer)>0){
					$sudah_jawab = 1;
				} else {
					$sudah_jawab = 0;
				}
				
				if($row->gms_coin>0){
					$game_score_benefit='Koin';
					$game_score_value = $row->gms_coin;
				} else {
					$game_score_benefit='Skor';
					$game_score_value = $row->gms_score;
				}
				
				// cek logo klub
				$logo_klub_1 = $this->url_server.'upload/club/'.$club1->club_image;
				$logo_klub_2 = $this->url_server.'upload/club/'.$club2->club_image;
				
				$cust_answer = DB::table("game_score_answers")->where(['gms_id' => $row->pid, "gsa_delete" => '0'])->get();
				
				if(1==1){
					$results = [
						'game_score_id' => $row->pid,
						'game_score_name' => $row->gms_name,
						'game_score_description' => $row->gms_description,
						//'game_score_view' => (int)$row->gms_view,
						'game_score_view' => count($cust_answer),
						'game_score_benefit' => $game_score_benefit,
						'game_score_value' => (int)$game_score_value,
						'game_score_club_1' => $club1->club_name,
						'game_score_club_1_image' => $logo_klub_1,
						'game_score_club_2' => $club2->club_name,
						'game_score_club_2_image' => $logo_klub_2,
						'game_score_end_date' => $row->gms_enddate.' '.$row->gms_endtime,
						'game_score_user_answer' => $sudah_jawab,
					];
				}
            }// end foreach

        } // end if
		
        return $results;
    }
    
    // jawab tebak score
	public function scoreAnswer(Request $request)
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
		
		$game_score_id = $request->game_score_id;
		$cust_id = $request->cust_id;
		
		if(empty($game_score_id) || empty($cust_id)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field game_score_id cust_id tidak boleh kosong'
                ]
            ];
            return response()->json($data);
		}
		
		$row_gamescore = DB::table("game_scores")->where("id","=",$game_score_id)->first();
		// jika forum tidak ditemukan
		if(!$row_gamescore){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data game tebak skor tidak ditemukan!'
                ]
            ];
            return response()->json($data);
		}

        $tgl_mulai = date('Y-m-d', strtotime($row_gamescore->gms_enddate . " -2 days"));
		if(($tgl_mulai.' '.$row_gamescore->gms_starttime) > date("Y-m-d H:i:s")){
		//if(($row_gamescore->gms_startdate.' '.$row_gamescore->gms_starttime) > date("Y-m-d H:i:s")){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Oops!, tebak skor belum dibuka!'
                ]
            ];
            return response()->json($data);
		}
		
		if(($row_gamescore->gms_enddate.' '.$row_gamescore->gms_endtime) < date("Y-m-d H:i:s")){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Oops!, tebak skor sudah ditutup!'
                ]
            ];
            return response()->json($data);
		}
		
		$row_answer = DB::table("game_score_answers")->where("gms_id","=",$game_score_id)->where("cust_id","=",$cust_id)->where("gsa_delete","=",'0')->first();
		if(!$row_answer){
			$data_save = [
				'gms_id' => $game_score_id,
				'cust_id' => $cust_id,
				'gsa_score_1' => $request->score_1,
				'gsa_score_2' => $request->score_2,
				'gsa_description' => $request->description,
				'created_at' => date("Y-m-d H:i:s"),
			];
			$saves = DB::table("game_score_answers")->insertGetId(array_merge($data_save));
			
			// save log score
			/*
			if(!empty($cust_id)){
				$arr_cust = DB::table("customers")->where("id","=",$cust_id)->first();
			}
			*/
			
		} else {
		    /*
			$updated_date = [
				'updated_at' => date("Y-m-d H:i:s"),
			];
			DB::table("game_score_answers")->where('id', $row_answer->id)->update($updated_date);
			*/
			// tampilkan respon data		
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Oops!, kamu sudah mengisi tebak skor!'
				],
			];
			return response()->json($data);
		}
		
        // tampilkan respon data		
        $data = [
            'status' => [
                'code' => 200,
                'description' => 'Success'
            ],
        ];
        return response()->json($data);
    }
    
    // list jawaban tebak skor
	public function userlistAnswerscore(Request $request)
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
		
		// save log traffic
		/*
		$data_log = [
			'cust_id' => $request->cust_id,
			'ta_page_access' => "Forum",
			'ta_description' => "Mengakses laman forum aplikasi",
			'created_at' => date("Y-m-d H:i:s"),
		];
		$save_log = DB::table("traffic_apps")->insertGetId(array_merge($data_log));
		*/
		
		$game_score_id = $request->game_score_id;
		$cust_id = $request->cust_id;
		$filter_data = $request->filter_data;
		
		if(empty($game_score_id) || empty($cust_id)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field game_score_id cust_id tidak boleh kosong'
                ]
            ];
            return response()->json($data);
		}
		
		$raw_user = DB::table("customers")->where("id","=",$cust_id)->first();
		$row_gamescore = DB::table("game_scores")->where("id","=",$game_score_id)->first();
		// jika forum tidak ditemukan
		if(!$row_gamescore){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data game tebak skor tidak ditemukan!'
                ]
            ];
            return response()->json($data);
		}

		$club1 = DB::table("clubs")->where("id",'=',$row_gamescore->club_id_1)->first();
		$club2 = DB::table("clubs")->where("id",'=',$row_gamescore->club_id_2)->first();
				
		// cek logo klub
		$logo_klub_1 = $this->url_server.'upload/club/'.$club1->club_image;
		$logo_klub_2 = $this->url_server.'upload/club/'.$club2->club_image;
		

        $query = DB::table('game_score_answers')
		->select('game_score_answers.id', 'gms_id', 'gsa_score_1', 'gsa_score_2', 'gsa_description', 'gsa_win', 'cust_name', 'cust_phone', 'cust_gender', 'game_score_answers.created_at', 'game_score_answers.cust_id', 'cust_avatar', 'cust_gender')
		->leftJoin('customers', 'customers.id', '=', 'game_score_answers.cust_id')
		->where('gms_id', '=', $game_score_id)
		->where('cust_delete', '=', "0")
		->where('gsa_delete', '=', "0")
		->where(function($w) use($filter_data) {
			if (!empty($filter_data)) {
				//$w->where(DB::raw("CONCAT(foc_name, ' ', foc_description)"), 'LIKE', "%$forum_search%");
				if($filter_data=='club1'){
					$w->whereRaw("ts_game_score_answers.gsa_score_1>ts_game_score_answers.gsa_score_2");
				} else 
				if($filter_data=='club2'){
					$w->whereRaw("ts_game_score_answers.gsa_score_1<ts_game_score_answers.gsa_score_2");
				} else
				if($filter_data=='draw'){
					$w->whereRaw("ts_game_score_answers.gsa_score_1=ts_game_score_answers.gsa_score_2");
				}
				
			}
		})
        ->orderBy("game_score_answers.id",'ASC')
		->paginate(10);
		
        $results = array();
        
        // cek jika user yang cek sudah mengisi tebak skor maka tampilkan di paling awal data nya...
		if(empty($request->get('page')) || $request->get('page')=="0" || $request->get('page')=="1"){
		
		$row = DB::table('game_score_answers')
		->select('game_score_answers.id', 'gms_id', 'gsa_score_1', 'gsa_score_2', 'gsa_description', 'gsa_win', 'cust_name', 'cust_phone', 'cust_gender', 'game_score_answers.created_at', 'game_score_answers.cust_id', 'cust_avatar', 'cust_gender')
		->leftJoin('customers', 'customers.id', '=', 'game_score_answers.cust_id')
		->where('gms_id', '=', $game_score_id)
		->where('cust_delete', '=', "0")
		->where('gsa_delete', '=', "0")
		->where('game_score_answers.cust_id', '=', $cust_id) // data sesuai customer
        ->orderBy("game_score_answers.id",'ASC')
		->first();
		if($row){
				if(empty($row->cust_avatar)){
					if($raw_user->cust_gender == 'm'){
						$avatar_profile = "https://avatar.iran.liara.run/public/boy?username=scott";
					} else {
						$avatar_profile = "https://avatar.iran.liara.run/public/girl?username=maria";
					}
				} else {
					$avatar_profile = $row->cust_avatar;
				}
				
				// cek user join di forum apa?
				$user_join_forum = DB::table("forum_detail_joins")
					->select('forum_detail_joins.id', 'fodet_name', 'fodet_cities')
					->leftJoin('forum_details', 'forum_details.id', '=', 'forum_detail_joins.fodet_id')
					->where(["cust_id"=>$row->cust_id, "fodj_delete"=>'0', "foc_id"=>'2'])
					->first();
				if($user_join_forum){
					$forum_customer = $user_join_forum->fodet_name;
				} else {
					$forum_customer = '';
				}
				
				$results[] = [
						'score_answer_id' => $row->id,
						'score_answer_cust_name' => $row->cust_name,
						'score_answer_cust_phone' => ($row->cust_phone? $row->cust_phone : ''),
						'score_answer_cust_avatar' => $avatar_profile,
						'score_answer_description' => $row->gsa_description,
						'score_answer_club_1' => $club1->club_name,
						'score_answer_club_1_image' => $logo_klub_1,
						'score_answer_club_1_skor' => (int)$row->gsa_score_1,
						'score_answer_club_2' => $club2->club_name,
						'score_answer_club_2_image' => $logo_klub_2,
						'score_answer_club_2_skor' => (int)$row->gsa_score_2,
						'score_answer_date' => $row->created_at,
						'forum_user' => $forum_customer,
						'my_answer' => 1,
				];
		}
		}
		
        if (!empty($query)) {
            foreach ($query as $row) {
				if(empty($row->cust_avatar)){
					if($row->cust_gender == 'm'){
						$avatar_profile = "https://avatar.iran.liara.run/public/boy?username=scott";
					} else {
						$avatar_profile = "https://avatar.iran.liara.run/public/girl?username=maria";
					}
				} else {
					$avatar_profile = $row->cust_avatar;
				}
				
				// cek user join di forum apa?
				$user_join_forum = DB::table("forum_detail_joins")
					->select('forum_detail_joins.id', 'fodet_name', 'fodet_cities')
					->leftJoin('forum_details', 'forum_details.id', '=', 'forum_detail_joins.fodet_id')
					->where(["cust_id"=>$row->cust_id, "fodj_delete"=>'0', "foc_id"=>'2'])
					->first();
				if($user_join_forum){
					$forum_customer = $user_join_forum->fodet_name;
				} else {
					$forum_customer = '';
				}

				if($cust_id!=$row->cust_id){
					$results[] = [
						'score_answer_id' => $row->id,
						'score_answer_cust_name' => $row->cust_name,
						'score_answer_cust_phone' => ($row->cust_phone? $row->cust_phone : ''),
						'score_answer_cust_avatar' => $avatar_profile,
						'score_answer_description' => $row->gsa_description,
						'score_answer_club_1' => $club1->club_name,
						'score_answer_club_1_image' => $logo_klub_1,
						'score_answer_club_1_skor' => (int)$row->gsa_score_1,
						'score_answer_club_2' => $club2->club_name,
						'score_answer_club_2_image' => $logo_klub_2,
						'score_answer_club_2_skor' => (int)$row->gsa_score_2,
						'score_answer_date' => $row->created_at,
						'forum_user' => $forum_customer,
						'my_answer' => 0,
					];
				}

			}
		    
		    // cek jika game tebak skor sudah ada jawaban
			$jumlah_isi_skor = 0;
			if($row_gamescore->gms_score_1!='' || $row_gamescore->gms_score_1=='0'){$jumlah_isi_skor++;}
			if($row_gamescore->gms_score_2!='' || $row_gamescore->gms_score_2=='0'){$jumlah_isi_skor++;} 
			if($jumlah_isi_skor==2){$show_win=1;} else {$show_win=0;}
		    
		    
		    // tampilkan jumlah semua yang jawab tebak skor
			$all_users = DB::table('game_score_answers')
			->select('game_score_answers.id', 'gms_id', 'gsa_score_1', 'gsa_score_2', 'gsa_description', 'gsa_win', 'cust_name', 'cust_phone', 'cust_gender', 'game_score_answers.created_at', 'game_score_answers.cust_id', 'cust_avatar', 'cust_gender')
			->leftJoin('customers', 'customers.id', '=', 'game_score_answers.cust_id')
			->where('gms_id', '=', $game_score_id)
			->where('cust_delete', '=', "0")
			->where('gsa_delete', '=', "0")
			->orderBy("game_score_answers.id",'ASC')
			->get();
			$club_1 = DB::table('game_score_answers')
			->select('game_score_answers.id', 'gms_id', 'gsa_score_1', 'gsa_score_2', 'gsa_description', 'gsa_win', 'cust_name', 'cust_phone', 'cust_gender', 'game_score_answers.created_at', 'game_score_answers.cust_id', 'cust_avatar', 'cust_gender')
			->leftJoin('customers', 'customers.id', '=', 'game_score_answers.cust_id')
			->where('gms_id', '=', $game_score_id)
			->where('cust_delete', '=', "0")
			->where('gsa_delete', '=', "0")
			->whereRaw("ts_game_score_answers.gsa_score_1>ts_game_score_answers.gsa_score_2")
			->orderBy("game_score_answers.id",'ASC')
			->get();
			$club_2 = DB::table('game_score_answers')
			->select('game_score_answers.id', 'gms_id', 'gsa_score_1', 'gsa_score_2', 'gsa_description', 'gsa_win', 'cust_name', 'cust_phone', 'cust_gender', 'game_score_answers.created_at', 'game_score_answers.cust_id', 'cust_avatar', 'cust_gender')
			->leftJoin('customers', 'customers.id', '=', 'game_score_answers.cust_id')
			->where('gms_id', '=', $game_score_id)
			->where('cust_delete', '=', "0")
			->whereRaw("ts_game_score_answers.gsa_score_1<ts_game_score_answers.gsa_score_2")
			->where('gsa_delete', '=', "0")
			->orderBy("game_score_answers.id",'ASC')
			->get();
			$club_draw = DB::table('game_score_answers')
			->select('game_score_answers.id', 'gms_id', 'gsa_score_1', 'gsa_score_2', 'gsa_description', 'gsa_win', 'cust_name', 'cust_phone', 'cust_gender', 'game_score_answers.created_at', 'game_score_answers.cust_id', 'cust_avatar', 'cust_gender')
			->leftJoin('customers', 'customers.id', '=', 'game_score_answers.cust_id')
			->where('gms_id', '=', $game_score_id)
			->where('cust_delete', '=', "0")
			->where('gsa_delete', '=', "0")
			->whereRaw("ts_game_score_answers.gsa_score_1=ts_game_score_answers.gsa_score_2")
			->orderBy("game_score_answers.id",'ASC')
			->get();
		    
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success'
				],
				'flag_leaderboard' => $show_win,
				'user_all' => count($all_users),
				'user_club_1' => count($club_1),
				'user_club_2' => count($club_2),
				'user_draw' => count($club_draw),
				'results' => $results,
			];
        } else {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data not found'
				],
				'results' => $results
			];
		}
        return response()->json($data);
    }
    
    // Feed likes ***
	public function likeFeeds(Request $request)
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
		$feed_id = $request->feed_id;
		if($cust_id=='' || !$feed_id){
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Field cust_id dan feed_id harus di isi!'
				],
			];
			return response()->json($data);
		}
		
		$row_feed = DB::table("forum_feeds")->where(["id"=>$feed_id,"feed_delete"=>'0'])->first();
		if(!$row_feed){
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data feeds tidak ditemukan!'
				],
			];
			return response()->json($data);
		}
		
		// cek feed sudah dilike atau belum?
		$row_feed_like = DB::table("forum_feed_likes")->where(["feed_id"=>$feed_id,"cust_id"=>$cust_id])->first();
		if($row_feed_like){
			// update jika sudah ada data like?
			if($row_feed_like->feedlike_active == 1){$flag_like=0; $data_add=0; } else {$flag_like=1; $data_add=1;}
			$data_save = [
				'feedlike_active' => $flag_like,
				'updated_at' => date("Y-m-d H:i:s"),
			];
			$saves = DB::table("forum_feed_likes")->where('id', $row_feed_like->id)->update($data_save);
		} else {
			$data_add=1;
			// inser baru
			$data_save = [
				'feed_id' => $feed_id,
				'cust_id' => $cust_id,
				//'feedlike_active' => 1,
				'created_at' => date("Y-m-d H:i:s"),
			];
			$saves = DB::table("forum_feed_likes")->insertGetId(array_merge($data_save));
		}
		// proses save
		if($saves){
			// update feed_like
			if($data_add==1){$feed_like_count=$row_feed->feed_like+1;} else {$feed_like_count=$row_feed->feed_like-1;}
			$data_update = [
				'feed_like' => $feed_like_count,
			];
			$saves = DB::table("forum_feeds")->where('id', $row_feed->id)->update($data_update);

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
					'description' => 'Data gagal disimpan!'
				],
			];
		}
			
        return response()->json($data);
	}
	
	// Feed Comments
	public function commentFeeds(Request $request)
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
		$feed_id = $request->feed_id;
		$comment = $request->comment;
		if($cust_id=='' || !$feed_id || !$comment){
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Field cust_id, feed_id dan comment harus di isi!'
				],
			];
			return response()->json($data);
		}
		
		$row_feed = DB::table("forum_feeds")->where(["id"=>$feed_id,"feed_delete"=>'0'])->first();
		if(!$row_feed){
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data feeds tidak ditemukan!'
				],
			];
			return response()->json($data);
		}
		
		// cek feed sudah dilike atau belum?
		//$row_feed_comment = DB::table("forum_feed_comments")->where(["feed_id"=>$feed_id,"cust_id"=>$cust_id])->first();

		// inser baru
		$data_save = [
			'feed_id' => $feed_id,
			'cust_id' => $cust_id,
			//'feedcomment_active' => 1,
			'feedcomment_description' => $comment,
			'created_at' => date("Y-m-d H:i:s"),
		];
		$saves = DB::table("forum_feed_comments")->insertGetId(array_merge($data_save));

		// proses save
		if($saves){
			// update feed_like
			$data_update = [
				'feed_commment' => ($row_feed->feed_commment+1),
			];
			$saves = DB::table("forum_feeds")->where('id', $row_feed->id)->update($data_update);

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
					'description' => 'Data gagal disimpan!'
				],
			];
		}
			
        return response()->json($data);
	}
	
	// Feed Shares
	public function shareFeeds(Request $request)
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
		$feed_id = $request->feed_id;
		if($cust_id=='' || !$feed_id){
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Field cust_id dan feed_id harus di isi!'
				],
			];
			return response()->json($data);
		}
		
		$row_feed = DB::table("forum_feeds")->where(["id"=>$feed_id,"feed_delete"=>'0'])->first();
		if(!$row_feed){
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data feeds tidak ditemukan!'
				],
			];
			return response()->json($data);
		}
		
		// inser baru
		$data_save = [
			'feed_id' => $feed_id,
			'cust_id' => $cust_id,
			'created_at' => date("Y-m-d H:i:s"),
		];
		$saves = DB::table("forum_feed_shares")->insertGetId(array_merge($data_save));

		// proses save
		if($saves){
			// update feed_like
			$data_update = [
				'feed_share' => ($row_feed->feed_share+1),
			];
			$saves = DB::table("forum_feeds")->where('id', $row_feed->id)->update($data_update);

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
					'description' => 'Data gagal disimpan!'
				],
			];
		}
			
        return response()->json($data);
	}
	
	// list user yang sudah like feeds
	public function userlistlikeFeeds(Request $request)
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
		$feed_id = $request->feed_id;
		
		if(empty($cust_id) || empty($feed_id)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id dan feed_id tidak boleh kosong!'
                ]
            ];
            return response()->json($data);
		}
		
		$raw_user = DB::table("customers")->where("id","=",$cust_id)->first();
		$row_feed = DB::table("forum_feeds")->where(["id"=>$feed_id,"feed_delete"=>'0'])->first();
		if(!$row_feed){
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data feeds tidak ditemukan!'
				],
			];
			return response()->json($data);
		}

        $query = DB::table('forum_feed_likes')
		->select('forum_feed_likes.id', 'cust_name', 'cust_phone', 'cust_gender', 'forum_feed_likes.created_at', 'forum_feed_likes.cust_id', 'cust_avatar')
		->leftJoin('customers', 'customers.id', '=', 'forum_feed_likes.cust_id')
		->where('feed_id', '=', $feed_id)
		->where('feedlike_active', '=', "1")
		->where('feedlike_delete', '=', "0")
        ->orderBy("forum_feed_likes.id",'DESC')
		->paginate(20);
		
        $results = array();		
        if (!empty($query)) {
            foreach ($query as $row) {
				if(empty($row->cust_avatar)){
					if($row->cust_gender == 'm'){
						$avatar_profile = "https://avatar.iran.liara.run/public/boy?username=scott";
					} else {
						$avatar_profile = "https://avatar.iran.liara.run/public/girl?username=maria";
					}
				} else {
					$avatar_profile = $row->cust_avatar;
				}
								
				if(1==1){
					$results[] = [
						'feed_like_id' => $row->id,
						'cust_name' => $row->cust_name,
						'cust_phone' => ($row->cust_phone? $row->cust_phone : ''),
						'cust_avatar' => $avatar_profile,
						'feed_like_date' => $row->created_at,
					];
				}

			}
						
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success'
				],
				'results' => $results,
			];
        } else {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data not found'
				],
				'results' => $results
			];
		}
        return response()->json($data);
    }
	
	// Feeds User Comments
	public function userlistcommentFeeds(Request $request)
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
		$feed_id = $request->feed_id;
		$feed_sort = $request->feed_sort;
		if(empty($cust_id) || empty($feed_id) || empty($feed_sort)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id, feed_id dan feed_sort tidak boleh kosong!'
                ]
            ];
            return response()->json($data);
		}
		
		$raw_user = DB::table("customers")->where("id","=",$cust_id)->first();
		$row_feed = DB::table("forum_feeds")->where(["id"=>$feed_id,"feed_delete"=>'0'])->first();
		if(!$row_feed){
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data feeds tidak ditemukan!'
				],
			];
			return response()->json($data);
		}

        $query = DB::table('forum_feed_comments')
		->select('forum_feed_comments.id', 'cust_name', 'cust_phone', 'cust_gender', 'forum_feed_comments.created_at', 'forum_feed_comments.cust_id', 'cust_avatar', 'feedcomment_description')
		->leftJoin('customers', 'customers.id', '=', 'forum_feed_comments.cust_id')
		->where('feed_id', '=', $feed_id)
		->where('feedcomment_active', '=', "1")
		->where('feedcomment_delete', '=', "0")
        ->orderBy("forum_feed_comments.id",$feed_sort)
		->paginate(20);
		
        $results = array();		
        if (!empty($query)) {
            foreach ($query as $row) {
				if(empty($row->cust_avatar)){
					if($row->cust_gender == 'm'){
						$avatar_profile = "https://avatar.iran.liara.run/public/boy?username=scott";
					} else {
						$avatar_profile = "https://avatar.iran.liara.run/public/girl?username=maria";
					}
				} else {
					$avatar_profile = $row->cust_avatar;
				}
								
				if(1==1){
					$results[] = [
						'feed_comment_id' => $row->id,
						'cust_name' => $row->cust_name,
						'cust_phone' => ($row->cust_phone? $row->cust_phone : ''),
						'cust_avatar' => $avatar_profile,
						'feed_comment_description' => $row->feedcomment_description,
						'feed_comment_date' => $row->created_at,
					];
				}

			}
						
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success'
				],
				'results' => $results,
			];
        } else {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data not found'
				],
				'results' => $results
			];
		}
        return response()->json($data);
    }
    
    // report feeds
	public function reportFeeds(Request $request)
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
		$feed_id = $request->feed_id;
		if($cust_id=='' || !$feed_id){
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Field cust_id dan feed_id harus di isi!'
				],
			];
			return response()->json($data);
		}
		
		$row_feed = DB::table("forum_feeds")->where(["id"=>$feed_id,"feed_delete"=>'0'])->first();
		if(!$row_feed){
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data feeds tidak ditemukan!'
				],
			];
			return response()->json($data);
		}
		
		
		// cek sudah di report atau belum
		$row_feed_report = DB::table("forum_feed_reports")->where(["id"=>$feed_id,"cust_id"=>$cust_id])->first();
		if($row_feed_report){
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data feeds sudah pernah kamu laporkan!'
				],
			];
			return response()->json($data);
		}
		
		// inser baru
		$data_save = [
			'feed_id' => $feed_id,
			'cust_id' => $cust_id,
			'created_at' => date("Y-m-d H:i:s"),
		];
		$saves = DB::table("forum_feed_reports")->insertGetId(array_merge($data_save));

		// proses save
		if($saves){
			// update feed_like
			/*
			$data_update = [
				'feed_share' => ($row_feed->feed_share+1),
			];
			$saves = DB::table("forum_feed_reports")->where('id', $row_feed->id)->update($data_update);
			*/
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
					'description' => 'Data gagal disimpan!'
				],
			];
		}
			
        return response()->json($data);
	}
	
	// edit feeds
	public function editFeeds(Request $request)
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
		$feed_description = $request->feed_description;
		$feed_id = $request->feed_id;
		//$file_upload = $request->hasFile('file_upload');
		if($cust_id=='' || !$feed_id || !$feed_description){
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Field cust_id, feed_id dan feed_description harus di isi!'
				],
			];
		}
		
		$row_feed = DB::table("forum_feeds")->where(["id"=>$feed_id,"feed_delete"=>'0', "cust_id"=>$cust_id])->first();
		if(!$row_feed){
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data feeds tidak ditemukan!'
				],
			];
			return response()->json($data);
		}
		
		// proses save
		$data = [
			'feed_description' => $feed_description,
			'updated_at' => date("Y-m-d H:i:s"),
		];
						
		//$check = $this->checkData("forum_feeds",'id', ['cust_id' => $cust_id, 'lookup_id' => $lookup_id, 'ra_type' => $ra_type]);
			/*
			$data_upload = array();
			if ($request->hasFile('file_upload')) {				
				$request->validate([
						'file_upload' => 'required|file|mimes:jpg,jpeg,png,gif',
				]);
				$image = $request->file('file_upload');
				$input['fileName'] = 'feeds'.rand(10000,99999).time().'.'.$image->extension();
			
				$destinationPath = public_path('/forum');
				$image->move($destinationPath, $input['fileName']);
				
				$data_upload = ['feed_image' => $input['fileName']];
			}
			*/
		
		//$saves = DB::table("forum_feeds")->insertGetId(array_merge($data, $data_upload));
		$saves = DB::table("forum_feeds")->where('id', $row_feed->id)->update($data);
		if($saves){
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
					'description' => 'Data gagal disimpan!'
				],
			];
		}
			
        return response()->json($data);

	}
	
	// list feed by customer
	public function userlistFeeds(Request $request)
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
		
		// save log traffic
		/*
		$data_log = [
			'cust_id' => $request->cust_id,
			'ta_page_access' => "Forum",
			'ta_description' => "Mengakses laman forum aplikasi",
			'created_at' => date("Y-m-d H:i:s"),
		];
		$save_log = DB::table("traffic_apps")->insertGetId(array_merge($data_log));
		*/
		
		$cust_id = $request->cust_id;
		if(empty($cust_id)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field cust_id tidak boleh kosong!'
                ]
            ];
            return response()->json($data);
		}
		
		$arr_cust = DB::table("customers")->where("id","=",$cust_id)->first();
		if(!$arr_cust){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data customer tidak ditemukan!'
                ]
            ];
            return response()->json($data);
		}
		
        $query = DB::table('forum_feeds')
		->select('forum_feeds.id', 'feed_description', 'feed_image', 'feed_like', 'feed_commment', 'feed_share', 'feed_view', 'forum_feeds.created_at', 'cust_name', 'cust_phone', 'forum_feeds.cust_id')
		->leftJoin('customers', 'customers.id', '=', 'forum_feeds.cust_id')
		->where('forum_feeds.cust_id', '=', $cust_id)
		->where('feed_delete', '=', "0")
		/*
		->where(function($w) use($forum_search, $forum_cat_id) {
			if (!empty($forum_search)) {
				$w->where(DB::raw("CONCAT(foc_name, ' ', foc_description)"), 'LIKE', "%$forum_search%");
			}
			if(!empty($forum_cat_id)){
				$w->where('forum_categories.id', '=', $forum_cat_id);
			}
		})
		*/
        ->orderBy("forum_feeds.id",'DESC')
		->paginate(10);
		
        $results = array();
        if (!empty($query)) {
            foreach ($query as $row) {
				if (!empty($row->feed_image)) {
                	$feed_image = url('public/forum/'.$row->feed_image);
                } else {
					$feed_image = '';
				}
				
				// cek postingan sendri atau user lain
				if($row->cust_id==$cust_id){$feed_user=1;} else {$feed_user=0;}
				// cek like user (sudah like atau belum)
				$cek_like_user = DB::table("forum_feed_likes")->where(["feed_id"=>$row->id, "cust_id"=>$cust_id, "feedlike_active"=>"1"])->first();
				if($cek_like_user){$feed_like_user=1;} else {$feed_like_user=0;}
				
                $results[] = [
                    'feed_id' => $row->id,
					'feed_image' => $feed_image,
					'feed_description' => $row->feed_description,
					'feed_like' => (int)$row->feed_like,
					'feed_commment' => (int)$row->feed_commment,
					'feed_share' => (int)$row->feed_share,
					'feed_cust_name' => $row->cust_name,
					'feed_cust_phone' => ($row->cust_phone? $row->cust_phone : ''),
					'feed_date' => $row->created_at,
					'feed_user' => $feed_user,
					'feed_like_user' => $feed_like_user,
                ];
			}
		
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success'
				],
				'results' => $results,
			];
        } else {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data tidak ditemukan!'
				],
				'results' => $results
			];
		}
        return response()->json($data);
    }
    
    // user join forum list
	public function userjoinList(Request $request)
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
		
		$forum_detail_id = $request->forum_detail_id;
		$cust_id = $request->cust_id;
		$key_search = $request->key_search;
		if (!$forum_detail_id || !$cust_id) {
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field forum_detail_id dan cust_id tidak boleh kosong'
                ]
            ];
            return response()->json($data);
        }
		$forum = DB::table("forum_details")->where("id","=",$forum_detail_id)->where("fodet_delete","=","0")->first();
		if(!$forum){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Forum dengan ID "'.$forum_detail_id.'" tidak ditemukan',
                ]
            ];
            return response()->json($data);
		}

		$customer_join_data = DB::table('forum_detail_joins')
		->select('forum_detail_joins.id', 'cust_id', 'cust_name', 'cust_email', 'cust_address', 'cust_phone', 'cust_score', 'cust_coin', 'cust_gender', 'cust_avatar', 'cust_reseller')
        ->leftJoin('customers', 'customers.id', '=', 'forum_detail_joins.cust_id')
		->where('forum_detail_joins.fodet_id', '=', $forum->id)
		->where('fodj_delete', '=', '0')
		->where(function($w) use($key_search) {
			if (!empty($key_search)) {
				$w->where(DB::raw("CONCAT(cust_name, ' ', cust_phone)"), 'LIKE', "%$key_search%");
			}
		})
		->paginate(10);
		$results=array();
		foreach($customer_join_data as $raw){
			if(empty($raw->cust_avatar)){
				if($raw->cust_gender == 'm'){
					$avatar_profile = "https://avatar.iran.liara.run/public/boy?username=scott";
				} else {
					$avatar_profile = "https://avatar.iran.liara.run/public/girl?username=maria";
				}
			} else {
				$avatar_profile = $raw->cust_avatar;
			}
			
			$results[] = [
				'cust_id' => $raw->cust_id,
				'cust_name' => $raw->cust_name,
				'cust_email' => $raw->cust_email,
				'cust_phone' => $raw->cust_phone,
				'cust_address' => $raw->cust_address,
				'cust_coin' => $raw->cust_coin,
				'cust_score' => $raw->cust_score,
				'cust_avatar_join' => $avatar_profile,
				'cust_reseller' => $raw->cust_reseller,
			];
		}

        if(1==1){
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success'
				],
				'results' => $results,
			];
		}
        return response()->json($data);


	}
}
