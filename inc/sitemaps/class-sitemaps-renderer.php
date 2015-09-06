<?php
/**
 * @package WPSEO\XML_Sitemaps
 */

/**
 * Renders XML output for sitemaps.
 */
class WPSEO_Sitemaps_Renderer {

	/** @var string $charset Holds the get_bloginfo( 'charset' ) value to reuse for performance. */
	protected $charset = 'UTF-8';

	/** @var WPSEO_Sitemap_Timezone $timezone */
	protected $timezone;

	/**
	 * Set up object properties.
	 */
	public function __construct(  ) {

		$this->charset  = get_bloginfo( 'charset' );
		$this->timezone = new WPSEO_Sitemap_Timezone();
	}

	/**
	 * @param array $links Set of sitemaps index links.
	 *
	 * @return string
	 */
	public function get_index( $links ) {

		$xml = '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ( $links as $link ) {
			$xml .= $this->sitemap_index_url( $link );
		}

		// Allow other plugins to add their sitemaps to the index.
		// TODO document filter. R.
		$xml .= apply_filters( 'wpseo_sitemap_index', '' );
		$xml .= '</sitemapindex>';

		return $xml;
	}

	/**
	 * @param array  $links        Set of sitemap links.
	 * @param string $type         Sitemap type.
	 * @param int    $current_page Current sitemap page number.
	 *
	 * @return string
	 */
	public function get_sitemap( $links, $type, $current_page ) {

		$xml =
			'<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" '
			. 'xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd" '
			. 'xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ( $links as $url ) {
			$xml .= $this->sitemap_url( $url );
		}

		// Filter to allow adding extra URLs, only do this on the first XML sitemap, not on all.
		if ( $current_page === 1 ) {
			$xml .= apply_filters( "wpseo_sitemap_{$type}_content", '' ); // TODO document filter. R.
		}

		$xml .= '</urlset>';

		return $xml;
	}

	/**
	 * Produce final XML output with debug information.
	 *
	 * @param string  $sitemap    Sitemap XML.
	 * @param string  $stylesheet Stylesheet XML.
	 * @param boolean $transient  Transient cache flag.
	 *
	 * @return string
	 */
	public function get_output( $sitemap, $stylesheet, $transient ) {

		$output = '<?xml version="1.0" encoding="' . esc_attr( $this->charset ) . '"?>';

		if ( $stylesheet ) {
			$output .= apply_filters( 'wpseo_stylesheet_url', $stylesheet ) . "\n";
		}

		$output .= $sitemap;
		$output .= "\n<!-- XML Sitemap generated by Yoast SEO -->";

		$debug = WP_DEBUG || ( defined( 'WPSEO_DEBUG' ) && true === WPSEO_DEBUG );

		if ( ! WP_DEBUG_DISPLAY || ! $debug ) {
			return $output;
		}

		$memory_used = number_format( ( memory_get_peak_usage() / 1024 / 1024 ), 2 );
		$queries_run = ( $transient ) ? 'Served from transient cache' : absint( $GLOBALS['wpdb']->num_queries );

		$output .= "\n<!-- {$memory_used}MB | {$queries_run} -->";

		if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES ) {

			$queries = print_r( $GLOBALS['wpdb']->queries, true );
			$output .= "\n<!-- {$queries} -->";
		}

		return $output;
	}

	/**
	 * Build the `<sitemap>` tag for a given URL.
	 *
	 * @param array $url Array of parts that make up this entry.
	 *
	 * @return string
	 */
	protected function sitemap_index_url( $url ) {

		$date = null;

		if ( ! empty( $url['lastmod'] ) ) {
			$date = $this->timezone->format_date( $url['lastmod'] );
		}

		$url['loc'] = htmlspecialchars( $url['loc'] );

		$output = "\t<sitemap>\n";
		$output .= "\t\t<loc>" . $url['loc'] . "</loc>\n";
		$output .= empty( $date ) ? '' : "\t\t<lastmod>" . htmlspecialchars( $date ) . "</lastmod>\n";
		$output .= "\t</sitemap>\n";

		return $output;
	}

	/**
	 * Build the `<url>` tag for a given URL.
	 *
	 * Public access for backwards compatibility reasons.
	 *
	 * @param array $url Array of parts that make up this entry.
	 *
	 * @return string
	 */
	public function sitemap_url( $url ) {

		$date = null;


		if ( ! empty( $url['mod'] ) ) {
			// Create a DateTime object date in the correct timezone.
			$date = $this->timezone->format_date( $url['mod'] );
		}

		$url['loc'] = htmlspecialchars( $url['loc'] );

		$output = "\t<url>\n";
		$output .= "\t\t<loc>" . $url['loc'] . "</loc>\n";
		$output .= empty( $date ) ? '' : "\t\t<lastmod>" . htmlspecialchars( $date ) . "</lastmod>\n";
		$output .= "\t\t<changefreq>" . $url['chf'] . "</changefreq>\n";
		$output .= "\t\t<priority>" . str_replace( ',', '.', $url['pri'] ) . "</priority>\n";

		if ( empty( $url['images'] ) ) {
			$url['images'] = array();
		}

		foreach ( $url['images'] as $img ) {

			if ( empty( $img['src'] ) ) {
				continue;
			}

			$output .= "\t\t<image:image>\n";
			$output .= "\t\t\t<image:loc>" . esc_html( $img['src'] ) . "</image:loc>\n";

			if ( ! empty( $img['title'] ) ) {
				$title = _wp_specialchars( html_entity_decode( $img['title'], ENT_QUOTES, $this->charset ) );
				$output .= "\t\t\t<image:title><![CDATA[{$title}]]></image:title>\n";
			}

			if ( ! empty( $img['alt'] ) ) {
				$alt = _wp_specialchars( html_entity_decode( $img['alt'], ENT_QUOTES, $this->charset ) );
				$output .= "\t\t\t<image:caption><![CDATA[{$alt}]]></image:caption>\n";
			}

			$output .= "\t\t</image:image>\n";
		}
		unset( $img, $title, $alt );

		$output .= "\t</url>\n";

		return $output;
	}
}