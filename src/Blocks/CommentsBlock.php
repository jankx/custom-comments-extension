<?php
namespace Jankx\Extensions\CustomComments\Blocks;

use Jankx\Gutenberg\Block;

class CommentsBlock extends Block
{
    protected $blockId = 'jankx/comments';

    public function render($attributes, $content = '', $block = null)
    {
        $wrapper_attributes = get_block_wrapper_attributes();

        // Render the wrapper for comments container
        return sprintf(
            '<div %1$s>%2$s</div>',
            $wrapper_attributes,
            $content
        );
    }
}
