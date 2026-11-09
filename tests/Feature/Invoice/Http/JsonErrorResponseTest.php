<?php

declare(strict_types=1);

namespace Tests\Feature\Invoice\Http;

use Ramsey\Uuid\Uuid;
use Tests\TestCase;

final class JsonErrorResponseTest extends TestCase
{
    public function test_validation_errors_are_json_without_accept_header(): void
    {
        $this->post(route('invoices.store'), ['customer_name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_name', 'customer_email']);
    }

    public function test_unknown_invoice_is_json_without_accept_header(): void
    {
        $this->get(route('invoices.show', Uuid::uuid4()->toString()))
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }

    public function test_malformed_invoice_id_is_json_without_accept_header(): void
    {
        $this->get('/api/invoices/not-a-uuid')
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }
}
