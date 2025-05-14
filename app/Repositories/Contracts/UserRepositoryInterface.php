<?php

namespace App\Repositories\Contracts;

interface UserRepositoryInterface extends RepositoryInterface
{
    /**
     * Find a user by username
     * 
     * @param string $username
     * @return mixed
     */
    public function findByUsername(string $username);

    /**
     * Find all users with puskesmas role
     * 
     * @return mixed
     */
    public function findPuskesmasUsers();

    /**
     * Find all users with admin role
     * 
     * @return mixed
     */
    public function findAdminUsers();
}