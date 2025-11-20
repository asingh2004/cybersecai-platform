<?php

/**
 * Message Model
 *
 * Message Model manages Message operation.
 *
 * @category   Message
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

class MessageType extends Model
{
    protected $table    = 'message_type';
    public $timestamps  = false;

    public function messages()
    {
        return $this->hasMany('App\Models\Messages', 'type_id', 'id');
    }
}
