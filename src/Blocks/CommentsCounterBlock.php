<?php
namespace Jankx\Extensions\CustomComments\Blocks;

use Jankx\Extensions\CustomComments\Services\CommentListRenderer;
use Jankx\Gutenberg\Block;

/**
 * jankx/comments-counter — "123 bình luận" counter (renamed to avoid
 * clashing with the parent theme's jankx/comment-count block).
 *
 * Renders the approved comment total (all levels); the frontend script
 * keeps it in sync when a comment is posted or the list refreshes.
 */
class CommentsCounterBlock extends Block
{
    protected $blockId = 'jankx/comments-counter';

    public function render($attributes, $content = '', $block = null)
    {
        $postId = CommentListRenderer::resolvePostId($attributes, $block);
        if ($postId <= 0) {
            return '';
        }

        $format = (string) ($attributes['format'] ?? '');
        if ($format === '') {
            $format = __('%d bình luận', 'jankx');
        }

        $count = (int) get_comments([
            'post_id' => $postId,
            'status'  => 'approve',
            'count'   => true,
        ]);

        $wrapper = get_block_wrapper_attributes([
            'class' => 'jankx-comments-counter',
            'data-jcc-count' => '',
            'data-count' => $count,
            'data-post-id' => $postId,
            'data-format' => $format,
        ]);

        return sprintf(
            '<span %s>%s</span>',
            $wrapper,
            esc_html(str_replace('%d', (string) $count, $format))
        );
    }
}
