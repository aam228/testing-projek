<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Facades\DB;
use App\Models\ExceptionLocation;

class MassExport implements FromCollection , withHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */

    protected $st_id;
    protected $psc_id;
    protected $br_id;
    protected $pl_id;
    protected $qty_filter;

    function __construct($st_id, $psc_id, $br_id, $pl_id, $qty_filter)
    {
        $this->st_id = $st_id;
        $this->psc_id = $psc_id;
        $this->br_id = $br_id;
        $this->pl_id = $pl_id;
        $this->qty_filter = $qty_filter;
    }

    public function headings(): array
    {
        return ["Identify", "BIN", "BRAND", "ARTIKEL", "WARNA", "SIZE", "SUB KATEGORI", "Harga Beli", "Harga Jual", "QTY SYSTEM", "QTY SO"];
    }

    public function collection()
    {
        $export = array();
        $exception = ExceptionLocation::select('pl_code')
            ->leftJoin('product_locations', 'product_locations.id', '=', 'exception_locations.pl_id')
            ->get()
            ->toArray();
        $data = DB::table('product_location_setups')->selectRaw("ts_product_location_setups.id as id, pl_code, br_name, p_name, p_color, sz_name, psc_name,
        pls_qty, avg(ts_purchase_order_article_details.poad_purchase_price) as purchase_2, avg(ts_purchase_order_article_detail_statuses.poads_purchase_price) as purchase_1, ps_sell_price, p_sell_price, ps_purchase_price, p_purchase_price")
        ->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
        ->leftJoin('product_stocks', 'product_stocks.id', '=', 'product_location_setups.pst_id')
        ->leftJoin('purchase_order_article_details', 'purchase_order_article_details.pst_id', '=', 'product_stocks.id')
        ->leftJoin('purchase_order_article_detail_statuses', 'purchase_order_article_detail_statuses.poad_id', '=', 'purchase_order_article_details.id')
        ->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
        ->leftJoin('brands', 'brands.id', '=', 'products.br_id')
        ->leftJoin('product_sub_categories', 'product_sub_categories.id', '=', 'products.psc_id')
        ->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
        ->where(function($w) use ($exception) {
            $st_id = $this->st_id;
            $psc_id = $this->psc_id;
            $br_id = $this->br_id;
            $pl_id = $this->pl_id;
            $qty_filter = $this->qty_filter;
            $w->whereNotIn('product_locations.pl_code', $exception);
            if ($st_id != 'all') {
                $w->where('product_locations.st_id', $st_id);
            }
            if ($psc_id != 'all') {
                $w->where('products.psc_id', $psc_id);
            }
            if ($br_id != 'all') {
                $w->where('products.br_id', $br_id);
            }
            if (!empty($pl_id)) {
                $code = array();
                $exp = explode(',', $pl_id);
                for ($i = 0; $i < count($exp); $i++) {
                    $code[] = [$exp[$i]];
                }
                $w->whereIn('product_locations.id', $code);
            }
            if ($qty_filter == '1') {
                $w->where('product_location_setups.pls_qty', '>', '0');
            }
        })
        ->groupBy('product_location_setups.id')
        ->get();
        if (!empty($data->first())) {
            foreach ($data as $row) {
                $stock = $row->pls_qty;
                if (empty($stock)) {
                    $stock = '0';
                }
                $purchase = null;
                if (!empty($row->purchase_1)) {
                    $purchase = $row->purchase_1;
                } else if (!empty($row->purchase_2)) {
                    $purchase = $row->purchase_2;
                } else if (!empty($row->ps_purchase_price)) {
                    $purchase = $row->ps_purchase_price;
                } else {
                    $purchase = $row->p_purchase_price;
                }
                $sell = null;
                if (!empty($row->ps_sell_price)) {
                    $sell = $row->ps_sell_price;
                } else {
                    $sell = $row->p_sell_price;
                }
                $export[] = [$row->id, $row->pl_code, $row->br_name, $row->p_name, $row->p_color, $row->sz_name, $row->psc_name, $purchase, $sell, $stock, ""];
            }
        }
        return collect($export);
    }
}
