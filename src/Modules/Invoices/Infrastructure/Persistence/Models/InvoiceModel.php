<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Persistence\Models;

use Database\Factories\InvoiceModelFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $customer_name
 * @property string $customer_email
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, ProductLineModel> $productLines
 */
#[UseFactory(InvoiceModelFactory::class)]
final class InvoiceModel extends Model
{
    /** @use HasFactory<InvoiceModelFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $table = 'invoices';

    protected $keyType = 'string';

    protected $guarded = [];

    /** @return HasMany<ProductLineModel, $this> */
    public function productLines(): HasMany
    {
        return $this->hasMany(ProductLineModel::class, 'invoice_id')->orderBy('id');
    }
}
