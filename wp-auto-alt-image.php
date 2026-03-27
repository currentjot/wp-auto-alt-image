<?php
/**
 * Plugin Name:  WP Auto Alt Image
 * Plugin URI:   https://github.com/currentjot/wp-auto-alt-image
 * Description:  Sostituisce gli alt delle immagini sul frontend usando il titolo del post. Zero modifiche al DB. Rilevamento lingua via slug (/{LANG}/slug).
 * Version:      1.0.0
 * Author:       currentjot
 * License:      GPL-2.0+
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:  wp-auto-alt-image
 *
 * @package WP_Auto_Alt_Image
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPAAI_VERSION',    '1.0.0' );
define( 'WPAAI_OPTION_KEY', 'wpaai_options' );

// ─────────────────────────────────────────────────────────────────────────────
// HELPERS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Restituisce le opzioni salvate con i valori di default.
 *
 * @return array
 */
function wpaai_get_options() {
	$defaults = array(
		'enabled'          => 1,
		'replace_mode'     => 'empty_only',  // 'empty_only' | 'all'
		'fallback'         => 'site_title',  // 'site_title' | 'filename' | 'empty'
		'separator'        => '',
		'append_site_name' => 0,
		'post_types'       => array( 'post', 'page' ),
		'excluded_ids'     => '',
		'debug_mode'       => 0,
	);

	return wp_parse_args( get_option( WPAAI_OPTION_KEY, array() ), $defaults );
}

// ─────────────────────────────────────────────────────────────────────────────
// LANG DETECTION (slug-based only)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Cerca un prefisso di lingua nell'URL corrente.
 * Supporta pattern: /{LANG}/ con codici a 2 lettere o locale (es. /en/, /pt-BR/).
 *
 * @return string|null Codice lingua (es. 'en', 'it') o null se non trovato.
 */
function wpaai_detect_lang_from_slug() {
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
	$path        = parse_url( $request_uri, PHP_URL_PATH );

	if ( ! $path ) {
		return null;
	}

	// Rimuove eventuale sottodirectory di installazione WordPress.
	$home_path = parse_url( home_url(), PHP_URL_PATH );
	if ( $home_path && '/' !== $home_path ) {
		$path = substr( $path, strlen( $home_path ) );
	}

	$parts = explode( '/', trim( $path, '/' ) );

	// Il primo segmento deve essere un codice lingua valido (es. "en", "pt-BR").
	if ( ! empty( $parts[0] ) && preg_match( '/^[a-z]{2}(-[A-Za-z]{2,4})?$/i', $parts[0] ) ) {
		return strtolower( $parts[0] );
	}

	return null;
}

// ─────────────────────────────────────────────────────────────────────────────
// CORE — OUTPUT BUFFER
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Avvia il buffer di output, se il contesto è eleggibile.
 */
function wpaai_maybe_start_buffer() {
	$options = wpaai_get_options();

	if ( empty( $options['enabled'] ) ) {
		return;
	}

	// Escludi feed, AJAX, REST, preview.
	if (
		is_feed()
		|| wp_doing_ajax()
		|| ( defined( 'REST_REQUEST' ) && REST_REQUEST )
		|| is_preview()
	) {
		return;
	}

	// Verifica contesto eleggibile (post type + ID esclusi).
	if ( ! wpaai_is_eligible_context( $options ) ) {
		return;
	}

	ob_start( 'wpaai_process_output' );
}
add_action( 'template_redirect', 'wpaai_maybe_start_buffer' );

/**
 * Callback di ob_start: riceve l'HTML completo e restituisce quello modificato.
 *
 * @param  string $html HTML originale della pagina.
 * @return string HTML con gli alt sostituiti.
 */
function wpaai_process_output( $html ) {
	if ( empty( $html ) ) {
		return $html;
	}

	$options  = wpaai_get_options();
	$alt_text = wpaai_resolve_alt_text( $options );

	// Sostituzione regex su tutti i tag <img>.
	$html = preg_replace_callback(
		'/<img(\s[^>]*)?>/is',
		function ( $matches ) use ( $alt_text, $options ) {
			return wpaai_replace_img_alt( $matches[0], $alt_text, $options );
		},
		$html
	);

	// Debug mode: commento HTML + console.log.
	if ( ! empty( $options['debug_mode'] ) ) {
		$lang = wpaai_detect_lang_from_slug() ?? 'none';

		$comment = sprintf(
			"\n<!-- [WPAAI DEBUG] lang=%s | alt=\"%s\" -->\n",
			esc_html( $lang ),
			esc_html( $alt_text )
		);
		$js = sprintf(
			'<script>console.log("[WPAAI] lang=%s | alt=%s");</script>',
			esc_js( $lang ),
			esc_js( $alt_text )
		);

		$html = str_replace( '</body>', $comment . $js . '</body>', $html );
	}

	return $html;
}

