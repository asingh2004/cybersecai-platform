<?php

/**
 * PropertyFees Model
 *
 * PropertyFees Model manages PropertyFees operation.
 *
 * @category   PropertyFees
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

class PropertyFees extends Model
{
    protected $table   = 'property_fees';
    public $timestamps = false;
}
