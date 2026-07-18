<?php
/**
 * Book review / Bookshop.org constants.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('SPENPO_BOOKSHOP_AFFILIATE_ID')) {
    define('SPENPO_BOOKSHOP_AFFILIATE_ID', '125387');
}

/**
 * Meta keys registered on book_review posts.
 *
 * @return string[]
 */
function spenpo_book_review_meta_keys() {
    return [
        'book_isbn',
        'bookshop_isbn',
        'book_author',
        'book_format',
        'book_publisher',
        'libby_title_id',
        'libby_cover_url',
        'libby_borrowed_at',
    ];
}

/**
 * Build a Bookshop.org affiliate URL for an ISBN.
 *
 * @param string $isbn
 * @return string
 */
function spenpo_bookshop_affiliate_url($isbn) {
    $isbn = preg_replace('/[^0-9X]/i', '', (string) $isbn);
    if ($isbn === '') {
        return '';
    }

    return sprintf(
        'https://bookshop.org/a/%s/%s',
        SPENPO_BOOKSHOP_AFFILIATE_ID,
        rawurlencode($isbn)
    );
}
