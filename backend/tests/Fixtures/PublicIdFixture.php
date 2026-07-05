<?php

namespace Tests\Fixtures;

use App\Core\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

/**
 * Test-only fixture exercising App\Core\Concerns\HasPublicId in isolation,
 * since no real domain aggregate exists yet to attach it to.
 */
class PublicIdFixture extends Model
{
    use HasPublicId;

    protected $table = 'public_id_fixtures';

    protected $fillable = ['name', 'public_id'];
}
