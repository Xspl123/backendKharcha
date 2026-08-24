<?php

namespace App\Models\Concerns;

trait UsesCentralConnection
{
    public function getConnectionName()
    {
        return config('tenancy.database.central_connection', config('database.default'));
    }
}
