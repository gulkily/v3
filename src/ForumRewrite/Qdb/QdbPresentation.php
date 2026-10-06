<?php

declare(strict_types=1);

namespace ForumRewrite\Qdb;

final class QdbPresentation
{
    /** @return list<array{href:string,label:string,section:string}> */
    public static function navigation(): array
    {
        return [
            ['href' => '/', 'label' => 'Welcome', 'section' => 'welcome'],
            ['href' => '/latest', 'label' => 'Latest', 'section' => 'latest'],
            ['href' => '/top', 'label' => 'Top', 'section' => 'top'],
            ['href' => '/random', 'label' => 'Random', 'section' => 'random'],
            ['href' => '/add', 'label' => 'Add Quote', 'section' => 'compose'],
            ['href' => '/search', 'label' => 'Search', 'section' => 'search'],
        ];
    }
}
