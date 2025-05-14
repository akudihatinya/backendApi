<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\RepositoryInterface;
use Illuminate\Database\Eloquent\Model;

abstract class BaseRepository implements RepositoryInterface
{
    /**
     * The model instance.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $model;

    /**
     * Create a new repository instance.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return void
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Get all records
     *
     * @param array $columns
     * @return mixed
     */
    public function all(array $columns = ['*'])
    {
        return $this->model->all($columns);
    }

    /**
     * Find a record by ID
     *
     * @param mixed $id
     * @param array $columns
     * @return mixed
     */
    public function find($id, array $columns = ['*'])
    {
        return $this->model->find($id, $columns);
    }

    /**
     * Find records matching given criteria
     *
     * @param array $criteria
     * @param array $columns
     * @return mixed
     */
    public function findBy(array $criteria, array $columns = ['*'])
    {
        $query = $this->model->newQuery();

        foreach ($criteria as $key => $value) {
            $query->where($key, $value);
        }

        return $query->get($columns);
    }

    /**
     * Create a new record
     *
     * @param array $attributes
     * @return mixed
     */
    public function create(array $attributes)
    {
        return $this->model->create($attributes);
    }

    /**
     * Update a record
     *
     * @param mixed $id
     * @param array $attributes
     * @return mixed
     */
    public function update($id, array $attributes)
    {
        $record = $this->find($id);

        if ($record) {
            $record->update($attributes);
            return $record;
        }

        return false;
    }

    /**
     * Delete a record
     *
     * @param mixed $id
     * @return bool
     */
    public function delete($id)
    {
        return $this->model->destroy($id) > 0;
    }
}