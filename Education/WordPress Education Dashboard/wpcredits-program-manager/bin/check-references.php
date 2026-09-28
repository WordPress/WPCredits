<?php
/**
 * Static check: every `self::THING` and `WPCPM_X::THING` reference resolves.
 *
 * `php -l` parses a file; it does not check that a name exists. An undefined class
 * constant is a *runtime* fatal, so it ships happily and then takes the site down on the
 * one request that reaches it. That is exactly what `WPCPM_Mentor_Calls::ANCHOR` did: it
 * was written as `self::ANCHOR` in a method every booking passes through, and it survived
 * from 1.13.1 to 1.17.1 because nothing ever executed that line.
 *
 * Run from the plugin root:  php bin/check-references.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$root = dirname( __DIR__ );

$files = array();
$rii   = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );

foreach ( $rii as $file ) {
	if ( ! $file->isFile() || 'php' !== $file->getExtension() ) {
		continue;
	}
	$path = $file->getPathname();
	// `.superpowers/` is git-ignored scratch (plans, ledgers, copies of scripts from other branches)
	// and a linked worktree may sit under it too; `.worktrees/`, git-ignored as well, holds the linked
	// worktrees of other branches. None of it is this checkout's code, and another branch's files
	// read with this one's are checked against the wrong classes.
	if ( false !== strpos( $path, '/.git/' ) || false !== strpos( $path, '/bin/' ) || false !== strpos( $path, '/.superpowers/' ) || false !== strpos( $path, '/.worktrees/' ) ) {
		continue;
	}
	$files[] = $path;
}

sort( $files );

// What each class actually declares, and the class it extends: a subclass calls the static methods
// it inherits through its own name, so the method runs as the subclass, and such a reference resolves
// in the parent it came from.
$declared = array();

foreach ( $files as $path ) {
	$src = file_get_contents( $path );

	if ( ! preg_match( '/^(?:final |abstract )?class ([A-Za-z_]+)(?:\s+extends\s+([A-Za-z_]+))?/m', $src, $m ) ) {
		continue;
	}

	preg_match_all( '/^\s*const ([A-Z_][A-Z0-9_]*)/m', $src, $consts );
	preg_match_all( '/function ([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $src, $methods );
	preg_match_all( '/(?:public|protected|private)\s+static\s+\$([a-zA-Z_][a-zA-Z0-9_]*)/', $src, $props );

	$declared[ $m[1] ] = array(
		'consts'  => $consts[1],
		'methods' => $methods[1],
		'props'   => $props[1],
		'src'     => $src,
		'file'    => $path,
		'parent'  => isset( $m[2] ) ? $m[2] : '',
	);
}

$problems = 0;
$checked  = 0;

/**
 * Whether a member exists on a class or on a class it extends, as far as the plugin declares them.
 *
 * A parent outside the plugin (core's list table) ends the walk: a member only core declares is
 * not one this can vouch for, and stays a problem.
 *
 * @param array  $class    Declaration record.
 * @param string $name     Member name.
 * @param bool   $isCall   Whether it was called as a method.
 * @param array  $declared Every declaration record, by class, for the walk up.
 * @return bool
 */
function wpcpm_has_member( array $class, $name, $isCall, array $declared ) {
	$seen = array();

	while ( true ) {
		$found = $isCall
			? in_array( $name, $class['methods'], true )
			: in_array( $name, $class['consts'], true ) || in_array( ltrim( $name, '$' ), $class['props'], true );

		if ( $found ) {
			return true;
		}

		$parent = $class['parent'];

		if ( '' === $parent || ! isset( $declared[ $parent ] ) || isset( $seen[ $parent ] ) ) {
			return false;
		}

		$seen[ $parent ] = true;
		$class           = $declared[ $parent ];
	}
}

// 1. self:: / static:: inside each class.
foreach ( $declared as $cls => $class ) {
	preg_match_all( '/(?:self|static)::([A-Za-z_][A-Za-z0-9_]*)\s*(\()?/', $class['src'], $refs, PREG_SET_ORDER );

	foreach ( $refs as $ref ) {
		$isCall = isset( $ref[2] ) && '(' === $ref[2];
		++$checked;

		if ( 'class' === $ref[1] || wpcpm_has_member( $class, $ref[1], $isCall, $declared ) ) {
			continue;
		}

		printf( "UNDEFINED  %s::%s%s\n           %s\n", $cls, $ref[1], $isCall ? '()' : '', $class['file'] );
		++$problems;
	}
}

