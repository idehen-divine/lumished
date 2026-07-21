<?php

namespace App\Repositories\User;

use App\Models\User;
use L0n3ly\LaravelRepositoryWithService\Implementations\Eloquent;

class UserRepositoryImplement extends Eloquent implements UserRepository
{
    public function __construct(User $model)
    {
        $this->model = $model;
    }

    /** {@inheritDoc} */
    public function findByEmail(string $email): ?User
    {
        return $this->model->where('email', $email)->first();
    }

    /** {@inheritDoc} */
    public function createCustomer(array $data): User
    {
        $user = $this->model->create($data);

        $user->setRole('CUSTOMER');

        return $user->load(['roles']);
    }
}
