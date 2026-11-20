<?php
/**
 * The one call to the WordPress AI Client that Need help? and the tutor share.
 *
 * Which provider and model a call goes to (the **Model** setting, the providers set up in Settings >
 * Connectors, the twelve-hour model check), and `converse()`, a turn of a conversation with tools: the
 * system instruction, the history so far and the functions the model may call, in; its text, the
 * calls it made and its message, out. The loop that answers the calls is the caller's.
 *
 * Always a model object fetched fresh for each call, handed to `using_model()`: a model object kept
 * between calls carries the last call's tools and system instruction into the next. `using_model_preference()`
 * is never used.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The model lookup and the conversation call.
 */
class WPCPM_AI_Call {

	/** Transient keeping, for twelve hours, which provider set up offers the model, keyed by its name. */
	const MODEL_CHECK = 'wpcpm_handbook_model_check';

	/**
	 * How long a model lookup the provider refused or could not answer, busy, unreachable or failing with
	 * no status, is kept, in seconds:
	 * a quarter of an hour, so page views do not each wait on the request, and a passing outage or a key
	 * put right at the provider does not hold answers back for twelve hours.
	 */
	const MODEL_RETRY = 900;

	/** The model when the setting names none. */
	const DEFAULT_MODEL = 'gemini-flash-latest';

	/** What a lookup of the model found, as the model check keeps it and `provider_state()` says it. */
	const STATES = array( 'ready', 'not_offered', 'refused', 'unreachable' );

	/** The most tokens a conversation reply may take. */
	const MAX_TOKENS = 2048;

	/*
	 * The provider and the model
	 * --------------------------------------------------------------------
	 */

	/**
	 * The model the setting names.
	 *
	 * @return string
	 */
	public static function model(): string {
		$model = trim( (string) WPCPM_Settings::get_value( 'handbook_model', self::DEFAULT_MODEL ) );

		return '' !== $model ? $model : self::DEFAULT_MODEL;
	}

	/**
	 * Why no model can be called on this WordPress at all, or null when one can.
	 *
	 * @return WP_Error|null
	 */
	private static function client_missing() {
		if ( ! function_exists( 'wp_supports_ai' ) || ! function_exists( 'wp_ai_client_prompt' ) || ! function_exists( 'wp_get_connector' ) || ! class_exists( 'WordPress\AiClient\AiClient' ) || ! class_exists( 'WordPress\AiClient\Providers\Http\DTO\RequestOptions' ) ) {
			return new WP_Error( 'wpcpm_handbook_provider', __( 'this WordPress has no AI Client', 'wpcredits-program-manager' ) );
		}

		if ( ! wp_supports_ai() ) {
			return new WP_Error( 'wpcpm_handbook_provider', __( 'AI support is switched off on this site', 'wpcredits-program-manager' ) );
		}

		return null;
	}

	/**
	 * The providers set up in Connectors, in the registry's order: registered with the AI Client, and
	 * with an API key in the connector's environment variable, its constant or its option, the three
	 * places core reads a key from. Makes no request, and keeps nothing of the key but whether there is
	 * one.
	 *
	 * @return string[] Provider IDs.
	 */
	public static function set_up() {
		if ( null !== self::client_missing() ) {
			return array();
		}

		$out = array();

		try {
			$registry = \WordPress\AiClient\AiClient::defaultRegistry();

			foreach ( (array) $registry->getRegisteredProviderIds() as $id ) {
				$id = (string) $id;

				if ( '' !== $id && $registry->hasProvider( $id ) && self::has_key( $id ) ) {
					$out[] = $id;
				}
			}
		} catch ( \Throwable $e ) {
			return $out;
		}

		return $out;
	}

	/**
	 * Whether a provider's connector has an API key: in its environment variable, its constant or its
	 * option, as `_wp_connectors_get_api_key_source()` reads them.
	 *
	 * @param string $id The provider's ID, which is its connector's.
	 * @return bool
	 */
	private static function has_key( $id ) {
		$connector = wp_get_connector( $id );
		$option    = self::key_option( $connector );

		if ( '' === $option ) {
			return false;
		}

		$auth = $connector['authentication'];

		if ( ! empty( $auth['env_var_name'] ) ) {
			$env = getenv( (string) $auth['env_var_name'] );

			if ( false !== $env && '' !== $env ) {
				return true;
			}
		}

		if ( ! empty( $auth['constant_name'] ) && defined( (string) $auth['constant_name'] ) ) {
			$constant = constant( (string) $auth['constant_name'] );

			if ( is_string( $constant ) && '' !== $constant ) {
				return true;
			}
		}

		$stored = get_option( $option, '' );

		return is_string( $stored ) && '' !== $stored;
	}

