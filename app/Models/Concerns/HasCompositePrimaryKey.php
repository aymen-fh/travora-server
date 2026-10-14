<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait HasCompositePrimaryKey
{
    public $incrementing = false;

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
     */
    protected function setKeysForSaveQuery($query)
    {
        foreach ($this->compositeKeyValues() as $column => $value) {
            $query->where($column, $value);
        }

        return $query;
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
     */
    protected function setKeysForSelectQuery($query)
    {
        foreach ($this->compositeKeyValues() as $column => $value) {
            $query->where($column, $value);
        }

        return $query;
    }

    public function getKey(): mixed
    {
        return $this->compositeKeyValues();
    }

    /**
     * @return array<string, mixed>
     */
    private function compositeKeyValues(): array
    {
        $keys = [];

        foreach ((array) $this->getKeyName() as $column) {
            $keys[$column] = $this->getAttribute($column);
        }

        return $keys;
    }
}
