<?php

namespace Cultpantry\Elections\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Cultpantry\Elections\Actions\BuildTagDirectory;
use Cultpantry\Elections\Actions\BuildTagPage;
use Cultpantry\Elections\Http\Controllers\Admin\Concerns\ElectionsAdminMiddleware;
use Cultpantry\Elections\Models\Tag;
use Cultpantry\Elections\Support\Options;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Browse the election by subject: the tag directory, and one page per tag
 * gathering everything filed under it. Read-only; tags come in through the
 * XML import like everything else.
 */
class TagController extends Controller implements HasMiddleware
{
    use ElectionsAdminMiddleware;

    public function index(BuildTagDirectory $buildTagDirectory): Response
    {
        $this->authorize('viewAny', Tag::class);

        return Inertia::render('Vendor/elections/Tags/Index', [
            ...$buildTagDirectory->handle(),
        ]);
    }

    public function show(Tag $tag, BuildTagPage $buildTagPage): Response
    {
        $this->authorize('view', $tag);

        return Inertia::render('Vendor/elections/Tags/Show', [
            ...$buildTagPage->handle($tag),
            'options' => Options::all(),
        ]);
    }
}
