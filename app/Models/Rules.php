<?php

/**
 * Rules Model
 *
 * Rules Model manages Rules operation.
 *
 * @category   Rules
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

class Rules extends Model
{
    protected $table   = 'rules';
    public $timestamps = false;
}
