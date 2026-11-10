<?php
/**
 * The suggestion form, laid out as a ticket to fill in. Posts to admin-post.php; see
 * Submission\Handler. With JS off every field shows and the validator ignores the
 * new-ride and new-park ones unless "It's not on here yet" was picked.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner content.
 * @var WP_Block $block      Block instance.
 */

use DropAndPair\Queries;
use DropAndPair\Submission\Handler;
use DropAndPair\Submission\Validator;
use DropAndPair\Vocab;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only display state.
$wrapper = get_block_wrapper_attributes( array( 'class' => 'dp-suggest' ) );

if ( isset( $_GET['thanks'] ) ) : ?>
	<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
		<div class="dp-ticket dp-suggest__thanks" role="status">
			<div class="dp-ticket__main">
				<div class="dp-ticket__admit">Admit one · Suggestion</div>
				<p class="dp-suggest__thanks-text">Thanks! I'll read it before it goes up, and if it makes it onto the site your name will be next to it.</p>
				<p><a href="<?php echo esc_url( Handler::page_url() ); ?>">Suggest another pairing</a></p>
			</div>
			<div class="dp-ticket__stub"><span>Received</span></div>
		</div>
	</div>
	<?php
	return;
endif;

$token  = isset( $_GET['dp_form'] ) && is_string( $_GET['dp_form'] ) ? sanitize_text_field( wp_unslash( $_GET['dp_form'] ) ) : '';
$saved  = '' !== $token ? Handler::saved_form( $token ) : null;
$values = $saved['values'] ?? array();
$errors = $saved['errors'] ?? array();
$notice = $saved['form_error'] ?? null;
// Rate-limited posts come back with a fixed code and nothing stored.
if ( ! $notice && isset( $_GET['dp_error'] ) && is_string( $_GET['dp_error'] ) ) {
	$notice = Handler::error_message( sanitize_key( $_GET['dp_error'] ) );
}
if ( ! $notice && $errors ) {
	$notice = "A couple of things need sorting before I can take this. They're marked below.";
}

// Coming from a ride page: pre-select that ride, but only if it's one the list offers.
if ( ! isset( $values['ride_id'] ) && isset( $_GET['ride'] ) && is_string( $_GET['ride'] ) ) {
	$from = absint( $_GET['ride'] );
	if ( $from && Handler::is_published_ride( $from ) ) {
		$values['ride_id'] = (string) $from;
	}
}
// phpcs:enable

$rides = get_posts(
	array(
		'post_type'      => 'dp_ride',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'no_found_rows'  => true,
	)
);
$parks = get_posts(
	array(
		'post_type'      => 'dp_park',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'no_found_rows'  => true,
	)
);

$value = static fn( string $field ): string => (string) ( $values[ $field ] ?? '' );
$id    = static fn( string $field ): string => 'dp-suggest-' . str_replace( '_', '-', $field );