/**
 * Sostituisce l'attributo alt in un singolo tag <img>.
 *
 * @param  string $img_tag  Tag <img> originale.
 * @param  string $alt_text Testo alt da impostare.
 * @param  array  $options  Opzioni plugin.
 * @return string Tag <img> modificato.
 */
function wpaai_replace_img_alt( $img_tag, $alt_text, $options ) {
	$replace_mode = $options['replace_mode'] ?? 'empty_only';

	$has_alt   = (bool) preg_match( '/\balt\s*=/i', $img_tag );
	$alt_empty = $has_alt && (bool) preg_match( '/\balt\s*=\s*(["\'])\s*\1/i', $img_tag );

	// Modalità "solo vuoti": salta se alt è già valorizzato.
	if ( 'empty_only' === $replace_mode && $has_alt && ! $alt_empty ) {
		return $img_tag;
	}

	$escaped = esc_attr( $alt_text );

	if ( $has_alt ) {
		// Sostituisce il valore dell'alt esistente.
		return preg_replace(
			'/\balt\s*=\s*(["\'])[^"\']*\1/i',
			'alt="' . $escaped . '"',
			$img_tag,
			1
		);
	}

	// Aggiunge alt prima della chiusura del tag.
	return preg_replace(
		'/(\s*\/?>)$/i',
		' alt="' . $escaped . '"$1',
		$img_tag,
		1
	);
}

/**
 * Calcola il testo alt per la pagina corrente.
 *
 * WordPress, grazie al routing basato sullo slug, carica già il post corretto
 * nella lingua giusta quando l'URL è /{LANG}/slug. Quindi get_the_title()
 * restituisce il titolo nella lingua del post richiesto.
 *
 * @param  array $options Opzioni plugin.
 * @return string
 */
function wpaai_resolve_alt_text( $options ) {
	$title   = '';
	$post_id = get_queried_object_id();

	if ( is_singular() && $post_id ) {
		$title = get_the_title( $post_id );
	} elseif ( is_home() || is_front_page() ) {
		$title = get_bloginfo( 'name' );
	} elseif ( is_archive() ) {
		$title = get_the_archive_title();
	} elseif ( is_search() ) {
		$title = sprintf( 'Ricerca: %s', get_search_query() );
	}

	// Fallback se ancora vuoto.
	if ( empty( $title ) ) {
		$title = wpaai_get_fallback( $options );
	}

	// Suffisso nome sito.
	if ( ! empty( $options['append_site_name'] ) && ! empty( $options['separator'] ) ) {
		$site = get_bloginfo( 'name' );
		if ( $site && $title !== $site ) {
			$title = $title . $options['separator'] . $site;
		}
	}

	/**
	 * Filtro per personalizzare il testo alt finale.
	 *
	 * @param string $title   Alt calcolato.
	 * @param int    $post_id ID post corrente.
	 * @param array  $options Opzioni plugin.
	 */
	return apply_filters( 'wpaai_alt_text', $title, $post_id, $options );
}

/**
 * Restituisce il testo di fallback.
 *
 * @param  array $options
 * @return string
 */
function wpaai_get_fallback( $options ) {
	switch ( $options['fallback'] ?? 'site_title' ) {
		case 'empty':
			return '';
		case 'site_title':
		default:
			return get_bloginfo( 'name' );
	}
}

/**
 * Verifica se il contesto corrente è eleggibile per la sostituzione.
 *
 * @param  array $options
 * @return bool
 */
function wpaai_is_eligible_context( $options ) {
	if ( ! is_singular() ) {
		return true; // archivi, ricerca, homepage: sempre sì.
	}

	$post = get_queried_object();

	if ( ! $post instanceof WP_Post ) {
		return true;
	}

	// Post type consentito?
	$allowed = (array) ( $options['post_types'] ?? array( 'post', 'page' ) );
	if ( ! in_array( $post->post_type, $allowed, true ) ) {
		return false;
	}

	// ID escluso?
	$excluded_raw = $options['excluded_ids'] ?? '';
	if ( ! empty( $excluded_raw ) ) {
		$excluded = array_map( 'absint', explode( ',', $excluded_raw ) );
		if ( in_array( (int) $post->ID, $excluded, true ) ) {
			return false;
		}
	}

	return true;
}