	/**
	 * The option an AI provider's connector keeps its API key in, or '' for another kind of connector.
	 *
	 * @param mixed $connector A connector, as Connectors gives it.
	 * @return string
	 */
	public static function key_option( $connector ) {
		if ( ! is_array( $connector ) || ! isset( $connector['type'], $connector['authentication'] ) || 'ai_provider' !== $connector['type'] || ! is_array( $connector['authentication'] ) ) {
			return '';
		}

		$auth = $connector['authentication'];

		if ( ! isset( $auth['method'] ) || 'api_key' !== $auth['method'] || empty( $auth['setting_name'] ) ) {
			return '';
		}

		return (string) $auth['setting_name'];
	}

	/**
	 * The provider a question goes to: the one the model check names, or, with no check for the model,
	 * the first set up that offers it, by name.
	 *
	 * A lookup's outcome is kept in MODEL_CHECK: that a provider offers the model, or that none does, for
	 * twelve hours; that the provider refused the request, was busy, unreachable or failed with no status,
	 * for MODEL_RETRY.
	 * A key changed in Connectors forgets it (`watch_keys()`), so does a save of the Need help? settings
	 * (`WPCPM_Settings::save()`), and so does a check naming a provider no longer set up.
	 *
	 * @return string|WP_Error The provider's ID.
	 */
	public static function provider() {
		$missing = self::client_missing();

		if ( null !== $missing ) {
			return $missing;
		}

		$set_up = self::set_up();

		if ( array() === $set_up ) {
			return new WP_Error( 'wpcpm_handbook_provider', __( 'no AI provider is set up in Settings > Connectors', 'wpcredits-program-manager' ) );
		}

		$model = self::model();
		$check = self::check();

		if ( null !== $check ) {
			if ( 'not_offered' === $check['state'] ) {
				return self::not_offered( $model );
			}

			if ( 'ready' !== $check['state'] ) {
				return self::lookup_failed( $check['status'], '' );
			}

			if ( in_array( $check['provider'], $set_up, true ) ) {
				return $check['provider'];
			}
		}

		return self::look_up( $set_up, $model );
	}

	/**
	 * Ask the providers set up, in order, for the model, and keep the outcome.
	 *
	 * @param string[] $set_up The providers set up.
	 * @param string   $model  The model.
	 * @return string|WP_Error The first provider that offers it.
	 */
	private static function look_up( array $set_up, $model ) {
		$registry = \WordPress\AiClient\AiClient::defaultRegistry();
		$unsure   = null;

		foreach ( $set_up as $id ) {
			try {
				$registry->getProviderModel( $id, $model );
			} catch ( \Throwable $e ) {
				$error = self::lookup_error( $e, $model );

				if ( 'wpcpm_handbook_model' !== $error->get_error_code() && null === $unsure ) {
					$unsure = $error;
				}

				continue;
			}

			self::keep_check( $model, $id, 'ready', 0 );

			return $id;
		}

		if ( null !== $unsure ) {
			self::keep_check( $model, '', self::state_of( $unsure ), self::status_of( $unsure ) );

			return $unsure;
		}

		self::keep_check( $model, '', 'not_offered', 0 );

		return self::not_offered( $model );
	}

	/**
	 * The model, from the provider that offers it, for one call. When the provider the check named no
	 * longer gives it (a key revoked since, the model withdrawn), every provider set up is asked again
	 * and that outcome kept, so the next question reads it from the check.
	 *
	 * @return \WordPress\AiClient\Providers\Models\Contracts\ModelInterface|WP_Error
	 */
	public static function model_object() {
		$provider = self::provider();

		if ( is_wp_error( $provider ) ) {
			return $provider;
		}

		$model    = self::model();
		$registry = \WordPress\AiClient\AiClient::defaultRegistry();

		try {
			return $registry->getProviderModel( $provider, $model );
		} catch ( \Throwable $e ) {
			self::forget_check();
		}

		$provider = self::look_up( self::set_up(), $model );

		if ( is_wp_error( $provider ) ) {
			return $provider;
		}

		try {
			return $registry->getProviderModel( $provider, $model );
		} catch ( \Throwable $e ) {
			return self::lookup_error( $e, $model );
		}
	}

