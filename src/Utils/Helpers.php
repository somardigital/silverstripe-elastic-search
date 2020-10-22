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

        // Strip line breaks from elemental markup
        $content = str_replace("\n", " ", $content);

        // Decode HTML entities back to plain text
        return trim(Convert::xml2raw($content));
    }
}
