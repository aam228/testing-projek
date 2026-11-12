<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\ExceptionLocation;

class ExportData implements FromCollection , withHeadings
{
    protected $start;
    protected $end;
    protected $label;
    protected $store;
    protected $division;

    function __construct($start, $end, $label, $store, $division)
    {
        $this->start = $start;
        $this->end = $end;
        $this->label = $label;
        $this->store = $store;
        $this->division = $division;
    }

    public function headings(): array
    {
        if ($this->label == 'sales' || $this->label == 'cross_sales') {
            return ["Date", "INV", "Item", "Total", "AdminCost", "Nett Sales"];
        }
        if ($this->label == 'profits' || $this->label == 'cross_profits') {
            return ["Date", "INV", "Brand", "Article", "Color", "Size", "Qty", "Price", "Sales Total", "Purchase Price", "Purchase Total", "Profit"];
        }
        if ($this->label == 'purchases') {
            return ["Date", "INV", "Brand", "Article", "Color", "Size", "Qty", "Purchase Price", "Total"];
        }
        if ($this->label == 'cc_assets' || $this->label == 'cc_exc_assets') {
            return ["BIN", "Brand", "Article", "Color", "Size", "IDStock", "Stock", "Purchase Price", "Total"];
        }
        if ($this->label == 'c_assets' || $this->label == 'c_exc_assets') {
            return ["BIN", "Brand", "Article", "Color", "Size", "IDStock", "Stock", "Purchase Price", "Total"];
        }
    }