	/**
	 * The model check for the model the setting names, or null when there is none, or it is not one this
	 * version wrote.
	 *
	 * @return array{provider: string, state: string, status: int}|null The provider that offers the model
	 *                                                                  ('' for none), what the lookup
	 *                                                                  found (one of STATES), and the
	 *                                                                  HTTP status of a lookup that
	 *                                                                  failed (0 for none).
	 */
	public static function check() {
		$check = get_transient( self::MODEL_CHECK );

		if ( ! is_array( $check ) || ! isset( $check['model'], $check['provider'], $check['state'] ) || self::model() !== $check['model'] || ! in_array( $check['state'], self::STATES, true ) ) {
			return null;
		}

		return array(
			'provider' => (string) $check['provider'],
			'state'    => (string) $check['state'],
			'status'   => isset( $check['status'] ) ? (int) $check['status'] : 0,
		);
	}

	/**
	 * Keep a lookup's outcome: for twelve hours when it says something of the model, the provider
	 * offering it or none offering it; for MODEL_RETRY when the provider refused the request, was busy,
	 * could not be reached or failed with no status. A refusal is about the key, which can be put right
	 * at the provider with nothing changing on this site, so it is tried again as soon as an outage is.
	 *
	 * @param string $model    The model.
	 * @param string $provider The provider that offers it, or '' for none.
	 * @param string $state    What the lookup found: one of STATES.
	 * @param int    $status   The HTTP status of a lookup that failed, or 0.
	 */
	private static function keep_check( $model, $provider, $state, $status ) {
		set_transient(
			self::MODEL_CHECK,
			array(
				'model'    => $model,
				'provider' => $provider,
				'state'    => $state,
				'status'   => $status,
			),
			in_array( $state, array( 'refused', 'unreachable' ), true ) ? self::MODEL_RETRY : 12 * HOUR_IN_SECONDS
		);
	}

	/**
	 * What a failed lookup says: `not_offered`, `refused` (400, 401, 403), or `unreachable` (busy, a
	 * network failure, any other status or none).
	 *
	 * @param WP_Error $error The failure.
	 * @return string
	 */
	public static function state_of( WP_Error $error ) {
		if ( 'wpcpm_handbook_model' === $error->get_error_code() ) {
			return 'not_offered';
		}

		return in_array( self::status_of( $error ), array( 400, 401, 403 ), true ) ? 'refused' : 'unreachable';
	}

	/**
	 * A failure's HTTP status, or 0.
	 *
	 * @param WP_Error $error The failure.
	 * @return int
	 */
	private static function status_of( WP_Error $error ) {
		$data = $error->get_error_data();

		return is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 0;
	}

	/**
	 * A failed model lookup as a failure: a model the provider does not offer, or the lookup's own
	 * failure with the status the AI Client gives the same exception, a refused request, an unreachable
	 * or failing service.
	 *
	 * The SDK's refused request is a kind of its invalid argument, so it is told apart first.
	 *
	 * @param \Throwable $e     What the lookup threw.
	 * @param string     $model The model looked up.
	 * @return WP_Error
	 */
	private static function lookup_error( \Throwable $e, $model ) {
		if ( $e instanceof \WordPress\AiClient\Providers\Http\Exception\ClientException ) {
			return self::lookup_failed( $e->getCode() > 0 ? (int) $e->getCode() : 400, $e->getMessage() );
		}

		if ( $e instanceof \WordPress\AiClient\Common\Exception\InvalidArgumentException ) {
			return self::not_offered( $model );
		}

		if ( $e instanceof \WordPress\AiClient\Providers\Http\Exception\NetworkException ) {
			return self::lookup_failed( 503, $e->getMessage() );
		}

		if ( $e instanceof \WordPress\AiClient\Providers\Http\Exception\ServerException ) {
			return self::lookup_failed( $e->getCode() > 0 ? (int) $e->getCode() : 500, $e->getMessage() );
		}

		return self::lookup_failed( 0, $e->getMessage() );
	}

	/**
	 * The failure of a model lookup the provider could not answer. Nothing was sent to the model.
	 *
	 * @param int    $status  The HTTP status, or 0.
	 * @param string $message The provider's words, or '' when read from the model check.
	 * @return WP_Error
	 */
	private static function lookup_failed( $status, $message ) {
		return new WP_Error(
			'wpcpm_handbook_lookup',
			'' !== $message ? $message : __( 'the AI provider did not answer a request for its models', 'wpcredits-program-manager' ),
			$status > 0 ? array( 'status' => $status ) : null
		);
	}

