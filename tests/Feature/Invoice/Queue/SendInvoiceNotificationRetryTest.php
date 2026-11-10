<?php

declare(strict_types=1);

namespace Tests\Feature\Invoice\Queue;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Invoices\Infrastructure\Persistence\Models\InvoiceModel;
use Tests\Support\Invoices\FakesInvoiceNotifier;
use Tests\TestCase;

final class SendInvoiceNotificationRetryTest extends TestCase
{
    use FakesInvoiceNotifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeTime();

        config([
            'queue.default' => 'database',
            'queue.failed.driver' => 'null',
        ]);

        Schema::create('jobs', static function (Blueprint $table): void {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }

    public function test_retries_with_backoff_and_returns_invoice_to_draft_after_last_attempt(): void
    {
        $notifier = $this->fakeInvoiceNotifier()->failTimes(3);
        $id = $this->sendInvoice();

        $this->work();
        $this->assertStatus($id, 'sending');
        $this->assertDatabaseHas('jobs', ['attempts' => 1]);

        $this->travel(9)->seconds();
        $this->work();
        $this->assertDatabaseHas('jobs', ['attempts' => 1]);

        $this->travel(2)->seconds();
        $this->work();
        $this->assertStatus($id, 'sending');
        $this->assertDatabaseHas('jobs', ['attempts' => 2]);

        $this->travel(31)->seconds();
        $this->work();
        $this->assertStatus($id, 'draft');
        $this->assertDatabaseCount('jobs', 0);
        self::assertSame([], $notifier->sent);
    }

    public function test_stops_retrying_once_notification_succeeds(): void
    {
        $notifier = $this->fakeInvoiceNotifier()->failTimes(1);
        $id = $this->sendInvoice();

        $this->work();
        $this->travel(11)->seconds();
        $this->work();

        $this->assertStatus($id, 'sending');
        $this->assertDatabaseCount('jobs', 0);
        self::assertCount(1, $notifier->sent);
    }

    private function sendInvoice(): string
    {
        $id = InvoiceModel::factory()->withProductLines()->create()->id;

        $this->postJson(route('invoices.send', $id))->assertAccepted();
        $this->artisan('invoices:relay-outbox')->assertSuccessful();

        return $id;
    }

    private function work(): void
    {
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertExitCode(0);
    }

    private function assertStatus(string $id, string $status): void
    {
        self::assertSame($status, DB::table('invoices')->where('id', $id)->value('status'));
    }
}
