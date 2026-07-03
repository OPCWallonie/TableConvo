<?php

namespace Database\Factories;

use App\Enums\CompanyJoinRequestStatus;
use App\Models\Company;
use App\Models\CompanyJoinRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyJoinRequestFactory extends Factory
{
    protected $model = CompanyJoinRequest::class;

    public function definition(): array
    {
        return [
            'company_id'          => Company::factory(),
            'user_id'             => User::factory(),
            'status'              => CompanyJoinRequestStatus::Pending,
            'message'             => $this->faker->optional()->sentence(),
            'requested_at'        => now(),
            'resolved_at'         => null,
            'resolved_by_user_id' => null,
            'rejection_reason'    => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status'      => CompanyJoinRequestStatus::Approved,
            'resolved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status'           => CompanyJoinRequestStatus::Rejected,
            'resolved_at'      => now(),
            'rejection_reason' => $this->faker->sentence(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status'      => CompanyJoinRequestStatus::Cancelled,
            'resolved_at' => now(),
        ]);
    }
}
