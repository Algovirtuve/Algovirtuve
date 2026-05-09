<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

if (! function_exists('insert')) {
    /**
     * @param  Builder<Model>|Relation<Model, Model>|class-string<Model>  $model
     * @param  array<string, mixed>  $attributes
     */
    function insert(string|Builder|Relation $model, array $attributes = []): Model
    {
        if ($model instanceof Builder || $model instanceof Relation) {
            return $model->create($attributes);
        }

        if (! is_subclass_of($model, Model::class)) {
            throw new InvalidArgumentException('Insert expects an Eloquent model class name, builder, or relation.');
        }

        return $model::query()->create($attributes);
    }
}
