<?php
/**
 * REST endpoint for Libby JSON import.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register spenpo/v1/libby-import.
 */
function spenpo_register_libby_import_rest_route() {
    register_rest_route('spenpo/v1', '/libby-import', [
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'spenpo_rest_libby_import',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        },
    ]);
}
add_action('rest_api_init', 'spenpo_register_libby_import_rest_route');

/**
 * Handle authenticated Libby import requests.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function spenpo_rest_libby_import(WP_REST_Request $request) {
    $payload = $request->get_json_params();

    if (empty($payload)) {
        // Allow raw body if Content-Type was not application/json.
        $raw = $request->get_body();
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $payload = $decoded;
            }
        }
    }

    if (!is_array($payload)) {
        return new WP_Error(
            'spenpo_invalid_libby_json',
            'Request body must be valid Libby JSON (object with timeline or an array).',
            ['status' => 400]
        );
    }

    $summary = spenpo_import_libby_timeline($payload);

    return rest_ensure_response([
        'ok'           => empty($summary['errors']),
        'created'      => (int) $summary['created'],
        'skipped'      => (int) $summary['skipped'],
        'missing_isbn' => (int) $summary['missing_isbn'],
        'errors'       => array_values($summary['errors']),
    ]);
}
