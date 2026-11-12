<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Facades\DB;
use App\Models\ExceptionLocation;

class ScanBINExport implements FromCollection, withHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */
    protected $id;

    function __construct($id)
    {
        $this->id = $id;
    }

    public function headings(): array
    {
        return ["BIN", "BRAND", "ARTIKEL", "WARNA", "SIZE", "BARCODE", "SUB KATEGORI", "Harga Beli", "Harga Jual", "QTY SYSTEM", "QTY SO"];
    }

    public function collection()
    {
        $export = array();
        $br_id = array();
        $sab = DB::table('scan_adjustment_brands')->where('sa_id', '=', $this->id)->get();
        if (!empty($sab->first())) {
            foreach ($sab as $row) {
                $br_id[] = [$row->br_id];
            }
        }
        $psc_id = array();
        $sab = DB::table('scan_adjustment_sub_categories')->where('sa_id', '=', $this->id)->get();
        if (!empty($sab->first())) {
            foreach ($sab as $row) {
                $psc_id[] = [$row->psc_id];
            }
        }

        $data = DB::table('product_location_setups')->selectRaw("ts_product_location_setups.id as id, pl_code, br_name, p_name, p_color, sz_name, psc_name, ps_barcode,
        pls_qty, avg(ts_purchase_order_article_details.poad_purchase_price) as purchase_2, avg(ts_purchase_order_article_detail_statuses.poads_purchase_price) as purchase_1, ps_sell_price, p_sell_price, ps_purchase_price, p_purchase_price")
        ->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
        ->leftJoin('product_stocks', 'product_stocks.id', '=', 'product_location_setups.pst_id')
        ->leftJoin('purchase_order_article_details', 'purchase_order_article_details.pst_id', '=', 'product_stocks.id')
        ->leftJoin('purchase_order_article_detail_statuses', 'purchase_order_article_detail_statuses.poad_id', '=', 'purchase_order_article_details.id')
        ->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
        ->leftJoin('brands', 'brands.id', '=', 'products.br_id')
        ->leftJoin('product_sub_categories', 'product_sub_categories.id', '=', 'products.psc_id')
        ->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
        ->where(function($w) use ($br_id, $psc_id) {
            if (count($psc_id) > 0) {
                $w->whereIn('products.psc_id', $psc_id);
            }
            if (count($br_id) > 0) {
                $w->whereIn('products.br_id', $br_id);
            }
            $w->where('product_location_setups.pls_qty', '>', '0');
        })
        ->groupBy('product_location_setups.id')
        ->orderBy('pl_code')
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
                $export[] = [$row->pl_code, $row->br_name, $row->p_name, $row->p_color, $row->sz_name, $row->ps_barcode, $row->psc_name, $purchase, $sell, $stock, "0"];
            }
        }
        return collect($export);
    }
}
