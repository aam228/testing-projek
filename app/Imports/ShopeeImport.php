<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use App\Models\ExceptionLocation;

class ShopeeImport implements ToCollection, WithStartRow
{
    private $rows = 0;
    protected $tg_id;
    protected $storage;
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */

    function __construct($tg_id, $storage)
    {
        $this->tg_id = $tg_id;
        $this->storage = $storage;
    }

    public function startRow(): int
    {
        return 5;
    }

    public function collection(Collection $row)
    {
        ++$this->rows;
        $data_id = array();
        $status = 0;
        $dt_rows = 5;
        foreach ($row as $r) {
            if ($r[0] == null) {
                return null;
            }
            $pst_id = null;
            $system_stock = null;
            $template_row = $dt_rows;
            $kode_produk = $r[0];
            $nama_produk = $r[1];
            $kode_variasi = $r[2];
            $nama_variasi = $r[3];
            $min_buy = $r[10];
            $sku = $r[11];
            $price = $r[12];
            $stok = $r[13];

            $br_id = null;
            $sz_id = null;
            $article = null;
            $p_id = null;
            $size = null;
            $color = null;

            // training product name
            $exp = explode(' ', $nama_produk);
            $total = count($exp);
            if ($total > 0) {
                if ($total > 5) {
                    $total = 5;
                }
                for ($i = 0; $i < $total; $i ++) {
                    $x = 1;
                    $is_brand = DB::table('brands')->select('id')
                    ->where('br_delete', '!=', '1')->where('br_name', '=', ltrim($exp[$i]))->get()->first();
                    if (!empty($is_brand)) {
                        $br_id = $is_brand->id;
                        $phrase_1 = null;
                        $phrase_2 = null;
                        $phrase_3 = null;
                        $phrase_4 = null;
                        if (!empty($exp[$i+4])) {
                            $article = ltrim($exp[$i+1]).' '.ltrim($exp[$i+2]).' '.ltrim($exp[$i+4]).' '.ltrim($exp[$i+4]);
                        } else if (!empty($exp[$i+3])) {
                            $article = ltrim($exp[$i+1]).' '.ltrim($exp[$i+2]).' '.ltrim($exp[$i+3]);
                        } else if (!empty($exp[$i+2])) {
                            $article = ltrim($exp[$i+1]).' '.ltrim($exp[$i+2]);
                        } else if (!empty($exp[$i+1])) {
                            $article = ltrim($exp[$i+1]);
                        } else {
                            $article = ltrim($exp[$i]);
                        }
                        $exp_size_color = explode(',', $nama_variasi);
                        $total_exp_size_color = count($exp_size_color);
                        if ($total_exp_size_color > 1) {
                            $color = ltrim($exp_size_color[0]);
                        }
                        if (!empty($color)) {
                            $exp_color = explode(' ', $color);
                            if (count($exp_color) >= 4) {
                                $ph1 = ltrim($exp_color[0]);
                                $ph2 = ltrim($exp_color[1]);
                                $ph3 = ltrim($exp_color[2]);
                                $ph4 = ltrim($exp_color[3]);
                                $phrase_1 = $ph1.' '.$ph2.' '.$ph3.' '.$ph4;
                                $phrase_2 = $ph1.'-'.$ph2.'-'.$ph3.'-'.$ph4;
                                $phrase_3 = $ph1.'/'.$ph2.'/'.$ph3.'/'.$ph4;
                                $phrase_4 = $ph1.'-'.$ph2.'/'.$ph3.'-'.$ph4;
                                if(strpos($article, $ph1) !== false){
                                    $phrase_1 = $ph2.' '.$ph3.' '.$ph4;
                                    $phrase_2 = $ph2.'-'.$ph3.'-'.$ph4;
                                    $phrase_3 = $ph2.'/'.$ph3.'/'.$ph4;
                                    $phrase_4 = $ph2.'/'.$ph3.'-'.$ph4;
                                }
                                if(strpos($article, $ph2) !== false){
                                    $phrase_1 = $ph3.' '.$ph4;
                                    $phrase_2 = $ph3.'-'.$ph4;
                                    $phrase_3 = $ph3.'/'.$ph4;
                                    $phrase_4 = $ph3.'-'.$ph4;
                                } else {
                                    $check_ph2 = DB::table('products')->where('p_color', 'LIKE', '%'.$ph2.'%')->exists();
                                    if (!$check_ph2) {
                                        $article = $article.' '.$ph2;
                                    }
                                }
                                if(strpos($article, $ph3) !== false){
                                    $phrase_1 = $ph4;
                                    $phrase_2 = $ph4;
                                    $phrase_3 = $ph4;
                                    $phrase_4 = $ph4;
                                } else {
                                    $check_ph3 = DB::table('products')->where('p_color', 'LIKE', '%'.$ph3.'%')->exists();
                                    if (!$check_ph3) {
                                        $article = $article.' '.$ph3;
                                    }
                                }
                            } else if (count($exp_color) == 3) {
                                $ph1 = ltrim($exp_color[0]);
                                $ph2 = ltrim($exp_color[1]);
                                $ph3 = ltrim($exp_color[2]);
                                $phrase_1 = $ph1.' '.$ph2.' '.$ph3;
                                $phrase_2 = $ph1.'-'.$ph2.'-'.$ph3;
                                $phrase_3 = $ph1.'/'.$ph2.'/'.$ph3;
                                if(strpos($article, $ph1) !== false){
                                    $phrase_1 = $ph2.' '.$ph3;
                                    $phrase_2 = $ph2.'-'.$ph3;
                                    $phrase_3 = $ph2.'/'.$ph3;
                                }
                                if(strpos($article, $ph2) !== false){
                                    $phrase_1 = $ph3;
                                    $phrase_2 = $ph3;
                                    $phrase_3 = $ph3;
                                } else {
                                    $check_ph2 = DB::table('products')->where('p_color', 'LIKE', '%'.$ph2.'%')->exists();
                                    if (!$check_ph2) {
                                        $article = $article.' '.$ph2;
                                    }
                                }
                            } else if (count($exp_color) == 2) {
                                $ph1 = ltrim($exp_color[0]);
                                $ph2 = ltrim($exp_color[1]);
                                $phrase_1 = $ph1.' '.$ph2;
                                $phrase_2 = $ph1.'-'.$ph2;
                                $phrase_3 = $ph1.'/'.$ph2;
                                if(strpos($article, $ph1) !== false){
                                    $phrase_1 = $ph2;
                                    $phrase_2 = $ph2;
                                    $phrase_3 = $ph2;
                                }
                            } else {
                                $phrase_1 = ltrim($exp_color[0]);
                            }
                        }
                        if (empty($color)) {
                            $check_pid1 = DB::table('products')->select('id', 'psc_id')
                            ->where('br_id', '=', $br_id)
                            ->where('p_name', 'LIKE', '%'.$article.'%')
                            ->where('p_delete', '!=', '1')->get()->first();
                        } else {
                            $check_pid1 = DB::table('products')->select('id', 'psc_id')
                            ->where('br_id', '=', $br_id)
                            ->where('p_name', 'LIKE', '%'.$article.'%')
                            ->where('p_color', 'LIKE', '%'.ltrim($phrase_1).'%')
                            ->where('p_delete', '!=', '1')->get()->first();
                            $check_pid2 = DB::table('products')->select('id', 'psc_id')
                            ->where('br_id', '=', $br_id)
                            ->where('p_name', 'LIKE', '%'.$article.'%')
                            ->where('p_color', 'LIKE', '%'.ltrim($phrase_2).'%')
                            ->where('p_delete', '!=', '1')->get()->first();
                            $check_pid3 = DB::table('products')->select('id', 'psc_id')
                            ->where('br_id', '=', $br_id)
                            ->where('p_name', 'LIKE', '%'.$article.'%')
                            ->where('p_color', 'LIKE', '%'.ltrim($phrase_3).'%')
                            ->where('p_delete', '!=', '1')->get()->first();
                            $check_pid4 = DB::table('products')->select('id', 'psc_id')
                            ->where('br_id', '=', $br_id)
                            ->where('p_name', 'LIKE', '%'.$article.'%')
                            ->where('p_color', 'LIKE', '%'.ltrim($phrase_4).'%')
                            ->where('p_delete', '!=', '1')->get()->first();
                        }
                        if (!empty($check_pid1)) {
                            $p_id = $check_pid1->id;
                            $psc_id = $check_pid1->psc_id;
                        } else if (!empty($check_pid2)) {
                            $p_id = $check_pid2->id;
                            $psc_id = $check_pid2->psc_id;
                        } else if (!empty($check_pid3)) {
                            $p_id = $check_pid3->id;
                            $psc_id = $check_pid3->psc_id;
                        } else if (!empty($check_pid4)) {
                            $p_id = $check_pid4->id;
                            $psc_id = $check_pid4->psc_id;
                        } else {
                            $ck_pid = DB::table('products')->select('id', 'psc_id')
                            ->where('br_id', '=', $br_id)
                            ->where('p_name', 'LIKE', '%'.$article.'%')
                            ->where('p_delete', '!=', '1')->get()->first();
                            if (!empty($ck_pid)) {
                                $p_id = $ck_pid->id;
                                $psc_id = $ck_pid->psc_id;
                            }
                        }
                        $exp_size_color = explode(',', $nama_variasi);
                        $total_exp_size_color = count($exp_size_color);
                        if ($total_exp_size_color > 1) {
                            $size = ltrim($exp_size_color[1]);
                            $check_size = DB::table('sizes')->select('id')
                            ->where('sz_delete', '!=', '1')->where('sz_name', '=', $size)
                            ->where('psc_id', '=', $psc_id)->get()->first();
                            if (!empty($check_size)) {
                                $sz_id = $check_size->id;
                            }
                        } else {
                            $size = ltrim($exp_size_color[0]);
                            $check_size = DB::table('sizes')->select('id')
                            ->where('sz_delete', '!=', '1')->where('sz_name', '=', $size)
                            ->where('psc_id', '=', $psc_id)->get()->first();
                            if (!empty($check_size)) {
                                $sz_id = $check_size->id;
                            }
                        }
                        $check_pst = DB::table('product_stocks')->select('id')->where([
                            'p_id' => $p_id,
                            'sz_id' => $sz_id
                        ])
                        ->where('ps_delete', '!=', '1')->get()->first();
                        if (!empty($check_pst)) {
                            $pst_id = $check_pst->id;
                            break;
                        }
                    }
                    $x ++;
                }
            }
            if (!empty($pst_id)) {
                $exception = ExceptionLocation::select('pl_code')
                ->leftJoin('product_locations', 'product_locations.id', '=', 'exception_locations.pl_id')->get()->toArray();
                if ($this->storage == 'all') {
                    $system_stock = DB::table('product_location_setups')->select('pls_qty')
                    ->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
                    ->whereNotIn('product_locations.pl_code', $exception)
                    ->where('product_location_setups.pst_id', '=', $pst_id)->sum('pls_qty');
                } else {
                    $system_stock = DB::table('product_location_setups')->select('pls_qty')
                    ->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
                    ->whereNotIn('product_locations.pl_code', $exception)
                    ->where('product_locations.st_id', '=', $this->storage)
                    ->where('product_location_setups.pst_id', '=', $pst_id)->sum('pls_qty');
                }
            }
            $check_kode_produk = DB::table('shopee_data')
            ->where('kode_produk', '=', $kode_produk)
            ->where('kode_variasi', '=', $kode_variasi)->get()->first();
            if (!empty($check_kode_produk)) {
                $shopee_id = DB::table('shopee_data')->where('id', '=', $check_kode_produk->id)
                ->update([
                    'pst_id' => $pst_id,
                    'stok' => $stok,
                    'system_stock' => $system_stock,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            } else {
                $shopee_id = DB::table('shopee_data')->insertGetId([
                    'pst_id' => $pst_id,
                    'tg_id' => $this->tg_id,
                    'template_row' => $template_row,
                    'kode_produk' => $kode_produk,
                    'nama_produk' => $nama_produk,
                    'kode_variasi' => $kode_variasi,
                    'nama_variasi' => $nama_variasi,
                    'min_buy' => $min_buy,
                    'sku' => $sku,
                    'price' => $price,
                    'stok' => $stok,
                    'system_stock' => $system_stock,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $dt_rows ++;
            }
        }
        return '200';
    }

    public function getRowCount(): int
    {
        return $this->rows;
    }
}
