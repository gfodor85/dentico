# Dentico – átadás a Codex fejlesztőkörnyezetnek

Frissítve: 2026-10-20

## 1. A projekt célja

A Dentico egy fogászati rendelő PHP 8.0+ / MySQL alapú webalkalmazása. Meglévő nyilvános weboldalból fejlődik foglalási rendszerré. A cél a meglévő működés biztonságos, fokozatos továbbfejlesztése, nem teljes technológiaváltás.

## 2. Repo és branchállapot

- GitHub: https://github.com/gfodor85/dentico
- `master`: alapértelmezett ág; **nem** ez a jelenlegi PHP-fejlesztés legfrissebb alapja.
- `feature/php-pdo-foundation`: az új PDO- és foglalási fejlesztés fő munkaváltozata.
- PR #3 (`feature/session-security` → `feature/php-pdo-foundation`): 2026-10-20-án beolvasztva; HttpOnly, SameSite=Lax, HTTPS esetén Secure PHP session cookie paraméterek az `admin.php` és `booking.php` fájlokban.
- `feature/admin-logout-security`: a fentire épülő, még külön álló admin-kijelentkezési javítás (régi session adatainak törlése, session cookie lejáratása, szerveroldali session megszüntetése, átirányítás). Nem kezelendő úgy, mintha már be lenne olvasztva.
- `feature/codex-handoff`: a Codexnek szánt átadási dokumentumok ága; a `feature/admin-logout-security` ágból készült, tehát a fenti logout-javítást is tartalmazza.

**Mielőtt új fejlesztés indul:** ellenőrizd a branchállapotot és a célágat. Ne készíts véletlenül PR-t `master` ellen; a munkaágak integrálásáról a tulajdonos dönt.

## 3. Helyi fejlesztési környezet

- Operációs rendszer: Windows; webkiszolgáló/adatbázis: XAMPP (Apache és MySQL/MariaDB).
- Tipikus helyi mappa: `D:\WEB_DEV\xampp\htdocs\Dentico`.
- Böngészős cím: `http://localhost/Dentico/`; phpMyAdmin: `http://localhost/phpmyadmin`.
- DB: `dentico`, `utf8mb4_unicode_ci`.
- Sémaleírás és kezdeti tesztadatok: `database/schema.sql`.
- A `config/database.example.php`, `config/admin.example.php` és `config/mail.example.php` mintákból készülnek a gitignore-olt helyi konfigurációk. Ne olvasd be vagy másold fel a valós jelszavakat, SMTP-adatokat és betegadatokat.
- Composer: `composer.json`, PHPMailer `^6.10`; `composer install` után a gyökérszintű `vendor/` könyvtár lokális. A régebbi kapcsolatfelvételi funkcióhoz külön, verziókezelt vendorkód is létezik az `assets/php/contact-form/vendor/` alatt; ezt ne módosítsd mellékesen.

## 4. Jelenleg megvalósított fő funkciók

- Nyilvános weboldal és kapcsolatfelvételi funkció (`index.php`, `services.php`, `partials/`, `assets/`).
- Szolgáltatás- és időpontválasztás; 15 perces időközök; hétfő–péntek 08:00–17:00 alapnyitvatartás (`booking.php`, `available-slots.php`).
- PDO-alapú adatbázis (`app/database.php`), `services` és `appointment_requests` táblák (`database/schema.sql`).
- Beérkező kérések `pending` állapotban; az adminfelület képes jóváhagyni és lemondani, ütköző jóváhagyott foglalások ellenőrzésével (`admin.php`).
- Adminjelszó-hash és PHP session-alapú beléptetés, CSRF-védelem, session ID-regenerálás belépéskor; legutóbb javított kijelentkezés.
- Foglalási és státuszváltozási e-mail-értesítések PHPMailerrel (`app/mailer.php`), a levélküldés hibája önmagában nem törli a sikeresen mentett foglalást.

## 5. Amit már manuálisan ellenőriztünk

