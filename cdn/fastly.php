<?php
namespace WSR\Premium\CDN;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Fastly CDN — purges via Fastly Purge API.
 * Docs: https://developer.fastly.com/reference/api/purging/
 */
class Fastly implements CDN_Provider {

    private string $api_key;
    private string $service_id;

    private const API_BASE = 'https://api.fastly.com/';

    public function __construct( string $api_key, string $service_id ) {
        $this->api_key    = $api_key;
        $this->service_id = $service_id;
    }

    public function name(): string { return 'fastly'; }

    /**
     * Purge a single URL using surrogate key based on the URL path.
     */
    public function purge_url( string $url ): array {
        // Fastly URL purge
        $endpoint = self::API_BASE . 'purge/' . ltrim( wp_parse_url( $url, PHP_URL_PATH ), '/' );
        return $this->request( 'PURGE', $endpoint );
    }

    public function purge_urls( array $urls ): array {
        $results = [];
        foreach ( $urls as $url ) {
            $results[] = $this->purge_url( $url );
        }
        return [ 'success' => true, 'results' => $results ];
    }

    /**
     * Purge entire service (purge_all).
     */
    public function purge_all(): array {
        if ( empty( $this->service_id ) ) {
            return [ 'success' => false, 'message' => 'Fastly Service ID not configured.' ];
        }
        $endpoint = self::API_BASE . 'service/' . $this->service_id . '/purge_all';
        return $this->request( 'POST', $endpoint );
    }

    private function request( string $method, string $endpoint, array $body = [] ): array {
        if ( empty( $this->api_key ) ) {
            return [ 'success' => false, 'message' => 'Fastly API key not configured.' ];
        }

        $args = [
            'method'  => $method,
            'timeout' => 15,
            'headers' => [
                'Fastly-Key'   => $this->api_key,
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
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
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        return [
            'success' => $code >= 200 && $code < 300,
            'code'    => $code,
            'status'  => $data['status'] ?? '',
        ];
    }
}
