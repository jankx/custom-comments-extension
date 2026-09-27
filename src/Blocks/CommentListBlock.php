<?php
namespace Jankx\Extensions\CustomComments\Blocks;

use Jankx\Extensions\CustomComments\Services\CommentListRenderer;
use Jankx\Gutenberg\Block;

/**
 * jankx/comment-list — renders the threaded comment list.
 *
 * Merges the list attributes with the item attributes taken from its inner
 * jankx/comment-item template block, then delegates to CommentListRenderer
 * (shared with the REST refresh endpoint). The frontend script replaces the
 * inner HTML of the wrapper when sorting / after posting a comment.
 */
class CommentListBlock extends Block
{
    protected $blockId = 'jankx/comment-list';

    public function render($attributes, $content = '', $block = null)
    {
        $postId = CommentListRenderer::resolvePostId($attributes, $block);
        if ($postId <= 0) {
            return '';
        }

        $attrs = $this->mergeAttributes($attributes, $block);
        $order = CommentListRenderer::resolveOrder($attrs);

        $wrapper = get_block_wrapper_attributes([
            'class' => 'jankx-comment-list ' . CommentListRenderer::guestClass(),
            'data-jcc-list' => '',
            'data-post-id' => $postId,
            'data-order' => $order,
            'data-attrs' => wp_json_encode($attrs),
        ]);

        $html = CommentListRenderer::render($postId, $attrs, $order);
        if ($html === '') {
            $html = '<p class="jankx-comment-list__empty">'
                . esc_html__('Chưa có bình luận nào. Hãy là người đầu tiên bình luận!', 'jankx')
                . '</p>';
        }

        return sprintf('<div %s><div class="jankx-comment-list__inner">%s</div></div>', $wrapper, $html);
    }

    protected function mergeAttributes(array $attributes, $block): array
    {
        $attrs = CommentListRenderer::sanitizeRenderAttributes($attributes);

        // Item settings live on the inner template block so the editor
        // inspector of jankx/comment-item controls them.
        if (is_object($block) && !empty($block->inner_blocks)) {
            foreach ($block->inner_blocks as $inner) {
                if (($inner->block_name ?? '') === 'jankx/comment-item') {
                    $attrs = CommentListRenderer::sanitizeRenderAttributes(
                        array_merge($attrs, (array) $inner->attributes)
                    );
                    break;
                }
            }
        }

        return $attrs;
    }
}
