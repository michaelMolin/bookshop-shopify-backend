# CLAUDE.md — Bookshop Shopify Backend

Contesto e convenzioni per lavorare su questo repository. Leggere prima di proporre modifiche.

## Cos'è questo progetto

Backend **API-only** in Laravel per una libreria indipendente headless. Fa da layer intermedio tra Shopify (piattaforma e-commerce) e un frontend Next.js separato.

**Architettura complessiva (tre livelli):**

```
Shopify  →  Laravel (questo repo, API-only)  →  Next.js (repo separato)
```

- **Shopify** è la fonte di verità per catalogo e transazioni.
- **Laravel** (qui) gestisce sync, webhook, cache locale, e espone API REST proprie.
- **Next.js** (repo separato) consuma SOLO le API di questo backend, non tocca mai Shopify direttamente.

Questo repo NON ha frontend. Nessuna view Blade di prodotto, nessun asset. Solo API, logica, integrazione Shopify.

## Stack

- PHP 8.4, Laravel (API-only)
- MySQL
- Libreria ufficiale `shopify/shopify-api` (client GraphQL Admin + Storefront)
- Queue su driver `database`
- Dev: Docker (casa) / Laragon (ufficio, host `bookshop-shopify-backend.test`)
- ngrok per esporre i webhook in sviluppo

## Decisioni architetturali chiave (NON reinterpretare)

Queste scelte sono state prese deliberatamente. Non proporre di cambiarle senza che venga chiesto.

1. **Cache locale dei prodotti.** I prodotti Shopify vengono sincronizzati in una tabella `products` locale. Il frontend legge da qui, non da Shopify. Motivo: performance, rate limit della Storefront API, resilienza. NON suggerire di chiamare Shopify a ogni request del frontend.

2. **Sync idempotente.** Tutte le operazioni di sync usano `updateOrCreate` / `firstOrCreate`. Rilanciare un sync non deve mai creare duplicati.

3. **Resilienza per-record.** Il sync non deve fallire in blocco se un singolo prodotto ha dati sporchi: cattura l'errore sul singolo record, incrementa un contatore, continua.

4. **Architettura a layer.** Controller → FormRequest → Service → Repository → Model. La logica di business sta nei Service, non nei Controller (no fat controller). I Job e i Comandi sono trigger magri che chiamano i Service. Repository dove la logica di lettura è complessa/riusabile; per scritture semplici il Service va diretto sul Model (evitare over-engineering).

5. **Webhook con endpoint unico + Registry.** Tutti i topic Shopify arrivano a un solo endpoint (`/api/webhooks/shopify`). Lo smistamento per topic è gestito da `Shopify\Webhooks\Registry::process`, con handler registrati al boot nel `ShopifyServiceProvider` via `addHandler`. La verifica HMAC è delegata alla libreria (non riscritta a mano).

6. **Processamento webhook asincrono.** Il controller webhook verifica, logga, dispatcha un Job e risponde 200 subito (regola dei 5 secondi di Shopify). Il lavoro pesante gira in coda.

7. **Idempotenza webhook.** Ogni webhook logga `shopify_webhook_id`. Prima di processare si controlla se già gestito, per via dei retry di Shopify.

8. **Filosofia generale: non reinventare la ruota.** Preferire pacchetti ufficiali e strumenti maturi a implementazioni custom. Le scelte custom si giustificano solo dove aggiungono valore reale.

## Schema dati

- `products` — cache locale Shopify. Campi strutturati (query/filtri): `slug`, `publisher`, `isbn`, `price`, `inventory_quantity`, `status`. Campi JSON: `tags` (filtri frontend), `data` (dati passivi tipo descrizione). `shopify_id` unique = chiave di match per il sync.
- `categories` — con dati editoriali per pagine categoria (`name`, `slug`, `description`, `cover_image`, `sort_order`, `is_featured`).
- `category_product` — pivot many-to-many (un libro può stare in più categorie).
- `sync_logs` — tracking delle sincronizzazioni (durata, processati, falliti, esito).
- `webhook_logs` — log dei webhook ricevuti (topic, payload, status, `shopify_webhook_id` per idempotenza).
- Pianificate: `articles`, `events`, `newsletter_subscribers`.

## Convenzioni di codice

- Mass assignment: si usa `$guarded = []` (non `$fillable`). Mantenere coerenza tra i Model.
- Cast espliciti nei Model per JSON (`array`) e date (`datetime`).
- `getRouteKeyName()` ritorna `slug` per i model esposti via URL (no id numerici negli URL).
- Accessor con la sintassi moderna `Attribute` (non i vecchi `getXxxAttribute`).
- Le env si leggono SOLO via `config()`, mai `env()` diretto nel codice applicativo.
- Nomi comandi artisan con prefisso di dominio: `shopify:...`.

## Comandi principali

- `php artisan shopify:sync` — dispatcha il Job di sincronizzazione prodotti da Shopify.
- `php artisan shopify:register-webhooks` — registra i topic webhook su Shopify (products create/update/delete, inventory). Da rilanciare quando cambia l'URL ngrok in dev.
- `php artisan queue:work` — worker della coda (necessario per Job e webhook).
- `php artisan migrate` — migrazioni.
- (Pianificato) `php artisan shopify:setup` — comando orchestratore che concatena migrate + sync + register-webhooks.

## Integrazione Shopify — note operative

- Due tipi di token: **Storefront** (lato lettura catalogo/carrello) e **Admin** (`shpat_...`, per sync + webhook). Sono distinti, generati da posti diversi (Headless channel vs custom app admin).
- `Context::initialize()` va nel `ShopifyServiceProvider` (`isPrivateApp: true`, `isEmbeddedApp: false`). Senza, il client HTTP della libreria è null.
- `hostName` del Context = l'host della NOSTRA app (URL ngrok in dev), NON il dominio del negozio. Il dominio negozio (`*.myshopify.com`) si passa al client GraphQL e alla registrazione webhook.
- API secret serve per la verifica HMAC dei webhook.
- Le route webhook stanno in `routes/api.php` (già escluse da CSRF).

## Frontend (repo separato) — implicazioni per il backend

Il frontend sarà in **Next.js** (repo Git separato). Questo comporta scelte che riguardano questo backend:

- Le API di questo repo devono essere **stateless e JSON**, pensate per essere consumate da Next.js (SSR/SSG lato loro).
- CORS va configurato per accettare l'origine del frontend Next.js.
- L'autenticazione (dove servirà, es. area admin) sarà via token/API, non via sessione web classica.
- I filtri catalogo (autore, genere, disponibilità) sono gestiti **lato frontend** su un set di prodotti già caricato: il backend espone il catalogo, il filtraggio fine non richiede un endpoint per ogni combinazione.
- Le API vanno documentate mentre si costruiscono (Postman/README): il frontend è un consumatore separato e ha bisogno del contratto.
- La dashboard admin (analytics, sync monitor, webhook log) sarà anch'essa in Next.js e consumerà endpoint dedicati di questo backend.

## Cosa NON fare

- NON aggiungere un frontend/view a questo repo (è API-only).
- NON bypassare la cache locale chiamando Shopify a ogni request.
- NON mettere logica di business nei Controller.
- NON riscrivere la verifica HMAC a mano (usare la libreria).
- NON introdurre Filament o pannelli admin Laravel: l'admin è nel frontend Next.js.
- NON trasformare tutto in JSON `data`: solo i campi passivi non filtrabili. I campi su cui si fanno query restano colonne dedicate.
