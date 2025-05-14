<?php

namespace App\Repositories\Contracts;

interface UserRepositoryInterface extends RepositoryInterface
{
    public function findByUsername(string $username);
    public function findPuskesmasUsers();
    public function findAdminUsers();
}