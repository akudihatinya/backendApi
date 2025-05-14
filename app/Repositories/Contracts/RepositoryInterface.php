<?php

namespace App\Repositories\Contracts;

interface RepositoryInterface
{
    /**
     * Get all records
     * 
     * @param array $columns
     * @return mixed
     */
    public function all(array $columns = ['*']);

    /**
     * Find a record by ID
     * 
     * @param mixed $id
     * @param array $columns
     * @return mixed
     */
    public function find($id, array $columns = ['*']);

    /**
     * Find records matching given criteria
     * 
     * @param array $criteria
     * @param array $columns
     * @return mixed
     */
    public function findBy(array $criteria, array $columns = ['*']);

    /**
     * Create a new record
     * 
     * @param array $attributes
     * @return mixed
     */
    public function create(array $attributes);

    /**
     * Update a record
     * 
     * @param mixed $id
     * @param array $attributes
     * @return mixed
     */
    public function update($id, array $attributes);

    /**
     * Delete a record
     * 
     * @param mixed $id
     * @return bool
     */
    public function delete($id);
}