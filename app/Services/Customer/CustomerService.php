<?php

namespace App\Services\Customer;

use App\Exceptions\BusinessConflictException;
use App\Models\Customer;

class CustomerService
{
    public function findByPhone(string $phone): ?Customer
    {
        return Customer::query()->where('phone', $phone)->first();
    }

    /**
     * @param  array{name: string, phone: string, notes?: string|null}  $data
     */
    public function register(array $data): Customer
    {
        $customer = $this->findByPhone($data['phone']);

        if ($customer) {
            $customer->fill([
                'name' => $data['name'],
                'notes' => $data['notes'] ?? $customer->notes,
                'is_active' => true,
            ]);
            $customer->save();

            return $customer->fresh();
        }

        return Customer::query()->create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'notes' => $data['notes'] ?? null,
            'is_active' => true,
        ]);
    }

    public function requireActive(Customer $customer): Customer
    {
        if (! $customer->is_active) {
            throw new BusinessConflictException('This customer is inactive and cannot be used for new orders.');
        }

        return $customer;
    }
}
