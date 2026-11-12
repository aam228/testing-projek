<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ArtikelController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\JneController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\WilayahController;
use App\Http\Controllers\CustController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\PaymentMidtrans;
use App\Http\Controllers\PaymentXendit;
use App\Http\Controllers\FavoritController;
//use App\Http\Controllers\JneController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\YoutubeController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\CallbackController;
use App\Http\Controllers\PointCustController;
use App\Http\Controllers\PointOfSaleController;
use App\Http\Controllers\NotifController;
use App\Http\Controllers\BiteshipController;
use App\Http\Controllers\HelpcenterController;
use App\Http\Controllers\UlasanController;
use App\Http\Controllers\MailController;
use App\Http\Controllers\ChannelPayController;
use App\Http\Controllers\FirebaseNotify;

Route::any('seed_test_product', function() {
    \Illuminate\Support\Facades\DB::statement("SET FOREIGN_KEY_CHECKS=0;");
    \Illuminate\Support\Facades\DB::table('stores')->updateOrInsert(['id' => 1], ['st_name' => 'Topscore Utama', 'st_code' => 'TS-STORE-01', 'st_description' => 'TS01', 'created_at' => now(), 'updated_at' => now()]);
    \Illuminate\Support\Facades\DB::table('store_type_divisions')->updateOrInsert(['id' => 16], ['stt_id' => 1, 'dv_name' => 'APPS', 'dv_description' => 'AP', 'created_at' => now(), 'updated_at' => now()]);
    \Illuminate\Support\Facades\DB::table('web_configs')->updateOrInsert(['id' => 1], ['config_name' => 'pos_prefix', 'config_value' => 'INV/', 'created_at' => now(), 'updated_at' => now()]);
    \Illuminate\Support\Facades\DB::table('customers')->updateOrInsert(['id' => 1], ['cust_name' => 'Tester App User', 'cust_phone' => '08123456789', 'cust_email' => 'testuser@topscore.id', 'cust_address' => 'Jl. Margonda No. 100 Depok', 'created_at' => now(), 'updated_at' => now()]);
    \Illuminate\Support\Facades\DB::table('payment_methods')->updateOrInsert(['id' => 1], ['a_id' => 1, 'stt_id' => 1, 'pm_name' => 'CASH', 'pm_description' => 'TUNAI', 'created_at' => now(), 'updated_at' => now()]);
    \Illuminate\Support\Facades\DB::table('payment_methods')->updateOrInsert(['id' => 8], ['a_id' => 1, 'stt_id' => 1, 'pm_name' => 'MIDTRANS', 'pm_description' => 'MIDTRANS', 'created_at' => now(), 'updated_at' => now()]);
    \Illuminate\Support\Facades\DB::table('payment_methods')->updateOrInsert(['id' => 99], ['a_id' => 1, 'stt_id' => 1, 'pm_name' => 'XENDIT', 'pm_description' => 'XENDIT PAYMENT', 'created_at' => now(), 'updated_at' => now()]);
    \Illuminate\Support\Facades\DB::table('products')->updateOrInsert(['id' => 1], ['p_name' => 'Sepatu Topscore Pro Test', 'p_color' => 'Black', 'p_price' => 250000, 'sold' => 0, 'p_delete' => '0', 'created_at' => now(), 'updated_at' => now()]);
    \Illuminate\Support\Facades\DB::table('sizes')->updateOrInsert(['id' => 1], ['sz_name' => '42', 'created_at' => now(), 'updated_at' => now()]);
    \Illuminate\Support\Facades\DB::table('product_stocks')->updateOrInsert(['id' => 1], ['p_id' => 1, 'sz_id' => 1, 'created_at' => now(), 'updated_at' => now()]);
    \Illuminate\Support\Facades\DB::table('product_locations')->updateOrInsert(['id' => 1], ['st_id' => 1, 'pl_code' => 'RAK-A1', 'created_at' => now(), 'updated_at' => now()]);
    \Illuminate\Support\Facades\DB::table('product_location_setups')->updateOrInsert(['id' => 1], ['pst_id' => 1, 'pl_id' => 1, 'pls_qty' => 5, 'created_at' => now(), 'updated_at' => now()]);
    \Illuminate\Support\Facades\DB::statement("SET FOREIGN_KEY_CHECKS=1;");
    return response()->json(['status' => 200, 'message' => 'Test product seeded successfully with 5 stock!']);
});


