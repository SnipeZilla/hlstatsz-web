# 📈 HLstatsZ Web — Next-gen stats frontend for GoldSrc, Source and CS2

Player and clan rankings, awards, live servers and bans, read from the database of the [HLstatsZ daemon](https://github.com/SnipeZilla/HLSTATS-2).

Modern yet familiar: a clean, responsive, AJAX-first layout that keeps the original HLstats feel.

---

## Features

**Statistics**
- **True Rank** system
- Rankings: global, yesterday, weekends, last 7 days and last *MinActivity* days
- Players, clans, weapons, maps, actions, awards, ranks and ribbons
- Player profiles: Steam profile, charts, hitboxes (superlogs), forum signature (phpBB, XenForo, Invision, Discord)
- Charts with Chart.js or pChart, player map with OpenStreetMap

**Bans**
- SourceBans / SourceBans++ (Source, CS2) and AMXBans (GoldSrc), alone or merged
- Bans, comm blocks and servers with their live status
- Signed-in players see their own status, with *Report a player* and *Appeal a ban* links to your forum or Discord
- Admins kick, ban and unban from the servers list, over RCON
- No SourceBans or AMXBans website needed: bans, admins and servers are managed from HLstatsZ, which also creates their databases

**Community**
- Sign in with Steam: no account or password to create
- Discord, TeamSpeak 3 and Mumble servers, Steam Community group

**Look**
- 10 themes: Default (Steam blue), Dark, Light, Dark OLED, Matrix, Dust2 and Strike (CS2), Fortress (TF2), and the seasonal Halloween and Noel
- Pick the themes visitors can choose from, and switch a seasonal theme on for everybody: visitors can switch it off from the footer
- A simplified theme for the in-game MOTD
- Multi languages, included: English (US), French, German, Spanish (Mexico), Portuguese (Brazil), Russian, Albanian
- Plain CSS, JS and PHP, easy to customize

**Admin panel**
- **Admins sign in with Steam**: no admin password on the site to guess, share or steal
- Installer: the database, its tables and your first admin, in one step
- Database updater, daemon control, RCON console (also for servers HLstatsZ doesn't track)
- Game, server, award, rank and ban settings
- Database tools: optimize, reset, fix collations, repair double-encoded text

---

## Requirements

- The [HLstatsZ daemon](https://github.com/SnipeZilla/HLSTATS-2): the frontend shows what it records
- PHP 8.3+ with the extensions mysqli, gd (with FreeType), curl, mbstring, intl, sockets, bcmath and xml
- MySQL 8.0+ or MariaDB 10.2+
- A web server: IIS, Apache or nginx

---

## Installation & Setup

### Files
Copy the files to your web server. PHP must be able to write to `hlstatsimg/progress` and `cache/`.

### Configuration
Edit `config.php`: every setting is explained there. The ones to set first:
```php
define('DB_ADDR', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'yourpassword');
define('DB_NAME', 'hlstats');
define('SECRET_KEY', '');   // 64 random letters and digits
```
Generate your own secret key with `php -r "echo bin2hex(random_bytes(32));"`
or at https://passwords-generator.org/ (Password Length: 64, Lowercase Characters, Uppercase Characters, Numbers)

### Steam sign-in (recommended)
Admins sign in with their Steam account instead of a password. It is the safest way into the admin panel:
- The site keeps no admin password: Steam checks who signs in, with Steam Guard codes when the account uses them.
- Who is an admin is set in `config.php`, which only you can change.
- The username/password login is turned off: nothing is left to guess.
- Users can edit their profile

```php
define('STEAM_API', '');     // your Steam Web API key: https://steamcommunity.com/dev/apikey
define('STEAM_ADMIN', '');   // your SteamID64, '76561197960287930', or a list: ['7656…', '7656…']
```
Your SteamID64 is the number in the address of your Steam profile (`steamcommunity.com/profiles/7656…`); with a custom address, look it up on [steamid.io](https://steamid.io). Set both before installing: the installer then creates no password login at all.

Steam sign-in also turns on what needs to know who is who: the SourceBans admin pages, kick, ban and unban from the servers list, and the *Your status* card where players see their own bans.

> [!IMPORTANT]
> `SECRET_KEY` signs the Steam sign-in. Whoever knows it can sign in as any account, admins included: use your own, and keep it private.

### Database
Using the daemon's database? There is nothing to install: HLstatsZ opens it as it is.

For a new one, open the site: the installer takes over. It creates the database when it doesn't exist (if `DB_USER` may), then its tables and your first admin, and updates it to the latest version, in one step. To show the site is yours, it asks for the database password of `config.php`.

> [!NOTE]
> From the command line instead: `mysql -u root -p hlstats < sql/install.sql`, then *Admin › Tools › Updater*. The login is then **admin / 123456**: turn on Steam sign-in, which switches it off, or at least change the password in *Admin Users* right away.

### First visit
Sign in with Steam at the top right of the site, then click **admin** (without Steam sign-in, open `hlstats.php?mode=admin`). The first page checks PHP, its extensions and the folders, and offers to update the database to the latest version.

### Bans (optional)
Set `DB_SBNAME` (SourceBans / SourceBans++) and/or `DB_AMXNAME` (AMXBans) in `config.php`, with their table prefix (`DB_SBPREFIX`, `DB_AMXPREFIX`). Their databases must be on the same MySQL server; HLstatsZ's own database will do. Then open *Admin › Bans › Bans Settings*: when a database or its tables don't exist yet, it creates them with that prefix, and makes you the SourceBans owner.

### Web server
`web.config` (IIS) and `.htaccess` (Apache, with `AllowOverride All`) keep the private files out of reach. On nginx, add this before your PHP `location`:
```nginx
location ~ /(cache|sql|updater)/ { deny all; }
location ~ /(config\.php|_error\.txt)$ { deny all; }
```

---

## Upgrading from HLstatsX:CE

HLstatsZ uses the same database.

1. Back up the database.
2. Point `config.php` at it, open the admin panel and click **Update**.
3. Collation errors? *Admin › Tools › Reset DB Collations*.
4. Garbled accents in names (`Ã©` instead of `é`)? *Admin › Tools › Repair Double-Encoded Text*.
5. Turn on [Steam sign-in](#steam-sign-in-recommended): the old admin passwords stop working.

---

## FAQ

**Where do PHP errors go?**  
With `DEBUG` set to `true` in `config.php`, into `_error.txt` next to it.

**How do I turn on a seasonal theme (Halloween, Noel)?**  
In *Admin › HLstats Settings › Visual style settings*, switch it on under *Seasonal theme*, and off when the season is over. Every visitor sees it, with a *Seasonal theme* switch in the footer to go back to their own theme. Seasonal themes live in `styles/themes/seasonal/`; the ones in `styles/themes/` are the themes of the style selector, each switched on or off just above.

**Do I need the SourceBans or AMXBans website?**  
No. HLstatsZ shows and manages bans, admins and servers itself. You need their database, which HLstatsZ creates (*Admin › Bans › Bans Settings*), and their plugin on your game servers.

---

## Lineage

```
HLstats (Simon Garner)
  └─ HLstatsX (Tobias Oetzel)
       └─ ELstatsNEO (Malte Bayer)
            └─ HLstatsX:CE (Nicholas Hastings)
                 └─ HLstatsZ (SnipeZilla) ← now you are stuck here
```

## Credits

Based on HLstatsX:CE 1.6.19  
Maintained and modernized by [SnipeZilla](https://github.com/SnipeZilla)  
Built with Chart.js, pChart, Leaflet and OpenStreetMap. This product includes GeoLite2 data created by MaxMind, available from https://www.maxmind.com.

Support: [snipezilla.com](https://snipezilla.com) · [AlliedModders forum](https://forums.alliedmods.net/forumdisplay.php?f=156)

Licensed under the GNU General Public License v2 or later.
