<?php

/**
 * Banners Model
 *
 * Banners Model manages Banners operation.
 *
 * @category   Banners
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

class Banners extends Model
{
    protected $table    = 'banners';
    public $timestamps  = false;
    public $appends     = ['image_url'];

    public function getImageUrlAttribute()
    {
        return url('/').'/public/front/images/banners/'.$this->attributes['image'];
    }
}
