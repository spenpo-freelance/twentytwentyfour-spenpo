<?php
/**
 * Register book_review CPT and REST-exposed meta.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the book_review custom post type.
 */
function spenpo_register_book_review_cpt() {
    $labels = [
        'name'               => 'Book Reviews',
        'singular_name'      => 'Book Review',
        'menu_name'          => 'Book Reviews',
        'name_admin_bar'     => 'Book Review',
        'add_new'            => 'Add New',
        'add_new_item'       => 'Add New Book Review',
        'new_item'           => 'New Book Review',
        'edit_item'          => 'Edit Book Review',
        'view_item'          => 'View Book Review',
        'all_items'          => 'All Book Reviews',
        'search_items'       => 'Search Book Reviews',
        'not_found'          => 'No book reviews found.',
        'not_found_in_trash' => 'No book reviews found in Trash.',
    ];

    register_post_type('book_review', [
        'labels'              => $labels,
        'public'              => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_rest'        => true,
        'rest_base'           => 'book_review',
        'menu_icon'           => 'dashicons-book-alt',
        'capability_type'     => 'post',
        'map_meta_cap'        => true,
        'hierarchical'        => false,
        'supports'            => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
        'has_archive'         => false,
        'rewrite'             => ['slug' => 'book-review'],
        'exclude_from_search' => false,
    ]);
}
add_action('init', 'spenpo_register_book_review_cpt');

/**
 * Register book review post meta for admin + REST.
 */
function spenpo_register_book_review_meta() {
    $string_keys = [
        'book_isbn',
        'bookshop_isbn',
        'book_author',
        'book_format',
        'book_publisher',
        'libby_title_id',
        'libby_cover_url',
        'libby_borrowed_at',
    ];

    foreach ($string_keys as $key) {
        register_post_meta('book_review', $key, [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
            'auth_callback'     => function () {
                return current_user_can('edit_posts');
            },
            'sanitize_callback' => 'sanitize_text_field',
        ]);
    }
}
add_action('init', 'spenpo_register_book_review_meta');

/**
 * Flush rewrite rules once after book_review CPT registration.
 */
function spenpo_maybe_flush_book_review_rewrites() {
    $flag = 'spenpo_book_review_rewrite_flushed_v1';
    if (get_option($flag)) {
        return;
    }

    flush_rewrite_rules(false);
    update_option($flag, '1');
}
add_action('init', 'spenpo_maybe_flush_book_review_rewrites', 20);