/** aria attributes plus the error line, for a field that may have one. */
$invalid = static function ( string $field, string $help = '' ) use ( $errors, $id ): string {
	$ids = array();
	if ( '' !== $help ) {
		$ids[] = $id( $field ) . '-help';
	}
	if ( isset( $errors[ $field ] ) ) {
		$ids[] = $id( $field ) . '-error';
	}
	$out = $ids ? ' aria-describedby="' . esc_attr( implode( ' ', $ids ) ) . '"' : '';
	return $out . ( isset( $errors[ $field ] ) ? ' aria-invalid="true"' : '' );
};
$error = static function ( string $field ) use ( $errors, $id ): string {
	return isset( $errors[ $field ] )
		? '<p class="dp-suggest__error" id="' . esc_attr( $id( $field ) . '-error' ) . '">' . esc_html( $errors[ $field ] ) . '</p>'
		: '';
};
$help = static function ( string $field, string $text ) use ( $id ): string {
	return '<p class="dp-suggest__help" id="' . esc_attr( $id( $field ) . '-help' ) . '">' . esc_html( $text ) . '</p>';
};
/** A one-line text input with its label, help and error. */
$text_field = static function ( string $field, string $label, int $max, string $help_text = '', string $type = 'text', string $autocomplete = 'off' ) use ( $id, $value, $invalid, $error, $help ): string {
	$html  = '<div class="dp-suggest__field">';
	$html .= '<label class="dp-label" for="' . esc_attr( $id( $field ) ) . '">' . esc_html( $label ) . '</label>';
	$html .= sprintf(
		'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" maxlength="%5$d" autocomplete="%6$s"%7$s>',
		esc_attr( $type ),
		esc_attr( $id( $field ) ),
		esc_attr( $field ),
		esc_attr( $value( $field ) ),
		$max,
		esc_attr( $autocomplete ),
		$invalid( $field, $help_text )
	);
	$html .= '' !== $help_text ? $help( $field, $help_text ) : '';
	$html .= $error( $field ) . '</div>';
	return $html;
};
?>
<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<p class="dp-suggest__intro">Got something that goes really well with one of the rides? Tell me about it here. It can be a film, a song, something to eat, anything you think belongs with it.</p>

	<?php if ( $notice ) : ?>
		<p class="dp-suggest__notice" role="alert" tabindex="-1"><?php echo esc_html( $notice ); ?></p>
	<?php endif; ?>

	<form class="dp-ticket dp-suggest__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
		<div class="dp-ticket__main">
			<div class="dp-ticket__admit">Admit one · Suggestion</div>
			<input type="hidden" name="action" value="<?php echo esc_attr( Handler::ACTION ); ?>">
			<input type="hidden" name="started_at" value="<?php echo esc_attr( (string) time() ); ?>">
			<?php if ( $saved ) : ?>
				<?php // So the saved values can be deleted as soon as this form is sent again. ?>
				<input type="hidden" name="<?php echo esc_attr( Handler::TOKEN_FIELD ); ?>" value="<?php echo esc_attr( $token ); ?>">
			<?php endif; ?>
			<?php wp_nonce_field( Handler::ACTION, Handler::NONCE_FIELD, false ); ?>

			<fieldset class="dp-suggest__section">
				<legend><span class="dp-suggest__step">1</span> The ride</legend>
				<div class="dp-suggest__field">
					<label class="dp-label" for="<?php echo esc_attr( $id( 'ride_id' ) ); ?>">Which ride is it for?</label>
					<select id="<?php echo esc_attr( $id( 'ride_id' ) ); ?>" name="ride_id"<?php echo $invalid( 'ride_id' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
						<option value="">Choose a ride</option>
						<?php foreach ( $rides as $ride ) : ?>
							<?php $park = Queries::park_for_ride( $ride->ID ); ?>
							<option value="<?php echo esc_attr( (string) $ride->ID ); ?>" <?php selected( $value( 'ride_id' ), (string) $ride->ID ); ?>><?php echo esc_html( get_the_title( $ride ) . ( $park ? ' (' . get_the_title( $park ) . ')' : '' ) ); ?></option>
						<?php endforeach; ?>
						<option value="0" <?php selected( $value( 'ride_id' ), '0' ); ?>>It's not on here yet</option>
					</select>
					<?php echo $error( 'ride_id' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</div>

				<div class="dp-suggest__group" data-dp-new-ride>
					<p class="dp-suggest__help dp-suggest__nojs">Only if you picked "It's not on here yet":</p>
					<?php echo $text_field( 'new_ride_name', 'Ride name', Validator::MAX_PLACE ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<div class="dp-suggest__field">
						<label class="dp-label" for="<?php echo esc_attr( $id( 'park_id' ) ); ?>">Park</label>
						<select id="<?php echo esc_attr( $id( 'park_id' ) ); ?>" name="park_id"<?php echo $invalid( 'park_id' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
							<option value="">Choose a park</option>
							<?php foreach ( $parks as $park ) : ?>
								<option value="<?php echo esc_attr( (string) $park->ID ); ?>" <?php selected( $value( 'park_id' ), (string) $park->ID ); ?>><?php echo esc_html( get_the_title( $park ) ); ?></option>
							<?php endforeach; ?>
							<option value="0" <?php selected( $value( 'park_id' ), '0' ); ?>>It's a new park</option>
						</select>
						<?php echo $error( 'park_id' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</div>
					<div class="dp-suggest__group" data-dp-new-park>
						<p class="dp-suggest__help dp-suggest__nojs">Only if you picked "It's a new park":</p>
						<?php echo $text_field( 'new_park_name', 'Park name', Validator::MAX_PLACE ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<?php echo $text_field( 'new_park_country', 'Country', Validator::MAX_COUNTRY, '', 'text', 'country-name' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</div>
				</div>
			</fieldset>

			<fieldset class="dp-suggest__section">
				<legend><span class="dp-suggest__step">2</span> The pairing</legend>
				<?php echo $text_field( 'title', "What's the pairing?", Validator::MAX_TITLE, 'A film, a song, a snack, somewhere to go. Whatever you think goes with the ride.' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>

				<fieldset class="dp-suggest__moments"<?php echo $invalid( 'moment' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
					<legend class="dp-label">When's it for?</legend>
					<div class="dp-suggest__moment-grid">
						<?php foreach ( Vocab::MOMENTS as $slug => $moment ) : ?>
							<label class="dp-suggest__moment" style="<?php echo esc_attr( '--dp-stub: var(--wp--preset--color--stub-' . $slug . ')' ); ?>">
								<input type="radio" name="moment" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $value( 'moment' ), $slug ); ?>>
								<span class="dp-suggest__moment-name"><?php echo esc_html( $moment['label'] ); ?></span>
								<span class="dp-suggest__moment-desc"><?php echo esc_html( $moment['description'] ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
					<?php echo $error( 'moment' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</fieldset>

				<?php $blurb_help = "A sentence or two is plenty. This is the bit people read on the ride's page."; ?>
				<div class="dp-suggest__field">
					<label class="dp-label" for="<?php echo esc_attr( $id( 'blurb' ) ); ?>">Why does it work with this ride?</label>
					<textarea id="<?php echo esc_attr( $id( 'blurb' ) ); ?>" name="blurb" rows="4" maxlength="<?php echo (int) Validator::MAX_BLURB; ?>"<?php echo $invalid( 'blurb', $blurb_help ); // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php echo esc_textarea( $value( 'blurb' ) ); ?></textarea>
					<?php echo $help( 'blurb', $blurb_help ) . $error( 'blurb' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</div>

				<?php echo $text_field( 'link', 'A link (optional)', Validator::MAX_LINK, "If there's somewhere to find it, like a trailer or the café's website.", 'url', 'url' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</fieldset>

			<fieldset class="dp-suggest__section">
				<legend><span class="dp-suggest__step">3</span> You</legend>
				<?php echo $text_field( 'name', 'Your name', Validator::MAX_NAME, "This goes next to the pairing once it's up.", 'text', 'name' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php
				// The email is never kept while the form's being fixed, so a re-shown form asks again.
				$email_help = "Only I'll see your email, in case I want to ask you something. I delete it once your suggestion is up.";
				if ( $saved ) {
					$email_help .= " I didn't hang on to it while you sorted the form out, so pop it in again if you'd like.";
				}
				echo $text_field( 'email', 'Your email (optional)', 254, $email_help, 'email', 'email' ); // phpcs:ignore WordPress.Security.EscapeOutput
				?>
			</fieldset>

			<?php // Hidden from people (and screen readers); bots that fill every field get a fake thank-you. ?>
			<div class="dp-suggest__trap" aria-hidden="true">
				<label for="<?php echo esc_attr( $id( 'website_url' ) ); ?>">Leave this empty</label>
				<input type="text" id="<?php echo esc_attr( $id( 'website_url' ) ); ?>" name="website_url" value="" tabindex="-1" autocomplete="off">
			</div>

			<p class="dp-suggest__actions"><button type="submit" class="dp-suggest__send">Send suggestion</button></p>
		</div>
		<div class="dp-ticket__stub"><span>Suggestion</span></div>
	</form>
</div>
