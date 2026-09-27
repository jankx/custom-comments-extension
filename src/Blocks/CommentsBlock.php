<?php
namespace Jankx\Extensions\CustomComments\Blocks;

use Jankx\Extensions\CustomComments\Services\CommentListRenderer;
use Jankx\Gutenberg\Block;

/**
 * jankx/comments — container block.
 *
 * Wraps the inner blocks (sort / form / list) that compose the Facebook-like
 * comments layout. Dynamic: the saved InnerBlocks content is passed in as
 * $content after all children have rendered server-side.
 */
class CommentsBlock extends Block
{
    protected $blockId = 'jankx/comments';

    public function render($attributes, $content = '', $block = null)
    {
        $wrapper = get_block_wrapper_attributes([
            'class' => 'jankx-comments ' . CommentListRenderer::guestClass(),
        ]);

        return sprintf('<div %s>%s</div>', $wrapper, $content);
    }
}
