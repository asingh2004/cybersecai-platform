<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessProfile extends Model
{
    protected $table = 'business_profile';

    protected $fillable = [
        'business_id',
        'industry',
        'country',
        'about_company',
        'selected_regulations',
        'selected_subject_category',
        'subject_category_for_llm',
        'data_classification',
    ];

    protected $casts = [
        'selected_regulations' => 'array',
        'selected_subject_category' => 'array',
        'data_classification' => 'array',
    ];
}