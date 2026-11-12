<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Mycoinscore extends Model
{
    //use HasFactory;
    //protected $table = 'berita';

    /*
	public function checkData($select, $where)
    {
        $affected = DB::table($this->table)
            ->select($select)
            ->where($where)
            ->get()->first();
        return $affected;
    }
	*/

    public function saveCoinscore($type_data, $external_id)
    {
		/* 
		 $save = DB::table($this->table)->insert(array_merge($data, $created));
		 $save = DB::table($this->table)->where('id', $id)->update(array_merge($data, $updated));
 		 'created_at' => date('Y-m-d H:i:s')
		 'updated_at' => date('Y-m-d H:i:s')
		*/
		$cust_id = '';
		if($type_data=='pos'){
		    // cek produk kusus cashback 5%
			$product_jersey = DB::table("product_stocks")->select('id')->where("p_id","=","10738")->get();
			$in_specialproduct = array();
			if($product_jersey){
				foreach ($product_jersey as $row_jersey) {
					$in_specialproduct[] = $row_jersey->id;
				}
			}
			
		    $total_cashback = 0;
			$arr_transaksi = DB::table("pos_transactions")->where("external_id","=",$external_id)->first();
			if($arr_transaksi){
				// tambahkan koin 
				if($arr_transaksi->is_apps==1){
					// tampilkan data customer
					$arr_customer = DB::table("customers")->where("id","=",$arr_transaksi->cust_id)->first();
					$cust_id = $arr_customer->id;
					
					// cek semua data transaksi sesuai no external ID
					$query_transaksi = DB::table("pos_transactions")->where("external_id","=",$external_id)->get();
					$total_transaksi = 0; $total_awal=0; // $total_ongkir=0; $total_admin=0;
					foreach($query_transaksi as $row){
						$total_awal += $row->pos_real_price; // total semua data tanpa pengurangan ongkir dan admin
						$nilai_total_awal = ($row->pos_real_price - $row->pos_admin_cost - $row->pos_shipping);
						$total_transaksi += $nilai_total_awal;
						
						// cek pesanan details
						$query_dettransaksi = DB::table("pos_transaction_details")->where("pt_id","=",$row->id)->get();
						foreach($query_dettransaksi as $subrow){
							// kusus jersey garuda fantasy dapat 5% cachback
							if(in_array($subrow->pst_id,$in_specialproduct)){
								$total_cashback += (5/100) * $subrow->pos_td_total_price; // dapat 5% cashback
							} else {
								$total_cashback += (1/100) * $subrow->pos_td_total_price;
							}
						}
						
						// kirim wa ke admin toko
						$arr_store = DB::table("stores")->where("id","=",$row->st_id_ref)->first();
						$this->waSend_admin($arr_store->st_phone, $arr_store->st_name, $arr_customer->cust_name, $arr_customer->cust_phone, $row->pos_invoice, $row->pos_real_price);
					}
					
					//$tambahan_coin = (1/100) * ($total_transaksi);
					$tambahan_coin = $total_cashback;
					$cust_coin_update = $arr_customer->cust_coin + $tambahan_coin;
					// cek range total transaksi
					if($total_awal<=100000){
						$nilai_score = 1;
					} else 
					if($total_awal>100000 && $total_awal<=500000){
						$nilai_score = 3;
					} else 
					if($total_awal>500000 && $total_awal<=1000000){
						$nilai_score = 5;
					} else 
					if($total_awal>1000000){
						$nilai_score = 10;
					}
					$cust_score_update = $arr_customer->cust_score + $nilai_score;
					DB::table("customers")->where('id', $arr_customer->id)->update(['cust_coin'=>$cust_coin_update, 'cust_score'=>$cust_score_update]);
					// input coin log
					$data_coin = [
						'cust_id' => $cust_id,
						'ccoin_type' => 'pos',
						'ccoin_lookup_id' => $arr_transaksi->id,
						'ccoin_description' => 'Belanja no order '.$arr_transaksi->pos_invoice,
						'ccoin_value' => $tambahan_coin,
						'created_at' => date("Y-m-d H:i:s"),
					];
					DB::table("customer_coins")->insertGetId(array_merge($data_coin));							
					// input score log
					$data_score = [
						'cust_id' => $cust_id,
						'cscore_type' => 'pos',
						'cscore_lookup_id' => $arr_transaksi->id,
						'cscore_description' => 'Belanja no order '.$arr_transaksi->pos_invoice,
						'cscore_value' => $nilai_score,
						'created_at' => date("Y-m-d H:i:s"),
					];
					DB::table("customer_scores")->insertGetId(array_merge($data_score));
					
					// tambahan coin dari referal
					/*
					if($arr_customer->cust_referal_ref!=''){
						$arr_referal = DB::table("customers")->where("cust_referal_code","=",$arr_customer->cust_referal_ref)->first();
						if($arr_referal){
							$cust_coin_referal_update = (0.5/100) * ($total_transaksi); // 0.5% dari total belanja produk
							$cust_coin_referal = $arr_referal->cust_coin + $cust_coin_referal_update;
							DB::table("customers")->where('id', $arr_referal->id)->update(['cust_coin'=>$cust_coin_referal]);
							// input coin log
							$data_coin = [
								'cust_id' => $arr_referal->id,
								'ccoin_type' => 'pos',
								'ccoin_lookup_id' => $arr_transaksi->id,
								'ccoin_description' => 'Referal dari belanja no order '.$arr_transaksi->pos_invoice,
								'ccoin_value' => $cust_coin_referal_update,
								'created_at' => date("Y-m-d H:i:s"),
							];
							DB::table("customer_coins")->insertGetId(array_merge($data_coin));							
						}
					} // end tambahan coin referal
					*/
					
					// tambahan coin dari referal
					$arr_referal = DB::table("customer_referals")->where("cust_id_ref","=",$arr_customer->id)->first();
					if($arr_referal){
						$arr_referal_customer=DB::table("customers")->where("id","=",$arr_referal->cust_id)->first();
						if($arr_referal_customer){
							$cust_coin_referal_update = (0.5/100) * ($total_transaksi); // 0.5% dari total belanja produk
							$cust_coin_referal = $arr_referal_customer->cust_coin + $cust_coin_referal_update;
							DB::table("customers")->where('id', $arr_referal_customer->id)->update(['cust_coin'=>$cust_coin_referal]);
							// input coin log
							$data_coin = [
								'cust_id' => $arr_referal_customer->id,
								'ccoin_type' => 'pos',
								'ccoin_lookup_id' => $arr_transaksi->id,
								'ccoin_description' => 'Referal dari belanja no order '.$arr_transaksi->pos_invoice,
								'ccoin_value' => $cust_coin_referal_update,
								'created_at' => date("Y-m-d H:i:s"),
							];
							DB::table("customer_coins")->insertGetId(array_merge($data_coin));							
						}
					}
				}
			}
		}
		// untuk event
		if($type_data=='event'){
			$arr_transaksi = DB::table("event_orders")->where("external_id","=",$external_id)->first();
			if($arr_transaksi){
			    $arr_events = DB::table("event_tops")->where("id","=",$arr_transaksi->et_id)->first();
				// tampilkan data customer
				$arr_customer = DB::table("customers")->where("id","=",$arr_transaksi->u_id)->first();
				$cust_id = $arr_customer->id;
				if($arr_events){
				    if($arr_events->et_score>0){
				        $nilai_score = $arr_events->et_score;
				    } else {
				        $nilai_score = 1;
				    }
				} else {
				    $nilai_score = 1;
				}
				$cust_score_update = $arr_customer->cust_score + $nilai_score;
				DB::table("customers")->where('id', $arr_customer->id)->update(['cust_score'=>$cust_score_update]);
				// input score log
				$data_score = [
					'cust_id' => $cust_id,
					'cscore_type' => 'event',
					'cscore_lookup_id' => $arr_transaksi->id,
					'cscore_description' => 'Join event no order '.$arr_transaksi->eo_invoice,
					'cscore_value' => $nilai_score,
					'created_at' => date("Y-m-d H:i:s"),
				];
				DB::table("customer_scores")->insertGetId(array_merge($data_score));
			}
		}
		
		if(!empty($cust_id)){
			// update level jika sudah mencapai score tertentu
			$this->updateLevel($cust_id);
		}
    }
	
	private function updateLevel($cust_id)
	{
		$arr_customer = DB::table("customers")->where("id","=",$cust_id)->first();
		$arr_level = DB::table("apps_levels")
					 ->where("al_min",">=",$arr_customer->cust_score)
					 ->where("al_max","<=",$arr_customer->cust_score)
					 ->first();
		if($arr_level){
			if($arr_level->al_level!=$arr_customer->cust_membership_type){
				// update level menyesuikan nilai score yang di peroleh
				DB::table("customers")->where('id', $arr_customer->id)->update(['cust_membership_type'=>$arr_level->al_level]);
			}
		}
	}

    // kirim WA ke admin sesuai store
	private function waSend_admin($store_phone, $store_name, $nama_customer, $phone_customer, $invoice, $total)
    {
        return false;
        $phone = str_replace('+', '', $store_phone);
        $check = substr($phone, 0, 1);
        if ($check == '0' || $check == 0) {
            $phone = preg_replace('/^0/', '62', $phone);
        }
        $message = "Halo *".$store_name."*!

Ada orderan baru di aplikasi nih dengan Invoice *#".$invoice."*.
atas nama *".$nama_customer."* (".$phone_customer.")
total dibayar Rp ".number_format($total,0,',','.')."

Segera di proses ya min pesanannya
------------------------
Terimakasih :)";

        $curl = curl_init();
        /*
        $token = config('services.whatsapp.wablas_token');
        $data = [
            'phone' => $phone,
            'message' => $message,
        ];
        curl_setopt($curl, CURLOPT_HTTPHEADER,
            array(
                "Authorization: $token",
            )
        );
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($curl, CURLOPT_URL, "https://pati.wablas.com/api/send-message");
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

        $result = curl_exec($curl);
        curl_close($curl);
        $getdata = json_decode($result);
        */
        
        $dataSending = [
		  "api_key" => config('services.whatsapp.watzap_api_key'),
			"number_key" => "rS7eNiU9U4DXPRY6",
			"phone_no" => $phone,
			"message" => $message,
			"wait_until_send" => "1",
		];
		
		//$curl = curl_init();
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
		//echo $response;
		if($getdata->status=='200') {
			// save log kirim whatsapp ke admin toko
			$data_notifadmin = [
				'lwa_phone' => $phone,
				'lwa_description' => $message,
				'created_at' => date("Y-m-d H:i:s"),
			];
			DB::table("log_whatsapp_api")->insertGetId($data_notifadmin);
           	return 'Success';
		} else {
			return 'Fail';
		}
        /*
        if ($getdata->status=='pending') {
            return 'Success';
        } else {
            return 'Fail';
        }
        */
    }
}
