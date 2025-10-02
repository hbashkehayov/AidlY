<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;

class MailServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton('mailer', function ($app) {
            $config = $app->make('config')->get('mail');

            $smtpConfig = $config['mailers']['smtp'];
            $encryption = $smtpConfig['encryption'] === 'tls' ? 'tls' : ($smtpConfig['encryption'] === 'ssl' ? 'ssl' : null);

            // Build DSN for Symfony Mailer
            $dsn = sprintf(
                'smtp://%s:%s@%s:%s',
                urlencode($smtpConfig['username'] ?? ''),
                urlencode($smtpConfig['password'] ?? ''),
                $smtpConfig['host'],
                $smtpConfig['port']
            );

            if ($encryption) {
                $dsn .= '?encryption=' . $encryption;
            }

            $transport = Transport::fromDsn($dsn);

            return new Mailer($transport);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
