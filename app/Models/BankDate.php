<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankDate extends Model
{
    protected $fillable = ['booking_id', 'date'];

    public function getLowestDate($bookingId) {
        return BankDate::where('booking_id', $bookingId)->orderBy('date', 'asc')->first()->date;
    }
}
