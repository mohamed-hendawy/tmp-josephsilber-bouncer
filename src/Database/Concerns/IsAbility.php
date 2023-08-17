<?php

namespace Silber\Bouncer\Database\Concerns;

use App\User;
use Silber\Bouncer\Helpers;
use Silber\Bouncer\Database\Role;
use Silber\Bouncer\Database\Models;
use Silber\Bouncer\Constraints\Group;
use Spatie\Activitylog\Traits\LogsActivity;
use Silber\Bouncer\Constraints\constrainer;
use Silber\Bouncer\Database\Titles\AbilityTitle;
use Silber\Bouncer\Database\Scope\TenantScope;
use Silber\Bouncer\Database\Queries\AbilitiesForModel;

trait IsAbility
{
    /**
     * Boot the is ability trait.
     *
     * @return void
     */
    public static function bootIsAbility()
    {
        TenantScope::register(static::class);

        static::creating(function ($ability) {
            Models::scope()->applyToModel($ability);

            if (is_null($ability->title)) {
                $ability->title = AbilityTitle::from($ability)->toString();
            }
        });
    }

    /**
     * Attach the given roles to the model.
     *
     * @param mixed $roles
     *
     * @return void
     */
    public function setRolesAttribute($roles): void
    {
        static::saved(function (self $model) use ($roles) {
            $roles = collect($roles)->filter();

            (! in_array(LogsActivity::class,class_uses_recursive($model)) || $model->roles->pluck('id')->similar($roles))
            || activity()
                ->performedOn($model)
                ->withProperties(['attributes' => ['roles' => $roles], 'old' => ['roles' => $model->roles->pluck('id')->toArray()]])
                ->log('updated');

            $model->roles()->sync($roles, true);
        });
    }

    /**
     * Get the options attribute.
     *
     * @return array
     */
    public function getOptionsAttribute()
    {
        if (empty($this->attributes['options'])) {
            return [];
        }

        return json_decode($this->attributes['options'], true);
    }

    /**
     * Set the "options" attribute.
     *
     * @param  array  $options
     * @return void
     */
    public function setOptionsAttribute(array $options)
    {
        $this->attributes['options'] = json_encode($options);
    }

    /**
     * CHecks if the ability has constraints.
     *
     * @return bool
     */
    public function hasConstraints()
    {
        return ! empty($this->options['constraints']);
    }

    /**
     * Get the ability's constraints.
     *
     * @return \Silber\Bouncer\Constraints\Constrainer
     */
    public function getConstraints()
    {
        if (empty($this->options['constraints'])) {
            return new Group();
        }

        $data = $this->options['constraints'];

        return $data['class']::fromData($data['params']);
    }

    /**
     * Set the ability's constraints.
     *
     * @param  \Silber\Bouncer\Constraints\Constrainer  $constrainer
     * @return $this
     */
    public function setConstraints(Constrainer $constrainer)
    {
        $this->options = array_merge($this->options, [
            'constraints' => $constrainer->data(),
        ]);

        return $this;
    }

    /**
     * Create a new ability for a specific model.
     *
     * @param  \Illuminate\Database\Eloquent\Model|string  $model
     * @param  string|array  $attributes
     * @return static
     */
    public static function createForModel($model, $attributes)
    {
        $model = static::makeForModel($model, $attributes);

        $model->save();

        return $model;
    }

    /**
     * Make a new ability for a specific model.
     *
     * @param  \Illuminate\Database\Eloquent\Model|string  $model
     * @param  string|array  $attributes
     * @return static
     */
    public static function makeForModel($model, $attributes)
    {
        if (is_string($attributes)) {
            $attributes = ['name' => $attributes];
        }

        if ($model === '*') {
            return (new static)->forceFill($attributes + [
                'entity_type' => '*',
            ]);
        }

        if (is_string($model)) {
            $model = app($model);
        }

        return (new static)->forceFill($attributes + [
            'entity_type' => $model->getMorphClass(),
            'entity_id'   => $model->exists ? $model->getKey() : null,
        ]);
    }

    /**
     * The roles relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function roles()
    {
        $relation = $this->morphedByMany(
            config('cortex.auth.models.role'),
            'entity',
            config('cortex.auth.tables.permissions')
        )->withPivot('forbidden', 'scope');

        return Models::scope()->applyToRelation($relation);
    }

    /**
     * The users relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function users(string $class)
    {
        $relation = $this->morphedByMany(
            $class,
            'entity',
            config('cortex.auth.tables.permissions')
        )->withPivot('forbidden', 'scope');

        return Models::scope()->applyToRelation($relation);
    }

    /**
     * Get the identifier for this ability.
     *
     * @return string
     */
    final public function getIdentifierAttribute()
    {
        $slug = $this->attributes['name'];

        if ($this->attributes['entity_type']) {
            $slug .= '-'.$this->attributes['entity_type'];
        }

        if ($this->attributes['entity_id']) {
            $slug .= '-'.$this->attributes['entity_id'];
        }

        if ($this->attributes['only_owned']) {
            $slug .= '-owned';
        }

        return strtolower($slug);
    }

    /**
     * Get the ability's "slug" attribute.
     *
     * @return string
     */
    public function getSlugAttribute()
    {
        return $this->getIdentifierAttribute();
    }

    /**
     * Constrain a query to having the given name.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     * @return string|array  $name
     * @return bool  $strict
     * @return void
     */
    public function scopeByName($query, $name, $strict = false)
    {
        $names = (array) $name;

        if (! $strict && $name !== '*') {
            $names[] = '*';
        }

        $query->whereIn("{$this->table}.name", $names);
    }

    /**
     * Constrain a query to simple abilities.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     * @return void
     */
    public function scopeSimpleAbility($query)
    {
        $query->whereNull("{$this->table}.entity_type");
    }

    /**
     * Constrain a query to an ability for a specific model.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Model|string  $model
     * @param  bool  $strict
     * @return void
     */
    public function scopeForModel($query, $model, $strict = false)
    {
        (new AbilitiesForModel)->constrain($query, $model, $strict);
    }
}
