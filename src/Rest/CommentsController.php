<?php
namespace Jankx\Extensions\CustomComments\Rest;

use Jankx\Extensions\CustomComments\Services\CommentListRenderer;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST: namespace `jankx/custom-comments/v1`.
 *
 * GET  /comments  — render/refresh the comment list HTML (sort, after post).
 * POST /comments  — submit a real comment (threaded, guest friendly).
 *
 * Permission: valid `wp_rest` nonce for everyone (guests included) —
 * localized as jankxCustomComments.nonce by CustomCommentsExtension.
 */
class CommentsController
{
    public const REST_NAMESPACE = 'jankx/custom-comments/v1';

    public function registerRoutes(): void
    {
        register_rest_route(self::REST_NAMESPACE, '/comments', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getComments'],
                'permission_callback' => [$this, 'canAccess'],
                'args'                => [
                    'post_id' => [
                        'required' => true,
                        'type'     => 'integer',
                    ],
                    'order' => [
                        'required' => false,
                        'type'     => 'string',
                        'enum'     => ['newest', 'oldest'],
                    ],
                    // Query strings cannot be schema-typed as object (no
                    // auto JSON decode) — decoded manually in the callback.
                    'attrs' => [
                        'required' => false,
                    ],
                ],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'createComment'],
                'permission_callback' => [$this, 'canAccess'],
                'args'                => [
                    'post_id' => ['required' => true, 'type' => 'integer'],
                    'content' => ['required' => true, 'type' => 'string'],
                    'parent'  => ['required' => false, 'type' => 'integer', 'default' => 0],
                    'author'  => ['required' => false, 'type' => 'string'],
                    'email'   => ['required' => false, 'type' => 'string'],
                ],
            ],
        ]);
    }

    /**
     * Guest-friendly nonce check (nonce is localized on pages with comments).
     */
    public function canAccess(): bool
    {
        $nonce = $_SERVER['HTTP_X_WP_NONCE'] ?? '';
        if (!is_string($nonce) || $nonce === '') {
            $nonce = (string) wp_unslash($_REQUEST['_wpnonce'] ?? '');
        }

        return (bool) wp_verify_nonce($nonce, 'wp_rest');
    }

    public function getComments(WP_REST_Request $request): WP_REST_Response
    {
        $postId = (int) $request->get_param('post_id');
        $attrs = $request->get_param('attrs');
        if (is_string($attrs)) {
            $decoded = json_decode($attrs, true);
            $attrs = is_array($decoded) ? $decoded : [];
        }
        $attrs = is_array($attrs) ? $attrs : [];
        $order = $request->get_param('order');
        $order = in_array($order, ['newest', 'oldest'], true)
            ? $order
            : CommentListRenderer::resolveOrder($attrs);

        return new WP_REST_Response([
            'success' => true,
            'order'   => $order,
            'html'    => CommentListRenderer::render($postId, $attrs, $order),
        ]);
    }

    /**
     * Submit through wp_handle_comment_submission() so WP core rules
     * (moderation, flood, duplicate, comment_closed...) all apply.
     */
    public function createComment(WP_REST_Request $request)
    {
        $postId = (int) $request->get_param('post_id');
        $content = (string) $request->get_param('content');
        $parent = (int) $request->get_param('parent');

        if ($postId <= 0 || trim($content) === '') {
            return new WP_Error(
                'jcc_invalid_request',
                __('Vui lòng nhập nội dung bình luận.', 'jankx'),
                ['status' => 400]
            );
        }

        $post = get_post($postId);
        if (!$post || get_post_status($post) !== 'publish') {
            return new WP_Error(
                'jcc_invalid_post',
                __('Không tìm thấy nội dung cần bình luận.', 'jankx'),
                ['status' => 404]
            );
        }

        if (!comments_open($postId)) {
            return new WP_Error(
                'jcc_comments_closed',
                __('Bình luận đã bị đóng trên nội dung này.', 'jankx'),
                ['status' => 403]
            );
        }

        // wp_handle_comment_submission() expects the wp-comments-post.php
        // field names: author / email / comment / comment_parent.
        $comment = [
            'comment_post_ID'  => $postId,
            'comment'          => $content,
            'comment_parent'   => $parent,
            'comment_author_url' => '',
        ];

        if (!is_user_logged_in()) {
            $author = trim((string) $request->get_param('author'));
            $email = trim((string) $request->get_param('email'));

            if ($author === '' || !is_email($email)) {
                return new WP_Error(
                    'jcc_missing_author',
                    __('Vui lòng nhập tên và email hợp lệ.', 'jankx'),
                    ['status' => 400]
                );
            }

            $comment['author'] = $author;
            $comment['email'] = $email;
        }

        if ($parent > 0) {
            $parentComment = get_comment($parent);
            if (!$parentComment || (int) $parentComment->comment_post_ID !== $postId) {
                return new WP_Error(
                    'jcc_invalid_parent',
                    __('Bình luận gốc không tồn tại.', 'jankx'),
                    ['status' => 400]
                );
            }
        }

        $submission = wp_handle_comment_submission(wp_unslash($comment));

        if (is_wp_error($submission)) {
            return new WP_Error(
                $submission->get_error_code() ?: 'jcc_submit_failed',
                $submission->get_error_message() ?: __('Không thể đăng bình luận. Vui lòng thử lại.', 'jankx'),
                ['status' => 400]
            );
        }

        /** @var \WP_Comment $submission */
        $pending = (string) $submission->comment_approved !== '1';

        return new WP_REST_Response([
            'success' => true,
            'id'      => (int) $submission->comment_ID,
            'pending' => $pending,
            'message' => $pending
                ? __('Bình luận của bạn đang chờ kiểm duyệt.', 'jankx')
                : __('Bình luận đã được đăng.', 'jankx'),
        ], 201);
    }
}
