<?php
/**
 * Présentation de l'auteur en tête de sa page : ce qui fonde la confiance dans ses guides (E-E-A-T).
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;

$tt_user = is_author() ? get_queried_object() : null;
if ( ! $tt_user instanceof WP_User ) {
	return;
}
$tt_role  = (string) get_user_meta( $tt_user->ID, 'tt_role', true );
$tt_count = count_user_posts( $tt_user->ID, 'post', true );
// Profils renseignés dans le profil utilisateur (champs Yoast) et site personnel.
$tt_links = array();
foreach ( array( 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'twitter' => 'X' ) as $tt_key => $tt_label ) {
	$tt_val = trim( (string) get_user_meta( $tt_user->ID, $tt_key, true ) );
	if ( '' !== $tt_val ) {
		$tt_links[ $tt_label ] = 'twitter' === $tt_key && ! str_starts_with( $tt_val, 'http' ) ? 'https://x.com/' . ltrim( $tt_val, '@' ) : $tt_val;
	}
}
if ( $tt_user->user_url ) {
	$tt_links['Site'] = $tt_user->user_url;
}
?>
<section <?php echo get_block_wrapper_attributes( array( 'class' => 'tt-author' ) ); ?>>
	<?php echo get_avatar( $tt_user->ID, 160, '', '', array( 'class' => 'tt-author__img', 'loading' => 'eager' ) ); ?>
	<div class="tt-author__body">
		<?php if ( $tt_role ) : ?><p class="tt-author__role"><?php echo esc_html( $tt_role ); ?></p><?php endif; ?>
		<h1 class="tt-author__name"><?php echo esc_html( $tt_user->display_name ); ?></h1>
		<?php if ( $tt_user->description ) : ?><div class="tt-author__bio"><?php echo wp_kses_post( wpautop( $tt_user->description ) ); ?></div><?php endif; ?>
		<p class="tt-author__meta">
			<span><?php echo esc_html( sprintf( _n( '%s article publié', '%s articles publiés', $tt_count, 'tt' ), number_format_i18n( $tt_count ) ) ); ?></span>
			<?php foreach ( $tt_links as $tt_label => $tt_url ) : ?>
				<a href="<?php echo esc_url( $tt_url ); ?>" rel="me noopener" target="_blank"><?php echo esc_html( $tt_label ); ?></a>
			<?php endforeach; ?>
		</p>
	</div>
</section>
