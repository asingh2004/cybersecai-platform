<?php

/**
 * RoleAdmin Model
 *
 * RoleAdmin Model manages RoleAdmin operation.
 *
 * @category   RoleAdmin
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

class RoleAdmin extends Model
{
    protected $table     = 'role_admin';
    protected $fillable  = ['role_id', 'admin_id'];
    public $timestamps   = false;

    public static function getAll()
    {
        $data = Cache::get(config('cache.prefix') . '.role_admin');
        if (empty($data)) {
            $data = parent::all();
            Cache::forever(config('cache.prefix') . '.role_admin', $data);
        }
        return $data;
    }
}
