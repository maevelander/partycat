# 🎉 Party Cat

A free, open-source party planner: RSVPs, to-dos and shopping in one place. Try it at https://partycat.app

![Party Cat Screenshot](screenshot.png)

## ✨ Features
- **RSVP Tracker** - Mark guests Yes, No or Pending, with a live headcount
- **Smart guest counting** - Works out how many people each entry is
  - "Smith Family" → 4 people
  - "John & Jane" → 2 people
  - "Kate and Steve" → 2 people
  - "Bob, Alice, Charlie" → 3 people
- **Todo List** - Track party planning tasks
- **Shopping List** - Tick items off as you buy them
- **No account needed** - Guest mode keeps everything in the browser (`localStorage`)
- **Optional accounts** - Sync a party across devices through a small PHP + MySQL backend
- **Four themes** - Confetti, Astro Cat, Loose Thread and Calm Cat, switched with the cat button at the bottom right

## 🚀 Running It

### Guest mode only
No setup needed. Serve the folder with any web server, for example:
```bash
git clone https://github.com/maevelander/partycat.git
cd partycat
php -S localhost:8000
```
Then open http://localhost:8000

### With accounts
Accounts need PHP 8.1+ and MySQL or MariaDB:

1. Create an empty database and run `schema.sql` against it
2. Copy `api/config-example.php` to `api/config.php` and fill in:
   - database credentials
   - the app's public URL (used in password reset links)
   - SMTP settings for the mailbox that sends password reset emails
   - optionally, admin emails for the analytics dashboard and the timezone used to count visits

`api/config.php` holds secrets, so it's gitignored and `api/.htaccess` blocks web access to it. For local development you can add an `api/config.local.php` instead (same format, also gitignored); it takes precedence over `config.php`. Set `secure_cookies` to `false` when running over plain `http://`.

To host it, upload the files to any PHP host with `api/config.php` in place. The root `.htaccess` and `api/.htaccess` assume Apache or LiteSpeed; on other servers, make sure only `api/index.php` is reachable under `api/`.

## 🧩 How the Backend Works

The frontend is static HTML, CSS and JavaScript. Account features talk to `api/index.php`, a small JSON API with no framework:

| Action | Method | What it does |
|---|---|---|
| `session` | GET | Returns the logged-in user, if any |
| `signup` / `login` / `logout` | POST | Account management (signup logs straight in) |
| `forgot_password` / `reset_password` | POST | Emails a one-hour reset link (`/?reset=<token>`) and sets the new password |
| `party` / `update_party` / `clear_party` | GET / POST | The user's party, its title, and Clear Data |
| `items` / `create_item` / `update_item` / `delete_item` | GET / POST | Guests, todos and shopping items (`type` = `guests`, `todos` or `shopping_items`) |
| `visit` | POST | Records an anonymous visit (once per browser per day; guests only after they click or type) |
| `stats` | GET | Visit counts for the analytics dashboard (admin accounts only) |

Call it as `api/?action=<name>`; POST bodies are JSON.

**Security**
- Logins use an HttpOnly, SameSite=Lax session cookie backed by the `sessions` table (30 days). Passwords are hashed with PHP's `password_hash`.
- Every data query is scoped to the logged-in user's party, so users can only see and change their own data.
- POST requests must be JSON, which blocks cross-site form submissions (CSRF).
- Login, signup and password reset requests are rate limited per IP and per email address.
- Password reset emails go out over authenticated SMTP using PHPMailer, bundled in `api/lib/PHPMailer/`.

### Analytics Dashboard (optional)
List one or more accounts in `admin_emails`, then open `/analytics.html` and log in. It shows guest, account and returning users for the last 30, 60, 90 and 180 days.

The app keeps a random ID in the browser's `localStorage` and sends a `visit` when someone uses it. Guests count only after their first click or keypress, so bots and drive-by page views don't count. The server stores at most one row per browser per day in the `visits` table. No cookies or third-party services are involved. Leave `admin_emails` empty to switch the dashboard off.

## 🎨 Themes

Every colour, font and shape in `style.css` comes from CSS custom properties, and each theme in `themes/<id>/theme.css` sets them and places its own artwork. To add a theme, create `themes/<id>/` with a `theme.css` and its artwork, then add `{ id, name }` to `THEMES` in `themes/themes.js`.

## 🤝 Contributing

Bug reports and pull requests are welcome.

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This project is open source and available under the MIT License.
