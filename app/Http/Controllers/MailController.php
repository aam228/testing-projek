<?php
 
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Exception;
 
class MailController extends Controller
{
	/*
	public function index()
	{
		//Mail::to("susanto.wibowoo@gmail.com")->send(new MalasngodingEmail());
		$details = [
          'link' => url(''),
        ];
		Mail::to("susanto.wibowoo@gmail.com")->send(new TestingMail($details));
		return "Email telah dikirim";
	}
	*/
	
	public function kirim_otp(Request $request)
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

		$email 	= $request->email;
		if(empty($email)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Email tidak boleh kosong'
                ]
            ];
            return response()->json($data);
		}
		
        $arr_cust = DB::table("customers")
		->select('*')
		->where('cust_email', '=', $email)
		->where('cust_delete', '=', '0')
        ->get()->first();
        if(!empty($arr_cust)){
            $kode_otp = rand(10000,99999);
    		// update master address
    		$updated_cust = [
    			'cust_otp' => $kode_otp,
    		];
    		DB::table("customers")->where('id', $arr_cust->id)->update($updated_cust);
            
            try {
    		    $body = "<p>Kode OTP kamu: <b>{$kode_otp}</b> OTP ini bersifat RAHASIA harap tidak dibagikan ke siapapun. Silakan masukan kode ini untuk melakukan Reset Pasword, Terima Kasih.</p>";
    		    Mail::to($arr_cust->cust_email)->queue(new \App\Mail\OtpEmail($body, 'Reset Password | topscore.id'));
    		    $r['status'] = 200;
    		    $r['description'] = 'kode OTP berhasil di kirim';
            } catch (Exception $e) {
                $r['status'] = 500;
                $r['description'] = 'Gagal mengirim email: ' . $e->getMessage();
            }
        } else {
            $r['status'] = 400;
            $r['description'] = 'Email tidak ditemukan';
        }
		return response()->json($r);
	}
	
    public function kirim_otp_verify_mail(Request $request)
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

		$email 	= $request->email;
		if(empty($email)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Email tidak boleh kosong'
                ]
            ];
            return response()->json($data);
		}
		
        $kode_otp = rand(1000, 9999);
        
        try {
            $body = "<p>Kode OTP kamu: <b>{$kode_otp}</b> OTP ini bersifat RAHASIA harap tidak dibagikan ke siapapun. Silakan masukan kode ini untuk melakukan validasi email anda, Terima Kasih.</p>";
            Mail::to($email)->queue(new \App\Mail\OtpEmail($body));
            $r['status'] = 200;
            $r['otp'] = $kode_otp;
            $r['description'] = 'kode OTP berhasil di kirim';
        } catch (Exception $e) {
            $r['status'] = 500;
            $r['description'] = 'Gagal mengirim email OTP: ' . $e->getMessage();
        }
		return response()->json($r);
	}
	
	// kirim ticket
	public function kirim_ticket(Request $request)
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

		$email 	= $request->email;
		if(empty($email)){
            $data = [
                'status' => [
                    'code' => 400,
                    'description' => 'Email tidak boleh kosong'
                ]
            ];
            return response()->json($data);
		}
		
        $arr_cust = DB::table("customers")
		->select('*')
		->where('cust_email', '=', $email)
		->where('cust_delete', '=', '0')
        ->get()->first();
        if(!empty($arr_cust)){
            $kode_otp = rand(10000,99999);
            $event_name = "GOES TO SCHOOL";
			
            try {
                $body = "<p>E-Ticket Event {$event_name} kamu: <b>{$kode_otp}</b>. Harap simpan e-ticket ini untuk verifikasi saat event berlangsung.</p>";
    		    Mail::to($arr_cust->cust_email)->queue(new \App\Mail\OtpEmail($body, 'E-Ticket Event ' . $event_name . ' | topscore.id'));
    		    $r['status'] = 200;
    		    $r['description'] = 'E-ticket berhasil di kirim';
            } catch (Exception $e) {
                $r['status'] = 500;
                $r['description'] = 'Gagal mengirim e-ticket: ' . $e->getMessage();
            }
        } else {
            $r['status'] = 400;
            $r['description'] = 'Email tidak ditemukan';
        }
		return response()->json($r);
	}
 
}
?>