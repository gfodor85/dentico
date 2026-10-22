# Dentico – aktuális átadás a Codexnek

Frissítve: 2026-10-22. Ez a dokumentum felülírja a korábbi, 2026-10-20-i Codex-átadási tervet.

## 1. Projektállapot és Git

- Repo: https://github.com/gfodor85/dentico
- **`master`**: a teljes, beolvasztott PHP/MySQL foglalási és biztonsági fejlesztést tartalmazza a 2026. október 21-én sikeresen beolvasztott [PR #12](https://github.com/gfodor85/dentico/pull/12) után. A korábbi PR #4 és #5 lezárt; ne próbáld őket újra beolvasztani.
- **`feature/ui-refresh-2026`**: a jelenlegi aktív vizuális fejlesztési ág, [draft PR #13](https://github.com/gfodor85/dentico/pull/13) a `master` ellen. **A következő UI-változtatásokat ezen az ágon végezd**. Merge csak felhasználói jóváhagyás után.
- A felhasználó a Codex Windows-alkalmazásban folytatja a fejlesztést. A cél, hogy a Codex közvetlenül szerkessze, ellenőrizze és frissítse a helyi projektet; ne kérjen folyamatos PowerShell-kódmásolást.

## 2. Windows/XAMPP mappák

- Git munkamappa: `D:\WEB_DEV\Dentico-GitHub`.
- **Védendő eredeti, működő XAMPP-projekt**: `D:\WEB_DEV\xampp\htdocs\Dentico`.
- **Vizuális előnézet**: `D:\WEB_DEV\xampp\htdocs\Dentico-Preview`.
- Böngészős URL: `http://localhost/Dentico-Preview/`.
- A preview a helyi `dentico` MySQL-adatbázist használhatja. Vizuális teszt közben ne módosíts élő/valós adatokat és ne küldj valódi leveleket.
- A helyi `config/database.php`, `config/admin.php`, `config/mail.php`, `config/maps.php`, valamint a `vendor/` privát/lokális. Ne publikáld, ne módosítsd indokolatlanul és ne másold be chatbe az értéküket.

## 3. Jelenlegi UI-irány: eredeti Dentico + modern használhatóság

A felhasználó kimondottan az **eredeti** Dentico-arculatot kedveli, nem a generikus fehér-kék rendelői sablont. Megtartandó:

- A türkiz `#00bbae` és Quicksand betűtípus.
- Az eredeti SVG-embléma (`assets/img/svg/symbols.svg#hero-img`), „Dentico” cím és **Your Smile. Our Passion.** szlogen.
- A nagy, eredeti fotós hero (`assets/img/bg_2_blurred.jpg`), türkizes overlay és áttetsző panelhangulat.
- A hero **desktopon fixed háttérrel** rendelkezzen, mobilon normál scrollos háttérrel.
- A „Welcome to” felirat **nem kell** a hero részben.
- Az „Explore Care” három kártyájában az eredeti kezelési illusztrációk láthatók; a **kártyák felső kb. 50%-át a képek** foglalják el.
- A modernizált reszponzív foglalási űrlap, szolgáltatáskártyák és adminnaptár maradjon használható és olvasható.

Fájlok: `index.php`, `assets/css/dentico-ui.css`, `partials/ui-header.php`, `partials/form.php`, `services.php`, `booking.php`, `admin.php`. A korábbi minimalizált `assets/css/main.css` más örökölt oldalak számára még megvan; ne módosítsd ok nélkül.

## 4. Működés és biztonság, amit meg kell őrizni

- PHP 8+, PDO/MySQL, Bootstrap 4 néhány alkalmazási oldalon, PHPMailer/Composer.
- Foglalás: `booking.php`, `available-slots.php`; 15 perces kezdési lépések, hétfő–péntek 08:00–17:00; új kérelem `pending`; elérhetőség a szolgáltatáshossztól és jóváhagyott foglalásoktól függ.
- Admin: `admin.php`; hitelesített, CSRF-védett jóváhagyás/törlés; ütköző jóváhagyások ellen InnoDB napi zárolás `appointment_day_locks`; jelszó és session-cookie biztonsági korlátok.
- Ne változtasd meg a POST mezőneveket, hitelesítést, CSRF-et, prepared statementeket, foglalási szabályokat vagy tranzakciókat pusztán a design miatt.
- A `database/migrations/20261020_security_hardening.sql` **korábban már lefutott a helyi adatbázison**. Ne futtasd újra. Új telepítésnél az aktuális `database/schema.sql` az alap.
- Korábban sikeres helyi tesztek: foglalási és adminállapot-műveletek, session/logout, CSRF, rate limit, elérhető időpontok és párhuzamos, ütköző jóváhagyási teszt. A mostani UI-branch **még nem kapott teljes helyi lint + böngészős smoke testet**; ne állítsd, hogy igen.

## 5. Munkafolyamat a Codexnek

1. Nyisd meg helyben a `D:\WEB_DEV\Dentico-GitHub` mappát, ellenőrizd a `feature/ui-refresh-2026` branchet és a tiszta munkamappát. A felhasználó megadott aktuális vizuális kéréseit ezen a branchen végezd.
2. Módosítsd közvetlenül a fájlokat, és ahol van, futtasd a `php -l` és célzott ellenőrzéseket. Új SQL-migrációra nincs szükség UI-feladathoz.
3. Az előnézethez használd a repo `tools/Refresh-Dentico-Preview.cmd` és `tools/refresh-preview.ps1` segédprogramjait, vagy kontrollált, ekvivalens lokális szinkront a Git working tree-ből. A segédprogram clean worktree-t és jó branchet igényel, `git pull --ff-only`-t végez, nem tükröz törléssel, nem másol `config/`, `vendor/`, `.git/` könyvtárakat.
4. Az eredeti `D:\WEB_DEV\xampp\htdocs\Dentico` mappát ne írd felül.
5. Új commitot/push-t csak a fejlesztési ágra készíts; PR #13 draft maradjon a helyi vizuális/üzemi tesztek végéig. `master` merge vagy élesítés csak tulajdonosi jóváhagyással.
6. A felhasználónak mindig röviden jelezd: mi változott, mely fájlokban, mit teszteltél ténylegesen, és hol lehet megnézni az eredményt. A felhasználó lehetőleg ne kapjon bemásolandó parancsblokkokat, ha az alkalmazás futtathatja őket.

További részletes szabályok: `AGENTS.md` és `README.md`.
