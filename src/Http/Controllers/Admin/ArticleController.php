<?php

namespace Cultpantry\Elections\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Cultpantry\Elections\Http\Controllers\Admin\Concerns\ElectionsAdminMiddleware;
use Cultpantry\Elections\Models\Article;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Cultpantry\Elections\Models\Tag;

/**
 * Articles only arrive by XML import; this is just the way to drop one that
 * turned out to be irrelevant or mislinked.
 */
class ArticleController extends Controller implements HasMiddleware
{
    use ElectionsAdminMiddleware;

    public function destroy(Article $article): RedirectResponse
    {
        $this->authorize('delete', $article);

        $article->delete();
        Tag::pruneOrphans();

        return redirect()->back()->with('success', 'Article removed.');
    }
}
