<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;

class MarketplaceExport implements FromCollection
{
    /**
    * @return \Illuminate\Support\Collection
    */
    protected $result;

    function __construct($result)
    {
        $this->result = $result;
    }

    public function collection()
    {
        return collect($this->result);
    }
}
