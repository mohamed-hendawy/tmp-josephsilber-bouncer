<?php

namespace Silber\Bouncer\Database;

use Closure;
use App\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

use Silber\Bouncer\Database\Scope\Scope;
use Silber\Bouncer\Contracts\Scope as ScopeContract;

class Models
{
    /**
     * Map of bouncer's models.
     *
     * @var array
     */
    protected static $models = [];

    /**
     * Map of ownership for models.
     *
     * @var array
     */
    protected static $ownership = [];

    /**
     * Map of bouncer's tables.
     *
     * @var array
     */
    protected static $tables = [];

    /**
     * The model scoping instance.
     *
     * @var \Silber\Bouncer\Database\Scope\Scope
     */
    protected static $scope;

    /**
     * Get or set the model scoping instance.
     *
     * @param  \Silber\Bouncer\Contracts\Scope|null  $scope
     * @return mixed
     */
    public static function scope(ScopeContract $scope = null)
    {
        if (! is_null($scope)) {
            return static::$scope = $scope;
        }

        if (is_null(static::$scope)) {
            static::$scope = new Scope;
        }

        return static::$scope;
    }

    /**
     * Register an attribute/callback to determine if a model is owned by a given authority.
     *
     * @param  string|\Closure  $model
     * @param  string|\Closure|null  $attribute
     * @return void
     */
    public static function ownedVia($model, $attribute = null)
    {
        if (is_null($attribute)) {
            static::$ownership['*'] = $model;
        }

        static::$ownership[$model] = $attribute;
    }

    /**
     * Determines whether the given model is owned by the given authority.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $authority
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return bool
     */
    public static function isOwnedBy(Model $authority, Model $model)
    {
        $type = get_class($model);

        if (isset(static::$ownership[$type])) {
            $attribute = static::$ownership[$type];
        } elseif (isset(static::$ownership['*'])) {
            $attribute = static::$ownership['*'];
        } else {
            // Rinvex
            $attribute = strtolower(static::basename($authority));
            //$attribute = strtolower(static::basename($authority)).'_id';
        }

        return static::isOwnedVia($attribute, $authority, $model);
    }

    /**
     * Determines ownership via the given attribute.
     *
     * @param  string|\Closure  $attribute
     * @param  \Illuminate\Database\Eloquent\Model  $authority
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return bool
     */
    protected static function isOwnedVia($attribute, Model $authority, Model $model)
    {
        if ($attribute instanceof Closure) {
            return $attribute($model, $authority);
        }

        // Rinvex
        return $authority->getMorphClass() === $model->{$attribute.'_type'} && $authority->getKey() === $model->{$attribute.'_id'};
        // return $authority->getKey() == $model->{$attribute};
    }

    /**
     * Reset all settings to their original state.
     *
     * @return void
     */
    public static function reset()
    {
        static::$ownership = [];
    }

    /**
     * Get the basename of the given class.
     *
     * @param  string|object  $class
     * @return string
     */
    protected static function basename($class)
    {
        if ( ! is_string($class)) {
            $class = get_class($class);
        }

        $segments = explode('\\', $class);

        return end($segments);
    }
}
