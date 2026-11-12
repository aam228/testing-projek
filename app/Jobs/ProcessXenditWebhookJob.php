<?php

namespace App\Jobs;

use App\Models\Mycoinscore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Job ini menjalankan SEMUA proses berat webhook Xendit (cek payment,
 * tambah koin/score, update status pesanan, kirim notif, WA, email)
 * di background, supaya CallbackController bisa langsung merespon
 * Xendit tanpa menunggu proses ini selesai (antisipasi timeout).
 *
 * Logic di dalam job ini adalah pemindahan 1:1 dari method index(),
 * CallbackVirtualAccount(), dan CallbackEwallet() yang lama di
 * CallbackController, dipisah per channel supaya perbedaan kecil
 * antar channel (yang memang sudah ada sebelumnya) tetap terjaga.
 */
class ProcessXenditWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = 10;
    public $timeout = 90;

    protected string $channel; // 'qr' | 'va' | 'ewallet'
    protected string $jsonParsing;

    public function __construct(string $channel, string $jsonParsing)
    {
        $this->channel = $channel;
        $this->jsonParsing = $jsonParsing;
    }

    public function handle()
    {
        $data = json_decode($this->jsonParsing);

        switch ($this->channel) {
            case 'qr':
                $this->processQr($data);
                break;
            case 'va':
                $this->processVirtualAccount($data);
                break;
            case 'ewallet':
                $this->processEwallet($data);
                break;
        }
    }

    private function checkData($table, $select, $where)
    {
        return DB::table($table)->select($select)->where($where)->get()->first();
    }

    /**
     * Guard idempotensi: kunci baris payment_xendit yang match external_id
     * DAN belum pernah diproses (processed_at masih NULL), lalu langsung
     * tandai processed_at supaya kalau webhook yang sama dikirim ulang oleh
     * Xendit (atau job ke-retry), tidak diproses dua kali / koin dobel.
     */
    private function lockPayment($referenceId, array $select)
    {
        if (empty($referenceId)) {
            return null;
        }

        return DB::transaction(function () use ($referenceId, $select) {
            $arr_cek = DB::table('payment_xendit')
                ->select($select)
                ->where('external_id', $referenceId)
                ->whereNull('processed_at')
                ->lockForUpdate()
                ->first();

            if (!$arr_cek) {
                return null;
            }

            DB::table('payment_xendit')
                ->where('id_payment', $arr_cek->id_payment)
                ->update(['processed_at' => now()]);

            return $arr_cek;
        });
    }

    // ================= Channel: QR Code (dulu: CallbackController::index) =================
    private function processQr($data)
    {
        if (empty($data->status)) {
            return;
        }

        $reference_id = $data->qr_code->external_id ?? null;
        $arr_cek = $this->lockPayment($reference_id, ['id_payment', 'external_id', 'type_data', 'account_number', 'id_lookup']);
        if (!$arr_cek) {
            return;
        }

        $status_pay = ($data->status == 'COMPLETED') ? '1' : '0';
        $status_pay_text = ($data->status == 'COMPLETED') ? 'PAID' : 'UNPAID';

        // 1. Update status pesanan DULU (data kritis agar order muncul di daftar pesanan apps).
        //    Dulu: pos_status selalu 'PAID' walau status callback EXPIRED (bug: order expired
        //    ikut jadi PAID dengan payment_status 0) dan channel QR tidak kirim notif/WA.
        try {
            if ($arr_cek->type_data == 'event') {
                $this->handleEventPaid($arr_cek, $status_pay);
            } elseif ($arr_cek->type_data == 'pos') {
                DB::table('pos_transactions')->where('external_id', $arr_cek->external_id)->update([
                    'pos_status' => $status_pay_text,
                    'pt_callback' => $this->jsonParsing,
                    'pt_callback_date' => date('Y-m-d H:i:s'),
                    'payment_status' => $status_pay,
                ]);

                if ($status_pay == 1) {
                    $this->sendWebhook($arr_cek->external_id);
                    $arr_pos = $this->checkData('pos_transactions', ['*'], ['external_id' => $arr_cek->external_id]);
                    if ($arr_pos) {
                        $arr_customers = $this->checkData('customers', ['id', 'cust_name', 'cust_phone'], ['id' => $arr_pos->cust_id]);
                        if ($arr_customers) {
                            $this->waSend($arr_customers->cust_phone, $arr_customers->cust_name, $arr_pos->pos_invoice, $arr_pos->pos_real_price);
                        }

                        // hanya orderan aplikasi (paritas dengan channel VA/ewallet)
                        if ($arr_pos->is_apps == 1) {
                            DB::table('apps_notif')->insertGetId([
                                'id_lookup' => $arr_pos->id,
                                'cust_id' => $arr_pos->cust_id,
                                'type_data' => 'pos',
                                'an_title' => 'Pesanan sudah dibayar',
                                'an_description' => 'Pesanan ' . $arr_pos->pos_invoice . ' telah berhasil dibayarkan dengan nominal Rp ' . number_format($arr_pos->pos_real_price, 0, ',', '.'),
                                'created_at' => date('Y-m-d H:i:s'),
                            ]);
                        }
                    }
                }
            } elseif ($arr_cek->type_data == 'reseller' && $status_pay == 1) {
                $this->handleResellerPaid($arr_cek, true);
            }
        } catch (\Throwable $e) {
            Log::warning('XenditWebhook[qr]: gagal update status pesanan', [
                'external_id' => $reference_id,
                'status' => $data->status,
                'error' => $e->getMessage(),
            ]);
        }

        // 2. Side-effect (koin/score) terakhir & di-guard: tidak boleh menggagalkan job,
        //    karena processed_at sudah ditandai lockPayment (retry akan no-op).
        //    Dulu: koin diberikan walau status callback bukan COMPLETED.
        if ($status_pay == 1) {
            try {
                (new Mycoinscore)->saveCoinscore($arr_cek->type_data, $arr_cek->external_id);
            } catch (\Throwable $e) {
                Log::warning('XenditWebhook[qr]: gagal simpan koin/score', [
                    'external_id' => $arr_cek->external_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        DB::table('payment_xendit')->where('id_payment', $arr_cek->id_payment)->update([
            'flag_pembayaran' => $status_pay,
            'status' => $data->status,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    // ========== Channel: Virtual Account (dulu: CallbackController::CallbackVirtualAccount) ==========
    private function processVirtualAccount($data)
    {
        if (empty($data->id)) {
            return;
        }

        $reference_id = $data->external_id ?? null;
        $arr_cek = $this->lockPayment($reference_id, ['id_payment', 'external_id', 'type_data', 'account_number', 'id_lookup', 'expected_amount']);
        if (!$arr_cek) {
            return;
        }

        // 1. Update status pesanan dulu (kritis), side-effect (koin/WA/notif/email) di-guard
        //    agar tidak pernah menggagalkan job setelah processed_at ditandai.
        try {
            if ($arr_cek->type_data == 'event') {
                $this->handleEventPaid($arr_cek, '1');
            }

            if ($arr_cek->type_data == 'donasi') {
                DB::table('donations')->where('id', $arr_cek->id_lookup)->update([
                    'dn_status' => 'PAID',
                    'dn_callback' => $this->jsonParsing,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            if ($arr_cek->type_data == 'pos') {
                DB::table('pos_transactions')->where('external_id', $arr_cek->external_id)->update([
                    'pos_status' => 'PAID',
                    'pt_callback' => $this->jsonParsing,
                    'pt_callback_date' => date('Y-m-d H:i:s'),
                    'payment_status' => '1',
                ]);

                $this->sendWebhook($arr_cek->external_id);
                $arr_pos = $this->checkData('pos_transactions', ['*'], ['external_id' => $arr_cek->external_id]);
                if ($arr_pos) {
                    $arr_customers = $this->checkData('customers', ['id', 'cust_name', 'cust_phone'], ['id' => $arr_pos->cust_id]);
                    if ($arr_customers) {
                        $this->waSend($arr_customers->cust_phone, $arr_customers->cust_name, $arr_pos->pos_invoice, $arr_cek->expected_amount);
                    }

                    // hanya orderan aplikasi (perilaku asli: notif cuma utk is_apps == 1)
                    if ($arr_pos->is_apps == 1) {
                        DB::table('apps_notif')->insertGetId([
                            'id_lookup' => $arr_cek->external_id,
                            'cust_id' => $arr_pos->cust_id,
                            'type_data' => 'pos',
                            'an_title' => 'Pesanan sudah dibayar',
                            'an_description' => 'Pesanan ' . $arr_pos->pos_invoice . ' telah berhasil dibayarkan dengan nominal Rp ' . number_format($arr_pos->pos_real_price, 0, ',', '.'),
                            'created_at' => date('Y-m-d H:i:s'),
                        ]);
                    }
                }
            }

            if ($arr_cek->type_data == 'reseller') {
                $this->handleResellerPaid($arr_cek, true);
            }
        } catch (\Throwable $e) {
            Log::warning('XenditWebhook[va]: gagal update status pesanan', [
                'external_id' => $reference_id,
                'error' => $e->getMessage(),
            ]);
        }

        // 2. Koin/score terakhir & di-guard
        try {
            (new Mycoinscore)->saveCoinscore($arr_cek->type_data, $arr_cek->external_id);
        } catch (\Throwable $e) {
            Log::warning('XenditWebhook[va]: gagal simpan koin/score', [
                'external_id' => $arr_cek->external_id,
                'error' => $e->getMessage(),
            ]);
        }

        DB::table('payment_xendit')->where('id_payment', $arr_cek->id_payment)->update([
            'flag_pembayaran' => '1',
            'status' => 'PAID',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    // ================ Channel: E-Wallet (dulu: CallbackController::CallbackEwallet) ================
    private function processEwallet($data)
    {
        if (empty($data->business_id)) {
            return;
        }

        $data_sub = $data->data;
        $reference_id = $data_sub->reference_id ?? null;
        $arr_cek = $this->lockPayment($reference_id, ['id_payment', 'external_id', 'type_data', 'account_number', 'id_lookup', 'expected_amount']);
        if (!$arr_cek) {
            return;
        }

        if ($data_sub->status == 'SUCCEEDED') {
            $status_pay = '1';
            $status_pay_text = 'PAID';
        } else {
            $status_pay = '0';
            $status_pay_text = 'UNPAID';
        }

        // 1. Update status pesanan dulu (kritis), side-effect di-guard
        try {
            if ($arr_cek->type_data == 'event' && $status_pay == 1) {
                $this->handleEventPaid($arr_cek, $status_pay);
            }

            if ($arr_cek->type_data == 'pos') {
                DB::table('pos_transactions')->where('external_id', $arr_cek->external_id)->update([
                    'pos_status' => $status_pay_text,
                    'pt_callback' => $this->jsonParsing,
                    'pt_callback_date' => date('Y-m-d H:i:s'),
                    'payment_status' => $status_pay,
                ]);

                if ($status_pay == 1) {
                    $this->sendWebhook($arr_cek->external_id);
                    $arr_pos = $this->checkData('pos_transactions', ['*'], ['external_id' => $arr_cek->external_id]);
                    if ($arr_pos) {
                        $arr_customers = $this->checkData('customers', ['id', 'cust_name', 'cust_phone'], ['id' => $arr_pos->cust_id]);
                        if ($arr_customers) {
                            $this->waSend($arr_customers->cust_phone, $arr_customers->cust_name, $arr_pos->pos_invoice, $arr_cek->expected_amount);
                        }

                        DB::table('apps_notif')->insertGetId([
                            'id_lookup' => $arr_pos->id,
                            'cust_id' => $arr_pos->cust_id,
                            'type_data' => 'pos',
                            'an_title' => 'Pesanan sudah dibayar',
                            'an_description' => 'Pesanan ' . $arr_pos->pos_invoice . ' telah berhasil dibayarkan dengan nominal Rp ' . number_format($arr_pos->pos_real_price, 0, ',', '.'),
                            'created_at' => date('Y-m-d H:i:s'),
                        ]);
                    }
                }
            }

            if ($arr_cek->type_data == 'reseller' && $status_pay == 1) {
                // perilaku asli: WA reseller untuk channel ewallet memang tidak dikirim (sudah dikomentari di kode lama)
                $this->handleResellerPaid($arr_cek, false);
            }
        } catch (\Throwable $e) {
            Log::warning('XenditWebhook[ewallet]: gagal update status pesanan', [
                'external_id' => $reference_id,
                'status' => $data_sub->status,
                'error' => $e->getMessage(),
            ]);
        }

        // 2. Koin/score terakhir & di-guard (hanya utk pembayaran sukses)
        if ($status_pay == 1) {
            try {
                (new Mycoinscore)->saveCoinscore($arr_cek->type_data, $arr_cek->external_id);
            } catch (\Throwable $e) {
                Log::warning('XenditWebhook[ewallet]: gagal simpan koin/score', [
                    'external_id' => $arr_cek->external_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        DB::table('payment_xendit')->where('id_payment', $arr_cek->id_payment)->update([
            'flag_pembayaran' => $status_pay,
            'status' => $status_pay_text,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    // ================= Helper bersama antar channel =================

    private function handleEventPaid($arr_cek, $status_pay)
    {
        DB::table('event_orders')->where('id', $arr_cek->id_lookup)->update([
            'payment_status' => $status_pay,
            'eo_callback' => $this->jsonParsing,
            'eo_callback_date' => date('Y-m-d H:i:s'),
        ]);

        $arr_events = DB::table('event_orders')->where('id', $arr_cek->id_lookup)->first();
        if (!$arr_events) {
            return;
        }

        DB::table('apps_notif')->insertGetId([
            'id_lookup' => $arr_events->id,
            'cust_id' => $arr_events->u_id,
            'type_data' => 'event',
            'an_title' => 'Pesanan sudah dibayar',
            'an_description' => 'Pesanan ' . $arr_events->eo_invoice . ' telah berhasil dibayarkan dengan nominal Rp ' . number_format($arr_events->eo_total, 0, ',', '.'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $master_event = DB::table('event_tops')->where('id', $arr_events->et_id)->first();
        $master_cust = DB::table('customers')->where('id', $arr_events->u_id)->first();
        if (!$master_event || !$master_cust) {
            return;
        }

        $event_date = date('d M Y H:i', strtotime($master_event->et_start_date . ' ' . $master_event->et_start_time)) . ' s.d ' .
            date('d M Y H:i', strtotime($master_event->et_end_date . ' ' . $master_event->et_end_time));

        $details = [
            'invoice' => $arr_events->eo_invoice,
            'cust_name' => $arr_events->eo_name,
            'cust_phone' => $arr_events->eo_phone,
            'cust_email' => $arr_events->eo_email,
            'cust_address' => $arr_events->eo_address,
            'event_name' => $master_event->et_title,
            'event_price' => $master_event->et_price,
            'event_address' => $master_event->et_address,
            'event_date' => $event_date,
            'event_note' => str_replace(['\n'], ['<br>'], $master_event->et_description),
            'event_type' => $master_event->et_type,
            'et_link_online' => $master_event->et_link_online,
            'event_group_wa' => $master_event->et_group_whatsapp,
        ];

        if ($arr_events->eo_email != '') {
            $this->mailTopscore($arr_events->eo_email, $details);
        }
        if ($master_cust->cust_phone != '') {
            $this->waSendEvent($master_cust->cust_phone, $master_cust->cust_name, $arr_events->eo_invoice, $master_event->et_price);
        }
    }

    private function handleResellerPaid($arr_cek, $sendWa)
    {
        DB::table('reseller_registrations')->where('id', $arr_cek->id_lookup)->update([
            'rr_payment_status' => '1',
            'rr_json_callback' => $this->jsonParsing,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $arr_lookup = $this->checkData('reseller_registrations', ['*'], ['rr_external_id' => $arr_cek->external_id]);
        if (!$arr_lookup) {
            return;
        }

        DB::table('customers')->where('id', $arr_lookup->cust_id)->update(['cust_reseller' => '1']);

        if ($sendWa) {
            $this->waSendReseller($arr_lookup->rr_store_phone, $arr_lookup->rr_store_name, $arr_lookup->rr_external_id, $arr_lookup->rr_price);
        }
    }

    // ================= WA & Email sender (dipindah dari CallbackController, timeout diperbaiki) =================

    private function waSend($phone, $name, $invoice, $total)
    {
        $phone = str_replace('+', '', $phone);
        if (substr($phone, 0, 1) == '0') {
            $phone = preg_replace('/^0/', '62', $phone);
        }
        $message = "Halo *" . $name . "*\nTerima kasih sudah berbelanja di TOPSCORE.\nPembayaran kamu dengan invoice *#" . $invoice . "* sudah kami terima\n\nTotal yang telah dibayar:\nRp " . $total . "\n\nPesananmu sedang kami siapkan dan akan segera dikirim.\nKami akan mengabari lagi begitu nomor resinya tersedia ya, kak";

        if ($phone) {
            DB::table('whatsapps')->insertGetId([
                'wa_receiver' => $phone,
                'wa_phone' => $phone,
                'wa_message' => $message,
                'wa_status' => 'Waiting',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function waSendEvent($phone, $name, $invoice, $total)
    {
        $arr_orders = DB::table('event_orders')->where('eo_invoice', $invoice)->first();
        $text_join = '';
        if ($arr_orders) {
            $arr_event = DB::table('event_tops')->where('id', $arr_orders->et_id)->first();
            if ($arr_event && !empty($arr_event->et_group_whatsapp)) {
                $text_join = 'Silahkan join ke group whatsapp untuk mendapatkan link dokumentasi main bareng, dengan klik link ini : ' . $arr_event->et_group_whatsapp;
            }
        }

        $phone = str_replace('+', '', $phone);
        if (substr($phone, 0, 1) == '0') {
            $phone = preg_replace('/^0/', '62', $phone);
        }
        $message = "Halo *" . $name . "*!\n\nTerima kasih telah melakukan order event di TOPSCORE.\nKami telah menerima pembayaran anda dengan Invoice *#" . $invoice . "*.\n\nTotal yang sudah dibayar adalah:\nRp " . number_format($total, 0, ',', '.') . "\n\n" . $text_join . "\n------------------------\nBalas pesan ini jika butuh bantuan :)";

        $this->sendWatzap($phone, $message);
    }

    private function waSendReseller($phone, $name, $invoice, $total)
    {
        $phone = str_replace('+', '', $phone);
        if (substr($phone, 0, 1) == '0') {
            $phone = preg_replace('/^0/', '62', $phone);
        }
        $message = "Halo *" . $name . "*!\n\nTerima kasih telah mendaftar sebagai Reseller TOPSCORE.\nKami telah menerima pembayaran kamu dengan Invoice *#" . $invoice . "*.\n\nTotal yang sudah dibayar adalah:\nRp " . number_format($total, 0, ',', '.') . "\n\nNikmati potongan spesial sebagai Reseller Topscore\nPotongan spesial hanya berlaku untuk belanja di Aplikasi Topscore\n------------------------\nBalas pesan ini jika butuh bantuan :)";

        $this->sendWatzap($phone, $message);
    }

    private function sendWatzap($phone, $message)
    {
        $dataSending = [
            'api_key' => config('services.whatsapp.watzap_api_key'),
            'number_key' => config('services.whatsapp.watzap_number_key'),
            'phone_no' => $phone,
            'message' => $message,
            'wait_until_send' => '1',
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.watzap.id/v1/send_message',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30, // dulu 0 (infinite), bikin worker bisa nyangkut lama kalau Watzap lambat
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($dataSending),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);
        $response = curl_exec($curl);
        curl_close($curl);

        return json_decode($response);
    }

    private function mailTopscore($email, $dataEmail)
    {
        $data = [
            'client_id' => '8025087a9e2e8d0e95e2da2152e8be2f55276616',
            'api_key' => config('services.whatsapp.watzap_api_key'),
            'email' => $email,
            'data_email' => json_encode($dataEmail),
        ];

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30); // dulu tidak diset (default infinite)
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($curl, CURLOPT_URL, 'https://cron.apptopscore.com/api/kirim_ticket_event');
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

        $result = curl_exec($curl);
        curl_close($curl);

        return json_decode($result);
    }

    private function sendWebhook($externalId)
    {
        if (empty($externalId)) {
            return;
        }

        $url = "https://topsystem.id/api/push_webhook";
        $postData = http_build_query([
            'external_id' => $externalId
        ]);

        $command = sprintf(
            "curl -X POST '%s' " .
            "-H 'Content-Type: application/x-www-form-urlencoded' " .
            "--max-time 30 " .
            "--connect-timeout 5 " .
            "-d '%s' " .
            "> /dev/null 2>&1 &",
            $url,
            $postData
        );

        exec($command);
    }
}
