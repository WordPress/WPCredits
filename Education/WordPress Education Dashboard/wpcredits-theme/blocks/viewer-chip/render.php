<?php
/**
 * Server render for the viewer chip.
 *
 * Who is signed in, whether they hold the plugin's Mentor role, and whether the
 * mentor page exists are all request-time facts, which is why the header carries
 * a dynamic block here rather than static links.
 *
 * The link's label follows the plugin's own wording — "My Students" for mentors,
 * "Mentor Dashboard" for program managers inspecting it — so the header and the admin
 * bar never disagree about what the same page is called.
 *
 * @package WPCredits_Theme
 */

$wpcredits_page = wpcredits_mentor_page_url();

if ( ! is_user_logged_in() ) {
	$wpcredits_redirect = '' !== $wpcredits_page ? $wpcredits_page : home_url( '/' );

	printf(
		// get_block_wrapper_attributes() returns an already-escaped attribute
		// string; running it through an escaper again would mangle the quotes.
		'<div %1$s><a class="wpc-viewer__signin" href="%2$s">%3$s</a></div>',
		get_block_wrapper_attributes( array( 'class' => 'wpc-viewer wpc-viewer--guest' ) ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		esc_url( wp_login_url( $wpcredits_redirect ) ),
		esc_html__( 'Log in', 'wpcredits-theme' )
	);

	return;
}

$wpcredits_user = wp_get_current_user();

$wpcredits_can_manage = wpcredits_plugin_active() && class_exists( 'WPCPM_Roles' )
	&& current_user_can( WPCPM_Roles::CAP_MANAGE );
$wpcredits_is_mentor  = wpcredits_viewer_is_mentor();
$wpcredits_on_page    = wpcredits_is_mentor_page();

// A student's own page. Shown alongside the mentor link rather than instead of it:
// somebody can legitimately be both, and hiding one of them would strand them.
$wpcredits_student_page = wpcredits_student_page_url();
$wpcredits_is_student   = class_exists( 'WPCPM_Students_Dashboard' )
	&& WPCPM_Students_Dashboard::is_student();
$wpcredits_on_student   = wpcredits_is_student_page();

?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'wpc-viewer' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already-escaped attribute string from core. ?>>
	<?php if ( '' !== $wpcredits_student_page && ( $wpcredits_is_student || $wpcredits_can_manage ) ) : ?>
		<a
			class="wpc-viewer__link<?php echo $wpcredits_on_student ? ' is-current' : ''; ?>"
			href="<?php echo esc_url( $wpcredits_student_page ); ?>"
			<?php echo $wpcredits_on_student ? 'aria-current="page"' : ''; ?>
		>
			<?php
			echo esc_html(
				$wpcredits_is_student
					? __( 'My Program', 'wpcredits-theme' )
					: __( 'Student Dashboard', 'wpcredits-theme' )
			);
			?>
		</a>
	<?php endif; ?>

	<?php if ( '' !== $wpcredits_page && ( $wpcredits_is_mentor || $wpcredits_can_manage ) ) : ?>
		<a
			class="wpc-viewer__link<?php echo $wpcredits_on_page ? ' is-current' : ''; ?>"
			href="<?php echo esc_url( $wpcredits_page ); ?>"
			<?php echo $wpcredits_on_page ? 'aria-current="page"' : ''; ?>
		>
			<?php
			echo esc_html(
				$wpcredits_is_mentor
					? __( 'My Students', 'wpcredits-theme' )
					: __( 'Mentor Dashboard', 'wpcredits-theme' )
			);
			?>
		</a>
	<?php endif; ?>

	<a class="wpc-viewer__logout" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">
		<?php esc_html_e( 'Log out', 'wpcredits-theme' ); ?>
	</a>

	<span class="wpc-viewer__avatar" data-initials="<?php echo esc_attr( wpcredits_initials( $wpcredits_user->display_name ) ); ?>">
		<?php
		echo get_avatar(
			$wpcredits_user->ID,
			56,
			'',
			/* translators: %s: the signed-in person's name. */
			sprintf( __( 'Profile photo of %s', 'wpcredits-theme' ), $wpcredits_user->display_name ),
			array( 'class' => 'wpc-viewer__photo' )
		);
		?>
	</span>
</div>
