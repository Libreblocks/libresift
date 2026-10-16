<?php
/**
 * LibreSift unit tests reuse the LibreForge bootstrap (core stubs + in-memory $wpdb).
 */

require dirname( __DIR__, 2 ) . '/libreforge/tests/bootstrap.php';

LibreBlocks\Core\Autoloader::register( 'LibreBlocks\\Sift\\', dirname( __DIR__ ) . '/src/' );
