<?php

/**
 * RespiteType Model
 *
 * RespiteType Model manages RespiteType operation.
 *
 * @category   RespiteType
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

class RespiteType extends Model
{
    protected $table    = 'respite_type';
    public $timestamps  = false;

    public static function getAll()
    {
        $data = Cache::get(config('cache.prefix') . '.property.types.respite');
        if (empty($data)) {
            $data = parent::all();
            Cache::forever(config('cache.prefix') . '.property.types.respite', $data);
        }
        return $data;
    }
}