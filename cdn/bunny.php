<?php
namespace WSR\Premium\CDN;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * BunnyCDN — purges via BunnyCDN API.
 * Docs: https://docs.bunny.net/reference/purgepublicpullzone
 */
class BunnyCDN implements CDN_Provider {

    private string $api_key;
    private string $storage_zone;
    private string $pull_zone_id;

    private const API_BASE = 'https://api.bunny.net/';

    public function __construct( string $api_key, string $storage_zone, string $pull_zone_id ) {
        $this->api_key      = $api_key;
        $this->storage_zone = $storage_zone;
        $this->pull_zone_id = $pull_zone_id;
    }

    public function name(): string { return 'bunnycdn'; }

    public function purge_url( string $url ): array {
        $endpoint = self::API_BASE . 'purge?' . http_build_query( [ 'url' => $url, 'async' => 'false' ] );
        return $this->request( 'POST', $endpoint );
    }

    public function purge_urls( array $urls ): array {
        $results = [];
        foreach ( $urls as $url ) {
            $results[] = $this->purge_url( $url );
        }
        return [ 'success' => true, 'results' => $results ];
    }

    public function purge_all(): array {
        if ( empty( $this->pull_zone_id ) ) {
            return [ 'success' => false, 'message' => 'BunnyCDN Pull Zone ID not configured.' ];
        }
        $endpoint = self::API_BASE . 'pullzone/' . $this->pull_zone_id . '/purgeCache';
        return $this->request( 'POST', $endpoint );
    }

    private function request( string $method, string $endpoint, array $body = [] ): array {
        if ( empty( $this->api_key ) ) {
            return [ 'success' => false, 'message' => 'BunnyCDN API key not configured.' ];
        }

        $args = [
            'method'  => $method,
            'timeout' => 15,
            'headers' => [
                'AccessKey'    => $this->api_key,
                'Content-Type' => 'application/json',
            ],
        ];
        if ( ! empty( $body ) ) {
            $args['body'] = wp_json_encode( $body );
        }

        $response = wp_remote_request( $endpoint, $args );

        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'message' => $response->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        return [
            'success' => $code >= 200 && $code < 300,
            'code'    => $code,
            'body'    => wp_remote_retrieve_body( $response ),
        ];
    }
}
