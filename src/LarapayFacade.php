<?php

namespace Farayaz\Larapay;

use Illuminate\Support\Facades\Facade;

class LarapayFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    public static function getFacadeAccessor()
    {
        return 'larapay';
    }
}
