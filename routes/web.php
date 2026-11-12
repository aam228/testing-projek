<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ArtikelController;
use App\Http\Controllers\JneController;
use App\Http\Controllers\CrmEmailController;

//Route::get('', [AuthController::class, 'index'])->name('login');
//Route::get('', [ArtikelController::class, 'getStock'])->name('artikel_all');

// Legacy /public/* URLs from old server layout -> redirect to correct path
Route::get('/public/{any}', function ($any) {
    return redirect("/$any", 301);
})->where('any', '.*');

Route::get('artikel_all', [ArtikelController::class, 'getStock'])->name('artikel_all');
Route::get('artikel_detail', [ArtikelController::class, 'getStockDetail'])->name('artikel_detail');
Route::get('add_cart', [ArtikelController::class, 'AddCart'])->name('add_cart');
Route::get('get_cart', [ArtikelController::class, 'getCarts'])->name('get_cart');
Route::get('cek_ongkir_jne', [JneController::class, 'CekOngkir'])->name('cek_ongkir_jne');


Route::get('seed_test_product', function() {
    DB::statement("SET FOREIGN_KEY_CHECKS=0;");
    DB::table('stores')->updateOrInsert(['id' => 1], ['st_name' => 'Topscore Utama', 'st_code' => 'TS-STORE-01', 'st_description' => 'TS01', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('store_type_divisions')->updateOrInsert(['id' => 16], ['stt_id' => 1, 'dv_name' => 'APPS', 'dv_description' => 'AP', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('web_configs')->updateOrInsert(['id' => 1], ['config_name' => 'pos_prefix', 'config_value' => 'INV/', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('customers')->updateOrInsert(['id' => 1], ['cust_name' => 'Tester App User', 'cust_phone' => '08123456789', 'cust_email' => 'testuser@topscore.id', 'cust_address' => 'Jl. Margonda No. 100 Depok', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('payment_methods')->updateOrInsert(['id' => 1], ['a_id' => 1, 'stt_id' => 1, 'pm_name' => 'CASH', 'pm_description' => 'TUNAI', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('payment_methods')->updateOrInsert(['id' => 8], ['a_id' => 1, 'stt_id' => 1, 'pm_name' => 'MIDTRANS', 'pm_description' => 'MIDTRANS', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('payment_methods')->updateOrInsert(['id' => 99], ['a_id' => 1, 'stt_id' => 1, 'pm_name' => 'XENDIT', 'pm_description' => 'XENDIT PAYMENT', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('products')->updateOrInsert(['id' => 1], ['p_name' => 'Sepatu Topscore Pro Test', 'p_color' => 'Black', 'p_price' => 250000, 'sold' => 0, 'p_delete' => '0', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('sizes')->updateOrInsert(['id' => 1], ['sz_name' => '42', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('product_stocks')->updateOrInsert(['id' => 1], ['p_id' => 1, 'sz_id' => 1, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('product_locations')->updateOrInsert(['id' => 1], ['st_id' => 1, 'pl_code' => 'RAK-A1', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('product_location_setups')->updateOrInsert(['id' => 1], ['pst_id' => 1, 'pl_id' => 1, 'pls_qty' => 5, 'created_at' => now(), 'updated_at' => now()]);
    DB::statement("SET FOREIGN_KEY_CHECKS=1;");
    return response()->json(['status' => 200, 'message' => 'Test product seeded successfully with 5 stock!']);
});



//Route::post('user_login', [AuthController::class, 'login']);
//Route::get('logout', [AuthController::class, 'logout'])->name('logout');