// ─────────────────────────────────────────────────────────────────────────────
// ADMIN — PANNELLO IMPOSTAZIONI
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'admin_menu', 'wpaai_add_menu' );
function wpaai_add_menu() {
	add_options_page(
		'WP Auto Alt Image',
		'Auto Alt Image',
		'manage_options',
		'wp-auto-alt-image',
		'wpaai_render_page'
	);
}

add_action( 'admin_init', 'wpaai_register_settings' );
function wpaai_register_settings() {
	register_setting( 'wpaai_group', WPAAI_OPTION_KEY, 'wpaai_sanitize_options' );
}

/**
 * Sanitizza l'input prima del salvataggio.
 *
 * @param  array $input
 * @return array
 */
function wpaai_sanitize_options( $input ) {
	$clean = array();

	$clean['enabled']          = ! empty( $input['enabled'] ) ? 1 : 0;
	$clean['replace_mode']     = in_array( $input['replace_mode'] ?? '', array( 'empty_only', 'all' ), true )
	                              ? $input['replace_mode'] : 'empty_only';
	$clean['fallback']         = in_array( $input['fallback'] ?? '', array( 'site_title', 'empty' ), true )
	                              ? $input['fallback'] : 'site_title';
	$clean['separator']        = sanitize_text_field( $input['separator'] ?? '' );
	$clean['append_site_name'] = ! empty( $input['append_site_name'] ) ? 1 : 0;
	$clean['debug_mode']       = ! empty( $input['debug_mode'] ) ? 1 : 0;

	$clean['post_types'] = isset( $input['post_types'] ) && is_array( $input['post_types'] )
	                        ? array_map( 'sanitize_key', $input['post_types'] )
	                        : array( 'post', 'page' );

	if ( ! empty( $input['excluded_ids'] ) ) {
		$ids = array_filter( array_map( 'absint', explode( ',', $input['excluded_ids'] ) ) );
		$clean['excluded_ids'] = implode( ',', $ids );
	} else {
		$clean['excluded_ids'] = '';
	}

	add_settings_error( WPAAI_OPTION_KEY, 'wpaai_saved', 'Impostazioni salvate.', 'success' );

	return $clean;
}

