<?php
/**
 * The one ranking function of the guide index: BM25 over the passages the asker may read.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Which passages of the guides, the FAQ and the handbook a question is given.
 *
 * `rank()` is the one function the build plan asks for. It scores each passage the asker may read with
 * BM25 (k1 1.2, b 0.75, idf `ln(1 + (N - df + 0.5) / (df + 0.5))`, where N, df and the average length
 * are the whole index's, so a passage scores the same whoever asks), multiplies by its source's weight
 * and, in the asker's own guide, by HOME, keeps one passage of each text, and returns the best.
 * Embeddings may replace its body later; its callers do not change.
 */
final class WPCPM_Guide_Search {

	/** BM25's term-frequency saturation. */
	const K1 = 1.2;

	/** BM25's length normalization. */
	const B = 0.75;

	/** What the asker's own guide's passages are multiplied by. */
	const HOME = 1.25;

	/**
	 * A source kind's weight: the site's guides and its FAQ 1.0, the handbook 0.8, so where both answer
	 * a question the site's own, newer page comes first.
	 *
	 * @param string $kind `guide`, `faq` or `handbook`.
	 * @return float
	 */
	public static function weight( string $kind ): float {
		$weight = 'handbook' === $kind ? 0.8 : 1.0;

		/**
		 * Filters the weight of a kind of source in the guide index's ranking.
		 *
		 * @param float  $weight 1.0 for `guide` and `faq`, 0.8 for `handbook`.
		 * @param string $kind   The kind.
		 */
		return (float) apply_filters( 'wpcpm_guide_weight', $weight, $kind );
	}

	/**
	 * The passages that best answer some words, among those the audiences may read.
	 *
	 * @param string[] $words     Texts to search by: the model's search words, the question, or both.
	 *                            Their terms count once each, however often they appear.
	 * @param string[] $audiences The audiences the asker reads, such as `public` and `student`.
	 * @param string   $home      The asker's own guide's audience, whose passages get HOME; '' for none.
	 * @param int      $limit     The most passages returned.
	 * @param string[] $exclude   Source keys whose passages are left out, such as `page:2746`: the public
	 *                            pages the asker may not be given now (`WPCPM_Guide_Sources::excluded_for()`).
	 *                            N, df and the average length stay the whole index's.
	 * @return array<int, array{id: int, source: string, kind: string, audience: string, title: string, heading: string, url: string, body: string, score: float}>
	 *               Best first, ties by the lower id; one passage of each text (hash), the highest.
	 */
	public static function rank( array $words, array $audiences, string $home = '', int $limit = 5, array $exclude = array() ): array {
		$terms = array();

		foreach ( $words as $text ) {
			foreach ( WPCPM_Guide_Text::terms( (string) $text ) as $term ) {
				$terms[] = $term;
			}
		}

		$terms     = WPCPM_Guide_Index::cut_terms( $terms );
		$audiences = WPCPM_Guide_Index::audiences( $audiences );

		if ( array() === $terms || array() === $audiences || $limit < 1 ) {
			return array();
		}

		$stats = WPCPM_Guide_Index::stats();
		$n     = $stats['count'];
		$avg   = $stats['avg'] > 0 ? $stats['avg'] : 1.0;

		if ( $n < 1 ) {
			return array();
		}

		$idf = array();

		foreach ( WPCPM_Guide_Index::df( $terms ) as $term => $df ) {
			$idf[ $term ] = log( 1 + ( $n - $df + 0.5 ) / ( $df + 0.5 ) );
		}

		// BM25 per passage, summed over the query's terms it holds.
		$scores = array();
		$about  = array();

		foreach ( WPCPM_Guide_Index::postings( $terms, $audiences, $exclude ) as $row ) {
			$id   = $row['chunk_id'];
			$tf   = $row['tf'];
			$norm = self::K1 * ( 1 - self::B + self::B * $row['words'] / $avg );
			$part = ( isset( $idf[ $row['term'] ] ) ? $idf[ $row['term'] ] : 0.0 ) * ( $tf * ( self::K1 + 1 ) ) / ( $tf + $norm );

			$scores[ $id ] = ( isset( $scores[ $id ] ) ? $scores[ $id ] : 0.0 ) + $part;
			$about[ $id ]  = $row;
		}

		// The source's weight and the home guide's, then one passage of each text.
		$weights = array();
		$best    = array();

		foreach ( $scores as $id => $score ) {
			$kind = $about[ $id ]['kind'];

			if ( ! isset( $weights[ $kind ] ) ) {
				$weights[ $kind ] = self::weight( $kind );
			}

			$score *= $weights[ $kind ];

			if ( '' !== $home && $home === $about[ $id ]['audience'] ) {
				$score *= self::HOME;
			}

			$hash = $about[ $id ]['hash'];

			if ( ! isset( $best[ $hash ] ) || self::before( $score, $id, $best[ $hash ][0], $best[ $hash ][1] ) ) {
				$best[ $hash ] = array( $score, $id );
			}
		}

		$best = array_values( $best );

		usort(
			$best,
			function ( $a, $b ) {
				return self::before( $a[0], $a[1], $b[0], $b[1] ) ? -1 : 1;
			}
		);

		$best   = array_slice( $best, 0, $limit );
		$rows   = WPCPM_Guide_Index::rows( array_column( $best, 1 ) );
		$scored = array_column( $best, 0, 1 );
		$out    = array();

		foreach ( $rows as $row ) {
			$out[] = array(
				'id'       => (int) $row['id'],
				'source'   => (string) $row['source'],
				'kind'     => (string) $row['kind'],
				'audience' => (string) $row['audience'],
				'title'    => (string) $row['title'],
				'heading'  => (string) $row['heading'],
				'url'      => (string) $row['url'],
				'body'     => (string) $row['body'],
				'score'    => round( (float) $scored[ (int) $row['id'] ], 4 ),
			);
		}

		return $out;
	}

	/**
	 * Whether one scored passage ranks before another: the higher score, or at the same score the
	 * lower id.
	 *
	 * @param float $score_a The first's score.
	 * @param int   $id_a    The first's id.
	 * @param float $score_b The second's score.
	 * @param int   $id_b    The second's id.
	 * @return bool
	 */
	private static function before( float $score_a, int $id_a, float $score_b, int $id_b ): bool {
		return $score_a > $score_b || ( $score_a === $score_b && $id_a < $id_b );
	}
}