- A tulajdonos visszajelzése szerint a booking és admin oldal az új session-beállításokkal helyben működött.
- Chrome DevTools alatt a PHPSESSID cookie `HttpOnly=true` és `SameSite=Lax` volt; helyi HTTP mellett a `Secure` nem volt bekapcsolva (várható működés).
- A kijelentkezés után az adminfelület helyett a bejelentkező képernyő jelent meg; új, nem hitelesített PHPSESSID létrejötte az új munkamenet/CSRF miatt normális.
- **Nem igazolt:** automatizált tesztek, teljes biztonsági audit, terhelés alatti foglalásütközések és éles üzemre alkalmasság. Ezeket külön meg kell vizsgálni.

## 6. Első Codex-feladat – csak olvasási audit

Vizsgáld át a projekt saját alkalmazáskódját, a helyi konfigurációs mintákat, a sémát és a dokumentációt. Az első körben **ne módosíts fájlokat és ne nyiss PR-t**.

Az audit térjen ki a következőkre:

1. Adminhitelesítés, jogosultság, kijelentkezés, session/cookie és CSRF.
2. SQL injection, XSS, bemenetellenőrzés, hibakezelés és adatvédelmi kockázatok.
3. Foglalási versenyhelyzetek, tranzakciók és az azonos időpontot érintő párhuzamos műveletek.
4. Az `available-slots.php`, `booking.php` és `admin.php` eltérő szabályainak lehetséges inkonzisztenciái (dátum/időzóna, rendelési idő, státuszkezelés).
5. PHPMailer, Composer, régi verziókezelt harmadik féltől származó függőségek és karbantarthatóság.
6. Tesztelhetőség és javasolt automatikus tesztek; különítsd el, mit tudsz helyben futtatni, és mit kell XAMPP+MySQL alatt manuálisan ellenőrizni.

Az eredményben minden lényeges megállapításhoz adj súlyosságot, pontos fájl-/kódhelyet, rövid indoklást, konkrét javítási javaslatot és teszttervet. Ne nevezz feltételezett problémát igazolt sérülékenységnek bizonyíték nélkül.

## 7. Biztonsági javítási kör (nyitott PR #5)

A `feature/codex-security-hardening` ág a `feature/codex-handoff` ágra készített, még be nem olvasztott javításokat tartalmaz:

- Admin inaktivitási lejárat (30 perc), PHP strict session mód és egységes `Europe/Budapest` időzóna.
- Öt adminbelépési, foglalási vagy kontaktűrlap-kérés engedélyezett kliens-IP-címenként 15 perc alatt; CSRF-ellenőrzés a kontaktűrlapon.
- Közös root Composer PHPMailer használata a kontaktűrlapon, szerveroldali bemenetellenőrzés és HTML-escaping; mailerhiba nem fordítja vissza a már elmentett foglalást vagy státuszt.
- Adminjóváhagyások dátumonkénti InnoDB-zárolása, tiltott státuszátmenetek és múltbeli foglalás jóváhagyásának elutasítása.
- A foglaláskori szolgáltatáshossz rögzítése, valamint adminlista lapozása és külön heti naptárlekérdezés.
- Meglévő adatbázisnál egyszer alkalmazandó: `database/migrations/20261020_security_hardening.sql`. Új adatbázisnál a frissített `database/schema.sql` használható. Az alkalmazás nem futtat automatikus migrációt.

A módosított PHP-fájlok PHP 8.2.12 lintje sikeres. MySQL-konkurenciateszt, böngészős CSRF/XSS-próba és SMTP-integráció nincs igazolva; ezeket XAMPP-ban kell elvégezni a migráció alkalmazása után.

## 8. Ajánlott következő szakaszok

- Audit-jelentés közös áttekintése és prioritások kijelölése.
- Külön feature branchekben, kis PR-eken keresztül a valódi hibák javítása.
- PHP-lint, célzott automatikus tesztek és XAMPP/MySQL integrációs ellenőrzések bevezetése.
- Később fokozatos UX-, admin- és foglalási fejlesztések; a már működő PHP-rendszer megtartásával.

## 9. Fejlesztési szabály

A repo gyökerében található `AGENTS.md` tartalmazza a Codex számára szóló általános projektutasításokat. Változtatás, adatbázis-migráció, külső levélküldés, éles telepítés vagy merge előtt a tulajdonos jóváhagyása szükséges.
