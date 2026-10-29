<?php
/**
 * Auteurs du site (page « Qui sommes-nous ») : profil WordPress, toujours à jour.
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;

$tt_users = get_users( array( 'has_published_posts' => array( 'post' ), 'orderby' => 'display_name' ) );
if ( ! $tt_users ) {
	return;
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'tt-authors' ) ); ?>>
	<?php foreach ( $tt_users as $tt_user ) : ?>
		<?php
		$tt_count = count_user_posts( $tt_user->ID, 'post', true );
		$tt_role  = (string) get_user_meta( $tt_user->ID, 'tt_role', true );
		?>
		<article class="tt-authors__card">
			<?php echo get_avatar( $tt_user->ID, 112, '', '', array( 'class' => 'tt-authors__img', 'loading' => 'lazy' ) ); ?>
			<h3><?php echo esc_html( $tt_user->display_name ); ?></h3>
			<?php if ( $tt_role ) : ?><p class="tt-authors__role"><?php echo esc_html( $tt_role ); ?></p><?php endif; ?>
			<?php if ( $tt_user->description ) : ?><p class="tt-authors__bio"><?php echo esc_html( wp_trim_words( $tt_user->description, 34 ) ); ?></p><?php endif; ?>
			<a href="<?php echo esc_url( get_author_posts_url( $tt_user->ID ) ); ?>"><?php echo esc_html( sprintf( _n( 'Lire son guide', 'Lire ses %s guides', $tt_count, 'tt' ), number_format_i18n( $tt_count ) ) ); ?></a>
		</article>
	<?php endforeach; ?>
</div>
