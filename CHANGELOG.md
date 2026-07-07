# Changelog

Tutte le modifiche notevoli a questo progetto sono documentate in questo file.

Il formato segue [Keep a Changelog](https://keepachangelog.com/it/1.0.0/)
e il progetto adotta il [Versionamento Semantico](https://semver.org/lang/it/).

---

## [1.1.0] — 2026-07-07

### ✨ Aggiunto

- **Alt automatico per ogni immagine**: nuova opzione "Sorgente del testo alt" che permette di generare un alt unico per ciascuna immagine, derivato in automatico dal nome del file (es. `tramonto-sul-mare-1024x683.jpg` → *Tramonto sul mare*). Suffissi WordPress (`-300x200`, `-scaled`, `-rotated`, `-eXXXXXXXX`) rimossi automaticamente; nomi non descrittivi (es. `IMG_1234`, `DSC0001`, screenshot) usano il titolo della pagina come ripiego.
- Nuovo filtro `wpaai_alt_from_filename` per personalizzare l'alt derivato dal nome file.
- Il suffisso "nome del sito" viene applicato anche in modalità per-immagine.

### 🛠 Corretto

- La regex di sostituzione dell'alt ora gestisce correttamente valori contenenti l'altro tipo di apice (es. `alt="John's dog"`), prima potevano corrompere il tag.
- Le immagini decorative (`role="presentation"` o `aria-hidden="true"`) non vengono più toccate, come richiesto dalle linee guida di accessibilità.
- Rimosso dal codice il valore `filename` non implementato tra i fallback documentati.

---

## [1.0.0] — 2025-01-01

### ✨ Aggiunto

- **Core**: intercettazione dell'output HTML tramite `ob_start` su `template_redirect`. Nessuna modifica al database.
- **Alt dal titolo del post**: recupero automatico tramite `get_the_title()` sulla pagina corrente.
- **Gestione pagine speciali**: titolo del sito per homepage, `get_the_archive_title()` per archivi, query string per pagine di ricerca.
- **Modalità di sostituzione**:
  - *Solo alt vuoti o mancanti* — sostituisce `alt=""` e tag `<img>` senza attributo `alt`.
  - *Tutti gli alt* — sovrascrive qualsiasi valore `alt` già presente.
- **Fallback configurabile**: titolo del sito oppure `alt=""` vuoto.
- **Suffisso nome sito**: separatore personalizzabile (es. `" | "` → `"Titolo | Nome sito"`).
- **Filtro per post type**: selezione dei tipi di post su cui applicare la sostituzione.
- **Esclusione per ID**: campo per elencare ID da escludere, separati da virgola.
- **Debug mode**: commento HTML + `console.log` con lingua rilevata e alt calcolato. Da disabilitare in produzione.
- **Rilevamento lingua slug-based**: analisi del primo segmento dell'URL con pattern `/^[a-z]{2}(-[a-z]{2,4})?$/i` (es. `/en/`, `/it/`, `/pt-BR/`). WordPress carica già il post corretto tramite il proprio routing — `get_the_title()` restituisce il titolo nella lingua giusta senza dipendenze da WPML o Polylang.
- **Pannello impostazioni avanzato** (`Impostazioni → Auto Alt Image`) con toggle CSS, card visive, info box con lingua rilevata in tempo reale. CSS iniettato inline via `admin_head`, zero asset esterni.
- **Filtro `wpaai_alt_text`**: hook per sviluppatori per personalizzare programmaticamente il testo alt finale.
- **Singolo file PHP**: nessuna directory `includes/`, nessun file CSS esterno, zero dipendenze.
- **Sicurezza**: escape di tutti gli output con `esc_attr()`, `esc_html()`, `esc_js()`; sanitizzazione completa dell'input; `current_user_can('manage_options')`; nonce via Settings API.
- **Performance**: buffer non avviato su feed RSS, richieste AJAX, REST API e anteprime post.
- **GitHub Actions**: workflow `.github/workflows/deploy.yml` per deploy automatico su WordPress.org SVN al publish di una Release GitHub.

---

## Tipi di cambiamento

| Tipo | Descrizione |
|---|---|
| Aggiunto | Nuove funzionalità |
| Modificato | Cambiamenti a funzionalità esistenti |
| Rimosso | Funzionalità rimosse |
| Corretto | Bug fix |
| Sicurezza | Fix di vulnerabilità |
| Performance | Miglioramenti di prestazioni |
| Refactoring | Riscrittura senza cambiamenti funzionali |

---

[Unreleased]: https://github.com/currentjot/wp-auto-alt-image/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/currentjot/wp-auto-alt-image/releases/tag/v1.0.0
