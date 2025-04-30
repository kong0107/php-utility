<?php
/**
 * Output Buffering Control
 * @see https://www.php.net/manual/en/book.outcontrol.php
 */


/**
 * Turn all output buffer off and implicit flushing on.
 * @param bool $return True to get the buffer contents, or false to drop them.
 * @return string[]|int Array of each buffer content, or the nesting level turned off.
 */
function ob_end_all($return = false) {
    $buffers = array();
    while (ob_get_level()) $buffers[] = $return ? ob_get_clean() : ob_end_clean();
    ob_implicit_flush(1);
    return $return ? $buffers : count($buffers);
}
