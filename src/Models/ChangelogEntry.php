<?php

namespace Cultpantry\Elections\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One line of the site's change log, written for people who don't read code:
 * what is new or different on the site, in a sentence or two. Written by hand,
 * not copied from commit messages.
 *
 * @property int $id
 * @property string $key
 * @property \Illuminate\Support\Carbon $committed_at when the change went in; the email announces it if that falls in its window
 * @property string $title
 * @property string $body
 * @property string $scope both | provincial | municipal
 * @property string $page one of ChangelogEntry::PAGES
 * @property int $impact 1 to 5, overall effect on the user experience
 * @property string|null $url
 * @property string|null $commits the commit(s) behind it, for the maintainer
 */
class ChangelogEntry extends Model
{
    public const SCOPES = ['both' => 'Both elections', 'provincial' => 'BC provincial election', 'municipal' => 'Salmon Arm election'];

    /** Which page of the tracker a change belongs to. */
    public const PAGES = [
        'all' => 'Every page',
        'hub' => 'Elections home',
        'dashboards' => 'Both dashboards',
        'municipal' => 'Salmon Arm dashboard',
        'provincial' => 'BC provincial dashboard',
        'candidate' => 'Candidate pages',
        'provincial-candidate' => 'BC provincial candidate pages',
        'party' => 'Party pages',
        'polls' => 'Poll history',
        'city-plans' => 'City plans and finances',
        'email' => 'Update email',
    ];

    /** How much a change affects the user experience overall, 1 to 5. */
    public const IMPACTS = [1 => 'Small', 2 => 'Minor', 3 => 'Noticeable', 4 => 'Major', 5 => 'Big'];

    protected $table = 'elections_changelog';

    protected $fillable = ['key', 'committed_at', 'title', 'body', 'scope', 'page', 'impact', 'url', 'commits'];

    protected function casts(): array
    {
        return ['committed_at' => 'datetime'];
    }
}
