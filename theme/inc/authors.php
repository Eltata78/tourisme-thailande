<?php
/**
 * Auteurs : photo de profil depuis la médiathèque (méta utilisateur `tt_avatar_id`) et champs E-E-A-T.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	static function () {
		register_meta( 'user', 'tt_avatar_id', array( 'type' => 'integer', 'single' => true, 'show_in_rest' => true ) );
		register_meta( 'user', 'tt_role', array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'description' => 'Rôle affiché sous le nom (ex. Rédacteur, plongeur).' ) );
	}
);

add_filter(
	'pre_get_avatar_data',
	static function ( array $args, $id_or_email ): array {
		$user_id = 0;
		if ( is_numeric( $id_or_email ) ) {
			$user_id = (int) $id_or_email;
		} elseif ( $id_or_email instanceof WP_User ) {
			$user_id = $id_or_email->ID;
		} elseif ( $id_or_email instanceof WP_Post ) {
			$user_id = (int) $id_or_email->post_author;
		} elseif ( $id_or_email instanceof WP_Comment ) {
			$user_id = (int) $id_or_email->user_id;
		}
		$att = $user_id ? (int) get_user_meta( $user_id, 'tt_avatar_id', true ) : 0;
		if ( $att ) {
			// Taille nommée : les tailles sur mesure passent par des vignettes Elementor pas toujours générées.
			$src = wp_get_attachment_image_src( $att, (int) $args['size'] <= 75 ? 'thumbnail' : 'medium' );
			if ( $src ) {
				$args['url'] = $src[0];
			}
		}
		return $args;
	},
	10,
	2
);

/**
 * Champ « Photo (ID média) » et « Rôle » sur la page de profil.
 */
$tt_profile_fields = static function ( WP_User $user ) {
	?>
	<h2>Tourisme Thaïlande</h2>
	<table class="form-table">
		<tr><th><label for="tt_avatar_id">Photo (ID de l'image dans la médiathèque)</label></th>
			<td><input type="number" name="tt_avatar_id" id="tt_avatar_id" value="<?php echo esc_attr( get_user_meta( $user->ID, 'tt_avatar_id', true ) ); ?>" class="small-text"></td></tr>
		<tr><th><label for="tt_role">Rôle affiché</label></th>
			<td><input type="text" name="tt_role" id="tt_role" value="<?php echo esc_attr( get_user_meta( $user->ID, 'tt_role', true ) ); ?>" class="regular-text"></td></tr>
	</table>
	<?php
};
add_action( 'show_user_profile', $tt_profile_fields );
add_action( 'edit_user_profile', $tt_profile_fields );

$tt_profile_save = static function ( int $user_id ) {
	if ( ! current_user_can( 'edit_user', $user_id ) || ! isset( $_POST['tt_avatar_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	update_user_meta( $user_id, 'tt_avatar_id', absint( $_POST['tt_avatar_id'] ) ); // phpcs:ignore
	update_user_meta( $user_id, 'tt_role', sanitize_text_field( wp_unslash( $_POST['tt_role'] ?? '' ) ) ); // phpcs:ignore
};
add_action( 'personal_options_update', $tt_profile_save );
add_action( 'edit_user_profile_update', $tt_profile_save );
