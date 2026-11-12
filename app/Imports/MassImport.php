<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;

class MassImport implements ToCollection, WithStartRow
{
    /**
    * @param Collection $collection
    */

    private $rows = 0;
    private $ma_id_throw = null;
    private $ma_code_throw = null;
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

    public function startRow(): int
    {
        return 2;
    }


    public function collection(Collection $collection)
    {
        ++$this->rows;
        $ma_code = 'MADJ'.date('YmdHis');
        $this->ma_code_throw = $ma_code;
        $st_id = $this->st_id;
        $ma_id = null;
        $detail = array();
        foreach ($collection as $r) {
            if ($r[0] == null) {
                return null;
            }
            $pls_id = $r[0];
            $qty_export = (int)$r[9];
            $qty_so = (int)$r[10];
            $type = null;
            $diff = null;
            if ($qty_export > $qty_so) {
                $type = '-';
                $diff = $qty_export - $qty_so;
            } else if ($qty_export < $qty_so) {
                $type = '+';
                $diff = $qty_so - $qty_export;
            } else {
                $type = '=';
                $diff = 0;
            }
            if (empty($ma_id)) {
                $ma_id = DB::table('mass_adjustments')->insertGetId([
                    'st_id' => $st_id,
                    'u_id' => Auth::user()->id,
                    'ma_code' => $ma_code,
                    'ma_approve' => null,
                    'ma_editor' => null,
                    'ma_executor' => null,
                    'ma_status' => '0',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                $this->ma_id_throw = $ma_id;
            }
            $detail[] = [
                'ma_id' => $ma_id,
                'pls_id' => $pls_id,
                'qty_export' => $qty_export,
                'qty_so' => $qty_so,
                'mad_type' => $type,
                'mad_diff' => $diff,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
        }
        $insert = DB::table('mass_adjustment_details')->insert($detail);
    }

    public function getRowCount(): array
    {
        $data = [
            'ma_id' => $this->ma_id_throw,
            'ma_code' => $this->ma_code_throw,
        ];
        return $data;
    }
}
