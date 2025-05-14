<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    public function findByUsername(string $username)
    {
        return $this->model->where('username', $username)->first();
    }

    public function findPuskesmasUsers()
    {
        return $this->model->where('role', 'puskesmas')->get();
    }

    public function findAdminUsers()
    {
        return $this->model->where('role', 'admin')->get();
    }
}