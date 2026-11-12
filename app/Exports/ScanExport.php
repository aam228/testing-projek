<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Facades\DB;
use App\Models\ExceptionLocation;

class ScanExport implements FromCollection , withHeadings
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
        return ["Tanggal Jam", "BIN", "USER", "BRAND", "ARTIKEL", "WARNA", "SIZE", "SUB KATEGORI", "BARCODE", "Harga Beli", "Harga Jual", "QTY SYSTEM", "QTY SO", "Type", "Diff"];
    }

    public function collection()
    {
        $export = array();
        $data = DB::table('scan_adjustment_details')->selectRaw("ts_product_location_setups.id as id, u_name, pl_code, br_name, p_name, p_color, sz_name, psc_name,
        pls_qty, avg(ts_purchase_order_article_details.poad_purchase_price) as purchase_2, avg(ts_purchase_order_article_detail_statuses.poads_purchase_price) as purchase_1, ps_sell_price, p_sell_price, ps_purchase_price, p_purchase_price,
        qty, qty_so, mad_type, mad_diff, ps_barcode, p_code, ts_scan_adjustment_details.created_at")
        ->leftJoin('product_location_setups', 'product_location_setups.id', '=', 'scan_adjustment_details.pls_id')
        ->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
        ->leftJoin('product_stocks', 'product_stocks.id', '=', 'product_location_setups.pst_id')
        ->leftJoin('purchase_order_article_details', 'purchase_order_article_details.pst_id', '=', 'product_stocks.id')
        ->leftJoin('purchase_order_article_detail_statuses', 'purchase_order_article_detail_statuses.poad_id', '=', 'purchase_order_article_details.id')
        ->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
        ->leftJoin('brands', 'brands.id', '=', 'products.br_id')
        ->leftJoin('product_sub_categories', 'product_sub_categories.id', '=', 'products.psc_id')
        ->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
        ->leftJoin('users', 'users.id', '=', 'scan_adjustment_details.u_id')
        ->where('scan_adjustment_details.sa_id', '=', $this->id)
        ->groupBy('scan_adjustment_details.id')
        ->orderBy('scan_adjustment_details.created_at')
        ->get();
        if (!empty($data->first())) {
            foreach ($data as $row) {
                $qty_export = $row->qty;
                if (empty($qty_export)) {
                    $qty_export = '0';
                }
                $qty_so = $row->qty_so;
                if (empty($qty_so)) {
                    $qty_so = '0';
                }
                if ($qty_so > $qty_export) {
                    $diff = ($qty_so - $qty_export);
                    $type = '+';
                } else if ($qty_so < $qty_export) {
                    $diff = ($qty_export - $qty_so);
                    $type = '-';
                } else {
                    $diff = '0';
                    $type = '=';
                }
                $barcode = $row->ps_barcode;
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
                $export[] = [date('d/m/Y H:i:s', strtotime($row->created_at)), $row->pl_code, $row->u_name, $row->br_name, $row->p_name, $row->p_color, $row->sz_name, $row->psc_name, $barcode, $purchase, $sell, $qty_export, $qty_so, $type, $diff];
            }
        }
        return collect($export);
    }
}
