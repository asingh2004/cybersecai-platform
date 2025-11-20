<?php

/**
 * Notifications Model
 *
 * Notifications Model manages Notifications operation.
 *
 * @category   Notifications
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

class Notifications extends Model
{
    protected $table = 'notifications';

    public function users()
    {
        return $this->belongsTo('App\Models\User', 'user_id', 'id');
    }
}
