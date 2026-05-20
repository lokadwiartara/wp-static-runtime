<?php
namespace WSR\Premium\CDN;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Cloudflare CDN — purges via Cloudflare Cache API.
 *
 * Docs: https://developers.cloudflare.com/api/operations/zone-purge
 */
class Cloudflare implements CDN_Provider {

    private string $email;
    private string $api_key;
    private string $zone_id;

    private const API_BASE = 'https://api.cloudflare.com/client/v4/zones/';

    public function __construct( string $email, string $api_key, string $zone_id ) {
        $this->email   = $email;
        $this->api_key = $api_key;
        $this->zone_id = $zone_id;
    }

    public function name(): string {
        return 'cloudflare';
    }

    /**
     * Purge a single URL.
     */
    public function purge_url( string $url ): array {
        return $this->purge_urls( [ $url ] );
    }

    /**
     * Purge multiple URLs (Cloudflare accepts up to 30 per request).
     *
     * @param string[] $urls
     */
    public function purge_urls( array $urls ): array {
        $chunks = array_chunk( $urls, 30 );
        $results = [];

        foreach ( $chunks as $chunk ) {
            $response = $this->request( 'DELETE',
                self::API_BASE . $this->zone_id . '/purge_cache',
                [ 'files' => $chunk ]
            );
            $results[] = $response;
        }

        $success = ! empty( array_filter( $results, function( $r ) {
            return isset( $r['success'] ) ? $r['success'] : false;
        } ) );
        return [ 'success' => $success, 'results' => $results ];
    }

    /**
     * Purge everything (purge_everything).
     */
    public function purge_all(): array {
        return $this->request( 'DELETE',
            self::API_BASE . $this->zone_id . '/purge_cache',
            [ 'purge_everything' => true ]
        );
    }

    /**
     * Make an authenticated request to the Cloudflare API.
     */
    private function request( string $method, string $endpoint, array $body = [] ): array {
        if ( empty( $this->zone_id ) || empty( $this->api_key ) ) {
            return [ 'success' => false, 'message' => 'Cloudflare credentials not configured.' ];
        }

        $response = wp_remote_request( $endpoint, [
            'method'  => $method,
            'timeout' => 15,
            'headers' => [
                'X-Auth-Email' => $this->email,
                'X-Auth-Key'   => $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode( $body ),
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'message' => $response->get_error_message() ];
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return [
            'success' => ! empty( $data['success'] ),
            'message' => isset( $data['errors'][0]['message'] ) ? $data['errors'][0]['message'] : 'OK',
            'raw'     => $data,
        ];
    }
}
