<?php

namespace App\Providers;

use Laravel\Lumen\Providers\EventServiceProvider as Base;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Fitbit\FitbitExtendSocialite;
use SocialiteProviders\Twitter\TwitterExtendSocialite;

class EventServiceProvider extends Base
{
    protected $listen = [
        SocialiteWasCalled::class => [
            FitbitExtendSocialite::class . "@handle",
            TwitterExtendSocialite::class . "@handle",
        ],
    ];
}
