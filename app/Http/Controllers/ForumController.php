<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ForumController extends Controller
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
		$data_log = [
			'cust_id' => $request->cust_id,
			'ta_page_access' => "Forum",
			'ta_description' => "Mengakses laman forum aplikasi",
			'created_at' => date("Y-m-d H:i:s"),
		];
		$save_log = DB::table("traffic_apps")->insertGetId(array_merge($data_log));
		
		// kategori
		$forum_search = $request->forum_search; // keyword pencarian blog
		$forum_id = $request->forum_id;
		/*
		if($blog_type == "trending"){
			$orderby='forums.fo_view';
		} else {
			$orderby='forums.created_at';
		}
		*/
		
        $query = DB::table('forums')
		->select('forums.id', 'fo_name', 'fo_slug', 'fo_description', 'fo_image', 'fo_view', 'fo_active', 'forums.created_at', 'u_name')
        //->leftJoin('blog_categories', 'blog_categories.id', '=', 'blog_contents.bc_id')
		->leftJoin('users', 'users.id', '=', 'forums.user_input')
		->where('forums.fo_active', '=', "1")
		->where('forums.fo_delete', '=', "0")
		->where(function($w) use($forum_search, $forum_id) {
			if (!empty($forum_search)) {
				$w->where(DB::raw("CONCAT(fo_name, ' ', fo_description)"), 'LIKE', "%$forum_search%");
			}
			if(!empty($forum_id)){
				$w->where('forums.id', '=', $forum_id);
			}
		})
        ->orderBy("forums.created_at",'DESC')
		->paginate(5);
		
		// tampilkan header forum
		$forum_home = DB::table("forum_homes")->first();
		
        $results = array();
        if (!empty($query)) {
            foreach ($query as $row) {
				// cek join
				$join_cek = DB::table("forum_joins")->where("cust_id","=",$request->cust_id)->where("fo_id","=",$row->id)->where("foj_delete","=",'0')->first();
				if($join_cek){$flag_join = 1;} else {$flag_join = 0;}
				
				// https://topsystem.id/api/blog/600/1675249179.jpg
                if (!empty($row->fo_image)) {
                    //$image_url = "https://topsystem.id/api/blog/600/".$row->fo_image;
                    $image_url = "https://topsystem.id/upload/forum/".$row->fo_image;
                } else {
					$image_url = '';
				}
                
                $results[] = [
                    'forum_id' => $row->id,
					'forum_name' => $row->fo_name,
					'forum_slug' => $row->fo_slug,
					'forum_description' => $row->fo_description,
					'forum_view' => (int)$row->fo_view,
                    'forum_image' => $image_url,
					'forum_admin' => $row->u_name,
					'forum_join' => $flag_join,
					'forum_date' => $row->created_at,
					'cust_avatar' => "https://avatar.iran.liara.run/public/boy?username=scott",
                ];
				// update jika melihat detail data
				if(!empty($forum_id)){
					$updated_date = [
						'fo_view' => $row->fo_view+1,
					];
					DB::table("forums")->where('id', $row->id)->update($updated_date);
				}
			}
		
        $data = [
            'status' => [
                'code' => 200,
                'description' => 'Success'
            ],
			'info' => [
				'title' => $forum_home->fh_title,
				'image' => 'https://topsystem.id/public/upload/'.$forum_home->fh_image,
				'description' => $forum_home->fh_description,
			],
            'results' => $results,
        ];
        } else {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data not found'
				],
				'info' => [
					'title' => $forum_home->fh_title,
					'image' => 'https://topsystem.id/public/upload/'.$forum_home->fh_image,
					'description' => $forum_home->fh_description,
				],
				'results' => $results
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
		
		$forum_id = $request->forum_id;
		$cust_id = $request->cust_id;
		
		if(empty($forum_id) || empty($cust_id)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field forum_id dan cust_id tidak boleh kosong'
                ]
            ];
            return response()->json($data);
		}
		
		$row_forum = DB::table("forums")->where("id","=",$forum_id)->first();
		// jika forum tidak ditemukan
		if(!$row_forum){
			// $saves = DB::table("customer_address")->insertGetId(array_merge($data));
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data tidak ditemukan'
                ]
            ];
            return response()->json($data);
		}
		
		$row_forum_join = DB::table("forum_joins")->where("fo_id","=",$forum_id)->where("cust_id","=",$cust_id)->where("foj_delete","=",'0')->first();
		if(!$row_forum_join){
			$data_save = [
				'fo_id' => $forum_id,
				'cust_id' => $cust_id,
				'created_at' => date("Y-m-d H:i:s"),
			];
			$saves = DB::table("forum_joins")->insertGetId(array_merge($data_save));
			// save log score
			if(!empty($cust_id)){
				$arr_cust = DB::table("customers")->where("id","=",$cust_id)->first();
				// cek sudah pernah belum baca detail
				$arr_scores = DB::table("customer_scores")
							  ->where("cust_id","=",$arr_cust->id)
							  ->where("cscore_lookup_id","=",$forum_id)
							  ->where("cscore_type","=","forum")
							  ->first();
				if($arr_cust && empty($arr_scores)){
					$data_score = [
						'cust_id' => $arr_cust->id,
						'cscore_type' => 'forum',
						'cscore_lookup_id' => $forum_id,
						'cscore_description' => 'Join forum : '.$row_forum->fo_name,
						'cscore_value' => 1,
						'created_at' => date("Y-m-d H:i:s"),
					];
					DB::table("customer_scores")->insertGetId($data_score);
					
					// update nilai score customers...
					DB::table("customers")->where('id', $cust_id)->update([
						'cust_score' => ($arr_cust->cust_score+1), 
					]);
				}
			}
		} else {
			$updated_date = [
				'updated_at' => date("Y-m-d H:i:s"),
			];
			DB::table("forum_joins")->where('id', $row_forum_join->id)->update($updated_date);
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
	
	// list blog dari forum
	public function blogForum(Request $request)
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
		
		$forum_id = $request->forum_id;
		$cust_id = $request->cust_id;
		$forum_blog_id = $request->fblog_id;
		$forum_search = '';
		if (!$forum_id || !$cust_id) {
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field forum_id dan cust_id tidak boleh kosong'
                ]
            ];
            return response()->json($data);
        }
		$forum = DB::table("forums")->where("id","=",$forum_id)->where("fo_delete","=","0")->first();
		if(!$forum){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Forum dengan ID "'.$forum_id.'" tidak ditemukan',
                ]
            ];
            return response()->json($data);
		} else {
			if($forum->fo_active == 0){
				$data = [
					'status' => [
						'code' => 400,
						'description' => 'Forum dengan ID "'.$forum_id.'" sudah tidak aktif',
					]
				];
				return response()->json($data);
			}
		}
		
		// save log traffic
		$data_log = [
			'cust_id' => $request->cust_id,
			'ta_page_access' => "Detail Forum",
			'ta_description' => "Mengakses laman detail Forum",
			'ta_lookup_id' => $request->forum_id,
			'created_at' => date("Y-m-d H:i:s"),
		];
		$save_log = DB::table("traffic_apps")->insertGetId(array_merge($data_log));
		
		// data gambar forum
		if (!empty($forum->fo_image)) {
        	$image_url_forum = "https://topsystem.id/upload/forum/".$forum->fo_image;
        } else {
			$image_url_forum = '';
		}
		
		// cek customer
		$customer = DB::table("customers")->where("id","=",$cust_id)->where("cust_delete","=","0")->first();
		if(!$customer){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Customr dengan ID "'.$cust_id.'" tidak ditemukan',
                ]
            ];
            return response()->json($data);
		}
		
		// cek join forum user
		$customer_join = DB::table("forum_joins")->where("cust_id","=",$cust_id)->where("fo_id","=",$forum_id)->where("foj_delete","=",'0')->first();
		if(!$customer_join){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Customr dengan ID "'.$cust_id.'" belum bergabung ke forum dengan ID "'.$forum_id.'"',
                ]
            ];
            return response()->json($data);
		}
        
        $customer_join_data = DB::table('forum_joins')
		->select('forum_joins.id', 'cust_id', 'cust_name', 'cust_email', 'cust_address', 'cust_phone', 'cust_score', 'cust_coin', 'cust_gender', 'cust_avatar', 'cust_reseller')
        ->leftJoin('customers', 'customers.id', '=', 'forum_joins.cust_id')
        ->where('forum_joins.fo_id', '=', $forum->id)
		->where('foj_delete', '=', '0')
		->orderBy('cust_name','ASC')
		->get();
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
				'cust_phone' => $raw->cust_phone,
				'cust_email' => $raw->cust_email,
				'cust_address' => $raw->cust_address,
				'cust_coin' => $raw->cust_coin,
				'cust_score' => $raw->cust_score,
				'cust_avatar_join' => $avatar_profile,
				'cust_reseller' => $raw->cust_reseller,
			];
		}
        
        $query = DB::table('forum_blogs')
		->select('forum_blogs.id', 'fobl_name', 'fobl_slug', 'fubl_description', 'fobl_image', 'fobl_like', 'fobl_commment', 'fobl_share', 'forum_blogs.created_at', 'u_name', 'fobl_view')
        ->leftJoin('users', 'users.id', '=', 'forum_blogs.user_input')
		->where('forum_blogs.fo_id', '=', $forum->id)
		->where('forum_blogs.fobl_delete', '=', "0")
		->where(function($w) use($forum_search, $forum_blog_id) {
			if(!empty($forum_blog_id)){
				$w->where('forum_blogs.id', '=', $forum_blog_id);
			}
		})
        ->orderBy("forum_blogs.created_at",'DESC')
		->paginate(5);
		
		// tampilkan header forum
		$forum_home = DB::table("forum_homes")->first();
		
        $results = array();
		$comment = array();
        if (!empty($query)) {
            foreach ($query as $row) {
				// update jika melihat detail data
				if(!empty($forum_blog_id)){
					$updated_date = [
						'fobl_view' => $row->fobl_view+1,
					];
					DB::table("forum_blogs")->where('id', $row->id)->update($updated_date);
					// tampilkan komentar user
					// $comment
					$comment_sql = DB::table("forum_blog_comments")
						->select('forum_blog_comments.id', 'cust_name', 'fobc_comment', 'fobc_like', 'forum_blog_comments.created_at')
						->leftJoin('customers', 'customers.id', '=', 'forum_blog_comments.cust_id')
						->where("fobl_id","=",$row->id)
						->where("fobc_active","=","1")
						->where("fobc_delete","=","0")
						->orderBy("forum_blog_comments.created_at","DESC")
						->get();
					if($comment_sql){
						foreach($comment_sql as $sub_comment){
							// cek user jika sudah like komen
							$user_comment = DB::table("forum_blog_comment_likes")
								->where("cust_id","=",$cust_id)
								->where("fobc_id","=",$sub_comment->id)
								->where("fobclk_active","=","1")
								->first();
							if($user_comment){$flag_commment=1;} else {$flag_commment=0;}
							if(empty($sub_comment->cust_name)){$cust_names='User***';} else {$cust_names=$sub_comment->cust_name;}
							$comment[] = [
								'fbcomment_id' => $sub_comment->id,
								//'fbcomment_user' => $sub_comment->cust_name,
								'fbcomment_user' => $cust_names,
								'fbcomment_description' => $sub_comment->fobc_comment,
								//'fbcomment_like' => (int)$sub_comment->fobc_like,
								'fbcomment_like' => (int)$flag_commment,
								'fbcomment_date' => $sub_comment->created_at,
							];
						}
					}
				}

				if (!empty($row->fobl_image)) {
                    $image_url = "https://topsystem.id/upload/forum/".$row->fobl_image;
                } else {
					$image_url = '';
				}
                
                $fblog_bg=url('public/upload/bg_forum6.jpg');
                
                $results[] = [
                    'fblog_id' => $row->id,
					'fblog_bg' => $fblog_bg,
					'fblog_name' => $row->fobl_name,
					//'fblog_slug' => $row->fo_slug,
					'fblog_description' => $row->fubl_description,
					'fblog_view' => (int)$row->fobl_like,
					'fblog_comment' => (int)$row->fobl_commment,
					'fblog_share' => (int)$row->fobl_share,
                    'fblog_image' => $image_url,
					'fblog_admin' => $row->u_name,
					'fblog_date' => $row->created_at,
					'cust_avatar' => "https://avatar.iran.liara.run/public/boy",
					'fblog_comment_detail' => $comment,
                ];
			}
		
        $data = [
            'status' => [
                'code' => 200,
                'description' => 'Success'
            ],
			'info' => [
				'title' => $forum_home->fh_title,
				'image' => 'https://topsystem.id/public/upload/'.$forum_home->fh_image,
				'description' => $forum_home->fh_description,
			],
			'forum' => [
				'forum_id' => $forum->id,
				'forum_name' => $forum->fo_name,
				'forum_slug' => $forum->fo_slug,
				'forum_description' => $forum->fo_description,
				'forum_view' => (int)$forum->fo_view,
				'forum_image' => $image_url_forum,
				//'forum_admin' => $forum->u_name,
				'forum_date' => $forum->created_at,
			],
            'forum_blog' => $results,
            'customer_join' => $customer_join,
        ];
        } else {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data not found'
				],
				'info' => [
					'title' => $forum_home->fh_title,
					'image' => 'https://topsystem.id/public/upload/'.$forum_home->fh_image,
					'description' => $forum_home->fh_description,
				],
				'forum' => [
					'forum_id' => $forum->id,
					'forum_name' => $forum->fo_name,
					'forum_slug' => $forum->fo_slug,
					'forum_description' => $forum->fo_description,
					'forum_view' => (int)$forum->fo_view,
					'forum_image' => $image_url_forum,
					//'forum_admin' => $forum->u_name,
					'forum_date' => $forum->created_at,
				],
				'forum_blog' => $results,
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
		
		$forum_id = $request->forum_id;
		$cust_id = $request->cust_id;
		
		if(empty($forum_id) || empty($cust_id)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field forum_id dan cust_id tidak boleh kosong'
                ]
            ];
            return response()->json($data);
		}
		
		$row_forum = DB::table("forums")->where("id","=",$forum_id)->first();
		// jika forum tidak ditemukan
		if(!$row_forum){
			// $saves = DB::table("customer_address")->insertGetId(array_merge($data));
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data forum dengan ID "'.$forum_id.'" tidak ditemukan'
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
		$arr_scores = DB::table("customer_scores")
					  ->where("cust_id","=",$arr_cust->id)
					  ->where("cscore_lookup_id","=",$forum_id)
					  ->where("cscore_type","=","forum")
					  ->orderBy("id","DESC")
					  ->first();
		if($arr_scores){
			if($arr_scores->cscore_value>0){
				$data_score = [
					'cust_id' => $arr_cust->id,
					'cscore_type' => 'forum',
					'cscore_lookup_id' => $forum_id,
					'cscore_description' => 'Leave forum : '.$row_forum->fo_name,
					'cscore_value' => -1,
					'created_at' => date("Y-m-d H:i:s"),
				];
				DB::table("customer_scores")->insertGetId($data_score);
				
				// update nilai score customers...
				DB::table("customers")->where('id', $cust_id)->update([
					'cust_score' => ($arr_cust->cust_score-1), 
				]);
                
                /*
				$row_join = DB::table("forum_joins")
					  ->where("cust_id","=",$cust_id)
					  ->where("fo_id","=",$forum_id)
					  ->where("foj_delete","=",'0')
					  ->first();
				if($row_join){
					// update leave forum
					$updated_leave = [
						'foj_delete' => 1,
						'updated_at' => date("Y-m-d H:i:s"),
					];
					DB::table("forum_joins")->where('id', $row_join->id)->update($updated_leave);
				}
				*/
			}
			    $row_join = DB::table("forum_joins")
					  ->where("cust_id","=",$cust_id)
					  ->where("fo_id","=",$forum_id)
					  ->where("foj_delete","=",'0')
					  ->first();
				if($row_join){
					// update leave forum
					$updated_leave = [
						'foj_delete' => 1,
						'updated_at' => date("Y-m-d H:i:s"),
					];
					DB::table("forum_joins")->where('id', $row_join->id)->update($updated_leave);
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
    
}
