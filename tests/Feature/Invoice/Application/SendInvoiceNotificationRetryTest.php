<?php

declare(strict_types=1);

namespace Tests\Feature\Invoice\Application;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;
use Modules\Invoices\Infrastructure\Persistence\Models\InvoiceModel;
use Modules\Notifications\Api\NotificationFacadeInterface;
use RuntimeException;
use Tests\TestCase;

final class SendInvoiceNotificationRetryTest extends TestCase
{
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
        $id = InvoiceModel::factory()->withProductLines()->create()->id;

        $this->mock(NotificationFacadeInterface::class, static function (MockInterface $mock): void {
            $mock->shouldReceive('notify')->times(3)->andThrow(new RuntimeException('Mail server down'));
        });

        $this->postJson(route('invoices.send', $id))->assertAccepted();

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
    }

    public function test_stops_retrying_once_notification_succeeds(): void
    {
        $id = InvoiceModel::factory()->withProductLines()->create()->id;

        $this->mock(NotificationFacadeInterface::class, static function (MockInterface $mock): void {
            $mock->shouldReceive('notify')->once()->andThrow(new RuntimeException('Mail server down'));
            $mock->shouldReceive('notify')->once();
        });

        $this->postJson(route('invoices.send', $id))->assertAccepted();

        $this->work();
        $this->travel(11)->seconds();
        $this->work();

        $this->assertStatus($id, 'sending');
        $this->assertDatabaseCount('jobs', 0);
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