    public function collection()
    {
        $export = array();
        $start = $this->start;
        $end = $this->end;
        $st_id = $this->store;
        $division = $this->division;
        $exception = ExceptionLocation::select('pl_code')
            ->leftJoin('product_locations', 'product_locations.id', '=', 'exception_locations.pl_id')
            ->get()
            ->toArray();
        if ($this->label == 'sales') {
            $pos = DB::table('pos_transactions')
            ->selectRaw("ts_pos_transactions.id as id, pos_invoice, sum(ts_pos_transaction_details.pos_td_qty) as item, sum(ts_pos_transaction_details.pos_td_discount_price) as item_total_1, sum(ts_pos_transaction_details.pos_td_marketplace_price) as item_total_2, ts_pos_transactions.created_at as created_at, pos_admin_cost")
            ->leftJoin('pos_transaction_details', 'pos_transaction_details.pt_id', '=', 'pos_transactions.id')
            ->where(function ($w) use ($start, $end, $st_id, $division) {
                if (!empty($end)) {
                    $w->whereDate('pos_transactions.created_at', '>=', $start)
                    ->whereDate('pos_transactions.created_at', '<=', $end);
                } else {
                    $w->whereDate('pos_transactions.created_at', '=', $start);
                }
                if (!empty($st_id)) {
                    $w->whereIn('pos_transactions.st_id', $st_id);
                } else {
                    $w->where('pos_transactions.st_id', '!=', '4');
                }
                if ($division != 'all') {
                    if ($division == 'online') {
                        $w->where('pos_transactions.stt_id', '=', '1');
                    } else {
                        $w->where('pos_transactions.stt_id', '=', '2');
                    }
                }
                $w->whereNotIn('pos_transactions.pos_status', ['WAITING FOR CONFIRMATION','CANCEL', 'UNPAID']);
            })
            ->groupBy('pos_transactions.id')
            ->get();
            if (!empty($pos->first())) {
                foreach ($pos as $row) {
                    $created_at = date('d/m/Y H:i:s', strtotime($row->created_at));
                    if (!empty($row->item_total_2)) {
                        $total = $row->item_total_2;
                        $total_final = $row->item_total_2 - $row->pos_admin_cost;
                    } else {
                        $total = $row->item_total_1;
                        $total_final = $row->item_total_1 - $row->pos_admin_cost;
                    }
                    $export[] = [$created_at, $row->pos_invoice, $row->item, $total, $row->pos_admin_cost, $total_final];
                }
            }
        }
        if ($this->label == 'profits') {
            $profit = DB::table('pos_transaction_details')
            ->selectRaw("ts_pos_transactions.created_at as created_at, pos_invoice, br_name, p_name, p_color, sz_name, pos_td_qty, pos_td_sell_price, pos_td_marketplace_price, pos_td_discount_price, pos_td_total_price, avg(ts_purchase_order_article_detail_statuses.poads_purchase_price) as purchase, poad_total_price, poad_qty, ps_purchase_price, p_purchase_price")
            ->leftJoin('purchase_order_article_details', 'purchase_order_article_details.pst_id', '=', 'pos_transaction_details.pst_id')
            ->leftJoin('purchase_order_article_detail_statuses', 'purchase_order_article_detail_statuses.poad_id', '=', 'purchase_order_article_details.id')
            ->leftJoin('pos_transactions', 'pos_transaction_details.pt_id', '=', 'pos_transactions.id')
            ->leftJoin('product_stocks', 'product_stocks.id', '=', 'pos_transaction_details.pst_id')
            ->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
            ->leftJoin('brands', 'brands.id', '=', 'products.br_id')
            ->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
            ->where(function ($w) use ($start, $end, $st_id, $division) {
                if (!empty($end)) {
                    $w->whereDate('pos_transactions.created_at', '>=', $start)
                    ->whereDate('pos_transactions.created_at', '<=', $end);
                } else {
                    $w->whereDate('pos_transactions.created_at', '=', $start);
                }
                if (!empty($st_id)) {
                    $w->whereIn('pos_transactions.st_id', $st_id);
                } else {
                    $w->where('pos_transactions.st_id', '!=', '4');
                }
                if ($division != 'all') {
                    if ($division == 'online') {
                        $w->where('pos_transactions.stt_id', '=', '1');
                    } else {
                        $w->where('pos_transactions.stt_id', '=', '2');
                    }
                }
                $w->whereNotIn('pos_transactions.pos_status', ['WAITING FOR CONFIRMATION','CANCEL', 'UNPAID']);
            })
            ->groupBy('pos_transaction_details.id')
            ->get();
            if (!empty($profit->first())) {
                foreach ($profit as $row) {
                    $created_at = date('d/m/Y H:i:s', strtotime($row->created_at));
                    $total = 0;
                    if (!empty($row->pos_td_marketplace_price)) {
                        $total = $row->pos_td_marketplace_price;
                    } else {
                        $total = $row->pos_td_discount_price;
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
                    $export[] = [$created_at, $row->pos_invoice, $row->br_name, $row->p_name, $row->p_color, $row->sz_name, $row->pos_td_qty, $row->pos_td_sell_price, $total, $purchase, ($row->pos_td_qty*$purchase), $total-($row->pos_td_qty*$purchase)];
                }
            }
        }
        if ($this->label == 'cross_sales') {
            $pos = DB::table('pos_transactions')
            ->selectRaw("ts_pos_transactions.id as id, pos_invoice, sum(ts_pos_transaction_details.pos_td_qty) as item, sum(ts_pos_transaction_details.pos_td_discount_price) as item_total_1, sum(ts_pos_transaction_details.pos_td_marketplace_price) as item_total_2, ts_pos_transactions.created_at as created_at, pos_admin_cost")
            ->leftJoin('pos_transaction_details', 'pos_transaction_details.pt_id', '=', 'pos_transactions.id')
            ->where(function ($w) use ($start, $end, $st_id, $division) {
                if (!empty($end)) {
                    $w->whereDate('pos_transactions.created_at', '>=', $start)
                    ->whereDate('pos_transactions.created_at', '<=', $end);
                } else {
                    $w->whereDate('pos_transactions.created_at', '=', $start);
                }
                if (!empty($st_id)) {
                    $w->whereIn('pos_transactions.st_id_ref', $st_id);
                } else {
                    $w->whereNotNull('pos_transactions.st_id_ref')
                    ->where('pos_transactions.st_id_ref', '!=', '4');
                }
                if ($division != 'all') {
                    if ($division == 'online') {
                        $w->where('pos_transactions.stt_id', '=', '1');
                    } else {
                        $w->where('pos_transactions.stt_id', '=', '2');
                    }
                }
                $w->whereNotIn('pos_transactions.pos_status', ['WAITING FOR CONFIRMATION','CANCEL', 'UNPAID']);
            })
            ->groupBy('pos_transactions.id')
            ->get();
            if (!empty($pos->first())) {
                foreach ($pos as $row) {
                    $created_at = date('d/m/Y H:i:s', strtotime($row->created_at));
                    if (!empty($row->item_total_2)) {
                        $total = $row->item_total_2;
                        $total_final = $row->item_total_2 - $row->pos_admin_cost;
                    } else {
                        $total = $row->item_total_1;
                        $total_final = $row->item_total_1 - $row->pos_admin_cost;
                    }
                    $export[] = [$created_at, $row->pos_invoice, $row->item, $total, $row->pos_admin_cost, $total_final];
                }
            }
        }
        if ($this->label == 'cross_profits') {
            $profit = DB::table('pos_transaction_details')
            ->selectRaw("ts_pos_transactions.created_at as created_at, pos_invoice, br_name, p_name, p_color, sz_name, pos_td_qty, pos_td_sell_price, pos_td_marketplace_price, pos_td_discount_price, pos_td_total_price, avg(ts_purchase_order_article_detail_statuses.poads_purchase_price) as purchase, poad_total_price, poad_qty, ps_purchase_price, p_purchase_price")
            ->leftJoin('purchase_order_article_details', 'purchase_order_article_details.pst_id', '=', 'pos_transaction_details.pst_id')
            ->leftJoin('purchase_order_article_detail_statuses', 'purchase_order_article_detail_statuses.poad_id', '=', 'purchase_order_article_details.id')
            ->leftJoin('pos_transactions', 'pos_transaction_details.pt_id', '=', 'pos_transactions.id')
            ->leftJoin('product_stocks', 'product_stocks.id', '=', 'pos_transaction_details.pst_id')
            ->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
            ->leftJoin('brands', 'brands.id', '=', 'products.br_id')
            ->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
            ->where(function ($w) use ($start, $end, $st_id, $division) {
                if (!empty($end)) {
                    $w->whereDate('pos_transactions.created_at', '>=', $start)
                    ->whereDate('pos_transactions.created_at', '<=', $end);
                } else {
                    $w->whereDate('pos_transactions.created_at', '=', $start);
                }
                if (!empty($st_id)) {
                    $w->whereIn('pos_transactions.st_id_ref', $st_id);
                } else {
                    $w->whereNotNull('pos_transactions.st_id_ref')
                    ->where('pos_transactions.st_id_ref', '!=', '4');
                }
                if ($division != 'all') {
                    if ($division == 'online') {
                        $w->where('pos_transactions.stt_id', '=', '1');
                    } else {
                        $w->where('pos_transactions.stt_id', '=', '2');
                    }
                }
                $w->whereNotIn('pos_transactions.pos_status', ['WAITING FOR CONFIRMATION','CANCEL', 'UNPAID']);
            })
            ->groupBy('pos_transaction_details.id')
            ->get();
            if (!empty($profit->first())) {
                foreach ($profit as $row) {
                    $created_at = date('d/m/Y H:i:s', strtotime($row->created_at));
                    $total = 0;
                    if (!empty($row->pos_td_marketplace_price)) {
                        $total = $row->pos_td_marketplace_price;
                    } else {
                        $total = $row->pos_td_discount_price;
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
                    $export[] = [$created_at, $row->pos_invoice, $row->br_name, $row->p_name, $row->p_color, $row->sz_name, $row->pos_td_qty, $row->pos_td_sell_price, $total, $purchase, ($row->pos_td_qty*$purchase), $total-($row->pos_td_qty*$purchase)];
                }
            }
        }
        if ($this->label == 'purchases') {
            $purchases = DB::table('purchase_order_article_detail_statuses')
            ->selectRaw("ts_purchase_order_article_detail_statuses.created_at as created_at, po_invoice, br_name, p_name, p_color, sz_name, sum(ts_purchase_order_article_detail_statuses.poads_qty) as qty, sum(ts_purchase_order_article_detail_statuses.poads_total_price) as total, poads_purchase_price")
            ->leftJoin('purchase_order_article_details', 'purchase_order_article_details.id', '=', 'purchase_order_article_detail_statuses.poad_id')
            ->leftJoin('purchase_order_articles', 'purchase_order_articles.id', '=', 'purchase_order_article_details.poa_id')
            ->leftJoin('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_articles.po_id')
            ->leftJoin('product_stocks', 'product_stocks.id', '=', 'purchase_order_article_details.pst_id')
            ->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
            ->leftJoin('brands', 'brands.id', '=', 'products.br_id')
            ->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
            ->where(function($w) use ($start, $end, $st_id) {
                if (!empty($end)) {
                    $w->whereDate('purchase_order_article_detail_statuses.created_at', '>=', $start)
                    ->whereDate('purchase_order_article_detail_statuses.created_at', '<=', $end);
                } else {
                    $w->whereDate('purchase_order_article_detail_statuses.created_at', '=', $start);
                }
                if (!empty($st_id)) {
                    $w->whereIn('purchase_orders.st_id', $st_id);
                } else {
                    $w->where('purchase_orders.st_id', '!=', '4');
                }
            })
            ->groupBy('purchase_order_article_details.pst_id')
            ->get();
            if (!empty($purchases->first())) {
                foreach ($purchases as $row) {
                    $created_at = date('d/m/Y H:i:s', strtotime($row->created_at));
                    $export[] = [$created_at, $row->po_invoice, $row->br_name, $row->p_name, $row->p_color, $row->sz_name, $row->qty, $row->poads_purchase_price, $row->total];
                }
            }
        }
        if ($this->label == 'cc_assets') {
            $cc_assets = DB::table('product_location_setups')
            ->selectRaw("ts_product_location_setups.pls_qty as qty, br_name, p_name, p_color, sz_name, avg(ts_purchase_order_article_detail_statuses.poads_purchase_price) as purchase, ps_purchase_price, p_purchase_price, stkt_id, pl_code")
            ->leftJoin('product_stocks', 'product_stocks.id', '=', 'product_location_setups.pst_id')
            ->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
            ->leftJoin('brands', 'brands.id', '=', 'products.br_id')
            ->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
            ->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
            ->leftJoin('purchase_order_article_details', 'purchase_order_article_details.pst_id', '=', 'product_location_setups.pst_id')
            ->leftJoin('purchase_order_article_detail_statuses', 'purchase_order_article_detail_statuses.poad_id', '=', 'purchase_order_article_details.id')
            ->whereNotIn('pl_code', $exception)
            ->where(function ($w) use ($st_id) {
                if (!empty($st_id)) {
                    $w->whereIn('product_locations.st_id', $st_id);
                } else {
                    $w->where('product_locations.st_id', '!=', '4');
                }
            })
            ->whereIn('stkt_id', ['1', '3'])
            ->where('product_location_setups.pls_qty', '>', '0')
            ->groupBy('product_location_setups.id')
            ->get();
            if (!empty($cc_assets->first())) {
                foreach ($cc_assets as $row) {
                    if (!empty ($row->purchase)) {
                        $purchase = round($row->purchase);
                    } else {
                        if (!empty($row->ps_purchase_price)) {
                            $purchase = $row->ps_purchase_price;
                        } else {
                            $purchase = $row->p_purchase_price;
                        }
                    }
                    $export[] = [$row->pl_code, $row->br_name, $row->p_name, $row->p_color, $row->sz_name, $row->stkt_id, $row->qty, $purchase, ($row->qty*$purchase)];
                }
            }
        }
        if ($this->label == 'c_assets') {
            $cc_assets = DB::table('product_location_setups')
            ->selectRaw("ts_product_location_setups.pls_qty as qty, br_name, p_name, p_color, sz_name, avg(ts_purchase_order_article_detail_statuses.poads_purchase_price) as purchase, ps_purchase_price, p_purchase_price, stkt_id, pl_code")
            ->leftJoin('product_stocks', 'product_stocks.id', '=', 'product_location_setups.pst_id')
            ->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
            ->leftJoin('brands', 'brands.id', '=', 'products.br_id')
            ->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
            ->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
            ->leftJoin('purchase_order_article_details', 'purchase_order_article_details.pst_id', '=', 'product_location_setups.pst_id')
            ->leftJoin('purchase_order_article_detail_statuses', 'purchase_order_article_detail_statuses.poad_id', '=', 'purchase_order_article_details.id')
            ->whereNotIn('pl_code', $exception)
            ->where(function ($w) use ($st_id) {
                if (!empty($st_id)) {
                    $w->whereIn('product_locations.st_id', $st_id);
                } else {
                    $w->where('product_locations.st_id', '!=', '4');
                }
            })
            ->where('stkt_id', '=', '2')
            ->where('product_location_setups.pls_qty', '>', '0')
            ->groupBy('product_location_setups.id')
            ->get();
            if (!empty($cc_assets->first())) {
                foreach ($cc_assets as $row) {
                    if (!empty ($row->purchase)) {
                        $purchase = round($row->purchase);
                    } else {
                        if (!empty($row->ps_purchase_price)) {
                            $purchase = $row->ps_purchase_price;
                        } else {
                            $purchase = $row->p_purchase_price;
                        }
                    }
                    $export[] = [$row->pl_code, $row->br_name, $row->p_name, $row->p_color, $row->sz_name, $row->stkt_id, $row->qty, $purchase, ($row->qty*$purchase)];
                }
            }
        }
        if ($this->label == 'cc_exc_assets') {
            $cc_assets = DB::table('product_location_setups')
            ->selectRaw("ts_product_location_setups.pls_qty as qty, br_name, p_name, p_color, sz_name, avg(ts_purchase_order_article_detail_statuses.poads_purchase_price) as purchase, ps_purchase_price, p_purchase_price, stkt_id, pl_code")
            ->leftJoin('product_stocks', 'product_stocks.id', '=', 'product_location_setups.pst_id')
            ->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
            ->leftJoin('brands', 'brands.id', '=', 'products.br_id')
            ->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
            ->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
            ->leftJoin('purchase_order_article_details', 'purchase_order_article_details.pst_id', '=', 'product_location_setups.pst_id')
            ->leftJoin('purchase_order_article_detail_statuses', 'purchase_order_article_detail_statuses.poad_id', '=', 'purchase_order_article_details.id')
            ->whereIn('pl_code', $exception)
            ->where(function ($w) use ($st_id) {
                if (!empty($st_id)) {
                    $w->whereIn('product_locations.st_id', $st_id);
                } else {
                    $w->where('product_locations.st_id', '!=', '4');
                }
            })
            ->whereIn('stkt_id', ['1', '3'])
            ->where('product_location_setups.pls_qty', '>', '0')
            ->groupBy('product_location_setups.id')
            ->get();
            if (!empty($cc_assets->first())) {
                foreach ($cc_assets as $row) {
                    if (!empty ($row->purchase)) {
                        $purchase = round($row->purchase);
                    } else {
                        if (!empty($row->ps_purchase_price)) {
                            $purchase = $row->ps_purchase_price;
                        } else {
                            $purchase = $row->p_purchase_price;
                        }
                    }
                    $export[] = [$row->pl_code, $row->br_name, $row->p_name, $row->p_color, $row->sz_name, $row->stkt_id, $row->qty, $purchase, ($row->qty*$purchase)];
                }
            }
        }
        if ($this->label == 'c_exc_assets') {
            $cc_assets = DB::table('product_location_setups')
            ->selectRaw("ts_product_location_setups.pls_qty as qty, br_name, p_name, p_color, sz_name, avg(ts_purchase_order_article_detail_statuses.poads_purchase_price) as purchase, ps_purchase_price, p_purchase_price, stkt_id, pl_code")
            ->leftJoin('product_stocks', 'product_stocks.id', '=', 'product_location_setups.pst_id')
            ->leftJoin('products', 'products.id', '=', 'product_stocks.p_id')
            ->leftJoin('brands', 'brands.id', '=', 'products.br_id')
            ->leftJoin('sizes', 'sizes.id', '=', 'product_stocks.sz_id')
            ->leftJoin('product_locations', 'product_locations.id', '=', 'product_location_setups.pl_id')
            ->leftJoin('purchase_order_article_details', 'purchase_order_article_details.pst_id', '=', 'product_location_setups.pst_id')
            ->leftJoin('purchase_order_article_detail_statuses', 'purchase_order_article_detail_statuses.poad_id', '=', 'purchase_order_article_details.id')
            ->whereIn('pl_code', $exception)
            ->where(function ($w) use ($st_id) {
                if (!empty($st_id)) {
                    $w->whereIn('product_locations.st_id', $st_id);
                } else {
                    $w->where('product_locations.st_id', '!=', '4');
                }
            })
            ->where('stkt_id', '=', '2')
            ->where('product_location_setups.pls_qty', '>', '0')
            ->groupBy('product_location_setups.id')
            ->get();
            if (!empty($cc_assets->first())) {
                foreach ($cc_assets as $row) {
                    if (!empty ($row->purchase)) {
                        $purchase = round($row->purchase);
                    } else {
                        if (!empty($row->ps_purchase_price)) {
                            $purchase = $row->ps_purchase_price;
                        } else {
                            $purchase = $row->p_purchase_price;
                        }
                    }
                    $export[] = [$row->pl_code, $row->br_name, $row->p_name, $row->p_color, $row->sz_name, $row->stkt_id, $row->qty, $purchase, ($row->qty*$purchase)];
                }
            }
        }
        return collect($export);
    }
}
