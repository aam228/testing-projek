<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Illuminate\Support\Facades\DB;

class ShopeeExport implements FromCollection
{
    /**
    * @return \Illuminate\Support\Collection
    */
    protected $tg_id;

    function __construct($tg_id)
    {
        $this->tg_id = $tg_id;
    }

    public function collection()
    {
        $export = array();
        $data = DB::table('shopee_data')->select('system_stock', 'template_row')
        ->where('tg_id', '=', $this->tg_id)
        ->orderBy('template_row')->get();
        if (!empty($data->first())) {
            foreach ($data as $row) {
                $stock = $row->system_stock;
                if (empty($stock)) {
                    $stock = '0';
                }
                $export[] = [$stock];
            }
        }
        return collect($export);
    }
}
