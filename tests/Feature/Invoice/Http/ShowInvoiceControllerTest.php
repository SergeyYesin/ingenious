<?php

declare(strict_types=1);

namespace Tests\Feature\Invoice\Http;

use Modules\Invoices\Infrastructure\Persistence\Models\InvoiceModel;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

final class ShowInvoiceControllerTest extends TestCase
{
    public function test_returns_invoice(): void
    {
        $invoice = InvoiceModel::factory()
            ->sending()
            ->withProductLines(2, ['quantity' => 2, 'unit_price' => 100])
            ->create();

        $this->getJson(route('invoices.show', $invoice->id))
            ->assertOk()
            ->assertJsonPath('data.id', $invoice->id)
            ->assertJsonPath('data.status', 'sending')
            ->assertJsonPath('data.customer_email', $invoice->customer_email)
            ->assertJsonPath('data.product_lines.0.total_unit_price', 200)
            ->assertJsonPath('data.total_price', 400);
    }

    public function test_returns_not_found_for_unknown_invoice(): void
    {
        $this->getJson(route('invoices.show', Uuid::uuid4()->toString()))->assertNotFound();
    }

    public function test_returns_not_found_for_malformed_id(): void
    {
        $this->getJson('/api/invoices/not-a-uuid')->assertNotFound();
    }
}
