<?php

namespace Minsksanek\Mailreader;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    /**
     * Register services.
     * @return void
     */
    public function register()
    {
        $this->app->make('Minsksanek\Mailreader\ClientController');
    }

    /**
     * Bootstrap services.
     * @return void
     */
    public function boot()
    {
        //
    }
}
