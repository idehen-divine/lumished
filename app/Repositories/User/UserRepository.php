<?php

namespace App\Repositories\User;

use App\Models\User;
use L0n3ly\LaravelRepositoryWithService\Contracts\Repository;

interface UserRepository extends Repository
{
    /**
     * Find a user by their email address.
     *
     * @param  string  $email  The email address to search for
     * @return User|null The user model if found, otherwise null
     */
    public function findByEmail(string $email): ?User;

    /**
     * Create a new customer user and assign the CUSTOMER role.
     *
     * @param  array  $data  The user data (first_name, last_name, email, password)
     * @return User The newly created user with the CUSTOMER role assigned
     */
    public function createCustomer(array $data): User;
}
