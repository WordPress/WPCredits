<?php
/**
 * The WordPress AI Client, as far as Need help? reaches it, for the suites (the plan's Ruling 15).
 *
 * Core's `wp_supports_ai()`, `wp_ai_client_prompt()` and `wp_get_connector()`, the SDK's provider
 * registry (`WordPress\AiClient\AiClient::defaultRegistry()`), `RequestOptions` and the four exceptions
 * a model lookup or a prompt can raise, in the SDK's own hierarchy: `ClientException` is an
 * `InvalidArgumentException`, as the SDK's is, so code that reads a missing model from the plain
 * `InvalidArgumentException` must look for a refused request first. Nothing here touches the network.
 *
 * A suite sets the state it needs through globals:
 *
 * - `$GLOBALS['ai_supported']`: what `wp_supports_ai()` answers (true when unset).
 * - `$GLOBALS['ai_providers']`: provider ID => `models` (the IDs it offers) and `fails` (an exception
 *   every request to it throws, as a provider that cannot be reached or refuses its key does), in the
 *   registry's order. Both calls that ask the provider for its models, `isProviderConfigured()` (the
 *   SDK's availability check lists the models) and `getProviderModel()`, are requests, and each is
 *   recorded in `$GLOBALS['ai_lookups']`: `array( 'isProviderConfigured', $id )` or
 *   `array( 'getProviderModel', $id, $model )`. `hasProvider()` and `getRegisteredProviderIds()` are
 *   not, and record nothing.
 * - `$GLOBALS['ai_script']`: what each `generate_text()` returns, in order: a string or a `WP_Error`, or
 *   a Throwable it throws. `$GLOBALS['ai_throw_on']`, a method's name, makes that method throw an
 *   `Error` before anything is sent. `$GLOBALS['ai_prompts']` records each prompt builder and every
 *   method called on it.
 * - `$GLOBALS['connectors']`: connector ID => what `wp_get_connector()` returns, and `wp_get_connectors()`
 *   all of them. Whether a provider has a key is the suite's options, read where its connector says.
 *
 * Loaded with `require __DIR__ . '/stubs/ai-client.php';` from a suite's header, after its WP_Error.
 */

namespace WordPress\AiClient\Common\Exception {

	/** The SDK's own invalid argument, which a model the provider does not offer raises. */
	class InvalidArgumentException extends \InvalidArgumentException {}

	/** The SDK's own runtime failure. */
	class RuntimeException extends \RuntimeException {}
}

namespace WordPress\AiClient\Providers\Http\Exception {

	/** A 4xx from the provider; its code is the HTTP status. */
	class ClientException extends \WordPress\AiClient\Common\Exception\InvalidArgumentException {}

	/** No answer at all: a timeout, a refused connection. */
	class NetworkException extends \WordPress\AiClient\Common\Exception\RuntimeException {}

	/** A 5xx from the provider; its code is the HTTP status. */
	class ServerException extends \WordPress\AiClient\Common\Exception\RuntimeException {}
}

namespace WordPress\AiClient\Providers\Http\DTO {

	/** The request options a prompt is sent with. */
	class RequestOptions {

		const KEY_TIMEOUT = 'timeout';

		/** @var float|null Seconds. */
		public $timeout = null;

		public static function fromArray( array $array ) {
			$options = new self();

			if ( isset( $array[ self::KEY_TIMEOUT ] ) ) {
				$options->timeout = (float) $array[ self::KEY_TIMEOUT ];
			}

			return $options;
		}
	}
}

namespace WordPress\AiClient {

	use WordPress\AiClient\Common\Exception\InvalidArgumentException;

	/** A model a provider handed out: which provider, which model. */
	class Test_Model {

		public $provider = '';
		public $id       = '';

		public function __construct( $provider, $id ) {
			$this->provider = $provider;
			$this->id       = $id;
		}
	}

	/** The registry, answering from `$GLOBALS['ai_providers']`. */
	class Test_Registry {

		public function getRegisteredProviderIds() {
			return array_keys( isset( $GLOBALS['ai_providers'] ) ? $GLOBALS['ai_providers'] : array() );
		}

		public function hasProvider( $id ) {
			return isset( $GLOBALS['ai_providers'][ $id ] );
		}

		/** As the SDK's availability check: a request for the models, false whatever made it fail. */
		public function isProviderConfigured( $id ) {
			$GLOBALS['ai_lookups'][] = array( 'isProviderConfigured', $id );

			return isset( $GLOBALS['ai_providers'][ $id ] ) && ! isset( $GLOBALS['ai_providers'][ $id ]['fails'] );
		}

		public function getProviderModel( $id, $model_id, $config = null ) {
			$GLOBALS['ai_lookups'][] = array( 'getProviderModel', $id, $model_id );

			if ( ! isset( $GLOBALS['ai_providers'][ $id ] ) ) {
				throw new InvalidArgumentException( sprintf( 'Provider not registered: %s', $id ) );
			}

			$provider = $GLOBALS['ai_providers'][ $id ];

			if ( isset( $provider['fails'] ) ) {
				throw $provider['fails'];
			}

			if ( ! in_array( $model_id, isset( $provider['models'] ) ? $provider['models'] : array(), true ) ) {
				throw new InvalidArgumentException( sprintf( 'No model with ID %s was found in the provider', $model_id ) );
			}

			return new Test_Model( $id, $model_id );
		}
	}

	/** The entry point core calls. */
	final class AiClient {

		/** @var Test_Registry|null */
		private static $registry = null;

		public static function defaultRegistry() {
			if ( null === self::$registry ) {
				self::$registry = new Test_Registry();
			}

			return self::$registry;
		}
	}
}

namespace {

	/** Core's prompt builder: records every call, and answers `generate_text()` from the script. */
	class WP_AI_Client_Prompt_Builder {

		/** @var array List of array( method, arguments ), the prompt first. */
		public $calls = array();

		public function __construct( $prompt = null ) {
			$this->calls[]           = array( 'prompt', array( $prompt ) );
			$GLOBALS['ai_prompts'][] = $this;
		}

		public function __call( $name, $arguments ) {
			$this->calls[] = array( $name, $arguments );

			if ( isset( $GLOBALS['ai_throw_on'] ) && $name === $GLOBALS['ai_throw_on'] ) {
				throw new Error( sprintf( '%s threw', $name ) );
			}

			if ( 'generate_text' !== $name ) {
				return $this;
			}

			if ( empty( $GLOBALS['ai_script'] ) ) {
				return new WP_Error( 'prompt_builder_error', 'nothing is scripted', array( 'status' => 500 ) );
			}

			$next = array_shift( $GLOBALS['ai_script'] );

			if ( $next instanceof Throwable ) {
				throw $next;
			}

			return $next;
		}

		/**
		 * The arguments of the first call to a method, or null when it was never called.
		 *
		 * @param string $name Method.
		 * @return array|null
		 */
		public function args( $name ) {
			foreach ( $this->calls as $call ) {
				if ( $name === $call[0] ) {
					return $call[1];
				}
			}

			return null;
		}
	}

	function wp_supports_ai() {
		return ! isset( $GLOBALS['ai_supported'] ) || (bool) $GLOBALS['ai_supported'];
	}

	function wp_ai_client_prompt( $prompt = null ) {
		return new WP_AI_Client_Prompt_Builder( $prompt );
	}

	function wp_get_connector( $id ) {
		return isset( $GLOBALS['connectors'][ $id ] ) ? $GLOBALS['connectors'][ $id ] : null;
	}

	function wp_get_connectors() {
		return isset( $GLOBALS['connectors'] ) ? $GLOBALS['connectors'] : array();
	}
}
