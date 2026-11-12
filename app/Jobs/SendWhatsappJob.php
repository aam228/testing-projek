<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Exception;

class SendWhatsappJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $phone;
    protected $message;
    protected $log_api;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($phone, $message, $log_api = true)
    {
        $this->phone = $phone;
        $this->message = $message;
        $this->log_api = $log_api;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $phone = $this->phone;
        $phone = str_replace(['+',' '], ['',''], $phone);
        $check = substr($phone, 0, 1);
        if ($check == '0' || $check == 0) {
            $phone = preg_replace('/^0/', '62', $phone);
        }

        $dataSending = [
            "api_key" => config('services.whatsapp.watzap_api_key'),
            "number_key" => config('services.whatsapp.watzap_number_key'), // Menggunakan official number_key
            "phone_no" => $phone,
            "message" => $this->message,
            "wait_until_send" => "1",
        ];

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.watzap.id/v1/send_message',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($dataSending),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        
        $getdata = json_decode($response);
        $status = ($getdata && isset($getdata->status) && $getdata->status == '200') ? 'Success' : 'Failed';

        // Save log ke whatsapps table
        $data_log = [
            'wa_receiver' => $phone,
            'wa_phone' => $phone,
            'wa_message' => $this->message,
            'wa_status' => $status,
            'created_at' => date("Y-m-d H:i:s"),
        ];
        DB::table("whatsapps")->insertGetId($data_log);

        if ($this->log_api) {
            // Save log API 
            $data_log_api = [
                'lwa_type' => 'api_watzap',
                'lwa_phone' => $phone,
                'lwa_description' => $response,
                'created_at' => date("Y-m-d H:i:s"),
            ];
            DB::table("log_whatsapp_api")->insertGetId($data_log_api);
        }
        
        if ($status == 'Failed') {
            throw new Exception("Watzap API Failed: " . $response);
        }
    }
}
