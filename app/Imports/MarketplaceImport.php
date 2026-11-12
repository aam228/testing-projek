<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use App\Models\ExceptionLocation;

class MarketplaceImport implements ToCollection, WithStartRow
{
    private $rows = 0;
    protected $column;
    protected $row;
    protected $st_id;
    protected $results;
     /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */

    function __construct($column, $row, $st_id)
    {
        $this->column = $column-1;
        $this->row = $row;
        $this->st_id = $st_id;
    }

    public function startRow(): int
    {
        return $this->row;
    }

    public function collection(Collection $collection)
    {
        ++$this->rows;
        $exception = ExceptionLocation::select('pl_code')
        ->leftJoin('product_locations', 'product_locations.id', '=', 'exception_locations.pl_id')->get()->toArray();

        $export = array();
        $row = $this->row;
        foreach ($collection as $r) {
            if (empty($r[$this->column])) {
                continue;
            }
            $code = $r[$this->column];
            $article = 'kode marketplace tidak ditemukan';
            $stock = 0;
            $check = DB::table('marketplace_managers')->select('pst_id')->where('marketplace_code', '=', $code)
            ->first();
            if (!empty($check)) {
                $check2 = DB::table('product_stocks')->selectRaw("br_name, p_name, p_color, sz_name, sum(ts_product_location_setups.pls_qty) as stock")
                ->leftJoin('product_location_setups', 'product_location_setups.pst_id', '=', 'product_stocks.id')
                ->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
                ->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
                ->leftJoin('brands', 'brands.id', '=', 'products.br_id')
                ->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
                ->where('product_stocks.id', '=', $check->pst_id)
                ->where('product_locations.st_id', '=', $this->st_id)
                ->whereNotIn('product_locations.pl_code', $exception)
                ->groupBy('product_stocks.id')
                ->first();
                if (!empty($check2)) {
                    $article = $check2->br_name.' '.$check2->p_name.' '.$check2->p_color.' '.$check2->sz_name;
                    $stock = $check2->stock;
                }
            }
            $export[] = [$row, $code, $article, $stock];
            $row ++;
        }
        $this->results = $export;
    }

    public function getData()
    {
        return $this->results;
    }
}
