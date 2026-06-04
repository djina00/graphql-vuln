# GQL Blog

Mala blog aplikacija sa GraphQL endpoint-om. 
Backend: PHP(webonyx/graphql-php), 
Frontend: vanilla JS, Bootstrap.

## Grane

- **`vuln`** — grana sa ranjivostima
- **`fixed`** — grana sa popravljenim ranjivostima

git checkout vuln    # reprodukcija exploit-a
git checkout fixed   # pregled ispravki

## Zahtevi

- PHP 8.1+
- Composer
- Docker Desktop

## Podešavanje

1. Kopirajte šablon konfiguracije:
   ```
   cp backend/config.example.php backend/config.php
   ```
   Izmenite `backend/config.php` ako se vaši lokalni DB kredencijali razlikuju od podrazumevanih. (u projektu je dat primer u config.example.php)

2. Instalirajte backend zavisnosti:
   ```
   cd backend
   composer install
   cd ..
   ```

3. Pokrenite MySQL. Prvo pokretanje automatski uvozi `backend/sql/schema.sql` i `seed.sql`:
   ```
   docker compose up -d
   ```

## Pokretanje

Otvorite tri terminala:

- **MySQL** (već pokrenut nakon `docker compose up -d`)
- **Backend** na portu 8089:
  ```
  cd backend
  php -S localhost:8089 -t public
  ```
- **Frontend** na portu 8090:
  ```
  cd frontend
  php -S localhost:8090
  ```

Zatim otvorite `http://localhost:8090/login.html`.

## Test korisnici

| Email             | Lozinka    |
| alice@blog.com    | alice123   |
| bob@blog.com      | bob123     |
| carol@blog.com    | carol123   |

## Resetovanje baze podataka

```
docker compose down -v
docker compose up -d
```

## Ranjivosti i ispravke

Svi exploit-i ispod se šalju na `POST http://localhost:8089/graphql` sa `Content-Type: application/json`. Na grani `vuln` uspevaju; na grani `fixed` bivaju odbijeni.

### 1. IDOR — `user(id)` otkriva email, draft-ove, poruke

- **Gde:** `backend/src/GraphQL/Schema.php`, polja `User` tipa (`email`, `drafts`, `messages`).
- **Greška:** resolveri vraćaju podatke bez provere da pozivalac poseduje korisnički zapis.
- **Exploit (prijavljen kao bilo ko, ili kao Alice):**
  ```graphql
  { user(id: 2) { email drafts { title body } messages { body } } }
  ```
  Vraća Bob-ov email, njegove draft-ove i svaku DM poruku u kojoj učestvuje.
- **Ispravka:** svako polje poredi `$context['userId']` sa traženim `$user['id']` i vraća `null` / praznu listu za korisnike koji nisu vlasnici.

### 2. SQL injekcija — `searchPosts(keyword)`

- **Gde:** `backend/src/GraphQL/Schema.php`, `searchPosts` resolver.
- **Greška:** keyword se nadovezuje u `LIKE '%...%'` i izvršava preko `PDO::query`.
- **Exploit:**
  ```graphql
  { searchPosts(keyword: "%') UNION SELECT 1, 1, email, password_hash, 'published', NOW() FROM users #") { title body } }
  ```
  Izvlači tabelu `users` — email-ovi u `title`, bcrypt hešovi u `body`.
- **Ispravka:** pripremljeni upit (prepared statement) sa `?` placeholder-ima; `'%' . $keyword . '%'` se vezuje (bind), a ne interpolira.

### 3. CSRF na mutacijama

- **Gde:** `backend/public/index.php`, `/graphql` handler.
- **Greška:** prihvatao je `application/x-www-form-urlencoded` tela zahteva. Cross-origin HTML forma je mogla da pošalje mutaciju koristeći žrtvin session cookie.
- **Exploit:** napadač hostuje stranicu na bilo kom drugom origin-u (ili `file://`):
  ```html
  <form action="http://localhost:8089/graphql" method="POST">
    <input name="query" value='mutation { createPost(title: "Pwned", body: "x") { id } }'>
    <button>Click me</button>
  </form>
  ```
  Žrtva poseti stranicu dok je prijavljena, klikne, i post se kreira kao ona.
- **Ispravka:** `/graphql` prihvata samo `application/json` i zahteva `X-CSRF-Token` header po sesiji. Token se izdaje pri prijavi i izlaže preko `/auth/me`. Prilagođeni header-i prisiljavaju CORS preflight, koji napadačeva stranica ne može da prođe.

### 4. Grupisanje upita (batching)

- **Gde:** `backend/public/index.php`, `/graphql` handler.
- **Greška:** JSON niz kao telo zahteva izvršavao je svaku operaciju u jednom HTTP zahtevu, zaobilazeći bilo kakvo rate ograničenje po zahtevu.
- **Exploit:**
  ```json
  [{ "query": "{ posts { id } }" }, { "query": "{ user(id: 1) { email } }" }]
  ```
  Dve operacije, jedan zahtev.
- **Ispravka:** odbijanje niza kao tela zahteva sa HTTP 400 — jedna operacija po zahtevu.

### 5. Preopterećenje alias-ima (alias overload)

- **Greška:** isto skupo polje moglo je da se traži stotine puta u jednom upitu preko alias-a (`a1: ... a2: ... ... a200: ...`), pojačavajući svaki trošak po pozivu.
- **Exploit:** `{ a1: posts { id } a2: posts { id } ... a200: posts { id } }`.
- **Ispravka:** prilagođeno `MaxAliasesRule` (`backend/src/GraphQL/MaxAliasesRule.php`) ograničeno na 15 i registrovano preko `DocumentValidator::addRule`.

### 6. Neograničena dubina i složenost upita

- **Greška:** napadač je mogao da rekurzira duboko (`post → comments → author → posts → comments → ...`) ili da traži mnoštvo polja.
- **Exploit:**
  ```graphql
  { post(id:1) { comments { author { posts { comments { author { posts { id } } } } } } } }
  ```
- **Ispravka:** pravila `QueryDepth(7)` i `QueryComplexity(150)` dodata u `index.php`.

### 7. Curenje informacija — introspekcija, trace-ovi i predlozi polja

- **Gde:** `backend/public/index.php`, `executeQuery` debug + podrazumevani formatter grešaka.
- **Greška:** introspekcija je bila uključena (`__schema` je radio), debug flag-ovi su otkrivali PHP stack trace-ove pri greškama, a greške u kucanju polja su vraćale `Did you mean ...?` savete.
- **Exploit:**
  ```graphql
  { __schema { types { name fields { name } } } }
  ```
  Vraća celu šemu. Pogrešno otkucajte ime polja da vidite predloge.
- **Ispravka:** registrovano `DisableIntrospection` pravilo; `DebugFlag::NONE` za `toArray`; prilagođeni `errorFormatter` vraća samo `{ message }` i uklanja `Did you mean ...?` iz poruka.
