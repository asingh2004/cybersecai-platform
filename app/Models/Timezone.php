<?php

/**
 * Timezpne Model
 *
 * Timezpne Model manages Timezpne operation.
 *
 * @category   Timezpne
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

class Timezone extends Model
{
    protected $table   = 'timezone';
    public $timestamps = false;
}
