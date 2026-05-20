<?php
namespace WSR\Integrations;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Elementor Integration — purges static cache when Elementor saves a page.
 */
class Elementor {

    public function __construct() {
        if ( ! did_action( 'elementor/loaded' ) && ! defined( 'ELEMENTOR_VERSION' ) ) return;

        // Elementor editor save
        add_action( 'elementor/editor/after_save',         [ $this, 'on_save'    ], 10, 2 );

        // Elementor document save (newer API)
        add_action( 'elementor/document/after_save',       [ $this, 'on_document_save' ], 10, 2 );

        // Elementor global kit / settings saved
        add_action( 'elementor/core/files/clear_cache',    [ $this, 'on_global_flush' ] );

        // Elementor CSS regenerated
        add_action( 'elementor/css-file/post/enqueue',     [ $this, 'on_css_update' ], 10, 1 );

        // Elementor Pro Theme Builder
        add_action( 'elementor-pro/theme-builder/conditions/conditions_rebuilt', [ $this, 'on_global_flush' ] );
    }

    /**
     * Fires after Elementor saves a single post (legacy hook).
     *
     * @param int   $post_id
     * @param array $editor_data
     */
    public function on_save( int $post_id, array $editor_data ): void {
        \WSR\Cache::purge_post( $post_id );
        $this->log( "Elementor save — purged post #{$post_id}" );
    }

    /**
     * Fires after an Elementor document is saved (newer hook).
     *
     * @param \Elementor\Core\Base\Document $document
     * @param array                         $data
     */
    public function on_document_save( $document, array $data ): void {
        $post_id = $document->get_post()->ID ?? 0;
        if ( $post_id ) {
            \WSR\Cache::purge_post( $post_id );
            $this->log( "Elementor document save — purged post #{$post_id}" );
        }
    }

    /**
     * Global change — flush entire cache.
     */
    public function on_global_flush(): void {
        \WSR\Cache::flush();
        $this->log( 'Elementor global flush — full cache cleared.' );
    }

    /**
     * CSS file enqueued for a post — purge that post.
     *
     * @param \Elementor\Core\Files\CSS\Post $css_file
     */
    public function on_css_update( $css_file ): void {
        if ( method_exists( $css_file, 'get_post_id' ) ) {
            $post_id = $css_file->get_post_id();
            if ( $post_id ) {
                \WSR\Cache::purge_post( $post_id );
            }
        }
    }

    private function log( string $message ): void {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( '[WSR Elementor] ' . $message );
        }
    }
}
