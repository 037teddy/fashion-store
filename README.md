# Fashion Store (XAMPP)

Plain PHP + MySQL + JS storefront with customer accounts, a searchable catalog, size/color
variants, stock-aware carts, M-Pesa checkout, order history, and an admin inventory
screen. Product photos and the **FFmpeg slideshow video generator** are included too.

## Set up on Windows / XAMPP
1. Copy this folder to `C:\xampp\htdocs\fashion-store`.
   (If you use another folder name, change `BASE_URL` in `config/config.php`.)
2. Start **Apache** and **MySQL** in the XAMPP control panel.
3. In phpMyAdmin, import `database/schema.sql`, then `database/seed.sql` (one sample product).
   Existing installations should also run `database/delivery_settings_migration.sql`,
   `database/product_ratings_migration.sql`, `database/auth_otp_migration.sql`, and
   `database/mpesa_only_migration.sql`. Run `auth_otp_migration.sql` only once (skip it if the
   `login_otp_hash` column already exists); the M-Pesa migration preserves paid historical COD orders.
4. Check `config/config.php`: database login (XAMPP default is `root` with no password) and
   `FFMPEG_PATH` / `FFPROBE_PATH` (default `C:/ffmpeg/bin/...`). Set the mailer `SMTP_*` variables
   and `SWIFT_API_KEY` in Apache's environment before enabling email or M-Pesa checkout.
5. Create your admin account (Command Prompt, inside the project folder):
   `C:\xampp\php\php.exe tools\create_admin.php "Your Name" you@example.com 0712345678 "a-long-password"`
6. Open `http://localhost/fashion-store/admin/login.php`, sign in, choose **Variants & stock**
   on a product, and add its real SKUs, sizes/colors, prices, and stock. Products without an
   active in-stock variant cannot be purchased. Use **Photos & video** to upload media and
   generate a slideshow.
7. Open `http://localhost/fashion-store/` to shop. Customers can register, add variants to the
   session cart, pay for orders with M-Pesa, and rate products from their detail pages. After a
   successful payment, the customer receives an itemized order email with their delivery town and
   the 7-day delivery/refund promise.
   Admins can change the delivery fee from the **Delivery fee** link; each new order saves the fee
   used at checkout.

## Customer account verification
- Registration accepts a 10-digit `07...` Kenyan mobile number or a `+254...` number. Passwords
   must be at least 8 characters and include uppercase, lowercase, a number, and a symbol.
- Registration sends a 6-digit code to the new account's email before signing the customer in.
   After verification, the customer continues to the requested checkout or product page without
   signing in again.
- Customers signing in use their email or mobile number and password, then verify with a code sent
   to the account email. Existing signed-in sessions are also asked to verify before checkout or
   payment if they have not completed email verification in that session. Codes expire after 15
   minutes, with five attempts per code.
- Configure `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `SMTP_FROM`, and `SMTP_FROM_NAME`
   in the Apache environment. Set `SWIFT_API_KEY` there for M-Pesa checkout. Port 587 uses STARTTLS;
   port 465 uses implicit TLS. Restart Apache after setting the environment variables. Credentials
   are not stored in source; rotate any that have been disclosed.

Optional generator test without the website:
`C:\xampp\php\php.exe tools\test_slideshow.php photo1.jpg photo2.jpg photo3.jpg`

## If something does not work
- **"FFmpeg could not build the video"**: the message includes FFmpeg's own error. Also confirm
  `proc_open` is not listed under `disable_functions` in `C:\xampp\php\php.ini` (restart Apache after edits).
- **Uploads fail**: in `php.ini`, `extension=gd` and `extension=fileinfo` must be enabled
  (they are by default in XAMPP). `extension=exif` is optional (fixes sideways phone photos).
- **Large photos rejected**: raise `upload_max_filesize` / `post_max_size` in `php.ini`.

## How the video is made
- 2 to 8 photos (first 8 in order), 3 s each, 0.5 s crossfades, square 1080 x 1080, H.264, no audio.
- Photos are fitted inside the square over a blurred copy of themselves, never stretched.
- Total length is always under 20 s; it is verified with `ffprobe` after rendering.
- Regenerating replaces the previous generated video. If photos change, the admin page flags the video as outdated.

## Security notes
- Admin access is checked on the server for every admin request; passwords use `password_hash`.
- Customer and admin sign-ins share a database-backed throttle: five failures per email/IP pair
   lock that pair for 15 minutes. All forms use CSRF tokens and SQL uses prepared statements.
- Uploads are re-encoded and never executed. Checkout rechecks and decrements stock in a transaction.
- Checkout requires M-Pesa payment; configure the Swift Wallet settings before accepting orders.
