<?php

namespace App\Models;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
//use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use  HasFactory, Notifiable;
	protected $table = 'customers';
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
	
	public function checkData($select, $where)
    {
        $affected = DB::table($this->table)
            ->select($select)
            ->where($where)
            ->get()->first();
        return $affected;
    }
	
	public function storeData($mode, $data)
    {
		$password = '';
        /*
		$hash_password = Hash::make($password);
        $new_password = [
            'password' => $hash_password
        ];
		*/

        if ($mode == 'edit') {
            if (empty($password)) {
                $user = DB::table($this->table)->where('id', $id)->update(array_merge($data, $updated));
            } else {
                $user = DB::table($this->table)->where('id', $id)->update(array_merge($data, $new_password, $updated));
            }
			$notif = 'success_edit';
        } else {
			// add data baru...
            $user = DB::table($this->table)->insertGetId(array_merge($data));
			$notif = 'success_add';
        }
        return $notif;
    }

    public function storePassword($id, $data)
    {
        $user = DB::table($this->table)->where('id', $id)->update($data);
        return $user;
    }
}
