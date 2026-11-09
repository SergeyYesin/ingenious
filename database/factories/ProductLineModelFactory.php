<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Invoices\Infrastructure\Persistence\Models\InvoiceModel;
use Modules\Invoices\Infrastructure\Persistence\Models\ProductLineModel;
use Ramsey\Uuid\Uuid;

/** @extends Factory<ProductLineModel> */
final class ProductLineModelFactory extends Factory
{
    protected $model = ProductLineModel::class;

    public function definition(): array
    {
        return [
            'id' => Uuid::uuid7()->toString(),
            'invoice_id' => InvoiceModel::factory(),
            'name' => fake()->words(2, true),
            'quantity' => fake()->numberBetween(1, 10),
            'unit_price' => fake()->numberBetween(1, 10000),
        ];
    }
}
