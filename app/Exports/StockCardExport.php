<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\ExceptionLocation;
use App\Models\ProductLocation;

class StockCardExport implements FromCollection , withHeadings
{
    function __construct()
    {

    }

    public function headings(): array
    {
        return ["Start Date", "End Date", "Store", "Barcode", "Brand", "Article", "Color", "Size", "Beginning Stock", "Purchase", "Trans Out", "Trans In", "Sales", "Refund", "Adj (-)", "Adj (+)", "MAdj(-)", "MAdj (+)", "SAdj(-)", "SAdj (+)", "Waiting (CO/TAKE/OFF)", "Cross Setup In", "Cross Setup Out", "Ending Stock", "Today Exception", "Today Stock", "HB", "HJ"];
    }

    public function collection()
    {
        $export = array();
        $stock = DB::table('stock_exports')->where('u_id', '=', Auth::user()->id)->get();
        if (!empty($stock->first())) {
            foreach ($stock as $row) {
                $beginning = $row->beginning_stock;
                if (empty($beginning)) {
                    $beginning = '0';
                }
                $purchase = $row->purchase;
                if (empty($purchase)) {
                    $purchase = '0';
                }
                $trans_out = $row->trans_out;
                if (empty($trans_out)) {
                    $trans_out = '0';
                }
                $trans_in = $row->trans_in;
                if (empty($trans_in)) {
                    $trans_in = '0';
                }
                $sales = $row->sales;
                if (empty($sales)) {
                    $sales = '0';
                }
                $refund = $row->refund;
                if (empty($refund)) {
                    $refund = '0';
                }
                $adj_min = $row->adj_min;
                if (empty($adj_min)) {
                    $adj_min = '0';
                }
                $adj_plus = $row->adj_plus;
                if (empty($adj_plus)) {
                    $adj_plus = '0';
                }
                $madj_min = $row->madj_min;
                if (empty($madj_min)) {
                    $madj_min = '0';
                }
                $madj_plus = $row->madj_plus;
                if (empty($madj_plus)) {
                    $madj_plus = '0';
                }
                $sadj_min = $row->sadj_min;
                if (empty($sadj_min)) {
                    $sadj_min = '0';
                }
                $sadj_plus = $row->sadj_plus;
                if (empty($sadj_plus)) {
                    $sadj_plus = '0';
                }
                $waiting = $row->waiting;
                if (empty($waiting)) {
                    $waiting = '0';
                }
                $cross_setup_in = $row->cross_setup_in;
                if (empty($cross_setup_in)) {
                    $cross_setup_in = '0';
                }
                $cross_setup_out = $row->cross_setup_out;
                if (empty($cross_setup_out)) {
                    $cross_setup_out = '0';
                }
                $ending_stock = $row->ending_stock;
                if (empty($ending_stock) || $ending_stock < 0) {
                    $ending_stock = '0';
                }
                
                $export[] = [date('d/m/Y', strtotime($row->start_date)), date('d/m/Y', strtotime($row->end_date)), $row->store, $row->barcode, $row->brand, $row->article, $row->color, $row->size, $beginning, $purchase, $trans_out, $trans_in, $sales, $refund, $adj_min, $adj_plus, $madj_min, $madj_plus, $sadj_min, $sadj_plus, $waiting, $cross_setup_in, $cross_setup_out, $ending_stock, $row->today_exception, $row->today_stock, $row->hb, $row->hj];
            }
        }
        return collect($export);
    }
}