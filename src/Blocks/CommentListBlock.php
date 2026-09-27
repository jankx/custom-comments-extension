<?php
namespace Jankx\Extensions\CustomComments\Blocks;

use Jankx\Extensions\CustomComments\Services\CommentListRenderer;
use Jankx\Gutenberg\Block;

/**
 * jankx/comment-list — renders the threaded comment list.
 *
 * First page shows attrs.initialCount root comments (default 5) in the
 * active sort order; the "Hiển thị thêm bình luận" button loads
 * attrs.loadMoreCount more roots per request through the REST endpoint and
 * appends them (children travel inside their root node). The frontend script
 * swaps the inner HTML when sorting / after posting a comment.
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
        $range = CommentListRenderer::renderRange($postId, $attrs, null, 0, $attrs['initialCount']);

        $wrapper = get_block_wrapper_attributes([
            'class' => 'jankx-comment-list ' . CommentListRenderer::guestClass(),
            'data-jcc-list' => '',
            'data-post-id' => $postId,
            'data-order' => $range['order'],
            'data-shown' => $range['shown'],
            'data-total' => $range['total'],
            'data-initial' => $attrs['initialCount'],
            'data-load-more' => $attrs['loadMoreCount'],
            'data-attrs' => wp_json_encode($attrs),
        ]);

        $inner = $range['html'];
        if ($inner === '') {
            $inner = '<p class="jankx-comment-list__empty">'
                . esc_html__('Chưa có bình luận nào. Hãy là người đầu tiên bình luận!', 'jankx')
                . '</p>';
        }

        $more = sprintf(
            '<button type="button" class="jankx-comment-list__more" data-jcc-more%s>%s</button>',
            $range['has_more'] ? '' : ' hidden',
            esc_html__('Hiển thị thêm bình luận', 'jankx')
        );

        return sprintf(
            '<div %s><div class="jankx-comment-list__inner">%s</div>%s</div>',
            $wrapper,
            $inner,
            $more
        );
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