use App\Http\Controllers\DashboardAppsController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\ForumController;
use App\Http\Controllers\GamesController;
use App\Http\Controllers\SizeController;
use App\Http\Controllers\BenefitController;
use App\Http\Controllers\WishlistsController;
use App\Http\Controllers\MycoinController;
use App\Http\Controllers\PromoController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\RattingAppsController;
use App\Http\Controllers\CrmEmailController;
use App\Http\Controllers\PushnotifController;
use App\Http\Controllers\DashboardAppsduaController;
use App\Http\Controllers\EventduaController;
use App\Http\Controllers\WhatsappController;
use App\Http\Controllers\ResellerController;
use App\Http\Controllers\CronController;
use App\Http\Controllers\Forum2Controller;
use App\Http\Controllers\OnesignalNotify;
use App\Http\Controllers\StravaController;
use App\Http\Controllers\SurveyController;
use App\Http\Controllers\GamechallengeController;
use App\Http\Controllers\DashboardAppstigaController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('login', [AuthController::class, 'login']);
Route::post('loginGoogle', [AuthController::class, 'loginGoogle']);
Route::post('logout', [AuthController::class, 'logout']);
Route::post('vusers', [AuthController::class, 'userView']);
Route::post('checkEmail',[AuthController::class, 'checkEmail']);
Route::post('register', [AuthController::class, 'Registers']);
Route::post('update_password', [AuthController::class, 'changePassword']);
Route::post('update_user', [AuthController::class, 'updateUser']);
Route::post('delete_akun', [AuthController::class, 'DeleteAccount']);
Route::post('reset_password', [AuthController::class, 'changePassword_otp']);
Route::post('check_phone', [AuthController::class, 'phone_verification']);
Route::post('check_email', [AuthController::class, 'CekRegisterEmail']);
Route::post('login_phone', [AuthController::class, 'loginPhone']);
Route::post('register_v2', [AuthController::class, 'RegisterNew']);
Route::post('verify_email', [AuthController::class, 'EmailVerify']);
Route::post('referal_update', [AuthController::class, 'ReferalUpdate']);
Route::post('verify_otp', [AuthController::class, 'otp_verification']);
Route::post('register_v3', [AuthController::class, 'RegisterNew3']);
Route::post('whatsapp_token', [AuthController::class, 'sendwaUser']);
Route::post('verify_token_whatsapp', [AuthController::class, 'loginwithToken']);
Route::post('update_user_2', [AuthController::class, 'updateProfile']);
Route::post('info_sosmed', [AuthController::class, 'infoSosmed']);

Route::post('banner', [BannerController::class, 'getBanner']);
Route::post('category', [CategoryController::class, 'getBanner']);
Route::post('category_brand', [CategoryController::class, 'getBrand']);
Route::post('all_brand', [CategoryController::class, 'allBrand']);
Route::post('subcategory', [CategoryController::class, 'getSubcat']);
Route::post('cek_ongkir_jne', [JneController::class, 'CekOngkir'])->name('cek_ongkir_jne');
Route::post('store', [StoreController::class, 'getStore']);
Route::post('province', [WilayahController::class, 'getProvince']);
Route::post('city', [WilayahController::class, 'getCity']);
Route::post('district', [WilayahController::class, 'getDistrict']);
Route::post('subdistrict', [WilayahController::class, 'getSubdistrict']);
Route::post('search_district', [WilayahController::class, 'getsearchZip']);
Route::post('zip_koordinat', [WilayahController::class, 'googlePoint']);
Route::post('all_district', [WilayahController::class, 'getallDistrict']);
Route::post('all_zip', [WilayahController::class, 'getallZip']);
Route::post('koordinat_zip', [WilayahController::class, 'googleZip']);
Route::post('koordinat_place', [WilayahController::class, 'googlePlace']);

Route::post('profile_user', [CustController::class, 'getCust']);
Route::post('save_alamat', [CustController::class, 'storeAddress']);
Route::post('delete_alamat', [CustController::class, 'deleteAddress']);
Route::post('update_avatar', [CustController::class, 'updateAvatar']);
Route::post('update_topic', [CustController::class, 'updateTopic']);

