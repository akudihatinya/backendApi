<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\RepositoryInterface;
use Illuminate\Database\Eloquent\Model;

abstract class BaseRepository implements RepositoryInterface
{
    protected $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function all(array $columns = ['*'])
    {
        return $this->model->get($columns);
    }

    public function find($id, array $columns = ['*'])
    {
        return $this->model->find($id, $columns);
    }

    public function findBy(array $criteria, array $columns = ['*'])
    {
        $query = $this->model->newQuery();
        
        foreach ($criteria as $key => $value) {
            $query->where($key, $value);
        }
        
        return $query->get($columns);
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function update($id, array $data)
    {
        $record = $this->find($id);
        
        if ($record) {
            $record->update($data);
            return $record;
        }
        
        return false;
    }

    public function delete($id)
    {
        return $this->model->destroy($id);
    }
}