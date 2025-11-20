<?php

namespace App\Observers;

use App\Models\Bank;

class BankObserver
{
    /**
     * Handle the bank "created" event.
     *
     * @param Bank $bank
     * @return void
     */
    public function created(Bank $bank)
    {
        clearCache('.banks');
    }

    /**
     * Handle the bank "updated" event.
     *
     * @param Bank $bank
     * @return void
     */
    public function updated(Bank $bank)
    {
        clearCache('.banks');
    }

    /**
     * Handle the bank "deleted" event.
     *
     * @param Bank $bank
     * @return void
     */
    public function deleted(Bank $bank)
    {
        clearCache('.banks');
    }

    /**
     * Handle the bank "restored" event.
     *
     * @param Bank $bank
     * @return void
     */
    public function restored(Bank $bank)
    {
        //
    }

    /**
     * Handle the bank "force deleted" event.
     *
     * @param Bank $bank
     * @return void
     */
    public function forceDeleted(Bank $bank)
    {
        clearCache('.banks');
    }
}
