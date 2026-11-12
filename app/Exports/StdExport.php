<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Facades\DB;

class StdExport implements FromCollection , withHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */
    protected $start;
    protected $end;

    function __construct($start, $end)
    {
        $this->start = $start;
        $this->end = $end;
    }

    public function headings(): array
    {
        return ["Tanggal Dibuat", "Tanggal Terima", "Kode", "Pengirim", "Store Asal", "Store Tujuan", "Penerima", "Brand", "Artikel", "Warna", "Size", "Qty", "Qty Terima", "HPP", "Harga Jual"];
    }

    public function collection()
    {
        $export = array();
        $data = DB::table('stock_transfer_details')
                    ->selectRaw("ts_stock_transfers.created_at as created_at, ts_stock_transfer_details.pst_id as pst_id, ts_stock_transfer_detail_statuses.created_at as created_at_receive, stf_code, ts_stock_transfers.u_id, st_id_start, st_id_end, u_id_receive, br_name, p_name, p_color, sz_name, stfd_qty, sum(ts_stock_transfer_detail_statuses.stfds_qty) as qty_receive")
                    ->leftJoin('stock_transfers', 'stock_transfers.id', '=', 'stock_transfer_details.stf_id')
                    ->leftJoin('stock_transfer_detail_statuses', 'stock_transfer_detail_statuses.stfd_id', '=', 'stock_transfer_details.id')
                    ->leftJoin('product_stocks', 'product_stocks.id', '=', 'stock_transfer_details.pst_id')
                    ->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
                    ->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
                    ->leftJoin('brands', 'brands.id', '=', 'products.br_id')
                    ->whereDate('stock_transfers.created_at', '>=', $this->start)
                    ->whereDate('stock_transfers.created_at', '<=', $this->end)
                    ->groupBy('stock_transfer_details.id')
                    ->get()->toArray();
        if (!empty($data)) {
            foreach ($data as $row) {
                $poads = DB::table('purchase_order_article_detail_statuses')
                ->select('poads_purchase_price', 'ps_purchase_price')
                ->leftJoin('purchase_order_article_details', 'purchase_order_article_details.id', '=', 'purchase_order_article_detail_statuses.poad_id')
                ->leftJoin('product_stocks', 'product_stocks.id', '=', 'purchase_order_article_details.pst_id')
                ->where('product_stocks.id', '=', $row->pst_id)
                ->whereNotNull('purchase_order_article_detail_statuses.poad_id')
                ->orderByDesc('purchase_order_article_detail_statuses.id')
                ->groupBy('poads_purchase_price')
                ->get()->first();
                if (!empty($poads)) {
                    if (!empty($poads->poads_purchase_price)) {
                    $hpp = $poads->poads_purchase_price;
                    } else {
                    $hpp = $poads->ps_purchase_price;
                    }
                }

                $hj = DB::table('product_stocks')->select('ps_price_tag', 'p_price_tag', 'ps_sell_price', 'p_sell_price')
                ->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
                ->where('product_stocks.id', '=', $row->pst_id)
                ->get()->first();
                if (!empty($hj->ps_sell_price)) {
                    $hj = $hj->ps_sell_price;
                } else {
                    $hj = $hj->p_sell_price;
                }

                $sender = DB::table('users')->select('u_name')->where('id', '=', $row->u_id)->get()->first()->u_name;
                $receiver = DB::table('users')->select('u_name')->where('id', '=', $row->u_id_receive)->get()->first();
                if (!empty($receiver)) {
                    $receiver = $receiver->u_name;
                } else {
                    $receiver = '-';
                }
                $store_sender = DB::table('stores')->select('st_name')->where('id', '=', $row->st_id_start)->get()->first()->st_name;
                $store_receiver = DB::table('stores')->select('st_name')->where('id', '=', $row->st_id_end)->get()->first()->st_name;
                $export[] = [date('d/m/Y H:i:s', strtotime($row->created_at)), date('d/m/Y H:i:s', strtotime($row->created_at_receive)), $row->stf_code, $sender, $store_sender, $store_receiver, $receiver, $row->br_name, $row->p_name, $row->p_color, $row->sz_name, $row->stfd_qty, $row->qty_receive, $hpp, $hj];
            }
        }
        return collect($export);
    }
}
