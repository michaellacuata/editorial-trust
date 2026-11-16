<?php
/**
 * Plugin Name:       Editorial Trust – Author Profile System
 * Description:       Author, Edited by, Reviewed by and Fact checked bylines, author box, expert profiles with article archives, sources and author schema. No third-party plugins needed. Settings: Editorial Trust → Settings.
 * Version:           2.7.0
 * Author:            MAP
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Text Domain:       editorial-trust
 * Update URI:        https://github.com/michaellacuata/editorial-trust
 */

// Tells the old Editorial Trust code snippet (if still active) to pause itself.
if ( ! defined( 'ETP_VERSION' ) ) {
	define( 'ETP_VERSION', '2.7.0' );
}

// Updates from GitHub: new releases show as "Update available" on the Plugins screen.
if ( defined( 'ABSPATH' ) && is_readable( __DIR__ . '/lib/plugin-update-checker/plugin-update-checker.php' ) ) {
	require_once __DIR__ . '/lib/plugin-update-checker/plugin-update-checker.php';
	if ( class_exists( '\YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
		$etp_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			'https://github.com/michaellacuata/editorial-trust/',
			__FILE__,
			'editorial-trust'
		);
		$etp_update_checker->setBranch( 'main' );
		$etp_update_checker->getVcsApi()->enableReleaseAssets( '/editorial-trust.*\.zip($|[?&#])/i' );
	}
}

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! class_exists( 'Etpro' ) ) :

/* ======================================================================== */
/* Core helpers: settings, people, article data, icons                      */
/* ======================================================================== */

final class Etpro {

	const VERSION = '2.7.0';

	private static $settings = null;
	private static $people   = array();
	private static $map      = null;

	/** Runs on load. Everything else hangs off init. */
	public static function boot() {
		if ( did_action( 'init' ) ) {
			self::setup();
			if ( ! post_type_exists( 'etp_person' ) ) {
				Etpro_People::register_post_type();
			}
			return;
		}
		add_action( 'init', array( __CLASS__, 'setup' ), 0 );
	}

	public static function setup() {
		if ( class_exists( 'ETP_People', false ) ) {
			add_action( 'admin_notices', function () {
				if ( current_user_can( 'activate_plugins' ) ) {
					echo '<div class="notice notice-warning"><p><strong>Editorial Trust snippet:</strong> the Editorial Trust <em>plugin</em> is still active, so the snippet is paused. Deactivate the plugin (Plugins screen) and the snippet takes over automatically. No data is lost.</p></div>';
				}
			} );
			return;
		}
		if ( false === get_option( 'etp_settings' ) ) {
			add_option( 'etp_settings', self::defaults() );
		}
		Etpro_People::init();
		Etpro_Article_Meta::init();
		Etpro_Settings::init();
		Etpro_Admin::init();
		Etpro_Front::init();
		Etpro_Schema::init();
		add_action( 'init', array( __CLASS__, 'maybe_flush' ), 99 );
	}

	/** Replaces the plugin's activation hook: refresh permalinks once per version / base change. */
	public static function maybe_flush() {
		$key = self::VERSION . '|' . self::get( 'profile_base' );
		if ( get_option( 'etp_snippet_rewrite' ) !== $key || get_option( 'etp_flush_rewrite' ) ) {
			delete_option( 'etp_flush_rewrite' );
			update_option( 'etp_snippet_rewrite', $key, false );
			flush_rewrite_rules( false );
		}
	}

	/**
	 * Default settings. Every visible label lives here so it can be edited in
	 * Experts → Settings without touching code.
	 */
	public static function defaults() {
		return array(
			// General.
			'post_types'          => array( 'post' ),
			'default_author'      => 0,
			'default_reviewer'    => 0,
			'default_editor'      => 0,
			'default_fact_check'  => 1,
			'date_format'         => 'j M Y',
			'history_date_format' => 'j F Y',
			'updated_fallback'    => 'modified', // modified | published.

			// Display and placement.
			'auto_trust_bar'      => 1,
			'trust_bar_style'     => 'light', // light | dark.
			'trust_bar_card'      => 1,
			'auto_sources'        => 1,
			'auto_author_box'     => 0,
			'author_box_heading'  => 'About the authors',
			'published_label'     => 0,
			'custom_css'          => '',
			'cta_position'        => 'after_sources', // after_trust | after_sources | end | off.
			'profile_auto_append' => 1,
			'team_page_sections'  => 'expertise,qualifications,written,edited,reviewed',
			'accent_color'        => '#d99a00',
			'content_max_width'   => '',
			'load_everywhere'     => 0,

			// Fact check panel.
			'fc_label'            => 'Fact checked',
			'fc_hint'             => 'See how we checked',
			'fc_kicker'           => 'Editorial standards',
			'fc_heading'          => 'Why you can trust this article',
			'fc_summary'          => '{reviewer} checked this article for accuracy, lending policy context and clarity before it was published or updated.',
			'fc_items'            => "Lending policy and eligibility information\nGovernment schemes where relevant\nLoan terminology and examples\nRelevant lender or regulatory information",
			'fc_items_heading'    => 'What we checked',
			'fc_bold_leadins'     => 0,
			'fc_guidelines_url'   => '',
			'fc_guidelines_label' => 'Read our editorial guidelines',

			// Sources.
			'src_eyebrow'         => 'Experience and sources',
			'src_heading'         => 'Sources and further reading',
			'src_intro'           => 'The sources below explain the rules, information and options discussed in this guide.',
			'src_list_heading'    => 'Sources',
			'src_disclaimer'      => 'This article is general information only and doesn\'t take your objectives, financial situation or needs into account. Your loan options depend on your circumstances and each lender\'s assessment. Consider getting legal, tax or financial advice that relates to your situation.',
			'src_new_tab'         => 1,

			// Update history.
			'history_heading'     => 'Article update history',

			// CTA.
			'cta_question'        => 'Have a question about this article?',
			'cta_link_text'       => 'Ask {first_name}',
			'cta_url'             => '',
			'cta_mode'            => 'link', // link | popup.
			'cta_popup_selector'  => '',
			'content_selector'    => '',
			'cta_param_article'   => 'etp_article',
			'cta_param_expert'    => 'etp_expert',

			// Profiles.
			'profile_base'        => 'expert',
			'written_eyebrow'     => 'Published work',
			'written_heading'     => 'Written by {first_name}',
			'reviewed_eyebrow'    => 'Review work',
			'reviewed_heading'    => 'Reviewed by {first_name}',
			'edited_eyebrow'      => 'Editorial work',
			'edited_heading'      => 'Edited by {first_name}',
			'list_limit'          => 6,
			'expertise_heading'   => 'Areas of expertise',
			'profile_cta_text'    => 'Talk to {first_name}',

			// Structured data.
			'schema_mode'         => 'auto', // auto | standalone | off.
			'org_name'            => '',
			'org_logo'            => '',
		);
	}

	public static function settings() {
		if ( null === self::$settings ) {
			$saved          = get_option( 'etp_settings', array() );
			self::$settings = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		}
		return self::$settings;
	}

	public static function reset_cache() {
		self::$settings = null;
		self::$people   = array();
		self::$map      = null;
	}

	public static function get( $key ) {
		$s = self::settings();
		return isset( $s[ $key ] ) ? $s[ $key ] : null;
	}

	public static function post_types() {
		$types = (array) self::get( 'post_types' );
		$types = array_values( array_filter( array_map( 'sanitize_key', $types ) ) );
		return apply_filters( 'etp_post_types', $types ? $types : array( 'post' ) );
	}

	/** Split a textarea/string list. Accepts new lines, commas, middots and pipes. */
	public static function split_list( $value, $allow_commas = true ) {
		if ( is_array( $value ) ) {
			$items = $value;
		} else {
			$pattern = $allow_commas ? '/\r\n|\r|\n|·|\||,/u' : '/\r\n|\r|\n/u';
			$items   = preg_split( $pattern, (string) $value );
		}
		$items = array_map( 'trim', (array) $items );
		return array_values( array_filter( $items, 'strlen' ) );
	}

	public static function tokens( $text, $tokens ) {
		foreach ( $tokens as $k => $v ) {
			$text = str_replace( '{' . $k . '}', (string) $v, $text );
		}
		return $text;
	}

	public static function format_date( $date, $format = null ) {
		if ( ! $date ) {
			return '';
		}
		$format = $format ? $format : self::get( 'date_format' );
		if ( is_numeric( $date ) ) {
			return wp_date( $format, (int) $date );
		}
		try {
			$dt = new DateTimeImmutable( $date, wp_timezone() );
		} catch ( Exception $e ) {
			return '';
		}
		return wp_date( $format, $dt->getTimestamp() );
	}

	public static function valid_ymd( $value ) {
		$value = trim( (string) $value );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
	}

	/** Normalised person record, or null. */
	public static function person( $person_id ) {
		$person_id = (int) $person_id;
		if ( ! $person_id ) {
			return null;
		}
		if ( array_key_exists( $person_id, self::$people ) ) {
			return self::$people[ $person_id ];
		}
		$post = get_post( $person_id );
		if ( ! $post || 'etp_person' !== $post->post_type ) {
			self::$people[ $person_id ] = null;
			return null;
		}

		$name       = get_the_title( $post );
		$short      = trim( (string) get_post_meta( $person_id, '_etp_short_name', true ) );
		$parts      = preg_split( '/\s+/', trim( $name ) );
		$job        = trim( (string) get_post_meta( $person_id, '_etp_job_title', true ) );
		$creds      = trim( (string) get_post_meta( $person_id, '_etp_credentials', true ) );
		$page_id    = (int) get_post_meta( $person_id, '_etp_profile_page_id', true );
		$custom_url = esc_url_raw( (string) get_post_meta( $person_id, '_etp_profile_url', true ) );

		$new_tab = false;
		if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
			$url = get_permalink( $page_id );
		} elseif ( $custom_url ) {
			$url     = $custom_url;
			$new_tab = self::profile_new_tab( $person_id, $custom_url );
		} else {
			$url = get_permalink( $person_id );
		}

		$person = array(
			'id'              => $person_id,
			'name'            => $name,
			'first_name'      => $short ? $short : ( $parts ? $parts[0] : $name ),
			'new_tab'         => $new_tab,
			'job_title'       => $job,
			'credentials'     => $creds ? $creds : $job,
			'photo'           => self::person_photo( $person_id, 96 ),
			'photo_large'     => self::person_photo( $person_id, 400 ),
			'initials'        => self::initials( $name ),
			'linkedin'        => esc_url_raw( (string) get_post_meta( $person_id, '_etp_linkedin', true ) ),
			'url'             => $url,
			'profile_page_id' => $page_id,
			'summary'         => has_excerpt( $post ) ? $post->post_excerpt : '',
			'bio'             => $post->post_content,
			'experience'      => (string) get_post_meta( $person_id, '_etp_experience', true ),
			'qualifications'  => self::split_list( get_post_meta( $person_id, '_etp_qualifications', true ), false ),
			'expertise'       => self::split_list( get_post_meta( $person_id, '_etp_expertise', true ) ),
			'public'          => ( 'publish' === $post->post_status && '1' === (string) get_post_meta( $person_id, '_etp_public', true ) ),
			'schema_id'       => untrailingslashit( $url ) . '/#person',
		);

		self::$people[ $person_id ] = apply_filters( 'etp_person', $person, $person_id );
		return self::$people[ $person_id ];
	}

	public static function initials( $name ) {
		$words = preg_split( '/\s+/', trim( (string) $name ) );
		$out   = '';
		foreach ( array_slice( $words, 0, 2 ) as $w ) {
			$out .= function_exists( 'mb_substr' ) ? mb_substr( $w, 0, 1 ) : substr( $w, 0, 1 );
		}
		return strtoupper( $out );
	}

	/** Uploaded photo first, then Gravatar (only if it really has an image). */
	public static function person_photo( $person_id, $size = 96 ) {
		$thumb_id = get_post_thumbnail_id( $person_id );
		if ( $thumb_id ) {
			$url = wp_get_attachment_image_url( $thumb_id, $size > 150 ? 'medium' : 'thumbnail' );
			if ( $url ) {
				return $url;
			}
		}
		$email = sanitize_email( (string) get_post_meta( $person_id, '_etp_gravatar_email', true ) );
		if ( $email && self::gravatar_exists( $email ) ) {
			return get_avatar_url( $email, array( 'size' => $size * 2, 'default' => '404' ) );
		}
		return '';
	}

	public static function gravatar_exists( $email ) {
		$hash = md5( strtolower( trim( $email ) ) );
		$key  = 'etp_grav_' . $hash;
		$hit  = get_transient( $key );
		if ( false !== $hit ) {
			return 'yes' === $hit;
		}
		$res    = wp_remote_head( 'https://www.gravatar.com/avatar/' . $hash . '?d=404&s=80', array( 'timeout' => 3 ) );
		$exists = ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res );
		set_transient( $key, $exists ? 'yes' : 'no', $exists ? WEEK_IN_SECONDS : 6 * HOUR_IN_SECONDS );
		return $exists;
	}

	/** People of a role on an article, in order. Public only unless $include_hidden. */
	public static function article_people( $post_id, $role = 'author', $include_hidden = false ) {
		$keys = array( 'reviewer' => '_etp_reviewers', 'editor' => '_etp_editors' );
		$ids  = get_post_meta( $post_id, isset( $keys[ $role ] ) ? $keys[ $role ] : '_etp_authors', true );
		$ids = is_array( $ids ) ? array_map( 'intval', $ids ) : array();
		$out = array();
		foreach ( array_unique( array_filter( $ids ) ) as $id ) {
			$p = self::person( $id );
			if ( $p && ( $include_hidden || $p['public'] ) ) {
				$out[] = $p;
			}
		}
		return $out;
	}

	/** Everything the front end needs for one article, already resolved. */
	public static function article( $post_id ) {
		$post_id = (int) $post_id;
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return null;
		}
		$authors   = self::article_people( $post_id, 'author' );
		$reviewers = self::article_people( $post_id, 'reviewer' );
		$editors   = self::article_people( $post_id, 'editor' );

		$updated = self::valid_ymd( get_post_meta( $post_id, '_etp_updated_date', true ) );
		if ( $updated ) {
			$updated_ts  = ( new DateTimeImmutable( $updated, wp_timezone() ) )->getTimestamp();
			$updated_iso = $updated;
		} elseif ( 'published' === self::get( 'updated_fallback' ) ) {
			$updated_ts  = get_post_timestamp( $post, 'date' );
			$updated_iso = get_post_time( 'c', false, $post );
		} else {
			$updated_ts  = get_post_timestamp( $post, 'modified' );
			$updated_iso = get_post_modified_time( 'c', false, $post );
		}

		$fc_raw  = get_post_meta( $post_id, '_etp_fact_checked', true );
		$sources = get_post_meta( $post_id, '_etp_sources', true );
		$sources = is_array( $sources ) ? array_values( array_filter( $sources, function ( $s ) {
			return ! empty( $s['url'] ) && ! empty( $s['title'] );
		} ) ) : array();

		$history = get_post_meta( $post_id, '_etp_history', true );
		$history = is_array( $history ) ? array_values( array_filter( $history, function ( $h ) {
			return ! empty( $h['note'] ) && Etpro::valid_ymd( isset( $h['date'] ) ? $h['date'] : '' );
		} ) ) : array();
		usort( $history, function ( $a, $b ) {
			return strcmp( $b['date'], $a['date'] );
		} );

		$checked    = trim( (string) get_post_meta( $post_id, '_etp_fc_items', true ) );
		$cta_person = (int) get_post_meta( $post_id, '_etp_cta_person', true );
		$cta_p      = $cta_person ? self::person( $cta_person ) : null;
		if ( ! $cta_p || ! $cta_p['public'] ) {
			$cta_p = $authors ? $authors[0] : null;
		}

		$data = array(
			'post_id'         => $post_id,
			'title'           => get_the_title( $post ),
			'url'             => get_permalink( $post ),
			'authors'         => $authors,
			'reviewers'       => $reviewers,
			'author'          => $authors ? $authors[0] : null,
			'reviewer'        => $reviewers ? $reviewers[0] : null,
			'editors'         => $editors,
			'editor'          => $editors ? $editors[0] : null,
			'has_updated'     => (bool) $updated,
			'updated_ts'      => $updated_ts,
			'updated_iso'     => $updated_iso,
			'published_iso'   => get_post_time( 'c', false, $post ),
			'reviewed'        => self::valid_ymd( get_post_meta( $post_id, '_etp_reviewed_date', true ) ),
			'fact_checked'    => '' === $fc_raw ? false : ( '1' === (string) $fc_raw ),
			'fc_summary'      => trim( (string) get_post_meta( $post_id, '_etp_fc_summary', true ) ),
			'fc_items'        => self::split_list( $checked ? $checked : self::get( 'fc_items' ), false ),
			'sources'         => $sources,
			'src_intro'       => trim( (string) get_post_meta( $post_id, '_etp_src_intro', true ) ),
			'src_disclaimer'  => trim( (string) get_post_meta( $post_id, '_etp_src_disclaimer', true ) ),
			'hide_disclaimer' => '1' === (string) get_post_meta( $post_id, '_etp_hide_disclaimer', true ),
			'src_position'    => (string) get_post_meta( $post_id, '_etp_src_position', true ), // '' auto | end | manual | off.
			'history'         => $history,
			'show_history'    => '1' === (string) get_post_meta( $post_id, '_etp_show_history', true ),
			'hide_trust_bar'  => '1' === (string) get_post_meta( $post_id, '_etp_hide_trust_bar', true ),
			'cta_mode'        => (string) get_post_meta( $post_id, '_etp_cta_mode', true ), // '' | custom | off.
			'cta_question'    => trim( (string) get_post_meta( $post_id, '_etp_cta_question', true ) ),
			'cta_link_text'   => trim( (string) get_post_meta( $post_id, '_etp_cta_link_text', true ) ),
			'cta_person'      => $cta_p,
		);
		return apply_filters( 'etp_article_data', $data, $post_id );
	}

	/* -- Markup helpers --------------------------------------------------- */

	/** Inline SVG icons (no icon font, no extra requests). */
	public static function icon( $name, $size = 16 ) {
		$paths = array(
			'pen'      => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
			'check'    => '<path d="M20 6 9 17l-5-5"/>',
			'seal'     => '<path class="etp-fill" d="M3.85 8.62a4 4 0 0 1 4.78-4.77 4 4 0 0 1 6.74 0 4 4 0 0 1 4.78 4.78 4 4 0 0 1 0 6.74 4 4 0 0 1-4.77 4.78 4 4 0 0 1-6.75 0 4 4 0 0 1-4.78-4.77 4 4 0 0 1 0-6.76Z"/><path class="etp-seal__check" d="m8.6 12.2 2.4 2.4 4.5-4.8"/>',
			'book'     => '<path d="M2 4h6a4 4 0 0 1 4 4v13a3 3 0 0 0-3-3H2z"/><path d="M22 4h-6a4 4 0 0 0-4 4v13a3 3 0 0 1 3-3h7z"/>',
			'info'     => '<circle cx="12" cy="12" r="9.5"/><path d="M12 16v-4.5"/><path d="M12 8h.01"/>',
			'history'  => '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/>',
			'award'    => '<circle cx="12" cy="8" r="6"/><path d="M15.48 12.89 17 22l-5-3-5 3 1.52-9.11"/>',
			'arrow'    => '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
			'external' => '<path d="M7 17 17 7"/><path d="M8 7h9v9"/>',
			'chevron'  => '<path d="m6 9 6 6 6-6"/>',
			'close'    => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
			'message'  => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
			'file'     => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/>',
			'linkedin' => '<path class="etp-fill" d="M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.35V9h3.41v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28ZM5.34 7.43a2.06 2.06 0 1 1 0-4.13 2.06 2.06 0 0 1 0 4.13ZM7.12 20.45H3.56V9h3.56v11.45ZM22.22 0H1.77C.79 0 0 .77 0 1.73v20.54C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.73V1.73C24 .77 23.2 0 22.22 0Z"/>',
		);
		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}
		return sprintf(
			'<svg class="etp-i etp-i--%1$s" viewBox="0 0 24 24" width="%2$d" height="%2$d" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%3$s</svg>',
			esc_attr( $name ),
			(int) $size,
			$paths[ $name ]
		);
	}

	/** Photo or initials. Explicit width/height prevent layout shift. */
	public static function avatar( $person, $size = 40, $large = false, $lazy = true ) {
		if ( ! $person ) {
			return '';
		}
		$src = $large && $person['photo_large'] ? $person['photo_large'] : $person['photo'];
		if ( $src ) {
			return sprintf(
				'<img class="etp-avatar" src="%1$s" width="%2$d" height="%2$d" alt="%3$s" loading="%4$s" decoding="async" style="--etp-size:%2$dpx">',
				esc_url( $src ),
				(int) $size,
				esc_attr( $person['name'] ),
				$lazy ? 'lazy' : 'eager'
			);
		}
		return sprintf(
			'<span class="etp-avatar etp-avatar--initials" style="--etp-size:%1$dpx" aria-hidden="true">%2$s</span>',
			(int) $size,
			esc_html( $person['initials'] )
		);
	}

	/** Avatar with a ring and a role badge (pen = wrote it, tick = reviewed it). */
	public static function avatar_badged( $person, $size, $role = 'author', $lazy = true ) {
		if ( ! $person ) {
			return '';
		}
		$role = in_array( $role, array( 'reviewer', 'editor' ), true ) ? $role : 'author';
		return '<span class="etp-av etp-av--' . $role . '">' . self::avatar( $person, $size, $size > 64, $lazy )
			. '<span class="etp-av__badge">' . self::icon( 'reviewer' === $role ? 'check' : 'pen', 10 ) . '</span></span>';
	}

	/** Should a custom profile URL open in a new tab? Unset = yes for links to another website. */
	public static function profile_new_tab( $person_id, $url ) {
		$v = (string) get_post_meta( $person_id, '_etp_profile_new_tab', true );
		if ( '' === $v ) {
			$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
			$home = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
			return $host && preg_replace( '/^www\\./', '', $host ) !== preg_replace( '/^www\\./', '', $home );
		}
		return '1' === $v;
	}

	/** target / rel attributes for a link to a person's profile. */
	public static function link_attrs( $person, $author = false ) {
		$rel = $author ? array( 'author' ) : array();
		$out = '';
		if ( ! empty( $person['new_tab'] ) ) {
			$rel[] = 'noopener';
			$out   = ' target="_blank"';
		}
		return ( $rel ? ' rel="' . implode( ' ', $rel ) . '"' : '' ) . $out;
	}

	public static function linkedin_link( $person ) {
		if ( empty( $person['linkedin'] ) ) {
			return '';
		}
		return sprintf(
			'<a class="etp-linkedin" href="%1$s" target="_blank" rel="noopener" aria-label="%2$s">%3$s</a>',
			esc_url( $person['linkedin'] ),
			/* translators: %s: person name */
			esc_attr( sprintf( __( '%s on LinkedIn', 'editorial-trust' ), $person['name'] ) ),
			self::icon( 'linkedin', 14 )
		);
	}

	public static function domain( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		return $host ? preg_replace( '/^www\./', '', $host ) : '';
	}

	/** Expert IDs whose profile is the given page (usually 0 or 1). */
	public static function people_for_page( $page_id ) {
		$page_id = (int) $page_id;
		if ( ! $page_id ) {
			return array();
		}
		$map = self::page_map();
		return isset( $map[ $page_id ] ) ? $map[ $page_id ] : array();
	}

	/** [page_id => [person_id, …]] for published experts linked to a team page. */
	public static function page_map() {
		if ( null !== self::$map ) {
			return self::$map;
		}
		$cached = get_transient( 'etp_page_map' );
		if ( is_array( $cached ) ) {
			self::$map = $cached;
			return self::$map;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( "SELECT m.meta_value AS page_id, p.ID AS person_id FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = '_etp_profile_page_id' AND m.meta_value > 0 AND p.post_type = 'etp_person' AND p.post_status = 'publish' ORDER BY p.menu_order, p.ID" );
		self::$map = array();
		foreach ( (array) $rows as $r ) {
			self::$map[ (int) $r->page_id ][] = (int) $r->person_id;
		}
		set_transient( 'etp_page_map', self::$map, DAY_IN_SECONDS );
		return self::$map;
	}

	/** Find a person by ID, slug or name (shortcodes). */
	public static function find_person( $ref ) {
		$ref = trim( (string) $ref );
		if ( '' === $ref ) {
			return 0;
		}
		if ( ctype_digit( $ref ) ) {
			return 'etp_person' === get_post_type( (int) $ref ) ? (int) $ref : 0;
		}
		$p = get_page_by_path( sanitize_title( $ref ), OBJECT, 'etp_person' );
		if ( $p ) {
			return (int) $p->ID;
		}
		$q = get_posts( array(
			'post_type'      => 'etp_person',
			'title'          => $ref,
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'post_status'    => 'any',
		) );
		return $q ? (int) $q[0] : 0;
	}

	/* -- Colour helpers ----------------------------------------------------- */

	public static function rgb( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
	}

	/** Mix two hex colours. $weight = share of $b. */
	public static function mix( $a, $b, $weight ) {
		$ca  = self::rgb( $a );
		$cb  = self::rgb( $b );
		$out = '#';
		for ( $i = 0; $i < 3; $i++ ) {
			$out .= str_pad( dechex( (int) round( $ca[ $i ] * ( 1 - $weight ) + $cb[ $i ] * $weight ) ), 2, '0', STR_PAD_LEFT );
		}
		return $out;
	}

	/** Text colour (near-white or near-black) with the better contrast on $hex. */
	public static function on_color( $hex ) {
		$l = 0;
		foreach ( self::rgb( $hex ) as $i => $c ) {
			$c  = $c / 255;
			$c  = $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
			$l += $c * array( 0.2126, 0.7152, 0.0722 )[ $i ];
		}
		return ( 1.05 / ( $l + 0.05 ) ) >= ( ( $l + 0.05 ) / 0.05 ) ? '#ffffff' : '#16181d';
	}
}

/* ======================================================================== */
/* Experts (authors and reviewers)                                          */
/* ======================================================================== */

/**
 * Experts (authors and reviewers) post type and profile fields.
 */


class Etpro_People {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'add_meta_boxes_etp_person', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_etp_person', array( __CLASS__, 'save' ), 10, 2 );
		add_filter( 'manage_etp_person_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_etp_person_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_filter( 'enter_title_here', array( __CLASS__, 'title_placeholder' ), 10, 2 );
		add_filter( 'admin_post_thumbnail_html', array( __CLASS__, 'thumbnail_help' ), 10, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'single_redirects' ) );

		// Keep the team-page map fresh.
		add_action( 'save_post_etp_person', array( __CLASS__, 'flush_map' ), 99 );
		add_action( 'deleted_post', array( __CLASS__, 'flush_map' ) );
		add_action( 'trashed_post', array( __CLASS__, 'flush_map' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'flush_map' ) );

		// Expert URLs that redirect to a team page (or aren't public yet) don't belong in sitemaps.
		add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', array( __CLASS__, 'sitemap_exclude_ids' ) );
		add_filter( 'wp_sitemaps_posts_query_args', array( __CLASS__, 'core_sitemap_args' ), 10, 2 );
		add_filter( 'wpseo_robots', array( __CLASS__, 'noindex_hidden' ) );
	}

	public static function flush_map() {
		delete_transient( 'etp_page_map' );
		Etpro::reset_cache();
	}

	/**
	 * Experts whose own URL is not the canonical profile.
	 */
	public static function non_canonical_ids() {
		$ids = get_posts( array(
			'post_type'      => 'etp_person',
			'post_status'    => 'publish',
			'posts_per_page' => 300,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		) );
		$out = array();
		foreach ( $ids as $id ) {
			$p = Etpro::person( $id );
			if ( ! $p || ! $p['public'] || untrailingslashit( $p['url'] ) !== untrailingslashit( get_permalink( $id ) ) ) {
				$out[] = (int) $id;
			}
		}
		return $out;
	}

	public static function sitemap_exclude_ids( $ids ) {
		return array_merge( (array) $ids, self::non_canonical_ids() );
	}

	public static function core_sitemap_args( $args, $post_type ) {
		if ( 'etp_person' === $post_type ) {
			$args['post__not_in'] = array_merge( isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array(), self::non_canonical_ids() );
		}
		return $args;
	}

	public static function noindex_hidden( $robots ) {
		if ( is_singular( 'etp_person' ) ) {
			$p = Etpro::person( get_queried_object_id() );
			if ( $p && ! $p['public'] ) {
				return 'noindex, nofollow';
			}
		}
		return $robots;
	}

	public static function register_post_type() {
		$base = sanitize_title( Etpro::get( 'profile_base' ) );
		register_post_type( 'etp_person', array(
			'labels'              => array(
				'name'               => __( 'Experts', 'editorial-trust' ),
				'singular_name'      => __( 'Expert', 'editorial-trust' ),
				'add_new'            => __( 'Add expert', 'editorial-trust' ),
				'add_new_item'       => __( 'Add expert', 'editorial-trust' ),
				'edit_item'          => __( 'Edit expert', 'editorial-trust' ),
				'all_items'          => __( 'All experts', 'editorial-trust' ),
				'menu_name'          => __( 'Experts', 'editorial-trust' ),
				'featured_image'     => __( 'Profile photo', 'editorial-trust' ),
				'set_featured_image' => __( 'Set profile photo', 'editorial-trust' ),
				'remove_featured_image' => __( 'Remove profile photo', 'editorial-trust' ),
				'excerpt'            => __( 'Profile summary', 'editorial-trust' ),
				'search_items'       => __( 'Search experts', 'editorial-trust' ),
				'not_found'          => __( 'No experts yet.', 'editorial-trust' ),
				'view_item'          => __( 'View profile', 'editorial-trust' ),
			),
			'description'         => __( 'Authors and reviewers shown on articles.', 'editorial-trust' ),
			'public'              => true,
			'publicly_queryable'  => true,
			'exclude_from_search' => true,
			'show_in_rest'        => true,
			'show_in_nav_menus'   => false,
			'has_archive'         => false,
			'show_in_menu'        => 'etp-dashboard',
			'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ),
			'rewrite'             => array( 'slug' => $base ? $base : 'expert', 'with_front' => false ),
		) );
	}

	public static function title_placeholder( $text, $post ) {
		return 'etp_person' === $post->post_type ? __( 'Full name, e.g. John Doe', 'editorial-trust' ) : $text;
	}

	public static function thumbnail_help( $html, $post_id ) {
		if ( 'etp_person' === get_post_type( $post_id ) ) {
			$html .= '<p class="description">' . esc_html__( 'Square photo, at least 400×400. Leave empty to use the Gravatar email below.', 'editorial-trust' ) . '</p>';
		}
		return $html;
	}

	/**
	 * Existing team pages win over our own profile URL (no duplicate pages).
	 * Hidden (not ready) experts are never shown publicly.
	 */
	public static function single_redirects() {
		if ( ! is_singular( 'etp_person' ) ) {
			return;
		}
		$person = Etpro::person( get_queried_object_id() );
		if ( ! $person ) {
			return;
		}
		if ( ! $person['public'] && ! current_user_can( 'edit_post', $person['id'] ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			return;
		}
		if ( $person['url'] && untrailingslashit( $person['url'] ) !== untrailingslashit( get_permalink( $person['id'] ) ) ) {
			wp_safe_redirect( $person['url'], 301 );
			exit;
		}
	}

	public static function add_meta_boxes() {
		add_meta_box( 'etp_person_details', __( 'Expert details', 'editorial-trust' ), array( __CLASS__, 'render' ), 'etp_person', 'normal', 'high' );
		add_meta_box( 'etp_person_status', __( 'Public display', 'editorial-trust' ), array( __CLASS__, 'render_status' ), 'etp_person', 'side', 'high' );
	}

	public static function render_status( $post ) {
		$public  = '1' === (string) get_post_meta( $post->ID, '_etp_public', true );
		$missing = self::missing_fields( $post->ID );
		?>
		<p>
			<label>
				<input type="checkbox" name="etp_public" value="1" <?php checked( $public ); ?>>
				<strong><?php esc_html_e( 'Ready to show on the website', 'editorial-trust' ); ?></strong>
			</label>
		</p>
		<p class="description"><?php esc_html_e( 'Leave unticked while the photo or details are still being prepared. Articles can be assigned now; this person only appears publicly once ticked and published.', 'editorial-trust' ); ?></p>
		<?php if ( $missing ) : ?>
			<p class="etp-missing"><strong><?php esc_html_e( 'Still missing:', 'editorial-trust' ); ?></strong> <?php echo esc_html( implode( ', ', $missing ) ); ?></p>
		<?php else : ?>
			<p class="etp-complete">&#10003; <?php esc_html_e( 'Profile complete', 'editorial-trust' ); ?></p>
		<?php endif; ?>
		<?php
	}

	public static function missing_fields( $person_id ) {
		$missing = array();
		if ( ! Etpro::person_photo( $person_id ) ) {
			$missing[] = __( 'photo', 'editorial-trust' );
		}
		if ( ! get_post_meta( $person_id, '_etp_job_title', true ) ) {
			$missing[] = __( 'job title', 'editorial-trust' );
		}
		if ( ! trim( (string) get_post_field( 'post_content', $person_id ) ) ) {
			$missing[] = __( 'biography', 'editorial-trust' );
		}
		if ( ! get_post_meta( $person_id, '_etp_expertise', true ) ) {
			$missing[] = __( 'areas of expertise', 'editorial-trust' );
		}
		return $missing;
	}

	private static function field( $post_id, $key ) {
		return (string) get_post_meta( $post_id, '_etp_' . $key, true );
	}

	public static function render( $post ) {
		wp_nonce_field( 'etp_person_save', 'etp_person_nonce' );
		$f     = function ( $k ) use ( $post ) {
			return self::field( $post->ID, $k );
		};
		?>
		<div class="etp-admin etp-person-fields">
			<p class="description"><?php esc_html_e( 'Use the main editor above for the biography and the "Profile summary" box for a one or two sentence intro.', 'editorial-trust' ); ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="etp_short_name"><?php esc_html_e( 'First name / short name', 'editorial-trust' ); ?></label></th>
					<td><input type="text" class="regular-text" id="etp_short_name" name="etp_short_name" value="<?php echo esc_attr( $f( 'short_name' ) ); ?>" placeholder="e.g. John">
						<p class="description"><?php esc_html_e( 'Used in "Written by John" and "Ask John". Defaults to the first word of the name.', 'editorial-trust' ); ?></p></td>
				</tr>
				<tr>
					<th><label for="etp_job_title"><?php esc_html_e( 'Position / job title', 'editorial-trust' ); ?></label></th>
					<td><input type="text" class="regular-text" id="etp_job_title" name="etp_job_title" value="<?php echo esc_attr( $f( 'job_title' ) ); ?>" placeholder="e.g. Founder &amp; Principal Broker"></td>
				</tr>
				<tr>
					<th><label for="etp_credentials"><?php esc_html_e( 'Relevant expertise (one line)', 'editorial-trust' ); ?></label></th>
					<td><input type="text" class="large-text" id="etp_credentials" name="etp_credentials" value="<?php echo esc_attr( $f( 'credentials' ) ); ?>" placeholder="e.g. Credit Representative 000000 · Broker since 2012">
						<p class="description"><?php esc_html_e( 'Shown in the "Why you can trust this article" panel. Falls back to the job title.', 'editorial-trust' ); ?></p></td>
				</tr>
				<tr>
					<th><label for="etp_linkedin"><?php esc_html_e( 'LinkedIn URL', 'editorial-trust' ); ?></label></th>
					<td><input type="url" class="large-text" id="etp_linkedin" name="etp_linkedin" value="<?php echo esc_attr( $f( 'linkedin' ) ); ?>" placeholder="https://www.linkedin.com/in/…">
						<p class="description"><?php esc_html_e( 'LinkedIn is the only social link displayed.', 'editorial-trust' ); ?></p></td>
				</tr>
				<tr>
					<th><label for="etp_gravatar_email"><?php esc_html_e( 'Gravatar email', 'editorial-trust' ); ?></label></th>
					<td><input type="email" class="regular-text" id="etp_gravatar_email" name="etp_gravatar_email" value="<?php echo esc_attr( $f( 'gravatar_email' ) ); ?>" placeholder="e.g. name@proton.me">
						<p class="description"><?php esc_html_e( 'Used only when no profile photo is uploaded. Until the Gravatar account has an image, initials are shown instead of a placeholder face.', 'editorial-trust' ); ?></p></td>
				</tr>
				<tr>
					<th><label for="etp_profile_page_id"><?php esc_html_e( 'Existing team page', 'editorial-trust' ); ?></label></th>
					<td>
						<?php
						$sel_page = (int) $f( 'profile_page_id' );
						$dropdown = wp_dropdown_pages( array(
							'name'              => 'etp_profile_page_id',
							'id'                => 'etp_profile_page_id',
							'selected'          => $sel_page,
							'show_option_none'  => __( '— None: use this expert profile page —', 'editorial-trust' ),
							'option_none_value' => '0',
							'post_status'       => 'publish',
							'sort_column'       => 'menu_order,post_title',
							'echo'              => 0,
						) );
						if ( ! $dropdown ) {
							$dropdown = '<select id="etp_profile_page_id" name="etp_profile_page_id"><option value="0">' . esc_html__( '— None: use this expert profile page —', 'editorial-trust' ) . '</option></select>';
						}
						if ( $sel_page && 'publish' !== get_post_status( $sel_page ) && get_post( $sel_page ) ) {
							$dropdown = str_replace( '</select>', '<option value="' . $sel_page . '" selected="selected">' . esc_html( get_the_title( $sel_page ) . ' (' . get_post_status( $sel_page ) . ')' ) . '</option></select>', $dropdown );
						}
						echo $dropdown; // phpcs:ignore
						?>
						<p class="description"><?php esc_html_e( 'Published pages only, shown under their parent page (e.g. Meet the Team → Luke Kane).', 'editorial-trust' ); ?></p>
						<p class="description"><?php esc_html_e( 'If this person already has a team page, pick it. Author links go there, the Written by / Reviewed by lists are added to it, and this expert URL redirects to it.', 'editorial-trust' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="etp_profile_url"><?php esc_html_e( 'Or a custom profile URL', 'editorial-trust' ); ?></label></th>
					<td><input type="url" class="large-text" id="etp_profile_url" name="etp_profile_url" value="<?php echo esc_attr( $f( 'profile_url' ) ); ?>" placeholder="https://…">
						<input type="hidden" name="etp_profile_new_tab_sent" value="1">
						<p><label><input type="checkbox" name="etp_profile_new_tab" value="1" <?php checked( Etpro::profile_new_tab( $post->ID, (string) $f( 'profile_url' ) ) ); ?>> <?php esc_html_e( 'Open this link in a new tab', 'editorial-trust' ); ?></label></p>
						<p class="description"><?php esc_html_e( 'Only if the profile lives somewhere a page picker cannot reach. Leave empty normally.', 'editorial-trust' ); ?></p></td>
				</tr>
				<tr>
					<th><label for="etp_expertise"><?php esc_html_e( 'Areas of expertise', 'editorial-trust' ); ?></label></th>
					<td><textarea class="large-text" rows="3" id="etp_expertise" name="etp_expertise" placeholder="e.g. First home buyers · Refinancing · Investment lending"><?php echo esc_textarea( $f( 'expertise' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Separate with ·, commas or new lines.', 'editorial-trust' ); ?></p></td>
				</tr>
				<tr>
					<th><label for="etp_qualifications"><?php esc_html_e( 'Qualifications & accreditations', 'editorial-trust' ); ?></label></th>
					<td><textarea class="large-text" rows="4" id="etp_qualifications" name="etp_qualifications" placeholder="e.g. Diploma of Finance and Mortgage Broking Management&#10;MFAA accredited member"><?php echo esc_textarea( $f( 'qualifications' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One per line.', 'editorial-trust' ); ?></p></td>
				</tr>
				<tr>
					<th><label for="etp_experience"><?php esc_html_e( 'Experience', 'editorial-trust' ); ?></label></th>
					<td><textarea class="large-text" rows="5" id="etp_experience" name="etp_experience" placeholder="e.g. 12 years in lending, including 6 years as a credit assessor at a major bank"><?php echo esc_textarea( $f( 'experience' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Plain text or simple HTML. Paragraph breaks are kept.', 'editorial-trust' ); ?></p></td>
				</tr>
			</table>
		</div>
		<?php
	}

	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['etp_person_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['etp_person_nonce'] ) ), 'etp_person_save' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$text = array( 'short_name', 'job_title', 'credentials' );
		foreach ( $text as $k ) {
			update_post_meta( $post_id, '_etp_' . $k, sanitize_text_field( wp_unslash( $_POST[ 'etp_' . $k ] ?? '' ) ) );
		}
		foreach ( array( 'linkedin', 'profile_url' ) as $k ) {
			update_post_meta( $post_id, '_etp_' . $k, esc_url_raw( trim( wp_unslash( $_POST[ 'etp_' . $k ] ?? '' ) ) ) );
		}
		update_post_meta( $post_id, '_etp_gravatar_email', sanitize_email( wp_unslash( $_POST['etp_gravatar_email'] ?? '' ) ) );
		update_post_meta( $post_id, '_etp_profile_page_id', absint( $_POST['etp_profile_page_id'] ?? 0 ) );
		if ( ! empty( $_POST['etp_profile_new_tab_sent'] ) ) {
			update_post_meta( $post_id, '_etp_profile_new_tab', empty( $_POST['etp_profile_new_tab'] ) ? '0' : '1' );
		}
		update_post_meta( $post_id, '_etp_expertise', sanitize_textarea_field( wp_unslash( $_POST['etp_expertise'] ?? '' ) ) );
		update_post_meta( $post_id, '_etp_qualifications', sanitize_textarea_field( wp_unslash( $_POST['etp_qualifications'] ?? '' ) ) );
		update_post_meta( $post_id, '_etp_experience', wp_kses_post( wp_unslash( $_POST['etp_experience'] ?? '' ) ) );
		update_post_meta( $post_id, '_etp_public', empty( $_POST['etp_public'] ) ? '0' : '1' );

		$email = sanitize_email( wp_unslash( $_POST['etp_gravatar_email'] ?? '' ) );
		if ( $email ) {
			delete_transient( 'etp_grav_' . md5( strtolower( trim( $email ) ) ) );
		}
	}

	public static function columns( $cols ) {
		$new = array();
		foreach ( $cols as $k => $v ) {
			if ( 'title' === $k ) {
				$new['etp_photo'] = '';
			}
			$new[ $k ] = $v;
			if ( 'title' === $k ) {
				$new['etp_job']      = __( 'Position', 'editorial-trust' );
				$new['etp_status']   = __( 'On website', 'editorial-trust' );
				$new['etp_written']  = __( 'Written', 'editorial-trust' );
				$new['etp_reviewed'] = __( 'Reviewed', 'editorial-trust' );
				$new['etp_profile']  = __( 'Profile page', 'editorial-trust' );
			}
		}
		unset( $new['date'] );
		return $new;
	}

	public static function column( $col, $post_id ) {
		$p = Etpro::person( $post_id );
		if ( ! $p ) {
			return;
		}
		switch ( $col ) {
			case 'etp_photo':
				echo Etpro::avatar( $p, 36 ); // phpcs:ignore WordPress.Security.EscapeOutput
				break;
			case 'etp_job':
				echo esc_html( $p['job_title'] );
				break;
			case 'etp_status':
				$missing = self::missing_fields( $post_id );
				if ( $p['public'] ) {
					echo '<span class="etp-pill etp-pill--ok">' . esc_html__( 'Showing', 'editorial-trust' ) . '</span>';
				} else {
					echo '<span class="etp-pill etp-pill--hold">' . esc_html__( 'Hidden', 'editorial-trust' ) . '</span>';
				}
				if ( $missing ) {
					echo '<br><small>' . esc_html__( 'Missing:', 'editorial-trust' ) . ' ' . esc_html( implode( ', ', $missing ) ) . '</small>';
				}
				break;
			case 'etp_written':
				echo (int) self::count_articles( $post_id, 'author' );
				break;
			case 'etp_reviewed':
				echo (int) self::count_articles( $post_id, 'reviewer' );
				break;
			case 'etp_profile':
				echo '<a href="' . esc_url( $p['url'] ) . '" target="_blank" rel="noopener">' . esc_html( wp_parse_url( $p['url'], PHP_URL_PATH ) ) . '</a>';
				break;
		}
	}

	public static function count_articles( $person_id, $role ) {
		global $wpdb;
		$keys  = array( 'reviewer' => '_etp_reviewer_id', 'editor' => '_etp_editor_id' );
		$key   = isset( $keys[ $role ] ) ? $keys[ $role ] : '_etp_author_id';
		$types = Etpro::post_types();
		$in    = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders, WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID WHERE m.meta_key = %s AND m.meta_value = %s AND p.post_status = 'publish' AND p.post_type IN ($in)", array_merge( array( $key, (string) (int) $person_id ), $types ) ) );
	}

	/**
	 * All experts for pickers: [id => label].
	 */
	public static function options() {
		$people = get_posts( array(
			'post_type'      => 'etp_person',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => 200,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );
		$out = array();
		foreach ( $people as $p ) {
			$person = Etpro::person( $p->ID );
			$label  = $person['name'] . ( $person['job_title'] ? ' — ' . $person['job_title'] : '' );
			if ( ! $person['public'] ) {
				$label .= ' ' . __( '(hidden until ready)', 'editorial-trust' );
			}
			$out[ $p->ID ] = $label;
		}
		return $out;
	}
}

/* ======================================================================== */
/* Article fields (admin box, list column, bulk action)                     */
/* ======================================================================== */

/**
 * Article fields: authors, reviewers, dates, fact check, sources, update
 * history and CTA. All editable from the post screen, no code needed.
 */


class Etpro_Article_Meta {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ), 10, 2 );
		add_action( 'save_post', array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'save_post', array( __CLASS__, 'refresh_sort_date' ), 30, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'list_hooks' ) );
	}

	public static function assets( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return;
		}
		$is_editor = in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && ( in_array( $screen->post_type, Etpro::post_types(), true ) || 'etp_person' === $screen->post_type );
		$is_ours   = false !== strpos( (string) $screen->id, 'etp-settings' ) || 'edit-etp_person' === $screen->id || ( 'edit' === $screen->base && in_array( $screen->post_type, Etpro::post_types(), true ) );
		if ( ! $is_editor && ! $is_ours ) {
			return;
		}
		wp_register_style( 'etp-admin', false, array(), Etpro::VERSION );
		wp_enqueue_style( 'etp-admin' );
		wp_add_inline_style( 'etp-admin', Etpro_Assets::admin_css() );
		wp_register_script( 'etp-admin', false, array(), Etpro::VERSION, true );
		wp_enqueue_script( 'etp-admin' );
		wp_add_inline_script( 'etp-admin', Etpro_Assets::admin_js() );
		if ( false !== strpos( (string) $screen->id, 'etp-settings' ) ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );
		}
	}

	public static function add_meta_box( $post_type ) {
		if ( ! in_array( $post_type, Etpro::post_types(), true ) ) {
			return;
		}
		add_meta_box( 'etp_article', __( 'Editorial trust: author, reviewer & sources', 'editorial-trust' ), array( __CLASS__, 'render' ), $post_type, 'normal', 'high' );
	}

	/* ------------------------------------------------------------------ */
	/* Render                                                              */
	/* ------------------------------------------------------------------ */

	public static function render( $post ) {
		wp_nonce_field( 'etp_article_save', 'etp_article_nonce' );

		$options   = Etpro_People::options();
		$is_new    = ! metadata_exists( 'post', $post->ID, '_etp_authors' );
		$authors   = get_post_meta( $post->ID, '_etp_authors', true );
		$reviewers = get_post_meta( $post->ID, '_etp_reviewers', true );
		$editors   = get_post_meta( $post->ID, '_etp_editors', true );
		$authors   = is_array( $authors ) ? array_map( 'intval', $authors ) : array();
		$reviewers = is_array( $reviewers ) ? array_map( 'intval', $reviewers ) : array();
		$editors   = is_array( $editors ) ? array_map( 'intval', $editors ) : array();
		if ( $is_new ) {
			$authors   = array_filter( array( (int) Etpro::get( 'default_author' ) ) );
			$reviewers = array_filter( array( (int) Etpro::get( 'default_reviewer' ) ) );
			$editors   = array_filter( array( (int) Etpro::get( 'default_editor' ) ) );
		}
		$primary_editor   = $editors ? (int) $editors[0] : 0;
		$extra_editors    = array_slice( $editors, 1 );
		$primary_author   = $authors ? (int) $authors[0] : 0;
		$primary_reviewer = $reviewers ? (int) $reviewers[0] : 0;
		$extra_authors    = array_slice( $authors, 1 );
		$extra_reviewers  = array_slice( $reviewers, 1 );

		$fc_raw   = get_post_meta( $post->ID, '_etp_fact_checked', true );
		$fc       = '' === $fc_raw ? (bool) Etpro::get( 'default_fact_check' ) : '1' === (string) $fc_raw;
		$m        = function ( $k ) use ( $post ) {
			return (string) get_post_meta( $post->ID, '_etp_' . $k, true );
		};
		$sources  = get_post_meta( $post->ID, '_etp_sources', true );
		$sources  = is_array( $sources ) ? $sources : array();
		$history  = get_post_meta( $post->ID, '_etp_history', true );
		$history  = is_array( $history ) ? $history : array();
		$settings = Etpro::settings();

		$hidden_assigned = array();
		foreach ( array_merge( $authors, $editors, $reviewers ) as $pid ) {
			$p = Etpro::person( $pid );
			if ( $p && ! $p['public'] ) {
				$hidden_assigned[] = $p['name'];
			}
		}
		?>
		<div class="etp-admin etp-article">
			<?php if ( ! $options ) : ?>
				<div class="notice notice-warning inline"><p>
					<?php
					printf(
						/* translators: %s: link to add an expert */
						esc_html__( 'No experts yet. %s first, then assign them here.', 'editorial-trust' ),
						'<a href="' . esc_url( admin_url( 'post-new.php?post_type=etp_person' ) ) . '">' . esc_html__( 'Add an expert', 'editorial-trust' ) . '</a>'
					);
					?>
				</p></div>
			<?php endif; ?>

			<nav class="etp-tabs" role="tablist">
				<button type="button" class="etp-tab is-active" data-tab="people"><?php esc_html_e( 'Author, editor & reviewer', 'editorial-trust' ); ?></button>
				<button type="button" class="etp-tab" data-tab="factcheck"><?php esc_html_e( 'Fact check', 'editorial-trust' ); ?></button>
				<button type="button" class="etp-tab" data-tab="sources"><?php esc_html_e( 'Sources', 'editorial-trust' ); ?> <span class="etp-count"><?php echo (int) count( $sources ); ?></span></button>
				<button type="button" class="etp-tab" data-tab="history"><?php esc_html_e( 'Update history', 'editorial-trust' ); ?> <span class="etp-count"><?php echo (int) count( $history ); ?></span></button>
				<button type="button" class="etp-tab" data-tab="cta"><?php esc_html_e( 'CTA & display', 'editorial-trust' ); ?></button>
			</nav>

			<!-- People & dates -->
			<section class="etp-panel is-active" data-panel="people">
				<?php if ( $hidden_assigned ) : ?>
					<p class="etp-note"><?php echo esc_html( sprintf( /* translators: %s: names */ __( 'Not shown on the site yet (profile not marked ready): %s. The relationship is saved and will appear automatically once ready.', 'editorial-trust' ), implode( ', ', $hidden_assigned ) ) ); ?></p>
				<?php endif; ?>
				<div class="etp-grid">
					<p>
						<label for="etp_author"><strong><?php esc_html_e( 'Primary author (Author 1)', 'editorial-trust' ); ?></strong></label>
						<?php self::person_select( 'etp_author', $options, $primary_author ); ?>
					</p>
					<p>
						<label for="etp_editor"><strong><?php esc_html_e( 'Editor (Edited by)', 'editorial-trust' ); ?></strong></label>
						<?php self::person_select( 'etp_editor', $options, $primary_editor ); ?>
					</p>
					<p>
						<label for="etp_reviewer"><strong><?php esc_html_e( 'Reviewer (Author 2)', 'editorial-trust' ); ?></strong></label>
						<?php self::person_select( 'etp_reviewer', $options, $primary_reviewer ); ?>
					</p>
					<p>
						<label for="etp_updated_date"><strong><?php esc_html_e( 'Last updated', 'editorial-trust' ); ?></strong></label>
						<span class="etp-date-row">
							<input type="date" id="etp_updated_date" name="etp_updated_date" value="<?php echo esc_attr( $m( 'updated_date' ) ); ?>">
							<button type="button" class="button etp-today" data-target="etp_updated_date"><?php esc_html_e( 'Today', 'editorial-trust' ); ?></button>
						</span>
						<span class="description"><?php echo esc_html( 'modified' === $settings['updated_fallback'] ? __( 'Set this for meaningful content updates. Empty = WordPress last-modified date.', 'editorial-trust' ) : __( 'Set this for meaningful content updates. Empty = published date.', 'editorial-trust' ) ); ?></span>
					</p>
					<p>
						<label for="etp_reviewed_date"><strong><?php esc_html_e( 'Last reviewed', 'editorial-trust' ); ?></strong></label>
						<span class="etp-date-row">
							<input type="date" id="etp_reviewed_date" name="etp_reviewed_date" value="<?php echo esc_attr( $m( 'reviewed_date' ) ); ?>">
							<button type="button" class="button etp-today" data-target="etp_reviewed_date"><?php esc_html_e( 'Today', 'editorial-trust' ); ?></button>
						</span>
						<span class="description"><?php esc_html_e( 'Date the reviewer last checked the article.', 'editorial-trust' ); ?></span>
					</p>
				</div>

				<details class="etp-more" <?php echo ( $extra_authors || $extra_editors || $extra_reviewers ) ? 'open' : ''; ?>>
					<summary><?php esc_html_e( 'Additional authors, editors and reviewers', 'editorial-trust' ); ?></summary>
					<div class="etp-grid">
						<fieldset>
							<legend><?php esc_html_e( 'Additional authors', 'editorial-trust' ); ?></legend>
							<?php self::person_checklist( 'etp_extra_authors', $options, $extra_authors ); ?>
						</fieldset>
						<fieldset>
							<legend><?php esc_html_e( 'Additional editors', 'editorial-trust' ); ?></legend>
							<?php self::person_checklist( 'etp_extra_editors', $options, $extra_editors ); ?>
						</fieldset>
						<fieldset>
							<legend><?php esc_html_e( 'Additional reviewers', 'editorial-trust' ); ?></legend>
							<?php self::person_checklist( 'etp_extra_reviewers', $options, $extra_reviewers ); ?>
						</fieldset>
					</div>
					<p class="description"><?php esc_html_e( 'The primary author, editor and reviewer show in the bar at the top. Everyone ticked here is listed in the "Why you can trust this article" panel, in the structured data and on their profile page.', 'editorial-trust' ); ?></p>
				</details>
			</section>

			<!-- Fact check -->
			<section class="etp-panel" data-panel="factcheck" hidden>
				<p>
					<label><input type="checkbox" name="etp_fact_checked" value="1" <?php checked( $fc ); ?>> <strong><?php esc_html_e( 'Fact checked', 'editorial-trust' ); ?></strong></label><br>
					<span class="description"><?php esc_html_e( 'Shows the "Fact checked" badge and its "Why you can trust this article" panel. Only tick this once the reviewer has actually checked the article.', 'editorial-trust' ); ?></span>
				</p>
				<p>
					<label for="etp_fc_summary"><strong><?php esc_html_e( 'Fact check explanation (optional)', 'editorial-trust' ); ?></strong></label>
					<textarea class="large-text" rows="2" id="etp_fc_summary" name="etp_fc_summary" placeholder="<?php echo esc_attr( $settings['fc_summary'] ); ?>"><?php echo esc_textarea( $m( 'fc_summary' ) ); ?></textarea>
					<span class="description"><?php esc_html_e( 'Leave empty to use the site default. Tokens: {reviewer}, {author}, {site}.', 'editorial-trust' ); ?></span>
				</p>
				<p>
					<label for="etp_fc_items"><strong><?php esc_html_e( 'What we checked (one per line, optional)', 'editorial-trust' ); ?></strong></label>
					<textarea class="large-text" rows="4" id="etp_fc_items" name="etp_fc_items" placeholder="<?php echo esc_attr( $settings['fc_items'] ); ?>"><?php echo esc_textarea( $m( 'fc_items' ) ); ?></textarea>
					<span class="description"><?php esc_html_e( 'Leave empty to use the site default list (Experts → Settings).', 'editorial-trust' ); ?></span>
				</p>
			</section>

			<!-- Sources -->
			<section class="etp-panel" data-panel="sources" hidden>
				<p class="description"><?php esc_html_e( 'Only add sources that genuinely support this article: ASIC, AFCA, Moneysmart, government scheme pages, lender documentation, MFAA/FBAA and similar. The box is hidden when there are no sources.', 'editorial-trust' ); ?></p>
				<div class="etp-repeater" data-name="etp_sources">
					<div class="etp-rows">
						<?php
						foreach ( $sources as $i => $s ) {
							self::source_row( $i, $s );
						}
						?>
					</div>
					<template class="etp-row-template"><?php self::source_row( '__i__', array() ); ?></template>
					<button type="button" class="button etp-add-row"><?php esc_html_e( '+ Add source', 'editorial-trust' ); ?></button>
				</div>
				<div class="etp-grid">
					<p>
						<label for="etp_src_position"><strong><?php esc_html_e( 'Placement', 'editorial-trust' ); ?></strong></label>
						<select id="etp_src_position" name="etp_src_position">
							<option value="" <?php selected( $m( 'src_position' ), '' ); ?>><?php esc_html_e( 'Automatic: below the FAQ, otherwise end of article', 'editorial-trust' ); ?></option>
							<option value="end" <?php selected( $m( 'src_position' ), 'end' ); ?>><?php esc_html_e( 'End of article content', 'editorial-trust' ); ?></option>
							<option value="manual" <?php selected( $m( 'src_position' ), 'manual' ); ?>><?php esc_html_e( 'Manual: where I put [etp_sources]', 'editorial-trust' ); ?></option>
							<option value="off" <?php selected( $m( 'src_position' ), 'off' ); ?>><?php esc_html_e( 'Hide on this article', 'editorial-trust' ); ?></option>
						</select>
					</p>
					<p>
						<label for="etp_src_intro"><strong><?php esc_html_e( 'Intro sentence (optional)', 'editorial-trust' ); ?></strong></label>
						<input type="text" class="large-text" id="etp_src_intro" name="etp_src_intro" value="<?php echo esc_attr( $m( 'src_intro' ) ); ?>" placeholder="<?php echo esc_attr( $settings['src_intro'] ); ?>">
					</p>
				</div>
				<p>
					<label for="etp_src_disclaimer"><strong><?php esc_html_e( 'Disclaimer under the sources (optional)', 'editorial-trust' ); ?></strong></label>
					<textarea class="large-text" rows="2" id="etp_src_disclaimer" name="etp_src_disclaimer" placeholder="<?php echo esc_attr( $settings['src_disclaimer'] ); ?>"><?php echo esc_textarea( $m( 'src_disclaimer' ) ); ?></textarea>
					<label><input type="checkbox" name="etp_hide_disclaimer" value="1" <?php checked( $m( 'hide_disclaimer' ), '1' ); ?>> <?php esc_html_e( 'No disclaimer on this article (e.g. the template already shows one)', 'editorial-trust' ); ?></label>
				</p>
			</section>

			<!-- History -->
			<section class="etp-panel" data-panel="history" hidden>
				<p><label><input type="checkbox" name="etp_show_history" value="1" <?php checked( $m( 'show_history' ), '1' ); ?>> <strong><?php esc_html_e( 'Show "Article update history" on this article', 'editorial-trust' ); ?></strong></label></p>
				<p class="description"><?php esc_html_e( 'Only record meaningful content updates — not typo fixes or small edits. Newest entries are shown first automatically.', 'editorial-trust' ); ?></p>
				<div class="etp-repeater" data-name="etp_history">
					<div class="etp-rows">
						<?php
						foreach ( $history as $i => $h ) {
							self::history_row( $i, $h );
						}
						?>
					</div>
					<template class="etp-row-template"><?php self::history_row( '__i__', array() ); ?></template>
					<button type="button" class="button etp-add-row"><?php esc_html_e( '+ Add update', 'editorial-trust' ); ?></button>
				</div>
			</section>

			<!-- CTA & display -->
			<section class="etp-panel" data-panel="cta" hidden>
				<div class="etp-grid">
					<p>
						<label for="etp_cta_mode"><strong><?php esc_html_e( '"Ask the expert" link', 'editorial-trust' ); ?></strong></label>
						<select id="etp_cta_mode" name="etp_cta_mode">
							<option value="" <?php selected( $m( 'cta_mode' ), '' ); ?>><?php esc_html_e( 'Site default', 'editorial-trust' ); ?></option>
							<option value="custom" <?php selected( $m( 'cta_mode' ), 'custom' ); ?>><?php esc_html_e( 'Custom wording below', 'editorial-trust' ); ?></option>
							<option value="off" <?php selected( $m( 'cta_mode' ), 'off' ); ?>><?php esc_html_e( 'Hide on this article', 'editorial-trust' ); ?></option>
						</select>
					</p>
					<p>
						<label for="etp_cta_person"><strong><?php esc_html_e( 'Expert to ask', 'editorial-trust' ); ?></strong></label>
						<?php self::person_select( 'etp_cta_person', $options, (int) $m( 'cta_person' ), __( '— Primary author —', 'editorial-trust' ) ); ?>
					</p>
					<p>
						<label for="etp_cta_question"><strong><?php esc_html_e( 'Question text', 'editorial-trust' ); ?></strong></label>
						<input type="text" class="large-text" id="etp_cta_question" name="etp_cta_question" value="<?php echo esc_attr( $m( 'cta_question' ) ); ?>" placeholder="<?php echo esc_attr( $settings['cta_question'] ); ?>">
					</p>
					<p>
						<label for="etp_cta_link_text"><strong><?php esc_html_e( 'Link text', 'editorial-trust' ); ?></strong></label>
						<input type="text" class="large-text" id="etp_cta_link_text" name="etp_cta_link_text" value="<?php echo esc_attr( $m( 'cta_link_text' ) ); ?>" placeholder="<?php echo esc_attr( $settings['cta_link_text'] ); ?>">
						<span class="description"><?php esc_html_e( 'Tokens: {first_name}, {name}.', 'editorial-trust' ); ?></span>
					</p>
				</div>
				<p><label><input type="checkbox" name="etp_hide_trust_bar" value="1" <?php checked( $m( 'hide_trust_bar' ), '1' ); ?>> <?php esc_html_e( 'Hide the author / reviewer bar on this article', 'editorial-trust' ); ?></label></p>
			</section>
		</div>
		<?php
	}

	private static function person_select( $name, $options, $selected, $none = null ) {
		$none = $none ? $none : __( '— None —', 'editorial-trust' );
		echo '<select class="widefat" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '">';
		echo '<option value="0">' . esc_html( $none ) . '</option>';
		foreach ( $options as $id => $label ) {
			echo '<option value="' . (int) $id . '"' . selected( (int) $selected, (int) $id, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	private static function person_checklist( $name, $options, $selected ) {
		if ( ! $options ) {
			echo '<p class="description">' . esc_html__( 'No experts yet.', 'editorial-trust' ) . '</p>';
			return;
		}
		echo '<div class="etp-checklist">';
		foreach ( $options as $id => $label ) {
			printf(
				'<label><input type="checkbox" name="%1$s[]" value="%2$d"%3$s> %4$s</label>',
				esc_attr( $name ),
				(int) $id,
				checked( in_array( (int) $id, $selected, true ), true, false ),
				esc_html( $label )
			);
		}
		echo '</div>';
	}

	private static function source_row( $i, $s ) {
		$title = isset( $s['title'] ) ? $s['title'] : '';
		$url   = isset( $s['url'] ) ? $s['url'] : '';
		?>
		<div class="etp-row">
			<span class="etp-handle" aria-hidden="true">⋮⋮</span>
			<input type="text" name="etp_sources[<?php echo esc_attr( $i ); ?>][title]" value="<?php echo esc_attr( $title ); ?>" placeholder="<?php esc_attr_e( 'Link text, e.g. ASIC: Mortgage brokers best interests duty (RG 273)', 'editorial-trust' ); ?>">
			<input type="url" name="etp_sources[<?php echo esc_attr( $i ); ?>][url]" value="<?php echo esc_attr( $url ); ?>" placeholder="https://">
			<span class="etp-row-actions">
				<button type="button" class="button-link etp-up" aria-label="<?php esc_attr_e( 'Move up', 'editorial-trust' ); ?>">↑</button>
				<button type="button" class="button-link etp-down" aria-label="<?php esc_attr_e( 'Move down', 'editorial-trust' ); ?>">↓</button>
				<button type="button" class="button-link etp-remove" aria-label="<?php esc_attr_e( 'Remove', 'editorial-trust' ); ?>">✕</button>
			</span>
		</div>
		<?php
	}

	private static function history_row( $i, $h ) {
		$date = isset( $h['date'] ) ? $h['date'] : '';
		$note = isset( $h['note'] ) ? $h['note'] : '';
		?>
		<div class="etp-row etp-row--history">
			<input type="date" name="etp_history[<?php echo esc_attr( $i ); ?>][date]" value="<?php echo esc_attr( $date ); ?>">
			<input type="text" name="etp_history[<?php echo esc_attr( $i ); ?>][note]" value="<?php echo esc_attr( $note ); ?>" placeholder="<?php esc_attr_e( 'e.g. Reviewed lending criteria and updated examples', 'editorial-trust' ); ?>">
			<span class="etp-row-actions">
				<button type="button" class="button-link etp-remove" aria-label="<?php esc_attr_e( 'Remove', 'editorial-trust' ); ?>">✕</button>
			</span>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* Save                                                                */
	/* ------------------------------------------------------------------ */

	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['etp_article_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['etp_article_nonce'] ) ), 'etp_article_save' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! in_array( $post->post_type, Etpro::post_types(), true ) ) {
			return;
		}

		$ids = function ( $key ) {
			$raw = isset( $_POST[ $key ] ) ? (array) wp_unslash( $_POST[ $key ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			return array_values( array_filter( array_map( 'absint', $raw ) ) );
		};

		$authors   = array_merge( array( absint( $_POST['etp_author'] ?? 0 ) ), $ids( 'etp_extra_authors' ) );
		$reviewers = array_merge( array( absint( $_POST['etp_reviewer'] ?? 0 ) ), $ids( 'etp_extra_reviewers' ) );

		$editors   = array_merge( array( absint( $_POST['etp_editor'] ?? 0 ) ), $ids( 'etp_extra_editors' ) );

		self::set_people( $post_id, $authors, $reviewers, $editors );

		update_post_meta( $post_id, '_etp_updated_date', Etpro::valid_ymd( wp_unslash( $_POST['etp_updated_date'] ?? '' ) ) );
		update_post_meta( $post_id, '_etp_reviewed_date', Etpro::valid_ymd( wp_unslash( $_POST['etp_reviewed_date'] ?? '' ) ) );
		update_post_meta( $post_id, '_etp_fact_checked', empty( $_POST['etp_fact_checked'] ) ? '0' : '1' );
		update_post_meta( $post_id, '_etp_fc_summary', sanitize_textarea_field( wp_unslash( $_POST['etp_fc_summary'] ?? '' ) ) );
		update_post_meta( $post_id, '_etp_fc_items', sanitize_textarea_field( wp_unslash( $_POST['etp_fc_items'] ?? '' ) ) );

		// Sources.
		$sources = array();
		$raw     = isset( $_POST['etp_sources'] ) && is_array( $_POST['etp_sources'] ) ? wp_unslash( $_POST['etp_sources'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		foreach ( $raw as $row ) {
			$title = sanitize_text_field( $row['title'] ?? '' );
			$url   = esc_url_raw( trim( $row['url'] ?? '' ), array( 'http', 'https' ) );
			if ( $title && $url ) {
				$sources[] = array( 'title' => $title, 'url' => $url );
			}
		}
		update_post_meta( $post_id, '_etp_sources', $sources );

		$pos = sanitize_key( wp_unslash( $_POST['etp_src_position'] ?? '' ) );
		update_post_meta( $post_id, '_etp_src_position', in_array( $pos, array( 'end', 'manual', 'off' ), true ) ? $pos : '' );
		update_post_meta( $post_id, '_etp_src_intro', sanitize_text_field( wp_unslash( $_POST['etp_src_intro'] ?? '' ) ) );
		update_post_meta( $post_id, '_etp_src_disclaimer', sanitize_textarea_field( wp_unslash( $_POST['etp_src_disclaimer'] ?? '' ) ) );
		update_post_meta( $post_id, '_etp_hide_disclaimer', empty( $_POST['etp_hide_disclaimer'] ) ? '0' : '1' );

		// History.
		$history = array();
		$raw     = isset( $_POST['etp_history'] ) && is_array( $_POST['etp_history'] ) ? wp_unslash( $_POST['etp_history'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		foreach ( $raw as $row ) {
			$date = Etpro::valid_ymd( $row['date'] ?? '' );
			$note = sanitize_text_field( $row['note'] ?? '' );
			if ( $date && $note ) {
				$history[] = array( 'date' => $date, 'note' => $note );
			}
		}
		update_post_meta( $post_id, '_etp_history', $history );
		update_post_meta( $post_id, '_etp_show_history', empty( $_POST['etp_show_history'] ) ? '0' : '1' );

		// CTA & display.
		$mode = sanitize_key( wp_unslash( $_POST['etp_cta_mode'] ?? '' ) );
		update_post_meta( $post_id, '_etp_cta_mode', in_array( $mode, array( 'custom', 'off' ), true ) ? $mode : '' );
		update_post_meta( $post_id, '_etp_cta_person', absint( $_POST['etp_cta_person'] ?? 0 ) );
		update_post_meta( $post_id, '_etp_cta_question', sanitize_text_field( wp_unslash( $_POST['etp_cta_question'] ?? '' ) ) );
		update_post_meta( $post_id, '_etp_cta_link_text', sanitize_text_field( wp_unslash( $_POST['etp_cta_link_text'] ?? '' ) ) );
		update_post_meta( $post_id, '_etp_hide_trust_bar', empty( $_POST['etp_hide_trust_bar'] ) ? '0' : '1' );

		self::refresh_sort_date( $post_id, $post );
	}

	/**
	 * Store the ordered lists plus one queryable row per person, so
	 * "Written by" / "Reviewed by" lists are a simple, exact meta query.
	 */
	public static function set_people( $post_id, array $authors, array $reviewers, $editors = null ) {
		$clean = function ( $list ) {
			$list = array_values( array_unique( array_filter( array_map( 'intval', $list ) ) ) );
			return array_values( array_filter( $list, function ( $id ) {
				return 'etp_person' === get_post_type( $id );
			} ) );
		};
		if ( null === $editors ) {
			$editors = get_post_meta( $post_id, '_etp_editors', true );
			$editors = is_array( $editors ) ? $editors : array();
		}
		$authors   = $clean( $authors );
		$reviewers = $clean( $reviewers );
		$editors   = $clean( (array) $editors );

		update_post_meta( $post_id, '_etp_authors', $authors );
		update_post_meta( $post_id, '_etp_reviewers', $reviewers );
		update_post_meta( $post_id, '_etp_editors', $editors );
		delete_post_meta( $post_id, '_etp_editor_id' );
		foreach ( $editors as $id ) {
			add_post_meta( $post_id, '_etp_editor_id', $id );
		}

		delete_post_meta( $post_id, '_etp_author_id' );
		delete_post_meta( $post_id, '_etp_reviewer_id' );
		foreach ( $authors as $id ) {
			add_post_meta( $post_id, '_etp_author_id', $id );
		}
		foreach ( $reviewers as $id ) {
			add_post_meta( $post_id, '_etp_reviewer_id', $id );
		}
		self::refresh_sort_date( $post_id );
	}

	/**
	 * Date the profile lists sort by: the editor-set "Last updated" date,
	 * falling back the same way the front end does.
	 */
	public static function refresh_sort_date( $post_id, $post = null ) {
		$post = $post ? $post : get_post( $post_id );
		if ( ! $post || wp_is_post_revision( $post_id ) || ! in_array( $post->post_type, Etpro::post_types(), true ) ) {
			return;
		}
		$updated = Etpro::valid_ymd( get_post_meta( $post_id, '_etp_updated_date', true ) );
		if ( $updated ) {
			$sort = $updated . ' 00:00:00';
		} elseif ( 'published' === Etpro::get( 'updated_fallback' ) ) {
			$sort = $post->post_date;
		} else {
			$sort = $post->post_modified;
		}
		update_post_meta( $post_id, '_etp_sort_date', $sort );
	}

	/* ------------------------------------------------------------------ */
	/* Posts list: column + bulk action                                    */
	/* ------------------------------------------------------------------ */

	public static function list_hooks() {
		foreach ( Etpro::post_types() as $type ) {
			add_filter( "manage_{$type}_posts_columns", array( __CLASS__, 'list_column' ) );
			add_action( "manage_{$type}_posts_custom_column", array( __CLASS__, 'list_column_value' ), 10, 2 );
			add_filter( "bulk_actions-edit-{$type}", array( __CLASS__, 'bulk_actions' ) );
			add_filter( "handle_bulk_actions-edit-{$type}", array( __CLASS__, 'handle_bulk' ), 10, 3 );
		}
		add_action( 'admin_notices', array( __CLASS__, 'bulk_notice' ) );
	}

	public static function list_column( $cols ) {
		$cols['etp_editorial'] = __( 'Author / reviewer', 'editorial-trust' );
		return $cols;
	}

	public static function list_column_value( $col, $post_id ) {
		if ( 'etp_editorial' !== $col ) {
			return;
		}
		$a = Etpro::article_people( $post_id, 'author', true );
		$r = Etpro::article_people( $post_id, 'reviewer', true );
		if ( ! $a && ! $r && ! Etpro::article_people( $post_id, 'editor', true ) ) {
			echo '<span class="etp-muted">—</span>';
			return;
		}
		$names = function ( $people ) {
			return implode( ', ', wp_list_pluck( $people, 'first_name' ) );
		};
		echo '<span title="' . esc_attr__( 'Author', 'editorial-trust' ) . '">✍ ' . esc_html( $a ? $names( $a ) : '—' ) . '</span><br>';
		$e = Etpro::article_people( $post_id, 'editor', true );
		if ( $e ) {
			echo '<span title="' . esc_attr__( 'Editor', 'editorial-trust' ) . '">✎ ' . esc_html( $names( $e ) ) . '</span><br>';
		}
		echo '<span title="' . esc_attr__( 'Reviewer', 'editorial-trust' ) . '">✔ ' . esc_html( $r ? $names( $r ) : '—' ) . '</span>';
		$sources = get_post_meta( $post_id, '_etp_sources', true );
		$n       = is_array( $sources ) ? count( $sources ) : 0;
		echo '<br><small class="etp-muted">' . esc_html( sprintf( /* translators: %d sources */ _n( '%d source', '%d sources', $n, 'editorial-trust' ), $n ) ) . ( '1' === (string) get_post_meta( $post_id, '_etp_fact_checked', true ) ? ' · ' . esc_html__( 'fact checked', 'editorial-trust' ) : '' ) . '</small>';
	}

	public static function bulk_actions( $actions ) {
		if ( Etpro::get( 'default_author' ) ) {
			$actions['etp_assign_defaults'] = __( 'Assign default author & reviewer', 'editorial-trust' );
		}
		return $actions;
	}

	public static function handle_bulk( $redirect, $action, $ids ) {
		if ( 'etp_assign_defaults' !== $action ) {
			return $redirect;
		}
		$n = 0;
		foreach ( $ids as $id ) {
			if ( current_user_can( 'edit_post', $id ) ) {
				self::assign_defaults( $id, true );
				$n++;
			}
		}
		return add_query_arg( 'etp_assigned', $n, $redirect );
	}

	public static function bulk_notice() {
		if ( isset( $_GET['etp_assigned'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$n = absint( $_GET['etp_assigned'] ); // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( /* translators: %d posts */ _n( 'Default author and reviewer assigned to %d article.', 'Default author and reviewer assigned to %d articles.', $n, 'editorial-trust' ), $n ) ) . '</p></div>';
		}
	}

	/**
	 * Apply the site's default author + reviewer to one article.
	 *
	 * @param bool $overwrite Replace an existing primary author/reviewer.
	 * @param bool $fact_checked Also mark as fact checked.
	 * @param string $reviewed_date Optional Y-m-d.
	 */
	public static function assign_defaults( $post_id, $overwrite = false, $fact_checked = null, $reviewed_date = '' ) {
		$def_a     = (int) Etpro::get( 'default_author' );
		$def_r     = (int) Etpro::get( 'default_reviewer' );
		$authors   = get_post_meta( $post_id, '_etp_authors', true );
		$reviewers = get_post_meta( $post_id, '_etp_reviewers', true );
		$authors   = is_array( $authors ) ? $authors : array();
		$reviewers = is_array( $reviewers ) ? $reviewers : array();

		if ( $def_a && ( $overwrite || ! $authors ) ) {
			$authors = array_merge( array( $def_a ), array_diff( array_slice( $authors, 1 ), array( $def_a ) ) );
		}
		if ( $def_r && ( $overwrite || ! $reviewers ) ) {
			$reviewers = array_merge( array( $def_r ), array_diff( array_slice( $reviewers, 1 ), array( $def_r ) ) );
		}
		$def_e   = (int) Etpro::get( 'default_editor' );
		$editors = get_post_meta( $post_id, '_etp_editors', true );
		$editors = is_array( $editors ) ? $editors : array();
		if ( $def_e && ( $overwrite || ! $editors ) ) {
			$editors = array_merge( array( $def_e ), array_diff( array_slice( $editors, 1 ), array( $def_e ) ) );
		}
		self::set_people( $post_id, $authors, $reviewers, $editors );

		if ( null === $fact_checked ) {
			if ( '' === (string) get_post_meta( $post_id, '_etp_fact_checked', true ) ) {
				update_post_meta( $post_id, '_etp_fact_checked', Etpro::get( 'default_fact_check' ) ? '1' : '0' );
			}
		} else {
			update_post_meta( $post_id, '_etp_fact_checked', $fact_checked ? '1' : '0' );
		}
		if ( $reviewed_date ) {
			update_post_meta( $post_id, '_etp_reviewed_date', Etpro::valid_ymd( $reviewed_date ) );
		}
	}
}

/* ======================================================================== */
/* Experts → Settings                                                       */
/* ======================================================================== */

/**
 * Experts → Settings: site-wide labels, defaults, placement, CTA, schema
 * and the bulk-assign tool.
 */


class Etpro_Settings {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_post_etp_bulk_assign', array( __CLASS__, 'bulk_assign' ) );
	}

	public static function menu() {
		// Menus now live in Etpro_Admin (Editorial Trust top-level menu).
	}

	public static function register() {
		register_setting( 'etp_settings_group', 'etp_settings', array(
			'type'              => 'array',
			'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			'default'           => Etpro::defaults(),
		) );
	}

	/**
	 * Field types used by the sanitiser.
	 */
	private static function types() {
		return array(
			'post_types'          => 'post_types',
			'default_author'      => 'int',
			'default_reviewer'    => 'int',
			'default_editor'      => 'int',
			'default_fact_check'  => 'bool',
			'date_format'         => 'text',
			'history_date_format' => 'text',
			'updated_fallback'    => array( 'modified', 'published' ),
			'auto_trust_bar'      => 'bool',
			'trust_bar_style'     => array( 'light', 'dark' ),
			'trust_bar_card'      => 'bool',
			'auto_sources'        => 'bool',
			'auto_author_box'     => 'bool',
			'author_box_heading'  => 'text',
			'published_label'     => 'bool',
			'custom_css'          => 'css',
			'cta_position'        => array( 'after_trust', 'after_sources', 'end', 'off' ),
			'profile_auto_append' => 'bool',
			'team_page_sections'  => 'sections',
			'accent_color'        => 'color',
			'content_max_width'   => 'css_length',
			'load_everywhere'     => 'bool',
			'fc_label'            => 'text',
			'fc_hint'             => 'text',
			'fc_kicker'           => 'text',
			'fc_heading'          => 'text',
			'fc_summary'          => 'textarea',
			'fc_items'            => 'textarea',
			'fc_items_heading'    => 'text',
			'fc_bold_leadins'     => 'bool',
			'fc_guidelines_url'   => 'url',
			'fc_guidelines_label' => 'text',
			'src_eyebrow'         => 'text',
			'src_heading'         => 'text',
			'src_intro'           => 'text',
			'src_list_heading'    => 'text',
			'src_disclaimer'      => 'textarea',
			'src_new_tab'         => 'bool',
			'history_heading'     => 'text',
			'cta_question'        => 'text',
			'cta_link_text'       => 'text',
			'cta_url'             => 'url',
			'cta_mode'            => array( 'link', 'popup' ),
			'cta_popup_selector'  => 'text',
			'content_selector'    => 'text',
			'cta_param_article'   => 'key',
			'cta_param_expert'    => 'key',
			'profile_base'        => 'slug',
			'written_eyebrow'     => 'text',
			'written_heading'     => 'text',
			'reviewed_eyebrow'    => 'text',
			'reviewed_heading'    => 'text',
			'edited_eyebrow'      => 'text',
			'edited_heading'      => 'text',
			'list_limit'          => 'int',
			'expertise_heading'   => 'text',
			'profile_cta_text'    => 'text',
			'schema_mode'         => array( 'auto', 'standalone', 'off' ),
			'org_name'            => 'text',
			'org_logo'            => 'url',
		);
	}

	public static function sanitize( $input ) {
		$defaults = Etpro::defaults();
		$old      = get_option( 'etp_settings', array() );
		$out      = array();
		$input    = is_array( $input ) ? $input : array();
		$section  = isset( $input['_section'] ) ? (string) $input['_section'] : 'all';
		if ( in_array( $section, array( 'settings', 'skin' ), true ) ) {
			$prev = wp_parse_args( is_array( $old ) ? $old : array(), $defaults );
			$skin = Etpro_Admin::skin_keys();
			foreach ( array_keys( self::types() ) as $k ) {
				if ( ( 'skin' === $section ) !== in_array( $k, $skin, true ) ) {
					$input[ $k ] = $prev[ $k ] ?? null;
				}
			}
		}

		foreach ( self::types() as $key => $type ) {
			$v = isset( $input[ $key ] ) ? $input[ $key ] : null;
			if ( is_array( $type ) ) {
				$out[ $key ] = in_array( $v, $type, true ) ? $v : $defaults[ $key ];
				continue;
			}
			switch ( $type ) {
				case 'bool':
					$out[ $key ] = empty( $v ) ? 0 : 1;
					break;
				case 'int':
					$out[ $key ] = absint( $v );
					break;
				case 'text':
					$out[ $key ] = sanitize_text_field( (string) $v );
					break;
				case 'textarea':
					$out[ $key ] = sanitize_textarea_field( (string) $v );
					break;
				case 'url':
					$out[ $key ] = esc_url_raw( trim( (string) $v ) );
					break;
				case 'key':
					$out[ $key ] = sanitize_key( (string) $v ) ? sanitize_key( (string) $v ) : $defaults[ $key ];
					break;
				case 'slug':
					$out[ $key ] = sanitize_title( (string) $v ) ? sanitize_title( (string) $v ) : $defaults[ $key ];
					break;
				case 'color':
					$out[ $key ] = sanitize_hex_color( (string) $v ) ? sanitize_hex_color( (string) $v ) : $defaults[ $key ];
					break;
				case 'css':
					$out[ $key ] = trim( wp_strip_all_tags( (string) $v ) );
					break;
				case 'css_length':
					$v           = trim( (string) $v );
					$out[ $key ] = preg_match( '/^\d+(\.\d+)?(px|rem|em|%|ch|vw)$/', $v ) ? $v : '';
					break;
				case 'sections':
					$allowed     = array( 'hero', 'stats', 'about', 'experience', 'expertise', 'qualifications', 'written', 'edited', 'reviewed' );
					$list        = array_intersect( array_map( 'trim', explode( ',', strtolower( (string) $v ) ) ), $allowed );
					$out[ $key ] = $list ? implode( ',', $list ) : $defaults[ $key ];
					break;
				case 'post_types':
					$v           = is_array( $v ) ? array_map( 'sanitize_key', $v ) : array();
					$out[ $key ] = $v ? array_values( $v ) : array( 'post' );
					break;
			}
		}
		if ( $out['list_limit'] < 1 ) {
			$out['list_limit'] = $defaults['list_limit'];
		}
		if ( ( $old['profile_base'] ?? '' ) !== $out['profile_base'] ) {
			update_option( 'etp_flush_rewrite', 1 );
		}
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Field helpers                                                       */
	/* ------------------------------------------------------------------ */

	private static function name( $key ) {
		return 'etp_settings[' . $key . ']';
	}

	private static function text( $key, $label, $help = '', $class = 'regular-text', $type = 'text' ) {
		$v = Etpro::get( $key );
		echo '<tr><th><label for="etp-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
		printf( '<input type="%5$s" class="%4$s" id="etp-%1$s" name="%2$s" value="%3$s">', esc_attr( $key ), esc_attr( self::name( $key ) ), esc_attr( $v ), esc_attr( $class ), esc_attr( $type ) );
		if ( $help ) {
			echo '<p class="description">' . wp_kses( $help, array( 'code' => array(), 'strong' => array(), 'a' => array( 'href' => array() ) ) ) . '</p>';
		}
		echo '</td></tr>';
	}

	private static function textarea( $key, $label, $help = '', $rows = 3 ) {
		echo '<tr><th><label for="etp-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
		printf( '<textarea class="large-text" rows="%3$d" id="etp-%1$s" name="%2$s">%4$s</textarea>', esc_attr( $key ), esc_attr( self::name( $key ) ), (int) $rows, esc_textarea( Etpro::get( $key ) ) );
		if ( $help ) {
			echo '<p class="description">' . wp_kses( $help, array( 'code' => array(), 'strong' => array() ) ) . '</p>';
		}
		echo '</td></tr>';
	}

	private static function checkbox( $key, $label, $text, $help = '' ) {
		echo '<tr><th>' . esc_html( $label ) . '</th><td><label>';
		printf( '<input type="checkbox" name="%1$s" value="1"%2$s> %3$s', esc_attr( self::name( $key ) ), checked( (int) Etpro::get( $key ), 1, false ), esc_html( $text ) );
		echo '</label>';
		if ( $help ) {
			echo '<p class="description">' . wp_kses( $help, array( 'code' => array(), 'strong' => array() ) ) . '</p>';
		}
		echo '</td></tr>';
	}

	private static function select( $key, $label, $choices, $help = '' ) {
		echo '<tr><th><label for="etp-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo '<select id="etp-' . esc_attr( $key ) . '" name="' . esc_attr( self::name( $key ) ) . '">';
		foreach ( $choices as $val => $text ) {
			echo '<option value="' . esc_attr( $val ) . '"' . selected( (string) Etpro::get( $key ), (string) $val, false ) . '>' . esc_html( $text ) . '</option>';
		}
		echo '</select>';
		if ( $help ) {
			echo '<p class="description">' . wp_kses( $help, array( 'code' => array(), 'strong' => array() ) ) . '</p>';
		}
		echo '</td></tr>';
	}

	/* ------------------------------------------------------------------ */
	/* Page                                                                */
	/* ------------------------------------------------------------------ */

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$people    = array( 0 => __( '— None —', 'editorial-trust' ) ) + Etpro_People::options();
		$types     = get_post_types( array( 'public' => true ), 'objects' );
		$selected  = Etpro::post_types();
		$seo       = Etpro_Schema::detected_plugin();
		?>
		<div class="wrap etp-adm etp-settings">
			<?php Etpro_Admin::header( __( 'Settings', 'editorial-trust' ), __( 'Wording, placement, dates, profiles and structured data. Colours and Brand CSS are under Skin & design.', 'editorial-trust' ) ); ?>
			<?php settings_errors(); ?>


			<nav class="etp-settings-nav">
				<a href="#etp-general"><?php esc_html_e( 'General', 'editorial-trust' ); ?></a>
				<a href="#etp-display"><?php esc_html_e( 'Display', 'editorial-trust' ); ?></a>
				<a href="#etp-factcheck"><?php esc_html_e( 'Fact check panel', 'editorial-trust' ); ?></a>
				<a href="#etp-sources"><?php esc_html_e( 'Sources', 'editorial-trust' ); ?></a>
				<a href="#etp-cta"><?php esc_html_e( 'Expert CTA', 'editorial-trust' ); ?></a>
				<a href="#etp-profiles"><?php esc_html_e( 'Profiles', 'editorial-trust' ); ?></a>
				<a href="#etp-schema"><?php esc_html_e( 'Structured data', 'editorial-trust' ); ?></a>
			</nav>

			<form method="post" action="options.php">
				<?php settings_fields( 'etp_settings_group' ); ?>
				<input type="hidden" name="etp_settings[_section]" value="settings">

				<h2 id="etp-general"><?php esc_html_e( 'General', 'editorial-trust' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th><?php esc_html_e( 'Use on', 'editorial-trust' ); ?></th><td>
						<?php foreach ( $types as $t ) : if ( in_array( $t->name, array( 'attachment', 'etp_person' ), true ) ) { continue; } ?>
							<label style="margin-right:14px"><input type="checkbox" name="<?php echo esc_attr( self::name( 'post_types' ) ); ?>[]" value="<?php echo esc_attr( $t->name ); ?>" <?php checked( in_array( $t->name, $selected, true ) ); ?>> <?php echo esc_html( $t->labels->name ); ?></label>
						<?php endforeach; ?>
						<p class="description"><?php esc_html_e( 'Usually just Posts. Tick Pages too if guides are built as pages.', 'editorial-trust' ); ?></p>
					</td></tr>
					<?php
					self::select( 'default_author', __( 'Default author (Author 1)', 'editorial-trust' ), $people, __( 'Pre-filled on new articles and used by the bulk-assign tool.', 'editorial-trust' ) );
					self::select( 'default_reviewer', __( 'Default reviewer (Author 2)', 'editorial-trust' ), $people );
					self::select( 'default_editor', __( 'Default editor (Edited by)', 'editorial-trust' ), $people );
					self::checkbox( 'default_fact_check', __( 'Fact checked by default', 'editorial-trust' ), __( 'Tick "Fact checked" on new articles', 'editorial-trust' ) );
					self::text( 'date_format', __( 'Date format', 'editorial-trust' ), __( 'PHP date format. <code>j M Y</code> = 21 Oct 2026.', 'editorial-trust' ), 'small-text' );
					self::text( 'history_date_format', __( 'Update history date format', 'editorial-trust' ), __( '<code>j F Y</code> = 21 October 2026.', 'editorial-trust' ), 'small-text' );
					self::select( 'updated_fallback', __( 'When "Last updated" is empty, show', 'editorial-trust' ), array(
						'modified'  => __( 'WordPress last-modified date', 'editorial-trust' ),
						'published' => __( 'Published date (recommended if small edits are frequent)', 'editorial-trust' ),
					) );
					?>
				</table>

				<h2 id="etp-display"><?php esc_html_e( 'Display & placement', 'editorial-trust' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					self::checkbox( 'auto_trust_bar', __( 'Author / reviewer bar', 'editorial-trust' ), __( 'Insert automatically at the top of the article content', 'editorial-trust' ), __( 'Untick if you place <code>[etp_trust_bar]</code> in a theme or Elementor post template (e.g. in the hero under the title). It is never shown twice.', 'editorial-trust' ) );
					self::checkbox( 'auto_sources', __( 'Sources box', 'editorial-trust' ), __( 'Insert automatically below the FAQ, or at the end of the article if there is no FAQ', 'editorial-trust' ), __( 'Per-article placement can be changed in the article\'s Sources tab, or place <code>[etp_sources]</code> manually.', 'editorial-trust' ) );
					self::checkbox( 'auto_author_box', __( 'Author box', 'editorial-trust' ), __( 'Show the "About the authors" box at the end of every article', 'editorial-trust' ), __( 'Lists the author, editor and reviewer with photo, role, short summary and a link to their profile. Use <code>[etp_author_box]</code> to place it manually.', 'editorial-trust' ) );
					self::text( 'content_selector', __( 'Post content area (page builders)', 'editorial-trust' ), __( 'Optional. If a page builder template puts the bar above the hero or the boxes full width, the plugin moves them into the post content automatically (Breakdance, Bricks, Elementor, Beaver Themer, Divi and Oxygen are detected). Only fill this in if it still is not right: the CSS selector of the post content element, e.g. <code>.bde-post-content</code>.', 'editorial-trust' ) );
					self::text( 'author_box_heading', __( 'Author box heading', 'editorial-trust' ), __( 'Leave empty to hide the heading.', 'editorial-trust' ) );
					self::checkbox( 'published_label', __( 'Published / Updated label', 'editorial-trust' ), __( 'Show "Published [date]" until a "Last updated" date is set on the article', 'editorial-trust' ), __( 'Recommended: "Updated" then only appears for genuine content updates.', 'editorial-trust' ) );
					self::select( 'cta_position', __( '"Ask the expert" link position', 'editorial-trust' ), array(
						'after_sources' => __( 'Below the sources box (or end of article)', 'editorial-trust' ),
						'after_trust'   => __( 'Below the author / reviewer bar', 'editorial-trust' ),
						'end'           => __( 'End of article content', 'editorial-trust' ),
						'off'           => __( 'Off (use [etp_cta] manually)', 'editorial-trust' ),
					) );
					self::checkbox( 'profile_auto_append', __( 'Team pages', 'editorial-trust' ), __( 'Add Areas of expertise, Written by and Reviewed by sections to linked team pages automatically', 'editorial-trust' ), __( 'Untick if you prefer placing <code>[etp_expertise]</code> and <code>[etp_articles role="author"]</code> / <code>[etp_articles role="reviewer"]</code> on the page yourself.', 'editorial-trust' ) );
					self::text( 'team_page_sections', __( 'Sections added to team pages', 'editorial-trust' ), __( 'Comma list from: <code>hero, stats, about, experience, expertise, qualifications, written, reviewed</code>. Add only what the existing team page does not already show.', 'editorial-trust' ), 'large-text' );
					self::checkbox( 'load_everywhere', __( 'Assets', 'editorial-trust' ), __( 'Load the CSS/JS on every page', 'editorial-trust' ), __( 'Only needed if you use the shortcodes outside articles and profile pages (e.g. a homepage widget).', 'editorial-trust' ) );
					?>
				</table>

				<h2 id="etp-factcheck"><?php esc_html_e( '"Why you can trust this article" panel', 'editorial-trust' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					self::text( 'fc_label', __( 'Badge text', 'editorial-trust' ) );
					self::text( 'fc_hint', __( 'Text under the badge', 'editorial-trust' ), __( 'Small prompt that tells readers the badge opens. Leave empty to hide.', 'editorial-trust' ) );
					self::text( 'fc_kicker', __( 'Small label above the panel heading', 'editorial-trust' ) );
					self::text( 'fc_heading', __( 'Panel heading', 'editorial-trust' ), '', 'large-text' );
					self::textarea( 'fc_summary', __( 'Explanation', 'editorial-trust' ), __( 'Tokens: <code>{reviewer}</code>, <code>{author}</code>, <code>{site}</code>. Can be overridden per article.', 'editorial-trust' ), 2 );
					self::textarea( 'fc_items', __( 'What we checked (default list)', 'editorial-trust' ), __( 'One item per line. Can be overridden per article.', 'editorial-trust' ), 4 );
					self::text( 'fc_items_heading', __( 'List heading', 'editorial-trust' ), __( 'Shown above the list. Leave empty to hide.', 'editorial-trust' ) );
					self::checkbox( 'fc_bold_leadins', __( 'Bold lead-ins', 'editorial-trust' ), __( 'Make the first sentence of each list item bold', 'editorial-trust' ) );
					self::text( 'fc_guidelines_url', __( 'Editorial guidelines page', 'editorial-trust' ), __( 'Optional link at the bottom of the panel.', 'editorial-trust' ), 'large-text', 'url' );
					self::text( 'fc_guidelines_label', __( 'Guidelines link text', 'editorial-trust' ) );
					?>
				</table>

				<h2 id="etp-sources"><?php esc_html_e( 'Sources and further reading', 'editorial-trust' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					self::text( 'src_eyebrow', __( 'Small label above the heading', 'editorial-trust' ) );
					self::text( 'src_heading', __( 'Heading', 'editorial-trust' ), '', 'large-text' );
					self::text( 'src_intro', __( 'Intro sentence', 'editorial-trust' ), '', 'large-text' );
					self::text( 'src_list_heading', __( 'List heading', 'editorial-trust' ) );
					self::textarea( 'src_disclaimer', __( 'Disclaimer', 'editorial-trust' ), __( 'Shown under the sources. Can be changed or switched off per article.', 'editorial-trust' ), 3 );
					self::checkbox( 'src_new_tab', __( 'Links', 'editorial-trust' ), __( 'Open source links in a new tab', 'editorial-trust' ) );
					self::text( 'history_heading', __( 'Update history heading', 'editorial-trust' ) );
					?>
				</table>

				<h2 id="etp-cta"><?php esc_html_e( 'Expert CTA', 'editorial-trust' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					self::text( 'cta_question', __( 'Question text', 'editorial-trust' ), '', 'large-text' );
					self::text( 'cta_link_text', __( 'Link text', 'editorial-trust' ), __( 'Tokens: <code>{first_name}</code>, <code>{name}</code>.', 'editorial-trust' ) );
					self::text( 'cta_url', __( 'Enquiry page URL', 'editorial-trust' ), __( 'e.g. the Contact page. The article title, article URL and expert name are added to the link so the form can capture them.', 'editorial-trust' ), 'large-text', 'url' );
					self::select( 'cta_mode', __( 'On click', 'editorial-trust' ), array(
						'link'  => __( 'Go to the enquiry page', 'editorial-trust' ),
						'popup' => __( 'Open an on-page popup / form', 'editorial-trust' ),
					) );
					self::text( 'cta_popup_selector', __( 'Popup trigger', 'editorial-trust' ), __( 'For "popup": an Elementor Pro popup ID (e.g. <code>1234</code>) or a CSS selector of an existing button that opens your form (e.g. <code>.header-enquire-btn</code>).', 'editorial-trust' ) );
					self::text( 'cta_param_article', __( 'Article field name', 'editorial-trust' ), __( 'URL parameter / hidden form field that receives the article title. Default <code>etp_article</code> (the URL goes into <code>etp_article_url</code>).', 'editorial-trust' ), 'regular-text' );
					self::text( 'cta_param_expert', __( 'Expert field name', 'editorial-trust' ), '', 'regular-text' );
					?>
				</table>

				<h2 id="etp-profiles"><?php esc_html_e( 'Profiles', 'editorial-trust' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					self::text( 'profile_base', __( 'Profile URL base', 'editorial-trust' ), sprintf( /* translators: %s example URL */ __( 'Only used for experts without an existing team page, e.g. %s', 'editorial-trust' ), '<code>' . esc_html( home_url( '/' . Etpro::get( 'profile_base' ) . '/john-doe/' ) ) . '</code>' ), 'regular-text' );
					self::text( 'expertise_heading', __( 'Expertise heading', 'editorial-trust' ) );
					self::text( 'written_eyebrow', __( '"Written by" label', 'editorial-trust' ) );
					self::text( 'written_heading', __( '"Written by" heading', 'editorial-trust' ), __( 'Tokens: <code>{first_name}</code>, <code>{name}</code>.', 'editorial-trust' ) );
					self::text( 'reviewed_eyebrow', __( '"Reviewed by" label', 'editorial-trust' ) );
					self::text( 'reviewed_heading', __( '"Reviewed by" heading', 'editorial-trust' ) );
					self::text( 'edited_eyebrow', __( '"Edited by" label', 'editorial-trust' ) );
					self::text( 'edited_heading', __( '"Edited by" heading', 'editorial-trust' ) );
					self::text( 'list_limit', __( 'Articles shown before "Show more"', 'editorial-trust' ), '', 'small-text', 'number' );
					self::text( 'profile_cta_text', __( 'Profile button text', 'editorial-trust' ) );
					?>
				</table>

				<h2 id="etp-schema"><?php esc_html_e( 'Structured data', 'editorial-trust' ); ?></h2>
				<p>
					<?php
					if ( $seo ) {
						/* translators: %s: plugin name */
						echo esc_html( sprintf( __( 'Detected: %s. In Automatic mode the author, reviewer and profile data are added to its existing schema graph rather than output separately.', 'editorial-trust' ), $seo ) );
					} else {
						esc_html_e( 'No supported SEO plugin detected (Yoast SEO, Rank Math, All in One SEO). Choose "Standalone" only if the theme does not already output Article schema.', 'editorial-trust' );
					}
					?>
				</p>
				<table class="form-table" role="presentation">
					<?php
					self::select( 'schema_mode', __( 'Mode', 'editorial-trust' ), array(
						'auto'       => __( 'Automatic: extend the SEO plugin\'s schema', 'editorial-trust' ),
						'standalone' => __( 'Standalone: output our own Article / WebPage / Person JSON-LD', 'editorial-trust' ),
						'off'        => __( 'Off', 'editorial-trust' ),
					) );
					self::text( 'org_name', __( 'Organisation name (standalone)', 'editorial-trust' ), __( 'Defaults to the site title.', 'editorial-trust' ) );
					self::text( 'org_logo', __( 'Organisation logo URL (standalone)', 'editorial-trust' ), __( 'Defaults to the site icon.', 'editorial-trust' ), 'large-text', 'url' );
					?>
				</table>

				<?php submit_button(); ?>
			</form>

		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* Bulk tool                                                           */
	/* ------------------------------------------------------------------ */

	public static function bulk_assign() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'editorial-trust' ) );
		}
		check_admin_referer( 'etp_bulk_assign' );

		$scope = isset( $_POST['scope'] ) && 'all' === $_POST['scope'] ? 'all' : 'empty';
		$cat   = absint( $_POST['cat'] ?? 0 );
		$fc    = empty( $_POST['fact_checked'] ) ? null : true;
		$date  = Etpro::valid_ymd( wp_unslash( $_POST['reviewed_date'] ?? '' ) );

		$args = array(
			'post_type'      => Etpro::post_types(),
			'post_status'    => 'publish',
			'posts_per_page' => 300,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
		);
		if ( $cat ) {
			$args['cat'] = $cat;
		}
		if ( 'empty' === $scope ) {
			$args['meta_query'] = array(
				array(
					'key'     => '_etp_author_id',
					'compare' => 'NOT EXISTS',
				),
			);
		}
		$offset = 'all' === $scope ? absint( $_POST['offset'] ?? 0 ) : 0;
		if ( $offset ) {
			$args['offset'] = $offset;
		}
		$q = new WP_Query( $args );
		$n = 0;
		foreach ( $q->posts as $id ) {
			Etpro_Article_Meta::assign_defaults( $id, 'all' === $scope, $fc, $date );
			$n++;
		}
		$left = max( 0, (int) $q->found_posts - $offset - $n );

		$back = array(
			'etp_bulk' => $n,
			'etp_left' => $left,
		);
		if ( $left ) {
			$back['etp_next'] = rawurlencode( wp_json_encode( array(
				'scope'         => $scope,
				'cat'           => $cat,
				'fact_checked'  => $fc ? 1 : 0,
				'reviewed_date' => $date,
				'offset'        => $offset + $n,
			) ) );
		}
		wp_safe_redirect( add_query_arg( $back, admin_url( 'admin.php?page=etp-tools' ) ) );
		exit;
	}
}


/* ======================================================================== */
/* Admin: Editorial Trust menu, dashboard, skin & design, tools, help       */
/* ======================================================================== */

class Etpro_Admin {

	const SLUG = 'etp-dashboard';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu_top' ), 9 );
		add_action( 'admin_menu', array( __CLASS__, 'menu_sub' ), 11 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_etp_csv_import', array( __CLASS__, 'csv_import' ) );
		add_action( 'admin_post_etp_csv_export', array( __CLASS__, 'csv_export' ) );
		add_action( 'admin_post_etp_settings_export', array( __CLASS__, 'settings_export' ) );
		add_action( 'admin_post_etp_settings_import', array( __CLASS__, 'settings_import' ) );
		add_action( 'admin_post_etp_experts_export', array( __CLASS__, 'experts_export' ) );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'plugin_links' ), 10, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'update_notice' ) );
		add_action( 'admin_init', array( __CLASS__, 'mark_seen' ) );
		add_action( 'admin_post_etp_experts_import', array( __CLASS__, 'experts_import' ) );

		// Posts list: filter by editorial status + bulk edit of author / editor / reviewer.
		add_action( 'restrict_manage_posts', array( __CLASS__, 'list_filter' ), 20, 2 );
		add_action( 'pre_get_posts', array( __CLASS__, 'apply_list_filter' ) );
		add_action( 'bulk_edit_custom_box', array( __CLASS__, 'bulk_edit_box' ), 10, 2 );
		add_action( 'save_post', array( __CLASS__, 'bulk_edit_save' ), 20, 2 );
	}

	public static function url( $page = '' ) {
		return admin_url( 'admin.php?page=' . ( $page ? $page : self::SLUG ) );
	}

	/* -- Menu ---------------------------------------------------------------- */

	public static function menu_top() {
		add_menu_page(
			__( 'Editorial Trust', 'editorial-trust' ),
			__( 'Editorial Trust', 'editorial-trust' ),
			'edit_posts',
			self::SLUG,
			array( __CLASS__, 'dashboard' ),
			'dashicons-id-alt',
			26
		);
		add_submenu_page( self::SLUG, __( 'Editorial Trust dashboard', 'editorial-trust' ), __( 'Dashboard', 'editorial-trust' ), 'edit_posts', self::SLUG, array( __CLASS__, 'dashboard' ) );
	}

	public static function menu_sub() {
		add_submenu_page( self::SLUG, __( 'Add expert', 'editorial-trust' ), __( 'Add expert', 'editorial-trust' ), 'edit_posts', 'post-new.php?post_type=etp_person' );
		add_submenu_page( self::SLUG, __( 'Editorial Trust settings', 'editorial-trust' ), __( 'Settings', 'editorial-trust' ), 'manage_options', 'etp-settings', array( 'Etpro_Settings', 'page' ) );
		add_submenu_page( self::SLUG, __( 'Skin & design', 'editorial-trust' ), __( 'Skin & design', 'editorial-trust' ), 'manage_options', 'etp-skin', array( __CLASS__, 'skin' ) );
		add_submenu_page( self::SLUG, __( 'Editorial Trust tools', 'editorial-trust' ), __( 'Tools', 'editorial-trust' ), 'manage_options', 'etp-tools', array( __CLASS__, 'tools' ) );
		add_submenu_page( self::SLUG, __( 'Help & shortcodes', 'editorial-trust' ), __( 'Help & shortcodes', 'editorial-trust' ), 'edit_posts', 'etp-help', array( __CLASS__, 'help' ) );
		add_submenu_page( self::SLUG, __( "What's new", 'editorial-trust' ), __( "What's new", 'editorial-trust' ), 'edit_posts', 'etp-changelog', array( __CLASS__, 'changelog_page' ) );
	}

	public static function assets( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return;
		}
		$id = (string) $screen->id;
		if ( false === strpos( $id, 'etp-' ) ) {
			return;
		}
		$base = false === strpos( $id, 'etp-settings' ) ? Etpro_Assets::admin_css() : ''; // Settings already gets it.
		wp_register_style( 'etp-admin', false, array(), Etpro::VERSION );
		wp_enqueue_style( 'etp-admin' );
		wp_add_inline_style( 'etp-admin', $base . self::css() );
		if ( false !== strpos( $id, 'etp-skin' ) ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );
			$cm = wp_enqueue_code_editor( array( 'type' => 'text/css' ) );
			wp_register_script( 'etp-skin', false, array( 'jquery', 'wp-color-picker' ), Etpro::VERSION, true );
			wp_enqueue_script( 'etp-skin' );
			wp_add_inline_script( 'etp-skin', 'window.etpCodeEditor=' . wp_json_encode( $cm ) . ';' . self::skin_js() );
		}
	}

	/* -- Shared bits --------------------------------------------------------- */

	public static function header( $title, $intro = '' ) {
		$pages = array(
			self::SLUG     => __( 'Dashboard', 'editorial-trust' ),
			'experts'      => __( 'Experts', 'editorial-trust' ),
			'etp-settings' => __( 'Settings', 'editorial-trust' ),
			'etp-skin'     => __( 'Skin & design', 'editorial-trust' ),
			'etp-tools'    => __( 'Tools', 'editorial-trust' ),
			'etp-help'     => __( 'Help', 'editorial-trust' ),
			'etp-changelog' => __( "What's new", 'editorial-trust' ),
		);
		$current = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="etp-adm-head"><div class="etp-adm-brand"><span class="dashicons dashicons-id-alt"></span><span>' . esc_html__( 'Editorial Trust', 'editorial-trust' ) . '</span><em>v' . esc_html( Etpro::VERSION ) . '</em></div><nav class="etp-adm-tabs">';
		foreach ( $pages as $slug => $label ) {
			$href = 'experts' === $slug ? admin_url( 'edit.php?post_type=etp_person' ) : self::url( $slug );
			echo '<a class="' . ( $current === $slug ? 'is-active' : '' ) . '" href="' . esc_url( $href ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav></div>';
		echo '<h1 class="etp-adm-title">' . esc_html( $title ) . '</h1>';
		if ( $intro ) {
			echo '<p class="etp-adm-intro">' . esc_html( $intro ) . '</p>';
		}
	}

	private static function count_articles( $meta_query = array() ) {
		$q = new WP_Query( array(
			'post_type'      => Etpro::post_types(),
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery
		) );
		return (int) $q->found_posts;
	}

	/* -- Dashboard ----------------------------------------------------------- */

	public static function dashboard() {
		$total      = self::count_articles();
		$with_a     = self::count_articles( array( array( 'key' => '_etp_author_id', 'compare' => 'EXISTS' ) ) );
		$with_r     = self::count_articles( array( array( 'key' => '_etp_reviewer_id', 'compare' => 'EXISTS' ) ) );
		$checked    = self::count_articles( array( array( 'key' => '_etp_fact_checked', 'value' => '1' ) ) );
		$experts    = get_posts( array( 'post_type' => 'etp_person', 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => 200, 'orderby' => 'title', 'order' => 'ASC' ) );
		$ready      = 0;
		foreach ( $experts as $e ) {
			$p = Etpro::person( $e->ID );
			if ( $p && $p['public'] ) {
				$ready++;
			}
		}
		$pct = function ( $n ) use ( $total ) {
			return $total ? round( $n / $total * 100 ) : 0;
		};
		$list_url = function ( $status ) {
			$type = Etpro::post_types();
			return admin_url( 'edit.php?post_type=' . $type[0] . '&etp_status=' . $status );
		};
		$missing = get_posts( array(
			'post_type'      => Etpro::post_types(),
			'post_status'    => 'publish',
			'posts_per_page' => 12,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => array( array( 'key' => '_etp_author_id', 'compare' => 'NOT EXISTS' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		) );

		$checks = array(
			array( (bool) $experts, __( 'Add at least one expert', 'editorial-trust' ), admin_url( 'post-new.php?post_type=etp_person' ) ),
			array( $ready > 0, __( 'Mark an expert "Ready to show on the website"', 'editorial-trust' ), admin_url( 'edit.php?post_type=etp_person' ) ),
			array( '#d99a00' !== Etpro::get( 'accent_color' ) || '' !== trim( (string) Etpro::get( 'custom_css' ) ), __( 'Set the brand colour or Brand CSS', 'editorial-trust' ), self::url( 'etp-skin' ) ),
			array( 'published' === Etpro::get( 'updated_fallback' ), __( 'Show the published date when "Last updated" is empty (recommended)', 'editorial-trust' ), self::url( 'etp-settings' ) . '#etp-general' ),
			array( $with_a > 0, __( 'Assign an author to the first article', 'editorial-trust' ), $list_url( 'no_author' ) ),
			array( 'off' !== Etpro::get( 'schema_mode' ), __( 'Structured data switched on', 'editorial-trust' ), self::url( 'etp-settings' ) . '#etp-schema' ),
		);
		$done = count( array_filter( wp_list_pluck( $checks, 0 ) ) );
		?>
		<div class="wrap etp-adm">
			<?php self::header( __( 'Dashboard', 'editorial-trust' ), __( 'Overview of authors, reviewers and fact checks across this site.', 'editorial-trust' ) ); ?>

			<div class="etp-adm-stats">
				<a class="etp-adm-stat" href="<?php echo esc_url( admin_url( 'edit.php?post_type=etp_person' ) ); ?>">
					<strong><?php echo (int) $ready; ?><small>/<?php echo (int) count( $experts ); ?></small></strong>
					<span><?php esc_html_e( 'Experts ready to show', 'editorial-trust' ); ?></span>
				</a>
				<a class="etp-adm-stat" href="<?php echo esc_url( $list_url( 'no_author' ) ); ?>">
					<strong><?php echo (int) $pct( $with_a ); ?>%</strong>
					<span><?php echo esc_html( sprintf( /* translators: 1: count 2: total */ __( '%1$d of %2$d articles have an author', 'editorial-trust' ), $with_a, $total ) ); ?></span>
					<i style="--w:<?php echo (int) $pct( $with_a ); ?>%"></i>
				</a>
				<a class="etp-adm-stat" href="<?php echo esc_url( $list_url( 'no_reviewer' ) ); ?>">
					<strong><?php echo (int) $pct( $with_r ); ?>%</strong>
					<span><?php echo esc_html( sprintf( /* translators: 1: count 2: total */ __( '%1$d of %2$d articles have a reviewer', 'editorial-trust' ), $with_r, $total ) ); ?></span>
					<i style="--w:<?php echo (int) $pct( $with_r ); ?>%"></i>
				</a>
				<a class="etp-adm-stat" href="<?php echo esc_url( $list_url( 'not_checked' ) ); ?>">
					<strong><?php echo (int) $pct( $checked ); ?>%</strong>
					<span><?php echo esc_html( sprintf( /* translators: 1: count 2: total */ __( '%1$d of %2$d articles fact checked', 'editorial-trust' ), $checked, $total ) ); ?></span>
					<i style="--w:<?php echo (int) $pct( $checked ); ?>%"></i>
				</a>
			</div>

			<div class="etp-adm-cols">
				<section class="etp-adm-card">
					<h2><?php esc_html_e( 'Setup checklist', 'editorial-trust' ); ?> <span class="etp-adm-pill"><?php echo (int) $done; ?>/<?php echo (int) count( $checks ); ?></span></h2>
					<ul class="etp-adm-checks">
						<?php foreach ( $checks as $c ) : ?>
							<li class="<?php echo $c[0] ? 'is-done' : ''; ?>"><span class="dashicons <?php echo $c[0] ? 'dashicons-yes-alt' : 'dashicons-marker'; ?>"></span><a href="<?php echo esc_url( $c[2] ); ?>"><?php echo esc_html( $c[1] ); ?></a></li>
						<?php endforeach; ?>
					</ul>
					<p class="etp-adm-actions">
						<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=etp_person' ) ); ?>"><?php esc_html_e( 'Add expert', 'editorial-trust' ); ?></a>
						<a class="button" href="<?php echo esc_url( self::url( 'etp-skin' ) ); ?>"><?php esc_html_e( 'Skin & design', 'editorial-trust' ); ?></a>
						<a class="button" href="<?php echo esc_url( self::url( 'etp-tools' ) ); ?>"><?php esc_html_e( 'Import assignments', 'editorial-trust' ); ?></a>
					</p>
				</section>

				<section class="etp-adm-card">
					<h2><?php esc_html_e( 'Latest articles without an author', 'editorial-trust' ); ?></h2>
					<?php if ( ! $missing ) : ?>
						<p class="etp-adm-ok"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Every published article has an author.', 'editorial-trust' ); ?></p>
					<?php else : ?>
						<ul class="etp-adm-links">
							<?php foreach ( $missing as $m ) : ?>
								<li><a href="<?php echo esc_url( get_edit_post_link( $m->ID ) ); ?>"><?php echo esc_html( get_the_title( $m ) ); ?></a><span><?php echo esc_html( get_the_date( 'j M Y', $m ) ); ?></span></li>
							<?php endforeach; ?>
						</ul>
						<p><a href="<?php echo esc_url( $list_url( 'no_author' ) ); ?>"><?php esc_html_e( 'See all articles without an author →', 'editorial-trust' ); ?></a></p>
					<?php endif; ?>
				</section>
			</div>

			<section class="etp-adm-card">
				<h2><?php esc_html_e( 'Experts', 'editorial-trust' ); ?></h2>
				<?php if ( ! $experts ) : ?>
					<p><?php esc_html_e( 'No experts yet.', 'editorial-trust' ); ?> <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=etp_person' ) ); ?>"><?php esc_html_e( 'Add the first one', 'editorial-trust' ); ?></a></p>
				<?php else : ?>
					<table class="widefat striped etp-adm-table">
						<thead><tr>
							<th><?php esc_html_e( 'Expert', 'editorial-trust' ); ?></th>
							<th><?php esc_html_e( 'Status', 'editorial-trust' ); ?></th>
							<th class="num"><?php esc_html_e( 'Written', 'editorial-trust' ); ?></th>
							<th class="num"><?php esc_html_e( 'Edited', 'editorial-trust' ); ?></th>
							<th class="num"><?php esc_html_e( 'Reviewed', 'editorial-trust' ); ?></th>
							<th><?php esc_html_e( 'Still missing', 'editorial-trust' ); ?></th>
						</tr></thead>
						<tbody>
						<?php foreach ( $experts as $e ) : $p = Etpro::person( $e->ID ); $miss = Etpro_People::missing_fields( $e->ID ); ?>
							<tr>
								<td><div class="etp-adm-person"><?php echo Etpro::avatar( $p, 36, false ); // phpcs:ignore ?><span><a href="<?php echo esc_url( get_edit_post_link( $e->ID ) ); ?>"><strong><?php echo esc_html( $p['name'] ); ?></strong></a><br><small><?php echo esc_html( $p['job_title'] ); ?></small></span></div></td>
								<td><?php echo $p['public'] ? '<span class="etp-adm-tag is-ok">' . esc_html__( 'Ready', 'editorial-trust' ) . '</span>' : '<span class="etp-adm-tag">' . esc_html__( 'Hidden', 'editorial-trust' ) . '</span>'; ?></td>
								<td class="num"><?php echo (int) Etpro_People::count_articles( $e->ID, 'author' ); ?></td>
								<td class="num"><?php echo (int) Etpro_People::count_articles( $e->ID, 'editor' ); ?></td>
								<td class="num"><?php echo (int) Etpro_People::count_articles( $e->ID, 'reviewer' ); ?></td>
								<td><?php echo $miss ? esc_html( implode( ', ', $miss ) ) : '<span class="etp-adm-tag is-ok">' . esc_html__( 'Complete', 'editorial-trust' ) . '</span>'; ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</section>
		</div>
		<?php
	}

	/* -- Skin & design ------------------------------------------------------- */

	public static function skin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$sample = get_posts( array(
			'post_type'      => Etpro::post_types(),
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array( array( 'key' => '_etp_author_id', 'compare' => 'EXISTS' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		) );
		$sample = $sample ? (int) $sample[0] : 0;
		$opt    = Etpro::settings();
		?>
		<div class="wrap etp-adm etp-settings">
			<?php self::header( __( 'Skin & design', 'editorial-trust' ), __( 'This site\'s look: brand colour, bar style and Brand CSS. The core code stays the same on every site.', 'editorial-trust' ) ); ?>
			<?php settings_errors(); ?>
			<div class="etp-adm-skin">
				<form method="post" action="options.php" class="etp-adm-card">
					<?php settings_fields( 'etp_settings_group' ); ?>
					<input type="hidden" name="etp_settings[_section]" value="skin">
					<table class="form-table" role="presentation">
						<tr><th><label for="etp_accent"><?php esc_html_e( 'Accent colour', 'editorial-trust' ); ?></label></th><td>
							<input type="text" id="etp_accent" class="etp-color" name="etp_settings[accent_color]" value="<?php echo esc_attr( $opt['accent_color'] ); ?>">
							<p class="description"><?php esc_html_e( 'Seal, photo rings, labels and links. Text on top switches between dark and white automatically.', 'editorial-trust' ); ?></p>
						</td></tr>
						<tr><th><label for="etp_style"><?php esc_html_e( 'Bar style', 'editorial-trust' ); ?></label></th><td>
							<select id="etp_style" name="etp_settings[trust_bar_style]">
								<option value="light" <?php selected( $opt['trust_bar_style'], 'light' ); ?>><?php esc_html_e( 'Dark text (light background)', 'editorial-trust' ); ?></option>
								<option value="dark" <?php selected( $opt['trust_bar_style'], 'dark' ); ?>><?php esc_html_e( 'White text (dark hero background)', 'editorial-trust' ); ?></option>
							</select>
						</td></tr>
						<tr><th><?php esc_html_e( 'Bar frame', 'editorial-trust' ); ?></th><td>
							<label><input type="checkbox" name="etp_settings[trust_bar_card]" value="1" <?php checked( $opt['trust_bar_card'] ); ?>> <?php esc_html_e( 'Show the bar as a card (soft panel with border)', 'editorial-trust' ); ?></label>
						</td></tr>
						<tr><th><label for="etp_maxw"><?php esc_html_e( 'Max width of boxes', 'editorial-trust' ); ?></label></th><td>
							<input type="text" id="etp_maxw" class="regular-text" style="max-width:140px" name="etp_settings[content_max_width]" value="<?php echo esc_attr( $opt['content_max_width'] ); ?>" placeholder="800px">
							<p class="description"><?php esc_html_e( 'Leave empty normally. Set e.g. 800px if a page builder makes the boxes full width.', 'editorial-trust' ); ?></p>
						</td></tr>
						<tr><th><label for="etp_css"><?php esc_html_e( 'Brand CSS', 'editorial-trust' ); ?></label></th><td>
							<textarea id="etp_css" class="large-text code" rows="18" name="etp_settings[custom_css]"><?php echo esc_textarea( $opt['custom_css'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Main classes: .etp-trust (bar), .etp-fc__panel (How we checked), .etp-abox (author box), .etp-profile (profile page). Changes show in the preview straight away; click Save to publish them.', 'editorial-trust' ); ?></p>
						</td></tr>
					</table>
					<?php submit_button( __( 'Save skin', 'editorial-trust' ) ); ?>
				</form>

				<div class="etp-adm-card etp-adm-preview">
					<div class="etp-adm-preview__bar">
						<h2><?php esc_html_e( 'Live preview', 'editorial-trust' ); ?></h2>
						<div class="etp-adm-devices" role="group" aria-label="<?php esc_attr_e( 'Preview width', 'editorial-trust' ); ?>">
							<button type="button" class="button is-active" data-w="100%"><?php esc_html_e( 'Desktop', 'editorial-trust' ); ?></button>
							<button type="button" class="button" data-w="768px"><?php esc_html_e( 'Tablet', 'editorial-trust' ); ?></button>
							<button type="button" class="button" data-w="390px"><?php esc_html_e( 'Mobile', 'editorial-trust' ); ?></button>
						</div>
					</div>
					<?php if ( ! $sample ) : ?>
						<p><?php esc_html_e( 'Assign an author to one published article to see a preview here.', 'editorial-trust' ); ?></p>
					<?php else : ?>
						<p class="description"><?php echo esc_html( sprintf( /* translators: %s article title */ __( 'Using: %s', 'editorial-trust' ), get_the_title( $sample ) ) ); ?> · <a href="<?php echo esc_url( get_permalink( $sample ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View on site', 'editorial-trust' ); ?></a></p>
						<style id="etp-preview-base"><?php echo Etpro_Assets::css() . Etpro_Front::css_vars(); // phpcs:ignore ?></style>
						<style id="etp-preview-brand"><?php echo wp_strip_all_tags( (string) $opt['custom_css'] ); // phpcs:ignore ?></style>
						<div class="etp-adm-frame"><div class="etp-adm-frame__inner">
							<?php echo Etpro_Render::trust_bar( $sample ); // phpcs:ignore ?>
							<p class="etp-adm-lorem"><?php esc_html_e( 'Article content…', 'editorial-trust' ); ?></p>
							<?php echo Etpro_Render::author_box( $sample ); // phpcs:ignore ?>
						</div></div>
						<script><?php echo Etpro_Assets::js(); // phpcs:ignore ?></script>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	private static function skin_js() {
		return <<<'ETP_SKIN_JS'
( function ( $ ) {
	$( function () {
		$( '.etp-color' ).wpColorPicker();
		var css = document.getElementById( 'etp_css' );
		var live = document.getElementById( 'etp-preview-brand' );
		var update = function ( value ) { if ( live ) { live.textContent = value; } };
		if ( css && window.wp && wp.codeEditor && window.etpCodeEditor ) {
			var ed = wp.codeEditor.initialize( css, window.etpCodeEditor );
			ed.codemirror.on( 'change', function ( cm ) { cm.save(); update( cm.getValue() ); } );
		} else if ( css ) {
			css.addEventListener( 'input', function () { update( css.value ); } );
		}
		$( '.etp-adm-devices button' ).on( 'click', function () {
			$( '.etp-adm-devices button' ).removeClass( 'is-active' );
			$( this ).addClass( 'is-active' );
			$( '.etp-adm-frame__inner' ).css( 'max-width', $( this ).data( 'w' ) );
		} );
	} );
} )( jQuery );
ETP_SKIN_JS;
	}

	/* -- Tools --------------------------------------------------------------- */

	public static function tools() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$report = get_transient( 'etp_import_report_' . get_current_user_id() );
		if ( $report ) {
			delete_transient( 'etp_import_report_' . get_current_user_id() );
		}
		?>
		<div class="wrap etp-adm etp-settings">
			<?php self::header( __( 'Tools', 'editorial-trust' ), __( 'Assign people to many articles at once, and copy settings between sites.', 'editorial-trust' ) ); ?>

			<?php
			// phpcs:disable WordPress.Security.NonceVerification
			if ( isset( $_GET['etp_bulk'] ) ) :
				$left = absint( $_GET['etp_left'] ?? 0 );
				$next = isset( $_GET['etp_next'] ) ? json_decode( rawurldecode( wp_unslash( $_GET['etp_next'] ) ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				?>
				<div class="notice notice-success"><p>
					<?php echo esc_html( sprintf( /* translators: %d: updated count */ __( 'Bulk assign: %d articles updated.', 'editorial-trust' ), absint( $_GET['etp_bulk'] ) ) ); ?>
					<?php if ( $left && is_array( $next ) ) : ?>
						<?php echo esc_html( sprintf( /* translators: %d remaining */ __( '%d still to process.', 'editorial-trust' ), $left ) ); ?>
						</p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0 0 10px">
							<input type="hidden" name="action" value="etp_bulk_assign">
							<?php wp_nonce_field( 'etp_bulk_assign' ); ?>
							<?php foreach ( array( 'scope', 'cat', 'fact_checked', 'reviewed_date', 'offset' ) as $k ) : ?>
								<input type="hidden" name="<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( (string) ( $next[ $k ] ?? '' ) ); ?>">
							<?php endforeach; ?>
							<?php submit_button( __( 'Continue', 'editorial-trust' ), 'primary small', 'submit', false ); ?>
						</form><p>
					<?php endif; ?>
				</p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['etp_settings_imported'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings imported.', 'editorial-trust' ); ?></p></div>
			<?php elseif ( isset( $_GET['etp_settings_error'] ) ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'That file or text is not a valid Editorial Trust settings export.', 'editorial-trust' ); ?></p></div>
			<?php endif; ?>
			<?php // phpcs:enable ?>
			<?php
			$xrep = get_transient( 'etp_experts_report_' . get_current_user_id() );
			if ( $xrep ) :
				delete_transient( 'etp_experts_report_' . get_current_user_id() );
				?>
				<div class="etp-adm-card etp-adm-report">
					<h2><?php esc_html_e( 'Experts import finished', 'editorial-trust' ); ?></h2>
					<ul class="etp-adm-help"><?php foreach ( $xrep as $line ) : ?><li><?php echo esc_html( $line ); ?></li><?php endforeach; ?></ul>
					<p><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=etp_person' ) ); ?>"><?php esc_html_e( 'Go to All experts →', 'editorial-trust' ); ?></a></p>
				</div>
			<?php endif; ?>

			<?php if ( $report ) : ?>
				<div class="etp-adm-card etp-adm-report">
					<h2><?php echo $report['dry'] ? esc_html__( 'Import preview (nothing saved yet)', 'editorial-trust' ) : esc_html__( 'Import finished', 'editorial-trust' ); ?></h2>
					<p><?php echo esc_html( sprintf( /* translators: 1: ok rows 2: problem rows */ __( '%1$d rows OK, %2$d rows with problems.', 'editorial-trust' ), $report['ok'], count( $report['errors'] ) ) ); ?></p>
					<?php if ( $report['rows'] ) : ?>
						<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Article', 'editorial-trust' ); ?></th><th><?php esc_html_e( 'Author', 'editorial-trust' ); ?></th><th><?php esc_html_e( 'Editor', 'editorial-trust' ); ?></th><th><?php esc_html_e( 'Reviewer', 'editorial-trust' ); ?></th><th><?php esc_html_e( 'Fact checked', 'editorial-trust' ); ?></th></tr></thead><tbody>
							<?php foreach ( array_slice( $report['rows'], 0, 300 ) as $r ) : ?>
								<tr><td><?php echo esc_html( $r[0] ); ?></td><td><?php echo esc_html( $r[1] ); ?></td><td><?php echo esc_html( $r[2] ); ?></td><td><?php echo esc_html( $r[3] ); ?></td><td><?php echo esc_html( $r[4] ); ?></td></tr>
							<?php endforeach; ?>
						</tbody></table>
					<?php endif; ?>
					<?php if ( $report['errors'] ) : ?>
						<h3><?php esc_html_e( 'Problems', 'editorial-trust' ); ?></h3>
						<ul class="etp-adm-errors"><?php foreach ( $report['errors'] as $e ) : ?><li><?php echo esc_html( $e ); ?></li><?php endforeach; ?></ul>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<section class="etp-adm-card">
				<h2><?php esc_html_e( 'Import assignments (CSV)', 'editorial-trust' ); ?></h2>
				<p><?php esc_html_e( 'Set the author, editor, reviewer and fact check of many articles from a spreadsheet. Columns (header row required):', 'editorial-trust' ); ?> <code>article</code>, <code>author</code>, <code>editor</code>, <code>reviewer</code>, <code>fact_checked</code>, <code>reviewed_date</code>.</p>
				<ul class="etp-adm-help">
					<li><?php esc_html_e( 'article: the article URL or post ID.', 'editorial-trust' ); ?></li>
					<li><?php esc_html_e( 'author / editor / reviewer: the expert\'s full name (as in Experts), slug or ID. Several people: separate with |. Leave empty to keep what is there.', 'editorial-trust' ); ?></li>
					<li><?php esc_html_e( 'fact_checked: yes or no. reviewed_date: YYYY-MM-DD. Both optional.', 'editorial-trust' ); ?></li>
				</ul>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
					<input type="hidden" name="action" value="etp_csv_import">
					<?php wp_nonce_field( 'etp_csv_import' ); ?>
					<p><label><strong><?php esc_html_e( 'Upload a CSV file', 'editorial-trust' ); ?></strong><br><input type="file" name="csv_file" accept=".csv,text/csv"></label></p>
					<p><label><strong><?php esc_html_e( 'Or paste the CSV here', 'editorial-trust' ); ?></strong><br>
						<textarea name="csv_text" rows="6" class="large-text code" placeholder="article,author,editor,reviewer,fact_checked,reviewed_date&#10;https://example.com/blog/knee-pain/,Dr Jonathan Negus,,Dr Donald Cawthorne,yes,2026-11-11"></textarea></label></p>
					<p><label><input type="checkbox" name="dry_run" value="1" checked> <?php esc_html_e( 'Preview only (check matches first, save nothing)', 'editorial-trust' ); ?></label></p>
					<?php submit_button( __( 'Run import', 'editorial-trust' ), 'primary', 'submit', false ); ?>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=etp_csv_export' ), 'etp_csv_export' ) ); ?>"><?php esc_html_e( 'Download current assignments (CSV)', 'editorial-trust' ); ?></a>
				</form>
			</section>

			<section class="etp-adm-card">
				<h2><?php esc_html_e( 'Bulk assign the default people', 'editorial-trust' ); ?></h2>
				<p><?php esc_html_e( 'Give the default author, editor and reviewer (Settings → General) to many articles in one go. Processes 300 articles per run.', 'editorial-trust' ); ?></p>
				<?php if ( ! Etpro::get( 'default_author' ) ) : ?>
					<p><strong><?php esc_html_e( 'Choose and save a default author in Settings first.', 'editorial-trust' ); ?></strong> <a href="<?php echo esc_url( self::url( 'etp-settings' ) ); ?>"><?php esc_html_e( 'Go to Settings', 'editorial-trust' ); ?></a></p>
				<?php else : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="etp_bulk_assign">
						<?php wp_nonce_field( 'etp_bulk_assign' ); ?>
						<table class="form-table" role="presentation">
							<tr><th><?php esc_html_e( 'Which articles', 'editorial-trust' ); ?></th><td>
								<label><input type="radio" name="scope" value="empty" checked> <?php esc_html_e( 'Only articles without an author yet', 'editorial-trust' ); ?></label><br>
								<label><input type="radio" name="scope" value="all"> <?php esc_html_e( 'All published articles (replaces the primary author and reviewer)', 'editorial-trust' ); ?></label>
							</td></tr>
							<tr><th><label for="etp-bulk-cat"><?php esc_html_e( 'Category (optional)', 'editorial-trust' ); ?></label></th><td>
								<?php wp_dropdown_categories( array( 'show_option_all' => __( 'All categories', 'editorial-trust' ), 'name' => 'cat', 'id' => 'etp-bulk-cat', 'hide_empty' => false ) ); ?>
							</td></tr>
							<tr><th><?php esc_html_e( 'Fact check', 'editorial-trust' ); ?></th><td>
								<label><input type="checkbox" name="fact_checked" value="1"> <?php esc_html_e( 'Mark these articles as fact checked', 'editorial-trust' ); ?></label>
							</td></tr>
							<tr><th><label for="etp-bulk-date"><?php esc_html_e( 'Last reviewed date (optional)', 'editorial-trust' ); ?></label></th><td>
								<input type="date" id="etp-bulk-date" name="reviewed_date">
								<p class="description"><?php esc_html_e( 'Only set this if the reviewer has actually reviewed these articles.', 'editorial-trust' ); ?></p>
							</td></tr>
						</table>
						<?php submit_button( __( 'Assign now', 'editorial-trust' ), 'secondary' ); ?>
					</form>
				<?php endif; ?>
			</section>

			<section class="etp-adm-card">
				<h2><?php esc_html_e( 'Export / import experts', 'editorial-trust' ); ?></h2>
				<p><?php esc_html_e( 'Copy expert profiles (name, position, bio, summary, expertise, qualifications, experience, links and photo) from one site to another.', 'editorial-trust' ); ?></p>
				<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=etp_experts_export' ), 'etp_experts_export' ) ); ?>"><?php esc_html_e( 'Export all experts', 'editorial-trust' ); ?></a></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
					<input type="hidden" name="action" value="etp_experts_import">
					<?php wp_nonce_field( 'etp_experts_import' ); ?>
					<p><label><strong><?php esc_html_e( 'Import file (.json)', 'editorial-trust' ); ?></strong><br><input type="file" name="json_file" accept=".json,application/json"></label></p>
					<p><label><strong><?php esc_html_e( 'Or paste the export here', 'editorial-trust' ); ?></strong><br><textarea name="json_text" rows="4" class="large-text code"></textarea></label></p>
					<p><strong><?php esc_html_e( 'If an expert with the same name already exists', 'editorial-trust' ); ?></strong><br>
						<label><input type="radio" name="existing" value="skip" checked> <?php esc_html_e( 'Skip it (keep this site\'s version)', 'editorial-trust' ); ?></label><br>
						<label><input type="radio" name="existing" value="update"> <?php esc_html_e( 'Update it with the imported details', 'editorial-trust' ); ?></label></p>
					<p><label><input type="checkbox" name="photos" value="1" checked> <?php esc_html_e( 'Copy profile photos (downloaded into this site\'s Media Library)', 'editorial-trust' ); ?></label></p>
					<p class="description"><?php esc_html_e( 'Imported experts start unticked for "Ready to show on the website" unless they were ready on the original site. Team pages are matched by page address; if this site has no matching page, pick it after importing.', 'editorial-trust' ); ?></p>
					<?php submit_button( __( 'Import experts', 'editorial-trust' ), 'secondary', 'submit', false ); ?>
				</form>
			</section>

			<section class="etp-adm-card">
				<h2><?php esc_html_e( 'Export / import settings', 'editorial-trust' ); ?></h2>
				<p><?php esc_html_e( 'Copy the settings or just the skin (colour, bar style, Brand CSS) from one site to another.', 'editorial-trust' ); ?></p>
				<p>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=etp_settings_export&what=skin' ), 'etp_settings_export' ) ); ?>"><?php esc_html_e( 'Export skin', 'editorial-trust' ); ?></a>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=etp_settings_export&what=all' ), 'etp_settings_export' ) ); ?>"><?php esc_html_e( 'Export all settings', 'editorial-trust' ); ?></a>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
					<input type="hidden" name="action" value="etp_settings_import">
					<?php wp_nonce_field( 'etp_settings_import' ); ?>
					<p><label><strong><?php esc_html_e( 'Import file (.json)', 'editorial-trust' ); ?></strong><br><input type="file" name="json_file" accept=".json,application/json"></label></p>
					<p><label><strong><?php esc_html_e( 'Or paste the export here', 'editorial-trust' ); ?></strong><br><textarea name="json_text" rows="4" class="large-text code"></textarea></label></p>
					<p class="description"><?php esc_html_e( 'Default author, editor and reviewer are never imported, because experts are different on each site.', 'editorial-trust' ); ?></p>
					<?php submit_button( __( 'Import settings', 'editorial-trust' ), 'secondary', 'submit', false ); ?>
				</form>
			</section>
		</div>
		<?php
	}

	/* -- CSV import / export ------------------------------------------------- */

	private static function find_article( $ref ) {
		$ref = trim( (string) $ref );
		if ( '' === $ref ) {
			return 0;
		}
		if ( ctype_digit( $ref ) ) {
			$id = (int) $ref;
		} else {
			$id = url_to_postid( $ref );
			if ( ! $id ) {
				$path = trim( (string) wp_parse_url( $ref, PHP_URL_PATH ), '/' );
				$slug = $path ? basename( $path ) : '';
				if ( $slug ) {
					$found = get_posts( array( 'name' => $slug, 'post_type' => Etpro::post_types(), 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids' ) );
					$id    = $found ? (int) $found[0] : 0;
				}
			}
		}
		return $id && in_array( get_post_type( $id ), Etpro::post_types(), true ) ? $id : 0;
	}

	private static function people_from_cell( $cell, &$errors, $line, $col ) {
		$cell = trim( (string) $cell );
		if ( '' === $cell ) {
			return null; // Keep what is there.
		}
		if ( in_array( strtolower( $cell ), array( '-', 'none' ), true ) ) {
			return array(); // Clear.
		}
		$ids = array();
		foreach ( array_filter( array_map( 'trim', explode( '|', $cell ) ) ) as $ref ) {
			$id = Etpro::find_person( $ref );
			if ( $id ) {
				$ids[] = $id;
			} else {
				/* translators: 1: line 2: column 3: value */
				$errors[] = sprintf( __( 'Line %1$d: %2$s "%3$s" is not an expert on this site.', 'editorial-trust' ), $line, $col, $ref );
			}
		}
		return $ids;
	}

	public static function csv_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'editorial-trust' ) );
		}
		check_admin_referer( 'etp_csv_import' );
		$text = '';
		if ( ! empty( $_FILES['csv_file']['tmp_name'] ) && is_uploaded_file( $_FILES['csv_file']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$text = (string) file_get_contents( $_FILES['csv_file']['tmp_name'] ); // phpcs:ignore
		} elseif ( isset( $_POST['csv_text'] ) ) {
			$text = (string) wp_unslash( $_POST['csv_text'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		}
		$dry    = ! empty( $_POST['dry_run'] );
		$text   = preg_replace( '/^\xEF\xBB\xBF/', '', $text );
		$lines  = preg_split( '/\r\n|\r|\n/', trim( $text ) );
		$report = array( 'dry' => $dry, 'ok' => 0, 'rows' => array(), 'errors' => array() );
		$head   = $lines ? array_map( function ( $h ) {
			return strtolower( trim( $h ) );
		}, str_getcsv( array_shift( $lines ) ) ) : array();
		$col    = array_flip( $head );
		if ( ! isset( $col['article'] ) ) {
			$report['errors'][] = __( 'The first row must be a header with at least an "article" column.', 'editorial-trust' );
		} else {
			$get = function ( $row, $name ) use ( $col ) {
				return isset( $col[ $name ], $row[ $col[ $name ] ] ) ? $row[ $col[ $name ] ] : '';
			};
			foreach ( $lines as $i => $raw ) {
				if ( '' === trim( $raw ) ) {
					continue;
				}
				$line = $i + 2;
				$row  = str_getcsv( $raw );
				$id   = self::find_article( $get( $row, 'article' ) );
				if ( ! $id ) {
					/* translators: 1: line 2: value */
					$report['errors'][] = sprintf( __( 'Line %1$d: article "%2$s" not found.', 'editorial-trust' ), $line, $get( $row, 'article' ) );
					continue;
				}
				$before = count( $report['errors'] );
				$a      = self::people_from_cell( $get( $row, 'author' ), $report['errors'], $line, 'author' );
				$e      = self::people_from_cell( $get( $row, 'editor' ), $report['errors'], $line, 'editor' );
				$r      = self::people_from_cell( $get( $row, 'reviewer' ), $report['errors'], $line, 'reviewer' );
				if ( count( $report['errors'] ) > $before ) {
					continue;
				}
				$fc   = strtolower( trim( $get( $row, 'fact_checked' ) ) );
				$fc   = in_array( $fc, array( 'yes', 'y', '1', 'true' ), true ) ? '1' : ( in_array( $fc, array( 'no', 'n', '0', 'false' ), true ) ? '0' : '' );
				$date = Etpro::valid_ymd( $get( $row, 'reviewed_date' ) );
				$keep = function ( $role ) use ( $id ) {
					$v = get_post_meta( $id, '_etp_' . $role, true );
					return is_array( $v ) ? $v : array();
				};
				$a = null === $a ? $keep( 'authors' ) : $a;
				$e = null === $e ? $keep( 'editors' ) : $e;
				$r = null === $r ? $keep( 'reviewers' ) : $r;
				if ( ! $dry ) {
					Etpro_Article_Meta::set_people( $id, $a, $r, $e );
					if ( '' !== $fc ) {
						update_post_meta( $id, '_etp_fact_checked', $fc );
					}
					if ( $date ) {
						update_post_meta( $id, '_etp_reviewed_date', $date );
					}
				}
				$names = function ( $ids ) {
					$out = array();
					foreach ( $ids as $pid ) {
						$out[] = get_the_title( $pid );
					}
					return $out ? implode( ', ', $out ) : '—';
				};
				$report['ok']++;
				$report['rows'][] = array( get_the_title( $id ), $names( $a ), $names( $e ), $names( $r ), '1' === $fc ? __( 'yes', 'editorial-trust' ) : ( '0' === $fc ? __( 'no', 'editorial-trust' ) : '—' ) );
			}
		}
		set_transient( 'etp_import_report_' . get_current_user_id(), $report, 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( self::url( 'etp-tools' ) );
		exit;
	}

	public static function csv_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'editorial-trust' ) );
		}
		check_admin_referer( 'etp_csv_export' );
		$ids = get_posts( array( 'post_type' => Etpro::post_types(), 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'date', 'order' => 'DESC' ) );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=editorial-trust-assignments-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out   = fopen( 'php://output', 'w' );
		$names = function ( $post_id, $key ) {
			$v   = get_post_meta( $post_id, $key, true );
			$out = array();
			foreach ( is_array( $v ) ? $v : array() as $pid ) {
				$out[] = get_the_title( $pid );
			}
			return implode( '|', $out );
		};
		fputcsv( $out, array( 'article', 'title', 'published', 'author', 'editor', 'reviewer', 'fact_checked', 'reviewed_date' ) );
		foreach ( $ids as $id ) {
			fputcsv( $out, array(
				get_permalink( $id ),
				html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ),
				get_the_date( 'Y-m-d', $id ),
				$names( $id, '_etp_authors' ),
				$names( $id, '_etp_editors' ),
				$names( $id, '_etp_reviewers' ),
				'1' === (string) get_post_meta( $id, '_etp_fact_checked', true ) ? 'yes' : 'no',
				(string) get_post_meta( $id, '_etp_reviewed_date', true ),
			) );
		}
		fclose( $out ); // phpcs:ignore
		exit;
	}

	/* -- Settings export / import -------------------------------------------- */

	public static function skin_keys() {
		return array( 'accent_color', 'trust_bar_style', 'trust_bar_card', 'content_max_width', 'custom_css' );
	}

	public static function settings_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'editorial-trust' ) );
		}
		check_admin_referer( 'etp_settings_export' );
		$what = isset( $_GET['what'] ) && 'skin' === $_GET['what'] ? 'skin' : 'all';
		$s    = Etpro::settings();
		unset( $s['default_author'], $s['default_reviewer'], $s['default_editor'] );
		if ( 'skin' === $what ) {
			$s = array_intersect_key( $s, array_flip( self::skin_keys() ) );
		}
		$data = array( 'editorial_trust' => Etpro::VERSION, 'type' => $what, 'site' => home_url( '/' ), 'settings' => $s );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=editorial-trust-' . $what . '-' . sanitize_title( wp_parse_url( home_url(), PHP_URL_HOST ) ) . '.json' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore
		exit;
	}

	public static function settings_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'editorial-trust' ) );
		}
		check_admin_referer( 'etp_settings_import' );
		$text = '';
		if ( ! empty( $_FILES['json_file']['tmp_name'] ) && is_uploaded_file( $_FILES['json_file']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$text = (string) file_get_contents( $_FILES['json_file']['tmp_name'] ); // phpcs:ignore
		} elseif ( isset( $_POST['json_text'] ) ) {
			$text = (string) wp_unslash( $_POST['json_text'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		}
		$data = json_decode( $text, true );
		if ( ! is_array( $data ) || empty( $data['editorial_trust'] ) || empty( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
			wp_safe_redirect( add_query_arg( 'etp_settings_error', 1, self::url( 'etp-tools' ) ) );
			exit;
		}
		$incoming = $data['settings'];
		unset( $incoming['default_author'], $incoming['default_reviewer'], $incoming['default_editor'] );
		$current = Etpro::settings();
		$merged  = array_merge( $current, array_intersect_key( $incoming, Etpro::defaults() ) );
		$merged['_section'] = 'all';
		update_option( 'etp_settings', $merged ); // Runs the normal sanitiser.
		Etpro::reset_cache();
		wp_safe_redirect( add_query_arg( 'etp_settings_imported', 1, self::url( 'etp-tools' ) ) );
		exit;
	}

	/* -- Experts export / import --------------------------------------------- */

	private static function expert_meta_keys() {
		return array( 'short_name', 'job_title', 'credentials', 'linkedin', 'profile_url', 'profile_new_tab', 'gravatar_email', 'expertise', 'qualifications', 'experience', 'public' );
	}

	public static function experts_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'editorial-trust' ) );
		}
		check_admin_referer( 'etp_experts_export' );
		$out = array();
		foreach ( get_posts( array( 'post_type' => 'etp_person', 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => -1, 'orderby' => 'menu_order title', 'order' => 'ASC' ) ) as $e ) {
			$meta = array();
			foreach ( self::expert_meta_keys() as $k ) {
				$meta[ $k ] = (string) get_post_meta( $e->ID, '_etp_' . $k, true );
			}
			$page_id = (int) get_post_meta( $e->ID, '_etp_profile_page_id', true );
			$page_ok = $page_id && 'publish' === get_post_status( $page_id );
			$out[]   = array(
				'name'          => $e->post_title,
				'slug'          => $e->post_name,
				'status'        => $e->post_status,
				'bio'           => $e->post_content,
				'summary'       => $e->post_excerpt,
				'order'         => (int) $e->menu_order,
				'meta'          => $meta,
				'team_page'     => $page_ok ? get_page_uri( $page_id ) : '',
				'team_page_url' => $page_ok ? get_permalink( $page_id ) : '',
				'photo'         => (string) get_the_post_thumbnail_url( $e->ID, 'full' ),
			);
		}
		$data = array( 'editorial_trust_experts' => Etpro::VERSION, 'site' => home_url( '/' ), 'experts' => $out );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=editorial-trust-experts-' . sanitize_title( wp_parse_url( home_url(), PHP_URL_HOST ) ) . '.json' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore
		exit;
	}

	public static function experts_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'editorial-trust' ) );
		}
		check_admin_referer( 'etp_experts_import' );
		$text = '';
		if ( ! empty( $_FILES['json_file']['tmp_name'] ) && is_uploaded_file( $_FILES['json_file']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$text = (string) file_get_contents( $_FILES['json_file']['tmp_name'] ); // phpcs:ignore
		} elseif ( isset( $_POST['json_text'] ) ) {
			$text = (string) wp_unslash( $_POST['json_text'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		}
		$data   = json_decode( $text, true );
		$update = isset( $_POST['existing'] ) && 'update' === $_POST['existing'];
		$photos = ! empty( $_POST['photos'] );
		$report = array();
		if ( ! is_array( $data ) || empty( $data['editorial_trust_experts'] ) || ! isset( $data['experts'] ) || ! is_array( $data['experts'] ) ) {
			$report[] = __( 'That file or text is not an Editorial Trust experts export.', 'editorial-trust' );
		} else {
			if ( $photos ) {
				require_once ABSPATH . 'wp-admin/includes/media.php';
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}
			foreach ( $data['experts'] as $x ) {
				$name = sanitize_text_field( $x['name'] ?? '' );
				if ( '' === $name ) {
					continue;
				}
				$existing = get_posts( array( 'post_type' => 'etp_person', 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'title' => $name, 'posts_per_page' => 1, 'fields' => 'ids' ) );
				$id       = $existing ? (int) $existing[0] : 0;
				if ( $id && ! $update ) {
					/* translators: %s name */
					$report[] = sprintf( __( '%s: already on this site, skipped.', 'editorial-trust' ), $name );
					continue;
				}
				$post = array(
					'post_type'    => 'etp_person',
					'post_title'   => $name,
					'post_content' => wp_kses_post( $x['bio'] ?? '' ),
					'post_excerpt' => sanitize_textarea_field( $x['summary'] ?? '' ),
					'menu_order'   => (int) ( $x['order'] ?? 0 ),
					'post_status'  => in_array( $x['status'] ?? '', array( 'publish', 'draft', 'pending', 'private' ), true ) ? $x['status'] : 'draft',
				);
				if ( $id ) {
					$post['ID'] = $id;
					$id         = wp_update_post( wp_slash( $post ), true );
				} else {
					if ( ! empty( $x['slug'] ) ) {
						$post['post_name'] = sanitize_title( $x['slug'] );
					}
					$id = wp_insert_post( wp_slash( $post ), true );
				}
				if ( is_wp_error( $id ) || ! $id ) {
					/* translators: %s name */
					$report[] = sprintf( __( '%s: could not be saved.', 'editorial-trust' ), $name );
					continue;
				}
				$m = isset( $x['meta'] ) && is_array( $x['meta'] ) ? $x['meta'] : array();
				foreach ( array( 'short_name', 'job_title', 'credentials' ) as $k ) {
					update_post_meta( $id, '_etp_' . $k, sanitize_text_field( $m[ $k ] ?? '' ) );
				}
				foreach ( array( 'linkedin', 'profile_url' ) as $k ) {
					update_post_meta( $id, '_etp_' . $k, esc_url_raw( trim( (string) ( $m[ $k ] ?? '' ) ) ) );
				}
				foreach ( array( 'expertise', 'qualifications' ) as $k ) {
					update_post_meta( $id, '_etp_' . $k, sanitize_textarea_field( $m[ $k ] ?? '' ) );
				}
				update_post_meta( $id, '_etp_experience', wp_kses_post( $m['experience'] ?? '' ) );
				update_post_meta( $id, '_etp_gravatar_email', sanitize_email( $m['gravatar_email'] ?? '' ) );
				update_post_meta( $id, '_etp_public', '1' === (string) ( $m['public'] ?? '' ) ? '1' : '0' );
				if ( isset( $m['profile_new_tab'] ) && '' !== $m['profile_new_tab'] ) {
					update_post_meta( $id, '_etp_profile_new_tab', '1' === (string) $m['profile_new_tab'] ? '1' : '0' );
				}
				$notes = array();
				$page  = ! empty( $x['team_page'] ) ? get_page_by_path( sanitize_text_field( $x['team_page'] ) ) : null;
				if ( $page && 'publish' === $page->post_status ) {
					update_post_meta( $id, '_etp_profile_page_id', $page->ID );
					/* translators: %s page title */
					$notes[] = sprintf( __( 'team page "%s" linked', 'editorial-trust' ), $page->post_title );
				} else {
					update_post_meta( $id, '_etp_profile_page_id', 0 );
					if ( ! empty( $x['team_page_url'] ) && '' === (string) ( $m['profile_url'] ?? '' ) ) {
						update_post_meta( $id, '_etp_profile_url', esc_url_raw( $x['team_page_url'] ) );
						$notes[] = __( 'team page is on the other site, so its address is used as the custom profile URL', 'editorial-trust' );
					}
				}
				if ( $photos && ! empty( $x['photo'] ) && ( ! has_post_thumbnail( $id ) || $update ) ) {
					$short = function () {
						return 20;
					};
					add_filter( 'http_request_timeout', $short );
					$att = media_sideload_image( esc_url_raw( $x['photo'] ), $id, $name, 'id' );
					remove_filter( 'http_request_timeout', $short );
					if ( is_wp_error( $att ) ) {
						$notes[] = __( 'photo could not be copied, please add it by hand', 'editorial-trust' );
					} else {
						set_post_thumbnail( $id, $att );
						$notes[] = __( 'photo copied', 'editorial-trust' );
					}
				}
				$report[] = ( $existing ? __( 'Updated', 'editorial-trust' ) : __( 'Added', 'editorial-trust' ) ) . ': ' . $name . ( $notes ? ' (' . implode( '; ', $notes ) . ')' : '' );
			}
			if ( ! $report ) {
				$report[] = __( 'No experts found in the file.', 'editorial-trust' );
			}
			Etpro::reset_cache();
		}
		set_transient( 'etp_experts_report_' . get_current_user_id(), $report, 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( self::url( 'etp-tools' ) );
		exit;
	}

	/* -- Posts list: status filter + bulk edit -------------------------------- */

	public static function list_filter( $post_type, $which = 'top' ) {
		if ( 'top' !== $which || ! in_array( $post_type, Etpro::post_types(), true ) ) {
			return;
		}
		$current = isset( $_GET['etp_status'] ) ? sanitize_key( wp_unslash( $_GET['etp_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$opts    = array(
			''            => __( 'Editorial trust: all', 'editorial-trust' ),
			'no_author'   => __( 'No author yet', 'editorial-trust' ),
			'no_reviewer' => __( 'No reviewer yet', 'editorial-trust' ),
			'not_checked' => __( 'Not fact checked', 'editorial-trust' ),
			'complete'    => __( 'Author, reviewer & fact checked', 'editorial-trust' ),
		);
		echo '<select name="etp_status">';
		foreach ( $opts as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $current, $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	public static function apply_list_filter( $q ) {
		if ( ! is_admin() || ! $q->is_main_query() || empty( $_GET['etp_status'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$s  = sanitize_key( wp_unslash( $_GET['etp_status'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$mq = (array) $q->get( 'meta_query' );
		if ( 'no_author' === $s ) {
			$mq[] = array( 'key' => '_etp_author_id', 'compare' => 'NOT EXISTS' );
		} elseif ( 'no_reviewer' === $s ) {
			$mq[] = array( 'key' => '_etp_reviewer_id', 'compare' => 'NOT EXISTS' );
		} elseif ( 'not_checked' === $s ) {
			$mq[] = array(
				'relation' => 'OR',
				array( 'key' => '_etp_fact_checked', 'compare' => 'NOT EXISTS' ),
				array( 'key' => '_etp_fact_checked', 'value' => '1', 'compare' => '!=' ),
			);
		} elseif ( 'complete' === $s ) {
			$mq[] = array( 'key' => '_etp_author_id', 'compare' => 'EXISTS' );
			$mq[] = array( 'key' => '_etp_reviewer_id', 'compare' => 'EXISTS' );
			$mq[] = array( 'key' => '_etp_fact_checked', 'value' => '1' );
		} else {
			return;
		}
		$q->set( 'meta_query', $mq );
	}

	public static function bulk_edit_box( $column, $post_type ) {
		if ( 'etp_editorial' !== $column || ! in_array( $post_type, Etpro::post_types(), true ) ) {
			return;
		}
		$options = Etpro_People::options();
		$select  = function ( $name, $label ) use ( $options ) {
			echo '<label class="inline-edit-group"><span class="title">' . esc_html( $label ) . '</span><select name="' . esc_attr( $name ) . '">';
			echo '<option value="">' . esc_html__( '— No change —', 'editorial-trust' ) . '</option>';
			echo '<option value="-1">' . esc_html__( '— Remove —', 'editorial-trust' ) . '</option>';
			foreach ( $options as $id => $l ) {
				echo '<option value="' . (int) $id . '">' . esc_html( $l ) . '</option>';
			}
			echo '</select></label>';
		};
		echo '<fieldset class="inline-edit-col-right etp-bulk"><div class="inline-edit-col"><span class="title etp-bulk-title">' . esc_html__( 'Editorial trust', 'editorial-trust' ) . '</span>';
		wp_nonce_field( 'etp_bulk_edit', 'etp_bulk_nonce' );
		$select( 'etp_bulk_author', __( 'Author', 'editorial-trust' ) );
		$select( 'etp_bulk_editor', __( 'Editor', 'editorial-trust' ) );
		$select( 'etp_bulk_reviewer', __( 'Reviewer', 'editorial-trust' ) );
		echo '<label class="inline-edit-group"><span class="title">' . esc_html__( 'Fact checked', 'editorial-trust' ) . '</span><select name="etp_bulk_fc"><option value="">' . esc_html__( '— No change —', 'editorial-trust' ) . '</option><option value="1">' . esc_html__( 'Yes', 'editorial-trust' ) . '</option><option value="0">' . esc_html__( 'No', 'editorial-trust' ) . '</option></select></label>';
		echo '</div></fieldset>';
	}

	public static function bulk_edit_save( $post_id, $post ) {
		if ( empty( $_REQUEST['bulk_edit'] ) || empty( $_REQUEST['etp_bulk_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['etp_bulk_nonce'] ) ), 'etp_bulk_edit' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! in_array( $post->post_type, Etpro::post_types(), true ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		$pick = function ( $field, $role ) use ( $post_id ) {
			$v   = isset( $_REQUEST[ $field ] ) ? (int) $_REQUEST[ $field ] : 0; // phpcs:ignore WordPress.Security.NonceVerification
			$cur = get_post_meta( $post_id, '_etp_' . $role, true );
			$cur = is_array( $cur ) ? array_map( 'intval', $cur ) : array();
			if ( -1 === $v ) {
				return array();
			}
			if ( $v > 0 ) {
				return array_merge( array( $v ), array_diff( array_slice( $cur, 1 ), array( $v ) ) );
			}
			return $cur;
		};
		$changed = '' !== ( $_REQUEST['etp_bulk_author'] ?? '' ) || '' !== ( $_REQUEST['etp_bulk_editor'] ?? '' ) || '' !== ( $_REQUEST['etp_bulk_reviewer'] ?? '' ); // phpcs:ignore
		if ( $changed ) {
			Etpro_Article_Meta::set_people( $post_id, $pick( 'etp_bulk_author', 'authors' ), $pick( 'etp_bulk_reviewer', 'reviewers' ), $pick( 'etp_bulk_editor', 'editors' ) );
		}
		$fc = isset( $_REQUEST['etp_bulk_fc'] ) ? sanitize_key( wp_unslash( $_REQUEST['etp_bulk_fc'] ) ) : ''; // phpcs:ignore
		if ( '1' === $fc || '0' === $fc ) {
			update_post_meta( $post_id, '_etp_fact_checked', $fc );
		}
	}

	/* -- Changelog / What's new -------------------------------------------- */

	/** Newest first. Each: version, date, list of changes. */
	public static function changelog() {
		return array(
			array( '2.7.0', '2026-11-16', array(
				'Automatic updates from GitHub: new versions show as "Update available" on the Plugins screen (and Network Admin on multisite), with one-click Update now and optional auto-updates.',
			) ),
			array( '2.6.0', '2026-11-16', array(
				'Page builders (Breakdance, Bricks, Elementor, Beaver Themer, Divi, Oxygen): the automatic bar, sources box, author box and "Ask" box are moved into the post content area, so they no longer show above the hero or full width.',
				'New optional setting "Post content area" (Settings → Display) for builders that are not detected.',
			) ),
			array( '2.5.1', '2026-11-16', array(
				'Drop-in safe for sites that use the Editorial Trust code snippet: the new "About the authors" box is now off until it is switched on (Settings → Display), so the plugin looks exactly like the snippet after the switch.',
			) ),
			array( '2.5.0', '2026-11-13', array(
				"New What's new page with the full version history.",
				'"Changelog" link next to the plugin on the Plugins page.',
				'After an update, a notice says which version is installed, with a link to what changed.',
				'readme.txt and CHANGELOG.md included in the plugin folder.',
			) ),
			array( '2.4.0', '2026-11-13', array(
				'Tools: export all experts to a .json file and import them on another site (details, links and profile photos; team pages matched by page address).',
			) ),
			array( '2.3.0', '2026-11-13', array(
				'Custom profile URL: new "Open this link in a new tab" option (ticked automatically for links to another website).',
				'Existing team page list shows published pages only, under their parent page (e.g. Meet the Team → Luke Kane).',
				'Example names changed to John / John Doe.',
			) ),
			array( '2.2.1', '2026-11-12', array(
				'Article cards on profile and team pages say "Published" unless a real "Last updated" date is set.',
			) ),
			array( '2.2.0', '2026-11-12', array(
				'New Editorial Trust admin menu: Dashboard, All experts, Add expert, Settings, Skin & design, Tools, Help.',
				'Dashboard with coverage figures, setup checklist, latest articles without an author and an expert table.',
				'Skin & design page with Brand CSS code editor and live preview (desktop, tablet, mobile).',
				'Tools: CSV import of author / editor / reviewer / fact checked (with preview), CSV export, bulk assign, settings and skin export / import.',
				'Posts list: Editorial trust filter (no author, no reviewer, not fact checked, complete) and Bulk Edit fields.',
			) ),
			array( '2.1.1', '2026-11-11', array(
				'Fix: "How we checked" panel could be cut off when page scripts load late (caching plugins).',
			) ),
			array( '2.1.0', '2026-11-11', array(
				'"Edited by" role, "About the authors" box, "Published" label until a real update date is set.',
				'Brand CSS setting, bold lead-ins and heading options for the fact check list.',
			) ),
			array( '2.0.0', '2026-11-11', array(
				'First plugin release, built from the Editorial Trust code snippet (runs alongside it safely; the snippet pauses itself).',
			) ),
		);
	}

	public static function changelog_page() {
		?>
		<div class="wrap etp-adm etp-settings">
			<?php self::header( __( "What's new", 'editorial-trust' ), sprintf( /* translators: %s version */ __( 'Installed version: %s', 'editorial-trust' ), Etpro::VERSION ) ); ?>
			<?php foreach ( self::changelog() as $i => $rel ) : ?>
				<section class="etp-adm-card">
					<h2><?php echo esc_html( 'Version ' . $rel[0] ); ?> <span class="etp-adm-pill"><?php echo esc_html( wp_date( 'j M Y', strtotime( $rel[1] . ' 12:00:00' ) ) ); ?></span><?php if ( Etpro::VERSION === $rel[0] ) : ?> <span class="etp-adm-tag is-ok"><?php esc_html_e( 'Installed', 'editorial-trust' ); ?></span><?php endif; ?></h2>
					<ul class="etp-adm-help"><?php foreach ( $rel[2] as $line ) : ?><li><?php echo esc_html( $line ); ?></li><?php endforeach; ?></ul>
				</section>
			<?php endforeach; ?>
		</div>
		<?php
	}

	public static function plugin_links( $links, $file ) {
		if ( false !== strpos( $file, 'editorial-trust.php' ) ) {
			$links[] = '<a href="' . esc_url( self::url( 'etp-changelog' ) ) . '">' . esc_html__( 'Changelog', 'editorial-trust' ) . '</a>';
		}
		return $links;
	}

	public static function update_notice() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$seen = (string) get_user_meta( get_current_user_id(), 'etp_seen_version', true );
		if ( Etpro::VERSION === $seen ) {
			return;
		}
		$log   = self::changelog();
		$first = $log[0];
		echo '<div class="notice notice-info is-dismissible"><p><strong>' . esc_html( sprintf( /* translators: %s version */ __( 'Editorial Trust updated to %s.', 'editorial-trust' ), Etpro::VERSION ) ) . '</strong> ';
		echo esc_html( $first[2][0] ) . ' <a href="' . esc_url( self::url( 'etp-changelog' ) ) . '">' . esc_html__( "See what's new", 'editorial-trust' ) . '</a> · <a href="' . esc_url( wp_nonce_url( add_query_arg( 'etp_seen', 1 ), 'etp_seen' ) ) . '">' . esc_html__( 'Dismiss', 'editorial-trust' ) . '</a></p></div>';
	}

	public static function mark_seen() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$ok   = 'etp-changelog' === $page || ( isset( $_GET['etp_seen'], $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'etp_seen' ) );
		if ( $ok && is_user_logged_in() ) {
			update_user_meta( get_current_user_id(), 'etp_seen_version', Etpro::VERSION );
			if ( isset( $_GET['etp_seen'] ) ) {
				wp_safe_redirect( remove_query_arg( array( 'etp_seen', '_wpnonce' ) ) );
				exit;
			}
		}
	}

	/* -- Help ---------------------------------------------------------------- */

	public static function help() {
		?>
		<div class="wrap etp-adm etp-settings">
			<?php self::header( __( 'Help & shortcodes', 'editorial-trust' ) ); ?>
			<section class="etp-adm-card">
				<h2><?php esc_html_e( 'Quick start', 'editorial-trust' ); ?></h2>
				<ol class="etp-adm-steps">
					<li><?php esc_html_e( 'Skin & design: set the brand colour and paste the site\'s Brand CSS.', 'editorial-trust' ); ?></li>
					<li><?php esc_html_e( 'Settings: wording of the fact check panel, author box and dates.', 'editorial-trust' ); ?></li>
					<li><?php esc_html_e( 'Add expert: one profile per author, editor and reviewer. Tick "Ready to show on the website" when complete.', 'editorial-trust' ); ?></li>
					<li><?php esc_html_e( 'Assign people: on each article (Editorial trust box), in bulk from the Posts list (Bulk actions → Edit), or from a spreadsheet (Tools → Import assignments).', 'editorial-trust' ); ?></li>
					<li><?php esc_html_e( 'Check one article on desktop and mobile, and in Google\'s Rich Results Test.', 'editorial-trust' ); ?></li>
				</ol>
			</section>
			<section class="etp-adm-card">
				<h2><?php esc_html_e( 'Shortcodes', 'editorial-trust' ); ?></h2>
				<table class="widefat striped etp-shortcodes">
					<tbody>
						<tr><td><code>[etp_trust_bar]</code><br><code>[etp_trust_bar style="dark" card="no"]</code></td><td><?php esc_html_e( 'Author / editor / reviewer / fact checked bar for the current article (for theme or page-builder post templates).', 'editorial-trust' ); ?></td></tr>
						<tr><td><code>[etp_author_box]</code></td><td><?php esc_html_e( '"About the authors" box for the current article.', 'editorial-trust' ); ?></td></tr>
						<tr><td><code>[etp_sources]</code></td><td><?php esc_html_e( 'Sources and further reading box (with update history).', 'editorial-trust' ); ?></td></tr>
						<tr><td><code>[etp_history]</code></td><td><?php esc_html_e( 'Update history on its own.', 'editorial-trust' ); ?></td></tr>
						<tr><td><code>[etp_cta]</code></td><td><?php esc_html_e( '"Have a question about this article?" link.', 'editorial-trust' ); ?></td></tr>
						<tr><td><code>[etp_profile person="john-doe"]</code></td><td><?php esc_html_e( 'Full profile with article lists. person = expert slug, ID or name; omit on a linked team page.', 'editorial-trust' ); ?></td></tr>
						<tr><td><code>[etp_expertise person="…"]</code></td><td><?php esc_html_e( 'Areas of expertise only.', 'editorial-trust' ); ?></td></tr>
						<tr><td><code>[etp_articles person="…" role="author|editor|reviewer" limit="6"]</code></td><td><?php esc_html_e( 'Written by / Edited by / Reviewed by list.', 'editorial-trust' ); ?></td></tr>
					</tbody>
				</table>
				<p class="description"><?php esc_html_e( 'Developers: each block\'s HTML passes through the etp_template_html filter (trust-bar, author-box, sources, history, cta, profile, expertise, article-list).', 'editorial-trust' ); ?></p>
			</section>
		</div>
		<?php
	}

	/* -- Admin styles -------------------------------------------------------- */

	private static function css() {
		return <<<'ETP_ADM_CSS'
.etp-adm { max-width: 1240px; }
.etp-adm-head { display: flex; flex-wrap: wrap; align-items: center; gap: 12px 24px; margin: 14px 0 6px; padding: 12px 16px; background: #fff; border: 1px solid #dcdcde; border-radius: 10px; }
.etp-adm-brand { display: flex; align-items: center; gap: 8px; font-size: 15px; font-weight: 600; }
.etp-adm-brand .dashicons { color: #2271b1; }
.etp-adm-brand em { font-style: normal; font-size: 11px; font-weight: 500; color: #646970; background: #f0f0f1; padding: 2px 6px; border-radius: 99px; }
.etp-adm-tabs { display: flex; flex-wrap: wrap; gap: 4px; }
.etp-adm-tabs a { padding: 6px 12px; border-radius: 6px; color: #1d2327; text-decoration: none; font-weight: 500; }
.etp-adm-tabs a:hover { background: #f0f0f1; color: #1d2327; }
.etp-adm-tabs a.is-active { background: #2271b1; color: #fff; }
.etp-adm-title { margin: 18px 0 2px !important; }
.etp-adm-intro { margin: 0 0 16px; color: #50575e; font-size: 14px; }
.etp-adm-card { background: #fff; border: 1px solid #dcdcde; border-radius: 10px; padding: 18px 20px; margin: 0 0 18px; }
.etp-adm-card > h2:first-child { margin: 0 0 12px; padding: 0; border: 0; display: flex; align-items: center; gap: 8px; }
.etp-adm .etp-adm-card h2 { margin-top: 0; padding-top: 0; border-top: 0; }
.etp-adm-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 14px; margin: 0 0 18px; }
.etp-adm-stat { position: relative; display: block; overflow: hidden; padding: 16px 18px 20px; background: #fff; border: 1px solid #dcdcde; border-radius: 10px; color: #1d2327; text-decoration: none; }
.etp-adm-stat:hover { border-color: #2271b1; color: #1d2327; }
.etp-adm-stat strong { display: block; font-size: 30px; line-height: 1.1; font-weight: 700; }
.etp-adm-stat strong small { font-size: 16px; color: #646970; font-weight: 500; }
.etp-adm-stat span { display: block; margin-top: 4px; color: #50575e; }
.etp-adm-stat i { position: absolute; left: 0; bottom: 0; height: 4px; width: var(--w, 0); background: #2271b1; }
.etp-adm-cols { display: grid; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); gap: 18px; }
.etp-adm-pill { font-size: 12px; font-weight: 600; background: #f0f6fc; color: #2271b1; padding: 2px 8px; border-radius: 99px; }
.etp-adm-checks, .etp-adm-links, .etp-adm-help, .etp-adm-errors { margin: 0; padding: 0; list-style: none; }
.etp-adm-checks li { display: flex; align-items: center; gap: 8px; padding: 7px 0; border-bottom: 1px solid #f0f0f1; }
.etp-adm-checks li .dashicons { color: #a7aaad; }
.etp-adm-checks li.is-done .dashicons { color: #00a32a; }
.etp-adm-checks li.is-done a { color: #50575e; text-decoration: line-through; }
.etp-adm-actions { display: flex; flex-wrap: wrap; gap: 8px; margin: 14px 0 0; }
.etp-adm-links li { display: flex; justify-content: space-between; gap: 12px; padding: 7px 0; border-bottom: 1px solid #f0f0f1; }
.etp-adm-links li span { color: #646970; white-space: nowrap; }
.etp-adm-ok { color: #00a32a; font-weight: 500; }
.etp-adm-help li { margin: 0 0 4px; padding-left: 14px; position: relative; color: #50575e; }
.etp-adm-help li::before { content: "•"; position: absolute; left: 0; }
.etp-adm-errors li { color: #b32d2e; margin: 0 0 4px; }
.etp-adm-table .num { width: 80px; text-align: center; }
.etp-adm-person { display: flex; align-items: center; gap: 10px; }
.etp-adm-person .etp-avatar { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; display: inline-flex; align-items: center; justify-content: center; background: #f0f0f1; font-size: 12px; font-weight: 600; }
.etp-adm-tag { display: inline-block; padding: 2px 8px; border-radius: 99px; background: #f0f0f1; color: #50575e; font-size: 12px; font-weight: 500; }
.etp-adm-tag.is-ok { background: #edfaef; color: #007017; }
.etp-adm-skin { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.1fr); gap: 18px; align-items: start; }
.etp-adm-skin .form-table { table-layout: fixed; }
.etp-adm-skin .form-table th { width: 118px; padding-right: 14px; }
.etp-adm-skin .form-table td { overflow: hidden; }
.etp-adm-skin .CodeMirror { height: 420px; border: 1px solid #dcdcde; border-radius: 6px; }
.etp-adm-preview { position: sticky; top: 46px; }
.etp-adm-preview__bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; }
.etp-adm-preview__bar h2 { margin: 0 !important; }
.etp-adm-devices { display: flex; gap: 4px; }
.etp-adm-devices .button.is-active { background: #2271b1; border-color: #2271b1; color: #fff; }
.etp-adm-frame { margin-top: 12px; padding: 24px 16px; background: #f6f7f7; border-radius: 8px; overflow: auto; max-height: 75vh; }
.etp-adm-frame__inner { max-width: 100%; margin: 0 auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.08); transition: max-width .25s ease; font-size: 16px; line-height: 1.6; color: #1d2327; }
.etp-adm-lorem { color: #8c8f94; margin: 0 0 1.5em; }
.etp-adm-steps li { margin-bottom: 6px; }
.etp-bulk .inline-edit-group { display: block; margin: 4px 0; }
.etp-bulk .title { display: inline-block; width: 7.5em; }
.etp-bulk .etp-bulk-title { display: block; width: auto; font-weight: 600; margin-bottom: 4px; }
@media (max-width: 1100px) { .etp-adm-skin { grid-template-columns: minmax(0, 1fr); } .etp-adm-preview { position: static; } }
@media (max-width: 782px) { .etp-adm-cols { grid-template-columns: minmax(0, 1fr); } }
ETP_ADM_CSS;
	}
}

/* ======================================================================== */
/* Markup for each block                                                    */
/* ======================================================================== */

class Etpro_Render {

	/** Components already printed on this request: [post_id => [component => true]]. */
	private static $printed = array();

	public static function mark( $post_id, $component ) {
		self::$printed[ (int) $post_id ][ $component ] = true;
	}

	public static function printed( $post_id, $component ) {
		return ! empty( self::$printed[ (int) $post_id ][ $component ] );
	}

	private static function uid( $prefix ) {
		static $n = 0;
		$n++;
		return $prefix . '-' . $n;
	}

	/** Lets developers change any block's HTML. */
	private static function out( $name, $html, $vars = array() ) {
		return apply_filters( 'etp_template_html', $html, $name, $vars );
	}

	private static function person_line( $p, $label, $role, $size = 44, $lazy = true, $class = 'etp-step' ) {
		ob_start();
		?>
		<a class="<?php echo esc_attr( $class ); ?>__photo" href="<?php echo esc_url( $p['url'] ); ?>"<?php echo Etpro::link_attrs( $p ); // phpcs:ignore ?> tabindex="-1" aria-hidden="true"><?php echo Etpro::avatar_badged( $p, $size, $role, $lazy ); // phpcs:ignore ?></a>
		<span class="<?php echo esc_attr( $class ); ?>__text">
			<span class="<?php echo esc_attr( $class ); ?>__label"><?php echo $label; // phpcs:ignore -- escaped by caller. ?></span>
			<span class="<?php echo esc_attr( $class ); ?>__name"><a href="<?php echo esc_url( $p['url'] ); ?>"<?php echo Etpro::link_attrs( $p, 'author' === $role ); // phpcs:ignore ?>><?php echo esc_html( $p['name'] ); ?></a><?php echo Etpro::linkedin_link( $p ); // phpcs:ignore ?></span>
			<?php if ( $p['job_title'] ) : ?>
				<span class="<?php echo esc_attr( $class ); ?>__role"><?php echo esc_html( $p['job_title'] ); ?></span>
			<?php endif; ?>
		</span>
		<?php
		return ob_get_clean();
	}

	/* ------------------------------------------------------------------ */
	/* Trust bar: Written → Reviewed → Fact checked                        */
	/* ------------------------------------------------------------------ */

	public static function trust_bar( $post_id, $args = array() ) {
		$a = Etpro::article( $post_id );
		if ( ! $a || $a['hide_trust_bar'] || ! $a['author'] ) {
			return '';
		}
		$style = isset( $args['style'] ) && in_array( $args['style'], array( 'light', 'dark' ), true ) ? $args['style'] : Etpro::get( 'trust_bar_style' );
		$card  = (bool) Etpro::get( 'trust_bar_card' );
		if ( isset( $args['card'] ) && '' !== (string) $args['card'] ) {
			$card = ! in_array( strtolower( (string) $args['card'] ), array( '0', 'no', 'false', 'off' ), true );
		}
		$fc          = $a['fact_checked'] ? self::factcheck_vars( $a ) : null;
		$panel_id    = self::uid( 'etp-fc' );
		$pub_mode    = Etpro::get( 'published_label' ) && ! $a['has_updated'];
		$date_ts     = $pub_mode ? get_post_timestamp( $a['post_id'], 'date' ) : $a['updated_ts'];
		$updated     = Etpro::format_date( $date_ts );
		$updated_iso = wp_date( 'Y-m-d', $date_ts );
		$author      = $a['author'];
		$reviewer    = $a['reviewer'];
		/* translators: %s: date */
		$updated_lbl = sprintf( $pub_mode ? esc_html__( 'Published %s', 'editorial-trust' ) : esc_html__( 'Updated %s', 'editorial-trust' ), '<time datetime="' . esc_attr( $updated_iso ) . '">' . esc_html( $updated ) . '</time>' );

		ob_start();
		?>
<div class="etp etp-trust etp-trust--<?php echo esc_attr( $style ); ?><?php echo $card ? ' etp-trust--card' : ''; ?>" data-etp-trust>
	<div class="etp-trust__clip">
		<div class="etp-trust__row">
			<div class="etp-step etp-step--author">
				<?php echo self::person_line( $author, $updated_lbl, 'author', 44, false ); // phpcs:ignore ?>
			</div>
			<?php if ( $a['editor'] ) : ?>
				<div class="etp-step etp-step--editor">
					<?php echo self::person_line( $a['editor'], esc_html__( 'Edited by', 'editorial-trust' ), 'editor', 44, false ); // phpcs:ignore ?>
				</div>
			<?php endif; ?>
			<?php if ( $reviewer ) : ?>
				<div class="etp-step etp-step--reviewer">
					<?php echo self::person_line( $reviewer, esc_html__( 'Reviewed by', 'editorial-trust' ), 'reviewer', 44, false ); // phpcs:ignore ?>
				</div>
			<?php endif; ?>
			<?php if ( $fc ) : ?>
				<details class="etp-step etp-step--fc etp-fc" data-etp-fc>
					<summary class="etp-fc__toggle" aria-controls="<?php echo esc_attr( $panel_id ); ?>">
						<span class="etp-seal" aria-hidden="true"><?php echo Etpro::icon( 'seal', 26 ); // phpcs:ignore ?></span>
						<span class="etp-step__text">
							<span class="etp-step__name etp-fc__label"><?php echo esc_html( $fc['label'] ); ?></span>
							<?php if ( $fc['hint'] ) : ?>
								<span class="etp-step__hint"><?php echo esc_html( $fc['hint'] ); ?><?php echo Etpro::icon( 'chevron', 14 ); // phpcs:ignore ?></span>
							<?php endif; ?>
						</span>
					</summary>
					<?php echo self::factcheck_panel( $a, $fc, $panel_id, $updated, $updated_iso ); // phpcs:ignore ?>
				</details>
			<?php endif; ?>
		</div>
	</div>
</div>
		<?php
		return self::out( 'trust-bar', ob_get_clean(), array( 'a' => $a, 'style' => $style ) );
	}

	public static function factcheck_vars( $a ) {
		$site     = get_bloginfo( 'name' );
		/* translators: %s site name */
		$reviewer = $a['reviewer'] ? $a['reviewer']['name'] : sprintf( __( 'The %s team', 'editorial-trust' ), $site );
		$summary  = Etpro::tokens( $a['fc_summary'] ? $a['fc_summary'] : Etpro::get( 'fc_summary' ), array(
			'reviewer' => $reviewer,
			'author'   => $a['author'] ? $a['author']['name'] : '',
			'site'     => $site,
		) );
		return array(
			'label'        => Etpro::get( 'fc_label' ),
			'hint'         => Etpro::get( 'fc_hint' ),
			'kicker'       => Etpro::get( 'fc_kicker' ),
			'heading'      => Etpro::get( 'fc_heading' ),
			'summary'      => $summary,
			'items'        => $a['fc_items'],
			'reviewed'     => $a['reviewed'] ? Etpro::format_date( $a['reviewed'], Etpro::get( 'history_date_format' ) ) : '',
			'reviewed_iso' => $a['reviewed'],
			'guidelines'   => Etpro::get( 'fc_guidelines_url' ),
			'guide_text'   => Etpro::get( 'fc_guidelines_label' ),
		);
	}

	/** "Why you can trust this article" – a small certificate with the editorial chain. */
	private static function factcheck_panel( $a, $fc, $panel_id, $updated, $updated_iso ) {
		$who = function ( $people ) {
			foreach ( $people as $p ) {
				echo '<p class="etp-fc__who"><a href="' . esc_url( $p['url'] ) . '"' . Etpro::link_attrs( $p ) . '>' . esc_html( $p['name'] ) . '</a>';
				if ( $p['credentials'] ) {
					echo '<span>' . esc_html( $p['credentials'] ) . '</span>';
				}
				echo '</p>';
			}
		};
		ob_start();
		?>
<div class="etp-fc__panel" id="<?php echo esc_attr( $panel_id ); ?>" role="group" aria-label="<?php echo esc_attr( $fc['heading'] ); ?>" tabindex="-1">
	<div class="etp-fc__head">
		<span class="etp-fc__seal" aria-hidden="true"><?php echo Etpro::icon( 'seal', 34 ); // phpcs:ignore ?></span>
		<span class="etp-fc__titles">
			<?php if ( $fc['kicker'] ) : ?>
				<span class="etp-fc__kicker"><?php echo esc_html( $fc['kicker'] ); ?></span>
			<?php endif; ?>
			<span class="etp-fc__title"><?php echo esc_html( $fc['heading'] ); ?></span>
		</span>
		<button type="button" class="etp-fc__close" data-etp-close aria-label="<?php esc_attr_e( 'Close', 'editorial-trust' ); ?>"><?php echo Etpro::icon( 'close', 16 ); // phpcs:ignore ?></button>
	</div>
	<div class="etp-fc__body">
		<?php if ( $fc['summary'] ) : ?>
			<p class="etp-fc__summary"><?php echo esc_html( $fc['summary'] ); ?></p>
		<?php endif; ?>
		<ol class="etp-fc__steps">
			<li>
				<span class="etp-fc__node"><?php echo Etpro::icon( 'pen', 13 ); // phpcs:ignore ?></span>
				<div>
					<?php $pub_mode = Etpro::get( 'published_label' ) && ! $a['has_updated']; ?>
					<p class="etp-fc__step-label"><?php echo $pub_mode ? esc_html__( 'Written by', 'editorial-trust' ) : esc_html__( 'Written and updated by', 'editorial-trust' ); ?></p>
					<?php $who( $a['authors'] ); ?>
					<p class="etp-fc__when"><?php echo $pub_mode ? esc_html__( 'Published', 'editorial-trust' ) : esc_html__( 'Updated', 'editorial-trust' ); ?> <time datetime="<?php echo esc_attr( $updated_iso ); ?>"><?php echo esc_html( $updated ); ?></time></p>
				</div>
			</li>
			<?php if ( $a['editors'] ) : ?>
				<li>
					<span class="etp-fc__node"><?php echo Etpro::icon( 'pen', 13 ); // phpcs:ignore ?></span>
					<div>
						<p class="etp-fc__step-label"><?php esc_html_e( 'Edited by', 'editorial-trust' ); ?></p>
						<?php $who( $a['editors'] ); ?>
					</div>
				</li>
			<?php endif; ?>
			<?php if ( $a['reviewers'] ) : ?>
				<li>
					<span class="etp-fc__node"><?php echo Etpro::icon( 'check', 13 ); // phpcs:ignore ?></span>
					<div>
						<p class="etp-fc__step-label"><?php esc_html_e( 'Reviewed by', 'editorial-trust' ); ?></p>
						<?php $who( $a['reviewers'] ); ?>
					</div>
				</li>
			<?php endif; ?>
			<li class="is-done">
				<span class="etp-fc__node"><?php echo Etpro::icon( 'check', 13 ); // phpcs:ignore ?></span>
				<div>
					<p class="etp-fc__step-label"><?php echo esc_html( $fc['label'] ); ?></p>
					<?php if ( $fc['reviewed'] ) : ?>
						<p class="etp-fc__when"><?php esc_html_e( 'Last reviewed', 'editorial-trust' ); ?> <time datetime="<?php echo esc_attr( $fc['reviewed_iso'] ); ?>"><?php echo esc_html( $fc['reviewed'] ); ?></time></p>
					<?php else : ?>
						<p class="etp-fc__when"><?php esc_html_e( 'Checked before publishing', 'editorial-trust' ); ?></p>
					<?php endif; ?>
				</div>
			</li>
		</ol>
		<?php if ( $fc['items'] ) : ?>
			<div class="etp-fc__checked">
				<?php if ( Etpro::get( 'fc_items_heading' ) ) : ?>
					<p class="etp-fc__subhead"><?php echo esc_html( Etpro::get( 'fc_items_heading' ) ); ?></p>
				<?php endif; ?>
				<ul class="etp-fc__list">
					<?php foreach ( $fc['items'] as $item ) : ?>
						<?php if ( Etpro::get( 'fc_bold_leadins' ) && preg_match( '/^([^.]{2,80}\.)\s*(.*)$/s', $item, $lead ) ) : ?>
							<li><strong><?php echo esc_html( $lead[1] ); ?></strong> <?php echo esc_html( $lead[2] ); ?></li>
						<?php else : ?>
							<li><?php echo esc_html( $item ); ?></li>
						<?php endif; ?>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
		<?php if ( $fc['guidelines'] ) : ?>
			<a class="etp-fc__guidelines" href="<?php echo esc_url( $fc['guidelines'] ); ?>"><span><?php echo esc_html( $fc['guide_text'] ); ?></span><?php echo Etpro::icon( 'arrow', 16 ); // phpcs:ignore ?></a>
		<?php endif; ?>
	</div>
</div>
		<?php
		return ob_get_clean();
	}

	/* ------------------------------------------------------------------ */
	/* Sources and further reading                                          */
	/* ------------------------------------------------------------------ */

	public static function sources( $post_id, $args = array() ) {
		$a = Etpro::article( $post_id );
		if ( ! $a ) {
			return '';
		}
		$history = self::history_html( $a );
		if ( ! $a['sources'] ) {
			return $history ? '<div class="etp-history-wrap">' . $history . '</div>' : '';
		}
		$skip       = $a['hide_disclaimer'] || ( ! empty( $args['skip_disclaimer'] ) && ! $a['src_disclaimer'] );
		$disclaimer = $skip ? '' : ( $a['src_disclaimer'] ? $a['src_disclaimer'] : Etpro::get( 'src_disclaimer' ) );
		$heading_id = self::uid( 'etp-sources-h' );
		$intro      = $a['src_intro'] ? $a['src_intro'] : Etpro::get( 'src_intro' );
		$eyebrow    = Etpro::get( 'src_eyebrow' );
		$new_tab    = (bool) Etpro::get( 'src_new_tab' );
		$people     = ( ! empty( $a['authors'] ) || ! empty( $a['editors'] ) || ! empty( $a['reviewers'] ) ) && ! Etpro::get( 'auto_author_box' );

		ob_start();
		?>
<section class="etp etp-sources" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>">
	<div class="etp-sources__head">
		<?php if ( $eyebrow ) : ?>
			<p class="etp-eyebrow"><?php echo Etpro::icon( 'book', 16 ); // phpcs:ignore ?><span><?php echo esc_html( $eyebrow ); ?></span></p>
		<?php endif; ?>
		<h2 class="etp-sources__heading" id="<?php echo esc_attr( $heading_id ); ?>"><?php echo esc_html( Etpro::get( 'src_heading' ) ); ?></h2>
		<?php if ( $intro ) : ?>
			<p class="etp-sources__intro"><?php echo esc_html( $intro ); ?></p>
		<?php endif; ?>
	</div>

	<div class="etp-sources__body<?php echo $people ? '' : ' etp-sources__body--solo'; ?>">
		<?php if ( $people ) : ?>
			<div class="etp-sources__people">
				<?php foreach ( $a['authors'] as $p ) : ?>
					<div class="etp-pcard"><?php echo self::person_line( $p, esc_html__( 'Written by', 'editorial-trust' ), 'author', 52, true, 'etp-pcard' ); // phpcs:ignore ?></div>
				<?php endforeach; ?>
				<?php foreach ( $a['editors'] as $p ) : ?>
					<div class="etp-pcard"><?php echo self::person_line( $p, esc_html__( 'Edited by', 'editorial-trust' ), 'editor', 52, true, 'etp-pcard' ); // phpcs:ignore ?></div>
				<?php endforeach; ?>
				<?php foreach ( $a['reviewers'] as $p ) : ?>
					<div class="etp-pcard"><?php echo self::person_line( $p, esc_html__( 'Reviewed by', 'editorial-trust' ), 'reviewer', 52, true, 'etp-pcard' ); // phpcs:ignore ?></div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<div class="etp-sources__list">
			<h3 class="etp-sources__list-heading"><span><?php echo esc_html( Etpro::get( 'src_list_heading' ) ); ?></span><span class="etp-sources__count"><?php echo (int) count( $a['sources'] ); ?></span></h3>
			<ol class="etp-refs">
				<?php foreach ( $a['sources'] as $i => $s ) : ?>
					<li>
						<a class="etp-ref" href="<?php echo esc_url( $s['url'] ); ?>"<?php echo $new_tab ? ' target="_blank" rel="noopener"' : ''; ?>>
							<span class="etp-ref__num" aria-hidden="true"><?php echo esc_html( str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
							<span class="etp-ref__text">
								<span class="etp-ref__title"><?php echo esc_html( $s['title'] ); ?></span>
								<span class="etp-ref__domain"><?php echo esc_html( Etpro::domain( $s['url'] ) ); ?><?php if ( $new_tab ) : ?><span class="etp-sr"> <?php esc_html_e( '(opens in a new tab)', 'editorial-trust' ); ?></span><?php endif; ?></span>
							</span>
							<span class="etp-ref__go"><?php echo Etpro::icon( 'external', 16 ); // phpcs:ignore ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
	</div>

	<?php if ( $disclaimer ) : ?>
		<p class="etp-sources__disclaimer"><?php echo Etpro::icon( 'info', 16 ); // phpcs:ignore ?><span><?php echo esc_html( $disclaimer ); ?></span></p>
	<?php endif; ?>

	<?php echo $history; // phpcs:ignore ?>
</section>
		<?php
		return self::out( 'sources', ob_get_clean(), array( 'a' => $a ) );
	}

	/* ------------------------------------------------------------------ */
	/* Author box: every contributor at the end of the article              */
	/* ------------------------------------------------------------------ */

	public static function author_box( $post_id ) {
		$a = Etpro::article( $post_id );
		if ( ! $a || ( ! $a['authors'] && ! $a['editors'] && ! $a['reviewers'] ) ) {
			return '';
		}
		$groups  = array(
			array( $a['authors'], __( 'Written by', 'editorial-trust' ), 'author' ),
			array( $a['editors'], __( 'Edited by', 'editorial-trust' ), 'editor' ),
			array( $a['reviewers'], __( 'Reviewed by', 'editorial-trust' ), 'reviewer' ),
		);
		$heading = Etpro::get( 'author_box_heading' );
		$hid     = self::uid( 'etp-abox-h' );
		ob_start();
		?>
<section class="etp etp-abox"<?php echo $heading ? ' aria-labelledby="' . esc_attr( $hid ) . '"' : ' aria-label="' . esc_attr__( 'About the authors', 'editorial-trust' ) . '"'; ?>>
	<?php if ( $heading ) : ?>
		<h2 class="etp-abox__heading" id="<?php echo esc_attr( $hid ); ?>"><?php echo esc_html( $heading ); ?></h2>
	<?php endif; ?>
	<div class="etp-abox__grid">
		<?php foreach ( $groups as $g ) : ?>
			<?php foreach ( $g[0] as $p ) : ?>
				<div class="etp-abox__card etp-abox__card--<?php echo esc_attr( $g[2] ); ?>">
					<a class="etp-abox__photo" href="<?php echo esc_url( $p['url'] ); ?>"<?php echo Etpro::link_attrs( $p ); // phpcs:ignore ?> tabindex="-1" aria-hidden="true"><?php echo Etpro::avatar( $p, 72, true ); // phpcs:ignore ?></a>
					<div class="etp-abox__body">
						<p class="etp-abox__role"><?php echo esc_html( $g[1] ); ?></p>
						<p class="etp-abox__name"><a href="<?php echo esc_url( $p['url'] ); ?>"<?php echo Etpro::link_attrs( $p, 'author' === $g[2] ); // phpcs:ignore ?>><?php echo esc_html( $p['name'] ); ?></a><?php echo Etpro::linkedin_link( $p ); // phpcs:ignore ?></p>
						<?php if ( $p['job_title'] ) : ?>
							<p class="etp-abox__job"><?php echo esc_html( $p['job_title'] ); ?></p>
						<?php endif; ?>
						<?php if ( $p['summary'] ) : ?>
							<p class="etp-abox__summary"><?php echo esc_html( $p['summary'] ); ?></p>
						<?php endif; ?>
						<a class="etp-abox__more" href="<?php echo esc_url( $p['url'] ); ?>"<?php echo Etpro::link_attrs( $p ); // phpcs:ignore ?>><span><?php esc_html_e( 'View profile', 'editorial-trust' ); ?></span><?php echo Etpro::icon( 'arrow', 14 ); // phpcs:ignore ?></a>
					</div>
				</div>
			<?php endforeach; ?>
		<?php endforeach; ?>
	</div>
</section>
		<?php
		return self::out( 'author-box', ob_get_clean(), array( 'a' => $a ) );
	}

	public static function history_html( $a ) {
		if ( ! $a['show_history'] || ! $a['history'] ) {
			return '';
		}
		$format = Etpro::get( 'history_date_format' );
		ob_start();
		?>
<details class="etp etp-history">
	<summary><?php echo Etpro::icon( 'history', 16 ); // phpcs:ignore ?><span><?php echo esc_html( Etpro::get( 'history_heading' ) ); ?></span><span class="etp-history__count"><?php echo (int) count( $a['history'] ); ?></span><?php echo Etpro::icon( 'chevron', 14 ); // phpcs:ignore ?></summary>
	<ol class="etp-history__list">
		<?php foreach ( $a['history'] as $h ) : ?>
			<li>
				<time datetime="<?php echo esc_attr( $h['date'] ); ?>"><?php echo esc_html( Etpro::format_date( $h['date'], $format ) ); ?></time>
				<span><?php echo esc_html( $h['note'] ); ?></span>
			</li>
		<?php endforeach; ?>
	</ol>
</details>
		<?php
		return self::out( 'history', ob_get_clean(), array( 'a' => $a ) );
	}

	public static function history( $post_id ) {
		$a = Etpro::article( $post_id );
		return $a ? self::history_html( $a ) : '';
	}

	/* ------------------------------------------------------------------ */
	/* "Have a question about this article? Ask John"                     */
	/* ------------------------------------------------------------------ */

	public static function cta( $post_id ) {
		$a = Etpro::article( $post_id );
		if ( ! $a || 'off' === $a['cta_mode'] || ! $a['cta_person'] ) {
			return '';
		}
		$p        = $a['cta_person'];
		$custom   = 'custom' === $a['cta_mode'];
		$question = $custom && $a['cta_question'] ? $a['cta_question'] : Etpro::get( 'cta_question' );
		$link     = $custom && $a['cta_link_text'] ? $a['cta_link_text'] : Etpro::get( 'cta_link_text' );
		$link     = Etpro::tokens( $link, array( 'first_name' => $p['first_name'], 'name' => $p['name'] ) );
		$href     = self::enquiry_url( $a['title'], $a['url'], $p );

		ob_start();
		?>
<aside class="etp etp-cta" aria-label="<?php esc_attr_e( 'Ask the author', 'editorial-trust' ); ?>">
	<span class="etp-cta__avatar"><?php echo Etpro::avatar( $p, 52 ); // phpcs:ignore ?><span class="etp-cta__ping" aria-hidden="true"><?php echo Etpro::icon( 'message', 11 ); // phpcs:ignore ?></span></span>
	<span class="etp-cta__text">
		<span class="etp-cta__q"><?php echo esc_html( $question ); ?></span>
		<span class="etp-cta__who"><?php echo esc_html( $p['name'] . ( $p['job_title'] ? ' · ' . $p['job_title'] : '' ) ); ?></span>
	</span>
	<a class="etp-btn etp-cta__link" href="<?php echo esc_url( $href ); ?>" data-etp-cta data-article="<?php echo esc_attr( wp_strip_all_tags( $a['title'] ) ); ?>" data-article-url="<?php echo esc_url( $a['url'] ); ?>" data-expert="<?php echo esc_attr( $p['name'] ); ?>"><span><?php echo esc_html( $link ); ?></span><?php echo Etpro::icon( 'arrow', 16 ); // phpcs:ignore ?></a>
</aside>
		<?php
		return self::out( 'cta', ob_get_clean(), array( 'a' => $a, 'person' => $p ) );
	}

	/** Enquiry URL carrying the article title, URL and expert for any form plugin. */
	public static function enquiry_url( $title, $url, $person ) {
		$base = Etpro::get( 'cta_url' );
		// /contact/ only redirects to /make-an-enquiry/ without parameters; /contact/?… returns 404.
		if ( $base && '/contact' === untrailingslashit( (string) wp_parse_url( $base, PHP_URL_PATH ) ) ) {
			$base = '';
		}
		if ( ! $base ) {
			$contact = get_page_by_path( 'make-an-enquiry' );
			if ( ! $contact ) {
				$contact = get_page_by_path( 'contact' );
			}
			if ( ! $contact ) {
				$contact = get_page_by_path( 'contact-us' );
			}
			$base = $contact ? get_permalink( $contact ) : home_url( '/make-an-enquiry/' );
		}
		$pa = Etpro::get( 'cta_param_article' );
		$pe = Etpro::get( 'cta_param_expert' );
		return add_query_arg( array_map( 'rawurlencode', array(
			$pa          => wp_strip_all_tags( $title ),
			$pa . '_url' => $url,
			$pe          => $person ? $person['name'] : '',
		) ), $base );
	}

	/* ------------------------------------------------------------------ */
	/* Profiles                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * @param array $args sections: comma list of hero,stats,about,experience,expertise,qualifications,written,reviewed
	 *                    heading_level: 1 on a standalone profile page, 2 inside an existing page.
	 */
	public static function profile( $person_id, $args = array() ) {
		$p = Etpro::person( $person_id );
		if ( ! $p || ( ! $p['public'] && ! current_user_can( 'edit_post', $person_id ) ) ) {
			return '';
		}
		$all      = array( 'hero', 'stats', 'about', 'experience', 'expertise', 'qualifications', 'written', 'edited', 'reviewed' );
		$sections = isset( $args['sections'] ) && $args['sections'] ? array_intersect( array_map( 'trim', explode( ',', $args['sections'] ) ), $all ) : $all;
		$show     = function ( $s ) use ( $sections ) {
			return in_array( $s, $sections, true );
		};
		$level    = isset( $args['heading_level'] ) ? max( 1, min( 3, (int) $args['heading_level'] ) ) : 1;
		$name_tag = 'h' . $level;
		$sec_tag  = 'h' . min( 6, $level + 1 );
		/* translators: %s person name */
		$cta_article = sprintf( __( 'Profile: %s', 'editorial-trust' ), $p['name'] );
		$cta_href    = self::enquiry_url( $cta_article, $p['url'], $p );
		$cta_text    = Etpro::tokens( Etpro::get( 'profile_cta_text' ), array( 'first_name' => $p['first_name'], 'name' => $p['name'] ) );
		$bio         = $show( 'about' ) && $p['bio'] ? self::bio_html( $p['bio'] ) : '';
		$experience  = $show( 'experience' ) && $p['experience'] ? wpautop( wp_kses_post( $p['experience'] ) ) : '';
		$stats       = array();
		if ( $show( 'stats' ) ) {
			$w = Etpro_People::count_articles( $person_id, 'author' );
			$r = Etpro_People::count_articles( $person_id, 'reviewer' );
			$e = Etpro_People::count_articles( $person_id, 'editor' );
			if ( $w ) {
				$stats[] = array( $w, _n( 'Article written', 'Articles written', $w, 'editorial-trust' ) );
			}
			if ( $e ) {
				$stats[] = array( $e, _n( 'Article edited', 'Articles edited', $e, 'editorial-trust' ) );
			}
			if ( $r ) {
				$stats[] = array( $r, _n( 'Article reviewed', 'Articles reviewed', $r, 'editorial-trust' ) );
			}
			if ( $stats && $p['expertise'] ) {
				$stats[] = array( count( $p['expertise'] ), _n( 'Area of expertise', 'Areas of expertise', count( $p['expertise'] ), 'editorial-trust' ) );
			}
		}

		ob_start();
		?>
<div class="etp etp-profile">
	<?php if ( $show( 'hero' ) ) : ?>
		<header class="etp-profile__hero">
			<div class="etp-profile__photo"><?php echo Etpro::avatar( $p, 200, true, false ); // phpcs:ignore ?></div>
			<div class="etp-profile__intro">
				<?php if ( $p['job_title'] ) : ?>
					<p class="etp-eyebrow"><span><?php echo esc_html( $p['job_title'] ); ?></span></p>
				<?php endif; ?>
				<<?php echo esc_attr( $name_tag ); ?> class="etp-profile__name"><?php echo esc_html( $p['name'] ); ?></<?php echo esc_attr( $name_tag ); ?>>
				<?php if ( $p['summary'] ) : ?>
					<p class="etp-profile__summary"><?php echo esc_html( $p['summary'] ); ?></p>
				<?php endif; ?>
				<div class="etp-profile__actions">
					<a class="etp-btn" href="<?php echo esc_url( $cta_href ); ?>" data-etp-cta data-article="<?php echo esc_attr( $cta_article ); ?>" data-article-url="<?php echo esc_url( $p['url'] ); ?>" data-expert="<?php echo esc_attr( $p['name'] ); ?>"><span><?php echo esc_html( $cta_text ); ?></span><?php echo Etpro::icon( 'arrow', 16 ); // phpcs:ignore ?></a>
					<?php if ( $p['linkedin'] ) : ?>
						<a class="etp-btn etp-btn--ghost" href="<?php echo esc_url( $p['linkedin'] ); ?>" target="_blank" rel="noopener"><?php echo Etpro::icon( 'linkedin', 16 ); // phpcs:ignore ?><span><?php esc_html_e( 'LinkedIn', 'editorial-trust' ); ?></span></a>
					<?php endif; ?>
				</div>
			</div>
		</header>
	<?php endif; ?>

	<?php if ( $stats ) : ?>
		<ul class="etp-stats">
			<?php foreach ( $stats as $s ) : ?>
				<li><strong><?php echo (int) $s[0]; ?></strong><span><?php echo esc_html( $s[1] ); ?></span></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php if ( $bio ) : ?>
		<section class="etp-profile__section etp-profile__about">
			<<?php echo esc_attr( $sec_tag ); ?> class="etp-section-heading"><?php echo esc_html( sprintf( /* translators: %s first name */ __( 'About %s', 'editorial-trust' ), $p['first_name'] ) ); ?></<?php echo esc_attr( $sec_tag ); ?>>
			<div class="etp-prose"><?php echo $bio; // phpcs:ignore -- kses'd. ?></div>
		</section>
	<?php endif; ?>

	<?php if ( $experience ) : ?>
		<section class="etp-profile__section etp-profile__experience">
			<<?php echo esc_attr( $sec_tag ); ?> class="etp-section-heading"><?php esc_html_e( 'Experience', 'editorial-trust' ); ?></<?php echo esc_attr( $sec_tag ); ?>>
			<div class="etp-prose"><?php echo $experience; // phpcs:ignore -- kses'd. ?></div>
		</section>
	<?php endif; ?>

	<?php echo $show( 'expertise' ) ? self::expertise( $person_id, array( 'heading_level' => $level + 1 ) ) : ''; // phpcs:ignore ?>

	<?php if ( $show( 'qualifications' ) && $p['qualifications'] ) : ?>
		<section class="etp-profile__section etp-profile__quals">
			<<?php echo esc_attr( $sec_tag ); ?> class="etp-section-heading"><?php esc_html_e( 'Qualifications & accreditations', 'editorial-trust' ); ?></<?php echo esc_attr( $sec_tag ); ?>>
			<ul class="etp-quals">
				<?php foreach ( $p['qualifications'] as $q ) : ?>
					<li><span class="etp-quals__icon"><?php echo Etpro::icon( 'award', 18 ); // phpcs:ignore ?></span><span><?php echo esc_html( $q ); ?></span></li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php echo $show( 'written' ) ? self::article_list( $person_id, 'author', array( 'heading_level' => $level + 1 ) ) : ''; // phpcs:ignore ?>
	<?php echo $show( 'edited' ) ? self::article_list( $person_id, 'editor', array( 'heading_level' => $level + 1 ) ) : ''; // phpcs:ignore ?>
	<?php echo $show( 'reviewed' ) ? self::article_list( $person_id, 'reviewer', array( 'heading_level' => $level + 1 ) ) : ''; // phpcs:ignore ?>
</div>
		<?php
		return self::out( 'profile', ob_get_clean(), array( 'p' => $p, 'sections' => $sections ) );
	}

	private static function bio_html( $content ) {
		$content = do_blocks( $content );
		$content = wptexturize( $content );
		$content = wpautop( $content );
		$content = do_shortcode( shortcode_unautop( $content ) );
		return wp_kses_post( $content );
	}

	public static function expertise( $person_id, $args = array() ) {
		$p = Etpro::person( $person_id );
		if ( ! $p || ! $p['expertise'] || ( ! $p['public'] && ! current_user_can( 'edit_post', $person_id ) ) ) {
			return '';
		}
		$heading = isset( $args['heading'] ) ? $args['heading'] : Etpro::get( 'expertise_heading' );
		$tag     = 'h' . ( isset( $args['heading_level'] ) ? max( 2, min( 4, (int) $args['heading_level'] ) ) : 2 );
		ob_start();
		?>
<section class="etp etp-expertise">
	<?php if ( $heading ) : ?>
		<<?php echo esc_attr( $tag ); ?> class="etp-section-heading"><?php echo esc_html( $heading ); ?></<?php echo esc_attr( $tag ); ?>>
	<?php endif; ?>
	<ul class="etp-chips">
		<?php foreach ( $p['expertise'] as $item ) : ?>
			<li><?php echo esc_html( $item ); ?></li>
		<?php endforeach; ?>
	</ul>
</section>
		<?php
		return self::out( 'expertise', ob_get_clean(), array( 'p' => $p ) );
	}

	/** Dynamic Written by / Reviewed by list. */
	public static function article_list( $person_id, $role = 'author', $args = array() ) {
		$p = Etpro::person( $person_id );
		if ( ! $p || ( ! $p['public'] && ! current_user_can( 'edit_post', $person_id ) ) ) {
			return '';
		}
		$role  = in_array( $role, array( 'reviewer', 'editor' ), true ) ? $role : 'author';
		$limit = isset( $args['limit'] ) ? max( 1, (int) $args['limit'] ) : (int) Etpro::get( 'list_limit' );
		$max   = (int) apply_filters( 'etp_article_list_max', 60, $person_id, $role );

		$q = new WP_Query( array(
			'post_type'           => Etpro::post_types(),
			'post_status'         => 'publish',
			'posts_per_page'      => $max,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'meta_query'          => array(
				'relation' => 'AND',
				'person'   => array(
					'key'   => 'reviewer' === $role ? '_etp_reviewer_id' : ( 'editor' === $role ? '_etp_editor_id' : '_etp_author_id' ),
					'value' => (string) (int) $person_id,
				),
				'sort'     => array(
					'key'     => '_etp_sort_date',
					'compare' => 'EXISTS',
				),
			),
			'orderby'             => array( 'sort' => 'DESC', 'date' => 'DESC' ),
		) );
		if ( ! $q->posts ) {
			return '';
		}

		$items = array();
		foreach ( $q->posts as $post ) {
			$a       = Etpro::article( $post->ID );
			$items[] = array(
				'title'   => get_the_title( $post ),
				'url'     => get_permalink( $post ),
				'excerpt' => self::card_excerpt( $post ),
				'date'    => Etpro::format_date( $a['updated_ts'] ),
				'iso'     => wp_date( 'Y-m-d', $a['updated_ts'] ),
				'pub'     => Etpro::get( 'published_label' ) && empty( $a['has_updated'] ),
				'image'   => get_the_post_thumbnail( $post, 'medium_large', array(
					'class'    => 'etp-card__img',
					'loading'  => 'lazy',
					'decoding' => 'async',
					'alt'      => '',
					'sizes'    => '(max-width: 600px) 100vw, 360px',
				) ),
			);
		}
		$tokens  = array( 'first_name' => $p['first_name'], 'name' => $p['name'] );
		$list_id = self::uid( 'etp-list' );
		$labels  = array(
			'author'   => array( 'written_eyebrow', 'written_heading' ),
			'editor'   => array( 'edited_eyebrow', 'edited_heading' ),
			'reviewer' => array( 'reviewed_eyebrow', 'reviewed_heading' ),
		);
		$eyebrow = Etpro::get( $labels[ $role ][0] );
		$heading = Etpro::tokens( Etpro::get( $labels[ $role ][1] ), $tokens );
		$tag     = 'h' . ( isset( $args['heading_level'] ) ? max( 2, min( 4, (int) $args['heading_level'] ) ) : 2 );
		$first   = array_slice( $items, 0, $limit );
		$rest    = array_slice( $items, $limit );
		$read    = __( 'Read article', 'editorial-trust' );

		$card = function ( $item ) use ( $read ) {
			?>
			<li class="etp-card">
				<div class="etp-card__media">
					<?php if ( $item['image'] ) : ?>
						<?php echo $item['image']; // phpcs:ignore ?>
					<?php else : ?>
						<span class="etp-card__placeholder"><?php echo Etpro::icon( 'file', 28 ); // phpcs:ignore ?></span>
					<?php endif; ?>
				</div>
				<div class="etp-card__body">
					<p class="etp-card__date"><?php echo empty( $item['pub'] ) ? esc_html__( 'Updated', 'editorial-trust' ) : esc_html__( 'Published', 'editorial-trust' ); ?> <time datetime="<?php echo esc_attr( $item['iso'] ); ?>"><?php echo esc_html( $item['date'] ); ?></time></p>
					<a class="etp-card__title" href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a>
					<?php if ( $item['excerpt'] ) : ?>
						<p class="etp-card__excerpt"><?php echo esc_html( $item['excerpt'] ); ?></p>
					<?php endif; ?>
					<span class="etp-card__more" aria-hidden="true"><?php echo esc_html( $read ); ?><?php echo Etpro::icon( 'arrow', 14 ); // phpcs:ignore ?></span>
				</div>
			</li>
			<?php
		};

		ob_start();
		?>
<section class="etp etp-articles etp-articles--<?php echo esc_attr( $role ); ?>" aria-labelledby="<?php echo esc_attr( $list_id ); ?>">
	<?php if ( $eyebrow ) : ?>
		<p class="etp-eyebrow"><span><?php echo esc_html( $eyebrow ); ?></span></p>
	<?php endif; ?>
	<<?php echo esc_attr( $tag ); ?> class="etp-section-heading" id="<?php echo esc_attr( $list_id ); ?>"><?php echo esc_html( $heading ); ?> <span class="etp-articles__count"><?php echo (int) count( $items ); ?></span></<?php echo esc_attr( $tag ); ?>>
	<ul class="etp-cards">
		<?php
		foreach ( $first as $item ) {
			$card( $item );
		}
		?>
	</ul>
	<?php if ( $rest ) : ?>
		<details class="etp-cards-more">
			<summary><?php echo esc_html( sprintf( /* translators: %d: number of articles */ _n( 'Show %d more article', 'Show %d more articles', count( $rest ), 'editorial-trust' ), count( $rest ) ) ); ?><?php echo Etpro::icon( 'chevron', 14 ); // phpcs:ignore ?></summary>
			<ul class="etp-cards">
				<?php
				foreach ( $rest as $item ) {
					$card( $item );
				}
				?>
			</ul>
		</details>
	<?php endif; ?>
</section>
		<?php
		return self::out( 'article-list', ob_get_clean(), array( 'p' => $p, 'role' => $role, 'items' => $items ) );
	}

	/** Card summary: manual excerpt, then SEO description, then opening paragraph. */
	public static function card_excerpt( $post ) {
		if ( has_excerpt( $post ) ) {
			$text = $post->post_excerpt;
		} else {
			$text = '';
			foreach ( array( '_yoast_wpseo_metadesc', 'rank_math_description', '_aioseo_description' ) as $key ) {
				$v = trim( (string) get_post_meta( $post->ID, $key, true ) );
				if ( $v && false === strpos( $v, '%' ) && false === strpos( $v, '#' ) ) {
					$text = $v;
					break;
				}
			}
			if ( ! $text ) {
				$raw = preg_replace( '/<!--.*?-->/s', '', $post->post_content );
				$raw = preg_replace( '~<(script|style|h[1-6]|summary|figcaption)\b.*?</\1>~is', ' ', $raw );
				$raw = strip_shortcodes( $raw );
				if ( preg_match_all( '~<p\b[^>]*>(.*?)</p>~is', $raw, $mm ) ) {
					$raw = implode( ' ', $mm[1] );
				}
				$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( preg_replace( '/<[^>]+>/', ' ', $raw ) ) ) );
			}
		}
		$text = html_entity_decode( $text, ENT_QUOTES, get_bloginfo( 'charset' ) );
		return wp_trim_words( $text, (int) apply_filters( 'etp_card_excerpt_words', 26 ), '…' );
	}
}

/* ======================================================================== */
/* Front end: assets, automatic placement, shortcodes, profile pages        */
/* ======================================================================== */

class Etpro_Front {

	private static $rendering_person = false;

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 25 );
		add_action( 'template_redirect', array( __CLASS__, 'person_page' ), 99 );
		add_filter( 'template_include', array( __CLASS__, 'block_person_template' ), 100 );

		add_shortcode( 'etp_trust_bar', array( __CLASS__, 'sc_trust_bar' ) );
		add_shortcode( 'etp_sources', array( __CLASS__, 'sc_sources' ) );
		add_shortcode( 'etp_history', array( __CLASS__, 'sc_history' ) );
		add_shortcode( 'etp_cta', array( __CLASS__, 'sc_cta' ) );
		add_shortcode( 'etp_profile', array( __CLASS__, 'sc_profile' ) );
		add_shortcode( 'etp_expertise', array( __CLASS__, 'sc_expertise' ) );
		add_shortcode( 'etp_articles', array( __CLASS__, 'sc_articles' ) );
		add_shortcode( 'etp_author_box', array( __CLASS__, 'sc_author_box' ) );
	}

	/* -- Assets (printed inline: no files to upload) ------------------------ */

	public static function needs_assets() {
		if ( Etpro::get( 'load_everywhere' ) || is_singular( Etpro::post_types() ) || is_singular( 'etp_person' ) ) {
			return true;
		}
		if ( is_page() && Etpro::people_for_page( get_queried_object_id() ) ) {
			return true;
		}
		$post = get_post( get_queried_object_id() );
		if ( ! $post || ! is_singular() || false === strpos( $post->post_content, '[etp_' ) ) {
			return false;
		}
		foreach ( array( 'etp_trust_bar', 'etp_sources', 'etp_history', 'etp_cta', 'etp_profile', 'etp_expertise', 'etp_articles', 'etp_author_box' ) as $tag ) {
			if ( has_shortcode( $post->post_content, $tag ) ) {
				return true;
			}
		}
		return false;
	}

	public static function assets() {
		wp_register_style( 'etp', false, array(), Etpro::VERSION );
		wp_register_script( 'etp', false, array(), Etpro::VERSION, true );
		wp_add_inline_script( 'etp', 'window.etpConfig=' . wp_json_encode( array(
			'mode'    => Etpro::get( 'cta_mode' ),
			'popup'   => Etpro::get( 'cta_popup_selector' ),
			'place'   => (string) Etpro::get( 'content_selector' ),
			'article' => Etpro::get( 'cta_param_article' ),
			'expert'  => Etpro::get( 'cta_param_expert' ),
		) ) . ';' . Etpro_Assets::js() );

		$prefill = isset( $_GET[ Etpro::get( 'cta_param_article' ) ] ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( self::needs_assets() ) {
			self::ensure_assets();
		} elseif ( $prefill ) {
			wp_enqueue_script( 'etp' ); // Enquiry page: only the small prefill script.
		}
	}

	private static function ensure_assets() {
		if ( ! wp_style_is( 'etp', 'registered' ) ) {
			return;
		}
		if ( ! wp_style_is( 'etp', 'enqueued' ) ) {
			wp_add_inline_style( 'etp', Etpro_Assets::css() . self::css_vars() . "\n" . (string) Etpro::get( 'custom_css' ) );
			wp_enqueue_style( 'etp' );
		}
		if ( ! wp_script_is( 'etp', 'enqueued' ) ) {
			wp_enqueue_script( 'etp' );
		}
	}

	/** Brand colour → full palette, so one setting themes everything. */
	public static function css_vars() {
		$accent = sanitize_hex_color( Etpro::get( 'accent_color' ) );
		$accent = $accent ? $accent : '#d99a00';
		$vars   = array(
			'--etp-accent'        => $accent,
			'--etp-accent-rgb'    => implode( ',', Etpro::rgb( $accent ) ),
			'--etp-accent-soft'   => Etpro::mix( $accent, '#ffffff', 0.88 ),
			'--etp-accent-line'   => Etpro::mix( $accent, '#ffffff', 0.6 ),
			'--etp-accent-ink'    => Etpro::mix( $accent, '#000000', 0.5 ),
			'--etp-accent-bright' => Etpro::mix( $accent, '#ffffff', 0.3 ),
			'--etp-on-accent'     => Etpro::on_color( $accent ),
		);
		$css = '.etp{';
		foreach ( $vars as $k => $v ) {
			$css .= $k . ':' . $v . ';';
		}
		$css .= '}';
		$max = Etpro::get( 'content_max_width' );
		if ( $max ) {
			$css .= '.etp.etp-sources,.etp.etp-cta,.etp-history-wrap{max-width:' . esc_attr( $max ) . ';margin-left:auto;margin-right:auto}';
		}
		return $css;
	}

	/* -- Automatic placement ------------------------------------------------ */

	private static function is_main_content_call() {
		if ( is_admin() || is_feed() || wp_doing_ajax() || doing_filter( 'get_the_excerpt' ) || doing_action( 'wp_head' ) || ! did_action( 'template_redirect' ) ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		$id = (int) get_the_ID();
		return $id && $id === (int) get_queried_object_id();
	}

	public static function filter_content( $content ) {
		if ( ! self::is_main_content_call() ) {
			return $content;
		}
		$id = (int) get_the_ID();

		if ( is_singular( 'etp_person' ) ) {
			if ( true === self::$rendering_person ) {
				return $content; // Classic theme: already rendered by person_page().
			}
			self::ensure_assets();
			// Block themes: our header + profile + footer canvas (level 1), or the theme's own template (level 2).
			return Etpro_Render::profile( $id, array( 'heading_level' => 'block' === self::$rendering_person ? 1 : 2 ) );
		}
		$people = is_page() ? Etpro::people_for_page( $id ) : array();
		if ( $people ) {
			if ( Etpro::get( 'profile_auto_append' ) && false === strpos( $content, 'etp-profile' ) && false === strpos( $content, 'etp-articles' ) ) {
				$sections = (string) apply_filters( 'etp_team_page_sections', Etpro::get( 'team_page_sections' ), $people[0] );
				self::ensure_assets();
				$content .= Etpro_Render::profile( $people[0], array( 'sections' => $sections, 'heading_level' => 1 ) );
			}
			return $content;
		}
		if ( is_singular( Etpro::post_types() ) ) {
			return self::article_content( $content, $id );
		}
		return $content;
	}

	private static function article_content( $content, $id ) {
		$a = Etpro::article( $id );
		if ( ! $a ) {
			return $content;
		}
		self::ensure_assets();

		$cta_pos = Etpro::get( 'cta_position' );
		$cta     = '';
		if ( 'off' !== $cta_pos && ! Etpro_Render::printed( $id, 'cta' ) && false === strpos( $content, 'data-etp-cta' ) ) {
			$cta = preg_replace( '/<aside class="etp etp-cta"/', '<aside data-etp-auto="end" class="etp etp-cta"', Etpro_Render::cta( $id ), 1 );
		}

		// 1. Sources (worked out on the original content so wrappers are detected properly).
		$pos = $a['src_position'];
		if ( Etpro::get( 'auto_sources' ) && ! in_array( $pos, array( 'manual', 'off' ), true ) && ! Etpro_Render::printed( $id, 'sources' ) && false === strpos( $content, 'etp-sources' ) ) {
			$after_cta = ( 'after_sources' === $cta_pos && $cta ) ? $cta : '';
			$build     = function ( $disclaimer_follows ) use ( $id, $after_cta ) {
				$box = preg_replace( '/<section class="etp etp-sources"/', '<section data-etp-auto="end" class="etp etp-sources"', Etpro_Render::sources( $id, array( 'skip_disclaimer' => $disclaimer_follows ) ), 1 );
				return $box ? $box . $after_cta : '';
			};
			if ( 'end' === $pos ) {
				$box      = $build( false );
				$content .= $box;
			} else {
				$content = Etpro_Placement::insert( $content, $build );
				$box     = false !== strpos( $content, 'etp-sources' ) || false !== strpos( $content, 'etp-history-wrap' );
			}
			if ( $box && $after_cta ) {
				$cta = '';
			}
		}

		// 2. Trust bar at the top.
		$top = '';
		if ( Etpro::get( 'auto_trust_bar' ) && ! Etpro_Render::printed( $id, 'trust_bar' ) && false === strpos( $content, 'data-etp-trust' ) ) {
			$top = preg_replace( '/data-etp-trust/', 'data-etp-trust data-etp-auto="top"', Etpro_Render::trust_bar( $id ), 1 );
		}
		if ( 'after_trust' === $cta_pos && $cta && ( $top || Etpro_Render::printed( $id, 'trust_bar' ) ) ) {
			$top .= $cta;
			$cta  = '';
		}

		// 3. Author box at the end of every article.
		$box = '';
		if ( Etpro::get( 'auto_author_box' ) && ! Etpro_Render::printed( $id, 'author_box' ) && false === strpos( $content, 'etp-abox' ) ) {
			$box = preg_replace( '/<section class="etp etp-abox"/', '<section data-etp-auto="end" class="etp etp-abox"', Etpro_Render::author_box( $id ), 1 );
		}

		// 4. Anything left goes at the end.
		return $top . $content . $box . $cta;
	}

	/* -- Expert profile page (experts without an existing team page) -------- */

	public static function person_page() {
		if ( ! is_singular( 'etp_person' ) || is_404() ) {
			return;
		}
		if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			return;
		}
		if ( locate_template( array( 'single-etp_person.php' ) ) ) {
			return; // Theme provides its own.
		}
		self::$rendering_person = true;
		get_header();
		echo '<main id="primary" class="etp-single-person site-main"><div class="etp-single-person__inner">';
		while ( have_posts() ) {
			the_post();
			echo Etpro_Render::profile( get_the_ID(), array( 'heading_level' => 1 ) ); // phpcs:ignore
		}
		echo '</div></main>';
		get_footer();
		exit;
	}

	/**
	 * Block themes: render the profile between the theme's header and footer
	 * parts, instead of the single-post template (no duplicate title, featured
	 * image or "Written by" line). A theme template named single-etp_person wins.
	 */
	public static function block_person_template( $template ) {
		if ( ! is_singular( 'etp_person' ) || is_404() || ! function_exists( 'wp_is_block_theme' ) || ! wp_is_block_theme() ) {
			return $template;
		}
		if ( function_exists( 'get_block_template' ) && get_block_template( get_stylesheet() . '//single-etp_person' ) ) {
			return $template;
		}
		$canvas = ABSPATH . WPINC . '/template-canvas.php';
		if ( ! file_exists( $canvas ) ) {
			return $template;
		}
		$GLOBALS['_wp_current_template_content'] = '<!-- wp:template-part {"slug":"header","tagName":"header"} /--><main class="etp-single-person wp-block-group"><div class="etp-single-person__inner"><!-- wp:post-content /--></div></main><!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->';
		self::$rendering_person = 'block';
		return $canvas;
	}

	/* -- Shortcodes --------------------------------------------------------- */

	private static function context_post_id( $atts ) {
		$id = isset( $atts['id'] ) ? absint( $atts['id'] ) : 0;
		return $id ? $id : (int) get_the_ID();
	}

	private static function context_person( $atts ) {
		if ( ! empty( $atts['person'] ) ) {
			return Etpro::find_person( $atts['person'] );
		}
		$qid = (int) get_queried_object_id();
		if ( is_singular( 'etp_person' ) ) {
			return $qid;
		}
		$people = Etpro::people_for_page( $qid );
		return $people ? $people[0] : 0;
	}

	public static function sc_trust_bar( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0, 'style' => '', 'card' => '' ), $atts, 'etp_trust_bar' );
		$id   = self::context_post_id( $atts );
		if ( Etpro_Render::printed( $id, 'trust_bar' ) ) {
			return '';
		}
		self::ensure_assets();
		$html = Etpro_Render::trust_bar( $id, array( 'style' => $atts['style'], 'card' => $atts['card'] ) );
		if ( $html ) {
			Etpro_Render::mark( $id, 'trust_bar' );
			if ( 'after_trust' === Etpro::get( 'cta_position' ) && ! Etpro_Render::printed( $id, 'cta' ) ) {
				$cta = Etpro_Render::cta( $id );
				if ( $cta ) {
					Etpro_Render::mark( $id, 'cta' );
					$html .= $cta;
				}
			}
		}
		return $html;
	}

	public static function sc_sources( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'etp_sources' );
		$id   = self::context_post_id( $atts );
		if ( Etpro_Render::printed( $id, 'sources' ) ) {
			return '';
		}
		self::ensure_assets();
		$html = Etpro_Render::sources( $id );
		if ( $html ) {
			Etpro_Render::mark( $id, 'sources' );
			if ( 'after_sources' === Etpro::get( 'cta_position' ) && ! Etpro_Render::printed( $id, 'cta' ) ) {
				$cta = Etpro_Render::cta( $id );
				if ( $cta ) {
					Etpro_Render::mark( $id, 'cta' );
					$html .= $cta;
				}
			}
		}
		return $html;
	}

	public static function sc_history( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'etp_history' );
		self::ensure_assets();
		return Etpro_Render::history( self::context_post_id( $atts ) );
	}

	public static function sc_cta( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'etp_cta' );
		$id   = self::context_post_id( $atts );
		if ( Etpro_Render::printed( $id, 'cta' ) ) {
			return '';
		}
		self::ensure_assets();
		$html = Etpro_Render::cta( $id );
		if ( $html ) {
			Etpro_Render::mark( $id, 'cta' );
		}
		return $html;
	}

	public static function sc_profile( $atts ) {
		$atts = shortcode_atts( array( 'person' => '', 'sections' => '', 'heading_level' => 2 ), $atts, 'etp_profile' );
		self::ensure_assets();
		return Etpro_Render::profile( self::context_person( $atts ), $atts );
	}

	public static function sc_expertise( $atts ) {
		$atts = shortcode_atts( array( 'person' => '', 'heading' => Etpro::get( 'expertise_heading' ), 'heading_level' => 2 ), $atts, 'etp_expertise' );
		self::ensure_assets();
		return Etpro_Render::expertise( self::context_person( $atts ), $atts );
	}

	public static function sc_author_box( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'etp_author_box' );
		$id   = self::context_post_id( $atts );
		if ( Etpro_Render::printed( $id, 'author_box' ) ) {
			return '';
		}
		self::ensure_assets();
		$html = Etpro_Render::author_box( $id );
		if ( $html ) {
			Etpro_Render::mark( $id, 'author_box' );
		}
		return $html;
	}

	public static function sc_articles( $atts ) {
		$atts = shortcode_atts( array( 'person' => '', 'role' => 'author', 'limit' => Etpro::get( 'list_limit' ), 'heading_level' => 2 ), $atts, 'etp_articles' );
		self::ensure_assets();
		return Etpro_Render::article_list( self::context_person( $atts ), $atts['role'], $atts );
	}
}

/* ======================================================================== */
/* Sources placement (below the FAQ, or before a closing disclaimer)        */
/* ======================================================================== */

/**
 * Works out where the Sources box goes inside rendered article HTML:
 * directly below the FAQ section if there is one, otherwise at the end of
 * the article but before a trailing disclaimer / CTA.
 *
 * The HTML is never re-serialised. It is tokenised only to find a byte
 * offset, and the box is spliced in at that offset, so page-builder markup
 * is left exactly as it was.
 */


class Etpro_Placement {

	const MARKER = '<!-- etp:sources -->';

	const DISCLAIMER_RE = '/^\W*(disclaimer|important information|general (?:information|advice) (?:only|warning)|(?:this|the) (?:article|information|content|guide|page) (?:is|provides|contains|offers) (?:general|factual)|(?:this|the) information (?:in|on) this)/i';

	private static $void = array( 'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr' );

	private static $faq_classes = array(
		'wp-block-yoast-faq-block',
		'schema-faq',
		'rank-math-faq',
		'wp-block-rank-math-faq-block',
		'rank-math-block',
		'etp-faq',
		'faq',
		'faqs',
		'faq-section',
		'faq-wrapper',
		'faq-container',
		'faq-block',
		'faqs-section',
	);

	private static $faq_ids = array( 'faq', 'faqs', 'rank-math-faq', 'frequently-asked-questions', 'faq-section' );

	private static $tail_classes = array( 'disclaimer', 'etp-before', 'wp-block-buttons', 'cta', 'call-to-action', 'elementor-widget-call-to-action' );

	/** @var string */
	private $html;
	/** @var array */
	private $nodes = array();
	/** @var array */
	private $roots = array();

	public function __construct( $html ) {
		$this->html = (string) $html;
		$this->parse();
	}

	/**
	 * Insert $box into $html at the right place.
	 */
	public static function insert( $html, $box ) {
		if ( false !== strpos( $html, self::MARKER ) ) {
			$pos = strpos( $html, self::MARKER );
			$box = is_callable( $box ) ? call_user_func( $box, self::disclaimer_follows( substr( $html, $pos + strlen( self::MARKER ) ) ) ) : $box;
			return substr( $html, 0, $pos ) . $box . substr( $html, $pos + strlen( self::MARKER ) );
		}
		$p      = new self( $html );
		$offset = $p->sources_offset();
		$rest   = substr( $html, $offset );
		$box    = is_callable( $box ) ? call_user_func( $box, self::disclaimer_follows( $rest ) ) : $box;
		return substr( $html, 0, $offset ) . $box . $rest;
	}

	/**
	 * Does the article's own disclaimer come straight after this point?
	 * Used to avoid printing two disclaimers back to back.
	 */
	public static function disclaimer_follows( $rest ) {
		$text = trim( preg_replace( '/\s+/', ' ', html_entity_decode( wp_strip_all_tags( substr( (string) $rest, 0, 1500 ) ), ENT_QUOTES ) ) );
		return '' !== $text && (bool) preg_match( self::DISCLAIMER_RE, substr( $text, 0, 200 ) );
	}

	/**
	 * Byte offset where the sources box should go. Public for testing.
	 */
	public function sources_offset() {
		$len = strlen( $this->html );
		if ( ! $this->roots ) {
			return $len;
		}

		list( , $kids ) = $this->flow( -1, $this->roots );
		if ( ! $kids ) {
			return $len;
		}

		$anchor = $this->find_faq_anchor();
		if ( null !== $anchor ) {
			$off = $this->faq_end( $kids, $anchor );
			if ( null !== $off ) {
				return $off;
			}
		}
		return $this->end_offset( $kids );
	}

	/** Is there an FAQ in this HTML? */
	public function has_faq() {
		return null !== $this->find_faq_anchor();
	}

	/* ------------------------------------------------------------------ */

	private function parse() {
		$re = '~<!--.*?-->|<(script|style|textarea|template|svg|noscript)\b[^>]*>.*?</\1\s*>|<(/?)([a-zA-Z][a-zA-Z0-9:-]*)((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>~is';
		if ( ! preg_match_all( $re, $this->html, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			return;
		}
		$stack = array();
		foreach ( $m as $match ) {
			$full = $match[0][0];
			$pos  = $match[0][1];
			$len  = strlen( $full );
			if ( 0 === strpos( $full, '<!--' ) ) {
				continue;
			}
			$parent = $stack ? (int) end( $stack ) : -1;

			if ( isset( $match[1] ) && -1 !== $match[1][1] && '' !== $match[1][0] ) {
				$this->add_node( strtolower( $match[1][0] ), $pos, $pos + $len, '', $parent );
				continue;
			}

			$closing = isset( $match[2] ) && '/' === $match[2][0];
			$tag     = strtolower( $match[3][0] );
			$attrs   = isset( $match[4] ) ? $match[4][0] : '';

			if ( $closing ) {
				for ( $i = count( $stack ) - 1; $i >= 0; $i-- ) {
					if ( $this->nodes[ $stack[ $i ] ]['tag'] === $tag ) {
						break;
					}
				}
				if ( $i < 0 ) {
					continue; // Stray closing tag.
				}
				while ( count( $stack ) - 1 > $i ) {
					$this->nodes[ array_pop( $stack ) ]['end'] = $pos; // Implicitly closed.
				}
				$this->nodes[ array_pop( $stack ) ]['end'] = $pos + $len;
				continue;
			}

			$self_closing = '/' === substr( rtrim( $attrs ), -1 );
			$idx          = $this->add_node( $tag, $pos, ( in_array( $tag, self::$void, true ) || $self_closing ) ? $pos + $len : null, $attrs, $parent );
			if ( null === $this->nodes[ $idx ]['end'] ) {
				$stack[] = $idx;
			}
		}
		foreach ( $stack as $idx ) {
			$this->nodes[ $idx ]['end'] = strlen( $this->html );
		}
	}

	private function add_node( $tag, $start, $end, $attrs, $parent ) {
		$idx                 = count( $this->nodes );
		$this->nodes[ $idx ] = array(
			'tag'      => $tag,
			'start'    => $start,
			'end'      => $end,
			'attrs'    => $attrs,
			'parent'   => $parent,
			'children' => array(),
		);
		if ( $parent >= 0 ) {
			$this->nodes[ $parent ]['children'][] = $idx;
		} else {
			$this->roots[] = $idx;
		}
		return $idx;
	}

	/**
	 * Descend through single-child wrappers (page builder wrappers, one big
	 * group block) to the level where the article's sections sit side by side.
	 */
	private function flow( $container, $kids ) {
		while ( 1 === count( $kids ) ) {
			$only = $this->nodes[ $kids[0] ];
			if ( empty( $only['children'] ) || ! in_array( $only['tag'], array( 'div', 'section', 'article', 'main' ), true ) ) {
				break;
			}
			$container = $kids[0];
			$kids      = $only['children'];
		}
		return array( $container, $kids );
	}

	private function classes( $idx ) {
		if ( preg_match( '/\bclass\s*=\s*(["\'])(.*?)\1/is', $this->nodes[ $idx ]['attrs'], $mm ) ) {
			return preg_split( '/\s+/', strtolower( trim( $mm[2] ) ) );
		}
		return array();
	}

	private function id_attr( $idx ) {
		if ( preg_match( '/\bid\s*=\s*(["\'])(.*?)\1/is', $this->nodes[ $idx ]['attrs'], $mm ) ) {
			return strtolower( trim( $mm[2] ) );
		}
		return '';
	}

	private function text( $idx ) {
		$n = $this->nodes[ $idx ];
		return trim( preg_replace( '/\s+/', ' ', html_entity_decode( wp_strip_all_tags( substr( $this->html, $n['start'], $n['end'] - $n['start'] ) ), ENT_QUOTES ) ) );
	}

	private function level( $idx ) {
		return preg_match( '/^h([1-6])$/', $this->nodes[ $idx ]['tag'], $mm ) ? (int) $mm[1] : 0;
	}

	private function find_faq_anchor() {
		foreach ( $this->nodes as $idx => $n ) {
			$lvl = $this->level( $idx );
			if ( $lvl >= 2 && $lvl <= 4 ) {
				$t = $this->text( $idx );
				if ( strlen( $t ) <= 100 && preg_match( '/\b(faqs?|frequently asked questions?|common questions|questions (?:and|&) answers)\b/i', $t ) ) {
					return $idx;
				}
			}
			if ( array_intersect( $this->classes( $idx ), self::$faq_classes ) || in_array( $this->id_attr( $idx ), self::$faq_ids, true ) ) {
				return $idx;
			}
		}
		return null;
	}

	private function contains( $outer, $inner ) {
		$o = $this->nodes[ $outer ];
		$i = $this->nodes[ $inner ];
		return $o['start'] <= $i['start'] && $o['end'] >= $i['end'];
	}

	private function slice( $from, $to ) {
		return substr( $this->html, $from, max( 0, $to - $from ) );
	}

	private function has_heading_at_or_above( $html, $level ) {
		if ( ! $level || ! preg_match_all( '/<h([1-6])\b/i', $html, $mm ) ) {
			return false;
		}
		foreach ( $mm[1] as $l ) {
			if ( (int) $l <= $level ) {
				return true;
			}
		}
		return false;
	}

	private function has_faq_body( $html ) {
		return (bool) preg_match( '/accordion|toggle|<details\b|faq-(?:question|answer|item)|schema-faq-section|rank-math-question|acceptedAnswer|elementor-tab-title/i', $html );
	}

	private function looks_like_tail( $idx ) {
		if ( array_intersect( $this->classes( $idx ), self::$tail_classes ) ) {
			return true;
		}
		return (bool) preg_match( self::DISCLAIMER_RE, substr( $this->text( $idx ), 0, 200 ) );
	}

	/**
	 * End of the FAQ section within the list of sibling nodes $kids.
	 */
	private function faq_end( $kids, $anchor ) {
		$anchor_level = $this->level( $anchor );
		$count        = count( $kids );

		for ( $k = 0; $k < $count; $k++ ) {
			$u = $kids[ $k ];
			if ( ! $this->contains( $u, $anchor ) ) {
				continue;
			}

			if ( $u === $anchor && $anchor_level ) {
				// Flat content: FAQ heading followed by its questions.
				$end = $this->nodes[ $u ]['end'];
				for ( $j = $k + 1; $j < $count; $j++ ) {
					$s = $kids[ $j ];
					$sl = $this->level( $s );
					if ( ( $sl && $sl <= $anchor_level ) || $this->looks_like_tail( $s ) ) {
						break;
					}
					if ( ! $sl && $this->has_heading_at_or_above( $this->slice( $this->nodes[ $s ]['start'], $this->nodes[ $s ]['end'] ), $anchor_level ) ) {
						break;
					}
					$end = $this->nodes[ $s ]['end'];
				}
				return $end;
			}

			if ( $u !== $anchor ) {
				// The anchor sits inside a container. If that container also holds
				// content after the FAQ (another heading of the same level), go deeper.
				$after = $this->slice( $this->nodes[ $anchor ]['end'], $this->nodes[ $u ]['end'] );
				if ( $anchor_level && $this->has_heading_at_or_above( $after, $anchor_level ) && ! empty( $this->nodes[ $u ]['children'] ) ) {
					list( , $inner ) = $this->flow( $u, $this->nodes[ $u ]['children'] );
					return $this->faq_end( $inner, $anchor );
				}
			}

			// Container holding the FAQ (or an FAQ block itself).
			$end = $this->nodes[ $u ]['end'];
			if ( ! $this->has_faq_body( $this->slice( $this->nodes[ $u ]['start'], $end ) ) && isset( $kids[ $k + 1 ] ) ) {
				$next = $kids[ $k + 1 ];
				$html = $this->slice( $this->nodes[ $next ]['start'], $this->nodes[ $next ]['end'] );
				if ( $this->has_faq_body( $html ) && ! $this->has_heading_at_or_above( $html, $anchor_level ? $anchor_level : 2 ) ) {
					$end = $this->nodes[ $next ]['end'];
				}
			}
			return $end;
		}
		return null;
	}

	/**
	 * End of the article, stepping back over a trailing disclaimer / CTA.
	 */
	private function end_offset( $kids ) {
		$i = count( $kids ) - 1;
		while ( $i > 0 && $this->looks_like_tail( $kids[ $i ] ) ) {
			$i--;
		}
		if ( $i < count( $kids ) - 1 ) {
			return $this->nodes[ $kids[ $i + 1 ] ]['start'];
		}
		return $this->nodes[ $kids[ $i ] ]['end'];
	}
}

/* ======================================================================== */
/* Structured data                                                          */
/* ======================================================================== */

/**
 * Structured data. Extends the existing SEO plugin graph (Yoast SEO,
 * Rank Math, All in One SEO) instead of adding a second, competing one:
 *
 *  Article   → author: the visible author(s), dateModified = visible "Updated" date
 *  WebPage   → reviewedBy (visible reviewer), lastReviewed (when fact checked)
 *  Person    → name, url (profile page), jobTitle, image, sameAs (LinkedIn), worksFor, knowsAbout
 *  Profile   → WebPage becomes ProfilePage with mainEntity = Person
 *
 * Only properties that exist on schema.org are used, and only for things
 * the reader can actually see on the page.
 */


class Etpro_Schema {

	private static $article_types = array( 'Article', 'BlogPosting', 'NewsArticle', 'TechArticle', 'Report', 'AnalysisNewsArticle', 'SocialMediaPosting' );
	private static $page_types    = array( 'WebPage', 'ItemPage', 'FAQPage', 'AboutPage', 'CollectionPage', 'QAPage', 'ProfilePage', 'ContactPage' );

	public static function init() {
		$mode = Etpro::get( 'schema_mode' );
		if ( 'auto' === $mode ) {
			add_filter( 'wpseo_schema_graph', array( __CLASS__, 'filter_list_graph' ), 20, 1 );
			add_filter( 'rank_math/json_ld', array( __CLASS__, 'filter_keyed_graph' ), 99, 1 );
			add_filter( 'aioseo_schema_output', array( __CLASS__, 'filter_list_graph' ), 20, 1 );
		} elseif ( 'standalone' === $mode ) {
			add_action( 'wp_head', array( __CLASS__, 'standalone' ), 30 );
		}
	}

	public static function detected_plugin() {
		if ( defined( 'WPSEO_VERSION' ) ) {
			return 'Yoast SEO';
		}
		if ( class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' ) ) {
			return 'Rank Math';
		}
		if ( defined( 'AIOSEO_VERSION' ) || function_exists( 'aioseo' ) ) {
			return 'All in One SEO';
		}
		return '';
	}

	/* ------------------------------------------------------------------ */

	/**
	 * What the current request is about, or null.
	 */
	public static function context() {
		$team_page = is_page() && Etpro::people_for_page( get_queried_object_id() );
		if ( ! $team_page && is_singular( Etpro::post_types() ) ) {
			$id = (int) get_queried_object_id();
			$a  = Etpro::article( $id );
			if ( ! $a ) {
				return null;
			}
			$sources_visible = $a['sources'] && 'off' !== $a['src_position'] && ( Etpro::get( 'auto_sources' ) || 'manual' === $a['src_position'] );
			$visible         = ! $a['hide_trust_bar'] || $sources_visible || Etpro::get( 'auto_author_box' );
			$authors         = $visible ? $a['authors'] : array();
			$reviewers       = $visible ? $a['reviewers'] : array();
			$editors         = $visible ? $a['editors'] : array();
			if ( ! $authors && ! $reviewers && ! $editors ) {
				return null;
			}
			return array(
				'type'      => 'article',
				'a'         => $a,
				'authors'   => $authors,
				'editors'   => $editors,
				'reviewers' => $reviewers,
			);
		}

		$person_id = 0;
		if ( is_singular( 'etp_person' ) ) {
			$person_id = (int) get_queried_object_id();
		} elseif ( is_page() ) {
			$people    = Etpro::people_for_page( get_queried_object_id() );
			$person_id = $people ? $people[0] : 0;
		}
		$p = $person_id ? Etpro::person( $person_id ) : null;
		if ( $p && $p['public'] ) {
			return array( 'type' => 'profile', 'person' => $p );
		}
		return null;
	}

	/** Yoast SEO / AIOSEO: a plain list of nodes. */
	public static function filter_list_graph( $graph ) {
		if ( ! is_array( $graph ) ) {
			return $graph;
		}
		$ctx = self::context();
		return $ctx ? array_values( self::process( $graph, $ctx ) ) : $graph;
	}

	/** Rank Math: nodes keyed by name. */
	public static function filter_keyed_graph( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}
		$ctx = self::context();
		return $ctx ? self::process( $data, $ctx ) : $data;
	}

	/* ------------------------------------------------------------------ */

	private static function types( $node ) {
		return isset( $node['@type'] ) ? (array) $node['@type'] : array();
	}

	private static function is_type( $node, $list ) {
		return (bool) array_intersect( self::types( $node ), $list );
	}

	private static function refs( $people ) {
		$refs = array();
		foreach ( $people as $p ) {
			$refs[] = array( '@id' => $p['schema_id'] );
		}
		return 1 === count( $refs ) ? $refs[0] : $refs;
	}

	private static function org_id( $nodes ) {
		foreach ( $nodes as $n ) {
			if ( is_array( $n ) && ! empty( $n['@id'] ) && self::is_type( $n, array( 'Organization', 'Corporation', 'LocalBusiness', 'FinancialService' ) ) ) {
				return $n['@id'];
			}
		}
		foreach ( $nodes as $n ) {
			if ( is_array( $n ) && self::is_type( $n, self::$article_types ) && ! empty( $n['publisher']['@id'] ) ) {
				return $n['publisher']['@id'];
			}
		}
		return '';
	}

	public static function person_node( $p, $org_id = '' ) {
		$node = array(
			'@type' => 'Person',
			'@id'   => $p['schema_id'],
			'name'  => $p['name'],
			'url'   => $p['url'],
		);
		if ( $p['job_title'] ) {
			$node['jobTitle'] = $p['job_title'];
		}
		if ( $p['photo_large'] ) {
			$node['image'] = array(
				'@type' => 'ImageObject',
				'url'   => $p['photo_large'],
			);
		}
		if ( $p['linkedin'] ) {
			$node['sameAs'] = array( $p['linkedin'] );
		}
		if ( $org_id ) {
			$node['worksFor'] = array( '@id' => $org_id );
		}
		if ( $p['expertise'] ) {
			$node['knowsAbout'] = $p['expertise'];
		}
		return apply_filters( 'etp_schema_person', $node, $p );
	}

	/**
	 * Apply the editorial data to a graph (list or keyed array).
	 */
	public static function process( array $nodes, array $ctx ) {
		$org_id  = self::org_id( $nodes );
		$people  = array();
		$old_ids = array();

		if ( 'article' === $ctx['type'] ) {
			$a = $ctx['a'];
			foreach ( $nodes as $k => $n ) {
				if ( ! is_array( $n ) ) {
					continue;
				}
				if ( self::is_type( $n, self::$article_types ) ) {
					if ( $ctx['authors'] ) {
						foreach ( isset( $n['author']['@id'] ) ? array( $n['author'] ) : ( isset( $n['author'] ) && is_array( $n['author'] ) ? $n['author'] : array() ) as $old ) {
							if ( is_array( $old ) && ! empty( $old['@id'] ) ) {
								$old_ids[] = $old['@id'];
							}
						}
						$n['author'] = self::refs( $ctx['authors'] );
					}
					if ( ! empty( $ctx['editors'] ) ) {
						$n['editor'] = self::refs( $ctx['editors'] );
					}
					$n['dateModified'] = $a['updated_iso'];
					$nodes[ $k ]       = $n;
				} elseif ( self::is_type( $n, self::$page_types ) ) {
					if ( $ctx['reviewers'] ) {
						$n['reviewedBy'] = self::refs( $ctx['reviewers'] );
						if ( $a['fact_checked'] && $a['reviewed'] ) {
							$n['lastReviewed'] = $a['reviewed'];
						}
					}
					$n['dateModified'] = $a['updated_iso'];
					$nodes[ $k ]       = $n;
				}
			}
			$people = array_merge( $ctx['authors'], isset( $ctx['editors'] ) ? $ctx['editors'] : array(), $ctx['reviewers'] );
		} else {
			$p    = $ctx['person'];
			$done = false;
			foreach ( $nodes as $k => $n ) {
				if ( $done || ! is_array( $n ) || ! self::is_type( $n, self::$page_types ) ) {
					continue;
				}
				$types       = array_values( array_diff( self::types( $n ), array( 'WebPage' ) ) );
				$types[]     = 'ProfilePage';
				$types       = array_values( array_unique( $types ) );
				$n['@type']  = 1 === count( $types ) ? $types[0] : $types;
				$n['mainEntity'] = array( '@id' => $p['schema_id'] );
				$nodes[ $k ] = $n;
				$done        = true;
			}
			$people = array( $p );
		}

		// Add Person nodes (once each).
		$existing = array();
		foreach ( $nodes as $n ) {
			if ( is_array( $n ) && ! empty( $n['@id'] ) ) {
				$existing[ $n['@id'] ] = true;
			}
		}
		$new_ids = array();
		foreach ( $people as $p ) {
			$new_ids[] = $p['schema_id'];
			if ( isset( $existing[ $p['schema_id'] ] ) ) {
				continue;
			}
			$existing[ $p['schema_id'] ] = true;
			$nodes[ 'etp_person_' . $p['id'] ] = self::person_node( $p, $org_id );
		}

		// Drop the SEO plugin's WordPress-user author node if nothing references it any more.
		foreach ( array_diff( array_unique( $old_ids ), $new_ids ) as $old ) {
			$needle = '"@id":' . wp_json_encode( $old );
			foreach ( $nodes as $k => $n ) {
				if ( ! is_array( $n ) || ! isset( $n['@id'] ) || $n['@id'] !== $old || ! self::is_type( $n, array( 'Person' ) ) ) {
					continue;
				}
				$others = $nodes;
				unset( $others[ $k ] );
				if ( false === strpos( wp_json_encode( array_values( $others ) ), $needle ) ) {
					unset( $nodes[ $k ] );
				}
			}
		}

		return apply_filters( 'etp_schema_graph', $nodes, $ctx );
	}

	/* ------------------------------------------------------------------ */

	/**
	 * Standalone JSON-LD for sites without a supported SEO plugin.
	 */
	public static function standalone() {
		$ctx = self::context();
		if ( ! $ctx ) {
			return;
		}
		$org = array(
			'@type' => 'Organization',
			'@id'   => home_url( '/#organization' ),
			'name'  => Etpro::get( 'org_name' ) ? Etpro::get( 'org_name' ) : get_bloginfo( 'name' ),
			'url'   => home_url( '/' ),
		);
		$logo = Etpro::get( 'org_logo' ) ? Etpro::get( 'org_logo' ) : get_site_icon_url( 512 );
		if ( $logo ) {
			$org['logo'] = array( '@type' => 'ImageObject', 'url' => $logo );
		}
		$nodes = array( $org );
		$url   = get_permalink( get_queried_object_id() );

		if ( 'article' === $ctx['type'] ) {
			$a       = $ctx['a'];
			$page    = array(
				'@type'         => 'WebPage',
				'@id'           => $url . '#webpage',
				'url'           => $url,
				'name'          => wp_strip_all_tags( $a['title'] ),
				'datePublished' => $a['published_iso'],
				'dateModified'  => $a['updated_iso'],
			);
			$article = array(
				'@type'            => 'Article',
				'@id'              => $url . '#article',
				'headline'         => wp_html_excerpt( wp_strip_all_tags( $a['title'] ), 110 ),
				'datePublished'    => $a['published_iso'],
				'dateModified'     => $a['updated_iso'],
				'mainEntityOfPage' => array( '@id' => $url . '#webpage' ),
				'publisher'        => array( '@id' => $org['@id'] ),
			);
			$img = get_the_post_thumbnail_url( $a['post_id'], 'full' );
			if ( $img ) {
				$article['image'] = array( $img );
			}
			$nodes[] = $page;
			$nodes[] = $article;
		} else {
			$nodes[] = array(
				'@type' => 'WebPage',
				'@id'   => $url . '#webpage',
				'url'   => $url,
				'name'  => wp_strip_all_tags( get_the_title( get_queried_object_id() ) ),
			);
		}

		$graph = array_values( self::process( $nodes, $ctx ) );
		echo "\n<script type=\"application/ld+json\" class=\"etp-schema\">" . wp_json_encode( array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}

/* ======================================================================== */
/* CSS and JS, printed inline (nothing to upload)                           */
/* ======================================================================== */

class Etpro_Assets {

	/** Light minify: strip comments and whitespace. */
	private static function minify( $css ) {
		$css = preg_replace( '~/\*.*?\*/~s', '', $css );
		$css = preg_replace( '/\s+/', ' ', $css );
		$css = preg_replace( '/\s*([{};,>])\s*/', '$1', $css );
		return trim( str_replace( ';}', '}', $css ) );
	}

	public static function css() {
		static $out = null;
		if ( null === $out ) {
			$out = self::minify( self::raw_css() );
		}
		return $out;
	}

	public static function admin_css() {
		return self::minify( <<<'ETP_ADMIN_CSS'
/* Editorial Trust – admin */
.etp-admin .etp-tabs { display: flex; flex-wrap: wrap; gap: 4px; margin: 4px 0 14px; border-bottom: 1px solid #dcdcde; }
.etp-admin .etp-tab {
	padding: 8px 12px;
	margin-bottom: -1px;
	border: 1px solid transparent;
	border-radius: 4px 4px 0 0;
	background: none;
	font-size: 13px;
	font-weight: 600;
	color: #50575e;
	cursor: pointer;
}
.etp-admin .etp-tab.is-active { border-color: #dcdcde; border-bottom-color: #fff; background: #fff; color: #1d2327; }
.etp-admin .etp-count { display: inline-block; min-width: 18px; padding: 0 5px; border-radius: 9px; background: #f0f0f1; font-size: 11px; line-height: 18px; text-align: center; }
.etp-admin .etp-panel[hidden] { display: none; }
.etp-admin .etp-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 4px 20px; }
.etp-admin .etp-grid p, .etp-admin .etp-panel > p { margin: 0 0 14px; }
.etp-admin label strong { display: inline-block; margin-bottom: 4px; }
.etp-admin .description { display: block; margin-top: 4px; }
.etp-admin .etp-date-row { display: flex; gap: 6px; align-items: center; }
.etp-admin .etp-more { margin: 4px 0 8px; padding: 10px 12px; border: 1px solid #dcdcde; border-radius: 4px; background: #fbfbfc; }
.etp-admin .etp-more summary { font-weight: 600; cursor: pointer; }
.etp-admin .etp-more .etp-grid { margin-top: 12px; }
.etp-admin fieldset legend { font-weight: 600; margin-bottom: 6px; }
.etp-admin .etp-checklist { max-height: 160px; overflow: auto; padding: 6px 8px; border: 1px solid #dcdcde; border-radius: 4px; background: #fff; }
.etp-admin .etp-checklist label { display: block; margin: 3px 0; }
.etp-admin .etp-rows { display: grid; gap: 8px; margin: 12px 0; }
.etp-admin .etp-row { display: grid; grid-template-columns: 18px 1.2fr 1fr auto; gap: 8px; align-items: center; padding: 8px; border: 1px solid #dcdcde; border-radius: 4px; background: #fff; }
.etp-admin .etp-row--history { grid-template-columns: 150px 1fr auto; }
.etp-admin .etp-row input { width: 100%; }
.etp-admin .etp-handle { color: #a7aaad; letter-spacing: -2px; }
.etp-admin .etp-row-actions { display: flex; gap: 6px; }
.etp-admin .etp-row-actions .button-link { font-size: 15px; text-decoration: none; padding: 2px 4px; }
.etp-admin .etp-remove { color: #b32d2e; }
.etp-admin .etp-note { padding: 8px 12px; border-left: 4px solid #dba617; background: #fcf9e8; }
.etp-missing { color: #8a6100; }
.etp-complete { color: #007017; }
.etp-pill { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 12px; font-weight: 600; }
.etp-pill--ok { background: #edfaef; color: #007017; }
.etp-pill--hold { background: #fcf9e8; color: #8a6100; }
.etp-muted { color: #8c8f94; }
.column-etp_photo { width: 48px; }
.column-etp_photo .etp-avatar { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; display: inline-flex; align-items: center; justify-content: center; background: #f0f0f1; font-weight: 700; font-size: 12px; color: #50575e; }
.etp-settings-nav { display: flex; flex-wrap: wrap; gap: 6px 16px; margin: 12px 0 4px; }
.etp-settings h2 { margin-top: 2em; padding-top: 1em; border-top: 1px solid #dcdcde; }
.etp-settings .etp-shortcodes td:first-child { width: 38%; }
@media (max-width: 782px) {
	.etp-admin .etp-row { grid-template-columns: 1fr; }
	.etp-admin .etp-handle { display: none; }
}

.etp-person-fields ::placeholder { font-style: italic; color: #a7aaad; opacity: 1; }
ETP_ADMIN_CSS
		);
	}

	public static function admin_js() {
		return <<<'ETP_ADMIN_JS'
/* Editorial Trust – admin (tabs, repeaters, date shortcuts) */
( function () {
	'use strict';

	function init() {
		// Tabs.
		document.querySelectorAll( '.etp-article' ).forEach( function ( box ) {
			box.querySelectorAll( '.etp-tab' ).forEach( function ( tab ) {
				tab.addEventListener( 'click', function () {
					var name = tab.getAttribute( 'data-tab' );
					box.querySelectorAll( '.etp-tab' ).forEach( function ( t ) {
						t.classList.toggle( 'is-active', t === tab );
					} );
					box.querySelectorAll( '.etp-panel' ).forEach( function ( p ) {
						var on = p.getAttribute( 'data-panel' ) === name;
						p.classList.toggle( 'is-active', on );
						p.hidden = ! on;
					} );
				} );
			} );
		} );

		// Repeaters.
		document.querySelectorAll( '.etp-repeater' ).forEach( function ( rep ) {
			var rows = rep.querySelector( '.etp-rows' );
			var tpl = rep.querySelector( '.etp-row-template' );
			var add = rep.querySelector( '.etp-add-row' );
			if ( add && tpl ) {
				add.addEventListener( 'click', function () {
					var html = tpl.innerHTML.replace( /__i__/g, 'n' + Date.now() );
					var wrap = document.createElement( 'div' );
					wrap.innerHTML = html.trim();
					var row = wrap.firstElementChild;
					rows.appendChild( row );
					var first = row.querySelector( 'input' );
					if ( first ) {
						first.focus();
					}
					count( rep );
				} );
			}
			rep.addEventListener( 'click', function ( e ) {
				var row = e.target.closest( '.etp-row' );
				if ( ! row ) {
					return;
				}
				if ( e.target.closest( '.etp-remove' ) ) {
					row.remove();
					count( rep );
				} else if ( e.target.closest( '.etp-up' ) && row.previousElementSibling ) {
					row.parentNode.insertBefore( row, row.previousElementSibling );
				} else if ( e.target.closest( '.etp-down' ) && row.nextElementSibling ) {
					row.parentNode.insertBefore( row.nextElementSibling, row );
				}
			} );
		} );

		function count( rep ) {
			var panel = rep.closest( '.etp-panel' );
			if ( ! panel ) {
				return;
			}
			var box = rep.closest( '.etp-article' );
			var tab = box && box.querySelector( '.etp-tab[data-tab="' + panel.getAttribute( 'data-panel' ) + '"] .etp-count' );
			if ( tab ) {
				tab.textContent = rep.querySelectorAll( '.etp-rows .etp-row' ).length;
			}
		}

		// "Today" buttons.
		document.querySelectorAll( '.etp-today' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var input = document.getElementById( b.getAttribute( 'data-target' ) );
				if ( input ) {
					var d = new Date();
					var m = String( d.getMonth() + 1 ).padStart( 2, '0' );
					var day = String( d.getDate() ).padStart( 2, '0' );
					input.value = d.getFullYear() + '-' + m + '-' + day;
				}
			} );
		} );

		// Colour picker on settings.
		if ( window.jQuery && window.jQuery.fn.wpColorPicker ) {
			window.jQuery( '.etp-color' ).wpColorPicker();
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
ETP_ADMIN_JS;
	}

	public static function js() {
		return <<<'ETP_JS'
/* Editorial Trust 2 – front end (no dependencies) */
( function () {
	'use strict';

	var cfg = window.etpConfig || {};
	var STORE = 'etpEnquiry';
	var viaKeyboard = false;

	/* Fact checked panel ------------------------------------------- */

	function closeAll( except ) {
		document.querySelectorAll( '[data-etp-fc][open]' ).forEach( function ( d ) {
			if ( d !== except ) {
				d.open = false;
			}
		} );
	}

	/* While open, the panel lives in <body> so a hero with overflow:hidden
	   or a lower stacking order can't clip or cover it. */
	function place( d ) {
		var portal = d._etpPortal;
		if ( ! portal ) {
			return;
		}
		var s = d.querySelector( 'summary' ).getBoundingClientRect();
		var vw = document.documentElement.clientWidth;
		var w = portal.offsetWidth;
		var h = portal.offsetHeight;
		var left = vw <= 640 ? 16 : s.left + s.width / 2 - w / 2;
		left = Math.max( 16, Math.min( left, vw - 16 - w ) );
		var above = s.bottom + 14 + h > window.innerHeight && s.top - 14 - h > 0;
		var top = above ? s.top - 14 - h : s.bottom + 14;
		portal.classList.toggle( 'is-above', above );
		portal.style.left = ( left + window.pageXOffset ) + 'px';
		portal.style.top = ( top + window.pageYOffset ) + 'px';
		portal.style.setProperty( '--etp-arrow-x', Math.max( 22, Math.min( w - 22, s.left + s.width / 2 - left ) ) + 'px' );
	}

	function openPanel( d ) {
		var panel = d.querySelector( '.etp-fc__panel' );
		if ( ! panel ) {
			return;
		}
		var portal = document.createElement( 'div' );
		portal.className = 'etp etp-fc-portal';
		portal.setAttribute( 'data-etp-portal', '' );
		portal.appendChild( panel );
		document.body.appendChild( portal );
		d._etpPortal = portal;
		d._etpPanel = panel;
		place( d );
		if ( viaKeyboard ) {
			var btn = panel.querySelector( '[data-etp-close]' );
			try {
				( btn || panel ).focus( { preventScroll: true } );
			} catch ( err ) {
				( btn || panel ).focus();
			}
		}
	}

	function closePanel( d ) {
		if ( d._etpPortal ) {
			d.appendChild( d._etpPanel );
			d._etpPortal.remove();
			d._etpPortal = null;
		}
	}

	function focusSummary( d ) {
		var s = d.querySelector( 'summary' );
		if ( s ) {
			s.focus();
		}
	}

	function initFactCheck() {
		document.documentElement.classList.add( 'etp-js' );
		document.querySelectorAll( '[data-etp-fc]' ).forEach( function ( d ) {
			var summary = d.querySelector( 'summary' );
			if ( summary ) {
				summary.addEventListener( 'click', function ( e ) {
					viaKeyboard = 0 === e.detail;
				} );
			}
			d.addEventListener( 'toggle', function () {
				if ( d.open ) {
					closeAll( d );
					openPanel( d );
				} else {
					closePanel( d );
				}
				viaKeyboard = false;
			} );
			var btn = d.querySelector( '[data-etp-close]' );
			if ( btn ) {
				btn.addEventListener( 'click', function () {
					d.open = false;
					focusSummary( d );
				} );
			}
			// Opened before this script ran (e.g. a cache plugin delayed the JS): move it now.
			if ( d.open && ! d._etpPortal ) {
				openPanel( d );
			}
		} );

		document.addEventListener( 'click', function ( e ) {
			if ( ! e.target.closest || ! e.target.closest( '[data-etp-fc], [data-etp-portal]' ) ) {
				closeAll();
			}
		} );
		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' !== e.key ) {
				return;
			}
			var open = document.querySelector( '[data-etp-fc][open]' );
			if ( open ) {
				open.open = false;
				focusSummary( open );
			}
		} );
		// Tabbing out of the panel closes it and returns to the badge.
		document.addEventListener( 'focusin', function ( e ) {
			var open = document.querySelector( '[data-etp-fc][open]' );
			if ( open && open._etpPortal && ! open._etpPortal.contains( e.target ) && ! open.contains( e.target ) ) {
				open.open = false;
			}
		} );
		window.addEventListener( 'resize', function () {
			document.querySelectorAll( '[data-etp-fc][open]' ).forEach( place );
		} );
	}

	/* Ask-the-expert CTA + enquiry form prefill --------------------- */

	function remember( el ) {
		var data = {
			article: el.getAttribute( 'data-article' ) || document.title,
			url: el.getAttribute( 'data-article-url' ) || location.href,
			expert: el.getAttribute( 'data-expert' ) || '',
			t: Date.now()
		};
		try {
			sessionStorage.setItem( STORE, JSON.stringify( data ) );
		} catch ( err ) {}
		return data;
	}

	function stored() {
		try {
			var d = JSON.parse( sessionStorage.getItem( STORE ) || 'null' );
			if ( d && Date.now() - d.t < 60 * 60 * 1000 ) {
				return d;
			}
		} catch ( err ) {}
		return null;
	}

	function fromUrl() {
		var p;
		try {
			p = new URLSearchParams( location.search );
		} catch ( err ) {
			return null;
		}
		var a = cfg.article || 'etp_article';
		if ( ! p.get( a ) ) {
			return null;
		}
		return { article: p.get( a ), url: p.get( a + '_url' ) || '', expert: p.get( cfg.expert || 'etp_expert' ) || '' };
	}

	function selectors( name ) {
		var dash = name.replace( /_/g, '-' );
		return [
			'[name="' + name + '"]',
			'[name="form_fields[' + name + ']"]', // Elementor Forms (field ID).
			'[name="wpforms[fields][' + name + ']"]',
			'.' + name + ' input', // Gravity Forms / WPForms CSS class on the field wrapper.
			'.' + dash + ' input',
			'input.' + name,
			'textarea.' + name
		].join( ',' );
	}

	function setValue( el, value ) {
		if ( ! el || el.value || ! value ) {
			return;
		}
		el.value = value;
		el.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		el.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}

	function fill( root ) {
		var data = fromUrl() || stored();
		if ( ! data ) {
			return;
		}
		root = root && root.querySelectorAll ? root : document;
		var a = cfg.article || 'etp_article';
		var e = cfg.expert || 'etp_expert';
		root.querySelectorAll( selectors( a ) ).forEach( function ( el ) {
			setValue( el, data.article );
		} );
		root.querySelectorAll( selectors( a + '_url' ) ).forEach( function ( el ) {
			setValue( el, data.url );
		} );
		root.querySelectorAll( selectors( e ) ).forEach( function ( el ) {
			setValue( el, data.expert );
		} );
	}

	function openPopup() {
		var target = ( cfg.popup || '' ).trim();
		if ( ! target ) {
			return false;
		}
		if ( /^\d+$/.test( target ) ) {
			var epro = window.elementorProFrontend;
			if ( epro && epro.modules && epro.modules.popup ) {
				epro.modules.popup.showPopup( { id: parseInt( target, 10 ) } );
				return true;
			}
			return false;
		}
		var trigger;
		try {
			trigger = document.querySelector( target );
		} catch ( err ) {
			return false;
		}
		if ( trigger ) {
			trigger.click();
			return true;
		}
		return false;
	}

	function initCta() {
		document.addEventListener( 'click', function ( e ) {
			var el = e.target.closest ? e.target.closest( '[data-etp-cta]' ) : null;
			if ( ! el ) {
				return;
			}
			remember( el );
			if ( 'popup' === cfg.mode && openPopup() ) {
				e.preventDefault();
				setTimeout( fill, 300 );
				setTimeout( fill, 1000 );
			}
		} );

		fill( document );

		// Forms that render late.
		if ( window.jQuery ) {
			window.jQuery( document ).on( 'elementor/popup/show gform_post_render', function () {
				fill( document );
			} );
		}
		document.addEventListener( 'wpcf7mailsent', function () {
			try {
				sessionStorage.removeItem( STORE );
			} catch ( err ) {}
		} );
	}

	function placeBlocks() {
		var items = document.querySelectorAll( '[data-etp-auto]' );
		if ( ! items.length ) {
			return;
		}
		var cfg  = window.etpConfig || {};
		var list = cfg.place ? [ cfg.place ] : [];
		list = list.concat( [ '.bde-post-content', '.brxe-post-content', '.elementor-widget-theme-post-content > .elementor-widget-container', '.elementor-widget-theme-post-content', '.fl-module-fl-post-content > .fl-module-content', '.et_pb_post_content', '.ct-inner-content', '.oxy-post-content' ] );
		var box = null;
		for ( var i = 0; i < list.length && ! box; i++ ) {
			try {
				box = document.querySelector( list[ i ] );
			} catch ( err ) {}
		}
		if ( ! box ) {
			return;
		}
		var first = box.firstChild;
		Array.prototype.forEach.call( items, function ( el ) {
			if ( box.contains( el ) ) {
				return;
			}
			if ( 'top' === el.getAttribute( 'data-etp-auto' ) ) {
				box.insertBefore( el, first );
			} else {
				box.appendChild( el );
			}
		} );
	}

	function init() {
		initFactCheck();
		initCta();
	}

	try {
		placeBlocks();
	} catch ( err ) {}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
ETP_JS;
	}

	/* Front-end styles. Edit freely; the brand colour comes from Experts → Settings. */
	private static function raw_css() {
		return <<<'ETP_CSS'
/* Editorial Trust 2 – front end.
 * Inherits the theme's fonts and text colour. One brand colour (Experts →
 * Settings → Accent colour) drives the whole palette.
 * Selectors are doubled (.etp .etp-x) so theme rules like ".entry-content ul"
 * don't override the layout.
 */

.etp {
	--etp-accent: #d99a00;
	--etp-accent-rgb: 217, 154, 0;
	--etp-accent-soft: #fcf3dc;
	--etp-accent-line: #f0d699;
	--etp-accent-ink: #6d4d00;
	--etp-accent-bright: #e4b84d;
	--etp-on-accent: #16181d;
	--etp-accent-text: var(--etp-accent-ink);
	--etp-line: rgba(17, 24, 39, .1);
	--etp-line-strong: rgba(17, 24, 39, .18);
	--etp-surface: #fff;
	--etp-tint: rgba(17, 24, 39, .03);
	--etp-muted: .68;
	--etp-radius: 18px;
	--etp-ring: #fff;
	--etp-shadow: 0 1px 2px rgba(17, 24, 39, .04), 0 12px 32px -18px rgba(17, 24, 39, .22);
	--etp-ease: cubic-bezier(.2, .7, .2, 1);
	box-sizing: border-box;
}
.etp *, .etp *::before, .etp *::after { box-sizing: border-box; }
.etp a { text-decoration: none; box-shadow: none; }
.etp p { margin: 0; }
.etp svg.etp-i { display: inline-block; flex: none; vertical-align: middle; fill: none; stroke: currentColor; overflow: visible; }
.etp svg.etp-i .etp-fill { fill: currentColor; stroke: none; }
.etp .etp-sr { position: absolute; width: 1px; height: 1px; margin: -1px; padding: 0; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; border: 0; }

/* Security-print pattern (finance certificate feel), reused in a few places */
.etp .etp-sources::before,
.etp.etp-sources::before,
.etp .etp-fc__head::before,
.etp.etp-trust--card::before,
.etp .etp-profile__photo::after,
.etp .etp-card__placeholder::before {
	content: "";
	position: absolute;
	pointer-events: none;
	background:
		repeating-radial-gradient(circle at 100% 0, transparent 0 7px, rgba(var(--etp-accent-rgb), .16) 7px 8px),
		repeating-radial-gradient(circle at 70% 120%, transparent 0 9px, rgba(var(--etp-accent-rgb), .1) 9px 10px);
}

/* Avatars ---------------------------------------------------------- */
.etp img.etp-avatar,
.etp .etp-avatar {
	display: block;
	flex: none;
	width: var(--etp-size, 40px);
	height: var(--etp-size, 40px);
	max-width: none;
	margin: 0;
	padding: 0;
	border: 0;
	border-radius: 50%;
	object-fit: cover;
	aspect-ratio: 1 / 1;
	background: rgba(127, 127, 127, .18);
}
.etp .etp-avatar--initials {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	font-weight: 700;
	font-size: calc(var(--etp-size, 40px) * .36);
	letter-spacing: .02em;
	line-height: 1;
	color: var(--etp-accent-ink);
	background: var(--etp-accent-soft);
}

/* Ring + role badge: pen = wrote it, tick = reviewed it */
.etp .etp-av {
	--etp-av: 44px;
	position: relative;
	display: block;
	flex: none;
	width: var(--etp-av);
	height: var(--etp-av);
	border-radius: 50%;
	outline: 1.5px solid var(--etp-accent);
	outline-offset: 2.5px;
}
.etp .etp-av--reviewer { outline-style: dashed; }
.etp .etp-av--editor { outline-style: dotted; }
.etp .etp-av .etp-avatar { width: 100%; height: 100%; font-size: calc(var(--etp-av) * .36); }
.etp .etp-av__badge {
	position: absolute;
	right: -6px;
	bottom: -5px;
	display: flex;
	align-items: center;
	justify-content: center;
	width: 20px;
	height: 20px;
	border-radius: 50%;
	background: var(--etp-accent);
	color: var(--etp-on-accent);
	box-shadow: 0 0 0 2.5px var(--etp-ring);
}
.etp .etp-av__badge .etp-i { width: 10px; height: 10px; stroke-width: 2.8; }

.etp .etp-linkedin {
	display: inline-flex;
	flex: none;
	margin-left: 7px;
	color: #0a66c2;
	opacity: .9;
	line-height: 1;
	transition: opacity .2s, transform .2s var(--etp-ease);
}
.etp .etp-linkedin:hover,
.etp .etp-linkedin:focus-visible { opacity: 1; transform: translateY(-1px); }

/* Eyebrow: icon + small caps + hairline */
.etp .etp-eyebrow {
	display: flex;
	align-items: center;
	gap: 9px;
	margin: 0 0 12px;
	padding: 0;
	font-size: 12px;
	font-weight: 700;
	line-height: 1.3;
	letter-spacing: .12em;
	text-transform: uppercase;
	color: var(--etp-accent-text);
}
.etp .etp-eyebrow::after { content: ""; width: 32px; height: 1px; background: currentColor; opacity: .45; }
.etp .etp-eyebrow .etp-i { width: 16px; height: 16px; }

/* Buttons ---------------------------------------------------------- */
.etp a.etp-btn {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	gap: 9px;
	padding: 12px 22px;
	border: 0;
	border-radius: 999px;
	background: var(--etp-accent);
	color: var(--etp-on-accent);
	font-weight: 700;
	font-size: 15px;
	line-height: 1.2;
	letter-spacing: .01em;
	white-space: nowrap;
	transition: transform .25s var(--etp-ease), box-shadow .25s var(--etp-ease);
}
.etp a.etp-btn:hover,
.etp a.etp-btn:focus-visible {
	color: var(--etp-on-accent);
	transform: translateY(-2px);
	box-shadow: 0 12px 24px -12px rgba(var(--etp-accent-rgb), .9);
}
.etp a.etp-btn .etp-i--arrow { transition: transform .25s var(--etp-ease); }
.etp a.etp-btn:hover .etp-i--arrow { transform: translateX(4px); }
.etp a.etp-btn--ghost { background: transparent; color: inherit; box-shadow: inset 0 0 0 1.5px var(--etp-line-strong); }
.etp a.etp-btn--ghost:hover,
.etp a.etp-btn--ghost:focus-visible { color: inherit; box-shadow: inset 0 0 0 1.5px var(--etp-accent); }
.etp a.etp-btn--ghost .etp-i--linkedin { color: #0a66c2; }
.etp a:focus-visible,
.etp summary:focus-visible,
.etp button:focus-visible { outline: 2px solid var(--etp-accent); outline-offset: 3px; }

/* ================================================================== */
/* Trust bar: Written → Reviewed → Fact checked                       */
/* ================================================================== */
.etp.etp-trust {
	position: relative;
	margin: 0 0 1.75em;
	font-size: 15px;
	line-height: 1.25;
	isolation: isolate;
}
.etp.etp-trust--card {
	padding: 16px 22px;
	border: 1px solid var(--etp-line);
	border-radius: var(--etp-radius);
	background: var(--etp-surface);
	box-shadow: var(--etp-shadow);
}
.etp.etp-trust--card::before {
	inset: 0;
	z-index: -1;
	border-radius: inherit;
	-webkit-mask-image: radial-gradient(260px circle at 100% 0, #000, transparent 75%);
	mask-image: radial-gradient(260px circle at 100% 0, #000, transparent 75%);
}
.etp.etp-trust--dark {
	--etp-line: rgba(255, 255, 255, .14);
	--etp-line-strong: rgba(255, 255, 255, .26);
	--etp-surface: rgba(255, 255, 255, .07);
	--etp-ring: rgba(9, 14, 24, .7);
	--etp-accent-text: var(--etp-accent-bright);
	--etp-muted: .74;
	color: #fff;
}
.etp.etp-trust--dark.etp-trust--card { box-shadow: none; -webkit-backdrop-filter: blur(10px); backdrop-filter: blur(10px); }
.etp.etp-trust--dark .etp-linkedin { color: #fff; opacity: .8; }

/* Dividers between steps: hairline with an accent node (the "chain").
   The clip hides the divider of whichever step starts a wrapped line. */
.etp .etp-trust__clip { overflow-x: clip; margin-left: -10px; padding-left: 10px; }
.etp .etp-trust__row {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	row-gap: 14px;
	margin-left: -30px;
}
.etp .etp-step {
	position: relative;
	display: flex;
	align-items: center;
	gap: 13px;
	min-width: 0;
	margin: 0;
	padding: 0 0 0 30px;
}
.etp .etp-step::before {
	content: "";
	position: absolute;
	left: 14px;
	top: 2px;
	bottom: 2px;
	width: 1px;
	background: var(--etp-line-strong);
}
.etp .etp-step::after {
	content: "";
	position: absolute;
	left: 11.5px;
	top: 50%;
	width: 6px;
	height: 6px;
	margin-top: -3px;
	border-radius: 50%;
	background: var(--etp-accent);
	box-shadow: 0 0 0 3px var(--etp-surface);
}
.etp.etp-trust:not(.etp-trust--card) .etp-step::after { box-shadow: none; }
.etp .etp-step__photo { display: block; flex: none; border-radius: 50%; }
.etp .etp-step__text { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.etp .etp-step__label {
	font-size: 11px;
	font-weight: 700;
	line-height: 1.3;
	letter-spacing: .1em;
	text-transform: uppercase;
	opacity: var(--etp-muted);
	font-variant-numeric: tabular-nums;
}
.etp .etp-step__name {
	display: flex;
	align-items: center;
	font-weight: 700;
	font-size: 16px;
	line-height: 1.25;
}
.etp .etp-step__name a:not(.etp-linkedin),
.etp .etp-pcard__name a:not(.etp-linkedin) {
	color: inherit;
	background: linear-gradient(currentColor, currentColor) 0 100% / 0 1.5px no-repeat;
	transition: background-size .3s var(--etp-ease);
}
.etp .etp-step__name a:not(.etp-linkedin):hover,
.etp .etp-pcard__name a:not(.etp-linkedin):hover { background-size: 100% 1.5px; color: inherit; }
.etp .etp-step__name a.etp-linkedin,
.etp .etp-pcard__name a.etp-linkedin { color: #0a66c2; }
.etp.etp-trust--dark .etp-step__name a.etp-linkedin { color: #fff; }
.etp .etp-step__role,
.etp .etp-step__hint { font-size: 13px; line-height: 1.3; opacity: var(--etp-muted); }

/* Fact checked seal (opens the panel) */
.etp .etp-fc { position: relative; }
.etp .etp-fc__toggle {
	display: flex;
	align-items: center;
	gap: 13px;
	margin: -6px -10px -6px -6px;
	padding: 6px 10px 6px 6px;
	border-radius: 14px;
	cursor: pointer;
	list-style: none;
	user-select: none;
	transition: background-color .2s;
}
.etp .etp-fc__toggle::-webkit-details-marker { display: none; }
.etp .etp-fc__toggle::marker { content: ""; }
.etp .etp-fc__toggle:hover,
.etp .etp-fc[open] > .etp-fc__toggle { background: rgba(var(--etp-accent-rgb), .1); }
.etp .etp-seal {
	position: relative;
	display: flex;
	align-items: center;
	justify-content: center;
	flex: none;
	width: 44px;
	height: 44px;
	border-radius: 50%;
	color: var(--etp-accent);
	background: rgba(var(--etp-accent-rgb), .12);
}
.etp .etp-seal::before {
	content: "";
	position: absolute;
	inset: -4px;
	border: 1.5px dashed rgba(var(--etp-accent-rgb), .75);
	border-radius: 50%;
	transition: transform .9s var(--etp-ease);
}
.etp .etp-fc__toggle:hover .etp-seal::before,
.etp .etp-fc[open] .etp-seal::before { transform: rotate(90deg); }
.etp .etp-seal .etp-i { width: 26px; height: 26px; }
.etp .etp-i--seal .etp-seal__check { stroke: var(--etp-on-accent); stroke-width: 2.4; }
.etp .etp-step__hint { display: inline-flex; align-items: center; gap: 3px; }
.etp .etp-step__hint .etp-i { transition: transform .25s var(--etp-ease); }
.etp .etp-fc[open] .etp-step__hint .etp-i { transform: rotate(180deg); }
.etp .etp-fc__label {
	text-decoration: underline;
	text-decoration-style: dotted;
	text-decoration-thickness: 1.5px;
	text-underline-offset: 4px;
	text-decoration-color: rgba(var(--etp-accent-rgb), .8);
}

/* ------------------------------------------------------------------ */
/* "Why you can trust this article" panel – a small certificate        */
/* ------------------------------------------------------------------ */
.etp .etp-fc__panel {
	--etp-line: rgba(17, 24, 39, .1);
	--etp-line-strong: rgba(17, 24, 39, .18);
	position: absolute;
	z-index: 60;
	top: calc(100% + 14px);
	left: 50%;
	width: min(400px, calc(100vw - 32px));
	transform: translateX(-50%);
	border-radius: 20px;
	background: #fff;
	color: #1b1d22;
	box-shadow: 0 30px 70px -20px rgba(17, 24, 39, .45), 0 0 0 1px rgba(17, 24, 39, .07);
	font-size: 14px;
	line-height: 1.5;
	text-align: left;
	letter-spacing: normal;
	text-transform: none;
}
.etp .etp-fc__panel:focus { outline: none; }
.etp .etp-fc__panel::after {
	content: "";
	position: absolute;
	top: -7px;
	left: calc(50% - 7px);
	width: 14px;
	height: 14px;
	background: var(--etp-accent-soft);
	transform: rotate(45deg);
	border-radius: 3px 0 0 0;
}
.etp .etp-fc__head {
	position: relative;
	display: flex;
	align-items: center;
	gap: 13px;
	padding: 18px 16px 16px 20px;
	border-radius: 20px 20px 0 0;
	background: var(--etp-accent-soft);
	overflow: hidden;
	isolation: isolate;
}
.etp .etp-fc__head::before {
	inset: 0;
	z-index: -1;
	-webkit-mask-image: radial-gradient(240px circle at 100% 0, #000, transparent 80%);
	mask-image: radial-gradient(240px circle at 100% 0, #000, transparent 80%);
}
.etp .etp-fc__seal { display: flex; flex: none; color: var(--etp-accent); filter: drop-shadow(0 4px 8px rgba(var(--etp-accent-rgb), .35)); }
.etp .etp-fc__seal .etp-i { width: 38px; height: 38px; }
.etp .etp-fc__titles { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
.etp .etp-fc__kicker { font-size: 11px; font-weight: 700; line-height: 1.2; letter-spacing: .12em; text-transform: uppercase; color: var(--etp-accent-ink); }
.etp .etp-fc__title { font-size: 17px; font-weight: 700; line-height: 1.25; color: #14161a; }
.etp button.etp-fc__close {
	-webkit-appearance: none;
	appearance: none;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	flex: none;
	align-self: flex-start;
	width: 32px;
	height: 32px;
	min-width: 0;
	min-height: 0;
	margin: -4px -2px 0 auto;
	padding: 0;
	border: 0;
	border-radius: 50%;
	background: rgba(255, 255, 255, .7);
	color: #1b1d22;
	box-shadow: none;
	line-height: 1;
	text-transform: none;
	letter-spacing: 0;
	cursor: pointer;
	transition: background-color .2s, transform .2s var(--etp-ease);
}
.etp button.etp-fc__close:hover,
.etp button.etp-fc__close:focus-visible { background: #fff; color: #1b1d22; transform: rotate(90deg); }
.etp .etp-fc__body { padding: 18px 20px 20px; }
.etp .etp-fc__summary { margin: 0 0 16px; color: #3d4048; }

/* The editorial chain */
.etp ol.etp-fc__steps { margin: 0 0 16px; padding: 0; list-style: none; }
.etp ol.etp-fc__steps > li {
	position: relative;
	display: grid;
	grid-template-columns: 30px 1fr;
	gap: 12px;
	margin: 0;
	padding: 0 0 14px;
	list-style: none;
}
.etp ol.etp-fc__steps > li::before {
	content: "";
	position: absolute;
	left: 14.25px;
	top: 32px;
	bottom: 2px;
	border-left: 1.5px dashed rgba(var(--etp-accent-rgb), .55);
}
.etp ol.etp-fc__steps > li:last-child { padding-bottom: 0; }
.etp ol.etp-fc__steps > li:last-child::before { content: none; }
.etp .etp-fc__node {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 30px;
	height: 30px;
	border-radius: 50%;
	border: 1.5px solid var(--etp-accent);
	background: #fff;
	color: var(--etp-accent-ink);
}
.etp .etp-fc__node .etp-i { width: 13px; height: 13px; stroke-width: 2.4; }
.etp .etp-fc__steps > li.is-done .etp-fc__node { background: var(--etp-accent); color: var(--etp-on-accent); box-shadow: 0 0 0 4px rgba(var(--etp-accent-rgb), .18); }
.etp .etp-fc__step-label { margin: 1px 0 3px; font-size: 11px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #6b6f78; }
.etp .etp-fc__who { margin: 0 0 3px; }
.etp .etp-fc__who a { font-weight: 700; color: #14161a; }
.etp .etp-fc__who a:hover { color: #14161a; text-decoration: underline; text-underline-offset: 3px; }
.etp .etp-fc__who span { display: block; font-size: 13px; color: #5d616b; }
.etp .etp-fc__when { font-size: 12.5px; color: #6b6f78; font-variant-numeric: tabular-nums; }

.etp .etp-fc__checked { margin: 0 0 14px; padding: 14px 16px; border-radius: 14px; background: #f6f6f8; }
.etp .etp-fc__subhead { margin: 0 0 8px; font-size: 11px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #6b6f78; }
.etp ul.etp-fc__list { margin: 0; padding: 0; list-style: none; }
.etp ul.etp-fc__list li { position: relative; margin: 0 0 6px; padding: 0 0 0 26px; list-style: none; color: #2a2d33; }
.etp ul.etp-fc__list li:last-child { margin-bottom: 0; }
.etp ul.etp-fc__list li::before {
	content: "";
	position: absolute;
	left: 0;
	top: 2px;
	width: 17px;
	height: 17px;
	border-radius: 50%;
	background: rgba(var(--etp-accent-rgb), .2);
}
.etp ul.etp-fc__list li::after {
	content: "";
	position: absolute;
	left: 5px;
	top: 7px;
	width: 7px;
	height: 4px;
	border-left: 2px solid var(--etp-accent-ink);
	border-bottom: 2px solid var(--etp-accent-ink);
	transform: rotate(-45deg);
}
.etp a.etp-fc__guidelines {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 10px;
	padding: 12px 16px;
	border: 1px solid var(--etp-line);
	border-radius: 14px;
	color: var(--etp-accent-ink);
	font-weight: 700;
	transition: background-color .2s, border-color .2s;
}
.etp a.etp-fc__guidelines:hover { background: var(--etp-accent-soft); border-color: var(--etp-accent-line); color: var(--etp-accent-ink); }
.etp a.etp-fc__guidelines .etp-i { transition: transform .25s var(--etp-ease); }
.etp a.etp-fc__guidelines:hover .etp-i { transform: translateX(4px); }

/* Before the script runs (or if a cache plugin delays it) the open panel
   sits in the normal flow under the bar, full width, so nothing can clip it. */
html:not(.etp-js) .etp .etp-fc[open] { flex: 1 1 100%; flex-wrap: wrap; }
html:not(.etp-js) .etp .etp-fc__panel {
	position: relative;
	top: auto;
	left: auto;
	z-index: 2;
	width: 100%;
	max-width: 100%;
	margin-top: 14px;
	transform: none;
}
html:not(.etp-js) .etp .etp-fc__panel::after { left: 34px; }

/* Panel while open: moved to <body> so hero sections can't clip it */
.etp.etp-fc-portal { position: absolute; z-index: 99999; top: 0; left: 0; width: min(400px, calc(100vw - 32px)); }
.etp.etp-fc-portal .etp-fc__panel {
	position: relative;
	top: auto;
	left: auto;
	width: 100%;
	margin: 0;
	transform: none;
	animation: etp-pop .28s var(--etp-ease);
}
.etp.etp-fc-portal .etp-fc__panel::after { left: calc(var(--etp-arrow-x, 50%) - 7px); }
.etp.etp-fc-portal.is-above .etp-fc__panel { animation-name: etp-pop-up; }
.etp.etp-fc-portal.is-above .etp-fc__panel::after { top: auto; bottom: -7px; background: #fff; border-radius: 0 0 3px 0; }
@keyframes etp-pop { from { opacity: 0; transform: translateY(-6px) scale(.98); } to { opacity: 1; transform: none; } }
@keyframes etp-pop-up { from { opacity: 0; transform: translateY(6px) scale(.98); } to { opacity: 1; transform: none; } }

/* ================================================================== */
/* "Ask the expert" CTA                                               */
/* ================================================================== */
.etp.etp-cta {
	position: relative;
	display: flex;
	align-items: center;
	gap: 16px;
	margin: 1.75em 0;
	padding: 16px 16px 16px 18px;
	border: 1px solid rgba(var(--etp-accent-rgb), .35);
	border-radius: 22px 22px 22px 6px;
	background: linear-gradient(120deg, rgba(var(--etp-accent-rgb), .13), rgba(var(--etp-accent-rgb), .03) 60%);
	line-height: 1.35;
}
.etp .etp-cta__avatar { position: relative; flex: none; }
.etp .etp-cta__avatar .etp-avatar { --etp-size: 52px; outline: 1.5px solid var(--etp-accent); outline-offset: 2.5px; }
.etp .etp-cta__ping {
	position: absolute;
	top: -4px;
	right: -6px;
	display: flex;
	align-items: center;
	justify-content: center;
	width: 22px;
	height: 22px;
	border-radius: 50% 50% 50% 4px;
	background: var(--etp-accent);
	color: var(--etp-on-accent);
	box-shadow: 0 0 0 2.5px #fff;
}
.etp .etp-cta__ping .etp-i { width: 11px; height: 11px; stroke-width: 2.6; }
.etp .etp-cta__text { display: flex; flex-direction: column; gap: 3px; flex: 1 1 auto; min-width: 0; }
.etp .etp-cta__q { font-size: 17px; font-weight: 700; }
.etp .etp-cta__who { font-size: 13px; opacity: .7; }
.etp a.etp-cta__link { flex: none; }
.etp-trust + .etp-cta { margin-top: -0.5em; }

/* ================================================================== */
/* Sources and further reading                                        */
/* ================================================================== */
.etp.etp-sources {
	position: relative;
	margin: 2.75em 0;
	padding: clamp(22px, 4vw, 40px);
	border: 1px solid var(--etp-line);
	border-radius: 24px;
	background: var(--etp-surface);
	box-shadow: var(--etp-shadow);
	line-height: 1.5;
	overflow: hidden;
	overflow: clip;
	isolation: isolate;
	width: 100%;
	container-type: inline-size;
}
.etp.etp-sources::before {
	top: 0;
	right: 0;
	width: 340px;
	height: 300px;
	z-index: -1;
	-webkit-mask-image: radial-gradient(300px circle at 100% 0, #000, transparent 78%);
	mask-image: radial-gradient(300px circle at 100% 0, #000, transparent 78%);
}
.etp .etp-sources__head { max-width: 680px; }
.etp .etp-sources__heading {
	margin: 0 0 8px;
	padding: 0;
	font-size: clamp(23px, 2.8vw, 31px);
	line-height: 1.15;
	letter-spacing: -.01em;
}
.etp .etp-sources__heading::before,
.etp .etp-sources__heading::after { content: none; }
.etp .etp-sources__intro { font-size: 16px; opacity: .78; }
.etp .etp-sources__body {
	display: grid;
	grid-template-columns: minmax(210px, 260px) minmax(0, 1fr);
	gap: 36px;
	align-items: start;
	margin-top: 28px;
}
.etp .etp-sources__body--solo { grid-template-columns: minmax(0, 1fr); }

/* Who wrote / reviewed it */
.etp .etp-sources__people { position: sticky; top: 24px; display: grid; gap: 10px; }
.etp .etp-pcard {
	display: flex;
	align-items: center;
	gap: 15px;
	padding: 14px 14px 14px 16px;
	border-radius: 16px;
	background: var(--etp-tint);
	border: 1px solid var(--etp-line);
}
.etp .etp-pcard .etp-av { --etp-av: 50px; }
.etp .etp-pcard__photo { display: block; flex: none; border-radius: 50%; }
.etp .etp-pcard__text { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.etp .etp-pcard__label { font-size: 11px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--etp-accent-text); }
.etp .etp-pcard__name { display: flex; align-items: center; font-weight: 700; font-size: 16px; line-height: 1.25; }
.etp .etp-pcard__role { font-size: 13px; line-height: 1.3; opacity: .68; }

/* Numbered references */
.etp .etp-sources__list-heading {
	display: flex;
	align-items: center;
	gap: 9px;
	margin: 0 0 6px;
	padding: 0;
	font-size: 12px;
	font-weight: 700;
	line-height: 1.3;
	letter-spacing: .12em;
	text-transform: uppercase;
}
.etp .etp-sources__list-heading::before,
.etp .etp-sources__list-heading::after { content: none; }
.etp .etp-sources__list-heading > span:first-child { opacity: .7; }
.etp .etp-sources__count {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-width: 22px;
	height: 22px;
	padding: 0 7px;
	border-radius: 999px;
	background: rgba(var(--etp-accent-rgb), .16);
	color: var(--etp-accent-ink);
	font-size: 11px;
	letter-spacing: 0;
}
.etp ol.etp-refs { margin: 0; padding: 0; list-style: none; }
.etp ol.etp-refs > li { margin: 0; padding: 0; list-style: none; border-top: 1px solid var(--etp-line); }
.etp ol.etp-refs > li:first-child { border-top: 0; }
.etp ol.etp-refs > li::before,
.etp ol.etp-refs > li::marker { content: none; }
.etp a.etp-ref {
	display: grid;
	grid-template-columns: 30px minmax(0, 1fr) 28px;
	gap: 12px;
	align-items: start;
	margin: 0 -12px;
	padding: 13px 12px;
	border-radius: 12px;
	color: inherit;
	transition: background-color .2s;
}
.etp a.etp-ref:hover,
.etp a.etp-ref:focus-visible { background: rgba(var(--etp-accent-rgb), .09); color: inherit; }
.etp .etp-ref__num {
	padding-top: 3px;
	font-size: 12.5px;
	font-weight: 700;
	line-height: 1.3;
	letter-spacing: .04em;
	color: var(--etp-accent-text);
	font-variant-numeric: tabular-nums;
}
.etp .etp-ref__text { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
.etp .etp-ref__title {
	font-weight: 600;
	font-size: 16px;
	line-height: 1.4;
	text-decoration: underline;
	text-decoration-color: rgba(var(--etp-accent-rgb), .45);
	text-decoration-thickness: 1.5px;
	text-underline-offset: 3px;
	transition: text-decoration-color .2s;
	overflow-wrap: anywhere;
}
.etp a.etp-ref:hover .etp-ref__title { text-decoration-color: var(--etp-accent); }
.etp .etp-ref__domain { font-size: 12.5px; opacity: .6; letter-spacing: .01em; }
.etp .etp-ref__go {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 28px;
	height: 28px;
	border-radius: 50%;
	color: var(--etp-accent-text);
	background: rgba(var(--etp-accent-rgb), .1);
	opacity: .65;
	transition: opacity .2s, transform .25s var(--etp-ease), background-color .2s;
}
.etp .etp-ref__go .etp-i { width: 15px; height: 15px; }
.etp a.etp-ref:hover .etp-ref__go { opacity: 1; transform: translate(2px, -2px); background: var(--etp-accent); color: var(--etp-on-accent); }

.etp .etp-sources__disclaimer {
	display: flex;
	align-items: flex-start;
	gap: 10px;
	margin: 28px 0 0;
	padding: 18px 0 0;
	border-top: 1px dashed var(--etp-line-strong);
	font-size: 13px;
	line-height: 1.55;
}
.etp .etp-sources__disclaimer > span { opacity: .75; }
.etp .etp-sources__disclaimer .etp-i { margin-top: 1px; color: var(--etp-accent-text); }

/* Update history */
.etp-history-wrap { margin: 2em 0; }
.etp.etp-history { margin: 20px 0 0; font-size: 14px; line-height: 1.5; }
.etp.etp-history > summary {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	padding: 9px 14px 9px 12px;
	border-radius: 999px;
	box-shadow: inset 0 0 0 1px var(--etp-line-strong);
	font-weight: 700;
	font-size: 13.5px;
	cursor: pointer;
	list-style: none;
	transition: box-shadow .2s, background-color .2s;
}
.etp.etp-history > summary::-webkit-details-marker { display: none; }
.etp.etp-history > summary::marker { content: ""; }
.etp.etp-history > summary:hover { box-shadow: inset 0 0 0 1px var(--etp-accent); background: rgba(var(--etp-accent-rgb), .06); }
.etp.etp-history > summary > .etp-i:first-child { color: var(--etp-accent-text); }
.etp.etp-history > summary > .etp-i:last-child { opacity: .6; transition: transform .25s var(--etp-ease); }
.etp.etp-history[open] > summary > .etp-i:last-child { transform: rotate(180deg); }
.etp .etp-history__count {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-width: 20px;
	height: 20px;
	padding: 0 6px;
	border-radius: 999px;
	background: rgba(var(--etp-accent-rgb), .16);
	color: var(--etp-accent-ink);
	font-size: 11px;
}
.etp ol.etp-history__list {
	position: relative;
	margin: 16px 0 0 8px;
	padding: 0 0 0 24px;
	list-style: none;
	border-left: 1.5px solid rgba(var(--etp-accent-rgb), .35);
}
.etp ol.etp-history__list li { position: relative; margin: 0 0 14px; padding: 0; list-style: none; }
.etp ol.etp-history__list li:last-child { margin-bottom: 0; }
.etp ol.etp-history__list li::before {
	content: "";
	position: absolute;
	left: -31px;
	top: 3px;
	width: 11px;
	height: 11px;
	border-radius: 50%;
	background: var(--etp-surface);
	border: 2px solid var(--etp-accent);
}
.etp ol.etp-history__list li:first-child::before { background: var(--etp-accent); box-shadow: 0 0 0 4px rgba(var(--etp-accent-rgb), .18); }
.etp ol.etp-history__list time {
	display: block;
	margin-bottom: 1px;
	font-size: 11.5px;
	font-weight: 700;
	letter-spacing: .08em;
	text-transform: uppercase;
	color: var(--etp-accent-text);
}

/* ================================================================== */
/* Profiles                                                           */
/* ================================================================== */
.etp-single-person__inner { max-width: 960px; margin: 0 auto; padding: clamp(32px, 6vw, 80px) 20px; }
.etp.etp-profile { margin-top: 2.5em; line-height: 1.6; }
.etp-single-person__inner > .etp.etp-profile:first-child { margin-top: 0; }
.etp .etp-profile__hero {
	display: grid;
	grid-template-columns: auto minmax(0, 1fr);
	gap: clamp(28px, 5vw, 56px);
	align-items: center;
	margin: 0 0 40px;
}
.etp .etp-profile__photo { position: relative; width: 200px; height: 200px; margin: 0 14px 14px 0; isolation: isolate; }
.etp .etp-profile__photo::before {
	content: "";
	position: absolute;
	inset: 0;
	z-index: -1;
	border: 2px solid var(--etp-accent);
	border-radius: 36px;
	transform: translate(14px, 14px);
}
.etp .etp-profile__photo::after {
	inset: -22px auto auto -22px;
	width: 140px;
	height: 140px;
	z-index: -2;
	border-radius: 36px;
	-webkit-mask-image: radial-gradient(140px circle at 0 0, #000, transparent 85%);
	mask-image: radial-gradient(140px circle at 0 0, #000, transparent 85%);
	background:
		repeating-radial-gradient(circle at 0 0, transparent 0 7px, rgba(var(--etp-accent-rgb), .28) 7px 8px);
}
.etp .etp-profile__photo .etp-avatar { --etp-size: 200px; width: 200px; height: 200px; border-radius: 36px; font-size: 56px; }
.etp .etp-profile__intro .etp-eyebrow { margin-bottom: 14px; }
.etp .etp-profile__name { margin: 0 0 12px; line-height: 1.06; letter-spacing: -.015em; }
.etp .etp-profile__summary { margin: 0 0 22px; font-size: 1.1em; opacity: .82; max-width: 60ch; }
.etp .etp-profile__actions { display: flex; flex-wrap: wrap; gap: 10px; }

.etp ul.etp-stats {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
	gap: 12px;
	margin: 0 0 48px;
	padding: 0;
	list-style: none;
}
.etp ul.etp-stats li {
	position: relative;
	margin: 0;
	padding: 18px 20px 16px;
	border: 1px solid var(--etp-line);
	border-radius: 18px;
	background: var(--etp-surface);
	list-style: none;
	overflow: hidden;
}
.etp ul.etp-stats li::before { content: ""; position: absolute; left: 20px; top: 0; width: 28px; height: 3px; border-radius: 0 0 3px 3px; background: var(--etp-accent); }
.etp .etp-stats strong { display: block; font-size: 34px; font-weight: 800; line-height: 1; letter-spacing: -.02em; color: var(--etp-accent-ink); font-variant-numeric: tabular-nums; }
.etp .etp-stats span { display: block; margin-top: 8px; font-size: 13px; line-height: 1.3; opacity: .7; }

.etp .etp-profile__section,
.etp.etp-expertise,
.etp.etp-articles,
.etp .etp-expertise,
.etp .etp-articles { margin: 0 0 48px; }
.etp .etp-section-heading {
	position: relative;
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	gap: 10px;
	margin: 0 0 20px;
	padding: 16px 0 0;
	line-height: 1.2;
}
.etp .etp-section-heading::before {
	content: "";
	position: absolute;
	left: 0;
	top: 0;
	width: 34px;
	height: 3px;
	border-radius: 3px;
	background: var(--etp-accent);
}
.etp .etp-section-heading::after { content: none; }
.etp .etp-eyebrow + .etp-section-heading { padding-top: 0; }
.etp .etp-eyebrow + .etp-section-heading::before { content: none; }
.etp .etp-articles__count {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-width: 26px;
	height: 26px;
	padding: 0 8px;
	border-radius: 999px;
	background: rgba(var(--etp-accent-rgb), .16);
	color: var(--etp-accent-ink);
	font-size: 13px;
	font-weight: 700;
	letter-spacing: 0;
	line-height: 1;
}
.etp .etp-prose p { margin: 0 0 1em; }
.etp .etp-prose > :first-child { margin-top: 0; }
.etp .etp-prose > :last-child { margin-bottom: 0; }

/* Expertise chips with a diamond marker */
.etp ul.etp-chips { display: flex; flex-wrap: wrap; gap: 8px; margin: 0; padding: 0; list-style: none; }
.etp ul.etp-chips li {
	display: inline-flex;
	align-items: center;
	gap: 9px;
	margin: 0;
	padding: 9px 16px 9px 13px;
	border: 1px solid var(--etp-line-strong);
	border-radius: 999px;
	background: var(--etp-surface);
	font-size: 14px;
	font-weight: 600;
	line-height: 1.2;
	list-style: none;
	transition: border-color .2s, background-color .2s;
}
.etp ul.etp-chips li::before { content: ""; flex: none; width: 7px; height: 7px; border-radius: 1.5px; transform: rotate(45deg); background: var(--etp-accent); }
.etp ul.etp-chips li:hover { border-color: var(--etp-accent); background: var(--etp-accent-soft); }

/* Qualifications as tiles */
.etp ul.etp-quals { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 260px), 1fr)); gap: 10px; margin: 0; padding: 0; list-style: none; }
.etp ul.etp-quals li {
	display: flex;
	align-items: center;
	gap: 14px;
	margin: 0;
	padding: 14px 16px;
	border: 1px solid var(--etp-line);
	border-radius: 16px;
	background: var(--etp-surface);
	font-weight: 600;
	line-height: 1.4;
	list-style: none;
}
.etp ul.etp-quals li::before { content: none; }
.etp .etp-quals__icon { display: flex; align-items: center; justify-content: center; flex: none; width: 38px; height: 38px; border-radius: 12px; background: var(--etp-accent-soft); color: var(--etp-accent-ink); }

/* Written by / Reviewed by cards */
.etp ul.etp-cards {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(min(100%, 250px), 1fr));
	gap: 20px;
	margin: 0;
	padding: 0;
	list-style: none;
}
.etp .etp-card {
	position: relative;
	display: flex;
	flex-direction: column;
	margin: 0;
	padding: 0;
	border: 1px solid var(--etp-line);
	border-radius: 20px;
	background: var(--etp-surface);
	overflow: hidden;
	list-style: none;
	transition: transform .3s var(--etp-ease), box-shadow .3s var(--etp-ease), border-color .3s;
}
.etp .etp-card::before { content: none; }
.etp .etp-card:hover { transform: translateY(-4px); box-shadow: 0 22px 44px -24px rgba(17, 24, 39, .4); border-color: rgba(var(--etp-accent-rgb), .55); }
.etp .etp-card__media { position: relative; display: block; aspect-ratio: 16 / 10; overflow: hidden; background: var(--etp-accent-soft); }
.etp .etp-card__media img,
.etp img.etp-card__img {
	/* !important: some themes set inline width/height on images with JS. */
	position: absolute; inset: 0; display: block; width: 100% !important; height: 100% !important; max-width: none !important; margin: 0; object-fit: cover; border-radius: 0;
	transition: transform .6s var(--etp-ease);
}
.etp .etp-card:hover .etp-card__media img { transform: scale(1.05); }
.etp .etp-card__placeholder { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; color: var(--etp-accent); isolation: isolate; }
.etp .etp-card__placeholder::before { inset: 0; z-index: -1; }
.etp .etp-card__placeholder .etp-i { width: 30px; height: 30px; stroke-width: 1.6; }
.etp .etp-card__body { display: flex; flex-direction: column; flex: 1 1 auto; padding: 18px 20px 20px; }
.etp .etp-card__date { margin: 0 0 8px; font-size: 11px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--etp-accent-text); }
.etp a.etp-card__title { display: block; margin: 0 0 8px; font-size: 18px; font-weight: 700; line-height: 1.3; color: inherit; }
.etp a.etp-card__title::after { content: ""; position: absolute; inset: 0; z-index: 1; border-radius: inherit; }
.etp a.etp-card__title:hover { color: inherit; }
.etp .etp-card__excerpt {
	margin: 0 0 16px;
	font-size: 14.5px;
	line-height: 1.55;
	opacity: .76;
	display: -webkit-box;
	-webkit-line-clamp: 3;
	-webkit-box-orient: vertical;
	overflow: hidden;
}
.etp .etp-card__more { display: inline-flex; align-items: center; gap: 7px; margin-top: auto; font-size: 13.5px; font-weight: 700; color: var(--etp-accent-text); }
.etp .etp-card__more .etp-i { transition: transform .25s var(--etp-ease); }
.etp .etp-card:hover .etp-card__more .etp-i { transform: translateX(4px); }
.etp .etp-cards-more { margin: 22px 0 0; }
.etp .etp-cards-more > summary {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	padding: 12px 22px;
	border-radius: 999px;
	box-shadow: inset 0 0 0 1.5px var(--etp-line-strong);
	font-weight: 700;
	font-size: 14px;
	cursor: pointer;
	list-style: none;
	transition: box-shadow .2s, background-color .2s;
}
.etp .etp-cards-more > summary::-webkit-details-marker { display: none; }
.etp .etp-cards-more > summary::marker { content: ""; }
.etp .etp-cards-more > summary:hover { box-shadow: inset 0 0 0 1.5px var(--etp-accent); background: rgba(var(--etp-accent-rgb), .06); }
.etp .etp-cards-more[open] > summary { display: none; }

/* Narrow content columns (e.g. a sidebar layout) */
@container (max-width: 700px) {
	.etp .etp-sources__body { grid-template-columns: minmax(0, 1fr); gap: 24px; }
	.etp .etp-sources__people { position: static; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
}

/* Tablet ----------------------------------------------------------- */
@media (max-width: 900px) {
	.etp .etp-sources__body { grid-template-columns: minmax(0, 1fr); gap: 24px; }
	.etp .etp-sources__people { position: static; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); }
}

/* ================================================================== */
/* Author box (end of article)                                        */
/* ================================================================== */
.etp.etp-abox { margin: 2.5em 0; padding: clamp(20px, 3vw, 28px); border: 1px solid var(--etp-line); border-radius: var(--etp-radius); background: var(--etp-surface); box-shadow: var(--etp-shadow); }
.etp .etp-abox__heading { margin: 0 0 18px; padding: 0; font-size: clamp(20px, 2.2vw, 24px); line-height: 1.2; }
.etp .etp-abox__grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; }
.etp .etp-abox__card { display: flex; align-items: flex-start; gap: 14px; padding: 16px; border-radius: 14px; background: var(--etp-tint); border: 1px solid var(--etp-line); }
.etp .etp-abox__photo { display: block; flex: none; }
.etp .etp-abox__photo .etp-avatar { --etp-size: 72px; width: 72px; height: 72px; outline: 1.5px solid var(--etp-accent); outline-offset: 2.5px; }
.etp .etp-abox__body { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
.etp .etp-abox__role { font-size: 11px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--etp-accent-text); }
.etp .etp-abox__name { display: block; font-size: 17px; font-weight: 700; line-height: 1.25; }
.etp .etp-abox__name .etp-linkedin { vertical-align: -2px; }
.etp .etp-abox__name a:not(.etp-linkedin) { color: inherit; }
.etp .etp-abox__name a:not(.etp-linkedin):hover { text-decoration: underline; text-underline-offset: 3px; }
.etp .etp-abox__job { font-size: 13.5px; opacity: var(--etp-muted); }
.etp .etp-abox__summary { margin-top: 6px; font-size: 14px; line-height: 1.5; }
.etp a.etp-abox__more { display: inline-flex; align-items: center; gap: 6px; margin-top: 8px; font-size: 14px; font-weight: 700; color: var(--etp-accent-text); }
.etp a.etp-abox__more .etp-i { transition: transform .25s var(--etp-ease); }
.etp a.etp-abox__more:hover .etp-i { transform: translateX(3px); }

/* Mobile ----------------------------------------------------------- */
@media (max-width: 640px) {
	.etp.etp-trust--card { padding: 14px 16px; }
	.etp .etp-abox__grid { grid-template-columns: minmax(0, 1fr); }
	.etp .etp-abox__photo .etp-avatar { --etp-size: 56px; width: 56px; height: 56px; }
	.etp .etp-trust__clip { overflow: visible; margin-left: 0; padding-left: 0; }
	.etp .etp-trust__row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px 14px; margin-left: 0; }
	.etp .etp-step { padding-left: 0; gap: 10px; }
	.etp .etp-step::before,
	.etp .etp-step::after { content: none; }
	.etp .etp-step--author:only-child { grid-column: 1 / -1; }
	.etp .etp-step--fc { grid-column: 1 / -1; padding-top: 12px; border-top: 1px dashed var(--etp-line-strong); }
	.etp .etp-fc__toggle { margin: -4px; padding: 4px 8px 4px 4px; }
	.etp .etp-step .etp-av { --etp-av: 36px; }
	.etp .etp-av__badge { width: 17px; height: 17px; right: -5px; bottom: -4px; }
	.etp .etp-av__badge .etp-i { width: 9px; height: 9px; }
	.etp .etp-step__label { font-size: 10px; letter-spacing: .08em; }
	.etp .etp-step__name { font-size: 14.5px; }
	.etp .etp-step__role { font-size: 12px; }
	.etp .etp-seal { width: 36px; height: 36px; }
	.etp .etp-seal::before { inset: -3px; }
	.etp .etp-seal .etp-i { width: 22px; height: 22px; }
	.etp .etp-step--fc .etp-step__text { flex-direction: row; flex-wrap: wrap; align-items: baseline; column-gap: 10px; }

	.etp.etp-cta { flex-wrap: wrap; padding: 16px; }
	.etp .etp-cta__text { flex-basis: calc(100% - 72px); }
	.etp a.etp-cta__link { width: 100%; }

	.etp.etp-sources { border-radius: 20px; }
	.etp a.etp-ref { grid-template-columns: 26px minmax(0, 1fr) 24px; gap: 10px; }
	.etp .etp-ref__go { width: 24px; height: 24px; }
	.etp .etp-ref__title { font-size: 15px; }

	.etp .etp-profile__hero { grid-template-columns: minmax(0, 1fr); gap: 24px; }
	.etp .etp-profile__photo,
	.etp .etp-profile__photo .etp-avatar { width: 136px; height: 136px; font-size: 40px; }
	.etp .etp-profile__photo::before,
	.etp .etp-profile__photo .etp-avatar { border-radius: 28px; }
	.etp .etp-profile__photo::before { transform: translate(10px, 10px); }
	.etp ul.etp-stats { grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: 8px; }
	.etp ul.etp-stats li { padding: 16px 14px 14px; }
	.etp ul.etp-stats li::before { left: 14px; }
	.etp .etp-stats strong { font-size: 28px; }
}
@media (max-width: 480px) {
	.etp .etp-trust__row { grid-template-columns: minmax(0, 1fr); gap: 12px; }
	.etp .etp-step + .etp-step { padding-top: 12px; border-top: 1px dashed var(--etp-line-strong); }
	.etp .etp-step__label { font-size: 10.5px; }
	.etp .etp-step__name { font-size: 15px; }
}

@media (prefers-reduced-motion: reduce) {
	.etp *, .etp *::before, .etp *::after { transition: none !important; animation: none !important; }
}

@media print {
	.etp .etp-fc__panel, .etp.etp-cta, .etp .etp-step__hint { display: none !important; }
	.etp.etp-trust--card, .etp.etp-sources { box-shadow: none; }
}
ETP_CSS;
	}
}

Etpro::boot();

endif;