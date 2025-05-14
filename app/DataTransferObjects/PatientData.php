<?php

namespace App\DataTransferObjects;

use Carbon\Carbon;

class PatientData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $puskesmas_id,
        public readonly ?string $nik,
        public readonly ?string $bpjs_number,
        public readonly ?string $medical_record_number,
        public readonly string $name,
        public readonly ?string $address,
        public readonly ?string $gender,
        public readonly ?string $birth_date,
        public readonly ?int $age,
        public readonly array $ht_years = [],
        public readonly array $dm_years = []
    ) {
        // Calculate age from birth_date if age is null but birth_date is provided
        if ($this->age === null && $this->birth_date !== null) {
            $birthDate = Carbon::parse($this->birth_date);
            $this->age = $birthDate->age;
        }
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? null,
            puskesmas_id: $data['puskesmas_id'],
            nik: $data['nik'] ?? null,
            bpjs_number: $data['bpjs_number'] ?? null,
            medical_record_number: $data['medical_record_number'] ?? null,
            name: $data['name'],
            address: $data['address'] ?? null,
            gender: $data['gender'] ?? null,
            birth_date: $data['birth_date'] ?? null,
            age: $data['age'] ?? null,
            ht_years: $data['ht_years'] ?? [],
            dm_years: $data['dm_years'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'puskesmas_id' => $this->puskesmas_id,
            'nik' => $this->nik,
            'bpjs_number' => $this->bpjs_number,
            'medical_record_number' => $this->medical_record_number,
            'name' => $this->name,
            'address' => $this->address,
            'gender' => $this->gender,
            'birth_date' => $this->birth_date,
            'age' => $this->age,
            'ht_years' => $this->ht_years,
            'dm_years' => $this->dm_years,
        ];
    }

    public function hasHt(?int $year = null): bool
    {
        if ($year === null) {
            return !empty($this->ht_years);
        }
        
        return in_array($year, $this->ht_years);
    }

    public function hasDm(?int $year = null): bool
    {
        if ($year === null) {
            return !empty($this->dm_years);
        }
        
        return in_array($year, $this->dm_years);
    }
}