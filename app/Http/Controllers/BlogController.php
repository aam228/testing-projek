<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
//use App\Models\Artikel;

class BlogController extends Controller
{
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
			'ta_page_access' => "Blog",
			'ta_description' => "Mengakses laman blog",
			//'ta_lookup_id' => $request->product_id,
			'created_at' => date("Y-m-d H:i:s"),
			'ip_address' => $request->ip(),
		];
		$save_log = DB::table("traffic_apps")->insertGetId(array_merge($data_log));
		
		// kategori
		$blog_search = $request->blog_search; // keyword pencarian blog
		$blog_type = $request->blog_type; // tranding, terbaru
		$blog_id = $request->blog_id;
		$cust_id = $request->cust_id;
		
		if($blog_type == "trending"){
			$orderby='blog_contents.bct_views';
		} else {
			$orderby='blog_contents.created_at';
		}
		
		
        $blog = DB::table('blog_contents')
		->select('blog_contents.id', 'bct_title', 'bct_slug', 'bct_image', 'bct_content', 'bct_keywords', 'bct_views', 'blog_contents.created_at', 'bc_name', 'bc_slug', 'u_name', 'bc_id', 'bct_like', 'bct_comment', 'bct_share')
        ->leftJoin('blog_categories', 'blog_categories.id', '=', 'blog_contents.bc_id')
		->leftJoin('users', 'users.id', '=', 'blog_contents.u_id')
		->where(function($w) use($blog_search, $blog_type, $blog_id) {
			if (!empty($blog_search)) {
				//$w->whereIn('product_sub_categories.psc_name', $sub_details);
				//$w->where(DB::raw("CONCAT(p_name, ' ', br_name, ' ',psc_name,' ',pssc_name,' ',br_name)"), 'LIKE', "%$nama_produk%");
				$w->where(DB::raw("CONCAT(bct_title, ' ', bct_content)"), 'LIKE', "%$blog_search%");
			}
			if(!empty($blog_id)){
				$w->where('blog_contents.id', '=', $blog_id);
			}
		})
        ->orderBy($orderby,'DESC')
		->paginate(5);
		$bc_id = '';
        $results = array();
        $data_comment = array();
        if (!empty($blog)) {
            foreach ($blog as $row) {
				// https://topsystem.id/api/blog/600/1675249179.jpg
                if (!empty($row->bct_image)) {
                    $image_url = "https://topsystem.id/api/blog/600/".$row->bct_image;
                } else {
					$image_url = '';
				}
                
                // cek like
				//$blog_like = DB::table("blog_likes")->where("cust_id","=",$cust_id)->where("bct_id","=",$row->id)->get();
				$blog_like = DB::table("blog_likes")->where("cust_id","=",$cust_id)->where("bct_id","=",$row->id)->where("bllike_active","=",'1')->get();
				if(count($blog_like)>0){
					$blog_like=1;
				} else { $blog_like=0; }
				// cek comment
				$blog_comment = DB::table("blog_comments")->where("cust_id","=",$cust_id)->where("bct_id","=",$row->id)->get();
				if(count($blog_comment)>0){
					$blog_comment=1;
				} else { $blog_comment=0; }
                
                // all comment
				$all_comment = DB::table("blog_comments")
				->select('blog_comments.id', 'cust_name', 'cust_email', 'cust_phone', 'blog_comments.created_at', 'ip_address', 'blcom_comment', 'cust_avatar', 'cust_gender', 'cust_reseller')
        		->leftJoin('customers', 'customers.id', '=', 'blog_comments.cust_id')
				->where("bct_id","=",$row->id)
				->where("blcom_delete","=",'0')
				->where("blcom_active","=",'1')
				->orderBy('blog_comments.created_at',"DESC")
				->get();
				$data_comment = array();
				foreach($all_comment as $raw_comment){
				    
				    if(empty($raw_comment->cust_avatar)){
						if($raw_comment->cust_gender == 'm'){
							$avatar_profile = "https://avatar.iran.liara.run/public/boy?username=scott";
						} else {
							$avatar_profile = "https://avatar.iran.liara.run/public/girl?username=maria";
						}
					} else {
						$avatar_profile = $raw_comment->cust_avatar;
					}
				    
					$data_comment[]= [
						'comment_id' => $raw_comment->id,
						'comment_cust_name' => $raw_comment->cust_name,
						'comment_cust_phone' => $raw_comment->cust_phone,
						'comment_avatar' => $avatar_profile,
						'comment_ip' => $raw_comment->ip_address,
						'comment_text' => $raw_comment->blcom_comment,
						'comment_created' => $raw_comment->created_at,
						'cust_reseller' => $raw_comment->cust_reseller,
					];
				}
                
                if($row->bct_like<0){$bct_like=0;} else {$bct_like=$row->bct_like;}
                if($row->bct_comment<0){$bct_comment=0;} else {$bct_comment=$row->bct_comment;}
                
                $results[] = [
                    'blog_id' => $row->id,
					'blog_category' => $row->bc_name,
					'blog_title' => $row->bct_title,
					'blog_slug' => $row->bct_slug,
					'blog_content' => $row->bct_content,
					'blog_view' => $row->bct_views,
                    'blog_image' => $image_url,
					'blog_admin' => $row->u_name,
					'blog_date' => $row->created_at,
					'cust_avatar' => "https://avatar.iran.liara.run/public/boy",
					'blog_like' => (int)$blog_like,
					'blog_comment' => (int)$blog_comment,
					'blog_like_all' => (int)$bct_like,
					'blog_comment_all' => (int)$bct_comment,
					//'blog_share' => (int)$row->bct_share,
					'data_comment' => $data_comment,
                ];
				$bc_id = $row->bc_id;
            }
		
		// rekomendasi
		$recommendations = array();
		if(!empty($blog_id)){
			$blog_rekom = DB::table('blog_contents')
			->select('blog_contents.id', 'bct_title', 'bct_slug', 'bct_image', 'bct_content', 'bct_keywords', 'bct_views', 'blog_contents.created_at', 'bc_name', 'bc_slug', 'u_name', 'bct_like', 'bct_comment', 'bct_share')
			->leftJoin('blog_categories', 'blog_categories.id', '=', 'blog_contents.bc_id')
			->leftJoin('users', 'users.id', '=', 'blog_contents.u_id')
			->where('blog_contents.id', '!=', $blog_id)
			->where('blog_contents.bc_id', '=', $bc_id)
			->orderBy('blog_contents.created_at','DESC')
			->paginate(5);
			if (!empty($blog_rekom)) {
            	foreach ($blog_rekom as $row_rekom) {
					if (!empty($row_rekom->bct_image)) {
						$image_url = "https://topsystem.id/api/blog/600/".$row_rekom->bct_image;
					} else {
						$image_url = '';
					}
					
					// cek like
					$blog_like = DB::table("blog_likes")->where("cust_id","=",$cust_id)->where("bct_id","=",$row_rekom->id)->get();
					if(count($blog_like)>0){
						$blog_like=1;
					} else { $blog_like=0; }
					// cek comment
					$blog_comment = DB::table("blog_comments")->where("cust_id","=",$cust_id)->where("bct_id","=",$row_rekom->id)->get();
					if(count($blog_comment)>0){
						$blog_comment=1;
					} else { $blog_comment=0; }
					
					$recommendations[] = [
						'blog_id' => $row_rekom->id,
						'blog_category' => $row_rekom->bc_name,
						'blog_title' => $row_rekom->bct_title,
						'blog_slug' => $row_rekom->bct_slug,
						'blog_content' => $row_rekom->bct_content,
						'blog_view' => $row_rekom->bct_views,
						'blog_image' => $image_url,
						'blog_admin' => $row_rekom->u_name,
						'blog_date' => $row_rekom->created_at,
						'cust_avatar' => "https://avatar.iran.liara.run/public/boy",
						'blog_like' => (int)$blog_like,
						'blog_comment' => (int)$blog_comment,
						'blog_like_all' => (int)$row_rekom->bct_like,
						'blog_comment_all' => (int)$row_rekom->bct_comment,
						//'blog_share' => (int)$row->bct_share,
					];
				}
			}
			
			// tambahkan score ketika cust baca detail
			$arr_blog = DB::table("blog_contents")->where("id","=",$blog_id)->first();
			$cust_id = $request->cust_id;
			if(!empty($cust_id)){
			    $arr_cust = DB::table("customers")->where("id","=",$cust_id)->first();
				// cek sudah pernah belum baca detail
				$arr_scores = DB::table("customer_scores")
							  ->where("cust_id","=",$arr_cust->id)
							  ->where("cscore_lookup_id","=",$arr_blog->id)
							  ->where("cscore_type","=","blog")
							  ->first();
				/*
				if($arr_cust && empty($arr_scores)){
					$data_score = [
						'cust_id' => $arr_cust->id,
						'cscore_type' => 'blog',
						'cscore_lookup_id' => $arr_blog->id,
						'cscore_description' => 'Baca artikel dengan judul : '.$arr_blog->bct_title,
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
		}
		
        $data = [
            'status' => [
                'code' => 200,
                'description' => 'Success'
            ],
            'results' => $results,
			'recommendations' => $recommendations,
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
	
	// Like, Comment, and Share *********************
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
		
		$blog_id = $request->blog_id;
		$cust_id = $request->cust_id; // opsional
		$ip_address = $request->ip_address;
		$blog_comment = $request->blog_comment;
		
		if(empty($blog_id) || empty($blog_comment)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field blog_id, ip_address dan blog_comment tidak boleh kosong'
                ]
            ];
            return response()->json($data);
		}
		
		$row_blog = DB::table("blog_contents")->where("id","=",$blog_id)->first();
		// jika forum tidak ditemukan
		if(!$row_blog){
			// $saves = DB::table("customer_address")->insertGetId(array_merge($data));
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data blog dengan ID "'.$blog_id.'" tidak ditemukan'
                ]
            ];
            return response()->json($data);
		}
		
		// save comment
		$data_save = [
				'bct_id' => $blog_id,
				'cust_id' => $cust_id,
				'ip_address' => $ip_address,
				'blcom_comment' => $blog_comment,
				'created_at' => date("Y-m-d H:i:s"),
		];
		$saves = DB::table("blog_comments")->insertGetId(array_merge($data_save));
		
		/*
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
			$updated_date = [
				'updated_at' => date("Y-m-d H:i:s"),
			];
			DB::table("forum_blog_comments")->where('id', $row_blogcomment->id)->update($updated_date);
			$data_save = [
				'fobl_id' => $fblog_id,
				'cust_id' => $cust_id,
				'fobc_comment' => $fobc_comment,
				'created_at' => date("Y-m-d H:i:s"),
			];
			$saves = DB::table("forum_blog_comments")->insertGetId(array_merge($data_save));
		}
		*/
		
        // tampilkan respon data
		if($saves){
			// update count like...
			$updated_date = [
				'updated_at' => date("Y-m-d H:i:s"),
				'bct_comment' => ($row_blog->bct_comment+1),
			];
			DB::table("blog_contents")->where('id', $row_blog->id)->update($updated_date);
			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Komentar berhasil di submit'
				],
			];
		} else {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Komentar gagal di submit'
				],
			];
		}
        return response()->json($data);
    }
	
	// like blog
	public function likeBlog(Request $request)
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
		
		$blog_id = $request->blog_id;
		$cust_id = $request->cust_id;
		$ip_address = $request->ip_address;
		$status_like = $request->status_like; 
		
		if(empty($blog_id) || empty($cust_id)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field blog_id, cust_id dan ip_address tidak boleh kosong'
                ]
            ];
            return response()->json($data);
		}
		
		$row_blog = DB::table("blog_contents")->where("id","=",$blog_id)->first();
		// jika forum tidak ditemukan
		if(!$row_blog){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Data blog dengan ID "'.$blog_id.'" tidak ditemukan'

                ]
            ];
            return response()->json($data);
		}
		
		// cek jika sudah pernah d like
		$row_like = DB::table("blog_likes")
				->where("bct_id","=",$blog_id)
				->where("cust_id","=",$cust_id)
				->first();
		if(!$row_like){
			$data_save = [
					'bct_id' => $blog_id,
					'cust_id' => $cust_id,
					'ip_address' => $ip_address,
					'bllike_active' => 1,
					'created_at' => date("Y-m-d H:i:s"),
			];
			$saves = DB::table("blog_likes")->insertGetId(array_merge($data_save));
			// update count like...
			$updated_date = [
				'updated_at' => date("Y-m-d H:i:s"),
				'bct_like' => ($row_blog->bct_like+1),
			];
			DB::table("blog_contents")->where('id', $row_blog->id)->update($updated_date);
		} else {
			// update status unlike blog
			$updated_date = [
				'updated_at' => date("Y-m-d H:i:s"),
				'bllike_active' => $status_like,
			];
			DB::table("blog_likes")->where('id', $row_like->id)->update($updated_date);
			
			if($status_like==0 && $row_blog->bct_delete==0){
				// update count like...
				$updated_date = [
					'updated_at' => date("Y-m-d H:i:s"),
					'bct_like' => ($row_blog->bct_like-1),
				];
				DB::table("blog_contents")->where('id', $row_blog->id)->update($updated_date);
			} 
			if($status_like==1 && $row_blog->bct_delete==0){
				// update count like...
				$updated_date = [
					'updated_at' => date("Y-m-d H:i:s"),
					'bct_like' => ($row_blog->bct_like+1),
				];
				DB::table("blog_contents")->where('id', $row_blog->id)->update($updated_date);
			}
		}
        // tampilkan respon data		
        $data = [
            'status' => [
                'code' => 200,
                'description' => 'Success',
                'status_like' => (int)$status_like,
            ],
        ];
        return response()->json($data);
    }
	
}
