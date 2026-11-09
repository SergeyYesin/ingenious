<?php

declare(strict_types=1);

namespace Tests\Feature\Invoice\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class CreateInvoiceControllerTest extends TestCase
{
    public function test_returns_draft_invoice_with_totals(): void
    {
        $response = $this->postJson(route('invoices.store'), $this->invoicePayload());

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.customer_name', 'Jane Doe')
            ->assertJsonPath('data.customer_email', 'jane@example.com')
            ->assertJsonCount(2, 'data.product_lines')
            ->assertJsonPath('data.total_price', 350);

        $this->assertDatabaseHas('invoices', ['id' => $response->json('data.id'), 'status' => 'draft']);
        $this->assertDatabaseCount('invoice_product_lines', 2);
    }

    public function test_accepts_missing_product_lines(): void
    {
        $payload = $this->invoicePayload();
        unset($payload['product_lines']);

        $this->postJson(route('invoices.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.product_lines', [])
            ->assertJsonPath('data.total_price', 0);
    }

    public function test_accepts_zero_quantity_line(): void
    {
        $this->postJson(route('invoices.store'), $this->invoicePayload([
            'product_lines' => [['name' => 'Widget', 'quantity' => 0, 'unit_price' => 100]],
        ]))->assertCreated()->assertJsonPath('data.product_lines.0.total_unit_price', 0);
    }

    #[DataProvider('invalidPayloads')]
    public function test_rejects_invalid_payload(array $overrides, string $errorKey): void
    {
        $this->postJson(route('invoices.store'), $this->invoicePayload($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($errorKey);

        $this->assertDatabaseCount('invoices', 0);
    }

    public static function invalidPayloads(): iterable
    {
        yield 'missing name' => [['customer_name' => ''], 'customer_name'];
        yield 'invalid email' => [['customer_email' => 'nope'], 'customer_email'];
        yield 'negative quantity' => [['product_lines' => [['name' => 'A', 'quantity' => -1, 'unit_price' => 1]]], 'product_lines.0.quantity'];
        yield 'fractional unit price' => [['product_lines' => [['name' => 'A', 'quantity' => 1, 'unit_price' => 1.5]]], 'product_lines.0.unit_price'];
        yield 'missing line name' => [['product_lines' => [['quantity' => 1, 'unit_price' => 1]]], 'product_lines.0.name'];
        yield 'email without domain dot' => [['customer_email' => 'jane@localhost'], 'customer_email'];
        yield 'non ascii email' => [['customer_email' => 'jürgen@example.com'], 'customer_email'];
        yield 'boolean quantity' => [['product_lines' => [['name' => 'A', 'quantity' => true, 'unit_price' => 1]]], 'product_lines.0.quantity'];
        yield 'string unit price' => [['product_lines' => [['name' => 'A', 'quantity' => 1, 'unit_price' => '5']]], 'product_lines.0.unit_price'];
        yield 'quantity above limit' => [['product_lines' => [['name' => 'A', 'quantity' => 1000001, 'unit_price' => 1]]], 'product_lines.0.quantity'];
        yield 'unit price above limit' => [['product_lines' => [['name' => 'A', 'quantity' => 1, 'unit_price' => 2147483648]]], 'product_lines.0.unit_price'];
        yield 'too many lines' => [['product_lines' => array_fill(0, 101, ['name' => 'A', 'quantity' => 1, 'unit_price' => 1])], 'product_lines'];
        yield 'null product lines' => [['product_lines' => null], 'product_lines'];
        yield 'scalar product lines' => [['product_lines' => 'Widget'], 'product_lines'];
    }

    public function test_accepts_largest_allowed_invoice(): void
    {
        $this->postJson(route('invoices.store'), $this->invoicePayload([
            'product_lines' => array_fill(0, 100, ['name' => 'A', 'quantity' => 1000000, 'unit_price' => 2147483647]),
        ]))->assertCreated()->assertJsonPath('data.total_price', 214748364700000000);
    }

    public function test_keeps_product_line_order(): void
    {
        $names = ['First', 'Second', 'Third', 'Fourth', 'Fifth'];

        $id = $this->postJson(route('invoices.store'), $this->invoicePayload([
            'product_lines' => array_map(
                static fn (string $name): array => ['name' => $name, 'quantity' => 1, 'unit_price' => 1],
                $names,
            ),
        ]))->assertCreated()->json('data.id');

        self::assertSame($names, $this->getJson(route('invoices.show', $id))->json('data.product_lines.*.name'));
    }

    private function invoicePayload(array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'product_lines' => [
                ['name' => 'Widget', 'quantity' => 2, 'unit_price' => 100],
                ['name' => 'Gadget', 'quantity' => 3, 'unit_price' => 50],
            ],
        ], $overrides);
    }
}
