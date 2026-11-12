<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Mail\CrmEmail;
use Mail;
use Hash;


class CrmEmailController extends Controller
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
		
		$to = $request->to;
		$template_id = $request->template_id;
		$cust_id = $request->cust_id;
		if (!$to || !$template_id) {
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Field to dan template_id tidak boleh kosong'
                ]
            ];
            return response()->json($data);
        }
		
		$arr_customer = DB::table("customers")->where("id","=",$cust_id)->first();
		if($arr_customer){
    		$nama_customer = $arr_customer->cust_name;
		} else {
    		$email = explode("@",$to);
            $nama_customer = str_replace(['.'],[' '],$email[0]);
		}

		// cek join
		//$arr_template = DB::table("template_emails")->where("id","=",2)->where("te_delete","=",'0')->first();
		$arr_template = DB::table("template_emails")->where("id","=",$template_id)->first();

		$details = 
		[
			'id' => $arr_template->id,
			'te_type' => $arr_template->te_type,
			'te_subject' => $arr_template->te_subject,
			'te_content' => $arr_template->te_content,
			'nama_customer' => $nama_customer,
			'created_at' => $arr_template->created_at,
		];

		Mail::to($to)->send(new CrmEmail($details));
		$data = [
			'status' => [
				'code' => 200,
				'description' => 'Email berhasil di kirim'
			]
		];
        return response()->json($data);
    }
    
    // taampilkan html data
    public function indexHtml(Request $request)
    {
		/*
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
		*/
		
		// cek join
		//$arr_template = DB::table("template_emails")->where("id","=",2)->where("te_delete","=",'0')->first();
		$arr_template = DB::table("template_emails")->where("id","=",2)->first();

		$details = 
		[
			'id' => $arr_template->id,
			'te_type' => $arr_template->te_type,
			'te_subject' => $arr_template->te_subject,
			'te_content' => $arr_template->te_content,
			'created_at' => $arr_template->created_at,
		];

		//Mail::to('susanto.wibowoo@gmail.com')->send(new CrmEmail($details));
        $data = [
                'title' => "Email",
                'subtitle' => 'Data Email',
                'segment' => request()->segment(1),
                'details' => $details,
        ];
        return view('app.email.ecrm_blast_2', compact('data'));
    }
}
