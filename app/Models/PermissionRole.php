<?php

/**
 * PermissionRole Model
 *
 * PermissionRole Model manages PermissionRole operation.
 *
 * @category   PermissionRole
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

class PermissionRole extends Model
{
    protected $fillable = ['permission_id', 'role_id'];
    protected $table    = 'permission_role';
    public $timestamps  = false;
}
