<?php
/**
 * US English in everything a person reads: interface strings, the guides and the readme.
 *
 * The WordPress writing style guide (make.wordpress.org/marketing/handbook) asks for US
 * spelling per Merriam-Webster. Only text people read is checked: the arguments of the
 * translation functions, the guide sections and the readme. A status key, an RFC 5545 value
 * ("STATUS:CANCELLED"), a CSV column alias or a page title the site once used are data, not
 * prose, and are left exactly as the outside world spells them. Comments and identifiers are
 * the developer's.
 *
 * Run from the plugin root: php bin/check-spelling.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$root  = dirname( __DIR__ );
$words = array(
	'programme', 'programmes', 'colour', 'colours', 'coloured', 'behaviour', 'behaviours', 'enrolment', 'enrolments',
	'enrol', 'enrols', 'cancelled', 'cancelling', 'afterwards', 'organise', 'organised', 'organising', 'organisation',
	'organisations', 'recognise', 'recognised', 'recognising', 'licence', 'licences', 'centre', 'centres', 'favourite',
	'favourites', 'catalogue', 'labelled', 'labelling', 'whilst', 'amongst', 'analyse', 'analysed', 'analysing',
	'customise', 'customised', 'summarise', 'summarised', 'authorise', 'authorised', 'prioritise', 'optimise',
	'finalise', 'realise', 'realised', 'practise', 'practised', 'learnt', 'honour', 'neighbour', 'judgement', 'fulfil',
	'modelled', 'travelled', 'grey',
);
$i18n  = array( '__', '_e', '_x', '_ex', '_n', '_nx', 'esc_html__', 'esc_html_e', 'esc_html_x', 'esc_attr__', 'esc_attr_e', 'esc_attr_x', '_n_noop', '_nx_noop' );
$re    = '/\b(' . implode( '|', $words ) . ')\b/i';
$hits  = array();
$files = array_merge( glob( $root . '/includes/*.php' ), glob( $root . '/includes/*/*.php' ), array( $root . '/wpcredits-program-manager.php' ) );

foreach ( $files as $file ) {
	$tokens = token_get_all( (string) file_get_contents( $file ) );
	$count  = count( $tokens );

	for ( $i = 0; $i < $count; $i++ ) {
		$token = $tokens[ $i ];

		if ( ! is_array( $token ) || T_STRING !== $token[0] || ! in_array( $token[1], $i18n, true ) ) {
			continue;
		}

		// The call's arguments, to the parenthesis that closes it.
		$j = $i + 1;
		while ( $j < $count && is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
			++$j;
		}
		if ( $j >= $count || '(' !== $tokens[ $j ] ) {
			continue;
		}

		$depth = 0;
		for ( ; $j < $count; $j++ ) {
			$t = $tokens[ $j ];
			if ( '(' === $t ) {
				++$depth;
			} elseif ( ')' === $t ) {
				--$depth;
				if ( 0 === $depth ) {
					break;
				}
			} elseif ( is_array( $t ) && in_array( $t[0], array( T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE ), true ) && preg_match_all( $re, $t[1], $m ) ) {
				foreach ( $m[1] as $word ) {
					$hits[] = sprintf( '%s:%d  %s', substr( $file, strlen( $root ) + 1 ), $t[2], $word );
				}
			}
		}
	}
}

foreach ( array_merge( glob( $root . '/docs/sections/*.md' ), array( $root . '/readme.txt' ) ) as $file ) {
	foreach ( file( $file ) as $n => $line ) {
		// A word in double quotes is being talked about ("Programme" is "Program"), not used.
		$line = preg_replace( '/"[^"]*"/', '""', $line );

		if ( preg_match_all( $re, $line, $m ) ) {
			foreach ( $m[1] as $word ) {
				$hits[] = sprintf( '%s:%d  %s', substr( $file, strlen( $root ) + 1 ), $n + 1, $word );
			}
		}
	}
}

if ( $hits ) {
	echo "British spellings in text people read (the WordPress style guide is US English):\n  " . implode( "\n  ", $hits ) . "\n";
	exit( 1 );
}

printf( "%d PHP files, the guides and the readme: US English throughout.\n", count( $files ) );
exit( 0 );
