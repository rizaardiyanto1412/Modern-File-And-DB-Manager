<?php

namespace ModernFileManager;

if ( ! function_exists( __NAMESPACE__ . '\\is_uploaded_file' ) ) {
	function is_uploaded_file( $filename ) {
		if ( isset( $GLOBALS['mfm_test_is_uploaded_file'] ) ) {
			return (bool) $GLOBALS['mfm_test_is_uploaded_file'];
		}
		return \is_uploaded_file( $filename );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\move_uploaded_file' ) ) {
	function move_uploaded_file( $from, $to ) {
		if ( isset( $GLOBALS['mfm_test_force_move_uploaded_file'] ) ) {
			if ( ! $GLOBALS['mfm_test_force_move_uploaded_file'] ) {
				return false;
			}
			return @copy( $from, $to );
		}
		return \move_uploaded_file( $from, $to );
	}
}
