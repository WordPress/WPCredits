<?php
/**
 * The shared model call (AI Mentor R3, 1.123.105): the lookup Need help? and the tutor both use, and
 * `converse()`, one call of a conversation with tools.
 *
 * What the blocks pin, and why:
 *
 * - **The lookup moved, unchanged.** The model the setting names, the provider that offers it, the
 *   twelve-hour check, and a model object fetched fresh for each call, never reused: a reused one carries
 *   the last call's tools into the next.
 * - **`converse()` sends what it is given.** The system instruction, the history in order and the
 *   declarations, with no `using_model_preference()`; and returns the text, every call the model made and
 *   the model's own message, kept whole for the next step.
 * - **A bad turn is one error code.** A throw, a WP_Error and a result with nothing in it are each
 *   `wpcpm_ai_call`, so the tutor has one failure to handle.
 * - **The filter answers in place**, before any model is looked up, so a suite needs no provider.
 *
 * Run from the plugin root:  php bin/test-ai-call.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );

$GLOBALS['opts']   = array();
$GLOBALS['tapped'] = array();
$GLOBALS['ttl']    = array();

class WP_Error {
	private $code, $message, $data;
	public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->message = $m; $this->data = $d; }
	public function get_error_message() { return $this->message; }
	public function get_error_code() { return $this->code; }
	public function get_error_data() { return $this->data; }
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function apply_filters( $t, $v, ...$args ) { foreach ( $GLOBALS['tapped'][ $t ] ?? array() as $tap ) { $v = $tap( $v, ...$args ); } return $v; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function get_transient( $k ) { return $GLOBALS['opts'][ 'T_' . $k ] ?? false; }
function set_transient( $k, $v, $e = 0 ) { $GLOBALS['opts'][ 'T_' . $k ] = $v; $GLOBALS['ttl'][ $k ] = $e; return true; }
function delete_transient( $k ) { unset( $GLOBALS['opts'][ 'T_' . $k ], $GLOBALS['ttl'][ $k ] ); return true; }

/** The Settings class, as far as the lookup reads it. */
class WPCPM_Settings {
	public static function get_value( $key, $default = null ) { return $GLOBALS['opts']['settings'][ $key ] ?? $default; }
}

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

require __DIR__ . '/stubs/ai-client.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-ai-call.php';

use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\ModelMessage;
use WordPress\AiClient\Messages\DTO\UserMessage;
use WordPress\AiClient\Results\DTO\Test_Result;
use WordPress\AiClient\Tools\DTO\FunctionCall;
use WordPress\AiClient\Tools\DTO\FunctionDeclaration;
use WordPress\AiClient\Tools\DTO\FunctionResponse;

$fail = 0;
function ck( $label, $actual, $expected ) {
	global $fail;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? "ok   " : "FAIL " ) . $label . "\n";
	if ( ! $ok ) {
		echo "       expected: " . var_export( $expected, true ) . "\n";
		echo "       actual:   " . var_export( $actual, true ) . "\n";
	}
}

/** Providers, each with its connector and a stored key; the check forgotten, no lookup recorded. */
function set_providers( array $providers ) {
	$GLOBALS['ai_providers'] = $providers;
	$GLOBALS['connectors']   = array();

	foreach ( $providers as $id => $provider ) {
		$GLOBALS['connectors'][ $id ] = array(
			'name'           => $id,
			'type'           => 'ai_provider',
			'authentication' => array( 'method' => 'api_key', 'setting_name' => 'connectors_ai_' . $id . '_api_key' ),
		);
		$GLOBALS['opts'][ 'connectors_ai_' . $id . '_api_key' ] = 'test-key-value';
	}

	$GLOBALS['ai_lookups'] = array();
	$GLOBALS['ai_prompts'] = array();
	WPCPM_AI_Call::forget_check();
}

/** A scripted reply of the model: text and calls. */
function model_reply( array $parts ) {
	return new Test_Result( new ModelMessage( $parts ) );
}

$decl = new FunctionDeclaration( 'get_lesson', 'One lesson.', array( 'type' => 'object' ) );
$ask  = new UserMessage( array( new MessagePart( 'Hello' ) ) );

echo "\n=== The lookup, moved ===\n";

ck( 'the model is the default until the setting names one',
    WPCPM_AI_Call::model(), 'gemini-flash-latest' );
