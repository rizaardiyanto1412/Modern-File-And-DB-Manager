<?php

define( 'ABSPATH', sys_get_temp_dir() . '/mfm-tests-root/' );
define( 'MFM_TEST_ROOT', ABSPATH );

require_once __DIR__ . '/helpers/wp-stubs.php';
require_once __DIR__ . '/helpers/mfm-stubs.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-filesystem-service.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rest-controller.php';
