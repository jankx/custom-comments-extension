<?php
namespace Jankx\Extensions\CustomComments\Blocks;

use Jankx\Extensions\CustomComments\Context\CommentContext;
use Jankx\Extensions\CustomComments\Services\CommentListRenderer;
use Jankx\Gutenberg\Block;

/**
 * jankx/comment-item — template for ONE comment.
 *
 * Inside the editor it shows a static mock. On the frontend the raw inner
 * block renders nothing by itself (no CommentContext); the list renderer
 * calls renderComment() once per comment with the context of that comment.
 */
class CommentItemBlock extends Block
{
    protected $blockId = 'jankx/comment-item';

    public function render($attributes, $content = '', $block = null)
    {
        $comment = CommentContext::get();
        if (!$comment) {
            // Rendered as a raw inner block (template) — only the list
            // renderer (with a comment in context) produces markup.
            return '';
        }

        $attrs = CommentListRenderer::sanitizeRenderAttributes($attributes);

        return self::renderComment($comment, $attrs, 1, $content);
    }

    /**
     * Markup for a single comment (used by CommentListRenderer).
     *
     * @param \WP_Comment $comment
     * @param array       $attrs       Sanitized render attributes
     * @param int         $level       Nesting level (1 = top level)
     * @param string      $childrenHtml Pre-rendered nested replies
     */
    public static function renderComment(\WP_Comment $comment, array $attrs, int $level = 1, string $childrenHtml = ''): string
    {
        $commentId = (int) $comment->comment_ID;
        $showAvatar = !empty($attrs['showAvatar']);
        $avatarSize = (int) ($attrs['avatarSize'] ?? 40);

        $html = sprintf(
            '<div class="jankx-comment-item" data-comment-id="%d" style="--jcx-level:%d">',
            $commentId,
            max(0, $level - 1)
        );

        if ($showAvatar) {
            $html .= '<div class="jankx-comment-item__avatar">' . get_avatar(
                $comment,
                $avatarSize,
                '',
                get_comment_author($comment),
                ['class' => 'jankx-comment-item__avatar-img', 'force_display' => true]
            ) . '</div>';
        }

        $html .= '<div class="jankx-comment-item__main">';
        $html .= '<div class="jankx-comment-item__bubble">';
        $html .= '<div class="jankx-comment-item__author">' . esc_html(get_comment_author($comment)) . '</div>';
        // Run through the core comment_text filter chain (wpautop,
        // make_clickable, ...) so other extensions hooking comment_text —
        // e.g. comment-media appending its attachment grid — work inside
        // this layout too.
        $content = apply_filters('comment_text', $comment->comment_content, $comment);
        $html .= '<div class="jankx-comment-item__content">' . $content . '</div>';
        $html .= '</div>'; // .jankx-comment-item__bubble

        // Actions row: likes count (display only) · reply · relative time
        $actions = '';

        $likes = (int) get_comment_meta($commentId, 'jankx_likes', true);
        if (!empty($attrs['showLikes']) && $likes > 0) {
            $likesText = (string) ($attrs['likesText'] ?? '%d lượt thích');
            $actions .= '<span class="jankx-comment-item__likes">'
                . esc_html(sprintf($likesText, $likes))
                . '</span>';
        }

        if (!empty($attrs['showReply'])) {
            $actions .= '<button type="button" class="jankx-comment-item__reply-btn" data-jcc-toggle-reply aria-expanded="false">'
                . esc_html($attrs['replyLabel'] ?? 'Thỏa luận')
                . '</button>';
        }

        if (!empty($attrs['showTime'])) {
            $time = self::relativeTime($comment);
            if ($time !== '') {
                $actions .= '<time class="jankx-comment-item__time">' . esc_html($time) . '</time>';
            }
        }

        if ($actions !== '') {
            $html .= '<div class="jankx-comment-item__actions">' . $actions . '</div>';
        }

        // Inline reply form (hidden until "Thỏa luận" is clicked)
        if (!empty($attrs['showReply'])) {
            $html .= self::renderReplyForm($comment, $attrs, $avatarSize);
        }

        $html .= '<div class="jankx-comment-item__children">' . $childrenHtml . '</div>';
        $html .= '</div>'; // .jankx-comment-item__main
        $html .= '</div>';

        return $html;
    }

    protected static function renderReplyForm(\WP_Comment $comment, array $attrs, int $avatarSize): string
    {
        $html = sprintf(
            '<form class="jankx-comment-reply-form" data-jcc-reply-form data-parent="%d" hidden>',
            (int) $comment->comment_ID
        );

        $html .= '<div class="jankx-comment-reply-form__avatar">' . self::currentAvatar(
            min(32, $avatarSize)
        ) . '</div>';
        $html .= '<div class="jankx-comment-reply-form__body">';

        if (!is_user_logged_in()) {
            $html .= self::guestField('text', 'jcc-reply-author', __('Tên của bạn', 'jankx'));
            $html .= self::guestField('email', 'jcc-reply-email', __('Email (không hiển thị công khai)', 'jankx'));
        }

        $html .= '<textarea class="jankx-comment-reply-form__input" rows="1" required placeholder="'
            . esc_attr($attrs['replyPlaceholder'] ?? 'Viết câu trả lời của bạn...')
            . '"></textarea>';

        $html .= '<div class="jankx-comment-reply-form__actions">'
            . '<button type="button" class="jankx-comment-reply-form__cancel" data-jcc-cancel-reply>'
            . esc_html__('Huỷ', 'jankx')
            . '</button>'
            . '<button type="submit" class="jankx-comment-reply-form__submit" data-jcc-submit>'
            . esc_html__('Đăng', 'jankx')
            . '</button>'
            . '</div>';

        $html .= '<div class="jankx-comment-reply-form__message" data-jcc-message hidden></div>';
        $html .= '</div></form>';

        return $html;
    }

    protected static function guestField(string $type, string $name, string $placeholder): string
    {
        return '<input type="' . esc_attr($type) . '" class="jankx-comments-guest-only jankx-comments-guest-input" name="'
            . esc_attr($name) . '" placeholder="' . esc_attr($placeholder) . '" autocomplete="off">';
    }

    protected static function currentAvatar(int $size): string
    {
        $user = wp_get_current_user();
        $email = ($user && $user->ID) ? $user->user_email : '';

        return get_avatar($email ?: get_bloginfo('admin_email'), $size, '', '', [
            'class'         => 'jankx-comment-item__avatar-img',
            'force_display' => true,
        ]);
    }

    protected static function relativeTime(\WP_Comment $comment): string
    {
        $timestamp = strtotime($comment->comment_date_gmt . ' UTC');
        if (!$timestamp) {
            return '';
        }

        return human_time_diff($timestamp, time());
    }
}
