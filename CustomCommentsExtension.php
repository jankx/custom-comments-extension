<?php
namespace Jankx\Extensions\CustomComments;

use Jankx\Extensions\AbstractExtension;
use Jankx\Extensions\CustomComments\Blocks\CommentsCounterBlock;
use Jankx\Extensions\CustomComments\Blocks\CommentFormBlock;
use Jankx\Extensions\CustomComments\Blocks\CommentItemBlock;
use Jankx\Extensions\CustomComments\Blocks\CommentListBlock;
use Jankx\Extensions\CustomComments\Blocks\CommentsBlock;
use Jankx\Extensions\CustomComments\Blocks\CommentsSortBlock;
use Jankx\Extensions\CustomComments\Rest\CommentsController;

class CustomCommentsExtension extends AbstractExtension
{
    protected static $instance;

    public function __construct()
    {
        $this->register_autoloader();
        parent::__construct();
    }

    protected function register_autoloader()
    {
        spl_autoload_register(function ($class) {
            $prefix = 'Jankx\\Extensions\\CustomComments\\';
            $base_dir = __DIR__ . '/src/';
            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                return;
            }
            $relative_class = substr($class, $len);
            $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
            if (file_exists($file)) {
                require $file;
            }
        });
    }

    public function init(): void
    {
        self::$instance = $this;
    }

    public static function get_instance(): ?self
    {
        return self::$instance;
    }

    public function register_hooks(): void
    {
        // register_block_type_from_metadata() calls wp_script_is(), so blocks
        // must be registered on init — after scripts/styles are registered.
        add_action('init', [$this, 'register_blocks']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
    }

    /**
     * Scan each block directory for block.json and register the block with
     * its render callback (same pattern as base-ecommerce / review-system).
     */
    public function register_blocks(): void
    {
        $blocksDir = __DIR__ . '/blocks';
        if (!is_dir($blocksDir)) {
            return;
        }

        $blockClasses = [
            'comments'      => CommentsBlock::class,
            'comments-sort' => CommentsSortBlock::class,
            'comments-counter' => CommentsCounterBlock::class,
            'comment-form'  => CommentFormBlock::class,
            'comment-list'  => CommentListBlock::class,
            'comment-item'  => CommentItemBlock::class,
        ];

        foreach ($blockClasses as $blockName => $blockClass) {
            $blockPath = $blocksDir . '/' . $blockName;
            if (!is_dir($blockPath) || !file_exists($blockPath . '/block.json')) {
                continue;
            }

            $blockJson = json_decode((string) file_get_contents($blockPath . '/block.json'), true);
            $registeredName = $blockJson['name'] ?? '';
            if ($registeredName && \WP_Block_Type_Registry::get_instance()->is_registered($registeredName)) {
                continue;
            }

            $block = new $blockClass($blockPath);
            $block->setBlockPath($blockPath);
            $block->boot();
            $block->register();
        }
    }

    public function register_rest_routes(): void
    {
        (new CommentsController())->registerRoutes();
    }

    /**
     * Enqueue the frontend interaction layer (submit / sort / reply) plus its
     * styles on singular pages that use the comments block (or accept
     * comments at all — the script is a no-op without block roots).
     */
    public function enqueue_frontend_assets(): void
    {
        if (!is_singular()) {
            return;
        }

        $post = get_queried_object();
        $usesBlock = $post instanceof \WP_Post
            && (has_block('jankx/comments', $post) || has_block('jankx/comment-list', $post));

        if (!$usesBlock && !comments_open()) {
            return;
        }

        $cssPath = $this->get_extension_path() . '/assets/frontend.css';
        if (file_exists($cssPath)) {
            wp_enqueue_style(
                'jankx-custom-comments',
                $this->get_extension_url() . '/assets/frontend.css',
                [],
                filemtime($cssPath)
            );
        }

        $jsPath = $this->get_extension_path() . '/assets/frontend.js';
        if (!file_exists($jsPath)) {
            return;
        }

        wp_enqueue_script(
            'jankx-custom-comments',
            $this->get_extension_url() . '/assets/frontend.js',
            [],
            filemtime($jsPath),
            true
        );

        wp_localize_script('jankx-custom-comments', 'jankxCustomComments', [
            'restUrl' => esc_url_raw(rest_url(CommentsController::REST_NAMESPACE)),
            'nonce'   => wp_create_nonce('wp_rest'),
            'i18n'    => [
                'submitting' => __('Đang gửi...', 'jankx'),
                'loading'    => __('Đang tải...', 'jankx'),
                'posted'     => __('Đã đăng ✓', 'jankx'),
                'success'    => __('Bình luận của bạn đã được đăng.', 'jankx'),
                'pending'    => __('Bình luận của bạn đang chờ kiểm duyệt.', 'jankx'),
                'error'      => __('Có lỗi xảy ra, vui lòng thử lại.', 'jankx'),
                'empty'      => __('Chưa có bình luận nào.', 'jankx'),
                'required'   => __('Vui lòng viết bình luận.', 'jankx'),
                'loginName'  => __('Vui lòng nhập tên của bạn.', 'jankx'),
                'loginEmail' => __('Vui lòng nhập email hợp lệ.', 'jankx'),
            ],
        ]);
    }
}
