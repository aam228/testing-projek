<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;

class MPCImport implements ToCollection
{
    protected $code_column;
    protected $code_title;
    protected $article_column;
    protected $variation_column;
    protected $std_id;
    /**
    * @param Collection $collection
    */

    function __construct($code_column, $code_title, $article_column, $variation_column, $std_id)
    {
        $this->code_column = $code_column;
        $this->code_title = $code_title;
        $this->article_column = $article_column;
        $this->variation_column = $variation_column;
        $this->std_id = $std_id;
    }

    private function doFilter($string)
    {
        return ltrim(str_replace(array('/', '-', '<', '>', '&', '{', '}', '*'), ' ', $string));
    }

    private function rm($string) {
        return ltrim(str_replace(array("'", "''", ","), ' ', $string));
    }

    private function checkCode($code) {
        $r = false;
        $check = DB::table('marketplace_managers')->where('marketplace_code', '=', $code)->exists();
        if ($check) {
            $r = true;
        }
        return $r;
    }

    private function fetchArticle() {
        $data = array();
        $check = DB::table('no_symbol_articles')->select('pst_id', 'fullname')->get();
        if (!empty($check->first())) {
            $data = $check->toArray();
        }
        return $data;
    }

    private function fetchBrand() {
        $data = array();
        $check = DB::table('brands')->select('br_name')->get();
        if (!empty($check->first())) {
            $data = $check->toArray();
        }
        return $data;
    }

    private function validate($article, $brand, $item) {
        $found = array();
        $pst_id = array();
        $brand_key = null;
        $next_1_key = null;
        $next_2_key = null;
        $next_3_key = null;
        $next_4_key = null;
        $next_5_key = null;
        $next_6_key = null;
        $next_7_key = null;
        $next_8_key = null;
        $next_9_key = null;
        $next_10_key = null;
        $search = $this->doFilter($item);
        $exp = explode(" ", $search);
        $total = count($exp);
        for ($i = 0; $i < $total; $i ++) {
            if (in_array($exp[$i], array_column($brand, 'br_name'))) {
                $brand_key = $exp[$i];
                if (empty($next_1_key)) {
                    $next_1_key = $exp[$i+1];
                    $total = $total - 1;
                }
                if (empty($next_2_key) AND $total > 1) {
                    $next_2_key = $exp[$i+2];
                    $total = $total - 1;
                }
                if (empty($next_3_key) AND $total > 1) {
                    $next_3_key = $exp[$i+3];
                    $total = $total - 1;
                }
                if (empty($next_4_key) AND $total > 1) {
                    $next_4_key = $exp[$i+4];
                    $total = $total - 1;
                }
                if (empty($next_5_key) AND $total > 1) {
                    $next_5_key = $exp[$i+5];
                    $total = $total - 1;
                }
                if (empty($next_6_key) AND $total > 1) {
                    $next_6_key = $exp[$i+6];
                    $total = $total - 1;
                }
                if (empty($next_7_key) AND $total > 1) {
                    $next_7_key = $exp[$i+7];
                    $total = $total - 1;
                }
                if (empty($next_8_key) AND $total > 1) {
                    $next_8_key = $exp[$i+8];
                    $total = $total - 1;
                }
                if (empty($next_9_key) AND $total > 1) {
                    $next_9_key = $exp[$i+9];
                    $total = $total - 1;
                }
                if (empty($next_10_key) AND $total > 1) {
                    $next_10_key = $exp[$i+10];
                    $total = $total - 1;
                }
                break;
            } else {
                $total = $total - 1;
            }
        }
        
        foreach ($article as &$row) {
            $sub_exp = explode(" ", $row->fullname);
            if (!in_array($brand_key, $sub_exp)) {
                continue;
            }
            if (!empty($next_1_key)) {
                if (!in_array($next_1_key, $sub_exp)) {
                    continue;
                }
            }
            if (!empty($next_2_key)) {
                if (!in_array($next_2_key, $sub_exp)) {
                    continue;
                }
            }
            if (!empty($next_3_key)) {
                if (!in_array($next_3_key, $sub_exp)) {
                    continue;
                }
            }
            if (!empty($next_4_key)) {
                if (!in_array($next_4_key, $sub_exp)) {
                    continue;
                }
            }
            if (!empty($next_5_key)) {
                if (!in_array($next_5_key, $sub_exp)) {
                    continue;
                }
            }
            if (!empty($next_6_key)) {
                if (!in_array($next_6_key, $sub_exp)) {
                    continue;
                }
            }
            if (!empty($next_7_key)) {
                if (!in_array($next_7_key, $sub_exp)) {
                    continue;
                }
            }
            if (!empty($next_8_key)) {
                if (!in_array($next_8_key, $sub_exp)) {
                    continue;
                }
            }
            if (!empty($next_9_key)) {
                if (!in_array($next_9_key, $sub_exp)) {
                    continue;
                }
            }
            if (!empty($next_10_key)) {
                if (!in_array($next_10_key, $sub_exp)) {
                    continue;
                }
            }
            for ($x = 0; $x < count($sub_exp); $x ++) {
                if (!in_array($sub_exp[$x], $exp)) {
                    continue;
                } else {
                    if (!in_array($row->pst_id, $pst_id)) {
                        array_push($pst_id, $row->pst_id);
                    }
                    if (!in_array($sub_exp[$x], $found)) {
                        array_push($found, $sub_exp[$x]);
                    }
                }
            }
        }
        if (count($pst_id) > 0) {
            return $pst_id[0];
        } else {
            return null;
        }
    }

    public function collection(Collection $collection)
    {
        $data = array();
        $found = 0;
        $art = $this->fetchArticle();
        $brand = $this->fetchBrand();
        foreach ($collection as $r) {
            $code = $this->rm($r[$this->code_column]);
            $article = $this->rm($r[$this->article_column]);
            $variation = $this->rm($r[$this->variation_column]);
            
            if ($found == 0) {
                if (strtolower($code) != strtolower($this->code_title)) {
                    continue;
                }
            }

            if (strtolower($code) == strtolower($this->code_title)) {
                $found = 1;
                continue;
            }

            if ($this->checkCode($code)) {
                continue;
            }

            $pst_id = $this->validate($art, $brand, $article.' '.$variation);

            if (!empty($pst_id)) {
                $data[] = [
                    'pst_id' => $pst_id,
                    'std_id' => $this->std_id,
                    'marketplace_code' => $code
                ];
            }
        }
        $insert = DB::table('marketplace_managers')->insert($data);
    }
}