// withdraw koin customer
Route::post('request_withdraw', [CustController::class, 'withdrawRequest']);
Route::post('claim_benefit', [CustController::class, 'benefitLevel']);
Route::post('catproduct_user', [CustController::class, 'updateCategory']);

Route::post('setting_apps', [SettingController::class, 'getSetting']);
Route::post('locations', [WilayahController::class, 'getLocations']);

// Payment Midtrans
Route::post('paymandiriva', [PaymentMidtrans::class, 'getPaymentMandiri']);
Route::post('paybniva', [PaymentMidtrans::class, 'getPaymentBni']);
Route::post('paybriva', [PaymentMidtrans::class, 'getPaymentBri']);
Route::post('paypermatava', [PaymentMidtrans::class, 'getPaymentPermata']);
Route::post('paybcava', [PaymentMidtrans::class, 'getPaymentBca']);
// Payment Xendit
Route::post('payxdva', [PaymentXendit::class, 'createvirtualAccount']);
Route::post('payxdqris', [PaymentXendit::class, 'createQris']);
Route::post('payxdewallet', [PaymentXendit::class, 'createewallet']);
// FavoritController
Route::post('chekfavorit', [FavoritController::class, 'getFavorit']);
Route::post('savefavorit', [FavoritController::class, 'storeData']);
// Artikel and Cart
Route::post('artikel_all', [ArtikelController::class, 'getStock'])->name('artikel_all');
Route::post('artikel_all_new', [ArtikelController::class, 'getStock_post'])->name('artikel_all_new');
Route::post('artikel_detail', [ArtikelController::class, 'getStockDetail'])->name('artikel_detail');
Route::post('add_cart', [ArtikelController::class, 'AddCart'])->name('add_cart');
Route::post('get_cart', [ArtikelController::class, 'getCarts'])->name('get_cart');
Route::post('del_cart', [ArtikelController::class, 'DeleteCart'])->name('del_cart');
Route::post('artikel_price', [ArtikelController::class, 'getpriceDetail']);
// update cart qty
Route::post('qty_cart', [ArtikelController::class, 'UpdateQtyCart']);

Route::post('artikel_color', [ArtikelController::class, 'getStocksize']);
Route::post('check_fees', [ArtikelController::class, 'feeServices']);

Route::post('cek_ongkir_jne', [JneController::class, 'CekOngkir'])->name('cek_ongkir_jne');
// Voucher
Route::post('cek_voucher', [VoucherController::class, 'CekVoucher'])->name('cek_voucher');
Route::post('youtube', [YoutubeController::class, 'CekVideo'])->name('youtube');
Route::post('youtube_view', [YoutubeController::class, 'updateViewers']);
// EVENT
Route::post('event_list', [EventController::class, 'CekEvent'])->name('event_list');
Route::post('save_eo', [EventController::class, 'createOrders'])->name('save_eo');
Route::post('cek_order_event', [EventController::class, 'CekOrders'])->name('cek_order_event');

// CALBACK PG
//Route::post('callback_xd', [CallbackController::class, 'index'])->name('callback_xd');
//Route::post('callback_xd_va', [CallbackController::class, 'CallbackVirtualAccount'])->name('callback_xd_va');
Route::post('callback_xd', [CallbackController::class, 'index'])->name('callback_xd');
Route::post('callback_xd_va', [CallbackController::class, 'CallbackVirtualAccount'])->name('callback_xd_va');
Route::post('callback_xd_ewallet', [CallbackController::class, 'CallbackEwallet'])->name('callback_xd_ewallet');
Route::get('callback_watsapp', [CallbackController::class, 'CallbackEwallet'])->name('callback_watsapp');

// point customer
Route::post('point_customer', [PointCustController::class, 'CekPoint'])->name('point_customer');
Route::post('point_update', [PointCustController::class, 'savePoint'])->name('point_update');
// POS
Route::post('save_pos', [PointOfSaleController::class, 'saveTransaction'])->name('save_pos');
Route::post('cek_order_pos', [PointOfSaleController::class, 'CekOrders'])->name('cek_order_pos');
Route::post('cancel_invoice', [PointOfSaleController::class, 'cancelInvoice']);
Route::post('repeat_order_check', [PointOfSaleController::class, 'orderRepeatcheck']);
Route::post('repeat_order_cart', [PointOfSaleController::class, 'repeatOrderCart']);
Route::post('update_status_invoice', [PointOfSaleController::class, 'doneInvoice']);

