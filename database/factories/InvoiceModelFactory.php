<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Infrastructure\Persistence\Models\InvoiceModel;
use Ramsey\Uuid\Uuid;

/** @extends Factory<InvoiceModel> */
final class InvoiceModelFactory extends Factory
{
    protected $model = InvoiceModel::class;

    public function definition(): array
    {
        return [
            'id' => Uuid::uuid4()->toString(),
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'status' => StatusEnum::Draft->value,
        ];
    }

    public function sending(): self
    {
        return $this->state(['status' => StatusEnum::Sending->value]);
    }

    public function sentToClient(): self
    {
        return $this->state(['status' => StatusEnum::SentToClient->value]);
    }

    public function withProductLines(int $count = 1, array $attributes = []): self
    {
        return $this->has(ProductLineModelFactory::new()->count($count)->state($attributes), 'productLines');
    }
}
