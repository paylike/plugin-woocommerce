<?php

if ( ! function_exists( 'dk_get_locale' ) ) {

	function dk_get_locale() {
		$locale = get_locale();
		$norwegian_compatibility = false;
		if ( defined( 'PAYLIKE_NORWEGIAN_BOKMAL_COMPATIBILITY' ) ) {
			$norwegian_compatibility = PAYLIKE_NORWEGIAN_BOKMAL_COMPATIBILITY;
		}
		if ( in_array( $locale, array( 'nb_NO' ) ) && $norwegian_compatibility ) {
			$locale = 'no_NO';
		}

		return $locale;
	}
}
