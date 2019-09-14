<?php
declare(strict_types=1);

namespace Minsksanek\Mailreader;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;

/**
 * Class MailreaderServiceProvider
 * @package Minsksanek\Mailreader
 */
class MailreaderServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     * @return void
     * @throws BindingResolutionException
     */
    public function register()
    {
        try {
            $this->app->make('Minsksanek\Mailreader\ClientController');
        } catch (BindingResolutionException $e) {
            dump($e);
        }
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