// notifikasi apps
Route::post('notif_list', [NotifController::class, 'index'])->name('notif_list');
Route::post('read_notif', [NotifController::class, 'updateRead'])->name('read_notif');
Route::post('read_all', [NotifController::class, 'updateReadall'])->name('read_all');
// BiteshipController
Route::post('lookup_wilayah', [BiteshipController::class, 'index'])->name('lookup_wilayah');
Route::post('cek_ongkit_biteship', [BiteshipController::class, 'cekOngkir']);
Route::post('tracking_orders', [BiteshipController::class, 'TrackingOrder']);
Route::post('callback_biteship', [BiteshipController::class, 'callbackOrder']);
Route::post('cek_ongkir_instant', [BiteshipController::class, 'cekOngkirInstant']);
Route::post('expedisi_order', [BiteshipController::class, 'submitOrder_default']);
Route::post('expedisi_order_instant', [BiteshipController::class, 'submitOrder_instant']);
Route::post('expedisi_order_cod', [BiteshipController::class, 'submitOrder_cod']);
Route::post('expedisi_cekorder', [BiteshipController::class, 'checkOrder']);
Route::post('expedisi_cancel', [BiteshipController::class, 'cancelBiteship']);


Route::post('save_artikel_view', [ArtikelController::class, 'storeArticle']);
// Helpcenter
Route::post('faq', [HelpcenterController::class, 'index'])->name('faq');
// ulasan saya
Route::post('ulasan', [UlasanController::class, 'index'])->name('ulasan');
Route::post('v_ulasan', [UlasanController::class, 'ViewUlasan'])->name('v_ulasan');
// Tes Email
#Route::get('sendmail', [MailController::class, 'index']);
Route::post('kirim_otp', [MailController::class, 'kirim_otp']);
Route::post('kirim_eticket', [MailController::class, 'kirim_ticket']);
Route::post('kirim_otp_email', [MailController::class, 'kirim_otp_verify_mail']);

Route::post('channel_payment', [ChannelPayController::class, 'index']);

Route::post('push_notify', [FirebaseNotify::class, 'index']);
Route::post('send_notify', [FirebaseNotify::class, 'SendNotify']);
Route::post('send_notify_multi', [FirebaseNotify::class, 'SendMulti']);

Route::post('new_dashboard', [DashboardAppsController::class, 'getData']);
Route::post('count_status', [DashboardAppsController::class, 'cekPesananactive']);

Route::post('blog', [BlogController::class, 'index']);
Route::post('blog_comment', [BlogController::class, 'postComment']);
Route::post('blog_like', [BlogController::class, 'likeBlog']);


Route::post('forum', [ForumController::class, 'index']);
Route::post('forum_join', [ForumController::class, 'joinForum']);
Route::post('forum_blog', [ForumController::class, 'blogForum']);
Route::post('forum_blog_comment', [ForumController::class, 'postComment']);
Route::post('forum_blog_comment_like', [ForumController::class, 'likeComment']);
Route::post('forum_leave', [ForumController::class, 'leaveForum']);

Route::post('games', [GamesController::class, 'index']);
Route::post('games_fun', [GamesController::class, 'gameUser']);
Route::post('games_finish', [GamesController::class, 'gameAnswer']);
Route::post('games_score', [GamesController::class, 'tebakScore']);
Route::post('games_score_log', [GamesController::class, 'historyScore']);
Route::post('games_score_leaderboard', [GamesController::class, 'winuserScore']);

Route::post('size', [SizeController::class, 'index']);
Route::post('benefit_level', [BenefitController::class, 'index']);

// WishlistsController
Route::post('wishlist', [WishlistsController::class, 'index']);
Route::post('wishlist_add', [WishlistsController::class, 'storeData']);
Route::post('wishlist_delete', [WishlistsController::class, 'deleteData']);

// My coin
Route::post('log_coin', [MycoinController::class, 'CekCoin']);
Route::post('log_score', [MycoinController::class, 'CekScore']);
Route::post('log_coinscore', [MycoinController::class, 'CekCoinscore']);
Route::post('info_coin_score', [MycoinController::class, 'infoCoinScore']);

