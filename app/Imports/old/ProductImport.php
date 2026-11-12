<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\Brand;
use App\Models\MainColor;
use App\Models\ProductSupplier;
use App\Models\ProductUnit;
use App\Models\ProductStock;
use App\Models\Gender;
use App\Models\Season;
use App\Models\Size;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Illuminate\Support\Facades\DB;

class ProductImport implements ToCollection, WithStartRow
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    private $pc_id, $psc_id, $pssc_id;

    public function __construct(array $get_id)
    {
        $this->pc_id = $get_id['pc_id'];
        $this->psc_id = $get_id['psc_id'];
        $this->pssc_id = $get_id['pssc_id'];
    }

    public function startRow(): int
    {
        return 2;
    }

    public function generateRunningCode()
    {
        $number = mt_rand(1000000000000, 9999999999999);
        if ($this->runningCodeExists($number)) {
            return generateRunningCode();
        }
        return $number;
    }

    public function runningCodeExists($number) {
        return ProductStock::where(['ps_running_code' => $number])->exists();
    }

    public function collection(Collection $row)
    {
        //dd($row);
        foreach ($row as $r) {
            if ($r[0] == null) {
                return null;
            }
            $br_id = null;
            $ps_id = null;
            $pu_id = null;
            $gn_id = null;
            $ss_id = null;
            $mc_id = null;
            $brand = Brand::where('br_name', 'LIKE', '%'.$r[1].'%');
            if (!empty($brand->first()->id)) {
                $br_id = $brand->first()->id;
            } else {
                dd($br_id);
                return false;
            }
            $product_supplier = ProductSupplier::where('ps_name', 'LIKE', '%'.$r[2].'%');
            if (!empty($product_supplier->first()->id)) {
                $ps_id = $product_supplier->first()->id;
            } else {
                dd($ps_id);
                return false;
            }
            $product_unit = ProductUnit::where('pu_name', 'LIKE', '%'.$r[3].'%');
            if (!empty($product_unit->first()->id)) {
                $pu_id = $product_unit->first()->id;
            } else {
                dd($pu_id);
                return false;
            }
            $gender = Gender::where('gn_name', 'LIKE', '%'.$r[4].'%');
            if (!empty($gender->first()->id)) {
                $gn_id = $gender->first()->id;
            } else {
                dd($gn_id);
                return false;
            }
            $season = Season::where('ss_name', 'LIKE', '%'.$r[5].'%');
            if (!empty($season->first()->id)) {
                $ss_id = $season->first()->id;
            } else {
                dd($ss_id);
                return false;
            }
            $main_color = MainColor::where('mc_name', 'LIKE', '%'.$r[7].'%');
            if (!empty($main_color->first()->id)) {
                $mc_id = $main_color->first()->id;
            } else {
                dd($mc_id);
                return false;
            }

            $p_id = DB::table('products')->insertGetId([
                'br_id' => $br_id,
                'pc_id' => $this->pc_id,
                'psc_id' => $this->psc_id,
                'pssc_id' => $this->pssc_id,
                'mc_id' => $mc_id,
                'ps_id' => $ps_id,
                'pu_id' => $pu_id,
                'gn_id' => $gn_id,
                'ss_id' => $ss_id,
                'p_name' => $r[0],
                'p_aging' => $r[6],
                'p_color' => $r[8],
                'p_price_tag' => $r[9],
                'p_purchase_price' => $r[10],
                'p_sell_price' => $r[11],
                'p_delete' => '0',
            ]);

            if (!empty($p_id)) {
                $exp = explode(',', $r[12]);
                $count = (Integer)count($exp);
                for ($i=0; $i<=$count; $i++) {
                    if (empty($exp[$i])) {
                        continue;
                    }
                    $exp_ps = explode('-', $exp[$i]);
                    $size = Size::where(['sz_name' => $exp_ps[0]]);
                    if (!empty($size->first()->id)) {
                        $sz_id = $size->first()->id;
                    } else {
                        continue;
                    }
                    ProductStock::create([
                        'p_id' => $p_id,
                        'sz_id' => $sz_id,
                        'ps_qty' => '0',
                        'ps_barcode' => $exp_ps[1],
                        'ps_running_code' => $this->generateRunningCode()
                    ]);
                }
            } else {
                break;
                return false;
            }
        }
    }
}
