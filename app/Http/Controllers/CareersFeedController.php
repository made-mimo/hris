<?php

namespace App\Http\Controllers;

use App\Models\Vacancy;
use Illuminate\Http\Response;

/** Spec D1: "an RSS feed limited to published, open vacancies." */
class CareersFeedController extends Controller
{
    public function __invoke(): Response
    {
        $vacancies = Vacancy::where('is_open', true)->where('is_published', true)->latest()->get();

        $items = $vacancies->map(function (Vacancy $v) {
            $url = e(route('careers.show', $v));
            $title = e($v->title);
            $description = e($v->description ?? '');
            $pubDate = $v->created_at->toRfc2822String();

            return <<<XML
                <item>
                    <title>{$title}</title>
                    <link>{$url}</link>
                    <guid>{$url}</guid>
                    <pubDate>{$pubDate}</pubDate>
                    <description>{$description}</description>
                </item>
                XML;
        })->implode("\n");

        $feedUrl = e(route('careers.feed'));
        $siteUrl = e(route('careers'));

        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <rss version="2.0">
                <channel>
                    <title>Systems Intelligenz — Careers</title>
                    <link>{$siteUrl}</link>
                    <atom:link href="{$feedUrl}" rel="self" xmlns:atom="http://www.w3.org/2005/Atom"/>
                    <description>Open positions at Systems Intelligenz</description>
                    {$items}
                </channel>
            </rss>
            XML;

        return response($xml, 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
    }
}
