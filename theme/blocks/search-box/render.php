<?php
/**
 * Bloc recherche.
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'tt-search-block' ) ); ?>>
	<?php echo tt_search_form( 'b' . substr( md5( wp_json_encode( $attributes ) . wp_rand() ), 0, 6 ), $attributes['placeholder'] ); // phpcs:ignore ?>
</div>
