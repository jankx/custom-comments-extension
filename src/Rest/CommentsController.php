<?php
namespace Jankx\Extensions\CustomComments\Rest;

use Jankx\Extensions\CustomComments\Blocks\CommentItemBlock;
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
                    'offset' => [
                        'required' => false,
                        'type'     => 'integer',
                        'default'  => 0,
                    ],
                    'per_page' => [
                        'required' => false,
                        'type'     => 'integer',
                        'default'  => 0,
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
                    // JSON-encoded list attributes for rendering the new
                    // comment node (decoded manually, see decodeAttrs()).
                    'attrs'   => ['required' => false],
                    'level'   => ['required' => false, 'type' => 'integer', 'default' => 1],
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

    /**
     * Render/refresh a slice of the list.
     *
     * - offset=0          → first page (limit = attrs.initialCount)
     * - offset>0          → load-more slice (limit = per_page || attrs.loadMoreCount)
     *
     * The response always carries the pagination state so the client knows
     * whether more comments remain: shown / total / has_more.
     */
    /**
     * Query/body params carry attrs as a JSON string (query strings cannot
     * be schema-typed as object — no auto decode).
     */
    protected function decodeAttrs($value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        return is_array($value) ? $value : [];
    }

    public function getComments(WP_REST_Request $request): WP_REST_Response
    {
        $postId = (int) $request->get_param('post_id');
        $attrs = CommentListRenderer::sanitizeRenderAttributes(
            $this->decodeAttrs($request->get_param('attrs'))
        );

        $order = $request->get_param('order');
        $order = in_array($order, ['newest', 'oldest'], true) ? $order : null;

        $offset = max(0, (int) $request->get_param('offset'));
        $perPage = max(0, (int) $request->get_param('per_page'));
        if ($perPage <= 0) {
            $perPage = $offset > 0 ? $attrs['loadMoreCount'] : $attrs['initialCount'];
        }

        $range = CommentListRenderer::renderRange($postId, $attrs, $order, $offset, $perPage);

        return new WP_REST_Response([
            'success'  => true,
            'order'    => $range['order'],
            'html'     => $range['html'],
            'offset'   => $range['offset'],
            'count'    => $range['count'],
            'shown'    => $range['shown'],
            'total'    => $range['total'],
            'has_more' => $range['has_more'],
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

        $response = [
            'success' => true,
            'id'      => (int) $submission->comment_ID,
            'parent'  => $parent,
            'pending' => $pending,
            'message' => $pending
                ? __('Bình luận của bạn đang chờ kiểm duyệt.', 'jankx')
                : __('Bình luận đã được đăng.', 'jankx'),
        ];

        // Approved replies come back as a ready-to-append node so the client
        // can drop them into the parent's children container without a full
        // list refresh (keeps the loaded pages intact).
        if (!$pending) {
            $attrs = CommentListRenderer::sanitizeRenderAttributes(
                $this->decodeAttrs($request->get_param('attrs'))
            );
            $level = max(1, (int) $request->get_param('level'));
            $response['html'] = CommentItemBlock::renderComment($submission, $attrs, $level);
        }

        return new WP_REST_Response($response, 201);
    }
}
