<?php

namespace App\Services;

use League\CommonMark\GithubFlavoredMarkdownConverter;

class NotificationMarkdownService
{
    public function render(?string $markdown): string
    {
        $markdown = (string) $markdown;
        $markdown = preg_replace('/<(script|style|iframe|object|embed|form)[^>]*>.*?<\/\1>/is', '', $markdown) ?? '';
        $markdown = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $markdown) ?? '';
        $converter = new GithubFlavoredMarkdownConverter(['html_input' => 'strip', 'allow_unsafe_links' => false]);
        $html = $converter->convert($markdown)->getContent();
        return preg_replace_callback('/<(a|img)\b([^>]*)>/i', function (array $m): string {
            $attrs = preg_replace_callback('/\s(href|src)\s*=\s*(["\'])(.*?)\2/i', function (array $a): string {
                $url = trim($a[3]);
                return preg_match('/^https:\/\//i', $url) ? $a[0] : '';
            }, $m[2]) ?? '';
            return '<'.$m[1].$attrs.'>';
        }, $html) ?? $html;
    }
}
