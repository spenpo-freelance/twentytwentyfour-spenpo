<?php
/**
 * Shared Libby JSON upsert for book_review drafts.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Normalize Libby export payload into a list of timeline entries.
 *
 * @param mixed $payload Decoded JSON (object/array).
 * @return array<int, array<string, mixed>>
 */
function spenpo_normalize_libby_timeline($payload) {
    if (!is_array($payload)) {
        return [];
    }

    if (isset($payload['timeline']) && is_array($payload['timeline'])) {
        return array_values($payload['timeline']);
    }

    // Raw list of entries (PHP 8.1+ array_is_list, with fallback).
    $is_list = function_exists('array_is_list')
        ? array_is_list($payload)
        : array_keys($payload) === range(0, count($payload) - 1);

    if ($is_list) {
        return $payload;
    }

    return [];
}

/**
 * Find an existing book_review by ISBN meta.
 *
 * @param string $isbn
 * @return int Post ID or 0.
 */
function spenpo_find_book_review_by_isbn($isbn) {
    $existing = get_posts([
        'post_type'      => 'book_review',
        'post_status'    => ['publish', 'draft', 'pending', 'private', 'future'],
        'meta_key'       => 'book_isbn',
        'meta_value'     => $isbn,
        'posts_per_page' => 1,
        'fields'         => 'ids',
    ]);

    return !empty($existing) ? (int) $existing[0] : 0;
}

/**
 * Optionally sideload a cover image as the featured image.
 *
 * @param int    $post_id
 * @param string $cover_url
 * @param string $title
 * @return int Attachment ID or 0.
 */
function spenpo_sideload_book_cover($post_id, $cover_url, $title) {
    if (empty($cover_url) || !filter_var($cover_url, FILTER_VALIDATE_URL)) {
        return 0;
    }

    if (!function_exists('media_sideload_image')) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    $attachment_id = media_sideload_image($cover_url, $post_id, $title, 'id');
    if (is_wp_error($attachment_id)) {
        return 0;
    }

    set_post_thumbnail($post_id, (int) $attachment_id);
    return (int) $attachment_id;
}

/**
 * Upsert Libby timeline entries as draft book_review posts.
 *
 * @param mixed $payload Decoded Libby JSON.
 * @return array{created:int,skipped:int,missing_isbn:int,errors:string[]}
 */
function spenpo_import_libby_timeline($payload) {
    $result = [
        'created'      => 0,
        'skipped'      => 0,
        'missing_isbn' => 0,
        'errors'       => [],
    ];

    $timeline = spenpo_normalize_libby_timeline($payload);
    if (empty($timeline)) {
        $result['errors'][] = 'No timeline entries found in payload.';
        return $result;
    }

    foreach ($timeline as $index => $entry) {
        if (!is_array($entry)) {
            $result['errors'][] = "Entry {$index} is not an object.";
            continue;
        }

        $isbn = isset($entry['isbn']) ? preg_replace('/[^0-9X]/i', '', (string) $entry['isbn']) : '';
        if ($isbn === '') {
            $result['missing_isbn']++;
            continue;
        }

        if (spenpo_find_book_review_by_isbn($isbn)) {
            $result['skipped']++;
            continue;
        }

        $title_text = '';
        if (isset($entry['title']['text']) && is_string($entry['title']['text'])) {
            $title_text = $entry['title']['text'];
        } elseif (isset($entry['cover']['title']) && is_string($entry['cover']['title'])) {
            $title_text = $entry['cover']['title'];
        }

        if ($title_text === '') {
            $title_text = 'Untitled book (' . $isbn . ')';
        }

        $post_id = wp_insert_post([
            'post_title'   => sanitize_text_field($title_text),
            'post_type'    => 'book_review',
            'post_status'  => 'draft',
            'post_content' => '',
        ], true);

        if (is_wp_error($post_id)) {
            $result['errors'][] = sprintf(
                'Failed to create "%s": %s',
                $title_text,
                $post_id->get_error_message()
            );
            continue;
        }

        $author = isset($entry['author']) ? sanitize_text_field((string) $entry['author']) : '';
        $format = isset($entry['cover']['format']) ? sanitize_text_field((string) $entry['cover']['format']) : '';
        $publisher = isset($entry['publisher']) ? sanitize_text_field((string) $entry['publisher']) : '';
        $title_id = '';
        if (isset($entry['title']['titleId'])) {
            $title_id = sanitize_text_field((string) $entry['title']['titleId']);
        }
        $cover_url = isset($entry['cover']['url']) ? esc_url_raw((string) $entry['cover']['url']) : '';
        $borrowed_at = '';
        if (isset($entry['timestamp'])) {
            $borrowed_at = sanitize_text_field((string) $entry['timestamp']);
        }

        update_post_meta($post_id, 'book_isbn', $isbn);
        update_post_meta($post_id, 'book_author', $author);
        update_post_meta($post_id, 'book_format', $format);
        update_post_meta($post_id, 'book_publisher', $publisher);
        update_post_meta($post_id, 'libby_title_id', $title_id);
        update_post_meta($post_id, 'libby_cover_url', $cover_url);
        update_post_meta($post_id, 'libby_borrowed_at', $borrowed_at);

        if ($cover_url) {
            spenpo_sideload_book_cover($post_id, $cover_url, $title_text);
        }

        $result['created']++;
    }

    return $result;
}
