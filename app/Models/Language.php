<?php

/**
 * Language Model
 *
 * Language Model manages Language operation.
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

class Language extends Model
{
    protected $table   = 'language';
    public $timestamps = false;
    
    public function email_templates()
    {
        return $this->hasMany('App\Models\EmailTemplate', 'lang_id');
    }

    public static function name($name)
    {
        $name =  Language::where('short_name', $name)->first()->name;
        return $name;
    }
}
