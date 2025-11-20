<?php

namespace App\Observers;

use App\Models\Settings;
use Illuminate\Support\Facades\Cache;

class SettingsObserver
{
    /**
     * Handle the settings "created" event.
     *
     * @param Settings $settings
     * @return void
     */
    public function created(Settings $settings)
    {
        $this->clearCache();
    }

    /**
     * Handle the settings "updated" event.
     *
     * @param Settings $settings
     * @return void
     */
    public function updated(Settings $settings)
    {
        $this->clearCache();
    }

    /**
     * Handle the settings "deleted" event.
     *
     * @param Settings $settings
     * @return void
     */
    public function deleted(Settings $settings)
    {
        $this->clearCache();
    }

    /**
     * Handle the settings "restored" event.
     *
     * @param Settings $settings
     * @return void
     */
    public function restored(Settings $settings)
    {
        //
    }

    /**
     * Handle the settings "force deleted" event.
     *
     * @param Settings $settings
     * @return void
     */
    public function forceDeleted(Settings $settings)
    {
        //
    }

    private function clearCache() {
        Cache::forget((config('cache.prefix') . '.settings'));
    }
}
