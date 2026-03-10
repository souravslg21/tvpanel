<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait HasUserFiltering
{
    /**
     * Get the Eloquent query for the resource.
     * Filters by user_id.
     */
    public static function getEloquentQuery(): Builder
    {
        $model = static::getModel();
        $table = (new $model())->getTable();

        return parent::getEloquentQuery()
            ->where($table.'.user_id', auth()->id());
    }

    /**
     * Get the global search Eloquent query for the resource.
     * Filters by user_id.
     */
    public static function getGlobalSearchEloquentQuery(): Builder
    {
        $model = static::getModel();
        $table = (new $model())->getTable();

        return parent::getGlobalSearchEloquentQuery()
            ->where($table.'.user_id', auth()->id());
    }
}
