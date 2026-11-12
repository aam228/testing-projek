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
    protected $type;
    protected $start;
    protected $end;
    protected $stt_id;
    protected $st_id;

    function __construct($type, $start, $end, $stt_id, $st_id)
    {
        $this->start = $start;
        $this->end = $end;
        $this->stt_id = $stt_id;
        $this->st_id = $st_id;
        $this->type = $type;
    }

    public function headings(): array
    {
        if ($this->type == 'article' || $this->type == 'cross') {
            return ["Tanggal", "Invoice", "Cross", "Customer", "Kasir", "Divisi", "Tipe Stok", "Brand", "Artikel", "Warna", "Size", "Kategori", "Sub Kategori", "Sub Sub Kategori", "Qty", "Bandrol", "Harga Beli", "Harga Jual", "Discount", "Total Price", "Total Invoice"];
        } else {
            return ["Tanggal", "Invoice", "Customer", "Cross", "User", "Divisi", "Item Qty", "Item Value", "Ongkir", "Kode Unik", "Biaya Admin", "Biaya Lain", "Nameset", "Value-Admin", "Total", "Tipe Bayar 1", "Jumlah Bayar 1", "Kartu 1", "Ref 1", "Tipe Bayar 2", "Jumlah Bayar 2", "Kartu 2", "Ref 2", "Note"];
        }
    }

    public function collection()
    {
        $export = array();
        if ($this->type == 'article') {
            $data = DB::table('pos_transaction_details')->select('pos_transaction_details.id as ptd_id', 'pos_transaction_details.created_at as ptd_created', 'pos_transaction_details.pst_id as pst_id', 'pos_invoice', 'cross_order', 'u_name',
                    'dv_name', 'br_name', 'pc_name', 'psc_name', 'pssc_name', 'cust_name', 'p_name', 'p_color', 'pos_td_sell_price', 'sz_name', 'pos_td_qty', 'stkt_name', 'ps_price_tag', 'p_price_tag', 'std_id', 'ps_sell_price', 'p_sell_price', 
                    'pos_td_discount_price', 'pos_td_marketplace_price', 'pos_status', 'pos_refund', 'pt_id',
                    'p_purchase_price', 'ps_purchase_price', DB::raw("avg(ts_purchase_order_article_detail_statuses.poads_purchase_price) as purchase", 'poad_total_price', 'poad_qty'))
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
                    ->whereNotIn('pos_status', ['WAITING FOR CONFIRMATION', 'CANCEL', 'UNPAID'])
                    ->where(function($w) {
                        if (!empty($this->st_id)) {
                            $w->where('pos_transactions.st_id', '=', $this->st_id);
                        }
                        if (!empty($this->stt_id)) {
                            $w->where('pos_transactions.stt_id', '=', $this->stt_id);
                        }
                        if (!empty($this->end)) {
                            $w->whereDate('pos_transactions.created_at', '>=', $this->start)
                            ->whereDate('pos_transactions.created_at', '<=', $this->end);
                        } else {
                            $w->whereDate('pos_transactions.created_at', '=', $this->start);
                        }
                    })
                    ->orderBy('pos_transaction_details.created_at')
                    ->groupBy('pos_transaction_details.id')->get();
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

                    $purchase = 0;
                    if (!empty ($row->purchase)) {
                        $purchase = round($row->purchase);
                    } else {
                        if (!empty($row->poad_total_price)) {
                            $purchase = round($row->poad_total_price / $row->poad_qty);
                        } else {
                            if (!empty($row->ps_purchase_price)) {
                                $purchase = $row->ps_purchase_price;
                            } else {
                                $purchase = $row->p_purchase_price;
                            }
                        }
                    }

                    $export[] = [date('d/m/Y H:i:s', strtotime($row->ptd_created)), $row->pos_invoice, $row->cross_order, $row->cust_name, $row->u_name, $row->dv_name, $row->stkt_name, $row->br_name, $row->p_name, $row->p_color, $row->sz_name, $row->pc_name, $row->psc_name, $row->pssc_name, $row->pos_td_qty, $bandrol, $purchase, $row->pos_td_sell_price, $discount, $total, $total_price];
                }
            }
        }
        if ($this->type == 'cross') {
            $data = DB::table('pos_transaction_details')->select('pos_transaction_details.id as ptd_id', 'pos_transaction_details.created_at as ptd_created', 'pos_transaction_details.pst_id as pst_id', 'pos_invoice', 'cross_order', 'u_name',
                    'dv_name', 'br_name', 'pc_name', 'psc_name', 'pssc_name', 'cust_name', 'p_name', 'p_color', 'pos_td_sell_price', 'sz_name', 'pos_td_qty', 'stkt_name', 'ps_price_tag', 'p_price_tag', 'std_id', 'ps_sell_price', 'p_sell_price', 
                    'pos_td_discount_price', 'pos_td_marketplace_price', 'pos_status', 'pos_refund', 'pt_id',
                    'p_purchase_price', 'ps_purchase_price', DB::raw("avg(ts_purchase_order_article_detail_statuses.poads_purchase_price) as purchase", 'poad_total_price', 'poad_qty'))
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
                    ->whereNotIn('pos_status', ['WAITING FOR CONFIRMATION', 'CANCEL', 'UNPAID'])
                    ->where(function($w) {
                        if (!empty($this->st_id)) {
                            $w->where('pos_transactions.st_id_ref', '=', $this->st_id);
                        }
                        if (!empty($this->stt_id)) {
                            $w->where('pos_transactions.stt_id', '=', $this->stt_id);
                        }
                        if (!empty($this->end)) {
                            $w->whereDate('pos_transactions.created_at', '>=', $this->start)
                            ->whereDate('pos_transactions.created_at', '<=', $this->end);
                        } else {
                            $w->whereDate('pos_transactions.created_at', '=', $this->start);
                        }
                    })
                    ->orderBy('pos_transaction_details.created_at')
                    ->groupBy('pos_transaction_details.id')->get();
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

                    $purchase = 0;
                    if (!empty ($row->purchase)) {
                        $purchase = round($row->purchase);
                    } else {
                        if (!empty($row->poad_total_price)) {
                            $purchase = round($row->poad_total_price / $row->poad_qty);
                        } else {
                            if (!empty($row->ps_purchase_price)) {
                                $purchase = $row->ps_purchase_price;
                            } else {
                                $purchase = $row->p_purchase_price;
                            }
                        }
                    }

                    $export[] = [date('d/m/Y H:i:s', strtotime($row->ptd_created)), $row->pos_invoice, $row->cross_order, $row->cust_name, $row->u_name, $row->dv_name, $row->stkt_name, $row->br_name, $row->p_name, $row->p_color, $row->sz_name, $row->pc_name, $row->psc_name, $row->pssc_name, $row->pos_td_qty, $bandrol, $purchase, $row->pos_td_sell_price, $discount, $total, $total_price];
                }
            }
        }
        if ($this->type == 'invoice') {
            $data = DB::table('pos_transactions')->select('pos_transactions.id as pt_id', 'pos_transactions.created_at as pos_created', 'pos_invoice', 'pos_shipping', 'pos_unique_code', 'pos_admin_cost', 'pos_another_cost',
            'dv_name', 'cross_order', 'u_name', 'pos_payment', 'pos_payment_partial', 'pos_note', 'pm_id', 'pm_id_partial', 'cp_id', 'cp_id_partial', 'cust_name', 'pos_refund', 'pos_status', 'pos_card_number', 'pos_ref_number', 'pos_card_number_two', 'pos_ref_number_two')
            ->leftJoin('customers', 'customers.id', '=', 'pos_transactions.cust_id')
            ->leftJoin('users', 'users.id', '=', 'pos_transactions.u_id')
            ->leftJoin('store_type_divisions', 'store_type_divisions.id', '=', 'pos_transactions.std_id')
            ->whereNotIn('pos_status', ['WAITING FOR CONFIRMATION', 'CANCEL', 'UNPAID'])
            ->where(function($w) {
                if (!empty($this->st_id)) {
                    $w->where('pos_transactions.st_id', '=', $this->st_id);
                }
                if (!empty($this->stt_id)) {
                    $w->where('pos_transactions.stt_id', '=', $this->stt_id);
                }
                if (!empty($this->end)) {
                    $w->whereDate('pos_transactions.created_at', '>=', $this->start)
                    ->whereDate('pos_transactions.created_at', '<=', $this->end);
                } else {
                    $w->whereDate('pos_transactions.created_at', '=', $this->start);
                }
            })
            ->get();
            if (!empty($data)) {
                foreach ($data as $row) {
                    $item_qty = 0;
                    $item_value = 0;
                    $nameset = 0;
                    $value_admin = 0;
                    $total = 0;
                    $ptd = DB::table('pos_transaction_details')->select('pos_td_qty', 'pos_td_discount_price', 'pos_td_marketplace_price', 'pos_td_nameset_price')
                    ->leftJoin('product_location_setup_transactions', 'product_location_setup_transactions.pt_id', '=', 'pos_transaction_details.pt_id')
                    ->where('pos_transaction_details.pt_id', '=', $row->pt_id)
                    ->groupBy('pos_transaction_details.id')->get();
                    if (!empty($ptd)) {
                        foreach($ptd as $srow) {
                            if (!empty($srow->pos_td_marketplace_price)) {
                                $item_value += $srow->pos_td_marketplace_price;
                            } else {
                                $item_value += $srow->pos_td_discount_price;
                            }
                            $item_qty += $srow->pos_td_qty;
                            $nameset += $srow->pos_td_nameset_price;
                        }
                    }
                    $value_admin = $item_value - $row->pos_admin_cost;
                    $total = $value_admin + $row->pos_another_cost + $row->pos_shipping + $row->pos_unique_code;
                    $pm_one = '';
                    $pm_two = '';
                    $cp_one = '';
                    $cp_two = '';
                    if (!empty($row->pm_id)) {
                        $pm_one = DB::table('payment_methods')->select('pm_name')->where('id', '=', $row->pm_id)->get()->first()->pm_name;
                    }
                    if (!empty($row->cp_id)) {
                        $cp_one = DB::table('card_providers')->select('cp_name')->where('id', '=', $row->cp_id)->get()->first()->cp_name;
                    }
                    if (!empty($row->pm_id_partial)) {
                        $pm_two = DB::table('payment_methods')->select('pm_name')->where('id', '=', $row->pm_id_partial)->get()->first()->pm_name;
                    }
                    if (!empty($row->cp_id_partial)) {
                        $cp_two = DB::table('card_providers')->select('cp_name')->where('id', '=', $row->cp_id_partial)->get()->first()->cp_name;
                    }
                    $export[] = [date('d/m/Y H:i:s', strtotime($row->pos_created)), $row->pos_invoice, $row->cust_name, $row->cross_order, $row->u_name, $row->dv_name, $item_qty, $item_value, $row->pos_shipping, $row->pos_unique_code, $row->pos_admin_cost, $row->pos_another_cost, $nameset, $value_admin, $total, $pm_one.' '.$cp_one, $row->pos_payment, $row->pos_card_number, $row->pos_ref_number, $pm_two.' '.$cp_two, $row->pos_payment_partial, $row->pos_card_number_two, $row->pos_ref_number_two, $row->pos_note];
                }
            }
        }
        return collect($export);
    }
}
