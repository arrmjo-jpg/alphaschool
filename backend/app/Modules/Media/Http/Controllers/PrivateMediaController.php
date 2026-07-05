<?php

namespace App\Modules\Media\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Media\Models\Media;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Serves private-tier media exclusively through this authenticated route
 * -- never a raw signed URL -- so a revoked permission takes effect
 * instantly on the next request, per docs/DOMAIN_BLUEPRINT.md §12's
 * storage-and-serving decision.
 */
class PrivateMediaController extends Controller
{
    public function show(Media $media)
    {
        Gate::authorize('view', $media);

        return Storage::disk($media->disk)->response(
            $media->getPathRelativeToRoot(),
            $media->file_name,
        );
    }
}
