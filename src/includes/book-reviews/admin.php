<?php
/**
 * Book review admin UI: Libby import page + ISBN meta box.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register Libby import submenu under Book Reviews.
 */
function spenpo_register_libby_import_page() {
    add_submenu_page(
        'edit.php?post_type=book_review',
        'Import Libby Export',
        'Import Libby',
        'edit_posts',
        'spenpo-libby-import',
        'spenpo_render_libby_import_page'
    );
}
add_action('admin_menu', 'spenpo_register_libby_import_page');

/**
 * Handle Libby JSON file upload and render the import page.
 */
function spenpo_render_libby_import_page() {
    if (!current_user_can('edit_posts')) {
        wp_die(esc_html__('You do not have permission to import books.', 'twentytwentyfour-spenpo'));
    }

    $summary = null;

    if (
        isset($_SERVER['REQUEST_METHOD']) &&
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset($_POST['spenpo_libby_import_nonce']) &&
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['spenpo_libby_import_nonce'])), 'spenpo_libby_import')
    ) {
        if (empty($_FILES['libby_json']['tmp_name'])) {
            $summary = [
                'created'      => 0,
                'skipped'      => 0,
                'missing_isbn' => 0,
                'errors'       => ['No file uploaded.'],
            ];
        } else {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
            $raw = file_get_contents($_FILES['libby_json']['tmp_name']);
            $payload = json_decode($raw, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($payload)) {
                $summary = [
                    'created'      => 0,
                    'skipped'      => 0,
                    'missing_isbn' => 0,
                    'errors'       => ['Invalid JSON: ' . json_last_error_msg()],
                ];
            } else {
                $summary = spenpo_import_libby_timeline($payload);
            }
        }
    }

    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Import Libby Export', 'twentytwentyfour-spenpo'); ?></h1>
        <p>
            <?php echo esc_html__('Upload a Libby timeline JSON export. New books are created as drafts keyed by ISBN. Existing reviews are skipped.', 'twentytwentyfour-spenpo'); ?>
        </p>

        <?php if (is_array($summary)) : ?>
            <div class="notice notice-info is-dismissible">
                <p>
                    <?php
                    printf(
                        /* translators: 1: created count, 2: skipped count, 3: missing ISBN count */
                        esc_html__('Import finished: %1$d created, %2$d skipped, %3$d missing ISBN.', 'twentytwentyfour-spenpo'),
                        (int) $summary['created'],
                        (int) $summary['skipped'],
                        (int) $summary['missing_isbn']
                    );
                    ?>
                </p>
                <?php if (!empty($summary['errors'])) : ?>
                    <ul>
                        <?php foreach ($summary['errors'] as $error) : ?>
                            <li><?php echo esc_html($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('spenpo_libby_import', 'spenpo_libby_import_nonce'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="libby_json"><?php echo esc_html__('Libby JSON file', 'twentytwentyfour-spenpo'); ?></label>
                    </th>
                    <td>
                        <input type="file" id="libby_json" name="libby_json" accept="application/json,.json" required />
                    </td>
                </tr>
            </table>
            <?php submit_button(__('Import as drafts', 'twentytwentyfour-spenpo')); ?>
        </form>

        <hr />
        <p>
            <strong><?php echo esc_html__('REST API', 'twentytwentyfour-spenpo'); ?>:</strong>
            <code>POST <?php echo esc_html(rest_url('spenpo/v1/libby-import')); ?></code>
            <?php echo esc_html__('(requires authentication with edit_posts capability)', 'twentytwentyfour-spenpo'); ?>
        </p>
    </div>
    <?php
}

/**
 * Register ISBN meta box on book_review editor.
 */
function spenpo_register_book_review_meta_box() {
    add_meta_box(
        'spenpo_book_details',
        __('Bookshop / Libby details', 'twentytwentyfour-spenpo'),
        'spenpo_render_book_review_meta_box',
        'book_review',
        'side',
        'high'
    );
}
add_action('add_meta_boxes', 'spenpo_register_book_review_meta_box');

/**
 * Render book details meta box.
 *
 * @param WP_Post $post
 */
function spenpo_render_book_review_meta_box($post) {
    wp_nonce_field('spenpo_save_book_review_meta', 'spenpo_book_review_meta_nonce');

    $book_isbn = (string) get_post_meta($post->ID, 'book_isbn', true);
    $bookshop_isbn = (string) get_post_meta($post->ID, 'bookshop_isbn', true);
    $author = (string) get_post_meta($post->ID, 'book_author', true);
    $format = (string) get_post_meta($post->ID, 'book_format', true);
    $publisher = (string) get_post_meta($post->ID, 'book_publisher', true);

    $affiliate_isbn = $bookshop_isbn !== '' ? $bookshop_isbn : $book_isbn;
    $affiliate_url = spenpo_bookshop_affiliate_url($affiliate_isbn);

    ?>
    <p>
        <label for="spenpo_book_isbn"><strong><?php echo esc_html__('Libby ISBN', 'twentytwentyfour-spenpo'); ?></strong></label><br />
        <input type="text" class="widefat" id="spenpo_book_isbn" name="spenpo_book_isbn" value="<?php echo esc_attr($book_isbn); ?>" />
    </p>
    <p>
        <label for="spenpo_bookshop_isbn"><strong><?php echo esc_html__('Bookshop ISBN override', 'twentytwentyfour-spenpo'); ?></strong></label><br />
        <input type="text" class="widefat" id="spenpo_bookshop_isbn" name="spenpo_bookshop_isbn" value="<?php echo esc_attr($bookshop_isbn); ?>" />
        <span class="description"><?php echo esc_html__('Use when the Libby/audiobook ISBN is not on Bookshop.org.', 'twentytwentyfour-spenpo'); ?></span>
    </p>
    <p>
        <label for="spenpo_book_author"><strong><?php echo esc_html__('Author', 'twentytwentyfour-spenpo'); ?></strong></label><br />
        <input type="text" class="widefat" id="spenpo_book_author" name="spenpo_book_author" value="<?php echo esc_attr($author); ?>" />
    </p>
    <p>
        <label for="spenpo_book_format"><strong><?php echo esc_html__('Format', 'twentytwentyfour-spenpo'); ?></strong></label><br />
        <input type="text" class="widefat" id="spenpo_book_format" name="spenpo_book_format" value="<?php echo esc_attr($format); ?>" />
    </p>
    <p>
        <label for="spenpo_book_publisher"><strong><?php echo esc_html__('Publisher', 'twentytwentyfour-spenpo'); ?></strong></label><br />
        <input type="text" class="widefat" id="spenpo_book_publisher" name="spenpo_book_publisher" value="<?php echo esc_attr($publisher); ?>" />
    </p>
    <?php if ($affiliate_url) : ?>
        <p>
            <strong><?php echo esc_html__('Bookshop link', 'twentytwentyfour-spenpo'); ?></strong><br />
            <a href="<?php echo esc_url($affiliate_url); ?>" target="_blank" rel="noopener noreferrer">
                <?php echo esc_html($affiliate_url); ?>
            </a>
        </p>
    <?php else : ?>
        <p class="description"><?php echo esc_html__('Add an ISBN to preview the Bookshop.org affiliate link.', 'twentytwentyfour-spenpo'); ?></p>
    <?php endif; ?>
    <?php
}

/**
 * Persist meta box fields.
 *
 * @param int $post_id
 */
function spenpo_save_book_review_meta($post_id) {
    if (!isset($_POST['spenpo_book_review_meta_nonce'])) {
        return;
    }

    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['spenpo_book_review_meta_nonce'])), 'spenpo_save_book_review_meta')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (get_post_type($post_id) !== 'book_review') {
        return;
    }

    $fields = [
        'spenpo_book_isbn'        => 'book_isbn',
        'spenpo_bookshop_isbn'    => 'bookshop_isbn',
        'spenpo_book_author'      => 'book_author',
        'spenpo_book_format'      => 'book_format',
        'spenpo_book_publisher'   => 'book_publisher',
    ];

    foreach ($fields as $request_key => $meta_key) {
        if (!isset($_POST[$request_key])) {
            continue;
        }
        $value = sanitize_text_field(wp_unslash($_POST[$request_key]));
        if ($meta_key === 'book_isbn' || $meta_key === 'bookshop_isbn') {
            $value = preg_replace('/[^0-9X]/i', '', $value);
        }
        update_post_meta($post_id, $meta_key, $value);
    }
}
add_action('save_post_book_review', 'spenpo_save_book_review_meta');
