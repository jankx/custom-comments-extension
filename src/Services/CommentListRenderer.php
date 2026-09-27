<?php
namespace Jankx\Extensions\CustomComments\Services;

use Jankx\Extensions\CustomComments\Blocks\CommentItemBlock;

/**
 * Single source of truth for the comment list markup.
 *
 * Used by CommentListBlock::render() on page load and by the REST refresh
 * endpoint (sort change / after submitting a comment) so the frontend and
 * the refreshed fragment always render through exactly the same code path.
 */
class CommentListRenderer
{
    public static function defaultAttributes(): array
    {
        return [
            'order'            => 'newest',
            'perPage'          => 0,
            'maxDepth'         => 5,
            'showAvatar'       => true,
            'avatarSize'       => 40,
            'showTime'         => true,
            'showLikes'        => true,
            'likesText'        => '%d lượt thích',
            'showReply'        => true,
            'replyLabel'       => 'Thỏa luận',
            'replyPlaceholder' => 'Viết câu trả lời của bạn...',
        ];
    }

    /**
     * Resolve the post the comments belong to: explicit attribute first,
     * then block context (postId), then the queried object / global post.
     */
    public static function resolvePostId(array $attributes = [], $block = null): int
    {
        $postId = (int) ($attributes['postId'] ?? 0);
        if ($postId > 0) {
            return $postId;
        }

        if (is_object($block)) {
            // providesContext of jankx/comment-list: "jankx/commentList" -> postId
            $fromContext = $block->context['jankx/commentList']
                ?? $block->context['postId']
                ?? 0;
            if ((int) $fromContext > 0) {
                return (int) $fromContext;
            }
        }

        $queried = get_queried_object();
        if ($queried instanceof \WP_Post) {
            return (int) $queried->ID;
        }

        return (int) get_the_ID();
    }

    /**
     * Merge + type-check raw attributes (block attributes, template item
     * attributes, or JSON coming back from the REST refresh request).
     */
    public static function sanitizeRenderAttributes(array $raw = []): array
    {
        $defaults = self::defaultAttributes();

        return [
            'order'            => in_array($raw['order'] ?? '', ['newest', 'oldest'], true)
                ? $raw['order']
                : $defaults['order'],
            'perPage'          => max(0, (int) ($raw['perPage'] ?? $defaults['perPage'])),
            'maxDepth'         => min(10, max(1, (int) ($raw['maxDepth'] ?? $defaults['maxDepth']))),
            'showAvatar'       => (bool) ($raw['showAvatar'] ?? $defaults['showAvatar']),
            'avatarSize'       => min(128, max(16, (int) ($raw['avatarSize'] ?? $defaults['avatarSize']))),
            'showTime'         => (bool) ($raw['showTime'] ?? $defaults['showTime']),
            'showLikes'        => (bool) ($raw['showLikes'] ?? $defaults['showLikes']),
            'likesText'        => (string) ($raw['likesText'] ?? $defaults['likesText']),
            'showReply'        => (bool) ($raw['showReply'] ?? $defaults['showReply']),
            'replyLabel'       => sanitize_text_field((string) ($raw['replyLabel'] ?? $defaults['replyLabel'])),
            'replyPlaceholder' => sanitize_text_field(
                (string) ($raw['replyPlaceholder'] ?? $defaults['replyPlaceholder'])
            ),
        ];
    }

    /**
     * The active order: persisted ?cc_order= across reloads, falling back to
     * the block attribute.
     */
    public static function resolveOrder(array $attrs = []): string
    {
        if (isset($_GET['cc_order'])) {
            $fromGet = sanitize_key(wp_unslash($_GET['cc_order']));
            if (in_array($fromGet, ['newest', 'oldest'], true)) {
                return $fromGet;
            }
        }

        return (($attrs['order'] ?? 'newest') === 'oldest') ? 'oldest' : 'newest';
    }

    /**
     * Build the full list HTML for a post.
     *
     * @param int         $postId
     * @param array       $attrs  Raw attributes (sanitized internally)
     * @param string|null $order  newest|oldest — overrides attrs/GET
     */
    public static function render(int $postId, array $attrs = [], ?string $order = null): string
    {
        $attrs = self::sanitizeRenderAttributes($attrs);
        $order = in_array($order, ['newest', 'oldest'], true) ? $order : self::resolveOrder($attrs);

        if ($postId <= 0) {
            return '';
        }

        $flat = get_comments([
            'post_id' => $postId,
            'status'  => 'approve',
            'order'   => $order === 'oldest' ? 'ASC' : 'DESC',
        ]);

        if (empty($flat)) {
            return '';
        }

        // Group by parent. Top level keeps the query order (newest/oldest),
        // replies inside a thread are always oldest-first like a conversation.
        $byParent = [];
        foreach ($flat as $comment) {
            $byParent[(int) $comment->comment_parent][] = $comment;
        }

        foreach ($byParent as $parentId => $children) {
            if ($parentId > 0 && count($children) > 1) {
                usort($children, function ($a, $b) {
                    return strcmp($a->comment_date_gmt, $b->comment_date_gmt);
                });
                $byParent[$parentId] = $children;
            }
        }

        $roots = $byParent[0] ?? [];
        if ($attrs['perPage'] > 0) {
            $roots = array_slice($roots, 0, $attrs['perPage']);
        }

        $html = '';
        foreach ($roots as $root) {
            $html .= self::renderNode($root, $byParent, $attrs, 1);
        }

        return $html;
    }

    protected static function renderNode(\WP_Comment $comment, array $byParent, array $attrs, int $level): string
    {
        $childrenHtml = '';
        $kids = $byParent[(int) $comment->comment_ID] ?? [];

        foreach ($kids as $kid) {
            if ($level < $attrs['maxDepth']) {
                $childrenHtml .= self::renderNode($kid, $byParent, $attrs, $level + 1);
            } else {
                // Beyond the nesting cap: still visible, but flattened.
                $childrenHtml .= CommentItemBlock::renderComment($kid, $attrs, $level);
            }
        }

        return CommentItemBlock::renderComment($comment, $attrs, $level, $childrenHtml);
    }

    public static function guestClass(): string
    {
        return is_user_logged_in() ? 'is-user' : 'is-guest';
    }
}