$GLOBALS['opts']['settings']['handbook_model'] = ' gemini-pro ';
ck( 'the setting names it, trimmed', WPCPM_AI_Call::model(), 'gemini-pro' );
$GLOBALS['opts']['settings']['handbook_model'] = '';
ck( 'an empty setting is the default', WPCPM_AI_Call::model(), 'gemini-flash-latest' );

set_providers( array( 'google' => array( 'models' => array( 'gemini-flash-latest' ) ) ) );
$first  = WPCPM_AI_Call::model_object();
$second = WPCPM_AI_Call::model_object();
ck( 'a model object comes from the provider that offers the model',
    array( $first->provider, $first->id ), array( 'google', 'gemini-flash-latest' ) );
ck( 'and each call gets a fresh one, never the last call\'s', $first !== $second, true );
ck( 'the lookup is kept for twelve hours',
    array( $GLOBALS['ttl'][ WPCPM_AI_Call::MODEL_CHECK ] ?? null, get_transient( WPCPM_AI_Call::MODEL_CHECK )['provider'] ),
    array( 12 * HOUR_IN_SECONDS, 'google' ) );

set_providers( array( 'google' => array( 'models' => array( 'another-model' ) ) ) );
$none = WPCPM_AI_Call::model_object();
ck( 'a model no provider offers is a wpcpm_handbook_model failure',
    is_wp_error( $none ) ? $none->get_error_code() : null, 'wpcpm_handbook_model' );

set_providers( array() );
$none = WPCPM_AI_Call::model_object();
ck( 'no provider set up is a wpcpm_handbook_provider failure',
    is_wp_error( $none ) ? $none->get_error_code() : null, 'wpcpm_handbook_provider' );

echo "\n=== One turn of a conversation ===\n";

set_providers( array( 'google' => array( 'models' => array( 'gemini-flash-latest' ) ) ) );
$history = array( $ask );
$GLOBALS['ai_script'] = array( model_reply( array( new MessagePart( 'Hi there. ' ) ) ) );
$turn = WPCPM_AI_Call::converse( 'Be a tutor.', $history, array( $decl ), 20 );
$b    = $GLOBALS['ai_prompts'][0];
ck( 'a text reply gives its text, trimmed, no calls, and the model\'s message',
    array( $turn['text'], $turn['calls'], $turn['message'] instanceof ModelMessage ), array( 'Hi there.', array(), true ) );
ck( 'the history is the prompt, as given',
    $b->args( 'prompt' ), array( $history ) );
ck( 'the system instruction and the declarations are sent',
    array( $b->args( 'using_system_instruction' ), $b->args( 'using_function_declarations' ) ), array( array( 'Be a tutor.' ), array( $decl ) ) );
ck( 'the temperature is 0.2 and the most tokens 2,048',
    array( $b->args( 'using_temperature' ), $b->args( 'using_max_tokens' ) ), array( array( 0.2 ), array( 2048 ) ) );
ck( 'the model is an object from the lookup, never a preference',
    array( $b->args( 'using_model' )[0]->provider, $b->args( 'using_model_preference' ) ), array( 'google', null ) );
ck( 'the timeout is the one asked for',
    $b->args( 'using_request_options' )[0]->timeout, 20.0 );

$GLOBALS['ai_script'] = array( model_reply( array(
	new MessagePart( 'Let me look. ' ),
	new MessagePart( new FunctionCall( 'c1', 'get_lesson', array( 'lesson_id' => 4 ) ) ),
	new MessagePart( new FunctionCall( null, 'get_lesson' ) ),
	new MessagePart( 'Thinking', true ),
) ) );
$turn = WPCPM_AI_Call::converse( 'S', array( $ask ), array( $decl ) );
ck( 'two calls in one reply both come back; a missing id is unknown and no args is an empty array',
    $turn['calls'],
    array(
        array( 'id' => 'c1', 'name' => 'get_lesson', 'args' => array( 'lesson_id' => 4 ) ),
        array( 'id' => 'unknown', 'name' => 'get_lesson', 'args' => array() ),
    ) );
ck( 'the text is the content parts joined, the thoughts left out', $turn['text'], 'Let me look.' );
ck( 'the message comes back whole, every part',
    count( $turn['message']->getParts() ), 4 );
$GLOBALS['ai_script'] = array( model_reply( array( new MessagePart( 'Ok' ) ) ) );
WPCPM_AI_Call::converse( 'S', array( $ask ), array() );
ck( 'an empty list of declarations sends none',
    end( $GLOBALS['ai_prompts'] )->args( 'using_function_declarations' ), null );