	/**
	 * The failure of a model no provider set up offers.
	 *
	 * @param string $model The model.
	 * @return WP_Error
	 */
	private static function not_offered( $model ) {
		return new WP_Error(
			'wpcpm_handbook_model',
			sprintf(
				/* translators: %s: the AI model's name. */
				__( 'no AI provider set up in Settings > Connectors offers the model %s', 'wpcredits-program-manager' ),
				$model
			)
		);
	}

	/**
	 * Forget the model check.
	 */
	public static function forget_check() {
		delete_transient( self::MODEL_CHECK );
	}

	/*
	 * A turn of a conversation
	 * --------------------------------------------------------------------
	 */

	/**
	 * One call of a conversation with tools.
	 *
	 * @param string $system       The system instruction.
	 * @param array  $history      The library's message objects, in order: it begins and ends with a user message.
	 * @param array  $declarations The library's `FunctionDeclaration` objects the model may call.
	 * @param int    $timeout      How long to wait, in seconds.
	 * @return array|WP_Error `text` (the reply's text, '' when it is calls alone), `calls` (each `id`,
	 *                        `name`, `args`) and `message` (the model's message, kept whole for the next call).
	 */
	public static function converse( string $system, array $history, array $declarations, int $timeout = 30 ) {
		/**
		 * Filters a conversation call before it is made: an array of the shape `converse()` returns or a
		 * WP_Error answers in the model's place, and null lets the model answer.
		 *
		 * @param array|WP_Error|null $reply        Null.
		 * @param string              $system       The system instruction.
		 * @param array               $history      The message objects.
		 * @param array               $declarations The function declarations.
		 */
		$reply = apply_filters( 'wpcpm_ai_converse_reply', null, $system, $history, $declarations );

		if ( is_array( $reply ) || is_wp_error( $reply ) ) {
			return $reply;
		}

		$model = self::model_object();

		if ( is_wp_error( $model ) ) {
			return $model;
		}

		try {
			$builder = wp_ai_client_prompt( $history )
				->using_system_instruction( $system )
				->using_model( $model )
				->using_temperature( 0.2 )
				->using_max_tokens( self::MAX_TOKENS );

			if ( array() !== $declarations ) {
				$builder = $builder->using_function_declarations( ...array_values( $declarations ) );
			}

			$builder = $builder->using_request_options(
				\WordPress\AiClient\Providers\Http\DTO\RequestOptions::fromArray(
					array( \WordPress\AiClient\Providers\Http\DTO\RequestOptions::KEY_TIMEOUT => (float) $timeout )
				)
			);

			$result = $builder->generate_text_result();

			if ( is_wp_error( $result ) ) {
				return new WP_Error( 'wpcpm_ai_call', $result->get_error_message(), $result->get_error_data() );
			}

			return self::read( $result );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'wpcpm_ai_call', $e->getMessage() );
		}
	}

	/**
	 * A result as `converse()` returns it.
	 *
	 * @param mixed $result What `generate_text_result()` gave.
	 * @return array|WP_Error
	 */
	private static function read( $result ) {
		if ( ! is_object( $result ) || ! method_exists( $result, 'toMessage' ) ) {
			return self::malformed();
		}

		$message = $result->toMessage();

		if ( ! is_object( $message ) || ! method_exists( $message, 'getParts' ) ) {
			return self::malformed();
		}

		$text  = '';
		$calls = array();

		foreach ( $message->getParts() as $part ) {
			if ( $part->getType()->isFunctionCall() ) {
				$call    = $part->getFunctionCall();
				$id      = $call->getId();
				$args    = $call->getArgs();
				$calls[] = array(
					'id'   => null === $id || '' === (string) $id ? 'unknown' : (string) $id,
					'name' => (string) $call->getName(),
					'args' => is_array( $args ) ? $args : array(),
				);
			} elseif ( $part->getChannel()->isContent() && null !== $part->getText() ) {
				$text .= $part->getText();
			}
		}

		$text = trim( $text );

		if ( '' === $text && array() === $calls ) {
			return self::malformed();
		}

		return array(
			'text'    => $text,
			'calls'   => $calls,
			'message' => $message,
		);
	}

	/**
	 * The failure of a reply that is no result, or says nothing.
	 *
	 * @return WP_Error
	 */
	private static function malformed() {
		return new WP_Error( 'wpcpm_ai_call', __( 'the AI service gave no usable reply', 'wpcredits-program-manager' ) );
	}
}
