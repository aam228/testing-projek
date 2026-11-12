<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Hash;

class EventduaController extends Controller
{
	protected $table = 'event_tops';
	protected $server_domain = 'https://api.xendit.co';
    //protected $secret_api_key = 'xnd_development_BiwG1eyewnIqZZnY05AV6yaTmcXji00SbXM1JIJy2aM0t1hqduxxCrLCodi'; // SANDBOX
	protected $secret_api_key;

	public function __construct()
	{
		$this->secret_api_key = config('services.xendit.secret_key');
	}
	protected $url_server = 'https://topsystem.id/';

	private function checkData($table, $select, $where)
	{
		$affected = DB::table($table)
			->select($select)
			->where($where)
			->get()->first();
		return $affected;
	}

	// Cek View data
	public function CekEvent(Request $request)
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
			'ta_page_access' => "Event",
			'ta_description' => "Mengakses laman event aplikasi",
			'ta_lookup_id' => $request->event_id,
			'created_at' => date("Y-m-d H:i:s"),
			'ip_address' => $request->ip(),
		];
		$save_log = DB::table("traffic_apps")->insertGetId(array_merge($data_log));

		$id_event = $request->event_id;
		$type_event = $request->type_event; // (upcomming, berlangsung, selesai)
		$event_category = $request->event_category;

		// filter event ***
		$event_filter_date = $request->event_filter_date;
		$event_filter_text = $request->event_filter_text;

		$query_data = DB::table($this->table)
			->selectRaw('ts_event_tops.id as pid, et_title, et_start_date, et_start_time, et_end_date, et_end_time, et_address, et_banner, et_price, et_terms, et_description, et_quota, et_lat, et_lang, et_type, et_link_online, et_score, et_maps_url, et_category, evt_category, evt_name, et_city, et_tournament, et_blurhash, evt_icon, et_venue')
			->leftJoin('event_types', 'event_types.id', '=', 'event_tops.evt_id')
			->where('et_delete', '=', '0')
			->where(function ($w) use ($type_event, $id_event, $event_category, $request, $event_filter_date, $event_filter_text) {
				if (!empty($type_event) || $type_event >= "0") {
					if ($type_event == "1") { // berlangsung
						$w->where('et_start_date', '>=', date("Y-m-d"));
						$w->where('et_start_time', '<=', date("H:i:s"));
						$w->where('et_end_date', '<=', date("Y-m-d"));
						$w->where('et_end_time', '>=', date("H:i:s"));
					} else
						if ($type_event == "2") { // upcomming
							$w->whereRaw("CONCAT(ts_event_tops.et_start_date,' ',ts_event_tops.et_start_time) >= '" . date('Y-m-d H:i:s') . "'");
						} else
							if ($type_event == "0") { // selesai
								$w->whereRaw("CONCAT(ts_event_tops.et_end_date,' ',ts_event_tops.et_end_time) <= '" . date('Y-m-d H:i:s') . "'");
							}
				}

				// jika type event tidak di input
				if ($event_filter_date) {
					$date_filter = explode("|", $event_filter_date);
					$w->where('et_start_date', '>=', $date_filter[0]);
					$w->where('et_start_date', '<=', $date_filter[1]);
				}

				if ($event_filter_text) {
					// 1,2 (1=hari ini, 2=terdekat)
					if ($event_filter_text == 1) {
						$w->where('et_start_date', '=', date("Y-m-d"));
					}
					// event terdekat 14 hari dari hari ini ?
					if ($event_filter_text == 2) {
						$next14 = date('Y-m-d', strtotime('+14 days'));
						$w->where('et_start_date', '<=', $next14);
						$w->where('et_start_date', '>', date("Y-m-d"));
					}
				}

				// jika type event tidak di input
				/*
				else {
					$w->where('et_title', '=','event_null');
				}
				*/

				if (!empty($id_event)) {
					$w->where('event_tops.id', '=', $id_event);
				}
				// cari by nama event
				if (!empty($request->event_search)) {
					//$w->whereRaw("CONCAT(et_title, ' ', evt_name, ' ',evt_category)", 'LIKE', "%".$request->event_search."%");
					$w->where('event_tops.et_title', 'LIKE', "%" . $request->event_search . "%");
				}
				// cari kota
				if (!empty($request->event_city)) {
					$w->where('event_tops.et_city', '=', $request->event_city);
				}

				if (!empty($event_category)) {
					$w->where('event_tops.evt_id', '=', $event_category);
				}
			})
			//->orderBy('et_end_date','DESC')
			->orderBy('et_end_date', 'DESC')
			->orderBy('et_sort', 'ASC')
			->orderBy('evt_id', 'DESC')
			//->orderBy('et_end_date','ASC')
			//->get();
			->paginate(10);

		// tampilkan header event
		$event_home = DB::table("event_homes")->first();

		$results = array();
		if (!empty($query_data)) {
			foreach ($query_data as $row) {
				$event_users = DB::table("event_orders")
					->where("et_id", "=", $row->pid)
					->where("payment_status", "=", "1")
					->sum('eo_qty');
				if ($event_users > 0) {
					$jumlah_user = (int) $event_users;
				} else {
					$jumlah_user = 0;
				}
				// check product stock
				//$arr_pst = $this->checkData('product_stocks','id', ['p_id' => $row->product_id]);

				if (empty($row->et_quota)) {
					$quota = 0;
				} else {
					$quota = $row->et_quota;
				}
				//if(empty($row->et_quota)){$quota=($event_users+10);} else {$quota=$row->et_quota;}

				if ($type_event == "2") { // upcoming
					$jumlah_user = $jumlah_user;
					$quota = $quota;
				} else
					if ($type_event == "1") { // berlangsung
						$jumlah_user = 0;
						$quota = 0;
					} else
						if ($type_event == "0") { // selesai
							$jumlah_user = 0;
							$quota = 0;
						}
						// jika tidak ada pilihan tipe event
						else {
							if ($row->et_start_date . ' ' . $row->et_start_time <= date("Y-m-d H:i:s")) {
								$jumlah_user = 0;
								$quota = 0;
							} else {
								$jumlah_user = $jumlah_user;
								$quota = $quota;
							}
						}
				/*
				else { // selesai
					$jumlah_user = 0;
					$quota = 0;
				}
				*/

				// jika mencari salah 1 data event
				if (!empty($id_event)) {
					$jumlah_user = (int) $event_users;
					if (empty($row->et_quota)) {
						$quota = ($event_users + 10);
					} else {
						$quota = $row->et_quota;
					}
					//$quota = $row->et_quota;

					// cek jika sudah full, jadikan nol
					//if($quota<=$jumlah_user){$quota=0; $jumlah_user=0;}
					if ($quota <= $jumlah_user) {
						$quota = 0;
						$jumlah_user = 0;
					}

					//$quota = $quota;
					if ($row->et_start_date . ' ' . $row->et_start_time <= date("Y-m-d H:i:s")) {
						$quota = 0;
					}
				} else {
					// cek jika sudah full, jadikan nol
					if (empty($row->et_quota)) {
						$quota = (int) $event_users + 10;
					} else {
						if ($row->et_quota <= (int) $event_users) {
							$quota = 0;
							$jumlah_user = 0;
						}
					}
					//if($row->et_quota<=(int)$event_users){$quota=0; $jumlah_user=0;}
				}

				// data additional form
				$add_form = DB::table("event_master_forms")
					->where("et_id", "=", $row->pid)
					->where("emf_delete", "=", "0")
					->orderBy('emf_sort')
					->get();
				$array_form = array();
				$array_form_sub = array();
				if ($add_form) {
					foreach ($add_form as $rform) {
						$array_form_sub = array();
						if (!empty($rform->emf_option)) {
							$emf_option = explode(", ", $rform->emf_option);
						} else {
							$emf_option = array();
						}

						// jika ada quota
						if (count($emf_option) > 1) {
							$opsi_jawaban = array();
							foreach ($emf_option as $opilihan) {
								$opsi_pilihan = explode("#", $opilihan);
								//$opsi_jawaban[] = $opsi_pilihan[0];
								// jika ada quota
								if (count($opsi_pilihan) > 1) {
									// cek sudah berapa quota terpakai
									$quota_form = DB::table("event_form_answers")
										->leftJoin('event_orders', 'event_orders.id', '=', 'event_form_answers.eo_id')
										->leftJoin('event_master_forms', 'event_master_forms.id', '=', 'event_form_answers.emf_id')
										->where("payment_status", "=", "1")
										->where("event_form_answers.et_id", "=", $row->pid)
										->where("event_form_answers.emf_id", "=", $rform->id)
										->where("efa_answer", "=", $opsi_pilihan[0])
										->get();
									if (count($quota_form) < $opsi_pilihan[1]) {
										$sform = 1;
									} else {
										$sform = 0;
									}
								} else {
									$sform = 1;
								}

								if ($sform == 1) {
									$opsi_jawaban[] = $opsi_pilihan[0];
								}
							}
						} else {
							$opsi_jawaban = $emf_option;
						}

						$array_form[] = [
							'form_id' => $rform->id,
							'form_title' => $rform->emf_title,
							'form_type' => $rform->emf_type,
							//'form_option' => $emf_option,
							'form_option' => $opsi_jawaban,
							'form_required' => $rform->emf_required,
						];
						$array_form_sub = $array_form;
					}

					//$array_form = $array_form_sub;
				}

				if ($row->et_type == "online") {
					$link_webinar = $row->et_link_online;
				} else {
					$link_webinar = "";
				}

				$cust_terdaftar = DB::table("event_orders")
					->where("u_id", '=', $request->cust_id)
					->where("payment_status", '=', '1')
					->where("et_id", '=', $row->pid)
					->first();
				if ($cust_terdaftar) {
					$cust_terdaftar = 1;
				} else {
					$cust_terdaftar = 0;
				}

				// Cek ULASAN USER 1 data terbaru... *********************
				$arr_ratting = DB::table("ratting_apps")
					->selectRaw('ts_ratting_apps.id as pid, ra_value, ra_description, ra_file, ts_ratting_apps.created_at, cust_name, cust_email, external_id, eo_name, eo_email, eo_phone, eo_price, eo_qty, eo_total, et_title, et_banner, et_price, eo_invoice, ts_event_tops.id as product_id, cust_reseller')
					->leftJoin('event_orders', 'event_orders.id', '=', 'ratting_apps.lookup_id')
					->leftJoin('event_tops', 'event_tops.id', '=', 'event_orders.et_id')
					->leftJoin('customers', 'customers.id', '=', 'ratting_apps.cust_id')
					->where('ra_type', '=', 'event')
					->orderBy('ratting_apps.created_at', 'DESC')
					->first();
				$result_ratting = array();
				$image = array();
				if ($arr_ratting) {
					if (1 == 1) {
						if (!empty($arr_ratting->ra_file)) {
							$banner = url('public/ulasan_file') . '/' . $arr_ratting->ra_file;
						} else {
							$banner = '';
						}
						if ($arr_ratting->cust_email != '') {
							$cust_email = $arr_ratting->cust_email;
						} else {
							$cust_email = '';
						}

						$image_url = "https://www.topsystem.id/upload/event/";
						//$image[] = $arr_ratting->et_banner;
						$image[] = (isset($arr_ratting->et_banner) ? $arr_ratting->et_banner : '');

						$result_ratting = [
							'ulasan_id' => $arr_ratting->pid,
							'ratting' => $arr_ratting->ra_value,
							'description' => $arr_ratting->ra_description,
							'cust_name' => (isset($arr_ratting->eo_name) ? $arr_ratting->eo_name : ''),
							'cust_email' => (isset($arr_ratting->eo_email) ? $arr_ratting->eo_email : ''),
							'external_id' => (isset($arr_ratting->external_id) ? $arr_ratting->external_id : ''),
							'pos_invoice' => (isset($arr_ratting->eo_invoice) ? $arr_ratting->eo_invoice : ''),
							'banner' => $banner,
							'product_name' => (isset($arr_ratting->et_title) ? $arr_ratting->et_title : ''),
							'product_id' => (isset($arr_ratting->product_id) ? $arr_ratting->product_id : 0),
							'image_url' => $image_url,
							'image' => $image,
							'created_at' => $arr_ratting->created_at,
							'cust_reseller' => $arr_ratting->cust_reseller,
						];

					}
				}
				// END ULASAN USER

				// https://www.google.com/maps/search/?api=1&query=28.6139,77.2090
				if ($row->et_maps_url) {
					$et_maps_url = $row->et_maps_url;
				} else {
					$et_maps_url = "https://www.google.com/maps/search/?api=1&query=" . $row->et_lat . ',' . $row->et_lang;
				}


				$arr_customer = DB::table("customers")->where("id", "=", $request->cust_id)->first();
				// cek user tedaftar 
				$result_registration = array();
				//if(!empty($id_event) && ($cust_terdaftar==1 || $arr_customer->cust_role=='admin')){
				//if(!empty($id_event) && ($cust_terdaftar==1)){
				if (1 == 1) {
					$user_registration = DB::table("event_orders")
						->select('event_orders.id as id', 'eo_name', 'eo_phone', 'eo_email', 'cust_avatar', 'cust_gender', 'cust_reseller')
						->leftJoin('customers', 'customers.id', '=', 'event_orders.u_id')
						->where('payment_status', '=', '1')
						//->where('et_id', '=', $id_event)
						->where('et_id', '=', $row->pid)
						->orderBy('event_orders.eo_name', 'ASC')
						->get();
					if ($user_registration) {
						foreach ($user_registration as $reg_event) {
							if (empty($reg_event->cust_avatar)) {
								if ($reg_event->cust_gender == 'm') {
									$avatar_profile = "https://www.topsystem.id/upload/avatar/AV1.png";
								} else {
									$avatar_profile = "https://www.topsystem.id/upload/avatar/AV51.png";
								}
							} else {
								$avatar_profile = $reg_event->cust_avatar;
							}

							$result_registration[] = [
								'reg_order_id' => $reg_event->id,
								'reg_order_name' => ucwords(strtolower($reg_event->eo_name)),
								'reg_order_phone' => $reg_event->eo_phone,
								'reg_order_email' => $reg_event->eo_email,
								'reg_order_avatar' => $avatar_profile,
								'cust_reseller' => $reg_event->cust_reseller,
							];
						}
					}

				}

				// cek jika event coming soon
				$event_coming = '0';
				$start_date_check = $row->et_start_date . ' ' . $row->et_start_time;
				//$end_date_check = date("Y-m-d H:i:s", strtotime('-14 days', strtotime($start_date_check)));
				$end_date_check = date("Y-m-d H:i:s", strtotime('-90 days', strtotime($start_date_check)));
				if ($end_date_check <= date("Y-m-d H:i:s")) {
					$event_coming = 0;
				} else {
					$event_coming = 1;
				}

				// cek team event
				$result_team = array();
				//if(!empty($id_event)){
				if (1 == 1) {
					$event_team = DB::table("event_teams")
						->select('event_teams.id', 'eteam_name')
						->where('et_id', '=', $id_event)
						->where('eteam_delete', '=', '0')
						->orderBy('event_teams.eteam_name', 'ASC')
						->get();
					if ($event_team) {
						foreach ($event_team as $row_team) {
							$result_team[] = [
								'team_id' => $row_team->id,
								'team_name' => $row_team->eteam_name,
							];
						}
					}

				}

				// cek ticket event
				$price_ticket = array();
				$result_ticket = array();
				$event_ticket = DB::table("event_tickets")
					->select('event_tickets.id', 'eticket_name', 'eticket_price', 'eticket_quota', 'eticket_description', 'eticket_start_date', 'eticket_end_date', 'eticket_start_time', 'eticket_end_time', 'eticket_team', 'eticket_active')
					//->where('et_id', '=', $id_event)
					->where('et_id', '=', $row->pid)
					->where('eticket_active', '=', '1')
					->where('eticket_delete', '=', '0')
					->orderBy('event_tickets.eticket_team', 'DESC')
					->orderBy('event_tickets.eticket_name', 'ASC')
					->get();
				if ($event_ticket) {
					foreach ($event_ticket as $row_ticket) {
						// cek yang sudah order event ticket dan sudah bayar
						$ticket_user_order = DB::table("event_orders")
							->select('event_orders.id')
							//->where('event_orders.et_id', '=', $id_event)
							->where('event_orders.et_id', '=', $row->pid)
							->where('payment_status', '=', '1')
							->get();
						$result_ticket[] = [
							'ticket_id' => $row_ticket->id,
							'ticket_name' => $row_ticket->eticket_name,
							'ticket_price' => $row_ticket->eticket_price,
							'ticket_quota' => $row_ticket->eticket_quota,
							'ticket_description' => $row_ticket->eticket_description,
							'ticket_start' => $row_ticket->eticket_start_date . ' ' . $row_ticket->eticket_start_time,
							'ticket_end' => $row_ticket->eticket_end_date . ' ' . $row_ticket->eticket_end_time,
							'ticket_team' => $row_ticket->eticket_team,
							'ticket_user_order' => count($ticket_user_order),
						];
						$price_ticket[] = $row_ticket->eticket_price;
					}
				}

				// cek min dan max price
				if (count($price_ticket) > 1) {
					$price_range = 'Rp ' . number_format(min($price_ticket), 0, ',', '.') . ' - ' . 'Rp ' . number_format(max($price_ticket), 0, ',', '.');
				} else {
					$price_range = 'Rp ' . number_format($row->et_price, 0, ',', '.');
				}

				if ($row->evt_icon) {
					$evt_icon = $this->url_server . 'upload/event/' . $row->evt_icon;
				} else {
					$evt_icon = '';
				}

				if ($row->et_end_date . ' ' . $row->et_end_time < date("Y-m-d H:i:s")) {
					$status_event_end = 'selesai';
				} else {
					$status_event_end = '';
				}

				if ($row->et_category == "eksekutif" && $id_event) {
					$results[] = [
						'event_id' => $row->pid,
						//'evt_category' => $row->evt_category, 
						'event_category' => $row->evt_name,
						'event_type' => $row->et_type,
						'event_title' => $row->et_title,
						'event_start_date' => $row->et_start_date,
						'event_start_time' => $row->et_start_time,
						'event_end_date' => $row->et_end_date,
						'event_end_time' => $row->et_end_time,
						'event_address' => $row->et_address,
						'event_banner' => 'https://topsystem.id/upload/event/' . $row->et_banner,
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
						//'event_webinar' => $link_webinar,
						'additional_form' => $array_form_sub,
						'cust_terdaftar' => $cust_terdaftar,
						'ratting' => $result_ratting,
						'registrasi_user' => $result_registration,
						'event_coming_soon' => 0,
						'event_open_reg' => $end_date_check,
						'event_tournament' => $row->et_tournament,
						'event_team' => $result_team,
						'event_ticket' => $result_ticket,
						'event_price_detail' => $price_ticket,
						'event_price_text' => $price_range,
						'event_icon_category' => $evt_icon,
						'event_blurhash' => $row->et_blurhash ?? '',
						'event_venue' => $row->et_venue ?? '',
						'event_status' => $status_event_end,
					];
				} else if ($row->et_category != "eksekutif") {

					$results[] = [
						'event_id' => $row->pid,
						//'evt_category' => $row->evt_category, 
						'event_category' => $row->evt_name,
						'event_type' => $row->et_type,
						'event_title' => $row->et_title,
						'event_start_date' => $row->et_start_date,
						'event_start_time' => $row->et_start_time,
						'event_end_date' => $row->et_end_date,
						'event_end_time' => $row->et_end_time,
						'event_address' => $row->et_address,
						'event_banner' => 'https://topsystem.id/upload/event/' . $row->et_banner,
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
						//'event_webinar' => $link_webinar,
						'additional_form' => $array_form_sub,
						'cust_terdaftar' => $cust_terdaftar,
						'ratting' => $result_ratting,
						'registrasi_user' => $result_registration,
						'event_coming_soon' => $event_coming,
						'event_open_reg' => $end_date_check,
						'event_tournament' => $row->et_tournament,
						'event_team' => $result_team,
						'event_ticket' => $result_ticket,
						'event_price_detail' => $price_ticket,
						'event_price_text' => $price_range,
						'event_icon_category' => $evt_icon,
						'event_blurhash' => $row->et_blurhash ?? '',
						'event_venue' => $row->et_venue ?? '',
						'event_status' => $status_event_end,
					];
				}
			}


			// tampilkan kategori event
			$category = DB::table("event_types")
				->select('id as evt_id', 'evt_category', 'evt_name', 'evt_icon')->where(['evt_delete' => '0', 'evt_show' => '1'])
				->orderBy("evt_sort", "ASC")->get();
			$cities = DB::table("event_cities")
				->select('id as evc_id', 'evc_name')->where(['evc_delete' => '0'])
				->orderBy("evc_sort", "ASC")->get();

			$data_category = array();
			$nc = 0;
			foreach ($category as $cats) {
				$nc++;
				if ($nc == 1) {
					$data_category[] = [
						"evt_id" => 0,
						"evt_category" => "semua",
						"evt_name" => "Semua",
					];
				}

				if ($cats->evt_icon) {
					$evt_icon = $this->url_server . 'upload/event/' . $cats->evt_icon;
				} else {
					$evt_icon = '';
				}

				$data_category[] = [
					"evt_id" => $cats->evt_id,
					"evt_category" => $cats->evt_category,
					"evt_name" => $cats->evt_name,
					"evt_icon" => $evt_icon,
				];
			}

			$data_cities = array();
			$nk = 0;
			foreach ($cities as $city) {
				$nk++;
				if ($nk == 1) {
					$data_cities[] = [
						"evc_id" => 0,
						"evc_name" => "Semua",
					];
				}
				$data_cities[] = [
					"evc_id" => $city->evc_id,
					"evc_name" => $city->evc_name,
				];
			}

			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success'
				],
				/*
				'info' => [
					'title' => $event_home->eh_title,
					'image' => 'https://topsystem.id/public/upload/'.$event_home->eh_image,
					'description' => $event_home->eh_description,
				],
				*/
				//'category' => $category,
				//'city' => $cities,
				'category' => $data_category,
				'city' => $data_cities,
				'event' => $results
			];
		} else {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data not found'
				],
				'info' => [
					'title' => $event_home->eh_title,
					'image' => 'https://topsystem.id/public/upload/' . $event_home->eh_image,
					'description' => $event_home->eh_description,
				],
			];
		}
		return response()->json($data);
	}

	private function generateInvoice()
	{
		$invoice = date('YmdHis') . rand(1000, 9999);
		if ($this->InvoiceExists($invoice)) {
			return generateInvoice();
		}
		return $invoice;
	}

	private function InvoiceExists($number)
	{
		return DB::table('event_orders')->where(['external_id' => $number])->exists();
	}

	// SAVE PAYMENT ORDERS
	public function createOrders(Request $request)
	{
		error_reporting(0);
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

		$check_events = DB::table('event_tops')->select('*')
			->where('id', '=', $request->event_id)
			->where('et_delete', '=', '0')
			->get()->first();
		if (empty($check_events)) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data tidak ditemukan'
				]
			];
			return response()->json($data);
		}
		//if(!empty($check_events) && ($check_events->et_end_date.' '.$check_events->et_end_time>date("Y-m-d H:i:s"))){
		if ($check_events->et_end_date . ' ' . $check_events->et_end_time < date("Y-m-d H:i:s")) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data event sudah tidak aktif',
				]
			];
			return response()->json($data);
		}

		// cek external ID
		/*
		$external_id = $request->external_id;
		$arr_eo = $this->checkData('event_orders','external_id', ['external_id' => $external_id]);
		if(!empty($arr_eo)){
		$external_id = $this->generateInvoice();
		}
		*/
		$external_id = $this->generateInvoice();
		$amount = $request->amount;
		$nama_user = $request->nama_user;
		$bank_code = $request->bank_code;
		$phone = $request->phone;
		$bank_options = NULL;

		//$tgl_indo_expired = date('Y-m-d H:i:s', strtotime(date("Y-m-d H:i:s"). ' -4 hours')); // untuk +3 jam
		$tgl_indo_expired = date('Y-m-d H:i:s', strtotime(date("Y-m-d H:i:s") . ' -6 hours'));  // untuk +1 jam
		$curl = curl_init();

		$headers = array();
		$headers[] = 'Content-Type: application/json';

		$disbursement_options = [];
		if (!empty($disbursement_options['X-IDEMPOTENCY-KEY'])) {
			array_push($headers, 'X-IDEMPOTENCY-KEY: ' . $disbursement_options['X-IDEMPOTENCY-KEY']);
		}

		/*
		if($request->bank_code !='QRIS'){
			$end_point = $this->server_domain.'/callback_virtual_accounts';

			$data['external_id'] = $external_id;
			$data['name'] = $nama_user;
			$data['bank_code'] = $bank_code;
			$data['expected_amount'] = (int)$amount;
			//$data['expiration_date'] = date('Y-m-d', strtotime(date("Y-m-d"). ' + 1 days'))." 23:59:59"; 
			$data['expiration_date'] = date('Y-m-d H:i:s', strtotime(date("Y-m-d H:i:s"). ' -4 hours')); 
			$data['is_closed'] = true;

			if ( is_array($bank_options) ) {
				foreach ( $bank_options as $key => $value ) {
					$data[$key] = $value;
				}
			}			
		} 
		// jika Payment mengunakan QRIS
		else {
			$end_point = $this->server_domain.'/qr_codes';

			$data['external_id'] = $external_id;
			$data['amount'] = (int)$amount;
			$data['type'] = 'DYNAMIC';
			$data['callback_url'] = 'https://production.topscore.id/api/callback_xd';
		}
		*/

		// jika event free ************************************* //
		$data_attendance = array();
		if ($amount <= 0) {
			$row_event = DB::table("event_tops")->where("id", "=", $request->event_id)->first();
			// insert order event
			$data_event = [
				'et_id' => $request->event_id,
				'u_id' => $request->user_id,
				'eo_name' => $request->nama_user,
				'eo_phone' => $request->phone,
				'eo_email' => $request->email,
				'eo_address' => $request->address,
				'eo_invoice' => $external_id,
				'eo_qty' => $request->qty,
				'eo_price' => $request->price,
				'eo_total' => $request->amount,
				//'respon_pg' => $json_view,
				'external_id' => $external_id,
				//'va_number' => $nomor_va,
				//'channel_pg' => $bank_code,
				'eo_others' => $request->other_user,
				'eo_form' => $request->additional_form,
				'created_at' => date("Y-m-d H:i:s"),
				'payment_status' => 1,
				'channel_pg' => "FREE",
				'eteam_id' => $request->team_id,
				'eo_ticket_type' => $request->type_user,
				'eo_position' => $request->posisi_user,
				'eticket_id' => $request->ticket_id,
			];

			if ($row_event->et_category == "eksekutif") {
				/*
				$data_attendance = [
					'eo_scan_count' => 1,
					'eo_scan_date' => date("Y-m-d H:i:s"),
				];
				*/
				$data_attendance = [
					'eo_scan_count' => 0,
					'eo_scan_date' => NULL,
				];
			}
			$get_idsave = DB::table("event_orders")->insertGetId(array_merge($data_event, $data_attendance));

			// save additional form answers ************************************
			$master_form = $request->additional_form;
			$master_form = json_decode($master_form);
			// save answer form
			$no_simpan = 0;
			foreach ($master_form as $key => $lookup) {
				if (!empty($request->event_id)) {
					$data_addform = [
						'et_id' => $request->event_id,
						'eo_id' => $get_idsave,
						'emf_id' => $lookup->form_id,
						'efa_answer' => $lookup->form_answer,
						'cust_id' => $request->user_id,
						'created_at' => date("Y-m-d H:i:s"),
					];
					$save_forms = DB::table("event_form_answers")->insertGetId(array_merge($data_addform));
				}
			}

			// SAVE LOG NOTIF *********************** // ********************************************
			$an_description = 'Pesanan event ' . $external_id . ' telah berhasil di order';
			$save_notif = DB::table('apps_notif')->insertGetId([
				'id_lookup' => $get_idsave,
				'cust_id' => $request->user_id,
				'type_data' => 'event',
				'an_title' => 'Pesanan berhasil',
				'an_description' => $an_description,
				'created_at' => date('Y-m-d H:i:s'),
			]);

			$data = [
				'id' => "$get_idsave",
				'owner_id' => "",
				'external_id' => $external_id,
				'account_number' => "",
				'bank_code' => "FREE",
				'amount' => (int) $amount,
				'qr_string' => "",
				'callback_url' => "",
				'description' => "",
				'type' => "",
				'merchant_code' => "",
				'name' => $request->nama_user,
				'is_closed' => true,
				'expected_amount' => (int) $amount,
				'expiration_date' => date("Y-m-d H:i:s"),
				'is_single_use' => true,
				'status' => "PAID",
				'currency' => "IDR",
				'country' => "INDONESIA",
				'actions' => null,
				'img' => "",
				'pt' => 'PT. Mencetak Banyak Gol (Topscore)',
				'banner' => url('public/upload/payment_product.png'),
				'event' => [
					'event_id' => $row_event->id,
					'event_title' => $row_event->et_title,
					'event_price' => (int) $row_event->et_price,
					'event_banner' => 'https://topsystem.id/upload/event/' . $row_event->et_banner,
				],
				//'payment_instruction' => [],
				'payment_instruction' => NULL,
			];
			return response()->json($data); // tampilkan respon data...
		}


		if (in_array($request->bank_code, array("BCA", "BNI", "BRI", "MANDIRI", "PERMATA", "BSI"))) {
			$end_point = $this->server_domain . '/callback_virtual_accounts';

			$data['external_id'] = $external_id;
			$data['name'] = $nama_user;
			$data['bank_code'] = $bank_code;
			$data['expected_amount'] = (int) $amount;
			//$data['expiration_date'] = date('Y-m-d', strtotime(date("Y-m-d"). ' + 1 days'))." 23:59:59"; 
			//$data['expiration_date'] = date('Y-m-d H:i:s', strtotime(date("Y-m-d H:i:s"). ' -4 hours'));  // untuk +3 jam
			$data['expiration_date'] = date('Y-m-d H:i:s', strtotime(date("Y-m-d H:i:s") . ' -6 hours'));  // untuk +1 jam
			$data['is_closed'] = true;

			if (is_array($bank_options)) {
				foreach ($bank_options as $key => $value) {
					$data[$key] = $value;
				}
			}
		} else
			if (in_array($request->bank_code, array("ID_OVO", "ID_SHOPEEPAY", "ID_DANA", "ID_LINKAJA"))) {
				$end_point = $this->server_domain . '/ewallets/charges';

				$phone = str_replace('+', '', $phone);
				$check = substr($phone, 0, 1);
				if ($check == '0' || $check == 0) {
					$phone = preg_replace('/^0/', '62', $phone);
				}
				$data['reference_id'] = $external_id;
				$data['currency'] = "IDR";
				$data['amount'] = (int) $amount;
				$data['checkout_method'] = "ONE_TIME_PAYMENT";
				$data['channel_code'] = $bank_code;
				$data['channel_properties'] = array(
					"success_redirect_url" => 'https://production.topscore.id/api/callback_xd_ewallet',
					"mobile_number" => $phone
				);
			}
			// jika Payment mengunakan QRIS
			else {
				$end_point = $this->server_domain . '/qr_codes';

				$data['external_id'] = $external_id;
				$data['amount'] = (int) $amount;
				$data['type'] = 'DYNAMIC';
				$data['callback_url'] = 'https://production.topscore.id/api/callback_xd';
			}

		$payload = json_encode($data);

		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_USERPWD, $this->secret_api_key . ":");
		curl_setopt($curl, CURLOPT_URL, $end_point);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_CAINFO, dirname(__FILE__) . "/data/cacert.pem");

		$response = curl_exec($curl);
		curl_close($curl);

		$responseObject = json_decode($response, true);
		//return $responseObject;
		//return response()->json($responseObject);
		$json_view = json_encode($responseObject);
		$json_parsing = json_decode($json_view);

		$instruksi_payment = $this->instruksiPayment($bank_code);
		$results = array();
		if (!empty($instruksi_payment)) {
			foreach ($instruksi_payment as $row) {
				$results[] = [
					'metode' => $row->metod_instruction_payget,
					'instruction' => $row->instruction_payget,
				];
			}
		}

		//echo $json_view; exit();
		if ($json_parsing->error_code != '') {
			$data = [
				'status' => 400,
				'description' => 'Gagal mendapatkan data pembayaran, harap pastikan kembali param yang anda kirim sudah benar.',
			];
			return response()->json($data);
		}

		// tampilkan master data event **************
		$row_event = DB::table("event_tops")->where("id", "=", $request->event_id)->first();

		/*
		if($request->bank_code =='QRIS'){
			$nomor_va = $json_parsing->qr_string;
			$bank_code = 'QRIS';
		} else {
			$nomor_va = $json_parsing->account_number;
			$bank_code = $json_parsing->bank_code;
		}
		*/
		if ($request->bank_code == 'QRIS') {
			$nomor_va = $json_parsing->qr_string;
			$bank_code = 'QRIS';
		} else
			if (in_array($request->bank_code, array("ID_OVO", "ID_SHOPEEPAY", "ID_DANA", "ID_LINKAJA"))) {
				$nomor_va = $external_id;
				$bank_code = $request->bank_code;
			} else {
				$nomor_va = $json_parsing->account_number;
				$bank_code = $json_parsing->bank_code;
			}
		// insert order event
		$data_event = [
			'et_id' => $request->event_id,
			'u_id' => $request->user_id,
			'eo_name' => $request->nama_user,
			'eo_phone' => $request->phone,
			'eo_email' => $request->email,
			'eo_address' => $request->address,
			'eo_invoice' => $external_id,
			'eo_qty' => $request->qty,
			'eo_price' => $request->price,
			'eo_total' => $request->amount,
			'respon_pg' => $json_view,
			'external_id' => $external_id,
			'va_number' => $nomor_va,
			'channel_pg' => $bank_code,
			'eo_others' => $request->other_user,
			'eo_form' => $request->additional_form,
			'eteam_id' => $request->team_id,
			'eo_ticket_type' => $request->type_user,
			'eo_position' => $request->posisi_user,
			'eticket_id' => $request->ticket_id,
			'created_at' => date("Y-m-d H:i:s"),
		];
		$get_idsave = DB::table("event_orders")->insertGetId(array_merge($data_event));


		// save additional form answers ************************************
		$master_form = $request->additional_form;
		$master_form = json_decode($master_form);
		// save answer form
		$no_simpan = 0;
		foreach ($master_form as $key => $lookup) {
			if (!empty($request->event_id)) {
				$data_addform = [
					'et_id' => $request->event_id,
					'eo_id' => $get_idsave,
					'emf_id' => $lookup->form_id,
					'efa_answer' => $lookup->form_answer,
					'cust_id' => $request->user_id,
					'created_at' => date("Y-m-d H:i:s"),
				];
				$save_forms = DB::table("event_form_answers")->insertGetId(array_merge($data_addform));
			}
		}


		// SAVE LOG NOTIF *********************** // ********************************************
		$an_description = 'Pesanan event ' . $external_id . ' telah berhasil di order. Segera lakukan Pembayaran agar tidak otomatis terbatalkan pesanan anda.';
		$save_notif = DB::table('apps_notif')->insertGetId([
			'id_lookup' => $get_idsave,
			'cust_id' => $request->user_id,
			'type_data' => 'event',
			'an_title' => 'Pesanan berhasil',
			'an_description' => $an_description,
			'created_at' => date('Y-m-d H:i:s'),
		]);

		// Save to PG Master ******************************************************* //
		/*
		$data_pg = [
			'id_lookup' => $get_idsave,
			'type_data' => 'event',
			'external_id' => $external_id,
			'account_number' => $nomor_va,
			'bank_code' => $bank_code,
			'merchant_code' => $json_parsing->merchant_code,
			'name' => $json_parsing->name,
			'is_closed' => $json_parsing->is_closed,
			'expected_amount' => $json_parsing->expected_amount,
			'expiration_date' => $json_parsing->expiration_date,
			'status' => $json_parsing->status,
			'currency' => $json_parsing->currency,
			'created_at' => date("Y-m-d H:i:s"),
		];
		$get_pgmaster = DB::table("payment_xendit")->insertGetId(array_merge($data_pg));
		*/
		/* =========================================================================== */

		$cek_channels = DB::table('pg_metod_payget')->select('id', 'bank_code', 'img')
			->where('status', '=', '1')
			->where('bank_code', '=', $request->bank_code)->first();
		if (!empty($cek_channels)) {
			$img_channel = url('public/pg') . '/' . str_replace("svg", "png", $cek_channels->img);
		} else {
			$img_channel = '';
		}

		// OUTPUT JSON SAVE DATA...
		if ($request->bank_code == 'QRIS') {
			// expired date
			$tgl_expired_payment = date('Y-m-d H:i:s', strtotime(date("Y-m-d H:i:s") . ' +1 hours'));
			$data = [
				'id' => $json_parsing->id,
				/*
				'external_id' => $json_parsing->external_id,
				'amount' => $json_parsing->amount,
				'qr_string' => $json_parsing->qr_string,
				'callback_url' => $json_parsing->callback_url,
				'description' => $json_parsing->description,
				'type' => $json_parsing->type,
				'status' => $json_parsing->status,
				'created' => $json_parsing->created,
				'updated' => $json_parsing->updated,
				'metadata' => $json_parsing->metadata,
				*/
				'owner_id' => "",
				'external_id' => $json_parsing->external_id,
				'account_number' => $json_parsing->external_id,
				'bank_code' => $request->bank_code,
				'amount' => $json_parsing->amount,
				'qr_string' => $json_parsing->qr_string,
				'callback_url' => $json_parsing->callback_url,
				'description' => $json_parsing->description,
				'type' => $json_parsing->type,
				'merchant_code' => "",
				'name' => $request->nama_user,
				'is_closed' => true,
				'expected_amount' => $json_parsing->amount,
				//'expiration_date' => $tgl_indo_expired,
				'expiration_date' => $tgl_expired_payment,
				'is_single_use' => true,
				'status' => $json_parsing->status,
				'currency' => "IDR",
				'country' => "ID",
				'actions' => null,

				'img' => $img_channel,
				'pt' => 'PT. Mencetak Banyak Gol (Topscore)',
				'banner' => url('public/upload/payment_product.png'),
				'event' => [
					'event_id' => $row_event->id,
					'event_title' => $row_event->et_title,
					'event_price' => $row_event->et_price,
					'event_banner' => 'https://topsystem.id/upload/event/' . $row_event->et_banner,
					//'event_banner' => 'https://devtop.topsystem.id/upload/event/'.$row_event->et_banner,
				],
				'payment_instruction' => $results
			];
			// Save to PG Master ******************************************************* //
			$data_pg = [
				'id_lookup' => $get_idsave,
				'type_data' => 'event',
				'external_id' => $external_id,
				'account_number' => $nomor_va,
				'bank_code' => $bank_code,
				//'merchant_code' => $json_parsing->merchant_code,
				'name' => $nama_user,
				//'is_closed' => $json_parsing->is_closed,
				'expected_amount' => $json_parsing->amount,
				//'expiration_date' => $json_parsing->expiration_date,
				'status' => $json_parsing->status,
				//'currency' => $json_parsing->currency,
				'created_at' => date("Y-m-d H:i:s"),
			];
			$get_pgmaster = DB::table("payment_xendit")->insertGetId(array_merge($data_pg));
		} else
			// menggunakan E-Wallet
			if (in_array($request->bank_code, array("ID_OVO", "ID_SHOPEEPAY", "ID_DANA", "ID_LINKAJA"))) {
				// expired date
				if ($bank_code == "ID_OVO") {
					$tgl_expired_payment = date('Y-m-d H:i:s', strtotime(date("Y-m-d H:i:s") . ' +1 minutes'));
				} else {
					$tgl_expired_payment = date('Y-m-d H:i:s', strtotime(date("Y-m-d H:i:s") . ' +1 hours'));
				}
				$data = [
					'id' => $json_parsing->id,
					'owner_id' => "",
					'external_id' => $json_parsing->reference_id,
					'account_number' => $json_parsing->reference_id,
					'bank_code' => $request->bank_code,
					'amount' => $json_parsing->charge_amount,
					'qr_string' => "",
					'callback_url' => $json_parsing->callback_url,
					'description' => "",
					'type' => "",
					'merchant_code' => "",
					'name' => $request->nama_user,
					'is_closed' => true,
					'expected_amount' => $json_parsing->charge_amount,
					//'expiration_date' => $tgl_indo_expired,
					'expiration_date' => $tgl_expired_payment,
					'is_single_use' => true,
					'status' => $json_parsing->status,
					'currency' => "IDR",
					'country' => "ID",
					'actions' => $json_parsing->actions,

					'img' => $img_channel,
					'pt' => 'PT. Mencetak Banyak Gol (Topscore)',
					'banner' => url('public/upload/payment_product.png'),
					'event' => [
						'event_id' => $row_event->id,
						'event_title' => $row_event->et_title,
						'event_price' => $row_event->et_price,
						'event_banner' => 'https://topsystem.id/upload/event/' . $row_event->et_banner,
						//'event_banner' => 'https://devtop.topsystem.id/upload/event/'.$row_event->et_banner,
					],
					'payment_instruction' => $results
				];
				// Save to PG Master ******************************************************* //
				$data_pg = [
					'id_lookup' => $get_idsave,
					'type_data' => 'event',
					'external_id' => $external_id,
					'account_number' => $nomor_va,
					'bank_code' => $bank_code,
					'name' => $nama_user,
					'expected_amount' => $json_parsing->charge_amount,
					'status' => $json_parsing->status,
					'created_at' => date("Y-m-d H:i:s"),
				];
				$get_pgmaster = DB::table("payment_xendit")->insertGetId(array_merge($data_pg));
			}
			// Jika Payment menggunakan VA
			else {
				$data = [
					'id' => $json_parsing->id,
					/*
					'owner_id' => $json_parsing->owner_id,
					'external_id' => $json_parsing->external_id,
					'account_number' => $json_parsing->account_number,
					'bank_code' => $json_parsing->bank_code,
					'merchant_code' => $json_parsing->merchant_code,
					'name' => $json_parsing->name,
					'is_closed' => $json_parsing->is_closed,
					'expected_amount' => $json_parsing->expected_amount,
					'expiration_date' => $json_parsing->expiration_date,
					'is_single_use' => $json_parsing->is_single_use,
					'status' => $json_parsing->status,
					'currency' => $json_parsing->currency,
					'country' => $json_parsing->country,
					*/
					'owner_id' => $json_parsing->owner_id,
					'external_id' => $json_parsing->external_id,
					'account_number' => $json_parsing->account_number,
					'bank_code' => $request->bank_code,
					'amount' => $json_parsing->expected_amount,
					'qr_string' => "",
					'callback_url' => "",
					'description' => "",
					'type' => "",
					'merchant_code' => $json_parsing->merchant_code,
					'name' => $json_parsing->name,
					'is_closed' => $json_parsing->is_closed,
					'expected_amount' => $json_parsing->expected_amount,
					'expiration_date' => $json_parsing->expiration_date,
					'is_single_use' => $json_parsing->is_single_use,
					'status' => $json_parsing->status,
					'currency' => $json_parsing->currency,
					'country' => $json_parsing->country,
					'actions' => null,

					'img' => $img_channel,
					'pt' => 'PT. Mencetak Banyak Gol (Topscore)',
					'banner' => url('public/upload/payment_product.png'),
					'event' => [
						'event_id' => $row_event->id,
						'event_title' => $row_event->et_title,
						'event_price' => $row_event->et_price,
						'event_banner' => 'https://topsystem.id/upload/event/' . $row_event->et_banner,
						//'event_banner' => 'https://devtop.topsystem.id/upload/event/'.$row_event->et_banner,
					],
					'payment_instruction' => $results
				];
				// Save to PG Master ******************************************************* //
				$data_pg = [
					'id_lookup' => $get_idsave,
					'type_data' => 'event',
					'external_id' => $external_id,
					'account_number' => $nomor_va,
					'bank_code' => $bank_code,
					'merchant_code' => $json_parsing->merchant_code,
					'name' => $json_parsing->name,
					'is_closed' => $json_parsing->is_closed,
					'expected_amount' => $json_parsing->expected_amount,
					'expiration_date' => $json_parsing->expiration_date,
					'status' => $json_parsing->status,
					'currency' => $json_parsing->currency,
					'created_at' => date("Y-m-d H:i:s"),
				];
				$get_pgmaster = DB::table("payment_xendit")->insertGetId(array_merge($data_pg));
			}

		return response()->json($data);
	}

	public function createOrders_old(Request $request)
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

		// cek external ID
		/*
		$external_id = $request->external_id;
		$arr_eo = $this->checkData('event_orders','external_id', ['external_id' => $external_id]);
		if(!empty($arr_eo)){
		$external_id = $this->generateInvoice();
		}
		*/
		$external_id = $this->generateInvoice();
		$amount = $request->amount;
		$nama_user = $request->nama_user;
		$bank_code = $request->bank_code;
		$bank_options = NULL;

		$curl = curl_init();

		$headers = array();
		$headers[] = 'Content-Type: application/json';

		$disbursement_options = [];
		if (!empty($disbursement_options['X-IDEMPOTENCY-KEY'])) {
			array_push($headers, 'X-IDEMPOTENCY-KEY: ' . $disbursement_options['X-IDEMPOTENCY-KEY']);
		}

		$end_point = $this->server_domain . '/callback_virtual_accounts';

		$data['external_id'] = $external_id;
		$data['name'] = $nama_user;
		$data['bank_code'] = $bank_code;
		$data['expected_amount'] = (int) $amount;
		$data['expiration_date'] = date('Y-m-d', strtotime(date("Y-m-d") . ' + 1 days')) . " 23:59:59";
		$data['is_closed'] = true;

		if (is_array($bank_options)) {
			foreach ($bank_options as $key => $value) {
				$data[$key] = $value;
			}
		}

		$payload = json_encode($data);

		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_USERPWD, $this->secret_api_key . ":");
		curl_setopt($curl, CURLOPT_URL, $end_point);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_CAINFO, dirname(__FILE__) . "/data/cacert.pem");

		$response = curl_exec($curl);
		curl_close($curl);

		$responseObject = json_decode($response, true);
		//return $responseObject;
		//return response()->json($responseObject);
		$json_view = json_encode($responseObject);
		$json_parsing = json_decode($json_view);

		$instruksi_payment = $this->instruksiPayment($bank_code);
		$results = array();
		if (!empty($instruksi_payment)) {
			foreach ($instruksi_payment as $row) {
				$results[] = [
					'metode' => $row->metod_instruction_payget,
					'instruction' => $row->instruction_payget,
				];
			}
		}

		// insert order event
		$data_event = [
			'et_id' => $request->event_id,
			'u_id' => $request->user_id,
			'eo_name' => $request->nama_user,
			'eo_phone' => $request->phone,
			'eo_email' => $request->email,
			'eo_address' => $request->address,
			'eo_invoice' => $external_id,
			'eo_qty' => $request->qty,
			'eo_price' => $request->price,
			'eo_total' => $request->amount,
			'respon_pg' => $json_view,
			'external_id' => $external_id,
			'va_number' => $json_parsing->account_number,
			'channel_pg' => $json_parsing->bank_code,
			'created_at' => date("Y-m-d H:i:s"),
		];
		$get_idsave = DB::table("event_orders")->insertGetId(array_merge($data_event));

		// Save to PG Master ******************************************************* //
		$data_pg = [
			'id_lookup' => $get_idsave,
			'type_data' => 'event',
			'external_id' => $external_id,
			'account_number' => $json_parsing->account_number,
			'bank_code' => $json_parsing->bank_code,
			'merchant_code' => $json_parsing->merchant_code,
			'name' => $json_parsing->name,
			'is_closed' => $json_parsing->is_closed,
			'expected_amount' => $json_parsing->expected_amount,
			'expiration_date' => $json_parsing->expiration_date,
			'status' => $json_parsing->status,
			'currency' => $json_parsing->currency,
			'created_at' => date("Y-m-d H:i:s"),
		];
		$get_pgmaster = DB::table("payment_xendit")->insertGetId(array_merge($data_pg));
		/* =========================================================================== */
		// OUTPUT JSON SAVE DATA...
		$data = [
			'id' => $json_parsing->id,
			'owner_id' => $json_parsing->owner_id,
			'external_id' => $json_parsing->external_id,
			'account_number' => $json_parsing->account_number,
			'bank_code' => $json_parsing->bank_code,
			'merchant_code' => $json_parsing->merchant_code,
			'name' => $json_parsing->name,
			'is_closed' => $json_parsing->is_closed,
			'expected_amount' => $json_parsing->expected_amount,
			'expiration_date' => $json_parsing->expiration_date,
			'is_single_use' => $json_parsing->is_single_use,
			'status' => $json_parsing->status,
			'currency' => $json_parsing->currency,
			'country' => $json_parsing->country,
			'payment_instruction' => $results
		];
		return response()->json($data);
	}

	private function instruksiPayment($kode_bank)
	{
		$query_apps = DB::table('pg_instruction_payget')->selectRaw('ts_pg_instruction_payget.id as pid, metod_instruction_payget, instruction_payget')
			->leftJoin('pg_metod_instruction_payget', 'pg_metod_instruction_payget.id', '=', 'pg_instruction_payget.id_metod_instruction_payget')
			->leftJoin('pg_metod_payget', 'pg_metod_payget.id', '=', 'pg_instruction_payget.id_metod_payget')
			->where('pg_instruction_payget.status', '=', '1')
			->where('pg_metod_instruction_payget.status', '=', '1')
			->where('pg_metod_payget.status', '=', '1')
			->where('pg_metod_payget.bank_code', '=', $kode_bank)
			->orderBy('pg_metod_instruction_payget.sort')
			->get();
		return $query_apps;
	}

	// List Pesanan Event User
	public function CekOrders(Request $request)
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

		$user_id = $request->user_id;
		$order_id = $request->order_id;
		$external_id = $request->external_id;

		$query_data = DB::table("event_orders")
			->selectRaw('ts_event_orders.id as pid, eo_name, eo_phone, eo_email, eo_address, eo_invoice, eo_qty, eo_price, eo_total, external_id, va_number, type_pg, channel_pg, ts_event_orders.payment_status, et_title, et_start_date, et_start_time, et_end_date, et_end_time, et_address, et_banner, et_price, et_terms, ts_event_orders.created_at, et_type, et_link_online, et_id, respon_pg, et_lat, et_lang, et_maps_url, eo_scan_count, eo_scan_date, eo_scan_updated, eo_scan_user, eo_scan_update, et_tournament, eteam_id, eticket_id, evt_name, et_certificate, et_platform')
			->leftJoin('event_tops', 'event_tops.id', '=', 'event_orders.et_id')
			->leftJoin('event_types', 'event_types.id', '=', 'event_tops.evt_id')
			->where('u_id', '=', $user_id)
			->where(function ($w) use ($order_id, $external_id) {
				if (!empty($order_id)) {
					$w->where('event_orders.id', '=', $order_id);
				}
				if (!empty($external_id)) {
					$w->where('event_orders.external_id', '=', $external_id);
				}

			})
			->orderBy('event_orders.created_at', 'DESC')
			->get();
		$results = array();
		if (!empty($query_data)) {
			foreach ($query_data as $row) {

				// cek team ***
				$query_team = DB::table("event_orders")
					->selectRaw('ts_event_orders.id, eteam_name, ts_event_orders.created_at, ts_event_orders.eteam_id')
					->leftJoin('event_tops', 'event_tops.id', '=', 'event_orders.et_id')
					->leftJoin('event_teams', 'event_teams.id', '=', 'event_orders.eteam_id')
					->where('event_orders.payment_status', '=', '1')
					->whereNotNull('event_orders.eteam_id')
					->where('event_orders.id', '=', $row->pid)
					->where('event_orders.eteam_id', '=', $row->eteam_id)
					->get();

				$event_team = array();
				if (!empty($query_team)) {
					foreach ($query_team as $row_sub) {
						$team_detail = array();
						$query_anggota = DB::table("event_team_details")
							->selectRaw('ts_event_team_details.id, eteam_id, etdet_name, etdet_phone, etdet_email, ts_event_team_details.created_at')
							->where('etdet_delete', '=', '0')
							->where('eteam_id', '=', $row_sub->eteam_id)
							->orderBy('etdet_name', 'ASC')
							->get();
						if ($query_anggota) {
							foreach ($query_anggota as $sub) {
								$team_detail[] = [
									'teamdet_id' => $sub->id,
									'teamdet_name' => $sub->etdet_name,
									'teamdet_phone' => $sub->etdet_phone,
									'teamdet_email' => $sub->etdet_email,
								];
							}
						}

						$event_team[] = [
							'team_id' => $row_sub->id,
							'team_name' => $row_sub->eteam_name,
							'team_detail' => $team_detail,
						];
					}
				}
				// end team

				// cek ticket
				$price_ticket = $row->et_price; // harga event
				$event_ticket = array();
				$query_ticket = DB::table("event_tickets")
					->select('event_tickets.id', 'eticket_name', 'eticket_price', 'eticket_quota', 'eticket_description', 'eticket_start_date', 'eticket_end_date', 'eticket_start_time', 'eticket_end_time', 'eticket_team', 'eticket_active')
					->where('et_id', '=', $row->et_id)
					->where('id', '=', $row->eticket_id)
					->where('eticket_active', '=', '1')
					->where('eticket_delete', '=', '0')
					->get();
				if ($query_ticket) {
					foreach ($query_ticket as $row_ticket) {
						// cek yang sudah order event ticket dan sudah bayar
						$ticket_user_order = DB::table("event_orders")
							->select('event_orders.id')
							->where('event_orders.et_id', '=', $row->et_id)
							->where('payment_status', '=', '1')
							->get();
						$event_ticket[] = [
							'ticket_id' => $row_ticket->id,
							'ticket_name' => $row_ticket->eticket_name,
							'ticket_price' => $row_ticket->eticket_price,
							'ticket_quota' => $row_ticket->eticket_quota,
							'ticket_description' => $row_ticket->eticket_description,
							'ticket_start' => $row_ticket->eticket_start_date . ' ' . $row_ticket->eticket_start_time,
							'ticket_end' => $row_ticket->eticket_end_date . ' ' . $row_ticket->eticket_end_time,
							'ticket_team' => $row_ticket->eticket_team,
							'ticket_user_order' => count($ticket_user_order),
						];
						$price_ticket = $row_ticket->eticket_price;
					}
				}
				// end ticket

				if ($row->eo_scan_count > 0) {
					$attendance = 1;
				} else {
					$attendance = 0;
				}

				if ($row->eo_scan_date) {
					$eo_scan_date = $row->eo_scan_date;
				} else {
					$eo_scan_date = "";
				}

				$json_parsing = json_decode($row->respon_pg);

				if ($row->payment_status == "0") {
					$payment_status = 'Unpaid';
				} else
					if ($row->payment_status == "1") {
						$payment_status = 'Paid';
					} else
						if ($row->payment_status == "10") {
							$payment_status = 'Expired';
						}

				// cek link sertifikat
				if ($row->payment_status == "1" && $row->et_certificate == 1) {
					$link_certificate = 'https://topscore.id/generate_certificate/' . $row->external_id;
				} else {
					$link_certificate = '';
				}

				if ($row->et_type == "online") {
					$platform_webinar = $row->et_platform;
					if ($row->et_platform == "Google Meet") {
						$platform_webinar_img = $this->url_server . 'public/upload/google_meet.png';
					} else
						if ($row->et_platform == "Zoom Meeting") {
							$platform_webinar_img = $this->url_server . 'public/upload/zoom_meet.png';
						} else
							if ($row->et_platform == "Microsoft Teams") {
								$platform_webinar_img = $this->url_server . 'public/upload/microsoft_meet.png';
							} else {
								$platform_webinar_img = "";
							}
				} else {
					$platform_webinar = "";
					$platform_webinar_img = "";
				}

				// jika menggunakan payment gateway ************** //
				if ($row->respon_pg) {

					$instruksi_payment = $this->instruksiPayment($row->channel_pg);
					$result_pay = array();
					if (!empty($instruksi_payment)) {
						foreach ($instruksi_payment as $row_pay) {
							$result_pay[] = [
								'metode' => $row_pay->metod_instruction_payget,
								'instruction' => $row_pay->instruction_payget,
							];
						}
					}
					// status event
					if ($row->et_start_date . ' ' . $row->et_start_time > date("Y-m-d H:i:s")) {
						$status_events = 'Upcomming';
					} else
						if ($row->et_start_date . ' ' . $row->et_start_time <= date("Y-m-d H:i:s") && $row->et_end_date . ' ' . $row->et_end_time >= date("Y-m-d H:i:s")) {
							$status_events = 'Berlangsung';
						} else {
							$status_events = 'Selesai';
						}

					// cek channel bank pembayaran
					$cek_channels = DB::table('pg_metod_payget')->select('id', 'bank_code', 'img')
						->where('status', '=', '1')
						->where('bank_code', '=', $row->channel_pg)->first();
					if (!empty($cek_channels)) {
						$img_channel = url('public/pg') . '/' . str_replace("svg", "png", $cek_channels->img);
					} else {
						$img_channel = '';
					}

					// cek pg payments
					$cek_pgpayment = DB::table('payment_xendit')
						->where('id_lookup', '=', $row->pid)
						->where('type_data', '=', 'event')
						->first();
					if (!empty($cek_pgpayment)) {
						$exp_date = date("Y-m-d H:i:s", strtotime($cek_pgpayment->expiration_date));
					} else {
						$exp_date = $row->created_at;
					}
					//$exp_date = $cek_pgpayment->expiration_date;

					if ($row->et_type == "online") {
						$link_webinar = $row->et_link_online;
					} else {
						$link_webinar = "";
					}

					// cek user sudah memberikan ulasan
					/*
					$cek_ulasan = DB::table("ratting_apps")->where("cust_id","=",$user_id)
								  ->where("lookup_id","=",$row->et_id)->where("ra_type","=","event")->first();
					*/
					$cek_ulasan = DB::table("ratting_apps")->where("cust_id", "=", $user_id)
						->leftJoin('event_orders', 'event_orders.id', '=', 'ratting_apps.lookup_id')
						->where("event_orders.et_id", "=", $row->et_id)
						->where("ra_type", "=", "event")
						->first();
					if ($cek_ulasan) {
						$user_ratting = 1;
					} else {
						$user_ratting = 0;
					}

					// tambahkan objek data payment
					$payment_info = "";
					if (in_array($row->channel_pg, array("ID_OVO", "ID_SHOPEEPAY", "ID_DANA", "ID_LINKAJA"))) {
						// expired date
						if ($row->channel_pg == "ID_OVO") {
							$exp_date_new = date('Y-m-d H:i:s', strtotime($row->created_at . ' +1 minutes'));
						} else {
							$exp_date_new = date('Y-m-d H:i:s', strtotime($row->created_at . ' +3 hours'));
						}
						$payment_info = [
							'channel_type' => 0,
							//'owner_id' => "",
							'external_id' => $json_parsing->reference_id,
							'account_number' => "",
							'bank_code' => $row->channel_pg,
							'amount' => (int) $json_parsing->charge_amount,
							'qr_string' => "",
							'callback_url' => "",
							'description' => "",
							'type' => "",
							'merchant_code' => "",
							'name' => $row->eo_name,
							'is_closed' => true,
							'expected_amount' => (int) $json_parsing->charge_amount,
							//'expiration_date' => $exp_date,
							'expiration_date' => $exp_date_new,
							'is_single_use' => true,
							'status' => $json_parsing->status,
							'currency' => "IDR",
							'country' => "ID",
							'actions' => $json_parsing->actions,
						];
					} else
						if (in_array($row->channel_pg, array("QRIS"))) {
							// expired date
							$exp_date_new = date('Y-m-d H:i:s', strtotime($row->created_at . ' +3 hours'));
							$payment_info = [
								'channel_type' => 2,
								//'owner_id' => $json_parsing->owner_id,
								'external_id' => $json_parsing->external_id,
								'account_number' => "",
								'bank_code' => $row->channel_pg,
								'amount' => $json_parsing->amount,
								'qr_string' => $json_parsing->qr_string,
								'callback_url' => $json_parsing->callback_url,
								'description' => $json_parsing->description,
								'type' => $json_parsing->type,
								'merchant_code' => "",
								'name' => $row->eo_name,
								'is_closed' => true,
								'expected_amount' => $json_parsing->amount,
								//'expiration_date' => $exp_date,
								'expiration_date' => $exp_date_new,
								'is_single_use' => true,
								'status' => $json_parsing->status,
								'currency' => "IDR",
								'country' => "ID",
								'actions' => null,
							];
						} else {
							$payment_info = [
								'channel_type' => 1,
								//'owner_id' => $json_parsing->owner_id,
								'external_id' => $json_parsing->external_id,
								'account_number' => $json_parsing->account_number,
								'bank_code' => $row->channel_pg,
								'amount' => $json_parsing->expected_amount,
								'qr_string' => "",
								'callback_url' => "",
								'description' => "",
								'type' => "",
								'merchant_code' => $json_parsing->merchant_code,
								'name' => $json_parsing->name,
								'is_closed' => $json_parsing->is_closed,
								'expected_amount' => $json_parsing->expected_amount,
								'expiration_date' => $exp_date,
								'is_single_use' => $json_parsing->is_single_use,
								'status' => $json_parsing->status,
								'currency' => $json_parsing->currency,
								'country' => $json_parsing->country,
								'actions' => null,
							];
						}

					// expired date
					if ($row->channel_pg == "ID_OVO") {
						$exp_date_new = date('Y-m-d H:i:s', strtotime($row->created_at . ' +1 minutes'));
					} else {
						$exp_date_new = date('Y-m-d H:i:s', strtotime($row->created_at . ' +3 hours'));
					}

					// https://www.google.com/maps/search/?api=1&query=28.6139,77.2090
					if ($row->et_maps_url) {
						$et_maps_url = $row->et_maps_url;
					} else {
						$et_maps_url = "https://www.google.com/maps/search/?api=1&query=" . $row->et_lat . ',' . $row->et_lang;
					}

					$results[] = [
						'order_id' => $row->pid,
						'customer_ratting' => $user_ratting,
						'nama_user' => $row->eo_name,
						'phone_user' => $row->eo_phone,
						'email_user' => $row->eo_email,
						'address_user' => $row->eo_address,
						'order_invoice' => $row->eo_invoice,
						'qty' => $row->eo_qty,
						'price' => $row->eo_price,
						'total_price' => $row->eo_total,
						'external_id' => $row->external_id,
						'account_number' => $row->va_number,
						'bank_code' => $row->channel_pg,
						'img' => $img_channel,
						'pt' => 'PT. Mencetak Banyak Gol (Topscore)',
						'banner' => url('public/upload/payment_product.png'),
						//'payment_status' => $row->payment_status,
						'payment_status' => $payment_status,
						'event_id' => $row->et_id,
						'event_category' => $row->evt_name,
						'event_type' => $row->et_type,
						'event_title' => $row->et_title,
						'event_start_date' => $row->et_start_date,
						'event_start_time' => $row->et_start_time,
						'event_end_date' => $row->et_end_date,
						'event_end_time' => $row->et_end_time,
						'event_status' => $status_events,
						'event_address' => $row->et_address,
						'event_tournament' => $row->et_tournament,
						'event_team' => $event_team,
						'event_ticket' => $event_ticket,
						//'event_banner' => $row->et_banner,
						'event_banner' => 'https://topsystem.id/upload/event/' . $row->et_banner,
						//'event_banner' => 'https://devtop.topsystem.id/upload/event/'.$row->et_banner,
						//'event_price' => $row->et_price,
						'event_price' => $price_ticket,
						'event_terms' => $row->et_terms,
						'event_lat' => $row->et_lat,
						'event_lang' => $row->et_lang,
						//'maps_url' => $row->et_maps_url,
						'maps_url' => $et_maps_url,
						'link_webinar' => $link_webinar,
						'platform_webinar' => $platform_webinar,
						'platform_webinar_img' => $platform_webinar_img,
						'link_certificate' => $link_certificate,
						'created_at' => $row->created_at,
						//'expiration_date' => $exp_date,
						'expiration_date' => $exp_date_new,
						'payment_info' => $payment_info,
						'payment_instruction' => $result_pay,
						'attendance_status' => $attendance,
						'attendance_date' => $eo_scan_date,
					];

				} // jika menggunakan payment gateway

				// jika event FREE *********************
				else {
					$instruksi_payment = $this->instruksiPayment($row->channel_pg);
					$result_pay = array();
					if (!empty($instruksi_payment)) {
						foreach ($instruksi_payment as $row_pay) {
							$result_pay[] = [
								'metode' => $row_pay->metod_instruction_payget,
								'instruction' => $row_pay->instruction_payget,
							];
						}
					}
					// status event
					if ($row->et_start_date . ' ' . $row->et_start_time > date("Y-m-d H:i:s")) {
						$status_events = 'Upcomming';
					} else
						if ($row->et_start_date . ' ' . $row->et_start_time <= date("Y-m-d H:i:s") && $row->et_end_date . ' ' . $row->et_end_time >= date("Y-m-d H:i:s")) {
							$status_events = 'Berlangsung';
						} else {
							$status_events = 'Selesai';
						}

					$exp_date_new = $row->created_at;

					if ($row->et_type == "online") {
						$link_webinar = $row->et_link_online;
					} else {
						$link_webinar = "";
					}

					$cek_ulasan = DB::table("ratting_apps")->where("cust_id", "=", $user_id)
						->leftJoin('event_orders', 'event_orders.id', '=', 'ratting_apps.lookup_id')
						->where("event_orders.et_id", "=", $row->et_id)
						->where("ra_type", "=", "event")
						->first();
					if ($cek_ulasan) {
						$user_ratting = 1;
					} else {
						$user_ratting = 0;
					}

					$results[] = [
						'order_id' => $row->pid,
						'customer_ratting' => $user_ratting,
						'nama_user' => $row->eo_name,
						'phone_user' => $row->eo_phone,
						'email_user' => $row->eo_email,
						'address_user' => $row->eo_address,
						'order_invoice' => $row->eo_invoice,
						'qty' => $row->eo_qty,
						'price' => $row->eo_price,
						'total_price' => $row->eo_total,
						'external_id' => $row->external_id,
						'account_number' => $row->va_number,
						'bank_code' => $row->channel_pg,
						'img' => '', //$img_channel,
						'pt' => 'PT. Mencetak Banyak Gol (Topscore)',
						'banner' => url('public/upload/payment_product.png'),
						'payment_status' => $payment_status,
						'event_id' => $row->et_id,
						'event_category' => $row->evt_name,
						'event_type' => $row->et_type,
						'event_title' => $row->et_title,
						'event_start_date' => $row->et_start_date,
						'event_start_time' => $row->et_start_time,
						'event_end_date' => $row->et_end_date,
						'event_end_time' => $row->et_end_time,
						'event_status' => $status_events,
						'event_address' => $row->et_address,
						'event_tournament' => $row->et_tournament,
						'event_team' => $event_team,
						'event_ticket' => $event_ticket,
						'event_banner' => 'https://topsystem.id/upload/event/' . $row->et_banner,
						'event_price' => $row->et_price,
						'event_terms' => $row->et_terms,
						'event_lat' => $row->et_lat,
						'event_lang' => $row->et_lang,
						'maps_url' => $row->et_maps_url,
						'link_webinar' => $link_webinar,
						'platform_webinar' => $platform_webinar,
						'platform_webinar_img' => $platform_webinar_img,
						'link_certificate' => $link_certificate,
						'created_at' => $row->created_at,
						'expiration_date' => $exp_date_new,
						'payment_info' => NULL,
						//'payment_instruction' => $result_pay,
						'payment_instruction' => NULL,
						'attendance_status' => $attendance,
						'attendance_date' => $eo_scan_date,
					];
				}

			}

			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Success'
				],
				'results' => $results
			];
		} else {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data not found'
				]
			];
		}
		return response()->json($data);
	}

	// tampilkan semua team user
	public function CekTeam(Request $request)
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
			'ta_page_access' => "Profile",
			'ta_description' => "Mengakses laman Myteam aplikasi",
			'ta_lookup_id' => $request->event_id,
			'created_at' => date("Y-m-d H:i:s"),
			'ip_address' => $request->ip(),
		];
		$save_log = DB::table("traffic_apps")->insertGetId(array_merge($data_log));

		$cust_id = $request->cust_id;
		$team_id = $request->team_id;

		if (!$cust_id) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Field cust_id tidak boleh kosong!'
				]
			];
			return response()->json($data);
		}

		$arr_customer = DB::table("customers")->where('id', $cust_id)->first();
		if (empty($arr_customer)) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Customer tidak ditemukan!'
				]
			];
			return response()->json($data);
		}


		$query_data = DB::table("event_orders")
			->selectRaw('ts_event_orders.id, eteam_name, ts_event_orders.created_at, eteam_id')
			->leftJoin('event_tops', 'event_tops.id', '=', 'event_orders.et_id')
			->leftJoin('event_teams', 'event_teams.id', '=', 'event_orders.eteam_id')
			->where('event_orders.payment_status', '=', '1')
			->whereNotNull('event_orders.eteam_id')
			->where('event_orders.u_id', '=', $cust_id)
			->where(function ($w) use ($team_id, $request) {
				if (!empty($team_id)) {
					$w->where('event_orders.eteam_id', '=', $team_id);
				}
			})
			->orderBy('event_orders.created_at', 'DESC')
			->paginate(10);

		$result_team = array();
		if (!empty($query_data)) {
			foreach ($query_data as $row) {
				$team_detail = array();
				$query_anggota = DB::table("event_team_details")
					->selectRaw('ts_event_team_details.id, eteam_id, etdet_name, etdet_phone, etdet_email, ts_event_team_details.created_at')
					->where('etdet_delete', '=', '0')
					->where('eteam_id', '=', $row->eteam_id)
					->orderBy('etdet_name', 'ASC')
					->get();
				if ($query_anggota) {
					$no = 0;
					foreach ($query_anggota as $sub) {
						$no++;
						$team_detail[] = [
							'teamdet_id' => $sub->id,
							'teamdet_name' => $sub->etdet_name,
							'teamdet_phone' => $sub->etdet_phone,
							'teamdet_email' => $sub->etdet_email,
						];
					}

					/*
					if($no==0){
						$team_detail[] = [
							'teamdet_id' => 0,
							'teamdet_name' => 'Default',
							'teamdet_phone' => "+62",
							'teamdet_email' => "@",
						];
					}
					*/
				}


				$result_team[] = [
					'team_id' => $row->eteam_id,
					'team_name' => $row->eteam_name,
					'team_detail' => $team_detail,
				];
			}

			$data = [
				'status' => [
					'code' => 200,
					'description' => 'Data ditemukan'
				],
				'team' => $result_team,
			];

		} else {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Data not found'
				],
			];
		}

		return response()->json($data);
	}

	// add team
	public function AddTeam(Request $request)
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
		$team_id = $request->team_id;
		$name = $request->teamdet_name;
		$phone = $request->teamdet_phone;
		$email = $request->teamdet_email;

		if (!$team_id || !$name || !$phone) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Field team_id, name & phone tidak boleh kosong!'
				]
			];
			return response()->json($data);
		}

		$arr_team = DB::table("event_teams")->where('id', $team_id)->first();
		if (empty($arr_team)) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Team tidak ditemukan!'
				]
			];
			return response()->json($data);
		}

		// save anggota team
		$data_save = [
			'eteam_id' => $team_id,
			'etdet_name' => $name,
			'etdet_phone' => $phone,
			'etdet_email' => $email,
			'created_at' => date("Y-m-d H:i:s"),
		];
		$saves = DB::table("event_team_details")->insertGetId(array_merge($data_save));
		// DB::table("customer_address")->where('cust_id', $cust_id)->update($updateds);

		if ($saves) {
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
					'description' => 'Data not found'
				],
			];
		}

		return response()->json($data);
	}

	// delete team
	public function DeleteTeam(Request $request)
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
		$team_id = $request->team_id;
		$teamdet_id = $request->teamdet_id;

		if (!$team_id || !$teamdet_id) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Field team_id & teamdet_id tidak boleh kosong!'
				]
			];
			return response()->json($data);
		}

		$arr_team = DB::table("event_teams")->where('id', $team_id)->first();
		if (empty($arr_team)) {
			$data = [
				'status' => [
					'code' => 400,
					'description' => 'Team tidak ditemukan!'
				]
			];
			return response()->json($data);
		}

		// save anggota team
		$data_save = [
			'etdet_delete' => '1',
			'updated_at' => date("Y-m-d H:i:s"),
		];
		//$saves = DB::table("event_team_details")->insertGetId(array_merge($data_save));
		$saves = DB::table("event_team_details")->where('id', $teamdet_id)->update($data_save);

		if ($saves) {
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
					'description' => 'Data not found'
				],
			];
		}
		return response()->json($data);
	}
}
