<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\WebConfig;
use App\Models\User;
use App\Models\ProductLocation;
use App\Models\UserActivity;
use App\Models\PosTransaction;
use Hash;

class AuthController extends Controller
{
    protected function UserActivity($activity)
    {
        UserActivity::create([
            'user_id' => Auth::user()->id,
            'ua_description' => $activity,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    protected function UserActivityLogin($email, $activity)
    {
        $user_id = User::select('id')->where('u_email', $email)->get()->first();
        if (!empty($user_id)) {
            UserActivity::create([
                'user_id' => $user_id->id,
                'ua_description' => $activity,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }

    public function index()
    {
        $title = WebConfig::select('config_value')->where('config_name', 'app_title')->get()->first()->config_value;
        $data = [
            'title' => $title,
            'segment' => request()->segment(1),
            'pl_id' => ProductLocation::selectRaw('ts_product_locations.id as pl_id, CONCAT(pl_code," (",st_name,")") as location')
            ->join('stores', 'stores.id', '=', 'product_locations.st_id')
            ->where('pl_delete', '!=', '1')
            ->orderByDesc('pl_code')->pluck('location', 'pl_id'),
            'invoice' => PosTransaction::select('pos_invoice', 'plst_status')
            ->leftJoin('product_location_setup_transactions', 'product_location_setup_transactions.pt_id', '=', 'pos_transactions.id')
            ->whereIn('plst_status', ['WAITING ONLINE', 'WAITING FOR PACKING'])
            ->groupBy('pos_invoice')
            ->orderByDesc('pos_invoice')->pluck('pos_invoice', 'pos_invoice'),
            // 'pl_id' => ProductLocation::where('pl_delete', '!=', '1')->orderByDesc('id')->pluck('pl_code', 'id'),
        ];
        if (Auth::check()) {
            return redirect()->route('dashboard');
        } else {
            return view('auth.login', compact('data'));
        }
    }

    public function login(Request $request)
    {
        if ($request->input('u_email') == 'm.sadikin@topsystem.id') {
            $r['status'] = "400";
            return json_encode($r);
        }
        $user = new User;
        $data = [
            'u_email' => $request->input('u_email'),
            'password' => $request->input('password'),
        ];
        $lg_description = '';
        Auth::attempt($data);
        if (Auth::check()) {
            $check = $user->checkData('id', ['u_email' => $request->input('u_email'), 'u_delete' => '0']);
            if (!empty($check)) {
                $this->UserActivityLogin($request->input('u_email'), 'melakukan login');
                $r['status'] = "200";
                $lg_description = 'Login berhasil';
            } else {
                $this->UserActivityLogin($request->input('u_email'), 'mencoba login namun akun nonaktif');
                Auth::logout();
                $r['status'] = "500";
                $lg_description = 'User non aktif';
            }
        } else {
            $this->UserActivityLogin($request->input('u_email'), 'mencoba login namun salah password');
            Auth::logout();
            $r['status'] = "400";
            $lg_description = 'Gagal Login';
        }
        
        // save log login
		$mode = "add";
		$ip = $_SERVER['REMOTE_ADDR'];
        
        $data = [
            'lg_email' => $request->input('u_email'),
            'lg_password' => $request->input('password'),
            'lg_ip' => $ip,
            'lg_description' => $lg_description,
        ];
        $save = $user->storeDataLog($mode, $ip, $data);
		// end log login...
        
        return json_encode($r);
    }

    public function changePassword(Request $request)
    {
        $user = new User;
        $password = $request->_password;
        $data = [
            'password' => Hash::make($password),
        ];
        if ($user->storePassword(Auth::user()->id, $data)) {
            $this->UserActivity('mengubah password akun');
            $r['status'] = '200';
        } else {
            $r['status'] = '400';
        }
        return json_encode($r);
    }

    public function changeStore(Request $request)
    {
        $st_id = $request->post('st_id');
        $save = DB::table('users')->where('id', '=', Auth::user()->id)->update([
            'st_id' => $st_id
        ]);

        if (!empty($save)) {
            $this->UserActivity('mengubah store');
            $r['status'] = '200';
        } else {
            $r['status'] = '400';
        }
        return json_encode($r);
    }

    public function logout()
    {
        $this->UserActivity('melakukan logout');
        Auth::logout();
        return redirect()->route('login');
    }
}
