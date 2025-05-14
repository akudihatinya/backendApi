<?php

namespace App\Repositories\Contracts;

interface RepositoryInterface
{
    public function all(array $columns = ['*']);
    public function find($id, array $columns = ['*']);
    public function findBy(array $criteria, array $columns = ['*']);
    public function create(array $data);
    public function update($id, array $data);
    public function delete($id);
}