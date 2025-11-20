<?php

/**
 * Metas Model
 *
 * Metas Model manages Metas operation.
 *
 * @category   Metas
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

class Meta extends Model
{
    protected $table   = 'seo_metas';
    public $timestamps = false;
}