add_action( 'admin_head', 'wpaai_admin_inline_css' );
function wpaai_admin_inline_css() {
	$screen = get_current_screen();
	if ( ! $screen || 'settings_page_wp-auto-alt-image' !== $screen->id ) {
		return;
	}
	?>
	<style>
	.wpaai-wrap { max-width: 800px; }
	.wpaai-wrap h1 { display:flex; align-items:center; gap:8px; }
	.wpaai-ver { font-size:.72rem; background:#2271b1; color:#fff; padding:2px 8px; border-radius:12px; font-weight:400; }
	.wpaai-sub { color:#646970; margin-bottom:24px; }
	.wpaai-card { background:#fff; border:1px solid #c3c4c7; border-radius:6px; box-shadow:0 1px 3px rgba(0,0,0,.07); margin-bottom:20px; }
	.wpaai-card h2 { margin:0; padding:12px 20px; font-size:.95rem; background:#f6f7f7; border-bottom:1px solid #c3c4c7; border-radius:6px 6px 0 0; }
	.wpaai-card .form-table { margin:0; }
	.wpaai-card .form-table th, .wpaai-card .form-table td { padding:13px 20px; }
	.wpaai-card .form-table tr+tr th, .wpaai-card .form-table tr+tr td { border-top:1px solid #f0f0f1; }
	.wpaai-card--dbg { border-color:#dba617; }
	.wpaai-card--dbg h2 { background:#fef9e7; border-bottom-color:#dba617; }
	.wpaai-card--info { border-color:#2271b1; }
	.wpaai-card--info h2 { background:#f0f6fc; border-bottom-color:#c8dcf0; }
	.wpaai-card--info p, .wpaai-card--info ul { padding-left:20px; padding-right:20px; }
	.wpaai-card--info ul { list-style:disc; padding-left:40px; margin:0 0 14px; }
	/* Toggle */
	.wpaai-tgl { position:relative; display:inline-flex; align-items:center; cursor:pointer; }
	.wpaai-tgl input { opacity:0; width:0; height:0; position:absolute; }
	.wpaai-tgl-s { display:inline-block; width:44px; height:24px; background:#c3c4c7; border-radius:12px; position:relative; transition:background .2s; flex-shrink:0; }
	.wpaai-tgl-s::after { content:''; position:absolute; top:3px; left:3px; width:18px; height:18px; background:#fff; border-radius:50%; box-shadow:0 1px 3px rgba(0,0,0,.3); transition:transform .2s; }
	.wpaai-tgl input:checked + .wpaai-tgl-s { background:#00a32a; }
	.wpaai-tgl input:checked + .wpaai-tgl-s::after { transform:translateX(20px); }
	/* Info box */
	.wpaai-ibox { margin:0 20px 20px; background:#f0f6fc; border:1px solid #c8dcf0; border-radius:6px; padding:10px 14px; font-size:.875rem; }
	.wpaai-ibox code { background:#e0ecf9; padding:1px 6px; border-radius:3px; }
	/* Debug active badge */
	.wpaai-dbg-on { margin-top:8px; background:#fef9e7; border:1px solid #dba617; border-radius:6px; padding:8px 12px; font-size:.875rem; color:#6b4b00; }
	/* fieldset */
	.form-table fieldset label { display:flex !important; align-items:flex-start; gap:8px; margin-bottom:10px; cursor:pointer; }
	.form-table fieldset label p.description { margin:2px 0 0; font-size:.8125rem; color:#646970; }
	/* submit */
	.wpaai-wrap .submit { padding-left:0; }
	</style>
	<?php
}

/**
 * Render della pagina impostazioni.
 */
function wpaai_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$o          = wpaai_get_options();
	$post_types = get_post_types( array( 'public' => true ), 'objects' );
	$lang       = wpaai_detect_lang_from_slug();
	$key        = WPAAI_OPTION_KEY;
	?>
	<div class="wrap wpaai-wrap">

		<h1>
			<span>🖼️</span>
			WP Auto Alt Image
			<span class="wpaai-ver">v<?php echo esc_html( WPAAI_VERSION ); ?></span>
		</h1>
		<p class="wpaai-sub">Sostituisce gli alt delle immagini sul frontend in modo dinamico, senza toccare il database.</p>

		<?php settings_errors( $key ); ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'wpaai_group' ); ?>

			<!-- ░ Stato ░ -->
			<div class="wpaai-card">
				<h2>⚙️ Stato del plugin</h2>
				<table class="form-table" role="presentation"><tbody>
					<tr>
						<th><?php esc_html_e( 'Abilita plugin', 'wp-auto-alt-image' ); ?></th>
						<td>
							<label class="wpaai-tgl">
								<input type="checkbox" name="<?php echo esc_attr( $key ); ?>[enabled]" value="1" <?php checked( $o['enabled'], 1 ); ?> />
								<span class="wpaai-tgl-s"></span>
							</label>
							<p class="description">Quando disabilitato, il plugin non altera nulla sul frontend.</p>
						</td>
					</tr>
				</tbody></table>
			</div>

			<!-- ░ Sostituzione ░ -->
			<div class="wpaai-card">
				<h2>🔄 Modalità di sostituzione</h2>
				<table class="form-table" role="presentation"><tbody>
					<tr>
						<th>Quali alt sostituire</th>
						<td>
							<fieldset>
								<label>
									<input type="radio" name="<?php echo esc_attr( $key ); ?>[replace_mode]" value="empty_only" <?php checked( $o['replace_mode'], 'empty_only' ); ?> />
									<span><strong>Solo alt vuoti o mancanti</strong><p class="description">Salta le immagini che hanno già un alt valorizzato.</p></span>
								</label>
								<label>
									<input type="radio" name="<?php echo esc_attr( $key ); ?>[replace_mode]" value="all" <?php checked( $o['replace_mode'], 'all' ); ?> />
									<span><strong>Tutti gli alt (anche quelli già valorizzati)</strong><p class="description">Sovrascrive qualsiasi alt esistente con il titolo del post corrente.</p></span>
								</label>
							</fieldset>
						</td>
					</tr>
				</tbody></table>
			</div>

			<!-- ░ Testo alt ░ -->
			<div class="wpaai-card">
				<h2>✏️ Composizione del testo alt</h2>
				<table class="form-table" role="presentation"><tbody>
					<tr>
						<th>Aggiungi nome del sito</th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $key ); ?>[append_site_name]" value="1" <?php checked( $o['append_site_name'], 1 ); ?> />
								Aggiungi il nome del sito come suffisso al titolo
							</label>
						</td>
					</tr>
					<tr>
						<th><label for="wpaai_sep">Separatore</label></th>
						<td>
							<input type="text" id="wpaai_sep" name="<?php echo esc_attr( $key ); ?>[separator]" value="<?php echo esc_attr( $o['separator'] ); ?>" class="regular-text" placeholder=" | " />
							<p class="description">Usato solo se "Aggiungi nome del sito" è attivo. Esempio: <code> | </code> → <em>Titolo | Nome sito</em>.</p>
						</td>
					</tr>
					<tr>
						<th>Fallback (nessun post rilevato)</th>
						<td>
							<fieldset>
								<label><input type="radio" name="<?php echo esc_attr( $key ); ?>[fallback]" value="site_title" <?php checked( $o['fallback'], 'site_title' ); ?> /> Titolo del sito</label>
								<label><input type="radio" name="<?php echo esc_attr( $key ); ?>[fallback]" value="empty" <?php checked( $o['fallback'], 'empty' ); ?> /> Alt vuoto <code>alt=""</code></label>
							</fieldset>
							<p class="description">Usato su header, footer, widget e quando il titolo non è determinabile.</p>
						</td>
					</tr>
				</tbody></table>
			</div>

			<!-- ░ Ambito ░ -->
			<div class="wpaai-card">
				<h2>🎯 Ambito di applicazione</h2>
				<table class="form-table" role="presentation"><tbody>
					<tr>
						<th>Tipi di post abilitati</th>
						<td>
							<fieldset>
								<?php foreach ( $post_types as $pt ) : ?>
									<label style="display:block;margin-bottom:4px;">
										<input type="checkbox" name="<?php echo esc_attr( $key ); ?>[post_types][]" value="<?php echo esc_attr( $pt->name ); ?>" <?php checked( in_array( $pt->name, (array) $o['post_types'], true ) ); ?> />
										<strong><?php echo esc_html( $pt->labels->singular_name ); ?></strong> <code><?php echo esc_html( $pt->name ); ?></code>
									</label>
								<?php endforeach; ?>
							</fieldset>
							<p class="description">Archivi, ricerca e homepage sono sempre inclusi a prescindere.</p>
						</td>
					</tr>
					<tr>
						<th><label for="wpaai_excl">Post/Pagine escluse (ID)</label></th>
						<td>
							<input type="text" id="wpaai_excl" name="<?php echo esc_attr( $key ); ?>[excluded_ids]" value="<?php echo esc_attr( $o['excluded_ids'] ); ?>" class="regular-text" placeholder="12, 34, 56" />
							<p class="description">ID separati da virgola. Il plugin non verrà applicato su questi contenuti.</p>
						</td>
					</tr>
				</tbody></table>
			</div>

			<!-- ░ Debug ░ -->
			<div class="wpaai-card wpaai-card--dbg">
				<h2>🐛 Debug</h2>
				<table class="form-table" role="presentation"><tbody>
					<tr>
						<th>Modalità debug</th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $key ); ?>[debug_mode]" value="1" <?php checked( $o['debug_mode'], 1 ); ?> />
								Abilita debug mode
							</label>
							<p class="description">Inietta un commento HTML e un <code>console.log</code> con la lingua rilevata e l'alt calcolato. Da disabilitare in produzione.</p>
							<?php if ( ! empty( $o['debug_mode'] ) ) : ?>
								<div class="wpaai-dbg-on">✅ Debug attivo — apri la console del browser per vedere il log <code>[WPAAI]</code>.</div>
							<?php endif; ?>
						</td>
					</tr>
				</tbody></table>
			</div>

			<!-- ░ Info multilingua ░ -->
			<div class="wpaai-card wpaai-card--info">
				<h2>🌍 Rilevamento lingua (slug-based)</h2>
				<p>Il plugin legge il primo segmento dell'URL e lo interpreta come codice lingua se corrisponde al pattern <code>/^[a-z]{2}(-[a-z]{2,4})?$/i</code>.</p>
				<ul>
					<li><code>/it/nome-articolo</code> → lingua <strong>it</strong></li>
					<li><code>/en/article-name</code> → lingua <strong>en</strong></li>
					<li><code>/pt-BR/artigo</code> → lingua <strong>pt-br</strong></li>
					<li><code>/nome-articolo</code> → nessun prefisso, lingua di default</li>
				</ul>
				<p>WordPress, grazie al proprio routing, carica già il post corretto per quello slug: <code>get_the_title()</code> restituisce automaticamente il titolo nella lingua del post richiesto — senza bisogno di WPML o Polylang.</p>
				<div class="wpaai-ibox">
					<strong>Lingua pagina corrente:</strong>
					<code><?php echo esc_html( $lang ?? 'n/a — lingua di default (nessun prefisso in URL)' ); ?></code>
				</div>
			</div>

			<?php submit_button( 'Salva impostazioni' ); ?>
		</form>
	</div>
	<?php
}
