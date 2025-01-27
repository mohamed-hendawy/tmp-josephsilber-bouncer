<?php

namespace Silber\Bouncer\Database\Concerns;

use Illuminate\Container\Container;

use Silber\Bouncer\Helpers;
use Silber\Bouncer\Database\Models;
use Silber\Bouncer\Database\Ability;
use Silber\Bouncer\Contracts\Clipboard;
use Spatie\Activitylog\Traits\LogsActivity;
use Silber\Bouncer\Conductors\GivesAbilities;
use Silber\Bouncer\Conductors\ForbidsAbilities;
use Silber\Bouncer\Conductors\RemovesAbilities;
use Silber\Bouncer\Conductors\UnforbidsAbilities;

trait HasAbilities
{
    /**
     * Boot the HasAbilities trait.
     *
     * @return void
     */
    public static function bootHasAbilities()
    {
        static::deleted(function ($model) {
            if (! Helpers::isSoftDeleting($model)) {
                $model->abilities()->detach();
            }
        });
    }

    /**
     * Attach the given abilities to the model.
     *
     * @param mixed $abilities
     *
     * @return void
     */
    public function setAbilitiesAttribute($abilities): void
    {
        static::saved(function (self $model) use ($abilities) {
            $abilities = collect($abilities)->filter();

            (! in_array(LogsActivity::class,class_uses_recursive($model)) || $model->abilities->pluck('id')->similar($abilities))
            || activity()
                ->performedOn($model)
                ->withProperties(['attributes' => ['abilities' => $abilities], 'old' => ['abilities' => $model->abilities->pluck('id')->toArray()]])
                ->log('updated');

            $model->abilities()->sync($abilities, true);
        });
    }

    /**
     * The abilities relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function abilities()
    {
        $relation = $this->morphToMany(
            config('cortex.auth.models.ability'),
            'entity',
            config('cortex.auth.tables.permissions')
        )->withPivot('forbidden', 'scope');

        return Models::scope()->applyToRelation($relation);
    }

    /**
     * Get all of the model's allowed abilities.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAbilities()
    {
        return Container::getInstance()
            ->make(Clipboard::class)
            ->getAbilities($this);
    }

    /**
     * Get all of the model's allowed abilities.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getForbiddenAbilities()
    {
        return Container::getInstance()
            ->make(Clipboard::class)
            ->getAbilities($this, false);
    }

    /**
     * Give an ability to the model.
     *
     * @param  mixed  $ability
     * @param  mixed|null  $model
     * @return \Silber\Bouncer\Conductors\GivesAbilities|$this
     */
    public function allow($ability = null, $model = null)
    {
        if (is_null($ability)) {
            return new GivesAbilities($this);
        }

        (new GivesAbilities($this))->to($ability, $model);

        return $this;
    }

    /**
     * Remove an ability from the model.
     *
     * @param  mixed  $ability
     * @param  mixed|null  $model
     * @return \Silber\Bouncer\Conductors\RemovesAbilities|$this
     */
    public function disallow($ability = null, $model = null)
    {
        if (is_null($ability)) {
            return new RemovesAbilities($this);
        }

        (new RemovesAbilities($this))->to($ability, $model);

        return $this;
    }

    /**
     * Forbid an ability to the model.
     *
     * @param  mixed  $ability
     * @param  mixed|null  $model
     * @return \Silber\Bouncer\Conductors\ForbidsAbilities|$this
     */
    public function forbid($ability = null, $model = null)
    {
        if (is_null($ability)) {
            return new ForbidsAbilities($this);
        }

        (new ForbidsAbilities($this))->to($ability, $model);

        return $this;
    }

    /**
     * Remove ability forbiddal from the model.
     *
     * @param  mixed  $ability
     * @param  mixed|null  $model
     * @return \Silber\Bouncer\Conductors\UnforbidsAbilities|$this
     */
    public function unforbid($ability = null, $model = null)
    {
        if (is_null($ability)) {
            return new UnforbidsAbilities($this);
        }

        (new UnforbidsAbilities($this))->to($ability, $model);

        return $this;
    }
}
