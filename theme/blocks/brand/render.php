<?php
/**
 * Logo du site (emblème et nom), lien vers l'accueil.
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;
?>
<a <?php echo get_block_wrapper_attributes( array( 'class' => 'tt-brand-link' ) ); ?> href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php echo tt_brand(); // phpcs:ignore ?></a>