// PromoController
Route::post('promo', [PromoController::class, 'index']);
Route::post('promo_detail', [PromoController::class, 'detailPromo']);
Route::post('promo_benefit', [PromoController::class, 'benefitCustomer']);

// ScanController
Route::post('scan_invoice', [ScanController::class, 'index']);
Route::post('generate_encode', [ScanController::class, 'generateEncode']);

// SearchController
Route::post('search_home', [SearchController::class, 'index']);
Route::post('search_detail', [SearchController::class, 'searchProduct']);
Route::post('search_detail_keyword', [SearchController::class, 'searchProductlive']);
Route::post('search_trending', [SearchController::class, 'productTrending']);

Route::post('ratting_apps', [RattingAppsController::class, 'index']);
Route::post('ratting_app_save', [RattingAppsController::class, 'storeData']);

Route::post('check_artikel', [ArtikelController::class, 'getaArtikelall']);
Route::post('artikel_recom', [ArtikelController::class, 'recomendationArticle']);

// CrmEmailController
Route::post('kirim_email_test', [CrmEmailController::class, 'index']);
Route::post('kirim_email', [CrmEmailController::class, 'index']);


// save_pushnotif PushnotifController
Route::post('save_pushnotif', [PushnotifController::class, 'storeData']);
Route::post('delete_pushnotif', [PushnotifController::class, 'deleteData']);
Route::post('status_pushnotif', [PushnotifController::class, 'statusData']);
Route::post('status_pushnotif_onesignal', [PushnotifController::class, 'onesignalstatusData']);

Route::post('new_dashboard_v2', [DashboardAppsduaController::class, 'getData']);

// event versi 2
Route::post('event_list_v2', [EventduaController::class, 'CekEvent'])->name('event_list');
Route::post('save_eo_v2', [EventduaController::class, 'createOrders'])->name('save_eo');
Route::post('cek_order_event_v2', [EventduaController::class, 'CekOrders'])->name('cek_order_event');
Route::post('cek_team', [EventduaController::class, 'CekTeam'])->name('cek_team');
Route::post('add_team', [EventduaController::class, 'AddTeam'])->name('add_team');
Route::post('delete_team', [EventduaController::class, 'DeleteTeam'])->name('delete_team');

// WhatsappController
Route::post('wa_api', [WhatsappController::class, 'index']);
Route::post('wa_maxchat', [WhatsappController::class, 'sendMessages']);
Route::post('wa_send_bday', [WhatsappController::class, 'bdayCustomer']);

// ResellerController
Route::post('reseller_index', [ResellerController::class, 'index']);
Route::post('reseller_registration', [ResellerController::class, 'createOrders']);
Route::post('reseller_checkorder', [ResellerController::class, 'CekOrders']);
Route::post('reseller_checkprofile', [ResellerController::class, 'customerReseller']);
Route::post('reseller_update', [ResellerController::class, 'customerResellerupdate']);
Route::post('reseller_registration_web', [ResellerController::class, 'webcreateOrders']);
Route::post('reseller_checkorder_web', [ResellerController::class, 'webcekOrders']);
Route::post('reseller_packet', [ResellerController::class, 'listPacket']);
Route::post('reseller_checkprofile_new', [ResellerController::class, 'profileReseller']);
Route::post('reseller_update_new', [ResellerController::class, 'customerResellerupdate_new']);
Route::post('reseller_cancel', [ResellerController::class, 'offReseller']);
Route::post('reseller_off', [ResellerController::class, 'offuserReseller']); // off kan sementara sebegai reseller
Route::post('reseller_cancel_order', [ResellerController::class, 'cancelOrders']);

// CronController
Route::post('cron_resetcoinnewuser', [CronController::class, 'resetCoinnewmember']);
Route::post('cron_useridpos', [CronController::class, 'updateUseridapps']);
Route::post('cron_cart_reminder', [CronController::class, 'reminderCart']);
Route::post('cron_weborder', [CronController::class, 'waSendwebsite']);

