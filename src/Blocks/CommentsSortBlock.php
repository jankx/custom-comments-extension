<?php
namespace Jankx\Extensions\CustomComments\Blocks;

use Jankx\Extensions\CustomComments\Services\CommentListRenderer;
use Jankx\Gutenberg\Block;

/**
 * jankx/comments-sort — "Mới nhất / Cũ nhất" pill dropdown.
 *
 * The frontend script reads the selected value and refreshes
 * jankx/comment-list through the REST refresh endpoint.
 */
class CommentsSortBlock extends Block
{
    protected $blockId = 'jankx/comments-sort';

    public function render($attributes, $content = '', $block = null)
    {
        $attrs = CommentListRenderer::sanitizeRenderAttributes([
            'order' => $attributes['order'] ?? 'newest',
        ]);
        $current = CommentListRenderer::resolveOrder($attrs);

        $wrapper = get_block_wrapper_attributes(['class' => 'jankx-comments-sort']);

        $html = '<div ' . $wrapper . '>';
        $html .= '<select class="jankx-comments-sort__select" data-jcc-order aria-label="'
            . esc_attr__('Sắp xếp bình luận', 'jankx') . '">';
        $html .= '<option value="newest"' . selected($current, 'newest', false) . '>'
            . esc_html__('Mới nhất', 'jankx') . '</option>';
        $html .= '<option value="oldest"' . selected($current, 'oldest', false) . '>'
            . esc_html__('Cũ nhất', 'jankx') . '</option>';
        $html .= '</select>';
        $html .= '<span class="jankx-comments-sort__caret" aria-hidden="true">&#8645;</span>';
        $html .= '</div>';

        return $html;
    }
}
