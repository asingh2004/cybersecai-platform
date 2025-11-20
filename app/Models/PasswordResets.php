<?php

/**
 * PasswordResets Model
 *
 * PasswordResets Model manages PasswordResets operation.
 *
 * @category   Language
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

class PasswordResets extends Model
{
    protected $table   = 'password_resets';

    public $timestamps = false;
}