// Forum2Controller
Route::post('forum2', [Forum2Controller::class, 'index']);
Route::post('forum2_join', [Forum2Controller::class, 'joinForum']);
Route::post('forum2_detail', [Forum2Controller::class, 'DetailForum']);
//Route::post('forum2_blog_comment', [Forum2Controller::class, 'postComment']);
//Route::post('forum2_blog_comment_like', [Forum2Controller::class, 'likeComment']);
Route::post('forum2_leave', [Forum2Controller::class, 'leaveForum']);
Route::post('forum2_feeds', [Forum2Controller::class, 'listFeeds']);
Route::post('forum2_feed_save', [Forum2Controller::class, 'saveFeeds']);
Route::post('forum2_feed_delete', [Forum2Controller::class, 'deleteFeeds']);
Route::post('game_score_save', [Forum2Controller::class, 'scoreAnswer']); // simpan tebak skor game
Route::post('game_score_user', [Forum2Controller::class, 'userlistAnswerscore']);
Route::post('forum2_feed_like_save', [Forum2Controller::class, 'likeFeeds']);
Route::post('forum2_feed_comment_save', [Forum2Controller::class, 'commentFeeds']);
Route::post('forum2_feed_share_save', [Forum2Controller::class, 'shareFeeds']);
Route::post('forum2_feed_like', [Forum2Controller::class, 'userlistlikeFeeds']);
Route::post('forum2_feed_comment', [Forum2Controller::class, 'userlistcommentFeeds']);
Route::post('forum2_feed_report_save', [Forum2Controller::class, 'reportFeeds']);
Route::post('forum2_feed_edit_save', [Forum2Controller::class, 'editFeeds']);
Route::post('forum2_feed_user', [Forum2Controller::class, 'userlistFeeds']);
Route::post('forum2_detail_join', [Forum2Controller::class, 'userjoinList']);

// OnesignalNotify
Route::post('pushone_user', [OnesignalNotify::class, 'sendNotif']);
Route::post('pushall_user', [OnesignalNotify::class, 'sendNotifall']);

Route::post('connect_strava_webhook', [StravaController::class, 'connectwebhookStrava']);
Route::post('activity_strava_user', [StravaController::class, 'getlogActivity']);
Route::post('connect_strava_user', [StravaController::class, 'userConnect']);
Route::post('connect_strava_user_activities', [StravaController::class, 'listActivities']);
Route::post('running_challenge', [StravaController::class, 'listChallenge']);
Route::post('running_join', [StravaController::class, 'joinChallenge']);
Route::post('running_challenge_detail', [StravaController::class, 'detailChallenge']);
Route::post('running_leaderboard_all', [StravaController::class, 'leaderboardAll']);
Route::post('running_leaderboard_challenge', [StravaController::class, 'leaderboardChallenge']);
Route::post('running_claim_challenge', [StravaController::class, 'claimChallenge']);
Route::post('running_reset_challenge', [StravaController::class, 'resetchallenge']);
Route::post('running_ongoing_challenge', [StravaController::class, 'listongoingChallenge']);
Route::post('running_history_challenge', [StravaController::class, 'logChalengeuser']);
Route::post('running_savewipe', [StravaController::class, 'wipeSave']);
Route::post('activity_strava_user_save', [StravaController::class, 'getlogActivitysave']);
Route::post('strava_profile', [StravaController::class, 'profileStrava']); // profile user strava ***
Route::post('strava_report', [StravaController::class, 'reportStrava']); // report aktifitas mencurigakan
Route::post('strava_checkdouble', [StravaController::class, 'checklogDevicedouble']);

Route::post('surrvey_list', [SurveyController::class, 'surveyActive']);
Route::post('surrvey_save', [SurveyController::class, 'surveySave']);

// GamechallengeController
Route::post('gamechallenge', [GamechallengeController::class, 'index']);
Route::post('gamechallenge_terms', [GamechallengeController::class, 'challengeTerms']);
//Route::post('gamechallenge_scan', [GamechallengeController::class, 'index']);
Route::post('gamechallenge_logscan', [GamechallengeController::class, 'challengeLogsan']);

// DashboardAppstigaController
Route::post('new_dashboard_v3', [DashboardAppstigaController::class, 'index']);

// Remediasi Kontrol Akses: Rute dalam Grup Middleware Autentikasi Token
Route::group(['middleware' => ['auth.token']], function () {
    Route::post('order', [PointOfSaleController::class, 'saveTransaction'])->name('order_secure');
    Route::post('v2/save_pos', [PointOfSaleController::class, 'saveTransaction'])->name('save_pos_secure');
});
