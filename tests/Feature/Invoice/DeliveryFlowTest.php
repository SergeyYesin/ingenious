<?php

declare(strict_types=1);

namespace Tests\Feature\Invoice;

use Illuminate\Testing\TestResponse;
use Modules\Invoices\Infrastructure\Persistence\Models\InvoiceModel;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

final class DeliveryFlowTest extends TestCase
{
    public function test_delivered_webhook_marks_sent_invoice_as_sent_to_client(): void
    {
        $id = $this->createInvoice();
        $this->postJson(route('invoices.send', $id))
            ->assertAccepted();

        $this->deliver($id)->assertNoContent();

        $this->getJson(route('invoices.show', $id))
            ->assertJsonPath('data.status', 'sent-to-client');
    }

    public function test_delivered_webhook_ignores_draft_invoice(): void
    {
        $id = $this->createInvoice();

        $this->deliver($id)->assertNoContent();

        $this->getJson(route('invoices.show', $id))
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_repeated_delivered_webhook_keeps_sent_to_client(): void
    {
        $id = $this->createInvoice();
        $this->postJson(route('invoices.send', $id))
            ->assertAccepted();

        $this->deliver($id)->assertNoContent();
        $this->deliver($id)->assertNoContent();

        $this->getJson(route('invoices.show', $id))
            ->assertJsonPath('data.status', 'sent-to-client');
    }

    public function test_delivered_webhook_for_unknown_resource_is_ignored(): void
    {
        $this->deliver(Uuid::uuid4()->toString())
            ->assertNoContent();

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_delivered_invoice_cannot_be_sent_again(): void
    {
        $id = $this->createInvoice();

        $this->postJson(route('invoices.send', $id))
            ->assertAccepted();

        $this->deliver($id);

        $this->postJson(route('invoices.send', $id))
            ->assertConflict();
    }

    private function deliver(string $id): TestResponse
    {
        return $this->getJson(route('notification.hook', ['action' => 'delivered', 'reference' => $id]));
    }

    private function createInvoice(): string
    {
        return InvoiceModel::factory()->withProductLines()->create()->id;
    }
}