$sent_back = array( $ask, $turn['message'], new UserMessage( array( new MessagePart( new FunctionResponse( 'c1', 'get_lesson', array( 'ok' => true ) ) ) ) ) );
$GLOBALS['ai_script'] = array( model_reply( array( new MessagePart( 'Done.' ) ) ) );
WPCPM_AI_Call::converse( 'S', $sent_back, array( $decl ) );
ck( 'a later call carries the whole history in order',
    array_map( static function ( $m ) { return $m->getRole(); }, end( $GLOBALS['ai_prompts'] )->args( 'prompt' )[0] ), array( 'user', 'model', 'user' ) );
$models = array();
foreach ( $GLOBALS['ai_prompts'] as $built ) {
	$m = $built->args( 'using_model' );
	if ( null !== $m ) { $models[] = $m[0]; }
}
ck( 'and no two calls shared one model object', count( $models ), count( array_unique( array_map( 'spl_object_id', $models ) ) ) );

echo "\n=== A bad turn ===\n";

$GLOBALS['ai_script'] = array( new RuntimeException( 'boom' ) );
$bad = WPCPM_AI_Call::converse( 'S', array( $ask ), array( $decl ) );
ck( 'a throw is wpcpm_ai_call, with its words', is_wp_error( $bad ) ? array( $bad->get_error_code(), $bad->get_error_message() ) : null, array( 'wpcpm_ai_call', 'boom' ) );

$GLOBALS['ai_script'] = array( new WP_Error( 'prompt_network_error', 'no answer', array( 'status' => 503 ) ) );
$bad = WPCPM_AI_Call::converse( 'S', array( $ask ), array( $decl ) );
ck( 'a WP_Error is wpcpm_ai_call, keeping its words and status',
    is_wp_error( $bad ) ? array( $bad->get_error_code(), $bad->get_error_message(), $bad->get_error_data() ) : null,
    array( 'wpcpm_ai_call', 'no answer', array( 'status' => 503 ) ) );

foreach ( array( 'not a result' => 'text', 'an empty reply' => model_reply( array() ), 'thoughts alone' => model_reply( array( new MessagePart( 'hmm', true ) ) ), 'blank text' => model_reply( array( new MessagePart( '  ' ) ) ) ) as $what => $script ) {
	$GLOBALS['ai_script'] = array( $script );
	$bad = WPCPM_AI_Call::converse( 'S', array( $ask ), array( $decl ) );
	ck( $what . ' is wpcpm_ai_call', is_wp_error( $bad ) ? $bad->get_error_code() : null, 'wpcpm_ai_call' );
}

set_providers( array() );
$bad = WPCPM_AI_Call::converse( 'S', array( $ask ), array( $decl ) );
ck( 'with no provider the lookup\'s own failure comes back, and nothing was sent',
    array( is_wp_error( $bad ) ? $bad->get_error_code() : null, $GLOBALS['ai_prompts'] ), array( 'wpcpm_handbook_provider', array() ) );

echo "\n=== The filter ===\n";

$GLOBALS['tapped']['wpcpm_ai_converse_reply'][] = static function ( $reply, $system = '', $history = array(), $declarations = array() ) {
	$GLOBALS['filter_seen'] = array( $system, $history, $declarations );
	return array( 'text' => 'Stood in.', 'calls' => array(), 'message' => null );
};
$GLOBALS['ai_prompts'] = array();
$turn = WPCPM_AI_Call::converse( 'Sys', array( $ask ), array( $decl ) );
ck( 'the filter answers in place, handed the system, the history and the declarations',
    array( $turn['text'], $GLOBALS['filter_seen'] ), array( 'Stood in.', array( 'Sys', array( $ask ), array( $decl ) ) ) );
ck( 'before any model is looked up: no provider, no prompt', array( $GLOBALS['ai_prompts'], $GLOBALS['ai_lookups'] ), array( array(), array() ) );

$GLOBALS['tapped']['wpcpm_ai_converse_reply'] = array( static function () { return new WP_Error( 'x_filtered', 'Filtered.' ); } );
$turn = WPCPM_AI_Call::converse( 'Sys', array( $ask ), array() );
ck( 'a WP_Error from the filter answers too', is_wp_error( $turn ) ? $turn->get_error_code() : null, 'x_filtered' );

echo "\n" . ( 0 === $fail ? 'ALL PASS' : $fail . ' FAILED' ) . "\n";
exit( 0 === $fail ? 0 : 1 );
