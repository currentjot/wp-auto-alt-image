# WP Auto Alt Image

[![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://github.com/currentjot/wp-auto-alt-image/releases)
[![License](https://img.shields.io/badge/license-GPL--2.0%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-8892be.svg)](https://php.net)
[![Deploy](https://github.com/currentjot/wp-auto-alt-image/actions/workflows/deploy.yml/badge.svg)](https://github.com/currentjot/wp-auto-alt-image/actions/workflows/deploy.yml)

Sostituisce automaticamente gli attributi `alt` delle immagini sul frontend usando il titolo del post o della pagina corrente. **Zero modifiche al database.** Rilevamento lingua via slug.

---

## Come funziona

Il plugin si aggancia all'hook `template_redirect` e avvia un output buffer (`ob_start`). Quando WordPress termina di generare la pagina, il plugin intercetta l'HTML completo, applica una sostituzione regex su tutti i tag `<img>` e restituisce l'HTML modificato al browser.

Il database rimane intatto — disattivare il plugin riporta immediatamente tutto allo stato originale.

## Funzionalità

| Funzionalità | Dettaglio |
|---|---|
| Zero modifiche al DB | Tutto runtime via output buffer, reversibile in un click |
| Alt dal titolo del post | `get_the_title()` sulla pagina corrente |
| Rilevamento lingua via slug | Pattern `/{LANG}/slug` (es. `/en/`, `/it/`, `/pt-BR/`) |
| Fallback configurabile | Titolo del sito oppure `alt=""` vuoto |
| Suffisso personalizzabile | Es. `"Titolo articolo \| Nome sito"` |
| Modalità di sostituzione | Solo alt vuoti oppure tutti |
| Filtro per post type | Seleziona su quali tipi di contenuto applicarlo |
| Esclusione per ID | Escludi specifici post/pagine per ID |
| Debug mode | Commento HTML + `console.log` con lingua e alt calcolato |
| Hook per sviluppatori | Filtro `wpaai_alt_text` per personalizzare il testo alt |
| Singolo file | Zero dipendenze, zero asset esterni |

## Rilevamento lingua (slug-based)

Il plugin analizza il primo segmento dell'URL cercando un prefisso lingua nel formato `/^[a-z]{2}(-[a-z]{2,4})?$/i`:

```
/it/nome-articolo   → lingua: it
/en/article-name    → lingua: en
/pt-BR/artigo       → lingua: pt-br
/nome-articolo      → nessun prefisso, lingua di default
```

WordPress, grazie al proprio routing, carica già il post corretto per quello slug: `get_the_title()` restituisce automaticamente il titolo nella lingua del post richiesto, senza bisogno di WPML o Polylang.

## Installazione

**Manuale:**

1. Scarica il file `wp-auto-alt-image.php`
2. Caricalo in `/wp-content/plugins/wp-auto-alt-image/`
3. Attiva il plugin dal menu **Plugin** di WordPress
4. Vai su **Impostazioni → Auto Alt Image** per configurarlo

**Da WordPress.org:**

1. **Plugin → Aggiungi nuovo**
2. Cerca `WP Auto Alt Image`
3. **Installa ora** → **Attiva**

## Configurazione

Tutte le opzioni si trovano in **Impostazioni → Auto Alt Image**:

### Stato
- **Abilita plugin** — toggle on/off senza perdere le impostazioni

### Modalità di sostituzione
- **Solo alt vuoti o mancanti** — salta le immagini con un alt già valorizzato
- **Tutti gli alt** — sovrascrive qualsiasi alt esistente con il titolo del post

### Composizione del testo alt
- **Aggiungi nome del sito** — appende il nome del sito al titolo
- **Separatore** — il carattere/stringa tra titolo e nome sito (es. ` | `)
- **Fallback** — cosa usare quando il titolo non è determinabile (titolo del sito o `alt=""`)

### Ambito di applicazione
- **Tipi di post** — seleziona i post type su cui applicare la sostituzione
- **ID esclusi** — lista di ID separati da virgola da ignorare

### Debug mode
Inietta nella pagina un commento HTML e un `console.log` con lingua rilevata e alt calcolato. Da disabilitare in produzione.

## Hook per sviluppatori

```php
add_filter( 'wpaai_alt_text', function( $alt, $post_id, $options ) {
    // Personalizza il testo alt come vuoi.
    return $alt;
}, 10, 3 );
```

## FAQ

**Il plugin modifica il database?**
No. Tutto avviene in memoria tramite output buffering. Disattivarlo riporta tutto allo stato originale.

**Funziona con Elementor, Divi, Beaver Builder?**
Sì. Il plugin intercetta l'HTML finale a prescindere da come è stato generato.

**Funziona con WPML o Polylang?**
Parzialmente. Il plugin non usa le API di questi plugin, ma sfrutta il routing nativo di WordPress: se il plugin multilingua genera URL del tipo `/{LANG}/slug`, WordPress carica già il post corretto e `get_the_title()` restituisce il titolo tradotto. Per integrazioni più profonde usa il filtro `wpaai_alt_text`.

**Funziona con immagini lazy-loaded via JS?**
No. Il plugin agisce sull'HTML server-side — le immagini iniettate da JavaScript dopo il caricamento della pagina non vengono intercettate.

**Come trovo l'ID di un post/pagina?**
Vai su Pagine (o Articoli) → Modifica e guarda l'URL: il numero dopo `post=` è l'ID.

## Requisiti

- WordPress 6.0+
- PHP 7.4+

## Licenza

[GPL-2.0+](https://www.gnu.org/licenses/gpl-2.0.html)

## Changelog

Vedi [CHANGELOG.md](CHANGELOG.md)