// 2. WPCPM_X::member anywhere, skipping comment lines so prose references are not flagged.
foreach ( $files as $path ) {
	foreach ( explode( "\n", file_get_contents( $path ) ) as $n => $line ) {
		$trimmed = ltrim( $line );

		if ( '' === $trimmed || 0 === strpos( $trimmed, '*' ) || 0 === strpos( $trimmed, '//' ) || 0 === strpos( $trimmed, '/*' ) ) {
			continue;
		}

		if ( ! preg_match_all( '/(WPCPM_[A-Za-z_]+)::([A-Za-z_$][A-Za-z0-9_]*)\s*(\()?/', $line, $refs, PREG_SET_ORDER ) ) {
			continue;
		}

		foreach ( $refs as $ref ) {
			$cls    = $ref[1];
			$name   = $ref[2];
			$isCall = isset( $ref[3] ) && '(' === $ref[3];

			if ( ! isset( $declared[ $cls ] ) ) {
				printf( "UNKNOWN CLASS  %s\n               %s:%d\n", $cls, $path, $n + 1 );
				++$problems;
				continue;
			}

			++$checked;

			if ( 'class' === $name || wpcpm_has_member( $declared[ $cls ], $name, $isCall, $declared ) ) {
				continue;
			}

			printf( "UNDEFINED  %s::%s%s\n           %s:%d\n", $cls, $name, $isCall ? '()' : '', $path, $n + 1 );
			++$problems;
		}
	}
}

/*
 * Second pass: view-state reads inside form handlers.
 *
 * `WPCPM_Request::key()`, `text()` and `id()` read `$_GET`. A handler wired to
 * `admin_post_*` receives its fields in `$_POST`, so one of those inside a handler does not
 * error - it silently returns the fallback, and the feature keeps working while doing the
 * wrong thing. That is how the sample-invitation control came to send the student template
 * whichever of its two buttons was pressed: no warning, no failure, nothing on screen.
 *
 * A handler is recognised by its own `check_admin_referer()` call, which every one of them
 * makes. `posted_key()` and `posted_id()` are the `$_POST` counterparts and are fine here.
 */
$get_readers = array( 'key', 'text', 'id' );
$handlers    = 0;

foreach ( $files as $path ) {
	$source = file_get_contents( $path );

	// Split into function bodies by brace depth, which is enough to tell whether a read and
	// a nonce check live in the same function without parsing PHP properly.
	if ( ! preg_match_all( '/function\s+(\w+)\s*\([^)]*\)\s*\{/', $source, $starts, PREG_OFFSET_CAPTURE | PREG_SET_ORDER ) ) {
		continue;
	}

	foreach ( $starts as $start ) {
		$name   = $start[1][0];
		$offset = $start[0][1] + strlen( $start[0][0] );
		$depth  = 1;
		$end    = $offset;
		$length = strlen( $source );

		while ( $end < $length && $depth > 0 ) {
			if ( '{' === $source[ $end ] ) {
				++$depth;
			} elseif ( '}' === $source[ $end ] ) {
				--$depth;
			}

			++$end;
		}

		$body = substr( $source, $offset, $end - $offset );

		if ( false === strpos( $body, 'check_admin_referer' ) ) {
			continue;
		}

		++$handlers;

		foreach ( $get_readers as $reader ) {
			if ( preg_match( '/WPCPM_Request::' . $reader . '\s*\(\s*[\'"]([^\'"]+)/', $body, $m ) ) {
				printf(
					"GET READ IN A POST HANDLER  WPCPM_Request::%s( '%s' ) inside %s()\n"
					. "                            %s - use posted_%s() or the field is always the fallback\n",
					$reader,
					$m[1],
					$name,
					$path,
					'id' === $reader ? 'id' : 'key'
				);
				++$problems;
			}
		}
	}
}

printf(
	"\n%d classes, %d references checked, %d form handlers scanned - %s\n",
	count( $declared ),
	$checked,
	$handlers,
	$problems ? $problems . ' PROBLEM(S)' : 'all resolve'
);

exit( $problems ? 1 : 0 );
