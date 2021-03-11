<?php

namespace Somar\Search\Utils;

use SilverStripe\Core\Convert;

class Helpers
{
    /**
     * Strips out html and new lines
     *
     * @param array $blocks
     * @return string
     */
    public static function get_blocks_plain_content(array $blocks): string
    {
        $content = array_reduce($blocks, fn ($str, $block) => $str .= $block->forTemplate(), '');

        // Strip line breaks & multiple spaces from elemental markup
        $content = preg_replace('!\s+!', ' ', $content);

        // Strip HTML tags, decode HTML entities back to plain text & trim whitespaces
        return trim(html_entity_decode(strip_tags($content), ENT_QUOTES, 'UTF-8'));
    }
}
