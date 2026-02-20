<?php

namespace App\Repositories\Interfaces;

use App\Models\User;

interface UserRepositoryInterface
{
    public function register(array $data);
    public function findByEmail(string $email);
    public function logout(User $user);
    public function getUserById(int $id);
    public function resetPassword(array $credentials);
    public function getAllUsers();

}
