<?php

namespace App\DataTransferObjects;

class UserData
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $username,
        public readonly ?string $password,
        public readonly string $name,
        public readonly ?string $profile_picture,
        public readonly string $role,
        public readonly ?int $puskesmas_id
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? null,
            username: $data['username'],
            password: $data['password'] ?? null,
            name: $data['name'],
            profile_picture: $data['profile_picture'] ?? null,
            role: $data['role'],
            puskesmas_id: $data['puskesmas_id'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'password' => $this->password,
            'name' => $this->name,
            'profile_picture' => $this->profile_picture,
            'role' => $this->role,
            'puskesmas_id' => $this->puskesmas_id,
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isPuskesmas(): bool
    {
        return $this->role === 'puskesmas';
    }
}