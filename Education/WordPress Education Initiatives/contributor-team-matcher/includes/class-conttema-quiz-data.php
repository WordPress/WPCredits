<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CONTTEMA_Quiz_Data {

	/**
	 * Returns questions — from saved options if available, otherwise built-in defaults.
	 */
	public static function get_questions() {
		$stored = get_option( 'conttema_questions' );
		if ( is_array( $stored ) && ! empty( $stored ) ) {
			return $stored;
		}
		return self::get_default_questions();
	}

	/**
	 * Returns teams — from saved options if available, otherwise built-in defaults.
	 */
	public static function get_teams() {
		$stored = get_option( 'conttema_teams' );
		if ( is_array( $stored ) && ! empty( $stored ) ) {
			return $stored;
		}
		return self::get_default_teams();
	}

	/**
	 * Built-in default questions (used when no overrides are saved).
	 */
	public static function get_default_questions() {
		return array(
			array(
				'id'      => 'q_activity',
				'type'    => 'checkbox',
				'text'    => 'Which of these activities sounds most exciting to you?',
				'answers' => array(
					array(
						'id'   => 'a_write_code',
						'text' => 'Writing or reviewing code',
						'tags' => array( 'code', 'php', 'javascript', 'dev' ),
					),
					array(
						'id'   => 'a_design',
						'text' => 'Designing interfaces and user experiences',
						'tags' => array( 'design', 'ux', 'ui' ),
					),
					array(
						'id'   => 'a_write_words',
						'text' => 'Writing documentation, tutorials, or guides',
						'tags' => array( 'writing', 'docs', 'education' ),
					),
					array(
						'id'   => 'a_help_people',
						'text' => 'Helping people solve problems',
						'tags' => array( 'support', 'community', 'education' ),
					),
					array(
						'id'   => 'a_translate',
						'text' => 'Making software available in other languages',
						'tags' => array( 'translation', 'i18n', 'community' ),
					),
					array(
						'id'   => 'a_media',
						'text' => 'Creating or curating multimedia content',
						'tags' => array( 'media', 'video', 'photos' ),
					),
					array(
						'id'   => 'a_organise',
						'text' => 'Organising events and growing communities',
						'tags' => array( 'community', 'events', 'outreach' ),
					),
				),
			),
			array(
				'id'      => 'q_skills',
				'type'    => 'checkbox',
				'text'    => 'Which of these best describes your strongest skill?',
				'answers' => array(
					array(
						'id'   => 's_php',
						'text' => 'PHP / backend development',
						'tags' => array( 'php', 'code', 'dev', 'backend' ),
					),
					array(
						'id'   => 's_js',
						'text' => 'JavaScript / frontend development',
						'tags' => array( 'javascript', 'code', 'dev', 'frontend' ),
					),
					array(
						'id'   => 's_mobile',
						'text' => 'Mobile development (iOS / Android)',
						'tags' => array( 'mobile', 'swift', 'kotlin', 'dev' ),
					),
					array(
						'id'   => 's_devops',
						'text' => 'DevOps / infrastructure / server management',
						'tags' => array( 'devops', 'hosting', 'infrastructure' ),
					),
					array(
						'id'   => 's_design',
						'text' => 'Graphic design or UI/UX',
						'tags' => array( 'design', 'ux', 'ui' ),
					),
					array(
						'id'   => 's_writing',
						'text' => 'Writing or content creation',
						'tags' => array( 'writing', 'docs', 'education' ),
					),
					array(
						'id'   => 's_languages',
						'text' => 'Speaking or writing in multiple languages',
						'tags' => array( 'translation', 'i18n' ),
					),
					array(
						'id'   => 's_testing',
						'text' => 'QA / testing / bug reporting',
						'tags' => array( 'testing', 'qa', 'bugs' ),
					),
					array(
						'id'   => 's_community',
						'text' => 'Event organisation / community management',
						'tags' => array( 'community', 'events', 'outreach' ),
					),
					array(
						'id'   => 's_a11y',
						'text' => 'Accessibility or inclusive design',
						'tags' => array( 'accessibility', 'a11y', 'design' ),
					),
					array(
						'id'   => 's_cli',
						'text' => 'Command-line tooling / developer experience',
						'tags' => array( 'cli', 'devtools', 'dev' ),
					),
					array(
						'id'   => 's_perf',
						'text' => 'Performance optimisation',
						'tags' => array( 'performance', 'code', 'dev' ),
					),
				),
			),
			array(
				'id'      => 'q_experience',
				'type'    => 'radio',
				'text'    => 'How would you describe your experience level with WordPress?',
				'answers' => array(
					array(
						'id'   => 'e_new',
						'text' => 'Brand new — I am still learning the basics',
						'tags' => array( 'beginner', 'support', 'education' ),
					),
					array(
						'id'   => 'e_user',
						'text' => 'Experienced user — I build sites but rarely write code',
						'tags' => array( 'intermediate', 'support', 'community', 'docs' ),
					),
					array(
						'id'   => 'e_dev',
						'text' => 'Developer — I write plugins or themes regularly',
						'tags' => array( 'advanced', 'code', 'dev', 'php' ),
					),
					array(
						'id'   => 'e_core',
						'text' => 'Core contributor — I already contribute to WordPress',
						'tags' => array( 'expert', 'code', 'dev', 'meta' ),
					),
				),
			),
			array(
				'id'      => 'q_passion',
				'type'    => 'checkbox',
				'text'    => 'Which outcome matters most to you personally?',
				'answers' => array(
					array(
						'id'   => 'p_quality',
						'text' => 'Improving the quality and reliability of WordPress',
						'tags' => array( 'testing', 'qa', 'performance', 'code' ),
					),
					array(
						'id'   => 'p_reach',
						'text' => 'Making WordPress accessible to everyone, everywhere',
						'tags' => array( 'accessibility', 'translation', 'i18n', 'community' ),
					),
					array(
						'id'   => 'p_learn',
						'text' => 'Helping people learn and grow with WordPress',
						'tags' => array( 'education', 'docs', 'training', 'support' ),
					),
					array(
						'id'   => 'p_build',
						'text' => 'Building powerful new features and tools',
						'tags' => array( 'code', 'dev', 'php', 'javascript' ),
					),
					array(
						'id'   => 'p_beauty',
						'text' => 'Making WordPress more beautiful and easier to use',
						'tags' => array( 'design', 'ux', 'themes' ),
					),
					array(
						'id'   => 'p_ecosystem',
						'text' => 'Strengthening the plugin and theme ecosystem',
						'tags' => array( 'plugins', 'themes', 'code' ),
					),
				),
			),
			array(
				'id'      => 'q_time',
				'type'    => 'radio',
				'text'    => 'How would you prefer to contribute?',
				'answers' => array(
					array(
						'id'   => 't_async',
						'text' => 'Independently, on my own schedule',
						'tags' => array( 'async', 'docs', 'translation', 'photos' ),
					),
					array(
						'id'   => 't_sync',
						'text' => 'Live team meetings and real-time collaboration',
						'tags' => array( 'sync', 'community', 'events', 'support' ),
					),
					array(
						'id'   => 't_both',
						'text' => 'A mix of both',
						'tags' => array( 'async', 'sync', 'code', 'dev' ),
					),
				),
			),
		);
	}

	/**
	 * Built-in default teams with tag weights (used when no overrides are saved).
	 */
	public static function get_default_teams() {
		return array(
			array(
				'id'          => 'core',
				'name'        => 'Core',
				'url'         => 'https://make.wordpress.org/core/',
				'description' => 'Build the WordPress software itself. Work on PHP, JavaScript, and REST API with developers from all over the world.',
				'icon'        => '⚙️',
				'tags'        => array(
					'code'       => 5,
					'php'        => 5,
					'javascript' => 4,
					'dev'        => 5,
					'backend'    => 4,
					'frontend'   => 3,
					'advanced'   => 2,
					'expert'     => 2,
					'build'      => 3,
					'async'      => 2,
					'sync'       => 2,
				),
			),
			array(
				'id'          => 'design',
				'name'        => 'Design',
				'url'         => 'https://make.wordpress.org/design/',
				'description' => 'Shape the look and feel of WordPress. Create mockups, contribute to the design system, and improve user experience.',
				'icon'        => '🎨',
				'tags'        => array(
					'design' => 5,
					'ux'     => 5,
					'ui'     => 5,
					'beauty' => 3,
					'themes' => 2,
					'async'  => 2,
					'sync'   => 2,
				),
			),
			array(
				'id'          => 'accessibility',
				'name'        => 'Accessibility',
				'url'         => 'https://make.wordpress.org/accessibility/',
				'description' => 'Ensure WordPress is usable by everyone, including people with disabilities. Work on audits, testing, and inclusive design.',
				'icon'        => '♿',
				'tags'        => array(
					'accessibility' => 5,
					'a11y'          => 5,
					'design'        => 3,
					'testing'       => 3,
					'reach'         => 4,
					'code'          => 2,
				),
			),
			array(
				'id'          => 'polyglots',
				'name'        => 'Polyglots',
				'url'         => 'https://make.wordpress.org/polyglots/',
				'description' => 'Translate WordPress and its ecosystem into every language. Make WordPress truly global.',
				'icon'        => '🌍',
				'tags'        => array(
					'translation' => 5,
					'i18n'        => 5,
					'community'   => 3,
					'reach'       => 4,
					'async'       => 3,
				),
			),
			array(
				'id'          => 'support',
				'name'        => 'Support',
				'url'         => 'https://make.wordpress.org/support/',
				'description' => 'Answer questions in the WordPress.org support forums. Help users from beginners to advanced site owners.',
				'icon'        => '💬',
				'tags'        => array(
					'support'      => 5,
					'community'    => 4,
					'beginner'     => 3,
					'intermediate' => 3,
					'education'    => 2,
					'sync'         => 3,
					'async'        => 3,
				),
			),
			array(
				'id'          => 'docs',
				'name'        => 'Documentation',
				'url'         => 'https://make.wordpress.org/docs/',
				'description' => 'Write and maintain user guides, developer handbooks, and end-user documentation for WordPress.',
				'icon'        => '📝',
				'tags'        => array(
					'writing'   => 5,
					'docs'      => 5,
					'education' => 4,
					'async'     => 4,
					'beginner'  => 2,
					'learn'     => 3,
				),
			),
			array(
				'id'          => 'themes',
				'name'        => 'Themes',
				'url'         => 'https://make.wordpress.org/themes/',
				'description' => 'Review and approve themes submitted to the WordPress.org theme directory. Help maintain quality standards.',
				'icon'        => '🖼️',
				'tags'        => array(
					'themes'    => 5,
					'design'    => 4,
					'ui'        => 3,
					'php'       => 3,
					'code'      => 3,
					'ecosystem' => 4,
				),
			),
			array(
				'id'          => 'plugins',
				'name'        => 'Plugins',
				'url'         => 'https://make.wordpress.org/plugins/',
				'description' => 'Keep the plugin ecosystem healthy. Review guidelines, handle security reports, and support plugin developers.',
				'icon'        => '🔌',
				'tags'        => array(
					'plugins'   => 5,
					'php'       => 3,
					'code'      => 3,
					'ecosystem' => 5,
					'security'  => 3,
					'dev'       => 3,
				),
			),
			array(
				'id'          => 'community',
				'name'        => 'Community',
				'url'         => 'https://make.wordpress.org/community/',
				'description' => 'Organise WordCamps, meetups, and contributor days. Grow the worldwide WordPress community.',
				'icon'        => '🤝',
				'tags'        => array(
					'community' => 5,
					'events'    => 5,
					'outreach'  => 5,
					'sync'      => 4,
					'reach'     => 3,
				),
			),
			array(
				'id'          => 'meta',
				'name'        => 'Meta',
				'url'         => 'https://make.wordpress.org/meta/',
				'description' => 'Build and maintain WordPress.org — the website, GlotPress, profiles, and all the tools contributors use.',
				'icon'        => '🌐',
				'tags'        => array(
					'code'       => 4,
					'php'        => 4,
					'javascript' => 3,
					'dev'        => 4,
					'meta'       => 5,
					'expert'     => 3,
					'devtools'   => 3,
				),
			),
			array(
				'id'          => 'training',
				'name'        => 'Training',
				'url'         => 'https://make.wordpress.org/training/',
				'description' => 'Create courses and lesson plans for learn.wordpress.org. Help people of all backgrounds learn WordPress.',
				'icon'        => '🎓',
				'tags'        => array(
					'education' => 5,
					'writing'   => 4,
					'docs'      => 3,
					'training'  => 5,
					'learn'     => 5,
					'beginner'  => 2,
					'async'     => 3,
				),
			),
			array(
				'id'          => 'test',
				'name'        => 'Test',
				'url'         => 'https://make.wordpress.org/test/',
				'description' => 'Improve WordPress quality by running manual and automated tests, writing test cases, and reporting bugs.',
				'icon'        => '🧪',
				'tags'        => array(
					'testing'      => 5,
					'qa'           => 5,
					'bugs'         => 5,
					'quality'      => 4,
					'code'         => 2,
					'async'        => 3,
					'intermediate' => 2,
					'beginner'     => 2,
				),
			),
			array(
				'id'          => 'tv',
				'name'        => 'TV',
				'url'         => 'https://make.wordpress.org/tv/',
				'description' => 'Review and publish WordCamp videos on WordPress.tv. Add captions and manage post-production.',
				'icon'        => '🎬',
				'tags'        => array(
					'media'   => 5,
					'video'   => 5,
					'writing' => 2,
					'async'   => 4,
				),
			),
			array(
				'id'          => 'cli',
				'name'        => 'CLI',
				'url'         => 'https://make.wordpress.org/cli/',
				'description' => 'Maintain WP-CLI, the command-line interface for WordPress. Build powerful developer tooling.',
				'icon'        => '💻',
				'tags'        => array(
					'cli'      => 5,
					'devtools' => 5,
					'dev'      => 4,
					'php'      => 4,
					'code'     => 4,
					'advanced' => 3,
					'expert'   => 2,
				),
			),
			array(
				'id'          => 'hosting',
				'name'        => 'Hosting',
				'url'         => 'https://make.wordpress.org/hosting/',
				'description' => 'Test pre-release WordPress on various server environments and maintain hosting best-practice documentation.',
				'icon'        => '🖥️',
				'tags'        => array(
					'hosting'        => 5,
					'infrastructure' => 5,
					'devops'         => 5,
					'backend'        => 3,
					'dev'            => 3,
					'quality'        => 3,
				),
			),
			array(
				'id'          => 'openverse',
				'name'        => 'Openverse',
				'url'         => 'https://make.wordpress.org/openverse/',
				'description' => 'Work on the openly-licensed media search engine. Contribute via Python, Django, Vue.js, and more.',
				'icon'        => '🔎',
				'tags'        => array(
					'code'       => 4,
					'javascript' => 4,
					'media'      => 4,
					'photos'     => 3,
					'dev'        => 4,
					'frontend'   => 4,
				),
			),
			array(
				'id'          => 'photos',
				'name'        => 'Photos',
				'url'         => 'https://make.wordpress.org/photos/',
				'description' => 'Moderate and curate the WordPress Photo Directory. Contribute your own photography under a CC0 licence.',
				'icon'        => '📷',
				'tags'        => array(
					'photos' => 5,
					'media'  => 4,
					'async'  => 4,
				),
			),
			array(
				'id'          => 'performance',
				'name'        => 'Core Performance',
				'url'         => 'https://make.wordpress.org/performance/',
				'description' => 'Measure and improve WordPress performance. Work on benchmarks, profiling, and optimisation patches.',
				'icon'        => '⚡',
				'tags'        => array(
					'performance' => 5,
					'code'        => 4,
					'php'         => 4,
					'javascript'  => 3,
					'dev'         => 4,
					'quality'     => 4,
				),
			),
			array(
				'id'          => 'playground',
				'name'        => 'Playground',
				'url'         => 'https://make.wordpress.org/playground/',
				'description' => 'Build WordPress Playground — a fully in-browser WordPress experience powered by WebAssembly.',
				'icon'        => '🛝',
				'tags'        => array(
					'javascript' => 5,
					'code'       => 4,
					'dev'        => 4,
					'frontend'   => 5,
					'devtools'   => 4,
					'advanced'   => 3,
				),
			),
			array(
				'id'          => 'ai',
				'name'        => 'Core AI',
				'url'         => 'https://make.wordpress.org/ai/',
				'description' => 'Coordinate AI efforts within WordPress core, aligned with WordPress values and a plugin-first approach.',
				'icon'        => '🤖',
				'tags'        => array(
					'code'       => 4,
					'php'        => 4,
					'dev'        => 4,
					'javascript' => 3,
					'advanced'   => 3,
					'expert'     => 3,
				),
			),
			array(
				'id'          => 'program',
				'name'        => 'Core Program',
				'url'         => 'https://make.wordpress.org/program/',
				'description' => 'Strengthen cross-team coordination and empower contributors. Help keep the WordPress project running smoothly.',
				'icon'        => '📋',
				'tags'        => array(
					'community' => 4,
					'outreach'  => 4,
					'events'    => 3,
					'sync'      => 4,
					'meta'      => 3,
					'expert'    => 2,
				),
			),
		);
	}

	/**
	 * Score teams based on selected answer tags.
	 *
	 * @param array $selected_tags Flat array of tag strings from all chosen answers.
	 * @return array Teams sorted by descending score, each with a 'score' key added.
	 */
	public static function score_teams( array $selected_tags ) {
		$tag_counts = array_count_values( $selected_tags );
		$teams      = self::get_teams();

		foreach ( $teams as &$team ) {
			$score = 0;
			foreach ( $tag_counts as $tag => $count ) {
				if ( isset( $team['tags'][ $tag ] ) ) {
					$score += $team['tags'][ $tag ] * $count;
				}
			}
			$team['score'] = $score;
		}
		unset( $team );

		usort(
			$teams,
			function ( $a, $b ) {
				return $b['score'] <=> $a['score'];
			} 
		);

		return $teams;
	}
}
