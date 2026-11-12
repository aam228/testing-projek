<?php

namespace App\Imports;

use App\Models\DebtList;
use App\Models\ProductSupplier;
use App\Models\Brand;
use App\Models\Store;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Illuminate\Support\Facades\DB;
use Carbon;

class DebtListImport implements ToCollection, WithStartRow
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

    public function collection(Collection $row)
    {
        ++$this->rows;
        $data_id = array();
        $status = 0;
        foreach ($row as $r) {
            if ($r[0] == null) {
                return null;
            }
            $ps_id = null;
            $br_id = null;
            $st_id = null;

            $product_supplier = ProductSupplier::where('ps_name', '=', $r[0]);
            if (!empty($product_supplier->first()->id)) {
                $ps_id = $product_supplier->first()->id;
                $status += 1;
            } else {
                $this->rows = -1;
                $status = -1;
                break;
            }
            $brand = Brand::where('br_name', '=', $r[1]);
            if (!empty($brand->first()->id)) {
                $br_id = $brand->first()->id;
                $status += 1;
            } else {
                $this->rows = -1;
                $status = -1;
                break;
            }
            $store = Store::where('st_name', '=', $r[8]);
            if (!empty($store->first()->id)) {
                $st_id = $store->first()->id;
                $status += 1;
            } else {
                $this->rows = -1;
                $status = -1;
                break;
            }

            $dl_id = DB::table('debt_lists')->insertGetId([
                'ps_id' => $ps_id,
                'br_id' => $br_id,
                'st_id' => $st_id,
                'dl_invoice' => $r[2],
                'dl_invoice_date' => Carbon\Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($r[3])),
                'dl_invoice_due_date' => Carbon\Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($r[4])),
                'dl_value' => $r[5],
                'dl_vat' => $r[6],
                'dl_total' => $r[7],
                'dl_delete' => '0',
            ]);

            $data_id[] = $dl_id;
        }
        if ($status >= 0) {
            return '200'; 
        } else {
            DB::table('debt_list_payments')->whereIn('dl_id', $data_id)->delete();
            DB::table('debt_lists')->whereIn('id', $data_id)->delete();
            $this->rows = -1;
            return '400';
        }
    }

    public function getRowCount(): int
    {
        return $this->rows;
    }
}
