<?php

namespace App\Imports;

use App\Models\ProductDiscountDetail;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Size;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Illuminate\Support\Facades\DB;

class DiscountImport implements ToCollection, WithStartRow
{
    private $rows = 0;
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */

    public function startRow(): int
    {
        return 2;
    }

    public function generateRunningCode()
    {
        $check = ProductStock::select('ps_running_code')->orderByDesc('ps_running_code')->limit(1)->get()->first();
        if (!empty($check)) {
            $current_running_code = $check->ps_running_code;
            $next_running_code = $current_running_code + 1;
            $running_length = strlen($next_running_code);
            $new_running_code = '';
            if ($running_length == 1) {
                $new_running_code = '000000000000'.$next_running_code;
            } else if ($running_length == 2) {
                $new_running_code = '00000000000'.$next_running_code;
            } else if ($running_length == 3) {
                $new_running_code = '0000000000'.$next_running_code;
            } else if ($running_length == 4) {
                $new_running_code = '000000000'.$next_running_code;
            } else if ($running_length == 5) {
                $new_running_code = '00000000'.$next_running_code;
            } else if ($running_length == 6) {
                $new_running_code = '0000000'.$next_running_code;
            } else if ($running_length == 7) {
                $new_running_code = '000000'.$next_running_code;
            } else if ($running_length == 8) {
                $new_running_code = '00000'.$next_running_code;
            } else if ($running_length == 9) {
                $new_running_code = '0000'.$next_running_code;
            } else if ($running_length == 10) {
                $new_running_code = '000'.$next_running_code;
            } else if ($running_length == 11) {
                $new_running_code = '00'.$next_running_code;
            } else if ($running_length == 12) {
                $new_running_code = '0'.$next_running_code;
            } else if ($running_length == 13) {
                $new_running_code = $next_running_code;
            }
        } else {
            $new_running_code = '0000000000001';
        }

        if ($this->runningCodeExists($new_running_code)) {
            return generateRunningCode();
        }
        return $new_running_code;
    }

    public function runningCodeExists($number) {
        return ProductStock::where(['ps_running_code' => $number])->exists();
    }

    public function collection(Collection $row)
    {
        ++$this->rows;
        $data_id = array();
        $error = array();
        $status = 0;
        foreach ($row as $r) {
            if ($r[0] == null) {
                return null;
            }
            $pd_id = $r[0];
            $br_id = null;
            $p_id = null;
            $sz_id = null;
            $pst_id = null;

            $brand = Brand::where('br_name', '=', ltrim($r[1]));
            if (!empty($brand->first()->id)) {
                $br_id = $brand->first()->id;
                $status += 1;
            } else {
                $error[] = [
                    'brand' => $r[1]
                ];
                $this->rows = -1;
                $status = -1;
                break;
            }
            $product = Product::where('p_name', '=', ltrim($r[2]))
            ->where('p_color', '=', $r[3])
            ->where('br_id', '=', $br_id);
            if (!empty($product->first()->id)) {
                $p_id = $product->first()->id;
                $status += 1;
            } else {
                $error[] = [
                    'brand' => $r[1],
                    'article' => $r[2]
                ];
                $this->rows = -1;
                $status = -1;
                break;
            }
            $size = Size::where('sz_name', '=', ltrim($r[4]))
            ->where('psc_id', '=', $product->first()->psc_id);
            if (!empty($size->first()->id)) {
                $sz_id = $size->first()->id;
                $status += 1;
            } else {
                $error[] = [
                    'brand' => $r[1],
                    'article' => $r[2],
                    'size' => $r[4],
                ];
                $this->rows = -1;
                $status = -1;
                break;
            }

            $product_stock = ProductStock::where('p_id', '=', $p_id)->where('sz_id', '=', $sz_id);
            if (!empty($product_stock->first()->id)) {
                $pst_id = $product_stock->first()->id;
                $status += 1;
            } else {
                $in = [
                    'p_id' => $p_id, 
                    'sz_id' => $sz_id,
                    'ps_qty' => '0',
                    'ps_delete' => '0',
                    'ps_running_code' => $this->generateRunningCode(),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
                $inst = DB::table('product_stocks')->insertGetId($in);
                if (empty($inst)) {
                    $error[] = [
                        'brand' => $r[1],
                        'article' => $r[2],
                        'size' => $r[4],
                        'p_id' => $p_id,
                    ];
                    $this->rows = -1;
                    $status = -1;
                    break;
                } else {
                    $pst_id = $inst;
                    $status += 1;
                }
            }

            $pdd_id = DB::table('product_discount_details')->insertGetId([
                'pd_id' => $pd_id,
                'pst_id' => $pst_id,
                'pdd_type' => '1',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $data_id[] = $pdd_id;
        }
        if ($status >= 0) {
            return '200';
        } else {
            DB::table('product_discount_details')->whereIn('id', $data_id)->delete();
            dd($error);
            $this->rows = -1;
            return '400';
        }
    }

    public function getRowCount(): int
    {
        return $this->rows;
    }
}
