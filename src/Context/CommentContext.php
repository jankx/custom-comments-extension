<?php
namespace Jankx\Extensions\CustomComments\Context;

/**
 * Holds the WP_Comment currently being rendered.
 *
 * Mirrors CartItemContext in base-ecommerce: the list renderer sets the
 * comment before delegating to the comment-item template, and the item block
 * reads it to build the markup. When no comment is set, the item block knows
 * it is being rendered as a raw inner block (editor/template) and returns ''.
 */
class CommentContext
{
    /**
     * @var \WP_Comment|null
     */
    protected static $comment;

    public static function set(?\WP_Comment $comment): void
    {
        self::$comment = $comment;
    }

    public static function get(): ?\WP_Comment
    {
        return self::$comment;
    }

    public static function reset(): void
    {
        self::$comment = null;
    }
}
