<?php
namespace Jankx\Extensions\CustomComments\Blocks;

use Jankx\Extensions\CustomComments\Services\CommentListRenderer;
use Jankx\Gutenberg\Block;

/**
 * jankx/comment-form — main comment form.
 *
 * Facebook-style: avatar + rounded input on one row, blue "Đăng" button
 * right-aligned below. Guests additionally see name/email inputs. Submits
 * through the extension REST endpoint (assets/frontend.js).
 */
class CommentFormBlock extends Block
{
    protected $blockId = 'jankx/comment-form';

    public function render($attributes, $content = '', $block = null)
    {
        $postId = CommentListRenderer::resolvePostId($attributes, $block);
        if ($postId <= 0 || !comments_open($postId)) {
            return '';
        }

        $placeholder = (string) ($attributes['placeholder'] ?? '');
        if ($placeholder === '') {
            $placeholder = __('Hãy viết bình luận của bạn', 'jankx');
        }

        $submitText = (string) ($attributes['submitText'] ?? '');
        if ($submitText === '') {
            $submitText = __('Đăng', 'jankx');
        }

        $showAvatar = (bool) ($attributes['showAvatar'] ?? true);
        $guest = !is_user_logged_in();

        $wrapper = get_block_wrapper_attributes([
            'class' => 'jankx-comment-form ' . CommentListRenderer::guestClass(),
            'data-post-id' => $postId,
        ]);

        $html = '<form ' . $wrapper . ' data-jcc-form novalidate>';
        $html .= '<div class="jankx-comment-form__row">';

        if ($showAvatar) {
            $html .= '<div class="jankx-comment-form__avatar">' . $this->currentAvatar() . '</div>';
        }

        $html .= '<div class="jankx-comment-form__fields">';

        if ($guest) {
            $html .= '<input type="text" class="jankx-comments-guest-only jankx-comments-guest-input" name="jcc-author" placeholder="'
                . esc_attr__('Tên của bạn', 'jankx') . '" autocomplete="name">';
            $html .= '<input type="email" class="jankx-comments-guest-only jankx-comments-guest-input" name="jcc-email" placeholder="'
                . esc_attr__('Email (không hiển thị công khai)', 'jankx') . '" autocomplete="email">';
        }

        $html .= '<textarea class="jankx-comment-form__input" name="jcc-content" rows="1" required placeholder="'
            . esc_attr($placeholder) . '"></textarea>';
        $html .= '</div>'; // .jankx-comment-form__fields
        $html .= '</div>'; // .jankx-comment-form__row

        $html .= '<div class="jankx-comment-form__footer">';
        $html .= '<div class="jankx-comment-form__message" data-jcc-message hidden></div>';
        $html .= '<button type="submit" class="jankx-comment-form__submit" data-jcc-submit>'
            . esc_html($submitText) . '</button>';
        $html .= '</div>';

        $html .= '</form>';

        return $html;
    }

    protected function currentAvatar(): string
    {
        $user = wp_get_current_user();
        $email = ($user && $user->ID) ? $user->user_email : get_bloginfo('admin_email');

        return get_avatar($email, 40, '', '', [
            'class'         => 'jankx-comment-item__avatar-img',
            'force_display' => true,
        ]);
    }
}
