<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Facades\DB;

class ArticleReportExport implements FromCollection , withHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */
    protected $start;
    protected $end;
    protected $stt_id;
    protected $st_id;

    function __construct($start, $end, $stt_id, $st_id)
    {
        $this->start = $start;
        $this->end = $end;
        $this->stt_id = $stt_id;
        $this->st_id = $st_id;
    }

    public function headings(): array
    {
        return ["Tanggal", "Invoice", "Cross", "Customer", "Kasir", "Divisi", "Tipe Stok", "Brand", "Artikel", "Warna", "Size", "Kategori", "Sub Kategori", "Sub Sub Kategori", "Qty", "Bandrol", "Harga Beli", "Harga Jual", "Discount", "Total Price", "Total Invoice"];
    }

    public function collection()
    {
        $export = array();
        $data = DB::table('pos_transaction_details')->select('pos_transaction_details.id as ptd_id', 'pos_transaction_details.created_at as ptd_created', 'pos_transaction_details.pst_id as pst_id', 'pos_invoice', 'cross_order', 'u_name',
                'dv_name', 'br_name', 'pc_name', 'psc_name', 'pssc_name', 'cust_name', 'p_name', 'p_color', 'pos_td_sell_price', 'sz_name', 'pos_td_qty', 'stkt_name', 'ps_price_tag', 'p_price_tag', 'std_id', 'ps_sell_price', 'p_sell_price', 'pos_td_discount_price', 'pos_td_marketplace_price', 'pos_status', 'pos_refund', 'pt_id')
                ->leftJoin('pos_transactions', 'pos_transactions.id', '=', 'pos_transaction_details.pt_id')
                ->leftJoin('customers', 'customers.id', '=', 'pos_transactions.cust_id')
                ->leftJoin('store_type_divisions', 'store_type_divisions.id', '=', 'pos_transactions.std_id')
                ->leftJoin('users', 'users.id', '=', 'pos_transactions.u_id')
                ->leftJoin('product_stocks', 'product_stocks.id', '=', 'pos_transaction_details.pst_id')
                ->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
                ->leftJoin('product_categories', 'products.pc_id', '=', 'product_categories.id')
                ->leftJoin('product_sub_categories', 'products.psc_id', '=', 'product_sub_categories.id')
                ->leftJoin('product_sub_sub_categories', 'products.pssc_id', '=', 'product_sub_sub_categories.id')
                ->leftJoin('brands', 'brands.id', '=', 'products.br_id')
                ->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
                ->leftJoin('purchase_order_article_details', 'purchase_order_article_details.pst_id', '=', 'product_stocks.id')
                ->leftJoin('purchase_order_article_detail_statuses', 'purchase_order_article_detail_statuses.poad_id', '=', 'purchase_order_article_details.id')
                ->leftJoin('stock_types', 'purchase_order_article_detail_statuses.stkt_id', '=', 'stock_types.id')
                ->whereNotIn('pos_status', ['WAITING FOR CONFIRMATION', 'CANCEL'])
                ->where(function($w) {
                    if (!empty($this->st_id)) {
                        $w->where('pos_transactions.st_id', '=', $this->st_id);
                    }
                    if (!empty($this->stt_id)) {
                        $w->where('pos_transactions.stt_id', '=', $this->stt_id);
                    }
                    if (!empty($this->end)) {
                        $w->whereDate('pos_transaction_details.created_at', '>=', $this->start)
                        ->whereDate('pos_transaction_details.created_at', '<=', $this->end);
                    } else {
                        $w->whereDate('pos_transaction_details.created_at', '=', $this->start);
                    }
                })
                ->orderBy('pos_transaction_details.created_at')
                ->groupBy('pos_transaction_details.id')->get()->toArray();
        if (!empty($data)) {
            foreach ($data as $row) {
                if (!empty($row->ps_price_tag)) {
                    $bandrol = $row->ps_price_tag;
                } else {
                    $bandrol = $row->p_price_tag;
                }

                $total = $row->pos_td_qty * $row->pos_td_sell_price;
                if (!empty($row->pos_td_marketplace_price)) {
                    $total_price = DB::table('pos_transaction_details')->where('pt_id', '=', $row->pt_id)->sum('pos_td_marketplace_price');
                } else {
                    $total_price = DB::table('pos_transaction_details')->where('pt_id', '=', $row->pt_id)->sum('pos_td_discount_price');
                }

                $discount = DB::table('product_discount_details')->select('pd_type', 'pd_value')
                ->leftJoin('product_discounts', 'product_discounts.id', '=', 'product_discount_details.pd_id')
                ->where('product_discount_details.pst_id', '=', $row->pst_id)
                ->where('product_discounts.std_id', '=', $row->std_id)
                ->get()->first();
                if (!empty($discount)) {
                  if ($discount->pd_type == 'percent') {
                    $discount = $discount->pd_value.' %';
                  } else {
                    $discount = $discount->pd_value;
                  }
                } else {
                    $discount = '-';
                }

                $poads = DB::table('purchase_order_article_detail_statuses')
                ->select('poads_purchase_price', 'ps_purchase_price')
                ->leftJoin('purchase_order_article_details', 'purchase_order_article_details.id', '=', 'purchase_order_article_detail_statuses.poad_id')
                ->leftJoin('product_stocks', 'product_stocks.id', '=', 'purchase_order_article_details.pst_id')
                ->where('product_stocks.id', '=', $row->pst_id)
                ->whereNotNull('purchase_order_article_detail_statuses.poad_id')
                ->groupBy('poads_purchase_price')
                ->get()->first();
                if (!empty($poads->poads_purchase_price)) {
                    $hpp = $poads->poads_purchase_price;
                } else if (!empty($poads->ps_purchase_price)) {
                    $hpp = $poads->ps_purchase_price;
                } else {
                    $hpp = '-';
                }
                $export[] = [date('d/m/Y H:i:s', strtotime($row->ptd_created)), $row->pos_invoice, $row->cross_order, $row->cust_name, $row->u_name, $row->dv_name, $row->stkt_name, $row->br_name, $row->p_name, $row->p_color, $row->sz_name, $row->pc_name, $row->psc_name, $row->pssc_name, $row->pos_td_qty, $bandrol, $hpp, $row->pos_td_sell_price, $discount, $total, $total_price];
            }
        }
        return collect($export);
    }
}
