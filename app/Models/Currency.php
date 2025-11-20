<?php

/**
 * Currency Model
 *
 * Currency Model manages Currency operation.
 *
 * @category   Currency
 * @package    OzzieAccom
 * @author     Abhishek Singh
 * @copyright  OzzieAccom 2022
 * @license
 * @version    2.7
 * @link       Ozzieaccom.com
 * @since      Version 1.3
 * @deprecated None
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Session;
use DB;

class Currency extends Model
{
    protected $table   = 'currency';
    public $timestamps = false;

    protected $appends = ['org_symbol'];


    public static function code_to_symbol($code)
    {
        return self::getAll()->firstWhere('code', $code)->symbol;
    }

    public function getOrgSymbolAttribute()
    {
        $symbol = $this->attributes['symbol'];
        return $symbol;
    }

    public function getSessionCodeAttribute()
    {
        if (Session::get('currency')) {
            return Session::get('currency');
        } else {
            return self::getAll()->firstWhere('default', 1)->code;
        }
    }

    public static function getAll()
    {
        $data = Cache::get(config('cache.prefix') . '.currency');
        if (empty($data)) {
            $data = parent::all();
            Cache::put(config('cache.prefix') . '.currency', $data, 30 * 86400);
        }
        if(!array_key_exists(\Session::get('currency'), $data->pluck('code','code')->toArray())){
            \Session::put('currency', $data->where('status','Active')->firstWhere('default', 1)->code);
        }
        return $data;
    }
}
